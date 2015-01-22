<?php
require_once(ROOT_DIR . ENV_DIR . "Core/Model/Model.php");   
abstract class MySQLModel extends Model{


  public function initialize(MySQLModelFactory $modelFactory){
    parent::initialize($modelFactory); 
    $this->mysql = $modelFactory->MySQL(); 
  }


  public function Delete() {
    $tableName = $this->schema->TableName(); 
    $whereClause = $this->buildModelWhereClause();
    $query = "
      DELETE FROM $tableName 
      $whereClause 
    ";
    $bindings = $this->buildModelWhereBindings(); 
    $this->mysql->query($query, $bindings); 
    $this->Reset(); 
    return true; 
  }



  public function buildModelWhereClause() {
    $indices = $this->getSyncedIndiceKeyValues();
    if(empty($indices)) {
      throw new Exception("No indices are set!"); 
    }
    $clauseConstraints = array();
    $tableName = $this->schema->TableName(); 
    foreach($indices as $key => $value) {
      $clauseConstraints[] = $tableName.".$key=:$key"; 
    }
    $clause = "WHERE " . implode(" AND ", $clauseConstraints); 
     
    return $clause; 
  }



  public function buildModelWhereBindings() {
    $indices = $this->getSyncedIndiceKeyValues();
    if(empty($indices)) {
      throw new Exception("No indices are set!"); 
    }
    $bindings = array();
    foreach($indices as $key => $value) {
      $bindings[":$key"] = $value; 
    }
    return $bindings; 
  }



  public function getIndiceValues() {
    $indices = $this->schema->Indices();
    $indexKeyValue = array(); 
    foreach($indices as $index) {
      if($this->isFieldSet(new ModelField($index))){
        $value = $this->get($index);
        if($value != null) {
          $indexKeyValue[$index] = $value;  
        }
      }
    }
    return $indexKeyValue; 
  }



  protected function primaryKeyValuesInInitialFields(array $fieldValues) {
    $fields = array_keys($fieldValues);
    $indices = $this->schema->Indices();
    return array_diff($fields, $indices) == 0; 
  }


  protected function executeFetch(ModelField $field) {
    $selectClause = $this->buildFetchSelectClause($field);
    $fromClause = $this->buildFetchFromClause($field);
    $whereClause = $this->buildModelWhereClause();
    $query = "
      SELECT $selectClause
      FROM $fromClause
      $whereClause
    ";
    $indiceValues = $this->getIndiceValues();
    if(empty($indiceValues)){
      throw new Exception("No indices are set, so we cannot fetch data!"); 
    }
    $bindings = $this->buildModelWhereBindings(); 
    $record = $this->mysql->Row($query, $bindings);
    
    if($record == false) { 
      return false; 
    }
    else {
      $fields = $this->processSelectedFields($record); 
      return $fields; 
    }
  }



  protected function buildFetchSelectClause(ModelField $field) {
    $safeFields = $this->getSQLSafeFieldNames($field);
    $select = implode(', ', $safeFields);
    return $select; 
  }



  protected function getSQLSafeFieldNames(ModelField $field) {
    $fields = $this->getTableFieldsFromField($field);  
    $sqlSafe = array();
    foreach($fields as $field) {
      $sqlSafe[] = $field->getSqlSelectName(); 
    }
    return $sqlSafe; 
  }



  protected function getTableFieldsFromField(ModelField $field) {
    if($field->IsForeign()) {
      $otherSchema = $this->schema->FindSchema($field);
      $tableName = $otherSchema['tableName']; 
    }
    else {
      $tableName = $this->schema->TableName(); 
      $otherSchema = $this->schema->Schema();
    }
    // Get column names from (potentially) foreign schema
    $columns = $otherSchema['columns']; 
    $fields = array();
    // Get fields names of foreign column in terms of this schema's field names
    $columnFieldNames = $this->schema->ColumnFieldNames($tableName);
    foreach($columns as $k=> $column) {
      $fields[] = new ModelField($columnFieldNames[$column]); 
    }
    return $fields; 
  }



  protected function processSelectedFields(array $record) {
    $fields = array();
    $fkFields = $this->schema->ForeignKeys();
    foreach($record as $key=>$value){
      $fieldKey = str_replace("___", ".", $key);  
      $fields[$fieldKey] = $value; 
      if(array_key_exists($fieldKey, $fkFields)){
        $fields[$fkFields[$fieldKey]['referenced_table_name'].".$fieldKey"] = $value; 
      }
    }
    return $fields; 
  }



  protected function buildFetchFromClause(ModelField $field) {
    $tableName = $this->schema->TableName(); 
    if($field->IsForeign()) {
      $joinClause = $this->buildFetchJoinClause($field);
      return "$tableName $joinClause"; 
    }
    else {
      return $tableName;
    }
  }



  protected function buildFetchJoinClause(ModelField $field){
    $fkRelationships = $this->buildFKRelationships($field);
    $joinClause = ""; 
    foreach($fkRelationships as $relationship) {
      $joinClause .= " JOIN " . $relationship['refTable'] ." ON  ".$relationship['srcTable'].".".$relationship['srcColumnName']."=".$relationship['refTable'].".".$relationship['refColumnName'];
    }
    return $joinClause; 
  }



  protected function buildFKRelationships(ModelField $field){
    $curSchema = $this->schema->Schema();
    $curTable = $this->schema->TableName();
    $relationships = array(); 
    $fkTables = $field->FKTables(); 
    while(!empty( $fkTables)) {
      $fkTable = array_shift($fkTables); 
      $foreignKeys = $curSchema['foreignKeys'];
      if(empty($foreignKeys)){
        break;
      }
      foreach($foreignKeys as $fk) {
        // Find the table this foreign key maps to
        if($fk['referenced_table_name'] == $fkTable){
          $fkColumnName = $fk['column_name'];
          $fkRefColumnName = $fk['referenced_column_name']; 
          $fkSchema = $curSchema['foreignSchemas'][$fk['referenced_table_name']]; 
          $relationships[] = array(
            "srcTable"=>$curTable, 
            "refTable"=>$fkTable, 
            "srcColumnName"=>$fkColumnName, 
            "refColumnName"=>$fkRefColumnName,
            "srcSchema"=>$curSchema,
            "refSchema"=>$fkSchema
          ); 
          $curSchema = $fkSchema;
          $curTable = $fkTable;
          
          break;
        }
      }
    }
    return $relationships;
  }



  protected function executeSync() {
    // Rare case where this model is a table with all defaults and auto_incr id. Such a table is never 
    // modified, so misses normal insert logic. Since it is never modified, it is never updated, and does
    // not need to go through the same logic as normal syncs. 
    if($this->simpleInsert() ) { return; } 
    else { 
      $fieldSyncOperations = $this->determineFieldSyncOps();
      if(!empty($fieldSyncOperations['fieldsToInsert'])){
        $this->executeInserts($fieldSyncOperations['fieldsToInsert']);
      }
      else if(!empty($fieldSyncOperations['fieldsToUpdate'])) {
        $this->executeUpdates($fieldSyncOperations['fieldsToUpdate']); 
      }
    }
  }



  protected function simpleInsert() {
    if( empty($this->modifiedFields) AND 
        empty($this->syncedFields)   AND 
        empty($this->fieldValues)    AND
        $this->schema->isAutoIncrement() == true) 
    {
      $query = "INSERT INTO " . $this->schema->TableName() . " () VALUES ()"; 
      $this->mysql->query($query, array());
      $id = $this->mysql->getInsertId();
      $primaryKey = $this->schema->PrimaryKeys();
      $primaryKey = $primaryKey[0];
      $this->set($primaryKey, $id);
      $this->setFieldsToSynced($this->modifiedFields);
      return true; 
    }
    else {
      return false; 
    }
  }



  protected function determineFieldSyncOps() {
    $evaluatedSchemas = array(); 
    $pksToTest = array(); 
    $updateFields = array();
    $insertFields = array();
    foreach($this->modifiedFields as $field => $v) {
      $table = $this->getTableFromField($field); 
      if(array_key_exists($table, $evaluatedSchemas)){
        $schema = $evaluatedSchemas[$table]; 
      }
      else {
        $schema = $this->schema->FindSchema(new ModelField($field));
        $evaluatedSchemas[$schema['tableName']] = $schema; 
      }
      $pks = $schema['primaryKeys'];
      $columnFieldNames = $this->schema->ColumnFieldNames($schema['tableName']);
      $pkField = $columnFieldNames[current($pks)];
      if($this->get($pkField) != null && !array_key_exists($pkField, $this->modifiedFields)) {
          $updateFields[] = $field; 
      }
      else {
          $insertFields[] = $field; 
      }
    }
   
    return array("fieldsToInsert"=>$insertFields, "fieldsToUpdate"=>$updateFields); 
  }



  protected function executeInserts(array $fieldsToInsert){
    $tableInsertOrder = $this->computeInsertOrder($fieldsToInsert);
    $foreignKeyData = array();
    foreach($tableInsertOrder as $tableName => $tableData){  
      $currentSchema = $tableData['schema'];
      
      // The previous table created a potential foreign key dependency
      if(!empty($foreignKeyData)){
        if(array_key_exists($foreignKeyData['fkColumn'], $currentSchema['foreignKeys'])){
          $fkTableName = $currentSchema['foreignKeys'][$foreignKeyData['fkColumn']]['referenced_table_name'];
          $fkColumnName = $currentSchema['foreignKeys'][$foreignKeyData['fkColumn']]['referenced_column_name'];
          $tableData['columnFieldNames'][$fkColumnName] = $fkColumnName;
        }
        $foreignKeyData = array(); // reset whether used or not. The dependency graph is linear, so if it wasn't used above then it won't be used at all.  
      }
      $clause = $this->buildInsertClause($tableName, $tableData['columnFieldNames']); 
      $query = $clause['query'];
      $bindings = $clause['bindings'];
      $this->mysql->query($query,$bindings);
      if($currentSchema['isAutoIncrement'] == true) {
        $primaryKey = $currentSchema['indices'][0]; 
        $primaryKeyValue = $this->mysql->getInsertId();
        $this->set($primaryKey, $primaryKeyValue);
        $foreignKeyData = array("fkColumn"=>$primaryKey, "fkValue"=>$primaryKeyValue); 
      }
      $this->setFieldsToSynced(array_values($tableData['columnFieldNames'])); 
      
    }
  }



  protected function computeInsertOrder(array $fieldsToInsert) {
    $tableInsertOrder = array(); 
    foreach($fieldsToInsert as $field) {
      $field = new ModelField($field); 
      $fkTables = $field->FKTables(); 
      if(!empty($fkTables)) {
        foreach($fkTables as $table) {
          if(!array_key_exists($table, $tableInsertOrder)){
            $tableInsertOrder[$table] = array("schema" => $this->schema->FindSchema($field), "columnFieldNames"=>array());          
          }
          $tableInsertOrder[$table]['columnFieldNames'][$field->Column()] = $field->Value(); 
        }
      }
      else {
        if(!array_key_exists($this->schema->TableName(), $tableInsertOrder)){
          $tableInsertOrder[$this->schema->TableName()] = array("schema" => $this->schema->Schema(), "columnFieldNames"=>array());
        }
        $tableInsertOrder[$this->schema->TableName()]['columnFieldNames'][$field->Column()] = $field->Value(); 
      }
    }
    return array_reverse($tableInsertOrder); 
  }



  protected function buildInsertClause($tableName, array $tableColumnFieldNames){
    $columns = array();     
    $bindingNames = array();
    $bindings = array();
    
    foreach($tableColumnFieldNames  as $column => $fieldName) {
      $columns[] = $column;
      $bindingNames[] = ":$column"; 
      $bindings[":$column"] = $this->fieldValues[$fieldName]; 
    }
    $columns = implode(",", $columns); 
    $bindingNames = implode(",", $bindingNames); 
    $query = "
      INSERT INTO $tableName
      ($columns)
      VALUES
      ($bindingNames)
    ";
    return array("query"=>$query, "bindings"=>$bindings); 
  }



  protected function getTableFromField($field){
    $tablesWithColumn = explode('.',$field);
    if(count($tablesWithColumn) == 1) {
      $table = $this->schema->TableName(); 
    }
    else {
      $column = array_pop($tablesWithColumn);
      $table = array_pop($tablesWithColumn);
    }
    return $table; 
  }



  protected function removeSyncedTables($modifiedTables, $syncedFields){
    foreach($syncedFields as $field) {
      $table = $this->getTableFromField($field); 
      if(array_key_exists($table, $modifiedTables)){
        unset($modifiedTables[$table]); 
      }
    }
    return array_keys($modifiedTables); 
  }



  protected function shouldInsertTable($tableName) {
    $columnFieldNames = $this->schema->ColumnFieldNames($tableName);
    $field = current($columnFieldNames);
    $schema = $this->schema->FindSchema(new ModelField($field)); //$this->getTableSchema($tableName);
    $indices = $schema['indices'];
    // It's an auto incr table, so if it's index is set that means it already exists
    if($schema['isAutoIncrement'] == true) {
      foreach($indices as $index) {
        $indexFieldName = $columnFieldNames[$index]; 
        if(!isset($this->fieldValues[$indexFieldName])) {
          return true; 
        }
      }
    }
    else {  // It's a natural index table, so if we don't have all the indices, we can't insert so it must be an update
      $shouldInsert = true; 
      foreach($indices as $index) {
        $indexFieldName = $columnFieldNames[$index]; 
        if(!isset($this->fieldValues[$indexFieldName])) {
          $shouldInsert = false;
          break; 
        }
        if(array_key_exists($indexFieldName, $this->syncedFields)){
          $shouldInsert = false; 
          break; 
        }
      }
      return $shouldInsert; 
    }
    return false; 
  }



  protected function getTablesInsertData(array $insertTables, array $modifiedFields){
    $tables = array(); 
    foreach($insertTables as $table) {
      $tableInsertData = array(
        "table"=>$table,
        "autoIncr" => $this->isTableAutoIncr($table)
      );
      $tables[] = $tableInsertData;   
    }
    return $tables; 
  }



  protected function getTableSchema($tableName) {
    if($tableName == $this->schema->TableName()) {
      return $this->schema->Schema();
    }
    else {
      $schemas = $this->schema->ForeignSchemas();
      return $schemas[$tableName]; 
    }
  }



  protected function getTableModifiedColumns(array $modifiedFields){
    $schema = $this->schema->FindSchema(new ModelField(current($modifiedFields)));//$this->getTableSchema($tableName); 
    $columns = $schema['columns']; 
    $columnFieldNames = $this->schema->ColumnFieldNames($schema['tableName']);
    $modifiedColumns = array();
    foreach($columns as $column) {
      foreach($modifiedFields as $field) {
        if($columnFieldNames[$column] == $field) {
          $modifiedColumns[$column] = array("field"=>$field, "value"=>$this->fieldValues[$field]);
        }
      }
    }
    return $modifiedColumns; 
  }



  protected function isTableAutoIncr($table) {
    $schema = $this->getTableSchema($table);
    return $schema['isAutoIncrement'];  
  }



  protected function tableNeedsInserted(array $syncableTable) {
    if(empty($syncableTable['indices'])) {
      return true; 
    }
    foreach($syncableTable['indices'] as $index) {
      if($index == null) {
        return true; 
      }
    }
    return false; 
  }



  protected function formatColumnDataForInsert(array $modifiedColumns) {
    $columnData = array();
    foreach($modifiedColumns as $column => $data) {
      $columnData[$column] = $data['value']; 
    }
    return $columnData; 
  }



  protected function setTablePrimaryKey($primaryKey, $primaryKeyValue, $tableName){
    $columnFieldNames = $this->schema->ColumnFieldNames($tableName);
    $this->fieldValues[$columnFieldNames[$primaryKey]] = $primaryKeyValue; 
  }
 


  protected function updateForeignKeys($foreignKey, $foreignKeyValue, $tableName){
    $schema = $this->getTableSchema($tableName);
    if(isset($foreignKey, $schema['foreignKeys'])) {
      $fk = $schema['foreignKeys'][$foreignKey]; 
      $fieldName = $schema['columnFieldNames'][$tableName][$foreignKey];  
      $this->set($fieldName, $foreignKeyValue); 
    }
  }
 


  protected function executeUpdates($fieldsToUpdate){
    if(empty($fieldsToUpdate)) { return; }
    $fieldUpdates = new ModelFieldCollection($fieldsToUpdate); 
    $updatedTables = $this->computeUpdatedTables($fieldUpdates); 
    foreach($updatedTables as $table => $fields) {
      $setClause = $this->buildSetClause($fields); 
      $whereData = $this->buildUpdateWhereData($fields);
      $whereClause = $whereData['clause']; 
      $query = "
        UPDATE $table
        SET $setClause
        WHERE $whereClause
      ";
      $updateBindings = $this->buildUpdateValueBindings($fields); 
      $bindings = array_merge($whereData['bindings'], $updateBindings); 
      $this->mysql->query($query, $bindings); 
    }
  }



  protected function computeUpdatedTables(ModelFieldCollection $fieldsToUpdate) {
    $tables = array();
    foreach($fieldsToUpdate as $field){
      $table = $field->FKTableName(); 
      $table = ($table == null) ? $this->schema->TableName() : $table; 
      if(!array_key_exists($table, $tables)){
        $tables[$table] = new ModelFieldCollection();
      }
      $tables[$table]->Add($field); 
    }
    return $tables; 
  }



  protected function buildSetClause(ModelFieldCollection $fieldsToUpdate) {
    $setClause = array();
    foreach($fieldsToUpdate as $field) {
      $setClause[] = "{$field->getSQLName()}=:{$field->getSQLSafeFieldName()}";
    }
    return implode(',',$setClause); 
  }



  protected function buildUpdateWhereData(ModelFieldCollection $fields) {
    // The fields should be from the same schema, so we only need to get the schema of the first one
    $fields = $fields->ToArray();  // @todo bug with current() not recognizing $this->position
    $otherSchema = $this->schema->FindSchema(current($fields));

    // We need to get the tables primary keys to do the update
    $pks = $otherSchema['primaryKeys'];
    $pkFieldNames = $this->schema->ColumnFieldNames($otherSchema['tableName']);  
    $pkFieldClause = array(); 
    $pkFieldBindings = array(); 
    foreach($pks as $pk) {
      $pkFieldName = new ModelField($pkFieldNames[$pk]); 
      $pkFieldClause[] = "{$pkFieldName->Column()}=:{$pkFieldName->getSQLSafeFieldName()}";
      $pkFieldBindings[":{$pkFieldName->getSQLSafeFieldName()}"] = $this->get($pkFieldName->Value());
    }
    $whereClause = implode(" AND ", $pkFieldClause);
    return array("clause"=>$whereClause, "bindings"=>$pkFieldBindings); 
  }



  protected function buildUpdateValueBindings($fieldsToUpdate) {
    $bindings = array();
    foreach($fieldsToUpdate as $field) {
      $bindingName = ":{$field->getSQLSafeFieldName()}"; 
      $bindings[$bindingName] = $this->fieldValues[$field->Value()];
    }
    return $bindings; 
  }
}
