<?php
require_once(ROOT_DIR . ENV_DIR . "Core/Model/MySQLModel.php"); 
require_once(ROOT_DIR . ENV_DIR . "Numbershire/Schema/CcPurchaseSchemaData.php"); 
class CcPurchase extends MySQLModel {
  protected $schemaClass = "CcPurchaseSchemaData"; 
}

