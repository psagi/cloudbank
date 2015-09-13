<?php
/**
 * $Id: delete_account.php,v 1.2 2010/11/02 21:51:59 pety Exp pety $
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
$g_account_type = $g_variables->get('account_type');

try {
   Book::Singleton()->deleteAccount($g_account_id);
   header(
      'Location: ' .
	 Horde::url(
	    (
	       $g_account_type == CloudBankConsts::LedgerAccountType_Account ?
	       	  'accounts.php' :
		  'categories.php'
	    ), true
	 )
   );
}
catch (Exception $v_exception) {
   Cloudbank::PushError(Book::XtractMessage($v_exception));
   $page_output->header();
   $notification->notify(array('listeners' => 'status'));
   $page_output->footer();
}
