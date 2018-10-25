#!/usr/bin/php

<?php
#   require_once(dirname(__FILE__) . '/../server/
   require_once('SCA/SCA.php');

   $v_eventService = SCA::getService('../server/EventService.php');
   while (($v_record = fgetcsv(STDIN, 0, '|')) !== FALSE) {
      $v_eventService->createEvent(
       	 $v_record[0], $v_record[1], $v_record[2], $v_record[3], $v_record[4],
	 $v_record[5], $v_record[6], $v_record[7]
      );
   }
?>
