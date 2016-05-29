#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../server/CloudBankServer.php');

   $p_account_name = $argv[1];
   $p_date = $argv[2];

   CloudBankServer::Singleton()->beginTransaction();
   $v_ledgerAccountID = CloudBankServer::Singleton()->execQuery(
      'SELECT id FROM ledger_account WHERE name = :name',
      array(':name' => $p_account_name)
   );
   if (!$v_ledgerAccountID) exit;
   CloudBankServer::Singleton()->execQuery(
      '
	 UPDATE event
	 SET is_cleared = 1
	 WHERE
	    (
	       credit_ledger_account_id = :ledger_account_id OR
	       debit_ledger_account_id = :ledger_account_id
	    ) AND date <= :date
      ',
      array(
	 ':ledger_account_id' => $v_ledgerAccountID[0]['id'],
	 ':date' => $p_date
      )
   );
   CloudBankServer::Singleton()->commitTransaction();
?>
