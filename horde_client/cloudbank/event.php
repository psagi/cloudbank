<?php
/**
 * $Id: event.php,v 1.4 2011/01/30 21:29:23 pety Exp pety $
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

function prepareForm(&$p_variables) {
   $v_account_name = $p_variables->get('account_name');
   $v_form = new Horde_Form($p_variables, $v_account_name . '::Event', 'event');
   $v_date_form_variable = (
      $v_form->addVariable(
	 'Date', 'date', 'monthdayyear', true, false, null,
	 array('', '', true, null, Book::DateFormat)
      )
   );
//var_dump($v_date_form_variable->getValue($p_variables));
   Horde::log(
      "prepareForm(): \$v_date_form_variable->getValue() = " .
	 var_export($v_date_form_variable->getValue($p_variables), true),
      'DEBUG'
   );
   Horde::log(
      "prepareForm(): \$v_date_form_variable as string = " .
	 var_export(
	    $v_date_form_variable->getType()->formatDate(
	       $v_date_form_variable->getValue($p_variables)
	    ), true
      ), 'DEBUG'
   );
   $p_variables['date_str'] = (
      $v_date_form_variable->getType()->formatDate(
	 $v_date_form_variable->getValue($p_variables)
      )
   );
   $v_form->addVariable('Description', 'description', 'text', true);
   $v_form->addHidden('', 'account_id', 'text', false);
   $v_form->addHidden('', 'account_name', 'text', false);
   $v_form->addHidden('', 'limit_month', 'text', false);
   $v_form->addVariable('Is it income?', 'is_income', 'boolean', true);
   $v_accountsAndCategories = Book::Singleton()->getAccountsAndCategories();
   $v_form->addVariable(
      'To/From', 'other_account_id', 'enum', true, false, '',
      array($v_accountsAndCategories, true)
   );
   $v_form->addVariable('Quantity', 'quantity', 'text', false);
   $v_form->addVariable('Amount', 'amount', 'text', false);
   $v_form->addVariable('Is it cleared?', 'is_cleared', 'boolean', true);
   $v_form->addVariable(
      'Statement reference', 'statement_item_id', 'text', false
   );
   $v_form->addHidden('', 'event_id', 'text', false);
   $v_form->addHidden('', 'old_date_str', 'text', false);
   $v_form->addHidden('', 'old_description', 'text', false);
   $v_form->addHidden('', 'old_is_income', 'boolean', false);
   $v_form->addHidden('', 'old_other_account_id', 'text', false);
   $v_form->addHidden('', 'old_quantity', 'text', false);
   $v_form->addHidden('', 'old_amount', 'text', false);
   $v_form->addHidden('', 'old_is_cleared', 'boolean', false);
   $v_form->addHidden('', 'old_statement_item_id', 'text', false);
//var_dump($v_form->getVariables());
//Horde::log("just a test", "DEBUG");
   return $v_form;
}

function setDefaultValues(&$p_variables) {
   $p_variables->set('date', strftime(Book::DateFormat));
}

function processActions(&$p_variables, &$p_form) {
   /* + create new event (ID is empty)
	 + just render empty form (set defaults)
	 + form submitted (call server create, if OK redirect to events view)
	 + if error, render the form w/ data entered
      + create new event from statement data (ID is empty)
	 + render the form w/ data got in paramters (adjust is_income, amount)
	 + form submitted (call server create, if OK redirect to events view)
	 + if error, render the form w/ data entered
      + modify event (ID is filled)
	 + render the form w/ data got in paramters (adjust is_income, amount,
	    populate old_ variables)
	 + form submitted (call server modify, if OK redirect to events view)
	 + if error, render the form w/ data entered
   */
   $v_event_id = $p_variables->get('event_id');
   $v_isEdit = !empty($v_event_id);
   if ($p_form->validate($p_variables)) {	// submitted -> process
      $v_account_id = $p_variables->get('account_id');
      $v_limitMonth = $p_variables->get('limit_month');
      try {
	 if ($v_isEdit) {
	    Book::Singleton()->modifyEvent($p_variables);
	 } 
	 else {
//print "submitted/before createEvent(): " . $p_variables->get('amount');
	    Book::Singleton()->createEvent($p_variables);
	 }
	 header(
	    'Location: ' .
	       Horde::url('events.php', true)->add(
		  array(
		     'ledger_account_id' => $v_account_id,
		     'ledger_account_type' => (
		       	CloudBankConsts::LedgerAccountType_Account
		     ), 'limit_month' => $v_limitMonth
		  ), NULL, false
	       )
	 );
	 exit;
      }
      catch (Exception $v_exception) {
	 Cloudbank::PushError(Book::XtractMessage($v_exception));
      }
//print "submitted/after create/modifyEvent(): " . $p_variables->get('amount');
   }
   else {
      $v_isEmpty = !($p_variables->get('date'));
//print "not submitted/before PopulateEventForm():" . $p_variables->get('amount');
      if ($v_isEmpty) setDefaultValues($p_variables);
      else Book::Singleton()->PopulateEventForm($p_variables);
//print "not submitted/after PopulateEventForm():" . $p_variables->get('amount');
   }
   return $v_isEdit;
}

function renderView(&$p_form, $p_variables, $p_isEdit) {
   global $title, $page_output, $notification;
   $title = ($p_isEdit ? 'Edit Event' : 'Add Event');
   $page_output->header();
   $notification->notify(array('listeners' => 'status'));
   $p_form->renderActive(
      new Horde_Form_Renderer(), $p_variables, 'event.php', 'post'
   );
   $page_output->footer();
}


/* main() */

$g_variables = &Horde_Variables::getDefaultVariables();

$g_form = prepareForm($g_variables);
$g_isEdit = processActions($g_variables, $g_form);
renderView($g_form, $g_variables, $g_isEdit);
