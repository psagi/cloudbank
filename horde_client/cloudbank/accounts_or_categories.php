<?php
/**
 * $Id: accounts.php,v 1.2 2010/07/17 20:47:54 pety Exp pety $
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
require_once CLOUDBANK_BASE . '/lib/Book.php';

/* main() */

$g_accounts = Book::Singleton()->getAccountsWBalance();
CloudBank::AddLinks(
   $g_accounts, 'events.php',
   array('ledger_account_id' => 'id', 'ledger_account_type' => 'type'), 'name',
   'account_link'
);
Book::SortResultSet($g_accounts, 'name');
$g_total = Book::Singleton()->getAccountsTotal();
$g_template = &new Horde_Template;
$g_template->set('accounts', $g_accounts);
$g_template->set('total', $g_total);

$title = _("Accounts");

require CLOUDBANK_TEMPLATES . '/common-header.inc';
require CLOUDBANK_TEMPLATES . '/menu.inc';
echo $g_template->fetch(CLOUDBANK_TEMPLATES . '/accounts.html');
require $registry->get('templates', 'horde') . '/common-footer.inc';
