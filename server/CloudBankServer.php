<?php 
   require_once(dirname(__FILE__) . '/SchemaDef.php');
   require_once('Debug.php');

   class CloudBankServer {
      public static function Singleton() {
	 static $v_instance = NULL;
	 if (!isset($v_instance)) {
	    $v_instance = new CloudBankServer;
	 }
	 return $v_instance;
      }
      public static function UUID() {
	 return uniqid('', true);
      }
      public static function SwapIf(
	 $p_is2BSwapped, $p_in1, $p_in2, &$p_out1, &$p_out2
      ) {
	 $p_out1 = ($p_is2BSwapped ? $p_in2 : $p_in1);
	 $p_out2 = ($p_is2BSwapped ? $p_in1 : $p_in2);
      }
      public static function AssertIDsMatch($p_id1, $p_id2) {
	 if ($p_id1 != $p_id2) {
	    throw new Exception(
	       "IDs ($p_id1, $p_id2) must match when modifying."
	    );              
	 }
      }
      public static function IsEqual($p_arr1, $p_arr2, $p_mapping) {
	 Debug::Singleton()->log(
	    'CloudBankServer::IsEqual(' . var_export($p_arr1, true) . ', ' .
	       var_export($p_arr2, true) . ', ' . var_export($p_mapping, true) .
	       ')'
	 );
	 foreach ($p_mapping as $v_key1 => $v_key2) {
	    $v_result = ($p_arr1[$v_key1] == $p_arr2[$v_key2]);
	    if (!$v_result) break;
	 }
	 return $v_result;
      }
      
      private static function BindValues(&$p_statement, $p_bindArray) {
	 Debug::Singleton()->log(
	    'CloudBankServer::BindValues(' . var_export($p_statement, true) .
	    ', ' .  var_export($p_bindArray, true) . ')'
	 );
	 if (empty($p_bindArray)) return;
	 foreach($p_bindArray as $v_parameter => $v_value) {
	    Debug::Singleton()->log(
	       'CloudBankServer::BindValues: $v_parameter = ' . $v_parameter .
	       ', $v_value = ' . $v_value . ', is_bool($v_value) = ' .
	       (is_bool($v_value) ? 'true' : 'false')
	    );
	    $v_isSuccess = $p_statement->bindValue(
	       $v_parameter, $v_value,
	       is_bool($v_value) ? PDO::PARAM_BOOL : PDO::PARAM_STR
	    );
	    Debug::Singleton()->log(
	       'CloudBankServer::BindValues: $v_isSuccess = ' . $v_isSuccess
	    );
	 }
      }

      private function __construct() {
	 date_default_timezone_set(@date_default_timezone_get());
	 $v_dBConf = (
	    parse_ini_file(dirname(__FILE__) . '/../conf/cloudbank.ini')
	 );
//	 try {
	 $this->r_dBConnection = new PDO(
	       $v_dBConf['dsn'], $v_dBConf['user'], $v_dBConf['passwd']
	    );
/*	 }
	 catch (PDOException $v_exception) {
	    exit("Connection failed: $v_exception->getMessage()\n");
	 }
*/
	 $this->r_dBConnection->setAttribute(
	    PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION
	 );
      }
      private function __clone() { }

      public function execQuery($p_sQL, $p_bindArray = NULL) {
	 $v_statement = $this->r_dBConnection->prepare($p_sQL);
	 Debug::Singleton()->log(
	    'CloudBankServer::execQuery(): $v_statement = ' .
	       var_export($v_statement, true)
	 );
	 Debug::Singleton()->log(
	    'CloudBankServer::execQuery(): $p_bindArray = ' .
	       var_export($p_bindArray, true)
	 );
	 self::BindValues($v_statement, $p_bindArray);
	 Debug::Singleton()->log(
	    'CloudBankServer::execQuery(): $v_statement = ' .
	       var_export($v_statement, true)
	 );
	 $v_isSuccess = $v_statement->execute();
	 Debug::Singleton()->log(
	    'CloudBankServer::execQuery(): $v_isSuccess = ' .
	    ($v_isSuccess ? 'true' : 'false')
	 );
	 $v_resultSet = $v_statement->fetchAll();
	 Debug::Singleton()->log(
	    'CloudBankServer::execQuery(): $v_resultSet = ' .
	       var_export($v_resultSet, true)
	 );
	 return $v_resultSet;
      }
      public function tryQuery($p_sQLStatement) {
	 try {
	    $this->execQuery($p_sQLStatement);
	 }
	 catch (PDOException $v_exception) {
	    exit(
	       "Execution of SQL statement failed: " .
		  $v_exception->getMessage() .  "\n"
	    );
	 }
      }
      public function beginTransaction() {
	 $this->r_dBConnection->beginTransaction();
      }
/*
      public function rollBackTransaction() {
	 $this->r_dBConnection->rollBack();
      }
*/
      public function commitTransaction() {
	 $this->r_dBConnection->commit();
      }

      private $r_dBConnection;
   }
?>
