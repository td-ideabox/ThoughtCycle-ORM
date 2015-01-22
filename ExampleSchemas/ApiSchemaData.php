
  <?php 
  require_once(ROOT_DIR . ENV_DIR . 'Core/Schema/SchemaData.php');
  
// !!! GENERATED CLASS !!! //

// Changes made to this file will likey be stomped the next time 
// the generation script will be run. Edit the buildSchema.php script instead!

// This object is a representation of the 
// database schema in application code, intended for use
// by the model objects. Generating it creates an 
// accurate representation of the database without 
// needing the programmer to go in and rewrite a
// bunch of boilerplate code. 

  class ApiSchemaData extends SchemaData {
    protected $tableName = 'api';
    
    protected $schema = array (
  'schemaClass' => 'ApiSchemaData',
  'tableName' => 'api',
  'columns' => 
  array (
    0 => 'api_id',
    1 => 'secret',
    2 => 'ns_api_key',
    3 => 'host',
    4 => 'metrics_endpoint',
    5 => 'transaction_endpoint',
  ),
  'fields' => 
  array (
    0 => 'api_id',
    1 => 'secret',
    2 => 'ns_api_key',
    3 => 'host',
    4 => 'metrics_endpoint',
    5 => 'transaction_endpoint',
  ),
  'columnFieldNames' => 
  array (
    'api' => 
    array (
      'api_id' => 'api_id',
      'secret' => 'secret',
      'ns_api_key' => 'ns_api_key',
      'host' => 'host',
      'metrics_endpoint' => 'metrics_endpoint',
      'transaction_endpoint' => 'transaction_endpoint',
    ),
  ),
  'indices' => 
  array (
    0 => 'api_id',
    1 => 'ns_api_key',
  ),
  'fetchableIndices' => 
  array (
    0 => 'api_id',
    1 => 'ns_api_key',
  ),
  'primaryKeys' => 
  array (
    0 => 'api_id',
  ),
  'nullable' => 
  array (
    0 => 'host',
    1 => 'metrics_endpoint',
    2 => 'transaction_endpoint',
  ),
  'isAutoIncrement' => true,
  'foreignKeys' => 
  array (
  ),
  'foreignSchemas' => 
  array (
  ),
);
  }
  