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
//$g_account_id = $g_variables->get('account_id');
//$g_account_type = $g_variables->get('account_type');

//$g_isEdit = !empty($g_account_id);
//$g_objectName = (
//   $g_account_type == CloudBankConsts::LedgerAccountType_Account ?
//      'Account' :
//      'Category'
//);   
//$g_isRetry = FALSE;
// build the form
$g_form = &new Horde_Form($g_variables, 'Import Statement', 'statement');
$g_statement_file = $g_form->addVariable('Statement File (CSV)', 'statement_file', 'file', false);
$g_form->appendButtons(array('submit', 'purge'));

if ($g_form->validate($g_variables)) {	// submitted -> process
   try {
//var_dump($g_variables);
//var_dump($g_form->getVariables(false));
      if ($g_variables['submitbutton'] == 'purge') {
	 Book::Singleton()->purgeStatement();
      }
      else {
//var_dump($g_statement_file);
	 $g_statement_file->getInfo($g_variables, $g_statement_file_info);
//var_dump($g_statement_file_info);
//var_dump($g_statement_file_info['file']);
	 $g_filename = $g_statement_file_info['file'];
	 if (
	    !(Book::Singleton()->importStatement($g_filename))
	 ) Cloudbank::PushError("Could not open file $g_filename");
	 else {
	    header('Location: ' . Horde::url('accounts.php'), true);
	    exit;
	 }
      }
   }
   catch (Exception $v_exception) {
      Cloudbank::PushError(Book::XtractMessage($v_exception));
//      $g_isRetry = TRUE;
   }
}

// render
//if ($g_isEdit && !$g_isRetry) {
//   Book::PopulateAccountForm($g_variables);
//}
$title = 'Import Statement';
$page_output->header();
$notification->notify(array('listeners' => 'status'));
$g_form->renderActive(
   new Horde_Form_Renderer(), $g_variables, 'import_statement.php', 'post'
);
$page_output->footer();
