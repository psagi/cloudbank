<?php
   require_once(dirname(__FILE__) . '/../lib/CloudBankConsts.php');
   require_once('Debug.php');
   require_once('Util.php');
   require_once('CloudBankServer.php');
   include('SCA/SCA.php');

   /**
      @service
      @binding.soap
      @types http://pety.homelinux.org/CloudBank/EventService ../lib/EventService.xsd
   */
   class EventService {
      private static function ToSDO(
         $p_resultSet, $p_setTypeName, $p_elementTypeName, $p_mapping
      ) {
	 return (
	    Util::ToSDO(
	       $p_resultSet,
	       SCA::createDataObject(
		  'http://pety.homelinux.org/CloudBank/EventService',
		  is_null($p_setTypeName) ? $p_elementTypeName : $p_setTypeName
	       ), // the root DO has to be created inside the SCA component
	       is_null($p_setTypeName) ? NULL : $p_elementTypeName, $p_mapping
	    )
	 );
      }
      /*
	 PHP SDO XML does not implement the default attribute of the XSD. This
	 is to fix this behaviour
      */
      private static function ApplyDefaults(&$p_event) {
	 if (!isset($p_event['is_cleared'])) $p_event['is_cleared'] = false;
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
	 @param string $p_amount	
	    The amount (negative if the starting account's balance is to be
	    decreased)
	 @param string $p_statement_item_id
	    Reference to the corresponding statement item (optional)
	 @param boolean $p_is_cleared
	    Flag for indicating the cleared status of the event. Mandatory, 
	    defaults to false.
	 @return boolean		Success
      */
      public function createEvent(
	 $p_date, $p_description, $p_accountID, $p_otherAccountID, $p_amount,
	 $p_statement_item_id, $p_is_cleared = false
      ) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->createOrUpdateEvent(
	    $p_date, $p_description, $p_accountID, $p_otherAccountID, $p_amount,
	    $p_statement_item_id, $p_is_cleared
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }
 
      /**
	 @param string $p_eventID	The ID of the event
	 @param string $p_accountID	\
	    The LedgerAccount the related event to be returned for
	 @return Event http://pety.homelinux.org/CloudBank/EventService
	     Event details
      */
      public function getEvent($p_eventID, $p_accountID) {
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
		  WHERE
		     id = :iD AND ledger_account_type = :ledgerAccountType AND
		     ledger_account_id = :accountID
	       ',
	       array(
		  ':iD' => $p_eventID,
		  ':ledgerAccountType' =>
		     CloudBankConsts::LedgerAccountType_Account, 
		  ':accountID' => $p_accountID
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

      /**
	 @param string $p_accountID	\
	    The LedgerAccount the related events to be returned for
	 @param string $p_limitDate					\
	    The oldest date thats Events are returned. If NULL all	\
	    Events are returned for the LedgerAccount
	 @return EventSet http://pety.homelinux.org/CloudBank/EventService
	     Set of Events
      */
      public function getEvents($p_accountID, $p_limitDate = NULL) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertLedgerAccountExists($p_accountID);
	 $v_bindArray = array(':accountID' => $p_accountID);
	 if ($p_limitDate) $v_bindArray[':limitDate'] = $p_limitDate;
	 $v_events = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT
		     id, date, description, other_ledger_account_id,
		     other_ledger_account_name, other_ledger_account_type,
		     amount, statement_item_id, is_cleared
		  FROM account_events
		  WHERE ledger_account_id = :accountID
	       ' . (empty($p_limitDate) ? '' : 'AND date >= :limitDate'),
	       $v_bindArray
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
		  'other_ledger_account_type' => 'other_account_type',
		  'amount' => 'amount',
		  'statement_item_id' => 'statement_item_id',
		  'is_cleared' => 'is_cleared'
	       )
	    )
	 );
      }
      
      /**
	 @param string $p_accountID	The account the event is started from
	 @param Event $p_oldEvent http://pety.homelinux.org/CloudBank/EventService
	 @param Event $p_newEvent http://pety.homelinux.org/CloudBank/EventService
	 @return boolean		Success
      */
      public function modifyEvent($p_accountID, $p_oldEvent, $p_newEvent) {
	 Debug::Singleton()->log(
	    'modifyEvent(' . var_export($p_accountID, true) . ', ' .
	    var_export($p_oldEvent, true) . ', ' .
	    var_export($p_newEvent, true) . ')'
	 );
	 CloudBankServer::AssertIDsMatch($p_newEvent['id'], $p_oldEvent['id']);
	 $this->r_cloudBankServer->beginTransaction();
	 $v_oldEvent = $p_oldEvent;
	 self::ApplyDefaults($v_oldEvent);
	 $this->assertSameAsCurrent($p_accountID, $v_oldEvent);
	 $v_newEvent = $p_newEvent;
	 self::ApplyDefaults($v_newEvent);
	 $this->createOrUpdateEvent(
	    $v_newEvent['date'], $v_newEvent['description'],
	    $p_accountID, $v_newEvent['other_account_id'],
	    $v_newEvent['amount'],
	    (
	       isset($v_newEvent['statement_item_id']) ?
	       $v_newEvent['statement_item_id'] :
	       NULL
	    ),
	    $v_newEvent['is_cleared'], $v_newEvent['id']
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      /**
	 @param string $p_eventID	The Event to be deleted
	 @return boolean			Success
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
	 $p_statement_item_id, $p_is_cleared,
	 $p_id = NULL
      ) {
	 if (!SchemaDef::IsValidDate($p_date)) {
	    throw new Exception("Invalid Event date ($p_date)");
	 }
	 if (!SchemaDef::IsValidEventDescription($p_description)) {
	    throw new Exception("Invalid Event description ($p_description)");
	 }
	 if (!SchemaDef::IsValidAmount($p_amount)) {
	    throw new Exception(
	       "Invalid amount ($p_amount). Must be a floating point number."
	    );
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
	 if (!SchemaDef::IsValidStatementItemIDInEvent($p_statement_item_id)) {
	    throw new Exception("Invalid statement reference ($p_statement_item_id)");
	 }
	 $this->r_cloudBankServer->execQuery(
	    (
	       is_null($p_id) ?
	       '
		  INSERT 
		     INTO event(
		     	id, date, description, credit_ledger_account_id,
		       	debit_ledger_account_id, amount, statement_item_id,
			is_cleared
		     ) VALUES (
		      	:id, :date, :description, :credit_ledger_account_id,
			:debit_ledger_account_id, :amount, :statement_item_id,
			:is_cleared
		     )
	       ' :
	       '
		  UPDATE event
		  SET
		     date = :date, description = :description,
		     credit_ledger_account_id = :credit_ledger_account_id,
		     debit_ledger_account_id = :debit_ledger_account_id,
		     amount = :amount, statement_item_id = :statement_item_id,
		     is_cleared = :is_cleared
		  WHERE id = :id
	       '
	    ),
	    array(
	       ':id' => (is_null($p_id) ? CloudBankServer::UUID() : $p_id),
	       ':date' => $p_date,
	       ':description' => $p_description,
	       ':credit_ledger_account_id' => $v_creditLedgerAccountID,
	       ':debit_ledger_account_id' => $v_debitLedgerAccountID,
	       ':amount' => $v_amount,
	       ':statement_item_id' => $p_statement_item_id,
	       ':is_cleared' => $p_is_cleared
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
		     ':beginningType' =>
			CloudBankConsts::LedgerAccountType_Beginning
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
	 Debug::Singleton()->log(
	    'assertSameAsCurrent(' . var_export($p_accountID, true) . ', ' .
	    var_export($p_oldEvent, true) . ')'
	 );
	 $v_currentEvent = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT
		     date, description, other_ledger_account_id, amount,
		     statement_item_id, is_cleared
		  FROM account_events
		  WHERE ledger_account_id = :account_id AND id = :id
	       ',
	       array(':account_id' => $p_accountID, ':id' => $p_oldEvent['id'])
	    )
	 );
	 $v_mapping = (
	    array(
	       'date' => 'date', 'description' => 'description',
	       'other_account_id' => 'other_ledger_account_id',
	       'amount' => 'amount',
	       'is_cleared' => 'is_cleared'
	    )
	 );
	 if (isset($p_oldEvent['statement_item_id'])) {
	    $v_mapping['is_cleared'] = 'is_cleared';
	 }
	 if (
	    !CloudBankServer::IsEqual(
	       $p_oldEvent, $v_currentEvent[0], $v_mapping
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
	    $p_accountID, CloudBankConsts::LedgerAccountType_Account
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
