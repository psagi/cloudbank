#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');
   require_once(dirname(__FILE__) . '/../server/LedgerAccount.php');

   $v_dBConnection = CloudBankServer::GetDBConnection();
   foreach (SchemaDef::CreateSchemaStatements() as $v_sQLStatement) {
//      try {
	 $v_dBConnection->exec($v_sQLStatement);
//      }
/*      
      catch (PDOException $v_exception) {
	 exit(
	    "Execution of SQL statement failed: " . $v_exception->getMessage() .
	    "\n"
	 );
      }
*/
   }
   LedgerAccount::CreateBeginningAccount();
?>
