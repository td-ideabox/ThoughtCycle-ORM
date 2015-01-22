<?php
require_once(ROOT_DIR . ENV_DIR . "Core/Model/MySQLModel.php"); 
require_once(ROOT_DIR . ENV_DIR . "Numbershire/Schema/PurchaseOrderSchemaData.php"); 
class PurchaseOrder extends MySQLModel {
  protected $schemaClass = "PurchaseOrderSchemaData"; 
}
