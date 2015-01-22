<?php
require_once(ROOT_DIR . ENV_DIR . "Core/Database/MySQL.php");



function getTableForeignKeys($table, $mysql, $databaseName){
  $query = "
    SELECT column_name, referenced_table_name, referenced_column_name 
    FROM information_schema.key_column_usage 
    WHERE information_schema.key_column_usage.table_name=:table_name
      AND constraint_schema=:constraint_schema
      AND referenced_table_name!='NULL'
  ";

  $bindings = array(":table_name"=>$table, ":constraint_schema"=>$databaseName); 
  $rawFKs = $mysql->All($query, $bindings); 
  $fks = array(); 
  foreach($rawFKs as $rawFK) {
    $fks[$rawFK['column_name']] = $rawFK; 
  }
  return $fks;
}



function rawColumns($table, $mysql, $databaseName) {
  $query  = "
    SELECT column_name, column_comment, is_nullable, column_key, extra 
    FROM information_schema.columns
    WHERE table_name=:table_name
      AND table_schema=:table_schema
    ORDER BY ordinal_position
  "; 
  $bindings = array(":table_name"=>$table, ":table_schema"=>$databaseName);
  $columns = $mysql->All($query, $bindings);
  return $columns; 

}



function formatColumnName($rawColumn){
  $column = $rawColumn['column_name']; 
  return $column; 
}



function getTableColumnNames($rawColumns) {
  $columnNames = array();
  foreach($rawColumns as $rawColumn) {
    $columnNames[] = formatColumnName($rawColumn); 
  }
  return $columnNames; 
}



function getTableIndices($rawColumns){
  $tableIndices = array();
  foreach($rawColumns as $rawColumn) {
    if(!empty($rawColumn['column_key'])) {
      $tableIndices[] = formatColumnName($rawColumn);
    }
  }
  return $tableIndices; 
}



function getFetchableIndices($rawColumns) {
  $tableIndices = array();
  foreach($rawColumns as $rawColumn) {
    if($rawColumn['column_key'] == "PRI" OR $rawColumn['column_key'] == 'UNI') {
      $tableIndices[] = $rawColumn['column_name']; 
    }
  }
  return $tableIndices; 
}



function getTablePKs($rawColumns) {
  $tableIndices = array();
  foreach($rawColumns as $rawColumn) {
    if($rawColumn['column_key'] == "PRI") {
      $tableIndices[] = $rawColumn['column_name']; 
    }
  }
  return $tableIndices; 
}



function getNullableColumns($rawColumns) {
  $nullable = array();
  foreach($rawColumns as $rawColumn) {
    if($rawColumn['is_nullable'] == 'YES') {
      $nullable[] = $rawColumn['column_name']; 
    }
  }
  return $nullable; 
}



function isAutoIncrement($rawColumns) {
  foreach($rawColumns as $rawColumn) {
    if($rawColumn['extra'] == 'auto_increment') {
      return true; 
    }
  }
  return false; 
}



function getForeignColumns($foreignKeys, $mysql, $databaseName) {
  $scopedFKColumns = array(); 
  foreach($foreignKeys as $fk) {
    $fkRawColumns = rawColumns($fk['referenced_table_name'], $mysql, $databaseName);
    $fkColumns = getTableColumnNames($fkRawColumns); 
    foreach($fkColumns as $fkColumn) {
      $scopedFKColumns[] = $fk['referenced_table_name'].'.'.$fkColumn; 
    }
  }
  return $scopedFKColumns; 
}



function buildSchema($databaseName, $mysql) {
  $query = "SHOW tables"; 
  $rawTables = $mysql->All($query, array());
  $schema = array();
  foreach($rawTables as $raw) {
    $tableName = $raw["Tables_in_$databaseName"]; 
    $schema[$tableName] = buildTableSchema($databaseName, $mysql, $tableName);
  }
  return $schema;
}



function getFieldFromFKTableColumn($tableName, $column, $fields) {
  foreach($fields as $field) {
    $pieces = explode("//", $column);
    $column = trim($pieces[0]); 
    if(preg_match("/.*$tableName.$column/", $field) == 1) {
      return $field; 
    }
  }
}



function mapForeignColumnsToFieldNames($tableName, $fields, $columns, $foreignSchemas) {
  $tableColumnFieldMap = array();
  foreach($foreignSchemas as $fkTable => $fkSchema) {
    foreach($fkSchema['columns'] as $fkColumn) {
      $tableColumnFieldMap[$fkTable][$fkColumn] = getFieldFromFKTableColumn($fkTable, $fkColumn, $fields);  
      if(!empty($fkSchema['foreignSchemas'])){
        $tableColumnFieldMap = array_merge($tableColumnFieldMap,  mapForeignColumnsToFieldNames($fkTable, $fields, $fkSchema['columns'], $fkSchema['foreignSchemas'])); 
      }
    }
  }
  return $tableColumnFieldMap; 
}



function buildSchemaClassFilename($tableName) {
  return implode('', explode(' ', ucwords(implode(' ',explode('_',$tableName)))))."SchemaData";
}



function buildTableSchema($databaseName, $mysql, $tableName) {
  $rawColumns = rawColumns($tableName, $mysql, $databaseName);
  $columns = getTableColumnNames($rawColumns); 
  $indices = getTableIndices($rawColumns);
  $fetchableIndices = getFetchableIndices($rawColumns); 
  $primaryKeys = getTablePKs($rawColumns); 
  $nullable = getNullableColumns($rawColumns);
  $isAutoIncrement = isAutoIncrement($rawColumns);
  $foreignKeys = getTableForeignKeys($tableName, $mysql, $databaseName);
  $foreignSchemas = array(); 
  foreach($foreignKeys as $fk) {
    $foreignSchemas[$fk['referenced_table_name']] = buildTableSchema($databaseName, $mysql, $fk['referenced_table_name']);
  }
  $fkColumns = array();
  foreach($foreignSchemas as $fkTable => $fkSchema) {
    foreach($fkSchema['fields'] as $fkColumn) {
      $fkColumns[] = "$fkTable.$fkColumn";
    }
  }
  $fields = array_merge($columns, $fkColumns); 
  $columnFieldNames = mapForeignColumnsToFieldNames($tableName, $fields, $columns, $foreignSchemas);  
  $columnFieldNames[$tableName] = array();
  foreach($columns as $column) {
    $columnFieldNames[$tableName][$column] = $column; 
  }
  $schema = array(
    "schemaClass"=>buildSchemaClassFilename($tableName), 
    "tableName"=>$tableName, 
    "columns"=>$columns,
    "fields"=>$fields,
    "columnFieldNames"=>$columnFieldNames, 
    "indices"=>$indices,
    "fetchableIndices"=>$fetchableIndices, 
    "primaryKeys"=>$primaryKeys, 
    "nullable"=>$nullable,
    "isAutoIncrement"=>$isAutoIncrement,
    "foreignKeys"=>$foreignKeys,
    "foreignSchemas"=>$foreignSchemas
  );
  return $schema; 
}

// ======================================================== //
// ============== Execute Schema Generation  ============== //
// ======================================================== //
function createBaseSchemaClass($rootDir, $envDir, $generatedWarning, $isInitialized) {
  $depPath = "require_once(ROOT_DIR . ENV_DIR . 'Core/Base.php');";
  $emptySchemaVar = 'protected $schema;';
  $emptyTableNameVar = 'protected $tableName;'; 
  $getTableName = '
    public function TableName() { return $this->tableName; }
  '; 
  $getSchema = 'public function Schema() { return $this->schema; }';
  $getColumns = 'public function Columns() { return $this->schema["columns"];}';
  $getFields = 'public function Fields() { return $this->schema["fields"]; }';
  $getIndices = '
    public function Indices() {
      return $this->schema["indices"]; 
    }'; 
  
  $getFetchableIndices = ' 
    public function FetchableIndices() {
      return $this->schema["fetchableIndices"]; 
    }
  '; 

  $getPrimaryKeys = '
    public function PrimaryKeys() {
      return $this->schema["primaryKeys"]; 
    }
  '; 
  
  $getForeignKeys = '
    public function ForeignKeys() {
      return $this->schema["foreignKeys"]; 
    }
  ';
  $getForeignSchemas = '
    public function ForeignSchemas($schema=null) {
      if($schema == null){
        return $this->schema["foreignSchemas"]; 
      }
      else {
        return $this->schema["foreignSchemas"][$schema]; 
      }
    }
  ';

  $findSchema = '
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
  '; 

  $getColumnFieldNames = '
    public function ColumnFieldNames($table = null) {
      if($table == null) {
        return $this->schema["columnFieldNames"]; 
      }
      else {
        return $this->schema["columnFieldNames"][$table]; 
      }
    }
  '; 
  $getNullable = '
    public function Nullable() {
      return $this->schema["nullable"]; 
    }
  '; 
  $isAutoIncrement = '
    public function IsAutoIncrement() {
      return $this->schema["isAutoIncrement"]; 
    }
  ';

  $hasColumn = '
    public function hasColumn($column) {
      if(in_array($column, $this->Columns())){
        return true; 
      }
      return false; 
    }
  '; 
  
  $hasField = '
    public function hasField($field) {
      if(in_array($field, $this->Fields())) {
        return true; 
      }
      return false; 
    }
  '; 
  
  $schemaDataSource = "
  <?php
  $depPath
  $generatedWarning
  class SchemaData extends Base {
    $isInitialized
    
    $emptySchemaVar
    $getTableName    
    $getSchema
    $getColumns
    $getFields

    $getIndices
    $getFetchableIndices
    $getPrimaryKeys    
    $findSchema
    $getForeignKeys
    $getForeignSchemas  
    $getColumnFieldNames 
    $getNullable

    $isAutoIncrement

    $hasColumn

    $hasField
  }
  ";

  file_put_contents("{$rootDir}{$envDir}Schema/SchemaData.php", $schemaDataSource); 
}



function createSchemaClass($tableName, $tableSchema, $rootDir, $envDir, $generatedWarning, $isInitialized){
  $schemaClassName = buildSchemaClassFilename($tableName); 
  $depPath = "require_once(ROOT_DIR . ENV_DIR . 'Core/Schema/SchemaData.php');";
  $schemaSource = var_export($tableSchema, true);
  $schemaVar = 'protected $schema = ' . "$schemaSource;"; 
  $tableNameVar = 'protected $tableName = ' . "'$tableName';";
  $schemaDataSource = "
  <?php 
  $depPath
  $generatedWarning
  class $schemaClassName extends SchemaData {
    $tableNameVar
    
    $schemaVar
  }
  "; 
  file_put_contents("{$rootDir}{$envDir}Schema/$schemaClassName.php", $schemaDataSource); 
}

$mysql =  new MySQL();
$mysql->initialize(DB_HOST,DB_USER,DB_PASS,DB_NAME);

$rootDir = ROOT_DIR;
$envDir = ENV_DIR;
$generatedWarning = "
// !!! GENERATED CLASS !!! //

// Changes made to this file will likey be stomped the next time 
// the generation script will be run. Edit the buildSchema.php script instead!

// This object is a representation of the 
// database schema in application code, intended for use
// by the model objects. Generating it creates an 
// accurate representation of the database without 
// needing the programmer to go in and rewrite a
// bunch of boilerplate code. 
"; 

$isInitialized = '
  // Since the file is generated with all the data it needs, we\'ll 
  // set it to be automatically initialized
  protected $isInitialized = true;';  

$schema = buildSchema(DB_NAME, $mysql);

createBaseSchemaClass($rootDir, $envDir, $generatedWarning, $isInitialized); 
foreach($schema as $tableName=>$tableSchema) {
  createSchemaClass($tableName, $tableSchema, $rootDir, $envDir, $generatedWarning, $isInitialized); 
}
