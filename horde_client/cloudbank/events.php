<?php
/**
 * $Id: events.php,v 1.5 2010/11/02 21:52:29 pety Exp pety $
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

require_once HORDE_BASE . '/lib/Horde/Template.php';
require_once HORDE_BASE . '/lib/Horde/Variables.php';
require_once CLOUDBANK_BASE . '/lib/Book.php';

/* main() */

$g_variables = &Variables::getDefaultVariables();
$g_id = $g_variables->get('ledger_account_id');
$g_type = $g_variables->get('ledger_account_type');
$g_limitMonth = $g_variables->get('limit_month');

$g_accountOrCategoryName = (
   Book::Singleton()->getAccountOrCategoryName($g_id, $g_type)
);
$g_accountOrCategoryIcon = Book::Singleton()->getAccountOrCategoryIcon($g_type);
if (empty($g_limitMonth)) {
   $g_currentTime = new DateTime();
   $g_limitMonth = $g_currentTime->format(Book::MonthFormat);
}
$g_events = (
   Book::Singleton()->getEvents(
      $g_id, $g_type, $g_accountOrCategoryName, $g_limitMonth
   )
);
CloudBank::AddLinks(
   $g_events, 'event.php',
   array(
      'date' => 'date', 'description' => 'description', 
      'account_id' => 'account_id', 'other_account_id' => 'other_account_id',
      'amount' => 'amount', 'event_id' => 'id',
      'account_type' => 'account_type', 'account_name' => 'account_name',
      'other_account_type' => 'other_account_type'
	 /* Note that this field is not required in the link, but needed for the
	    exclusion filter to work */
   ), 'description', 'description_link',
   array(
      'date' => NULL, 'description' => NULL, 'account_id' => NULL,
      'other_account_id' => NULL, 'amount' => NULL, 'id' => NULL,
      'account_type' => CloudBankConsts::LedgerAccountType_Category,
      'account_name' => NULL,
      'other_account_type' => CloudBankConsts::LedgerAccountType_Beginning
   )
);
CloudBank::AddLinks(
   $g_events, 'delete_event.php',
   array(
      'event_id' => 'id', 'account_id' => 'account_id',
      'other_account_type' => 'other_account_type'
	 /* Note that this field is not required in the link, but needed for the
	    exclusion filter to work */
   ), 'delete_icon', 'delete_icon_link',
   array(
      'id' => NULL, 'account_id' => NULL,
      'other_account_type' => CloudBankConsts::LedgerAccountType_Beginning
   ), 'Delete'
);
CloudBank::AddLinks(
   $g_events, 'events.php',
   array(
      'ledger_account_id' => 'other_account_id',
      'ledger_account_type' => 'other_account_type'
   ), 'other_account_name', 'account_link',
   array(
      'other_account_id' => NULL,
      'other_account_type' => CloudBankConsts::LedgerAccountType_Beginning
   )
);
CloudBank::AddIcons(
   $g_events, 'right_arrow.png', 'cloudbank',
   array('other_account_type' => CloudBankConsts::LedgerAccountType_Account)
);
Book::SortResultSet($g_events, 'date', TRUE);
$g_total = Book::Singleton()->getAccountOrCategoryBalance($g_id);

$g_template = &new Horde_Template;
$g_template->set(
   'new_event.link', (
      $g_type == CloudBankConsts::LedgerAccountType_Account ? (
	 Horde::link(
	    Util::addParameter(
	       Horde::applicationUrl('event.php'),
	       array(
		  'account_id' => $g_id,
		  'account_name' => $g_accountOrCategoryName
	       )
	    ), 'New'
	 ) . 'New</a>'
      ) :
      ''
   )
);
$g_template->set('account_or_category_name', $g_accountOrCategoryName);
$g_template->set('account_or_category_icon', $g_accountOrCategoryIcon);
$g_template->set('limit_month', $g_limitMonth);
$g_template->set('events', $g_events);
$g_template->set('total', $g_total);
$g_template->set(
   'more_events.link', (
      Horde::link(
	 Util::addParameter(
	    Horde::applicationUrl('events.php'),
	    array(
	       'ledger_account_id' => $g_id, 'ledger_account_type' => $g_type,
	       'limit_month' => Book::PreviousMonth($g_limitMonth)
	    )
	 ), 'More...'
      ) . 'More...</a>'
   )
);

$title = $g_accountOrCategoryName;

require CLOUDBANK_TEMPLATES . '/common-header.inc';
require CLOUDBANK_TEMPLATES . '/menu.inc';
echo $g_template->fetch(CLOUDBANK_TEMPLATES . '/events.html');
require $registry->get('templates', 'horde') . '/common-footer.inc';
