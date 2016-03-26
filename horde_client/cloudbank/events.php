<?php
/**
 * $Id: events.php,v 1.7 2012/01/15 08:37:53 pety Exp pety $
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

function populateStatementItemsTemplateIf($p_account_id) {
   if (!Book::Singleton()->isThereStatementForAccount($p_account_id)) {
      return NULL;
   }
   $v_statementItems = (
      Book::Singleton()->getUnmatchedStatementItems($p_account_id)
   );
   Book::SortResultSet($v_statementItems, 'date', TRUE);
   $v_clearedOrMatchedBalance = (
      Book::Singleton()->getClearedOrMatchedBalance($p_account_id)
   );
   $v_closingStatementItem = (
      Book::Singleton()->getClosingBalance($p_account_id)
   );
   $v_template = new Horde_Template;
   $v_template->set('statement_items', $v_statementItems);
   $v_template->set(
      'cleared_or_matched_balance',
      Book::FormatAmount($v_clearedOrMatchedBalance)
   );
   $v_template->set('statement_closing', $v_closingStatementItem);
   $v_template->set(
      'amount_left',
      Book::FormatAmount(
	 $v_clearedOrMatchedBalance - $v_closingStatementItem['amount']
      )
   );
//var_dump($v_template);
   return $v_template;
}


/* main() */

$g_variables = &Horde_Variables::getDefaultVariables();
$g_id = $g_variables->get('ledger_account_id');
$g_type = $g_variables->get('ledger_account_type');
$g_limitMonth = $g_variables->get('limit_month');
if (!$g_limitMonth) {
   $g_limitMonth = Book::PreviousMonth(NULL);
}

$g_isError = FALSE;
try {
   $g_accountOrCategoryName = (
      Book::Singleton()->getAccountOrCategoryName($g_id, $g_type)
   );
   $g_accountOrCategoryIcon = (
      Book::Singleton()->getAccountOrCategoryIcon($g_type)
   );
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
	 'amount' => 'amount', 'is_cleared' => 'is_cleared',
	 'statement_item_id' => 'statement_item_id', 'event_id' => 'id',
	 'account_type' => 'account_type', 'account_name' => 'account_name',
	 'other_account_type' => 'other_account_type'
	    /* Note that this field is not required in the link, but needed for
	    the exclusion filter to work */
      ), 'description', 'description_link',
      array(
	 'date' => NULL, 'description' => NULL, 'account_id' => NULL,
	 'other_account_id' => NULL, 'amount' => NULL, 'is_cleared' => NULL,
	 'statement_item_id' => NULL, 'id' => NULL,
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
	    /* Note that this field is not required in the link, but needed for
	    the exclusion filter to work */
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
      $g_events, 'right_arrow.png', 'Transfer', 'account_icon',
      array('other_account_type' => CloudBankConsts::LedgerAccountType_Account)
   );
   CloudBank::AddIcons(
      $g_events, 'checkmark.png', 'Cleared', 'cleared_icon', array('is_cleared' => true)
   );
   Book::SortResultSet($g_events, 'date', TRUE);
   $g_total = Book::Singleton()->getAccountOrCategoryBalance($g_id);

   $g_template = &new Horde_Template;
   $g_template->set(
      'new_event_link', (
	 $g_type == CloudBankConsts::LedgerAccountType_Account ? (
	    Horde::link(
	       Horde::url('event.php')->add(
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
      'more_events_link', (
	 Horde::link(
	    Horde::url('events.php')->add(
	       array(
		  'ledger_account_id' => $g_id,
		  'ledger_account_type' => $g_type,
		  'limit_month' => Book::PreviousMonth($g_limitMonth)
	       )
	    ), 'More...'
	 ) . 'More...</a>'
      )
   );

   $title = $g_accountOrCategoryName;

   $g_statementItemsTemplate = NULL;
   if ($g_type == CloudBankConsts::LedgerAccountType_Account) {
      $g_statementItemsTemplate = populateStatementItemsTemplateIf($g_id);
   }
}
catch (Exception $v_exception) {
   Cloudbank::PushError(Book::XtractMessage($v_exception));
   $g_isError = TRUE;
}

$page_output->header();
$notification->notify(array('listeners' => 'status'));
if (!$g_isError) {
   echo $g_template->fetch(CLOUDBANK_TEMPLATES . '/events.html');
   if ($g_statementItemsTemplate) {
//var_dump($g_statementItemsTemplate);
//$g_statementItemsTemplate->setOption('debug', true);
      echo(
	 $g_statementItemsTemplate->fetch(
	    CLOUDBANK_TEMPLATES . '/statement_items.html'
	 )
      );
   }
}
$page_output->footer();
