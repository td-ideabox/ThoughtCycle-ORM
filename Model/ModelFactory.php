<?php
require_once(ROOT_DIR . ENV_DIR . "Core/Factory/Factory.php");
class ModelFactory extends Factory{



  public function Create($modelClass, array $recordData=array()){
    $model = $this->newModel($modelClass); 
    $model->setMany($recordData); 
    return $model;
  }



  protected $schemas = array();
  public function GetSchema($schemaClass) {
    if(!is_subclass_of($schemaClass, "SchemaData")){
      throw new Exception("$schemaClass is not a subclass of SchemaData!"); 
    }
    if(!array_key_exists($schemaClass, $this->schemas)){
      $this->schemas[$schemaClass] = new $schemaClass(); 
    }
    return $this->schemas[$schemaClass]; 
  }
  


  public function BuildExisting($modelClass, array $fieldsAndValues) {
    $model = $this->newModel($modelClass); 
    $model->BuildExisting($fieldsAndValues);
    return $model; 
  }



  public function LoadExisting($modelClass, array $indicesWithValues){
    $model = $this->newModel($modelClass); 
    $loadedSuccessfully = $model->Load($indicesWithValues); 
    if($loadedSuccessfully == false) {
      return null; 
    }
    else {
      return $model; 
    }
  }



  public function DeleteExisting($modelClass, array $indicesWithValues) {
    $model = $this->LoadExisting($modelClass, $indicesWithValues); 
    return $model->Delete(); 
  }



  public function Collection($collectionClass, array $models = array()){
    $collection = new $collectionClass($this, $models);
    return $collection; 
  }



  protected function newModel($modelClass) {
    if(is_subclass_of($modelClass, 'MySQLModel') != true){
      throw new Exception("$modelClass is not a subclass of MySQLModel"); 
    }
    $model = new $modelClass(); 
    $model->initialize($this); 
    return $model; 
  }
}
