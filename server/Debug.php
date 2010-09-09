<?php
   class Debug {
      public static function Singleton() {
         static $v_instance = NULL;
         if (!isset($v_instance)) {
            $v_instance = new Debug;
         }
         return $v_instance;
      }

      public function log($p_message) {
	 if ($this->r_isOn) {
	    error_log(
	       strftime('%b %e %T') . ' ' . $p_message . "\n", 3,
	       $this->r_logFile
	    );
	 }
      }

      private function __construct() {
	 $v_conf = parse_ini_file(dirname(__FILE__) . '/../conf/cloudbank.ini');
	 $this->r_isOn = ($v_conf['debug'] == 'on');
	 $this->r_logFile = $v_conf['log_file'];
      }
      private function __clone() { }

      private $r_isOn;
      private $r_logFile;
   }
?>
