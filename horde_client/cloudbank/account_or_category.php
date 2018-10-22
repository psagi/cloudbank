<?php
/**
 * $Id: account_or_category.php,v 1.3 2011/01/30 21:29:23 pety Exp pety $
 *
 * Copyright 2007-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Peter Sagi <psagi@freemail.hu>
 */

require_once __DIR__ . '/lib/Application.php';
Horde_Registry::appInit('cloudbank');

require_once CLOUDBANK_BASE . '/lib/Cloudbank.php';
require_once CLOUDBANK_BASE . '/lib/Book.php';

/* main() */

$g_variables = &Horde_Variables::getDefaultVariables();
$g_account_id = $g_variables->get('account_id');
$g_account_type = $g_variables->get('account_type');

$g_isEdit = !empty($g_account_id);
$g_objectName = (
   $g_account_type == CloudBankConsts::LedgerAccountType_Account ?
      'Account' :
      'Category'
);   
$g_isRetry = FALSE;
// build the form
$g_form = &new Horde_Form(
   $g_variables, $g_objectName, strtolower($g_objectName)
);
$g_form->addVariable('Name', 'name', 'text', true);
if ($g_account_type == CloudBankConsts::LedgerAccountType_Account) {
   $g_form->addVariable(
      'Beginning quantity', 'beginning_quantity', 'text', FALSE
   );
   $g_form->addHidden('', 'old_beginning_quantity', 'text', false);
   $g_form->addVariable(
      'Beginning balance', 'beginning_balance', 'text', true
   );
   $g_form->addHidden('', 'old_beginning_balance', 'text', false);
   $g_isLocalCurrencyVar = $g_form->addVariable(
      'Is it in local currency?', 'is_local_currency', 'boolean', true
   );
   $g_isLocalCurrencyVar->setDefault(true);
   $g_form->addHidden('', 'old_is_local_currency', 'boolean', false);
   $g_form->addVariable('Rate/price', 'rate', 'text', false);
   $g_form->addHidden('', 'old_rate', 'text', false);
}
$g_form->addHidden('', 'account_id', 'text', false);
$g_form->addHidden('', 'account_type', 'text', false);
$g_form->addHidden('', 'old_name', 'text', false);

if ($g_form->validate($g_variables)) {	// submitted -> process
   try {
      if ($g_isEdit) {
	 if ($g_account_type == CloudBankConsts::LedgerAccountType_Account) {
   	    Book::Singleton()->modifyAccount($g_variables);
     	 }
       	 else {
   	    Book::Singleton()->modifyCategory($g_variables);
     	 }
      } 
      else {
	 if ($g_account_type == CloudBankConsts::LedgerAccountType_Account) {
	    Book::Singleton()->createAccount($g_variables);
	 }
	 else {
	    Book::Singleton()->createCategory($g_variables);
	 }
      }
      header(
	 'Location: ' .
   	    Horde::url(
   	       $g_account_type == CloudBankConsts::LedgerAccountType_Account ?
   		  'accounts.php' :
   		  'categories.php'
   	    ), true
      );
      exit;
   }
   catch (Exception $v_exception) {
      Cloudbank::PushError(Book::XtractMessage($v_exception));
      $g_isRetry = TRUE;
   }
}

// render
if ($g_isEdit && !$g_isRetry) {
   Book::Singleton()->populateAccountForm($g_variables);
}
$title = ($g_isEdit ? 'Edit' : 'Add') . ' ' . $g_objectName;
$page_output->header();
$notification->notify(array('listeners' => 'status'));
$g_form->renderActive(
   new Horde_Form_Renderer(), $g_variables, 'account_or_category.php', 'post'
);
$page_output->footer();
