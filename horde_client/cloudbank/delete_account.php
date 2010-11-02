<?php
/**
 * $Id: delete_account.php,v 1.1 2010/10/24 17:25:26 pety Exp pety $
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
$g_account_type = $g_variables->get('account_type');

Book::Singleton()->deleteAccount($g_account_id);
header(
   'Location: ' .
      Horde::applicationUrl(
	 (
	    $g_account_type == CloudBankConsts::LedgerAccountType_Account ?
	       'accounts.php' :
	       'categories.php'
	 ), true
      )
);
