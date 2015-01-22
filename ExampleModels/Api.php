<?php
require_once(ROOT_DIR . ENV_DIR . "Core/Model/MySQLModel.php"); 
require_once(ROOT_DIR . ENV_DIR . "Numbershire/Schema/ApiSchemaData.php");
class Api extends MySQLModel{
  protected $schemaClass = "ApiSchemaData"; 

  public function fatalResponse(Exception $exception, $publicMessage, array $errors, $serverMessage=null, $statusCode=500){
    $errors[] = $publicMessage;  
    $this->logException($exception); 
    return new APIResponse($statusCode, array(), $errors); 
  }


  public function modifyNsGroup(ExternalGroupID $groupId, array $addedStudents = array(), array $removedStudents = array(), iCacheService $cacheService ){
    try{
      $classroom = $this->loadClassroom($groupId); 
      if($classroom == null){
        $rawGroupId = $groupId->Value(); 
        $errors[] = "Group Id: {$rawGroupId} doesn't exist in database"; 
        return new APIResponse(404, array(), $errors);  
      }
      $errors = array();
    }
    catch(Exception $e){
      return $this->fatalResponse($e,"Failed to load classroom with external id: ".$groupId->Value(),  array()); 
    }
    $studentChanges = array(); 
    if(!empty($addedStudents)){
      $studentChanges['add_students'] = $addedStudents;  
    }
    if(!empty($removedStudents)){
      $studentChanges['remove_students'] = $removedStudents; 
    }
    
    try {
      $results = $classroom->processAPIStudents($studentChanges); 
    }
    catch(Exception $e){
      $rawGroupId = $groupId->Value(); 
      $serverMessage = var_export(array("message"=>"Failed to process students (add_students/remove_students) for nsgroup/:api_key/:group_id resource","apiKey"=>$this->get("ns_api_key"),"groupId"=>$rawGroupId, "studentChanges"=>$studentChanges), true); 
      $publicMessage= "Server Error: Students were not processed"; 
      return $this->fatalResponse($e, $publicMessage,$errors,$serverMessage  ); 
    }
 
    $response = array(); 
    if(array_key_exists('students_added', $results)){
      $response['students_added'] = $results['students_added'];
    }
    if(array_key_exists('students_removed', $results)){
      $response['students_removed'] = $results['students_removed']; 
    }
    if(array_key_exists('seats_freed', $results)) {
      $response['seats_freed'] = $results['seats_freed']; 
    }
    if(array_key_exists('errors', $results)){
      $errors= $results['errors']; 
    }
    if(!empty($response['students_added']) || !empty($response['students_removed'])) {
      $query = "
        SELECT user_id
        FROM classroom_user
          JOIN classroom USING (classroom_id)
        WHERE external_id=:external_id
      "; 
      $bindings = array(":external_id"=>$groupId->Value()); 
      $userIds = $this->mysql->All($query, $bindings);
      if(!empty($userIds)) {
        foreach($userIds as $k=>$userId){
          $cacheService->clearUserCache(new UserID($userId['user_id']));  
        }
      }
    }
    return new APIResponse(200, $response, $errors);
  }
  
  public function deleteNsGroup(ExternalGroupID $groupId){
    try{
      $classroom = $this->loadClassroom($groupId); 
      if($classroom == null) {
        $serverMessage = var_export(array("message"=>"Failed to delete classroom as it does not exist","apiKey"=>$this->get("ns_api_key"),"groupId"=>$groupId->Value()), true); 
        $publicMessage= "Server Error: Group not found"; 
        return $this->fatalResponse($e, $publicMessage, array(),$serverMessage  ); 
      }
      $classroom->Delete();
      /*$deleted = $this->modelFactory->DeleteExisting("Classroom", array("api_id"=>$this->get("api_id"), "external_id"=>$groupId->Value()));  
      if($deleted == false) {
        $error = "Server Error deleting {$groupId->Value()}";
        error_log($error); 
        return new APIResponse(500, array("errors"=>array($error)), array());
      }*/
    }
    catch(Exception $e){
      $serverMessage = var_export(array("message"=>"Failed to delete classroom","apiKey"=>$this->get("ns_api_key"),"groupId"=>$groupId->Value()), true); 
      $publicMessage= "Server Error: Group not deleted"; 
      return $this->fatalResponse($e, $publicMessage, array(),$serverMessage  ); 
    }
    $rawGroupId = $groupId->Value(); 
    return new APIResponse(200, array("group_deleted"=>"{$rawGroupId}"), array());
  }
  
 
  /*
    Currently the schema (as of 2014-11-25) does not have classrooms
    with a Unique (api_id, external_id) pairing. This means we cannot load 
    an ORM model of a classroom in the standard way and must do so manually. 
  */
  protected function loadClassroom(ExternalGroupID $groupId) {
      $query = "
        SELECT * 
        FROM classroom
        WHERE external_id=:external_id
      "; 
      $bindings = array(":external_id"=>$groupId->Value()); 
      $classroomData = $this->mysql->Row($query, $bindings); 
      if(empty($classroomData)) {
        return null; 
      }
      $classroom = $this->modelFactory->BuildExisting("Classroom", $classroomData); 
      return $classroom; 
  }


  public function createSignedRequestUrl(HostName $host, Secret $secret, HttpMethod $method, URI $baseuri, $body){
    // build up the URI and message for signing
    // querystring params must be in alphabetical order for signing
    $now = time();
    $uri = $baseuri->Value()."?zauth_timestamp={$now}";
    $msg = $method->Value()." ".$uri;
    if($method->allowsBody() && !empty($body)){
      $msg .= "\n{$body}"; 
    }
    $sig = hash_hmac('md5', $msg, $secret->Value());
    // assemble final URI and make the request
    $uri = $host->Value() ."{$uri}&zauth_signature={$sig}";
    return new URL($uri); 
  }

  public function curl(HttpMethod $method, URL $endpoint, $body, $verifyPeer=true){
    if(!$method->isValid()) {
      throw new Exception("Invalid http method used when attempting to curl"); 
    }
    if(!$endpoint->isValid()) {
      throw new Exception("Malformed url provieded when attempting to curl"); 
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyPeer);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method->Value());
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json')); 
    curl_setopt($ch, CURLOPT_URL, $endpoint->Value() );
    curl_setopt($ch, CURLOPT_VERBOSE, true); 
    if ($method->allowsBody() && !empty($body)) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    return curl_exec($ch);
  }
  
  public function createScope() {
    try {
      $scope = $this->modelFactory->Create("Scope", array());
      $scope->Sync();
      $scopeId = $scope->get("scope_id"); 
    } 
    catch(Exception $e){
      $serverMessage = var_export(array("message"=>"Could not insert new scope_id","apiKey"=>$this->get("ns_api_key")), true); 
      $publicMessage= "Server Error: Could not create scope"; 
      return $this->fatalResponse($e, $publicMessage,array(),$serverMessage  ); 
    }
    return new APIResponse(200, array("scope"=>"$scopeId"), array());
  }
  
  public function classroomExists(ExternalGroupID $groupId) {
    $query = "
      SELECT EXISTS (
        SELECT 1
        FROM classroom
        WHERE api_id=:api_id AND external_id=:external_id
        LIMIT 1
       )
    "; 
    $bindings = array(':api_id'=>$this->get("api_id"), ':external_id'=>$groupId->Value());
    return $this->mysql->One($query, $bindings); 
  }

  public function createClassroomAccessCode(){
    return Classroom::NewAccessCode();   
  }
}
