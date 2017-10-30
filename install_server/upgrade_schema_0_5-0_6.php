#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');

   CloudBankServer::Singleton()->beginTransaction();
   CloudBankServer::Singleton()->tryQuery('DROP VIEW event_matched');
   CloudBankServer::Singleton()->tryQuery('
      DROP VIEW event_statement_item_match
   ');
   CloudBankServer::Singleton()->tryQuery('DROP VIEW statement_item_unmatched');
   CloudBankServer::Singleton()->tryQuery('DROP VIEW account_events');
   CloudBankServer::Singleton()->tryQuery('
      ALTER TABLE event RENAME TO event_bak 
   ');
   $g_createSchemaStatements = SchemaDef::CreateSchemaStatements();
   CloudBankServer::Singleton()->tryQuery($g_createSchemaStatements['event']);
   CloudBankServer::Singleton()->tryQuery('
      INSERT INTO event SELECT * FROM event_bak
   ');
   CloudBankServer::Singleton()->tryQuery('
      DROP TABLE event_bak
   ');
   foreach (
      array(
	 'event_idx_debit_ledger_account_id',
	 'event_idx_credit_ledger_account_id', 'account_events',
	 'statement_item_unmatched', 'event_statement_item_match',
	 'event_matched'
      ) as
      $v_dBObject
   ) {
      CloudBankServer::Singleton()->tryQuery(
	 $g_createSchemaStatements[$v_dBObject]
      );
   }
   CloudBankServer::Singleton()->commitTransaction();
?>
