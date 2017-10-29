<?php
/**
 * $Id: delete_event.php,v 1.1 2010/10/24 17:24:39 pety Exp pety $
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
$g_event_id = $g_variables->get('event_id');
$g_limitMonth = $g_variables->get('limit_month');

try {
   Book::Singleton()->deleteEvent($g_event_id);
   header(
      'Location: ' .
	 Horde::url('events.php', true)->add(
	    array(
	       'ledger_account_id' => $g_account_id,
	       'ledger_account_type' => (
		  CloudBankConsts::LedgerAccountType_Account
	       ), 'limit_month' => $g_limitMonth
	    ), NULL, false
	 )
   );
}
catch (Exception $v_exception) {
   Cloudbank::PushError(Book::XtractMessage($v_exception));

   $page_output->header();
   $notification->notify(array('listeners' => 'status'));
   $page_output->footer();
}
