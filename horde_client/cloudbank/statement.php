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

function preparePurgeForm($p_variables) {
   $v_form = new Horde_Form($p_variables, NULL, 'purge_statement');
   $v_form->setButtons('purge');
   return $v_form;
}

function prepareImportForm($p_variables, &$p_statement_file_variable) {
   $v_form = (
      new Horde_Form($p_variables, 'Import Statement', 'import_statement')
   );
   $p_statement_file_variable = (
      $v_form->addVariable(
  	 'Statement File (CSV)', 'statement_file', 'file', TRUE
      )
   );
   $v_file_description = (
      '<p>The file must conform to the CSV standard and contain only ' .
      'printable, UTF-8 characters.</p>'.
      '<p>Fields are:</p>' .
      '<ul><li>Item ID (unique)</li><li>Account Name</li>' .
      '<li>Item Type (O: opening balance, E: event, C: closing balance)</li>' .
      '<li>Description</li><li>Amount</li></ul>'
   );
   $v_file_description_variable = (
      $v_form->addVariable('', 'description', 'html', FALSE, TRUE)
   );
   $v_file_description_variable->setDefault($v_file_description);
   $v_form->setButtons('import');
   return $v_form;
}

function getAction($p_variables, &$p_statement_file_variable) {
   $p_form = NULL;
   $p_statement_file_variable = NULL;
   switch ($p_variables['formname']) {
      case 'purge_statement': 
	 $v_form = preparePurgeForm($p_variables);
	 if ($v_form->validate($g_variables)) {	// submitted -> process
//	    if ($g_variables['submitbutton'] == 'purge') {
	    return 'purge';
	 }
	 break;
      case 'import_statement':
	 $v_form = prepareImportForm($p_variables, $p_statement_file_variable);
	 if ($v_form->validate($p_variables)) {	// submitted -> process
	    return 'import';
	 }
	 break;
   }
   return NULL;
}

function processActions($p_variables) {
   try {
      switch (getAction($p_variables, $v_statement_file_variable)) {
	 case 'purge': 
   	    Book::Singleton()->purgeStatement();
   	    break;
     	 case 'import': 
   	    $v_statement_file_variable->getInfo(
	       $p_variables, $v_statement_file_info
	    );
   	    $v_filename = $v_statement_file_info['file'];
   	    if (
   	       !(Book::Singleton()->importStatement($v_filename))
   	    ) {
   	       Cloudbank::PushError(
		  'Could not open file ' . $v_statement_file_info['name'] .
		  ' or it is not UTF-8 text.'
	       );
   	       return;
   	    }
   	    break;
      }
   }
   catch (Exception $v_exception) {
      Cloudbank::PushError(Book::XtractMessage($v_exception));
   }
}

function prepareView($p_variables, &$p_form) {
   $v_accounts = NULL;
   try {
      $v_accounts = Book::Singleton()->getAccountsForStatement();
   }
   catch (Exception $v_exception) {
      Cloudbank::PushError(Book::XtractMessage($v_exception));
   }
   $v_template = NULL;
   $p_form = NULL;
   switch (
      (is_null($v_accounts) || (count($v_accounts) == 0)) ?
      'import' :
      'accounts'
   ) {
      case 'accounts':
	 $v_template = new Horde_Template;
	 array_walk(
	    $v_accounts,
	    function(&$p_record, $p_key) { 
	       $p_record['type'] = CloudBankConsts::LedgerAccountType_Account;
	    }
	 );
	 CloudBank::AddLinks(
	    $v_accounts, 'events.php',
	    array(
	       'ledger_account_id' => 'id',
	       'ledger_account_type' => 'type'
	    ), 'name', 'account_link'
	 );
	 $v_template->set('accounts', $v_accounts);
	 $p_form = preparePurgeForm($p_variables);
	 break;
      case 'import': 
	 $p_form = prepareImportForm($p_variables, $v_dummy);
	 break;
   }
   return $v_template;
}

function renderView($p_template, $p_form, $p_variables) {
   global $page_output, $notification;
   $page_output->header();
   $notification->notify(array('listeners' => 'status'));
   if ($p_template) {
      echo(
	 $p_template->fetch(
	    CLOUDBANK_TEMPLATES . '/accounts_for_statement.html'
	 )
      );
   }
   if ($p_form) {
      $p_form->renderActive(
	 new Horde_Form_Renderer(), $p_variables, 'statement.php', 'post'
      );
   }
   $page_output->footer();
}


/* main() */

$g_variables = &Horde_Variables::getDefaultVariables();
processActions($g_variables);
$g_template = prepareView($g_variables, $g_form);
renderView($g_template, $g_form, $g_variables);
