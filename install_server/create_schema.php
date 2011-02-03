#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');
   require_once(dirname(__FILE__) . '/../server/SchemaDef.php');
   require_once('SCA/SCA.php');

   foreach (
      SchemaDef::CreateSchemaStatements() as $v_dBObject => $v_sQLStatement
   ) {
      CloudBankServer::Singleton()->tryQuery($v_sQLStatement);
   }
   $v_ledgerAccountService = (
      SCA::getService(dirname(__FILE__) . '/../server/LedgerAccountService.php')
   );
   $v_ledgerAccountService->createBeginningAccount();
?>
