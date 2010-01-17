<?php 
   require_once(dirname(__FILE__) . '/SchemaDef.php');
   require_once(dirname(__FILE__) . '/Debug.php');

   class CloudBankServer {
      public static function Singleton() {
	 static $v_instance = NULL;
	 if (!isset($v_instance)) {
	    $v_instance = new CloudBankServer;
	 }
	 return $v_instance;
      }
      public static function UUID() {
	 static $v_UUIDGenerator = NULL;
	 if (!$v_UUIDGenerator) uuid_create(&$v_UUIDGenerator);
	 uuid_make($v_UUIDGenerator, UUID_MAKE_V1);
	 $v_uuid = str_repeat(' ', 39);
	 uuid_export($v_UUIDGenerator, UUID_FMT_SIV, &$v_uuid);
	 return rtrim($v_uuid);	// uuid_export() adds an extra "\0" to the end
      }

      public static function ToSDO(
	 $p_resultSet, $p_rootDO, $p_elementTypeName, $p_mapping
      ) {
	 Debug::Singleton()->log(
	    'CloudBankServer::ToSDO(): $p_rootDO = ' .
	       SDO_Model_ReflectionDataObject::export(
		  new SDO_Model_ReflectionDataObject($p_rootDO), true
	       )
	 );
	 foreach ($p_resultSet as $v_record) {
	    $v_result_DO = $p_rootDO->createDataObject($p_elementTypeName);
	    foreach ($p_mapping as $v_dBField => $v_sDOField) {
	       $v_result_DO[$v_sDOField] = $v_record[$v_dBField];
	    }
	 }
	 return $p_rootDO;
      }
      public static function SwapIf(
	 $p_is2BSwapped, $p_in1, $p_in2, &$p_out1, &$p_out2
      ) {
	 $p_out1 = ($p_is2BSwapped ? $p_in2 : $p_in1);
	 $p_out2 = ($p_is2BSwapped ? $p_in1 : $p_in2);
      }

      private function __construct() {
	 date_default_timezone_set(@date_default_timezone_get());
	 $v_dBConf = parse_ini_file(dirname(__FILE__) . '/../conf/server.ini');
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
	 $v_statement->execute($p_bindArray);
	 $v_resultSet = $v_statement->fetchAll();
	 Debug::Singleton()->log(
	    'CloudBankServer::execQuery(): $v_resultSet = ' .
	       var_export($v_resultSet, true)
	 );
	 return $v_resultSet;
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
