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
	 uuid_export($v_UUIDGenerator, UUID_FMT_SIV, &$v_uuid);
	 return $v_uuid;
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
	 Debug::Singleton()->log(var_export($v_statement, true));
	 Debug::Singleton()->log(var_export($p_bindArray, true));
	 $v_statement->execute($p_bindArray);
	 return $v_statement->fetchAll();
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
