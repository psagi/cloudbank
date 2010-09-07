<?php
/**
 * $Id$
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

//require_once HORDE_BASE . '/lib/Horde/Template.php';

/* main() */

$g_variables = &Variables::getDefaultVariables();
$g_event_id = $g_variables->get('event_id');
$g_account_id = $g_variables->get('account_id');

$g_isEdit = !empty($g_event_id);
$g_accountName = (
   Book::Singleton()->getAccountOrCategoryName(
      $g_account_id, CloudBankConsts::LedgerAccountType_Account
   )
);
$g_accountsAndCategories = Book::Singleton()->getAccountsAndCategories();

// build the form
$g_form = &new Horde_Form($g_variables, $g_accountName . '::Event', 'event');
$g_form->addVariable('Date', 'date', 'date', true);
$g_form->addVariable('Description', 'description', 'text', true);
$g_form->addHidden('', 'account_id', 'text', true);
$g_form->addVariable('Is it income?', 'is_income', 'boolean', true);
$g_form->addVariable(
   'To/From', 'other_account_id', 'enum', true, false, '',
   array($g_accountsAndCategories, true)
);
$g_form->addVariable('Amount', 'amount', 'number', true, false, '', array(2));
$g_form->addHidden('', 'event_id', 'text', false);
$g_form->addHidden('', 'old_date', 'date', false);
$g_form->addHidden('', 'old_description', 'text', false);
$g_form->addHidden('', 'old_is_income', 'boolean', false);
$g_form->addHidden('', 'old_other_account_id', 'text', false);
$g_form->addHidden('', 'old_amount', 'number', false, true, '', array(2));

if ($g_form->validate($g_variables)) {	// submitted -> process
   if ($g_isEdit) {
      Book::Singleton()->modifyEvent($g_variables);
   } 
   else {
      Book::Singleton()->createEvent($g_variables);
   }
   header(
      'Location: ' .
	 Util::addParameter(
	    Horde::applicationUrl('events.php', true),
	    array(
	       'ledger_account_id' => $g_account_id,
	       'ledger_account_type' => (
		  CloudBankConsts::LedgerAccountType_Account
	       )
	    ), NULL, false
	 )
   );
   exit;
}
else {	// render
   if (!$g_isEdit) {
      $g_variables->set('date', strftime('%Y-%m-%d'));
   }
   else {
      Book::Singleton()->populateEventForm(
	 $g_variables, $g_account_id, $g_event_id
      );
   }
   $title = ($g_isEdit ? 'Edit Event' : 'Add Event');
   require CLOUDBANK_TEMPLATES . '/common-header.inc';
   require CLOUDBANK_TEMPLATES . '/menu.inc';
   $g_form->renderActive(
      new Horde_Form_Renderer(), $g_variables, 'event.php', 'post'
   );
   require $registry->get('templates', 'horde') . '/common-footer.inc';
}
