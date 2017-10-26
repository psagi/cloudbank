#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');

   CloudBankServer::Singleton()->tryQuery('
      DROP VIEW event_statement_item_match
   ');
   $g_createSchemaStatements = SchemaDef::CreateSchemaStatements();
   CloudBankServer::Singleton()->tryQuery(
      $g_createSchemaStatements['event_statement_item_match']
   );
?>
