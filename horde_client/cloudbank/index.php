<?php
/**
 * $Horde: cloudbank/index.php,v 1.13 2009-01-06 18:02:10 jan Exp $
 *
 * Copyright 2007-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Your Name <you@example.com>
 */

@define('CLOUDBANK_BASE', dirname(__FILE__));
$cloudbank_configured = is_readable(CLOUDBANK_BASE . '/config/conf.php');

if (!$cloudbank_configured) {
    require CLOUDBANK_BASE . '/../lib/Test.php';
    Horde_Test::configFilesMissing(
      'Cloudbank', CLOUDBANK_BASE, array('conf.php')
   );
}

require CLOUDBANK_BASE . '/accounts.php';
