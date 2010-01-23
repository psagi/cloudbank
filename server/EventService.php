<?php
   require_once('CloudBankServer.php');
   include('SCA/SCA.php');

   /**
      @service
      @binding.soap
      @types http://pety.homelinux.org/CloudBank/EventService EventService.xsd
   */
   class EventService {
      private static function ToSDO(
         $p_resultSet, $p_setTypeName, $p_elementTypeName, $p_mapping
      ) {
	 return (
	    CloudBankServer::ToSDO(
	       $p_resultSet,
	       SCA::createDataObject(
		  'http://pety.homelinux.org/CloudBank/EventService',
		  $p_setTypeName
	       ), // the root DO has to be created inside the SCA component
	       $p_elementTypeName, $p_mapping
	    )
	 );
      }

      public function __construct() {
	 $this->r_cloudBankServer = CloudBankServer::Singleton();
      }

      /**
	 @param string $p_date		The date of the event (YYYY-MM-DD)
	 @param string $p_description	The description of the event
	 @param string $p_accountID
	    The account the amount to be credited/debited from/to
	 @param string $p_categoryID
	    The category the amount to be accounted to
	 @param bool $p_isExpenditure
	    True if the event is expenditure, false if revenue
	 @param float $p_amount		The amount
	 @return bool		Success
      */
      public function createExpenditureRevenueEvent(
	 $p_date, $p_description, $p_accountID, $p_categoryID, $p_isExpenditure,
	 $p_amount
      ) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertAccountExists($p_accountID);
	 $this->assertCategoryExists($p_categoryID);
	 CloudBankServer::SwapIf(
	    !$p_isExpenditure, $p_categoryID, $p_accountID,
	    $v_debitLedgerAccountID, $v_creditLedgerAccountID
	 ); 
	 $this->createEvent(
	    $p_date, $p_description, $v_debitLedgerAccountID,
	    $v_creditLedgerAccountID, $p_amount
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      /**
	 @param string $p_date		The date of the event (YYYY-MM-DD)
	 @param string $p_description	The description of the event
	 @param string $p_startingAccountID
	    The account the event is started from
	 @param string $p_otherAccountID
	    The other account of the transfer
	 @param bool $p_isWithdrawal
	    True if the starting account is to be decreased, false if to be
	    increased
	 @param float $p_amount		The amount
	 @return bool		Success
      */
      public function createTransferEvent(
	 $p_date, $p_description, $p_startingAccountID, $p_otherAccountID,
	 $p_isWithdrawal, $p_amount
      ) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertAccountExists($p_startingAccountID);
	 $this->assertAccountExists($p_otherAccountID);
	 CloudBankServer::SwapIf(
	    $p_isWithdrawal, $p_startingAccountID, $p_otherAccountID,
	    $v_debitLedgerAccountID, $v_creditLedgerAccountID
	 ); 
	 $this->createEvent(
	    $p_date, $p_description, $v_debitLedgerAccountID,
	    $v_creditLedgerAccountID, $p_amount
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      /**
	 @param string $p_accountID	\
	    The LedgerAccount the related events to be returned for
	 @return EventSet http://pety.homelinux.org/CloudBank/EventService
	     Set of Events
      */
      public function getEvents($p_accountID) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertLedgerAccountExists($p_accountID);
	 $v_events = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT
		     id, date, description, other_ledger_account_id,
		     other_ledger_account_name, amount
		  FROM account_events
		  WHERE ledger_account_id = :accountID
	       ', array(':accountID' => $p_accountID)
	    )
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return (
	    self::ToSDO(
	       $v_events, 'EventSet', 'Event',
	       array(
		  'id' => 'id', 'date' => 'date',
		  'description' => 'description',
		  'other_ledger_account_id' => 'other_account_id',
		  'other_ledger_account_name' => 'other_account_name',
		  'amount' => 'amount'
	       )
	    )
	 );
      }
      
      /**
	 @param string $p_EventID	The Event to be deleted
      */
      public function deleteEvent($p_eventID) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertEventExists($p_eventID);
	 $this->r_cloudBankServer->execQuery(
	    'DELETE FROM event WHERE id = :id', array(':id' => $p_eventID)
	 );
	 $this->r_cloudBankServer->commitTransaction();
      }

      public function createEvent(
	 $p_date, $p_description, $p_debitLedgerAccountID,
	 $p_creditLedgerAccountID, $p_amount
      ) {
	 if (!SchemaDef::IsValidDate($p_date)) {
	    throw new Exception("Invalid Event date ($p_date)");
	 }
	 if (!SchemaDef::IsValidEventDescription($p_description)) {
	    throw new Exception("Invalid Event description ($p_description)");
	 }
	 if (!SchemaDef::IsValidAmount($p_amount)) {
	    throw new Exception("Invalid amount ($p_amount)");
	 }
	 if (
	    !(
	       SchemaDef::IsValidLedgerAccountPair(
		  $p_debitLedgerAccountID, $p_creditLedgerAccountID
	       )
	    )
	 ) {
	    throw new Exception(
	       "Referenced LedgerAccounts ($p_debitLedgerAccountID) must not " .
		  "be the same"
	    );
	 }
	 $this->r_cloudBankServer->execQuery(
	    '
	       INSERT 
		  INTO event(
		     id, date, description, credit_ledger_account_id,
		     debit_ledger_account_id, amount
		  ) VALUES (
		     :id, :date, :description, :credit_ledger_account_id,
		     :debit_ledger_account_id, :amount
		  )
	    ',
	    array(
	       ':id' => CloudBankServer::UUID(), ':date' => $p_date,
	       ':description' => $p_description,
	       ':credit_ledger_account_id' => $p_creditLedgerAccountID,
	       ':debit_ledger_account_id' => $p_debitLedgerAccountID,
	       ':amount' => $p_amount
	    )
	 );
      }

      private function __clone() { }
      private function assertAccountExists($p_accountID) {
	 $this->assertLedgerAccountExists(
	    $p_accountID, SchemaDef::LedgerAccountType_Account
	 );
      }
      private function assertCategoryExists($p_categoryID) {
	 $this->assertLedgerAccountExists(
	    $p_categoryID, SchemaDef::LedgerAccountType_Category
	 );
      }
      private function assertLedgerAccountExists(
	 $p_ledgerAccountID, $p_type = NULL
      ) {
	 $v_bindArray = array(':id' => $p_ledgerAccountID);
	 if (!empty($p_type)) $v_bindArray[':type'] = $p_type;
	 if (
            count(
               $this->r_cloudBankServer->execQuery(
                  '
		     SELECT 1
		     FROM ledger_account
		     WHERE
			id = :id' .
			   (empty($p_type) ? '' : ' AND type = :type') . '
		  ', $v_bindArray
               )
            ) == 0
	 ) {
	    throw new Exception(
	       "Referenced LedgerAccount ($p_ledgerAccountID" .
		  (empty($p_type) ? "" : ", $p_type") . ") does not exist."
	    );
	 }
      }
      private function assertEventExists($p_eventID) {
	 if (
            count(
               $this->r_cloudBankServer->execQuery(
                  'SELECT 1 FROM event WHERE id = :id',
		  array(':id' => $p_eventID)
               )
            ) == 0
	 ) {
	    throw new Exception(
	       "Referenced Event ($p_eventID) does not exist."
	    );
	 }
      }

      private $r_cloudBankServer;
   }
?>
