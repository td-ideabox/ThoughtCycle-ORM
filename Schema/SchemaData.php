
  <?php
  require_once(ROOT_DIR . ENV_DIR . 'Core/Base.php');
  
// !!! GENERATED CLASS !!! //

// Changes made to this file will likey be stomped the next time 
// the generation script will be run. Edit the buildSchema.php script instead!

// This object is a representation of the 
// database schema in application code, intended for use
// by the model objects. Generating it creates an 
// accurate representation of the database without 
// needing the programmer to go in and rewrite a
// bunch of boilerplate code. 

  class SchemaData extends Base {
    
  // Since the file is generated with all the data it needs, we'll 
  // set it to be automatically initialized
  protected $isInitialized = true;
    
    protected $schema;
    
    public function TableName() { return $this->tableName; }
      
    public function Schema() { return $this->schema; }
    public function Columns() { return $this->schema["columns"];}
    public function Fields() { return $this->schema["fields"]; }

    
    public function Indices() {
      return $this->schema["indices"]; 
    }
     
    public function FetchableIndices() {
      return $this->schema["fetchableIndices"]; 
    }
  
    
    public function PrimaryKeys() {
      return $this->schema["primaryKeys"]; 
    }
      
    
  public function FindSchema(ModelField $field){
    $tables = $field->FKTables();
    if(empty($tables)) {return $this->schema;}
    $currentSchema = $this->schema; 
    while(!empty($tables)){
      $table = array_shift($tables);
      $currentSchema = $currentSchema["foreignSchemas"][$table]; 
    }
    return $currentSchema; 
  }
  
    
    public function ForeignKeys() {
      return $this->schema["foreignKeys"]; 
    }
  
    
    public function ForeignSchemas($schema=null) {
      if($schema == null){
        return $this->schema["foreignSchemas"]; 
      }
      else {
        return $this->schema["foreignSchemas"][$schema]; 
      }
    }
    
    
    public function ColumnFieldNames($table = null) {
      if($table == null) {
        return $this->schema["columnFieldNames"]; 
      }
      else {
        return $this->schema["columnFieldNames"][$table]; 
      }
    }
   
    
    public function Nullable() {
      return $this->schema["nullable"]; 
    }
  

    
    public function IsAutoIncrement() {
      return $this->schema["isAutoIncrement"]; 
    }
  

    
    public function hasColumn($column) {
      if(in_array($column, $this->Columns())){
        return true; 
      }
      return false; 
    }
  

    
    public function hasField($field) {
      if(in_array($field, $this->Fields())) {
        return true; 
      }
      return false; 
    }
  
  }
  