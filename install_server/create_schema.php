#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');
   require_once('SCA/SCA.php');

   foreach (SchemaDef::CreateSchemaStatements() as $v_sQLStatement) {
//      try {
	 CloudBankServer::Singleton()->execQuery($v_sQLStatement);
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
   $v_ledgerAccountService = (
      SCA::getService(dirname(__FILE__) . '/../server/LedgerAccountService.php')
   );
   $v_ledgerAccountService->createBeginningAccount();
?>
