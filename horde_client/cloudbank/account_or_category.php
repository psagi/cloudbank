<?php
/**
 * $Id: account.php,v 1.2 2010/10/23 19:30:31 pety Exp $
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
require_once HORDE_BASE . '/lib/Horde/Form.php';
require_once HORDE_BASE . '/lib/Horde/Form/Renderer.php';
require_once CLOUDBANK_BASE . '/lib/Book.php';

/* main() */

$g_variables = &Variables::getDefaultVariables();
$g_account_id = $g_variables->get('account_id');
//$g_account_name = $g_variables->get('account_name');
//$g_account_type = $g_variables->get('account_type');

$g_isEdit = !empty($g_account_id);

// build the form
$g_form = &new Horde_Form($g_variables, 'Account', 'account');
$g_form->addVariable('Name', 'name', 'text', true);
$g_form->addVariable(
   'Beginning balance', 'beginning_balance', 'number', true, false, '', 2
);
$g_form->addHidden('', 'account_id', 'text', false);
$g_form->addHidden('', 'old_name', 'text', false);
$g_form->addHidden('', 'old_beginning_balance', 'number', false);

//print $g_form->isSubmitted();
if ($g_form->validate($g_variables)) {	// submitted -> process
   if ($g_isEdit) {
      Book::Singleton()->modifyAccount($g_variables);
   } 
   else {
      Book::Singleton()->createAccount($g_variables);
   }
   header('Location: ' . Horde::applicationUrl('accounts.php', true));
   exit;
}
else {	// render
//print 'Validation failed';
   if ($g_isEdit) {
      Book::PopulateAccountForm($g_variables);
   }
   $title = ($g_isEdit ? 'Edit Account' : 'Add Account');
   require CLOUDBANK_TEMPLATES . '/common-header.inc';
   require CLOUDBANK_TEMPLATES . '/menu.inc';
   $g_form->renderActive(
      new Horde_Form_Renderer(), $g_variables, 'account.php', 'post'
   );
   require $registry->get('templates', 'horde') . '/common-footer.inc';
}
