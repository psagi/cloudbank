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

function xtractEventID($p_variableName) {
   return Cloudbank::DecodeID(substr($p_variableName, 11));
}

function updateIsClearedAttributesIf($p_variables, $p_accountID) {
   $v_isUpdateDone = false;
   foreach($p_variables as $v_variable => $v_value) {
//echo "DEBUG: updateIsClearedAttributesIf(): $v_variable = $v_value<p>";
      if (strpos($v_variable, 'is_cleared_') === 0) {
//echo "DEBUG: updateIsClearedAttributesIf(): is_cleared. found<p>";
	 Book::Singleton()->updateIsClearedAttribute(
	    xtractEventID($v_variable), ($v_value == 1 ? 1 : 0), $p_accountID
	 );
	 $v_isUpdateDone = true;
      }
   }
   return $v_isUpdateDone;
}

function populateReconciliationTemplate($p_account_id) {
   $v_template = new Horde_Template;
   $v_clearedOrMatchedBalance = (
      Book::Singleton()->getClearedOrMatchedBalance($p_account_id)
   );
   $v_template->set(
      'cleared_or_matched_balance',
      Book::FormatAmount($v_clearedOrMatchedBalance)
   );
   if (Book::Singleton()->isThereStatementForAccount($p_account_id)) {
//echo "DEBUG: before getOpeningBalance()\n";
      $v_openingStatementItem = (
	 Book::Singleton()->getOpeningBalance($p_account_id)
      );
//echo "DEBUG: before getClosingBalance()\n";
      $v_closingStatementItem = (
	 Book::Singleton()->getClosingBalance($p_account_id)
      );
      $v_template->set('is_there_statement', true, true);
      $v_template->set('statement_opening', $v_openingStatementItem);
      $v_template->set('statement_closing', $v_closingStatementItem);
      $v_template->set(
	 'amount_left',
	 Book::FormatAmount(
	    $v_clearedOrMatchedBalance - $v_closingStatementItem['amount']
	 )
      );
   }
   else $v_template->set('is_there_statement', false, true);
//echo "DEBUG: before return\n";
   return $v_template;
}

function populateStatementItemsTemplateIf(
   $p_account_id, $p_accountName, $p_limitMonth
) {
   if (Book::Singleton()->isThereStatementForAccount($p_account_id)) {
      $v_template = new Horde_Template;
      $v_statementItems = (
	 Book::Singleton()->getUnmatchedStatementItems(
   	    $p_account_id, $p_accountName, $p_limitMonth
	 )
      );
      CloudBank::AddLinks(
	 $v_statementItems, 'event.php',
	 array(
	    'date' => 'date', 'description' => 'description_short', 
	    'amount' => 'amount',
	    'statement_item_id' => 'id', 'account_id' => 'account_id',
	    'account_type' => 'account_type', 'account_name' => 'account_name',
	    'limit_month' => 'limit_month',
	 ), 'description', 'description_link'
      );
      Book::SortResultSet($v_statementItems, 'date', TRUE);
      $v_template->set(
	 'match_link', (
   	    Horde::link(
   	       Horde::url('statement_item_match.php')->add(
     		  array('account_id' => $p_account_id)
       	       ), 'Update matching Events with Statement Item ID reference'
	    ) . 'Match</a>'
	 )
      );
      $v_template->set(
	 'clear_all_matched_link', (
	    Horde::link(
	       Horde::url('clear_all_matched_events.php')->add(
		  array('account_id' => $p_account_id)
	       ),
	       'Clear all matched Events and PURGE corresponding STATEMENT ' . 
		  'ITEMS'
	    ) . 'Clear all matched</a>'
	 )
      );
      $v_template->set('statement_items', $v_statementItems);
   }
   else return NULL;
//var_dump($v_template);
   return $v_template;
}


/* main() */

$g_variables = &Horde_Variables::getDefaultVariables();
//var_dump($g_variables);

/* process actions */
/* + just display the event list (we are not here via form submit)
      + no is_cleared.* form variables are set
   + update the is_cleared attribute of all events (we are here via submit)
      + if error occurs during the update, display the error and display the
	 event list (see above)
*/
$g_id = $g_variables->get('ledger_account_id');
try {
   if (updateIsClearedAttributesIf($g_variables, $g_id)) {
      Cloudbank::PushInfo("Cleared status updated");
   }
}
catch (Exception $v_exception) {
   Cloudbank::PushError(Book::XtractMessage($v_exception));
}
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
	 'account_id' => 'account_id', 'limit_month' => 'limit_month',
	 'other_account_id' => 'other_account_id', 'quantity' => 'quantity',
	 'amount' => 'amount', 'is_cleared' => 'is_cleared',
	 'statement_item_id' => 'statement_item_id', 'event_id' => 'id',
	 'account_type' => 'account_type', 'account_name' => 'account_name',
	 'other_account_type' => 'other_account_type'
	    /* Note that this field is not required in the link, but needed for
	    the exclusion filter to work */
      ), 'description', 'description_link',
      array(
	 'date' => NULL, 'description' => NULL, 'account_id' => NULL,
	 'limit_month' => NULL,
	 'other_account_id' => NULL, 'quantity' => NULL, 'amount' => NULL,
	 'is_cleared' => NULL,
	 'statement_item_id' => NULL, 'id' => NULL,
	 'account_type' => CloudBankConsts::LedgerAccountType_Category,
	 'account_name' => NULL,
	 'other_account_type' => CloudBankConsts::LedgerAccountType_Beginning
      )
   );
   if ($g_type == CloudBankConsts::LedgerAccountType_Account) {
      CloudBank::AddLinks(
	 $g_events, 'delete_event.php',
	 array(
	    'event_id' => 'id', 'account_id' => 'account_id',
	    'limit_month' => 'limit_month',
	    'other_account_type' => 'other_account_type'
	       /* Note that this field is not required in the link, but needed
		  for the exclusion filter to work */
	 ), 'delete_icon', 'delete_icon_link',
	 array(
	    'id' => NULL, 'account_id' => NULL, 'limit_month' => NULL,
	    'other_account_type' => CloudBankConsts::LedgerAccountType_Beginning
	 ), 'Delete'
      );
   }
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
   $g_total_arr = Book::Singleton()->getAccountOrCategoryBalance($g_id);

   $g_template = &new Horde_Template;
   $g_template->set(
      'self_url',
      Horde::url('events.php')->add(
	 array(
	    'ledger_account_id' => $g_id, 'ledger_account_type' => $g_type,
	    'limit_month' => $g_limitMonth
	 )
      )
   );
   $g_template->set(
      'new_event_link', (
	 $g_type == CloudBankConsts::LedgerAccountType_Account ? (
	    Horde::link(
	       Horde::url('event.php')->add(
		  array(
		     'account_id' => $g_id,
		     'account_name' => $g_accountOrCategoryName,
		     'limit_month' => $g_limitMonth
		  )
	       ), 'New (<Alt> + n)', '', '', '', '', 'n'
	    ) . 'New</a>'
	 ) :
	 ''
      )
   );
   $g_template->set('account_or_category_name', $g_accountOrCategoryName);
   $g_template->set(
      'is_account', $g_type == CloudBankConsts::LedgerAccountType_Account
   );
   $g_template->set('account_or_category_icon', $g_accountOrCategoryIcon);
   $g_template->set('limit_month', $g_limitMonth);
   $g_template->set('events', $g_events);
   $g_template->set('total_quantity', $g_total_arr['total_quantity']);
   $g_template->set('total', $g_total_arr['balance']);
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
   $g_template->set(
      'all_events_link', (
	 Horde::link(
	    Horde::url('events.php')->add(
	       array(
		  'ledger_account_id' => $g_id,
		  'ledger_account_type' => $g_type,
		  'limit_month' => 'all'
	       )
	    ), 'All'
	 ) . 'All</a>'
      )
   );

   $title = $g_accountOrCategoryName;

//echo "DEBUG: before populateReconciliationTemplate()\n";
   $g_reconciliationTemplate = populateReconciliationTemplate($g_id);
   $g_statementItemsTemplate = NULL;
   if ($g_type == CloudBankConsts::LedgerAccountType_Account) {
//echo "DEBUG: before populateStatementItemsTemplate()\n";
      $g_statementItemsTemplate = (
	 populateStatementItemsTemplateIf(
	    $g_id, $g_accountOrCategoryName, $g_limitMonth
	 )
      );
   }
//echo "DEBUG: before catch\n";
}
catch (Exception $v_exception) {
   Cloudbank::PushError(Book::XtractMessage($v_exception));
   $g_isError = TRUE;
}

/* render view */
$page_output->header();
$notification->notify(array('listeners' => 'status'));
if (!$g_isError) {
//$g_template->setOption('debug', true);
   echo $g_template->fetch(CLOUDBANK_TEMPLATES . '/events.html');
   echo (
      $g_reconciliationTemplate->fetch(
	 CLOUDBANK_TEMPLATES . '/reconciliation.html'
      )
   );
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
