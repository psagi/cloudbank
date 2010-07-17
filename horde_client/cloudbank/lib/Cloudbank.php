<?php
/**
 * Cloudbank Base Class.
 *
 * $Id$
 *
 * Copyright 2007-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Peter Sagi <psagi@freemail.hu>
 * @package Cloudbank
 */
class Cloudbank {

   public static function AddLinks(
      &$p_recordSet, $p_phpScript, $p_key, $p_label, $p_exclusionFilter = NULL
   ) {
   /* Please note that p_exclusionFilter has to contain every field of the key,
      exlusion occurs if ANY of the fields match the provided value. */
      foreach ($p_recordSet as &$v_record) {
	 $v_isExcluded = false;
	 $v_showURL = Horde::applicationUrl($p_phpScript);
	 foreach ($p_key AS $v_iDName => $v_fieldName) {
	    if (
	       !is_null($p_exclusionFilter) && (
		  $v_record[$v_fieldName] === $p_exclusionFilter[$v_fieldName]
	       )
	    ) {
	       $v_isExcluded = true;
	    }
	    else {
	       $v_showURL = (
		  Util::addParameter(
		     $v_showURL, array($v_iDName => $v_record[$v_fieldName])
		  )
	       );
	    }
	 }
	 $v_record['link'] = (
	    $v_isExcluded ?
	    $v_record[$p_label] :
	    Horde::link($v_showURL, $v_record[$p_label]) . $v_record[$p_label] .
	       '</a>'
	 );
	    /* the actual display text is included too to be able to keep the
	       template-generated HTML clean */
      }
   }
   public static function AddIcons(
      &$p_recordSet, $p_iconFile, $p_imagePool, $p_conditionFilter
   ) {
   /* Please note that inclusion occurs if EVERY field of the record included in
      p_conditionFilter match the provided value */
      global $registry;
      $v_icon = (
	 Horde::img(
	    $p_iconFile, 'Transfer', '', $registry->getImageDir($p_imagePool)
	 )
      );
      foreach ($p_recordSet as &$v_record) {
	 $v_isIncluded = true;
	 foreach ($p_conditionFilter AS $v_fieldName => $v_fieldValue) {
	    if ($v_record[$v_fieldName] !== $v_fieldValue) {
	       $v_isIncluded = false;
	    }
	 }
	 $v_record['icon'] = ($v_isIncluded ? $v_icon : NULL);
      }
   }

    /**
     * Build Cloudbank's list of menu items.
     */
    function getMenu($returnType = 'object')
    {
        global $conf, $registry, $browser, $print_link;

        require_once 'Horde/Menu.php';

        $menu = new Menu(HORDE_MENU_MASK_ALL);
        $menu->add(
	 Horde::applicationUrl('accounts.php'), _("Accounts"), 'account.png',
	 $registry->getImageDir('cloudbank')
        );

        if ($returnType == 'object') {
            return $menu;
        } else {
            return $menu->render();
        }
    }

}
