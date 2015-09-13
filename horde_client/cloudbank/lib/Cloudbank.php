<?php
/**
 * Cloudbank Base Class.
 *
 * $Id: Cloudbank.php,v 1.4 2010/11/02 21:54:24 pety Exp pety $
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
      &$p_recordSet, $p_phpScript, $p_key, $p_label, $p_linkField,
      $p_exclusionFilter = NULL, $p_title =  NULL
   ) {
   /* Please note that p_exclusionFilter has to contain every field of the key,
      exlusion occurs if ANY of the fields match the provided value. */
      foreach ($p_recordSet as &$v_record) {
	 $v_isExcluded = false;
	 $v_showURL = Horde::url($p_phpScript);
	 foreach ($p_key AS $v_iDName => $v_fieldName) {
	    if (
	       !is_null($p_exclusionFilter) && (
		  $v_record[$v_fieldName] === $p_exclusionFilter[$v_fieldName]
	       )
	    ) {
	       $v_isExcluded = true;
	    }
	    else {
	       $v_showURL->add(array($v_iDName => $v_record[$v_fieldName]));
	    }
	 }
	 $v_record[$p_linkField] = (
	    $v_isExcluded ?
	    $v_record[$p_label] :
	    Horde::link(
	       $v_showURL, (is_null($p_title) ? $v_record[$p_label] : $p_title)
	    ) . $v_record[$p_label] . '</a>'
	 );
	    /* the actual display text is included too to be able to keep the
	       template-generated HTML clean */
      }
   }
   public static function AddIcons(
      &$p_recordSet, $p_iconFile, $p_conditionFilter
   ) {
   /* Please note that inclusion occurs if EVERY field of the record included in
      p_conditionFilter match the provided value */
      $v_icon = (
	 Horde_Themes_Image::tag($p_iconFile, array('alt' => 'Transfer'))
      );
      foreach ($p_recordSet as &$v_record) {
	 $v_isIncluded = true;
	 foreach ($p_conditionFilter AS $v_fieldName => $v_fieldValue) {
	    if ($v_record[$v_fieldName] !== $v_fieldValue) {
	       $v_isIncluded = false;
	    }
	 }
	 $v_record['account_icon'] = ($v_isIncluded ? $v_icon : NULL);
      }
   }
   public static function PushError($p_message) {
      global $notification;
      $notification->push($p_message, 'horde.error');
   }
}
