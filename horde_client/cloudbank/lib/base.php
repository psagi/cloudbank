<?php
/**
 * Cloudbank base application file.
 *
 * $Horde: cloudbank/lib/base.php,v 1.19.2.1 2009-08-24 13:57:40 jan Exp $
 *
 * This file brings in all of the dependencies that every Cloudbank script will
 * need, and sets up objects that all scripts use.
 */

// Check for a prior definition of HORDE_BASE (perhaps by an auto_prepend_file
// definition for site customization).
if (!defined('HORDE_BASE')) {
    @define('HORDE_BASE', dirname(__FILE__) . '/../..');
}

// Load the Horde Framework core, and set up inclusion paths.
require_once HORDE_BASE . '/lib/core.php';

// Registry.
$registry = &Registry::singleton();
if (is_a(($pushed = $registry->pushApp('cloudbank', !defined('AUTH_HANDLER'))), 'PEAR_Error')) {
    if ($pushed->getCode() == 'permission_denied') {
        Horde::authenticationFailureRedirect();
    }
    Horde::fatal($pushed, __FILE__, __LINE__, false);
}
$conf = &$GLOBALS['conf'];
@define('CLOUDBANK_TEMPLATES', $registry->get('templates'));

// Notification system.
$notification = &Notification::singleton();
$notification->attach('status');

// Define the base file path of Cloudbank.
@define('CLOUDBANK_BASE', dirname(__FILE__) . '/..');

// Cloudbank base library
require_once CLOUDBANK_BASE . '/lib/Cloudbank.php';

// Start output compression.
Horde::compressOutput();
