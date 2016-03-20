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
	       UNIQUE (name, type),
	       CHECK (type IN (\'Account\', \'Category\', \'Beginning\'))
	    )
	 ',	/* Account types should have been referenced from the constants
		  declared above, but PHP can not do string concatenation in a
		  constant expression - as of now */
	 'event' => '
	    CREATE TABLE event (
	       id VARCHAR(39) NOT NULL PRIMARY KEY, date DATE NOT NULL,
	       description VARCHAR(32) NOT NULL,
	       debit_ledger_account_id VARCHAR(39)
		  NOT NULL REFERENCES ledger_account,
	       credit_ledger_account_id VARCHAR(39)
		  NOT NULL REFERENCES ledger_account,
	       amount NUMERIC(16,2) NOT NULL,
	       statement_item_id VARCHAR(16),
	       is_cleared BOOLEAN NOT NULL,
	       CHECK (amount >= 0), 
	       CHECK (debit_ledger_account_id <> credit_ledger_account_id)
	    )
	 ', 
	 'event_idx_debit_ledger_account_id' => '
	    CREATE INDEX event_idx_debit_ledger_account_id ON event(
	       debit_ledger_account_id
	    )
	 ',
	 'event_idx_credit_ledger_account_id' => '
	    CREATE INDEX event_idx_credit_ledger_account_id ON event(
	       credit_ledger_account_id
	    )
	 ',
	 'statement_item' => '
	    CREATE TABLE statement_item (
	       id VARCHAR(16) NOT NULL PRIMARY KEY,
	       ledger_account_name VARCHAR(32) NOT NULL,
	       item_type CHARACTER(1) NOT NULL, date DATE NOT NULL,
	       description VARCHAR(256), amount NUMERIC(16,2) NOT NULL,
	       CHECK (item_type IN (\'O\', \'E\', \'C\'))
	    )
	 ', 
	 'account_events' => '
	    CREATE VIEW account_events AS 
	       SELECT 
		  la.id AS ledger_account_id, la.name AS ledger_account_name,
		  la.type AS ledger_account_type, e.id AS id, e.date AS date,
		  e.description AS description, e.amount AS amount,
		  e.statement_item_id AS statement_item_id,
		  e.is_cleared AS is_cleared,
		  o_la.id AS other_ledger_account_id,
		  o_la.name AS other_ledger_account_name,
		  o_la.type AS other_ledger_account_type
	       FROM event e, ledger_account la, ledger_account o_la
	       WHERE 
                  e.debit_ledger_account_id = la.id AND
                  e.credit_ledger_account_id = o_la.id
	       UNION ALL
	       SELECT 
		  la.id AS ledger_account_id, la.name AS ledger_account_name,
		  la.type AS ledger_account_type, e.id AS id, e.date AS date,
		  e.description AS description, -e.amount AS amount,
		  e.statement_item_id AS statement_item_id,
		  e.is_cleared AS is_cleared,
		  o_la.id AS other_ledger_account_id,
		  o_la.name AS other_ledger_account_name,
		  o_la.type AS other_ledger_account_type
	       FROM event e, ledger_account la, ledger_account o_la
	       WHERE 
                  e.credit_ledger_account_id = la.id AND
                  e.debit_ledger_account_id = o_la.id
	 ',
	 'statement_item_unmatched' => '
	    CREATE VIEW statement_item_unmatched AS
	       SELECT
		  si.id, si.ledger_account_name, la.id as ledger_account_id,
		  si.item_type, si.date, si.description, si.amount
	       FROM statement_item si, ledger_account la
	       WHERE
		  si.item_type = "E" AND
		  la.name = si.ledger_account_name AND
		  NOT EXISTS (
		     SELECT 1
		     FROM account_events ae
		     WHERE
			ae.is_cleared = 0 AND
			si.ledger_account_name = ae.ledger_account_name AND
			ae.ledger_account_type = "Account" AND
			si.id = ae.statement_item_id
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
	 return (is_numeric($p_amount) && (strpos($p_amount, 'x') === FALSE));
      }
      public static function IsValidLedgerAccountPair(
	 $p_debitLedgerAccountID, $p_creditLedgerAccountID
      ) {
	 return ($p_debitLedgerAccountID <> $p_creditLedgerAccountID);
      }
      public static function IsValidStatementItemIDInEvent($p_statement_item_id) {
	 return self::CheckStrLength($p_statement_item_id, 0, 16);
      }
      public static function IsValidStatementItemID($p_statement_item_id) {
	 return self::CheckStrLength($p_statement_item_id, 1, 16);
      }
      public static function IsValidStatementItemType($p_item_type) {
	 return ($p_item_type == 'O' || $p_item_type == 'E' || $p_item_type == 'C');
      }
      public static function IsValidStatementItemDescription($p_item_description) {
	 return self::CheckStrLength($p_item_description, 0, 256);
      }

      private function __construct() { } // to prevent creating an instance
   }
?>
