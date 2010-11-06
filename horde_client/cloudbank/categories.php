<?php
/**
 * $Id: categories.php,v 1.5 2010/10/24 17:24:02 pety Exp $
 *
 * Copyright 2007-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Peter Sagi <psagi@freemail.hu>
 */

@define('CLOUDBANK_BASE', dirname(__FILE__));

require_once CLOUDBANK_BASE . '/lib/lib/CloudBankConsts.php';
$g_account_type = CloudBankConsts::LedgerAccountType_Category;

require CLOUDBANK_BASE . '/accounts_or_categories.php';
