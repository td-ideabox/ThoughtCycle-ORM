# ThoughtCycle ORM #
This ORM present a novel way to manage access to a model's data. Rather than having a model limited to it's table's defined columns, these models can access their column data and the columns of any table in which they have a unique foreign key. This reduces boilerplate code and gives models ownership of their actual domain of information. Models now get and set 'fields' rather than 'columns' (as data may be across several tables).  

Each model instantiates a specified SchemaData when the model is itself is created in the ModelFactory. When buildSchemaData.php is executed, each table in the specified MySQL database is walked and a SchemaData class generated for it. These SchemaData classes contain all the information our Models need to work their magic. 

## Executing buildSchemaData.php ##
Writing table definitions in application models is an error prone, time consuming, and painful task to do whenever the database schema changes. So much so it can disuade certain design decisions as too costly or unappealing based on the work that needs to go into maintaining the model's mapping to it's database table. The script buildSchemaData.php walks the target database and generates SchemaData class files. Each SchemaData class file maps to a table in your database, and contains a great deal of data about the table, enough so to remove any need to manually maintain the table definitions in the model. Whenever your database schema changes just run the script to get your new up-to-data SchemaData classes. Once generated, simply reference the correct SchemaData class in the corresponding model. In our example the Purchase model references the PurchaseSchemaData class. 

buildSchemaData.php relies on some configuation to be in place before it can successfully execute, and these requirements can be easily modified to be command line arguments if needed. 

##Example SchemaData and Model Classes ##
Our assumed database schema is:

* `purchase` parent table of purchase data. Has a foreign key into `api`.
* `cc_purchase` purchases completed with a credit card. Has a foreign key into `purchase`.
* `purchase_order`s purchases completed via a purchase order. Has a foreign key into `purchase`.
* `api`s all purchases are tied to third-party api's. 

Running buildSchemaData.php would generate the following files:

* `PurchaseSchemaData.php`
* `CcPurchaseSchemaData.php `
* `PurchaseOrderSchemaData.php`
* `ApiSchemaData.php`

All of which have column, index, foreign key, and other key details of their corresponding table.

With these tables we have the models in the following files:

* `Purchase.php` 
* `CcPurchase.php`
* `PurchaseOrder.php`
* `Api.php`

These models each have the name of the SchemaData class they require to function, and are given that SchemaData object when created at the ```ModelFactory```.

## Example Model Usage ##

Given the above setup, models can be used in the following manner:

```php

  // Given a PurchaseOrder model, let's go ahead and set some of it's fields
  $purchaseOrder->set("purchase_order_identifier", "#12345");
  $purchaseOrder->set("purchase.purchase_amount", "10.99");
  $purchaseOrder->set("purchase.api.secret", "Some Secret"); 
  
  // The model will sync the 'furthest' (table that must be accessed via the most foreign keys) first.
  // This is because we may need to insert a record into that furthest table if the data does not yet exist. 
  // Any changes in the farthest table will propagate down to 'closer' tables to ensure the sync is correct. 
  $purchaseOrder->Sync(); 

  // To retrieve some data
  $purchaseAmount = $purchaseOrder->get("purchase.purchase_amount"); 
  $apiHost = $purchaseOrder->get("purchase.api.host"); 
```

This example is simple, but illustrates some very important points:
* No boilerplate code to get related models. Example code would be writing functions such as getPurchase() and getApi(), which would require simple but unnecesary code.
* Schema is made clear in the code, ensuring places where joins are occuring is obvious. 
* Simple means to mutate database, no strange psuedo queries needed to do complex inserts and updates. 

