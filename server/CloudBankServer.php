<?php 
   require_once('SDO/DAS/Relational.php');
   require_once(dirname(__FILE__) . '/SchemaDef.php');

   class CloudBankServer {
      public static function Singleton() {
	 if (!isset(self::$r_instance)) {
	    self::$r_instance = new CloudBankServer;
	 }
	 
	 return self::$r_instance;
      }
      public static function GetDBConnection() {
	 $v_dBConf = parse_ini_file(dirname(__FILE__) . '/../conf/db.ini');
//	 try {
	    $v_dBConnection = new PDO(
	       $v_dBConf['dsn'], $v_dBConf['user'], $v_dBConf['passwd']
	    );
/*	 }
	 catch (PDOException $v_exception) {
	    exit("Connection failed: $v_exception->getMessage()\n");
	 }
*/
	 $v_dBConnection->setAttribute(
	    PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION
	 );
	 return $v_dBConnection;
      } 

      private static $r_instance;
   
      private function __construct() {
	 $this->r_dBConnection = self::GetDBConnection();
	 $this->r_dAS = (
	    new SDO_DAS_Relational(
               SchemaDef::Metadata(), 'ledger_account',
	       SchemaDef::ContainmentMetadata()
            )
	 );
	 $v_dASQuery = SchemaDef::DASQuery();
	 $this->r_rootDO = (
	    $this->r_dAS->executeQuery(
	       $this->r_dBConnection, $v_dASQuery['query'], $v_dASQuery['array']
	    )
	 );
      }
      private function __clone() { }

      public function dBConnection()	{ return $this->r_dBConnection;	}
      public function rootDO()		{ return $this->r_rootDO;	}
      public function applyChanges() {
	 return (
	    $this->r_dAS->applyChanges($this->r_dBConnection, $this->r_rootDO)
	 );
      }

      private $r_dBConnection;
      private $r_dAS;
      private $r_rootDO;
   }
?>
