<?php
/**
 * $Id: delete_event.php,v 1.1 2010/10/24 10:10:46 pety Exp $
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
