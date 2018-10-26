<?php
/**
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

function prepareForm($p_variables) {
   $v_account_name = $p_variables->get('account_name');
   $v_form = (
      new Horde_Form(
	 $p_variables, $v_account_name . '::Reconcile to balance',
	 'reconcile_to_balance'
      )
   );
   $v_form->addHidden('', 'account_id', 'text', false);
   $v_form->addHidden('', 'account_name', 'text', false);
   $v_form->addHidden('', 'balance', 'text', false);
   $v_form->addHidden('', 'limit_month', 'text', false);
   $v_form->addVariable('New amount', 'new_amount', 'text', true);
   return $v_form;
}

function processActions(&$p_variables, &$p_form) {
   if ($p_form->validate($p_variables)) {	// submitted -> process
      header(
	 'Location: ' .
     	    Horde::url('event.php', true)->add(
	       array(
		  'date' => strftime(Book::DateFormat),
		  'amount' => (
		     $p_variables->get('balance') -
		     $p_variables->get('new_amount')
		  ), 'account_id' => $p_variables->get('account_id'),
		  'account_name' => $p_variables->get('account_name'),
		  'limit_month' => $p_variables->get('limit_month')
	       ), NULL, false
	    )
      );
      exit;
//print "submitted/after create/modifyEvent(): " . $p_variables->get('amount');
   }
}

function renderView(&$p_form, $p_variables) {
   global $title, $page_output, $notification;
   $title = ('Reconcile to balance');
   $page_output->header();
   $notification->notify(array('listeners' => 'status'));
   $p_form->renderActive(
      new Horde_Form_Renderer(), $p_variables, 'reconcile_to_balance.php',
      'post'
   );
   $page_output->footer();
}


/* main() */

$g_variables = &Horde_Variables::getDefaultVariables();

$g_form = prepareForm($g_variables);
processActions($g_variables, $g_form);
renderView($g_form, $g_variables);
