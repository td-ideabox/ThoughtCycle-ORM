
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

  class PurchaseSchemaData extends SchemaData {
    protected $tableName = 'purchase';
    
    protected $schema = array (
  'schemaClass' => 'PurchaseSchemaData',
  'tableName' => 'purchase',
  'columns' => 
  array (
    0 => 'purchase_id',
    1 => 'purchase_type_id',
    2 => 'recorded',
    3 => 'subscribed',
    4 => 'expires',
    5 => 'order_status_id',
    6 => 'purchase_amount',
    7 => 'requested',
    8 => 'granted',
    9 => 'rostered',
    10 => 'description',
    11 => 'school',
    12 => 'district',
    13 => 'unlimited',
    14 => 'api_id',
    15 => 'handle',
  ),
  'fields' => 
  array (
    0 => 'purchase_id',
    1 => 'purchase_type_id',
    2 => 'recorded',
    3 => 'subscribed',
    4 => 'expires',
    5 => 'order_status_id',
    6 => 'purchase_amount',
    7 => 'requested',
    8 => 'granted',
    9 => 'rostered',
    10 => 'description',
    11 => 'school',
    12 => 'district',
    13 => 'unlimited',
    14 => 'api_id',
    15 => 'handle',
    16 => 'api.api_id',
    17 => 'api.secret',
    18 => 'api.ns_api_key',
    19 => 'api.host',
    20 => 'api.metrics_endpoint',
    21 => 'api.transaction_endpoint',
  ),
  'columnFieldNames' => 
  array (
    'api' => 
    array (
      'api_id' => 'api.api_id',
      'secret' => 'api.secret',
      'ns_api_key' => 'api.ns_api_key',
      'host' => 'api.host',
      'metrics_endpoint' => 'api.metrics_endpoint',
      'transaction_endpoint' => 'api.transaction_endpoint',
    ),
    'purchase' => 
    array (
      'purchase_id' => 'purchase_id',
      'purchase_type_id' => 'purchase_type_id',
      'recorded' => 'recorded',
      'subscribed' => 'subscribed',
      'expires' => 'expires',
      'order_status_id' => 'order_status_id',
      'purchase_amount' => 'purchase_amount',
      'requested' => 'requested',
      'granted' => 'granted',
      'rostered' => 'rostered',
      'description' => 'description',
      'school' => 'school',
      'district' => 'district',
      'unlimited' => 'unlimited',
      'api_id' => 'api_id',
      'handle' => 'handle',
    ),
  ),
  'indices' => 
  array (
    0 => 'purchase_id',
    1 => 'purchase_type_id',
    2 => 'order_status_id',
    3 => 'api_id',
    4 => 'handle',
  ),
  'fetchableIndices' => 
  array (
    0 => 'purchase_id',
    1 => 'handle',
  ),
  'primaryKeys' => 
  array (
    0 => 'purchase_id',
  ),
  'nullable' => 
  array (
    0 => 'subscribed',
    1 => 'expires',
    2 => 'requested',
    3 => 'granted',
    4 => 'rostered',
    5 => 'description',
    6 => 'school',
    7 => 'district',
    8 => 'unlimited',
    9 => 'api_id',
  ),
  'isAutoIncrement' => true,
  'foreignKeys' => 
  array (
    'api_id' => 
    array (
      'column_name' => 'api_id',
      'referenced_table_name' => 'api',
      'referenced_column_name' => 'api_id',
    ),
  ),
  'foreignSchemas' => 
  array (
    'api' => 
    array (
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
    ),
  ),
);
  }
  