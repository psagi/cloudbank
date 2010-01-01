<?php
   require_once(dirname(__FILE__) . '/LedgerAccount.php');

   /* Hardcoded description of the DB schema. These should be generated from a
      common description. */
   class SchemaDef {
      /* Constants cannot have complex type - as of now - that is why they are
	 defined as static properties */
      private static $r_createSchemaStatements = array(
	 'ledger_account' => '
	    CREATE TABLE ledger_account (
	       id NUMERIC(39) NOT NULL PRIMARY KEY,
	       name VARCHAR(32) NOT NULL UNIQUE,
	       type VARCHAR(16) NOT NULL
	    )
	 ', 
	 'event' => '
	    CREATE TABLE event (
	       id NUMERIC(39) NOT NULL PRIMARY KEY, date DATE NOT NULL,
	       description VARCHAR(32) NOT NULL,
	       credit_ledger_account_id NOT NULL REFERENCES ledger_account,
	       debit_ledger_account_id NOT NULL REFERENCES ledger_account,
	       amount NUMERIC(16,2) NOT NULL
	    )
	 '
      );
      private static $r_metadata = array(
	 array(
	    'name' => 'ledger_account',
	    'columns' => array('id', 'name', 'type'), 'PK' => 'id'
	 ), array(
	    'name' => 'event',
	    'columns' => array(
	       'id', 'date', 'description', 'credit_ledger_account_id',
	       'debit_ledger_account_id', 'amount'
	    ), 'PK' => 'id',
	    'FK' => array(
	       'from' => 'credit_ledger_account_id', 'to' => 'ledger_account'
	    )
	 )
      );
      private static $r_containmentMetadata = array(
	 array('parent' => 'ledger_account', 'child' => 'event')
      );
      private static $r_dASQuery = array(
	 'query' => '
	    SELECT
	       la.id, la.name, la.type, e.id, e.date, e.description,
	       e.debit_ledger_account_id, e.amount
	    FROM ledger_account la, event e
	    WHERE e.credit_ledger_account_id = la.id
	 ', 
	 'array' => array(
	    'ledger_account.id', 'ledger_account.name', 'ledger_account.type',
	    'event.id', 'event.date', 'event.description',
	    'event.debit_ledger_account_id', 'event.amount'
	 )
      );

      public static function CreateSchemaStatements() {
	 return self::$r_createSchemaStatements;
      } 
      public static function Metadata() { return self::$r_metadata; }
      public static function ContainmentMetadata() {
	 return self::$r_containmentMetadata;
      }
      public static function DASQuery() { return self::$r_dASQuery; }

      private function __construct() { } // to prevent creating an instance
   }
?>
