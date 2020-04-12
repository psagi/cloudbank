#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');

   CloudBankServer::Singleton()->tryQuery('DROP VIEW statement_item_unmatched');
   CloudBankServer::Singleton()->tryQuery('
      DROP VIEW event_statement_item_match
   ');
   CloudBankServer::Singleton()->tryQuery('DROP TABLE statement_item');
   $g_createSchemaStatements = SchemaDef::CreateSchemaStatements();
   foreach (
      array(
	 'ledger_account_balances', 'statement_item',
	 'statement_item_unmatched',
	 'event_statement_item_match'
      ) as
      $v_dBObject
   ) {
      CloudBankServer::Singleton()->tryQuery($g_createSchemaStatements[$v_dBObject]);
   }
?>
