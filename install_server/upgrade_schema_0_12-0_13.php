#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');

   $g_createSchemaStatements = SchemaDef::CreateSchemaStatements();
   CloudBankServer::Singleton()->tryQuery(
      $g_createSchemaStatements['ledger_account_balances']
   );
?>
