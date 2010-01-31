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
	    The account the event is started from
	 @param string $p_otherAccountID
	    The other account/category the amount to be debited/credited to/from
	 @param float $p_amount	
	    The amount (negative if the starting account's balance is to be
	    decreased)
	 @return bool		Success
      */
      public function createEvent(
	 $p_date, $p_description, $p_accountID, $p_otherAccountID, $p_amount
      ) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->createOrUpdateEvent(
	    $p_date, $p_description, $p_accountID, $p_otherAccountID, $p_amount
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
	 @param string $p_accountID	The account the event is started from
	 @param Event $p_oldEvent http://pety.homelinux.org/CloudBank/EventService
	 @param Event $p_newEvent http://pety.homelinux.org/CloudBank/EventService
	 @return bool		Success
      */
      public function modifyEvent($p_accountID, $p_oldEvent, $p_newEvent) {
	 CloudBankServer::AssertIDsMatch($p_newEvent['id'], $p_oldEvent['id']);
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertSameAsCurrent($p_accountID, $p_oldEvent);
	 $this->createOrUpdateEvent(
	    $p_newEvent['date'], $p_newEvent['description'],
	    $p_accountID, $p_newEvent['other_account_id'],
	    $p_newEvent['amount'], $p_newEvent['id']
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      /**
	 @param string $p_eventID	The Event to be deleted
	 @return bool			Success
      */
      public function deleteEvent($p_eventID) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertNonBeginningEventExists($p_eventID);
	 $this->r_cloudBankServer->execQuery(
	    'DELETE FROM event WHERE id = :id', array(':id' => $p_eventID)
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      public function createOrUpdateEvent(
	 $p_date, $p_description, $p_accountID, $p_otherAccountID, $p_amount,
	 $p_id = NULL
      ) {
	 if (!SchemaDef::IsValidDate($p_date)) {
	    throw new Exception("Invalid Event date ($p_date)");
	 }
	 if (!SchemaDef::IsValidEventDescription($p_description)) {
	    throw new Exception("Invalid Event description ($p_description)");
	 }
	 $this->prepareRelatedAccounts(
	    $p_accountID, $p_otherAccountID, $p_amount, $v_debitLedgerAccountID,
	    $v_creditLedgerAccountID, $v_amount
	 );
	 if (
	    !(
	       SchemaDef::IsValidLedgerAccountPair(
		  $v_debitLedgerAccountID, $v_creditLedgerAccountID
	       )
	    )
	 ) {
	    throw new Exception(
	       "Referenced LedgerAccounts ($v_debitLedgerAccountID) must not " .
		  "be the same"
	    );
	 }
	 $this->r_cloudBankServer->execQuery(
	    (
	       is_null($p_id) ?
	       '
		  INSERT 
		     INTO event(
		     	id, date, description, credit_ledger_account_id,
		       	debit_ledger_account_id, amount
		     ) VALUES (
		      	:id, :date, :description, :credit_ledger_account_id,
			:debit_ledger_account_id, :amount
		     )
	       ' :
	       '
		  UPDATE event
		  SET
		     date = :date, description = :description,
		     credit_ledger_account_id = :credit_ledger_account_id,
		     debit_ledger_account_id = :debit_ledger_account_id,
		     amount = :amount
		  WHERE id = :id
	       '
	    ),
	    array(
	       ':id' => (is_null($p_id) ? CloudBankServer::UUID() : $p_id),
	       ':date' => $p_date,
	       ':description' => $p_description,
	       ':credit_ledger_account_id' => $v_creditLedgerAccountID,
	       ':debit_ledger_account_id' => $v_debitLedgerAccountID,
	       ':amount' => $v_amount
	    )
	 );
      }
      public function deleteAllEvents($p_ledgerAccountID) {
	 $this->r_cloudBankServer->execQuery(
	    '
	       DELETE FROM event 
		  WHERE
		     debit_ledger_account_id = :ledger_account_id OR
		     credit_ledger_account_id = :ledger_account_id
	    ', array(':ledger_account_id' => $p_ledgerAccountID)
	 );
      }

      private function __clone() { }
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
      private function assertNonBeginningEventExists($p_eventID) {
	 if (
            count(
               $this->r_cloudBankServer->execQuery(
                  '
		     SELECT 1
		     FROM account_events
		     WHERE
		     id = :id AND
		     :beginningType NOT IN (
			ledger_account_type, other_ledger_account_type
		     )
		  ', array(
		     ':id' => $p_eventID,
		     ':beginningType' => SchemaDef::LedgerAccountType_Beginning
		  )
               )
            ) == 0
	 ) {
	    throw new Exception(
	       "Referenced (non-beginning) Event ($p_eventID) does not exist."
	    );
	 }
      }
      private function assertSameAsCurrent($p_accountID, $p_oldEvent) {
	 $v_currentEvent = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT date, description, other_ledger_account_id, amount
		  FROM account_events
		  WHERE ledger_account_id = :account_id AND id = :id
	       ',
	       array(':account_id' => $p_accountID, ':id' => $p_oldEvent['id'])
	    )
	 );
	 if (
	    !CloudBankServer::IsEqual(
	       $p_oldEvent, $v_currentEvent[0],
	       array(
		  'date' => 'date', 'description' => 'description',
		  'other_account_id' => 'other_ledger_account_id',
		  'amount' => 'amount'
	       )
	    )
	 ) {
	    throw new Exception(
	       "The event to be modified ({$p_oldEvent['id']}) does not " .
		  "exist or has been modified by another session. Please try " .
		  "again."
	    );
	 }
      }
      private function prepareRelatedAccounts(
	    $p_accountID, $p_otherAccountID, $p_amount_client,
	    &$p_debitLedgerAccountID, &$p_creditLedgerAccountID, &$p_amount
	 ) {
	 $this->assertLedgerAccountExists(
	    $p_accountID, SchemaDef::LedgerAccountType_Account
	 );
	 $this->assertLedgerAccountExists($p_otherAccountID);
	 CloudBankServer::SwapIf(
	    $p_amount_client < 0, $p_accountID, $p_otherAccountID,
	    $p_debitLedgerAccountID, $p_creditLedgerAccountID
	 ); 
	 $p_amount = abs($p_amount_client);
      }

      private $r_cloudBankServer;
   }
?>
