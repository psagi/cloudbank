<?php
   require_once('Debug.php');

   class Util {
      public static function ToSDO(
	 $p_resultSet, $p_rootDO, $p_elementTypeName, $p_mapping
      ) {
	 Debug::Singleton()->log(
	    'Util::ToSDO(): $p_rootDO = ' .
	       SDO_Model_ReflectionDataObject::export(
		  new SDO_Model_ReflectionDataObject($p_rootDO), true
	       )
	 );
	 foreach ($p_resultSet as $v_record) {
	    $v_result_DO = (
	       is_null($p_elementTypeName) ?
	       $p_rootDO :
	       $p_rootDO->createDataObject($p_elementTypeName)
	    );
	    foreach ($p_mapping as $v_dBField => $v_sDOField) {
	       $v_result_DO[$v_sDOField] = $v_record[$v_dBField];
	       Debug::Singleton()->log(
		  "Util::ToSDO(): mapped: " .
		     "\$v_record['$v_dBField'] = {$v_record[$v_dBField]} => " .
		     "\$v_result_DO['$v_sDOField'] = " .
		     "{$v_result_DO[$v_sDOField]}"
	       );
	    }
	    if (is_null($p_elementTypeName)) break;
	 }
//echo('CloudBankServer::ToSDO(): $p_rootDO = '); var_dump($p_rootDO);
	 return (
	    count($p_resultSet) ?
	    $p_rootDO :
	    (is_null($p_elementTypeName) ? NULL : $p_rootDO)
	 );
      }
      /* This is a wrapper for PHP builtin str_getcsv() as it has a bug
	 (https://bugs.php.net/bug.php?id=55507) of removing the first character
	 of the field if it is invalid in the current locale */
      public static function ParseCSVLine($p_line) {
      	 Debug::Singleton()->log("Util::ParseCSVLine($p_line)");
	 $v_line = preg_replace('/,/', ',_', $p_line);
	    /* ugly workaround to make the first character of the fields
	       non-vulnerable */
      	 Debug::Singleton()->log("Util::ParseCSVLine(): \$v_line = $v_line");
	 $v_csv_record_arr = str_getcsv($v_line);
	 foreach($v_csv_record_arr as &$v_field) {
	    $v_field = preg_replace('/^_/', '', $v_field);
	 }
	 return $v_csv_record_arr;
      }
   }
?>
