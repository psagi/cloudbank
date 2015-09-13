<?php
/**
 * $Id: accounts_or_categories.php,v 1.6 2010/11/02 21:51:26 pety Exp pety $
 *
 * Copyright 2007-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Peter Sagi <psagi@freemail.hu>
 */

@define('CLOUDBANK_BASE', dirname(__FILE__));

require_once CLOUDBANK_BASE . '/lib/Cloudbank.php';
require_once CLOUDBANK_BASE . '/lib/Book.php';

/* main() */

$g_isError = FALSE;
try {
   $g_accountsOrCategories = (
      Book::Singleton()->getAccountsOrCategoriesWBalance($g_account_type)
   );
   CloudBank::AddLinks(
      $g_accountsOrCategories, 'events.php',
      array('ledger_account_id' => 'id', 'ledger_account_type' => 'type'),
      'name', 'account_link'
   );
   if ($g_account_type == CloudBankConsts::LedgerAccountType_Account) {
      CloudBank::AddLinks(
	 $g_accountsOrCategories, 'account_or_category.php',
	 array(
	    'account_id' => 'id', 'name' => 'name', 'account_type' => 'type',
	    'beginning_balance' => 'beginning_balance'
	 ), 'edit_icon', 'edit_icon_link', NULL, 'Edit'
      );
   }
   else {
      CloudBank::AddLinks(
	 $g_accountsOrCategories, 'account_or_category.php',
	 array(
	    'account_id' => 'id', 'name' => 'name', 'account_type' => 'type',
	 ), 'edit_icon', 'edit_icon_link', NULL, 'Edit'
      );
   }
   CloudBank::AddLinks(
      $g_accountsOrCategories, 'delete_account.php',
      array('account_id' => 'id', 'account_type' => 'type'),
      'delete_icon', 'delete_icon_link', NULL, 'Delete'
   );
   Book::SortResultSet($g_accountsOrCategories, 'name');
   $g_total = (
      $g_account_type == CloudBankConsts::LedgerAccountType_Account ?
	 Book::Singleton()->getAccountsTotal() :
	 Book::Singleton()->getCategoriesTotal()
   );
   $g_template = new Horde_Template;
   $g_template->set(
      'new_account_link', 
      Horde::link(
	 Horde::url('account_or_category.php')->add(
	    array('account_type' => $g_account_type)
	 )
      ) . 'New</a>'
   );
   $g_template->set('accounts', $g_accountsOrCategories);
   $g_template->set('total', $g_total);

   $title = _(
      $g_account_type == CloudBankConsts::LedgerAccountType_Account ?
	 "Accounts" :
	 "Categories"
   );
}
catch (Exception $v_exception) {
   Cloudbank::PushError(Book::XtractMessage($v_exception));
   $g_isError = TRUE;
}

$page_output->header();
$notification->notify(array('listeners' => 'status'));
if (!$g_isError) {
//$g_template->setOption('debug', true);
   echo $g_template->fetch(CLOUDBANK_TEMPLATES . '/accounts.html');
}
$page_output->footer();
