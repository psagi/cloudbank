#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');

   CloudBankServer::Singleton()->tryQuery('
      DROP VIEW event_statement_item_match
   ');
   CloudBankServer::Singleton()->tryQuery('DROP VIEW statement_item_unmatched');
   $g_createSchemaStatements = SchemaDef::CreateSchemaStatements();
   CloudBankServer::Singleton()->tryQuery(
      $g_createSchemaStatements['event_statement_item_match']
   );
   CloudBankServer::Singleton()->tryQuery(
      $g_createSchemaStatements['statement_item_unmatched']
   );
?>
