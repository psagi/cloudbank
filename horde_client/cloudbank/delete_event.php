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

@define('CLOUDBANK_BASE', dirname(__FILE__));
require_once CLOUDBANK_BASE . '/lib/base.php';

require_once HORDE_BASE . '/lib/Horde/Variables.php';
require_once CLOUDBANK_BASE . '/lib/Book.php';

/* main() */

$g_variables = &Variables::getDefaultVariables();
$g_account_id = $g_variables->get('account_id');
$g_event_id = $g_variables->get('event_id');

try {
   Book::Singleton()->deleteEvent($g_event_id);
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
}
catch (Exception $v_exception) {
   Cloudbank::PushError(Book::XtractMessage($v_exception));

   require CLOUDBANK_TEMPLATES . '/common-header.inc';
   require CLOUDBANK_TEMPLATES . '/menu.inc';
   require $registry->get('templates', 'horde') . '/common-footer.inc';
}
