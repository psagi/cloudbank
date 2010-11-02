<?php
/**
 * $Id: account_or_category.php,v 1.1 2010/10/24 10:10:46 pety Exp pety $
 *
 * Copyright 2007-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Peter Sagi <psagi@freemail.hu>
 */

@define('CLOUDBANK_BASE', dirname(__FILE__));
require_once CLOUDBANK_BASE . '/lib/base.php';

require_once HORDE_BASE . '/lib/Horde/Variables.php';
require_once HORDE_BASE . '/lib/Horde/Form.php';
require_once HORDE_BASE . '/lib/Horde/Form/Renderer.php';
require_once CLOUDBANK_BASE . '/lib/Book.php';

/* main() */

$g_variables = &Variables::getDefaultVariables();
$g_account_id = $g_variables->get('account_id');
//$g_account_name = $g_variables->get('account_name');
$g_account_type = $g_variables->get('account_type');

$g_isEdit = !empty($g_account_id);
$g_objectName = (
   $g_account_type == CloudBankConsts::LedgerAccountType_Account ?
      'Account' :
      'Category'
);   
// build the form
$g_form = &new Horde_Form(
   $g_variables, $g_objectName, strtolower($g_objectName)
);
$g_form->addVariable('Name', 'name', 'text', true);
if ($g_account_type == CloudBankConsts::LedgerAccountType_Account) {
   $g_form->addVariable(
      'Beginning balance', 'beginning_balance', 'number', true, false, '', 2
   );
   $g_form->addHidden('', 'old_beginning_balance', 'number', false);
}
$g_form->addHidden('', 'account_id', 'text', false);
$g_form->addHidden('', 'account_type', 'text', false);
$g_form->addHidden('', 'old_name', 'text', false);

//print $g_form->isSubmitted();
if ($g_form->validate($g_variables)) {	// submitted -> process
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
	 Horde::applicationUrl(
	    $g_account_type == CloudBankConsts::LedgerAccountType_Account ?
	       'accounts.php' :
	       'categories.php'
	 ), true
   );
   exit;
}
else {	// render
//print 'Validation failed';
   if ($g_isEdit) {
      Book::PopulateAccountForm($g_variables);
   }
   $title = ($g_isEdit ? 'Edit' : 'Add') . ' ' . $g_objectName;
   require CLOUDBANK_TEMPLATES . '/common-header.inc';
   require CLOUDBANK_TEMPLATES . '/menu.inc';
   $g_form->renderActive(
      new Horde_Form_Renderer(), $g_variables, 'account_or_category.php', 'post'
   );
   require $registry->get('templates', 'horde') . '/common-footer.inc';
}
