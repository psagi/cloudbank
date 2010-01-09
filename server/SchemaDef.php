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
      public static function IsValidLedgerAccountName($p_name) {
	 $v_length = strlen($p_name);
	 return ($v_length > 0 && $v_length <= 32);
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

      private function __construct() { } // to prevent creating an instance
   }
?>
