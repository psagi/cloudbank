#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');
//   require_once('SCA/SCA.php');

   CloudBankServer::Singleton()->tryQuery('
      ALTER TABLE ledger_account ADD is_local_currency BOOLEAN
	 NOT NULL DEFAULT TRUE CHECK (type = \'Account\' OR is_local_currency)
   ');
/*   
   CloudBankServer::Singleton()->tryQuery('
      ALTER TABLE event ADD is_cleared BOOLEAN DEFAULT 0
   ');
   CloudBankServer::Singleton()->tryQuery('DROP VIEW account_events');
   $g_createSchemaStatements = SchemaDef::CreateSchemaStatements();
   foreach (
      array(
	 'account_events', 'statement_item', 'statement_item_unmatched',
	 'event_statement_item_match', 'event_matched'
      ) as
      $v_dBObject
   ) {
      CloudBankServer::Singleton()->tryQuery($g_createSchemaStatements[$v_dBObject]);
   }
*/
?>
