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
	 return $p_rootDO;
      }
   }
?>
