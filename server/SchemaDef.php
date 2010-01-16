<?php
   /* Hardcoded description of the DB schema. These should be generated from a
      common description. */
   class SchemaDef {
      /* Constants cannot have complex type - as of now - that is why they are
	 defined as static properties */
      private static $r_createSchemaStatements = array(
	 'ledger_account' => '
	    CREATE TABLE ledger_account (
	       id VARCHAR(39) NOT NULL PRIMARY KEY,
	       name VARCHAR(32) NOT NULL,
	       type VARCHAR(16) NOT NULL,
	       UNIQUE (name, type)
	    )
	 ', 
	 'event' => '
	    CREATE TABLE event (
	       id VARCHAR(39) NOT NULL PRIMARY KEY, date DATE NOT NULL,
	       description VARCHAR(32) NOT NULL,
	       credit_ledger_account_id VARCHAR(39)
		  NOT NULL REFERENCES ledger_account,
	       debit_ledger_account_id VARCHAR(39)
		  NOT NULL REFERENCES ledger_account,
	       amount NUMERIC(16,2) NOT NULL
	    )
	 '
      );

      public static function CreateSchemaStatements() {
	 return self::$r_createSchemaStatements;
      } 
      private static function CheckStrLength($p_str, $p_minLen, $p_maxLen) {
	 $v_length = strlen($p_str);
	 return ($v_length >= $p_minLen && $v_length <= $p_maxLen);
      }
      public static function IsValidLedgerAccountName($p_name) {
	 return self::CheckStrLength($p_name, 1, 32);
      }
      public static function IsValidDate($p_date) {
	 $v_dateComponents = strptime($p_date, '%Y-%m-%d');
	 return (
	    $v_dateComponents &&
	    checkdate(
	       $v_dateComponents['tm_mon']+1, $v_dateComponents['tm_mday'],
	       $v_dateComponents['tm_year']+1900 
	    ) && (strlen($v_dateComponents['unparsed']) == 0)
	 );
      }
      public static function IsValidEventDescription($p_description) {
	 return self::CheckStrLength($p_description, 1, 32);
      }
      public static function IsValidAmount($p_amount) {
	 return (!empty($p_amount) || $p_amount == 0);
      }

      private function __construct() { } // to prevent creating an instance
   }
?>
