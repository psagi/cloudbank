#!/usr/bin/php
<?php
   require_once(dirname(__FILE__) . '/../conf/db.conf');

   try {
      $v_dBConnection = new PDO($g_Dsn, $g_DBUser, $g_DBPasswd);
   }
   catch (PDOException $v_exception) {
      exit("Connection failed: $v_exception->getMessage()\n");
   }
   foreach (glob(dirname(__FILE__) . '/db/*.sql') as $v_SQLFileName) {
      try {
	 $v_dBConnection->exec(file_get_contents($v_SQLFileName));
      }
      catch (PDOException $v_exception) {
	 exit(
	    "Execution of SQL statement failed: $v_exception->getMessage()\n"
	 );
      }
   }
?>
