#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');
   require_once('SCA/SCA.php');

   CloudBankServer::Singleton()->tryQuery('DROP VIEW account_events');
   $g_createSchemaStatements = SchemaDef::CreateSchemaStatements();
   foreach (
      array(
	 'account_events', 'event_idx_debit_ledger_account_id',
	 'event_idx_credit_ledger_account_id'
      ) as $v_dBObject 
   ) {
      CloudBankServer::Singleton()->tryQuery(
	 $g_createSchemaStatements[$v_dBObject]
      );
   }
?>
