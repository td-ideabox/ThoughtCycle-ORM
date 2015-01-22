<?php
require_once(ROOT_DIR . ENV_DIR . "Core/Model/MySQLModel.php"); 
require_once(ROOT_DIR . ENV_DIR . "Numbershire/Schema/PurchaseSchemaData.php"); 
class Purchase extends MySQLModel{
  public static function GenerateHandle() {
    return Base::Guid(); 
  }
  
  protected $schemaClass = "PurchaseSchemaData";   
  // Map # of students before new price applies. # STUDENTS MUST BE IN DESCENDING ORDER
  protected $retailLicensePrices = array(array(1000, 5.99),array(20, 8.99), array(0, 11.99)); 
  protected $ddsLicensePrice = 4.0;  
  protected $sandbox = true; 
  protected static $purchaseTypes = array(
    'trial'=> 0,
    0=>'trial',
    
    'retail'=>1,
    1=>'retail',
    
    'api'=>2,
    2=>'api',
    
    'district'=>3,
    3=>'district'
  );
  public static function PurchaseTypes($key=null){
    if(empty($key)){
      return self::$purchaseTypes; 
    }
    else {
      return self::$purchaseTypes[$key]; 
    }
  }

  protected static $orderStatus = array(
    "pending"=>1,
    1=>"pending",

    "incomplete"=>2,
    2=>"incomplete",
    
    "complete"=>3,
    3=>"complete",
    
    "cancelled"=>4,
    4=>"cancelled",
    
    "failed"=>5,
    5=>"failed"
  ); 
  public static function OrderStatus($key=null) {
    if(empty($key)) {
      return self::$orderStatus; 
    }
    else {
      return self::$orderStatus[$key]; 
    }
  }

  public function generateSubscriptionTimespan(SubscriptionStart $start=null, SubscriptionExpires $end=null) {
    if($start == null) {
      $start = new SubscriptionStart(time()); 
    }
    if($end == null) {
      $end = $start->Value() + 365 * 24 * 60 * 60; // One year from the start
    }
    return new SubscriptionTimespan($start, $end);
  }


  public function retrievePurchasedStudents() {
    $query = "
      SELECT * FROM student_product_purchase
      WHERE purchase_id=:purchase_id
    "; 
    $bindings = array(":purchase_id"=>$this->get("purchase_id")); 
    $students = $this->mysql->All($query, $bindings); 
    return $students; 
  }

 

  protected function addTransactionToPurchase(PurchaseID $purchaseId, TransactionID $transactionId){
    $query = "
      INSERT INTO purchase_transaction
      (purchase_id, transaction_id) 
      VALUES
      (:purchase_id, :transaction_id)
    "; 
    $bindings = array(":purchase_id"=>$purchaseId->Value(), ":transaction_id"=>$transactionId->Value()); 
    $this->mysql->query($query, $bindings); 
  }
  

  
  public function grantStudents(GrantedStudentCount $grantedStudentCount){
    $currentGrantedCount = $this->get("granted");
    $requestedStudentCount = $this->get("requested");
    $newGrantedStudentCount = $currentGrantedCount + $grantedStudentCount->Value();   
    
    // Brain out the purchase status based on the number of granted licenses
    if($newGrantedStudentCount > $requestedStudentCount) {
      // Error: To many granted students
      return array("status"=>"failure","message"=> "$requestedStudentCount students were requested for this purchase, but we're trying to grant them $newGrantedStudentCount which is too many"); 
    }
    else if( $newGrantedStudentCount < $requestedStudentCount) {
      $this->set("granted", $newGrantedStudentCount); 
      $this->set("order_status_id", $this->OrderStatus("incomplete"));
    }
    else if ($newGrantedStudentCount == $requestedStudentCount) {
      $this->set("granted", $newGrantedStudentCount); 
      $this->set("order_status_id", $this->OrderStatus("complete"));
    }
    $this->Sync();

    // Record the update to the purchase
    $purchaseUpdate = $this->modelFactory->Create("PurchaseUpdate", array("purchase_id"=>$this->get("purchase_id"), "granted"=>$this->get("granted"), "timestamp"=>time()));
    $purchaseUpdate->Sync();

    // Now give the root roster the freshly granted licenses
    $roster = $this->getPurchaseRootRoster();
    $roster->set("free_student_licenses", $roster->get("free_student_licenses") + $grantedStudentCount->Value());
    $roster->Sync();

    // Sanity Check
    if($this->get("granted") < $roster->get("free_student_licenses")+ $roster->get("consumed_student_licenses")){
      throw new Exception("Purchase: " . $this->get("purchase_id") . " is out of sync with it's rosters! Root roster ".$roster->get("roster_id"). "  has more licenses than have been granted.");  
    }
    return array("status"=>"success"); 
  }
  
  public function sendStatusUpdateToThirdParty(){
    if($this->get('api_id') === null) { return; } 
    $api = $this->modelFactory->LoadExisting("Api", array("api_id"=>$this->get("api_id")));  
    $host =  new HostName($api->get('host'));
    $endpoint = new URI($api->get("transaction_endpoint") . $this->get('handle')); 
    $method = new HttpMethod('POST');
    $orderStatus = $this->OrderStatus($this->get('order_status_id')); 
    $body=array("status"=>$orderStatus, "units_requested"=>$this->get('requested'), "units_granted"=>$this->get('granted'));
    $body = json_encode($body); 
    $secret = new Secret($api->get('secret'));
    
    $apiUrl = $api->createSignedRequestUrl($host, $secret, $method, $endpoint, $body); 
    $response = $api->curl($method, $apiUrl, $body, false); 
    $result = $this->handleThirdPartyResponse($response); 
    return $result; 
  }

  protected function handleThirdPartyResponse($response) {
    $response = json_decode($response, true);
    if($response == null) {
      Base::Err(json_encode($response)); 
      return array("status"=>"failure", "message"=>"Server Error");
    }
    if($response['meta']['code'] == 200) {
      return array("status"=>"success");
    }
    else {
      error_log(json_encode($response)); 
      return array("status"=>"failure", "message"=>"Could not complete the request");
    }
  }


  public function calculateApiPurchaseAmount(RequestedStudentCount $requested) {
    return $requested->Value() * $this->ddsLicensePrice; 
  }

  /**
    Go through each price listing (descending order relative to student threshold)
    calculate the price for the number of students above that threshold. 
    
    ex. 1242 students
    price = $0
    
    // 1000+ student price listing ($7.99 per student)
    1242 - 1000 = 242 students
    242 * $7.99 = $1933.58

    // 20+ student price listing ($9.99 per student)
    1000 - 20 = 980 students
    980 * $9.99 = $9,790.20 

    // default retail price ($14.99)
    20 - 0 = 20 students
    20 * $14.99 = $299.80

    // Subtotal = $12023.58
  */
  public function calculateRetailPurchaseAmount(RequestedStudentCount $requested) {
    $considered = $requested->Value(); 
    $purchaseAmount = 0; 
    foreach($this->retailLicensePrices as $priceListing) {
      $threshold= $priceListing[0];
      $pricePerLicense= $priceListing[1]; 
      if($considered > $threshold) {
        $studentsInPriceBracket = $considered - $threshold;
        $purchaseAmount += $studentsInPriceBracket * $pricePerLicense; 
        $considered = $threshold; 
      }
    }
    return $purchaseAmount; 
  }

  public function CallerReference() {
    $callerReference = $this->get("handle"); 
    if(empty($callerReference)) {
      throw new Exception("Tried to create caller reference with empty handle"); 
    }
    return $callerReference;  
  }

  
  /* until I can figure out a better way to 
     manager rostering api students, we have
     to enforce the single roster per api
     purchase rule in app logic rather than
     db logic
  */
  public function getPurchaseRootRoster() {
    
    $query = "
      SELECT MIN(distributed_from_roster) as roster_id
      FROM student_license_distribution
        JOIN roster ON (roster.roster_id=student_license_distribution.distributed_from_roster)
      WHERE path_depth=0 AND purchase_id=:purchase_id
    ";
    $bindings = array(":purchase_id"=>$this->get("purchase_id"));
    $rootId = $this->mysql->One($query, $bindings);
    if(empty($rootId)) {
      return null;
    }
    $query = "
      SELECT * 
      FROM roster
      WHERE roster_id=:roster_id
    ";
    $bindings = array(":roster_id"=>$rootId); 
    $rosterData = $this->mysql->Row($query, $bindings);
    $roster = $this->modelFactory->BuildExisting("Roster", $rosterData); 
    return $roster; 
  }
}
