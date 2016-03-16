<?php
/**
 * Cloudbank application API.
 *
 * This file defines Horde's core API interface. Other core Horde libraries
 * can interact with Cloudbank_skel through this API.
 *
 * Copyright 2010-2012 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @package Cloudbank_skel
 */

/* Determine the base directories. */
if (!defined('CLOUDBANK_BASE')) {
    define('CLOUDBANK_BASE', __DIR__ . '/..');
}

if (!defined('HORDE_BASE')) {
    /* If Horde does not live directly under the app directory, the HORDE_BASE
     * constant should be defined in config/horde.local.php. */
    if (file_exists(CLOUDBANK_BASE . '/config/horde.local.php')) {
        include CLOUDBANK_BASE . '/config/horde.local.php';
    } else {
        define('HORDE_BASE', CLOUDBANK_BASE . '/..');
    }
}

/* Load the Horde Framework core (needed to autoload
 * Horde_Registry_Application::). */
require_once HORDE_BASE . '/lib/core.php';

class Cloudbank_Application extends Horde_Registry_Application
{
    /**
     */
    public $version = 'H5 (0.4)';

    /**
     */
    protected function _bootstrap()
    {
        $GLOBALS['injector']->bindFactory('Cloudbank_Driver', 'Cloudbank_Factory_Driver', 'create');
    }

    /**
     */
    public function menu($menu)
    {
        $menu->add(
	 Horde::url('accounts.php'), _("Accounts"), 'cloudbank-account'
        );
        $menu->add(
	 Horde::url('categories.php'), _("Categories"), 'cloudbank-category'
	);
        $menu->add(
	 Horde::url('statement.php'), _("Statement"),
	 'cloudbank-statement'
	);
    }
}
