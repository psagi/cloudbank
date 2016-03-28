<?php
   require_once(dirname(__FILE__) . '/../lib/CloudBankConsts.php');
   require_once('Debug.php');
//   require_once('Util.php');
   require_once('CloudBankServer.php');
   require_once('SchemaDef.php');
   include('SCA/SCA.php');

   /**
      @service
      @binding.soap
      @types http://pety.dynu.net/CloudBank/StatementService ../lib/StatementService.xsd
      @types http://pety.homelinux.org/CloudBank/LedgerAccountService ../lib/LedgerAccountService.xsd
   */
   class StatementService {
      private static function ToSDO(
         $p_resultSet, $p_setTypeName, $p_elementTypeName, $p_mapping
      ) {
	 return (
	    Util::ToSDO(
	       $p_resultSet,
	       SCA::createDataObject(
		  'http://pety.dynu.net/CloudBank/StatementService',
		  is_null($p_setTypeName) ? $p_elementTypeName : $p_setTypeName
	       ), // the root DO has to be created inside the SCA component
	       is_null($p_setTypeName) ? NULL : $p_elementTypeName, $p_mapping
	    )
	 );
      }

      public function __construct() {
	 $this->r_cloudBankServer = CloudBankServer::Singleton();
      }

      /**
	 @param Statement $p_statement http://pety.dynu.net/CloudBank/StatementService
	 @return boolean	Success
      */
      public function importStatement($p_statement) {
	 Debug::Singleton()->log(
	    'importStatement(' . var_export($p_statement, true) . ')'
	 );
	 $this->assertTableIsEmpty();
	 $this->r_cloudBankServer->beginTransaction();
	 $v_line_no = 0;
	 foreach($p_statement->StatementLine as $v_statement_line) {
	    ++$v_line_no;
	    $v_statement_item_arr = str_getcsv($v_statement_line);
	    if (count($v_statement_item_arr) == 0) continue;
	    $this->assertStatementItemIsValid(
	       $v_statement_item_arr, $v_line_no, $v_statement_line
	    );
	    $this->createStatementItem($v_statement_item_arr);
	 }
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }
      /**
	 @param string $p_eventID	The ID of the event
	 @return Event http://pety.homelinux.org/CloudBank/EventService
	     Event details
      */ /*
      public function getEvent($p_eventID) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertNonBeginningEventExists($p_eventID);
	 $v_event = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT
		     id, date, description, other_ledger_account_id,
		     other_ledger_account_name, other_ledger_account_type,
		     amount, statement_item_id, is_cleared
		  FROM account_events
		  WHERE id = :iD AND ledger_account_type = :ledgerAccountType
	       ',
	       array(
		  ':iD' => $p_eventID,
		  ':ledgerAccountType' =>
		     CloudBankConsts::LedgerAccountType_Account
	       )
	    )
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return (
	    self::ToSDO(
	       $v_event, NULL, 'Event',
	       array(
		  'id' => 'id', 'date' => 'date',
		  'description' => 'description',
		  'other_ledger_account_id' => 'other_account_id',
		  'other_ledger_account_name' => 'other_account_name',
		  'other_ledger_account_type' => 'other_account_type',
		  'amount' => 'amount',
		  'statement_item_id' => 'statement_item_id',
		  'is_cleared' => 'is_cleared'
	       )
	    )
	 );
      }
*/

      /**
	 @param string $p_accountID	\
	    The LedgerAccount the related items to be returned for
	 @return StatementItemSet http://pety.dynu.net/CloudBank/StatementService
	     Set of Statement Items
      */ 
      public function findUnmatchedItems($p_accountID) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->r_ledgerAccountService->assertAccountOrCategoryExists(
	    $p_accountID, CloudBankConsts::LedgerAccountType_Account
	 );
	 $v_statementItems = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT
		     id, ledger_account_name, item_type, date, description,
		     amount
		  FROM statement_item_unmatched
		  WHERE ledger_account_id = :accountID
	       ',
	       array(':accountID' => $p_accountID)
	    )
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return (
	    self::ToSDO(
	       $v_statementItems, 'StatementItemSet', 'StatementItem',
	       array(
		  'id' => 'id', 'ledger_account_name' => 'ledger_account_name',
		  'item_type' => 'item_type', 'date' => 'date',
		  'description' => 'description', 'amount' => 'amount'
	       )
	    )
	 );
      }
      /**
	 @param string $p_accountID	\
	    The LedgerAccount the matching to be run for
	 @return boolean	Success
      */ 
      public function match($p_accountID) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->r_ledgerAccountService->assertAccountOrCategoryExists(
	    $p_accountID, CloudBankConsts::LedgerAccountType_Account
	 );
	 $v_matches = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT event_id, statement_item_id
		  FROM event_statement_item_match
		  WHERE ledger_account_id = :account_id
	       ',
	       array(':account_id' => $p_accountID)
	    )
	 );
	 foreach($v_matches as $v_match_record) {
	    $this->r_cloudBankServer->execQuery(
	       '
		  UPDATE event
		  SET statement_item_id = :statement_item_id
		  WHERE is_cleared = 0 AND id = :event_id
	       ',
	       array(
		  ':statement_item_id' => $v_match_record['statement_item_id'],
		  ':event_id' => $v_match_record['event_id']
	       )
	    );
	    /* if there are multiple matches for a Statement Item then an
	       arbitrary one of them will prevail */
	 }
	 $this->r_cloudBankServer->commitTransaction();
	 return TRUE;
      }
      /**
	 @param string $p_accountID	\
	    The LedgerAccount the clearing to be run for
	 @return boolean	Success
      */ 
      public function clearAllMatchedEvents($p_accountID) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->r_ledgerAccountService->assertAccountOrCategoryExists(
	    $p_accountID, CloudBankConsts::LedgerAccountType_Account
	 );
	 $this->r_cloudBankServer->execQuery(
     	    '
     	       DELETE FROM statement_item
	       WHERE
		  id IN (
		     SELECT statement_item_id
		     FROM event_matched
		     WHERE ledger_account_id = :account_id
		  )
	    ',
	    array(':account_id' => $p_accountID)
	 );
	 $this->r_cloudBankServer->execQuery(
     	    '
	       UPDATE event
	       SET is_cleared = 1
	       WHERE
		  id IN (
		     SELECT id
		     FROM event_matched
		     WHERE ledger_account_id = :account_id
		  )
	    ',
	    array(':account_id' => $p_accountID)
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return TRUE;
      }
      /**
	 @param string $p_accountID	\
	    The LedgerAccount the related statement balance to be returned for
	 @return StatementItem http://pety.dynu.net/CloudBank/StatementService
	     Statement closing balance
      */ 
      public function findClosingBalance($p_accountID) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->r_ledgerAccountService->assertAccountOrCategoryExists(
	    $p_accountID, CloudBankConsts::LedgerAccountType_Account
	 );
	 $v_statementItems = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT
		     si.id, si.ledger_account_name, si.item_type, si.date,
		     si.description, si.amount
		  FROM statement_item si, ledger_account la
		  WHERE
		     si.ledger_account_name = la.name AND la.type = :Account AND
		     si.item_type = :C AND la.id = :accountID
	       ',
	       array(
		  ':Account' => CloudBankConsts::LedgerAccountType_Account,
		  ':C' => self::StatementItemType_Closing, 
		  ':accountID' => $p_accountID
	       )
	    )
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return (
	    self::ToSDO(
	       $v_statementItems, NULL, 'StatementItem',
	       array(
		  'id' => 'id', 'ledger_account_name' => 'ledger_account_name',
		  'item_type' => 'item_type', 'date' => 'date',
		  'description' => 'description', 'amount' => 'amount'
	       )
	    )
	 );
      }

      /**
	 @return boolean	Success
      */ 
      public function purge() {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->r_cloudBankServer->execQuery('DELETE FROM statement_item');
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      /**
	 @return AccountSet http://pety.homelinux.org/CloudBank/LedgerAccountService
	     Set of Accounts
      */
      public function findAccountsForStatement() {
	 return $this->r_ledgerAccountService->findAccounts(true);
      }


      private function __clone() { }
      private function assertTableIsEmpty() {
	 if (
	    count(
	       $this->r_cloudBankServer->execQuery(
		  'SELECT 1 FROM statement_item'
	       )
	    ) > 0
	 ) throw new Exception('Statement is already loaded');
      }
      private function assertStatementItemIsValid(
	 $p_statement_item_arr, $p_line_no, $p_statement_line
      ) {
     	 if (count($p_statement_item_arr) < 6) {
     	    throw new Exception(
     	       "Statement file: line #$p_line_no is incomplete (" .
	       "$p_statement_line)"
	    );
	 }
	 if (!SchemaDef::IsValidStatementItemID($p_statement_item_arr[0])) {
	    throw new Exception(
	       "Statement file: line #$p_line_no: invalid item ID (" .
	       "$p_statement_item_arr[0])"
	    );
	 }
	 if (
	    !(
	       $this->r_ledgerAccountService->doesExistAndNotThis(
	  	  $p_statement_item_arr[1],
		  CloudBankConsts::LedgerAccountType_Account
	       )
	    )
	 ) {
	    throw new Exception(
	       "Statement file: line #$p_line_no: account (" .
	       "$p_statement_item_arr[1]) does not exist"
	    );
	 }
	 if (!SchemaDef::IsValidStatementItemType($p_statement_item_arr[2])) {
     	    throw new Exception(
     	       "Statement file: line #$p_line_no: invalid item type (" .
	       "$p_statement_item_arr[2])"
	    );
	 }
	 if (!SchemaDef::IsValidDate($p_statement_item_arr[3])) {
	    throw new Exception(
	       "Statement file: line #$p_line_no: invalid date (" .
	       "$p_statement_item_arr[3])"
	    );
	 }
	 if (
	    !(
	       SchemaDef::IsValidStatementItemDescription(
		  $p_statement_item_arr[4]
	       )
	    )
	 ) {
	    throw new Exception(
	       "Statement file: line #$p_line_no: invalid description (" .
	       "$p_statement_item_arr[4])"
	    );
	 }
	 if (!SchemaDef::IsValidAmount($p_statement_item_arr[5])) {
	    throw new Exception(
	       "Statement file: line #$p_line_no: invalid amount (" .
	       "$p_statement_item_arr[4])"
	    );
	 }
      }
      private function createStatementItem($p_statement_item_arr) {
	 $this->r_cloudBankServer->execQuery(
	    '
	       INSERT
		  INTO statement_item(
		     id, ledger_account_name, item_type, date, description,
		     amount
		  ) VALUES (
		     :id, :ledger_account_name, :item_type, :date, :description,
		     :amount
		  )
	    ',
	    array(
	       ':id' => $p_statement_item_arr[0],
	       ':ledger_account_name' => $p_statement_item_arr[1],
	       ':item_type' => $p_statement_item_arr[2],
	       ':date' => $p_statement_item_arr[3],
	       ':description' => $p_statement_item_arr[4],
	       ':amount' => $p_statement_item_arr[5]
	    )
	 );
      }

      const StatementItemType_Closing = 'C';

      /**
         @reference
         @binding.php LedgerAccountService.php
      */
      public $r_ledgerAccountService;
         /* So methods of LedgerAccount can be accessed (instead of an include,
            which does not work due to the SCA include. And it has to be public
            in order SCA to be able to manipulate it. */

      private $r_cloudBankServer;
   }
?>
