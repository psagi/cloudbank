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
	 $v_conf = parse_ini_file(dirname(__FILE__) . '/../conf/cloudbank.ini');
	 $this->r_dateMatchRangeMin = (
	    array_key_exists('date_match_range_min', $v_conf) ?
	    $v_conf['date_match_range_min'] :
	    0
	 );
	 $this->r_dateMatchRangeMax = (
	    array_key_exists('date_match_range_max', $v_conf) ?
	    $v_conf['date_match_range_max'] :
	    0
	 );
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
	    Debug::Singleton()->log(
	       "importStatement: $v_line_no: $v_statement_line"
	    );
	    $v_statement_item_arr = Util::ParseCSVLine($v_statement_line);
	    Debug::Singleton()->log(
	       "importStatement: $v_line_no: " .
	       var_export($v_statement_item_arr, true)
	    );
	    if (count($v_statement_item_arr) == 0) continue;
	    $this->assertStatementItemIsValid(
	       $v_statement_item_arr, $v_line_no, $v_statement_line
	    );
	    if (is_null(
		  $v_ledger_account_id = (
		     $this->r_ledgerAccountService->findAccount(
			$v_statement_item_arr[1],
			CloudBankConsts::LedgerAccountType_Account
		     )
		  )
	       )
	    ) {
	       throw new Exception(
		  "Statement file: line #$v_line_no: account (" .
		  "$v_statement_item_arr[1]) does not exist"
	       );
	    }
	    $this->createStatementItem(
	       $v_statement_item_arr, $v_ledger_account_id
	    );
	 }
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

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
		     id, ledger_account_id, item_type, date, description,
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
		  'id' => 'id', 'ledger_account_id' => 'ledger_account_id',
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
		  WHERE
		     ledger_account_id = :account_id AND (
			date_diff BETWEEN
			:date_match_range_min AND
			:date_match_range_max
		     )
	       ',
	       array(
		  ':account_id' => $p_accountID,
		  ':date_match_range_min' => $this->r_dateMatchRangeMin,
		  ':date_match_range_max' => $this->r_dateMatchRangeMax
	       )
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
	     Statement opening balance
      */ 
      public function findOpeningBalance($p_accountID) {
	 return (
	    $this->findBalance($p_accountID, self::StatementItemType_Opening)
	 );
      }
      /**
	 @param string $p_accountID	\
	    The LedgerAccount the related statement balance to be returned for
	 @return StatementItem http://pety.dynu.net/CloudBank/StatementService
	     Statement closing balance
      */ 
      public function findClosingBalance($p_accountID) {
	 return (
	    $this->findBalance($p_accountID, self::StatementItemType_Closing)
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

      /**
	 @param string $p_accountID	\
	    The ID of the Account Statement items to be searched for
	 @return boolean	\
	    True if there is a Statement item for the Account
      */
      public function isThereStatementForAccount($p_accountID) {
	 $this->r_cloudBankServer->beginTransaction();
	 $v_result_arr = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT 1
		  FROM statement_item
		  WHERE ledger_account_id = :ledger_account_id
	       ', array(':ledger_account_id' => $p_accountID)
	    )

	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return (!empty($v_result_arr));
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
      private function createStatementItem(
	 $p_statement_item_arr, $p_ledger_account_id
      ) {
	 $this->r_cloudBankServer->execQuery(
	    '
	       INSERT
		  INTO statement_item(
		     id, ledger_account_id, item_type, date, description,
		     amount
		  ) VALUES (
		     :id, :ledger_account_id, :item_type, :date, :description,
		     :amount
		  )
	    ',
	    array(
	       ':id' => $p_statement_item_arr[0],
	       ':ledger_account_id' => $p_ledger_account_id,
	       ':item_type' => $p_statement_item_arr[2],
	       ':date' => $p_statement_item_arr[3],
	       ':description' => $p_statement_item_arr[4],
	       ':amount' => $p_statement_item_arr[5]
	    )
	 );
      }
      private function findBalance($p_accountID, $p_statementItemType) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->r_ledgerAccountService->assertAccountOrCategoryExists(
	    $p_accountID, CloudBankConsts::LedgerAccountType_Account
	 );
	 $v_statementItems = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT
		     si.id, si.ledger_account_id, si.item_type, si.date,
		     si.description, si.amount
		  FROM statement_item si 
		  WHERE
		     si.item_type = :C AND ledger_account_id = :accountID
	       ',
	       array(
		  ':C' => $p_statementItemType,
		  ':accountID' => $p_accountID
	       )
	    )
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return (
	    self::ToSDO(
	       $v_statementItems, NULL, 'StatementItem',
	       array(
		  'id' => 'id', 'ledger_account_id' => 'ledger_account_id',
		  'item_type' => 'item_type', 'date' => 'date',
		  'description' => 'description', 'amount' => 'amount'
	       )
	    )
	 );
      }

      const StatementItemType_Opening = 'O';
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
      private $r_dateMatchRangeMin;
      private $r_dateMatchRangeMax;
   }
?>
