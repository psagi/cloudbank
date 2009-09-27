<?php 
   require_once('SDO/DAS/Relational.php');
   require_once(dirname(__FILE__) . '/../conf/db.conf');
   require_once(dirname(__FILE__) . '/SchemaDef.php');

   class CloudBankServer {
      public static function Singleton() {
	 if (!isset(self::$r_instance)) {
	    self::$r_instance = new CloudBankServer;
	 }
	 
	 return self::$r_instance;
      }
      public static function GetDBConnection() {
	 global $g_dBConf;
	 try {
	    $v_dBConnection = new PDO(
	       $g_dBConf['dsn'], $g_dBConf['user'], $g_dBConf['passwd']
	    );
	 }
	 catch (PDOException $v_exception) {
	    exit("Connection failed: $v_exception->getMessage()\n");
	 }

	 return $v_dBConnection;
      } 

      private static $r_instance;
   
      private function __construct() {
	 $this->r_dBConnetcion = self::GetDBConnection();
	 $this->r_dAS = (
	    new SDO_DAS_Relational(
               SchemaDef::Metadata(), 'ledger_account',
	       SchemaDef::ContainmentMetadata()
            )
	 );
	 $v_dASQuery = SchemaDef::DASQuery();
//var_dump($this->r_dBConnection, $this->r_dBConnection->getAttribute(PDO::ATTR_SERVER_INFO), $v_dASQuery);	 
//var_dump($this->r_dBConnection->prepare('SELECT * FROM test'));
//exit();
	 $this->r_rootDO = (
	    $this->r_dAS->executeQuery(
	       $this->r_dBConnection, $v_dASQuery['query'], $v_dASQuery['array']
	    )
	 );
      }
      private function __clone() { }

      public function dBConnection()	{ return $r_dBConnection;	}
      public function rootDO()		{ return $r_rootDO;		}
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
