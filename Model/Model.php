<?php
require_once(ROOT_DIR . ENV_DIR . "Core/Base.php"); 
require_once(ROOT_DIR . ENV_DIR . "Core/Validation/ModelField.php"); 
require_once(ROOT_DIR . ENV_DIR . "Core/Collection/ModelFieldCollection.php");
abstract class Model extends Base{
  protected $db;
  protected $schemaClass; 
  protected $schema; 
  public function Schema() { return $this->schema; } 
  protected $modelFactory;
  protected $columns;
  protected $modifiedFields = array(); 
  public function ModifiedFields() { return $this->modifiedFields; } 
  protected $syncedFields = array(); 
  protected $fieldValues;

  abstract public function Delete(); 



  public function Reset() {
    $this->modifiedFields = array();
    $this->syncedFields = array();
    $this->fieldValues = array(); 
  }



  public function ToArray($includeTables=false){
    if($includeTables == true){
      return $this->fieldValues();
    }
    else {
      $fields = array();
      foreach($this->fieldValues as $key => $field) {
        $column = array_pop(explode('.', $key));
        $fields[$column] = $field; 
      }
      return $fields; 
    }
  }



  public function ToJSON($includeTables=false) {
    return json_encode($this->fieldValues($includeTables));
  }



  protected function areKeysSet($keys) {
    foreach($keys as $key) {
      if(!$this->isFieldSet($key)) {
        return false; 
      }
    }
    return true; 
  }



  // We can't fetch if we don't have any synced fetchable keys
  // to query with
  public function isFetchable() {
    $syncedFetchableKeyValues = $this->getSyncedFetchableKeyValues();  
    return !empty($syncedFetchableKeyValues); 
  }



  // These are the keys we can use to find this model in the db.
  // In order to Fetch at least one must be set. 
  public function getSyncedFetchableKeyValues(){
    $keyValue = array(); 
    $fetchableIndices = $this->schema->FetchableIndices(); 
    foreach($fetchableIndices as $key){
      $field = new ModelField($key); 
      if($this->isFieldSet($field) AND $this->isFieldSynced($field)){
        $keyValue[$key] = $this->fieldValues[$key]; 
      }
    }
    return $keyValue; 
  }



  // These include primary keys, uniques, and other indices. Not used
  // to determine if fetchability is possible. Fetchable keys are a subset of 
  // all the indices, so use these when you're actually retrieving the model, but 
  // you can't retrieve the model unless a fetchable index has been set
  public function getSyncedIndiceKeyValues() {
    $keyValue = array(); 
    $indices = $this->schema->Indices(); 
    foreach($indices as $key){
      $field = new ModelField($key); 
      if($this->isFieldSet($field) AND $this->isFieldSynced($field)){
        if($this->fieldValues[$key] != null) {
          $keyValue[$key] = $this->fieldValues[$key];
        }
      }
    }
    return $keyValue; 
  }



  public function FieldValues(){
    return $this->fieldValues; 
  }
  


  public function initialize(ModelFactory $modelFactory) {
    $this->modelFactory = $modelFactory;
    $this->schema = $this->modelFactory->GetSchema($this->schemaClass); 
  }



  public function get($field) {
    $modelField = new ModelField($field);
    if(!$this->schema->hasField($modelField->Value())) {
      throw new Exception($modelField->Value() . " does not exist in model's fields"); 
    }
    if($this->isFieldSet($modelField) == false AND $this->isFetchable() == true) {
      $this->Fetch($modelField);
    }
    if(array_key_exists($modelField->Value(), $this->fieldValues)){
      return $this->fieldValues[$modelField->Value()]; 
    }
    else return null; 
  }



  protected function isFieldSet(ModelField $field) {
    return array_key_exists($field->Value(), $this->fieldValues); 
  }



  protected function isFieldModified(ModelField $field) {
    return array_key_exists($field->Value(), $this->modifiedFields); 
  }



  protected function isFieldSynced(ModelField $field) {
    return array_key_exists($field->Value(), $this->syncedFields); 
  }



  public function set($field, $value){
    if(!$this->schema->hasField($field)){
      throw new Exception( "$field is not one of this model's allowed columns. Cannot insert data"); 
    }
    $modelField = new ModelField($field);
    $this->setFieldToModified($modelField); 
    $this->fieldValues[$modelField->Value()] = $value; 
  }



  protected function setFieldToModified(ModelField $field) {
    $this->modifiedFields[$field->Value()] = $field->Value(); // using a hashkey lets us do some constant time looks ups, having the same value lets us iterate intuitively
  }



  protected function resetModifiedFieldFlag(ModelField $field) {
    if(array_key_exists($field->Value(), $this->modifiedFields)){
      unset($this->modifiedFields[$field->Value()]); 
    }
  }



  protected function resetModifiedFieldFlags(array $fields){
    foreach($fields as $field) {
      if(is_string($field)) {
        $field = new ModelField($field);
      }
      $this->resetModifiedFieldFlag($field);
    }
  }



  protected function resetAllModifiedFieldFlags() {
    $this->modifiedFields = array(); 
  }



  public function setMany(array $data){
    foreach($data as $k => $v){
      $this->set($k, $v); 
    }
  }



  public function Load(array $indexFieldsAndValues) {
    if($this->isModified()) {
      throw new Exception("Cannot load a Model that has been modified"); 
    }
    $this->setMany($indexFieldsAndValues); 
    $this->setFieldsToSynced(array_keys($indexFieldsAndValues)); 
    $this->resetAllModifiedFieldFlags();
    return $this->Fetch(new ModelField(end(array_keys($indexFieldsAndValues)))); 
  }



  public function BuildExisting(array $fieldsAndValues) {
    if($this->isModified()) {
      throw new Exception("Cannot build a Model that has been modified"); 
    }
    $this->setMany($fieldsAndValues); 
    $this->setFieldsToSynced(array_keys($fieldsAndValues)); 
    $this->resetAllModifiedFieldFlags();
    $syncedIndices = $this->getSyncedIndiceKeyValues();
    if(empty($syncedIndices)){
      throw new Exception("Cannot assume Model is synced without synced indices"); 
    }
  }



  public function isSynced() {
    return (!empty($this->syncedFields)); 
  }



  public function isModified() {
    if(empty($this->syncedFields) AND empty($this->modifiedFields) AND empty($this->fieldValues)){
      return false;
    }
    else {
      return true; 
    }
  }



  public function Sync($force=false){
    $this->executeSync();
    $this->setFieldsToSynced($this->modifiedFields);
    $this->resetModifiedFieldFlags($this->modifiedFields); 
  }



  abstract protected function executeSync(); 



  protected function setFieldsToSynced(array $fields) {
    foreach($fields as $field) {
      $this->setFieldToSynced($field); 
    }
  }



  protected function setFieldToSynced($field) {
    if($this->schema->hasField($field)) {
      $this->syncedFields[$field] = $field;
    }
    else {
      throw new Exception("Schema does not have $field"); 
    }
  }



  // First check that we have some indices to fetch with then get the data.
  // SHOULD NOT be used to retrieve a field, use get()
  public function Fetch(ModelField $field, $force=false){
    if(!$this->isFetchable()){
      throw new Exception("We can't fetch model data with out at least one synced, fetchable index being set"); 
    }
    $fetchedFields = $this->executeFetch($field);
    if($fetchedFields == false) { return false; }
    else { 
      $this->processFetchedFields($fetchedFields); 
      return true; 
    }
  }



  protected function getFieldsToFetch(ModelField $field, $force=false){
    $ignoredFields = array_keys($this->syncedFields);  // Already in sync with the db
    if($force != true) {
      // Not forcing it, so we can ignore fields that have been changed. If we did force it, we would overwrite these values
      $ignoredFields = array_merge($ignoredFields, array_keys($this->modifiedFields));
    }
    $foreignTables = $this->schema->ForeignSchemas();
    $baseColumns = $this->schema->Columns();
    if($field->IsForeign()) {
      $foreignFields = $this->getFKColumnsFromField($field);
      $fieldsToFetch = array_merge($baseColumns, $foreignFields);
    }
    else {
      $fieldsToFetch = $baseColumns; 
    }
    $fieldsToFetch = array_diff($fieldsToFetch, $ignoredFields);
    return $fieldsToFetch; 
  }



  protected function getFKColumnsFromField(ModelField $field) {
    if($this->fieldIsFromForeignTable($field)){
      return array(); 
    }
    $fkSchema = $this->getForeignFieldsTableSchema($field);
    $fkColumns = $fkSchema['columns'];
    return $fkColumns; 
  }



  protected function getForeignFieldsTableSchema($field) {
    $fieldName = $this->getFKFieldName($field);
    $fks = $this->schema->ForeignKeys();
    $fk = $fks[$fieldName]; 
    $fkTableName = $fk['referenced_table_name']; 
    $foreignSchemas = $this->schema->foreignSchemas();
    return $foreignSchemas[$fkTableName]; 
  }



  protected function getFKFieldName($field) {
    return array_pop(explode('.', $field));
  }



  abstract protected function executeFetch(ModelField $field);



  protected function processFetchedFields(array $fetchedFields) {
    // set values unless they have been previously modified
    foreach($fetchedFields as $field => $value) {
      if(!array_key_exists($field, $this->modifiedFields)){
        $this->fieldValues[$field] = $value; 
        $this->setFieldToSynced($field);
        $this->resetModifiedFieldFlag(new ModelField($field)); 
      }
    }
  }
}
