#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');
//   require_once('SCA/SCA.php');

   CloudBankServer::Singleton()->tryQuery('
      ALTER TABLE event ADD statement_item_id VARCHAR(16)
   ');
   CloudBankServer::Singleton()->tryQuery('
      ALTER TABLE event ADD is_cleared BOOLEAN DEFAULT 0
   ');
   CloudBankServer::Singleton()->tryQuery('DROP VIEW account_events');
   $g_createSchemaStatements = SchemaDef::CreateSchemaStatements();
   foreach (
      array(
	 'account_events', 'statement_item', 'statement_item_unmatched',
	 'event_statement_item_match'
      ) as
      $v_dBObject
   ) {
      CloudBankServer::Singleton()->tryQuery($g_createSchemaStatements[$v_dBObject]);
   }
?>
