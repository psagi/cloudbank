#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');
   require_once('SCA/SCA.php');

   function tryQuery($p_sQLStatement) {
      try {
	 CloudBankServer::Singleton()->execQuery($p_sQLStatement);
      }
      catch (PDOException $v_exception) {
	 exit(
	    "Execution of SQL statement failed: " . $v_exception->getMessage() .
	       "\n"
	 );
      }
   }

   /*** MAIN ***/

   tryQuery('DROP VIEW account_events');
   $g_createSchemaStatements = SchemaDef::CreateSchemaStatements();
   foreach (
      array(
	 'account_events', 'event_idx_debit_ledger_account_id',
	 'event_idx_credit_ledger_account_id'
      ) as $v_dBObject 
   ) {
      tryQuery($g_createSchemaStatements[$v_dBObject]);
   }
?>
