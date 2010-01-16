<?php
   require_once('CloudBankServer.php');
   include('SCA/SCA.php');

   /**
      @service
      @binding.soap
   */
   class EventService {
      public function __construct() {
	 $this->r_cloudBankServer = CloudBankServer::Singleton();
      }

      /**
	 @param string $p_date		The date of the event (YYYY-MM-DD)
	 @param string $p_description	The description of the event
	 @param string $p_creditLedgerAccountID	\
	    The account the amount to be credited from
	 @param string $p_debitLedgerAccountID	\
	    The account the amount to be debited to
	 @param float $p_amount		The amount
	 @return bool		Success
      */
      public function createEvent(
	 $p_date, $p_description, $p_creditLedgerAccountID,
	 $p_debitLedgerAccountID, $p_amount
      ) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->createEvent_internal(
	    $p_date, $p_description, $p_creditLedgerAccountID,
	    $p_debitLedgerAccountID, $p_amount
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      public function createEvent_internal(
	 $p_date, $p_description, $p_creditLedgerAccountID,
	 $p_debitLedgerAccountID, $p_amount
      ) {
	 if (!SchemaDef::IsValidDate($p_date)) {
	    throw new Exception("Invalid Event date ($p_date)");
	 }
	 if (!SchemaDef::IsValidEventDescription($p_description)) {
	    throw new Exception("Invalid Event description ($p_description)");
	 }
	 if (!$this->isExistingLedgerAccount($p_creditLedgerAccountID)) {
	    throw new Exception(
	       "Referenced LedgerAccount ($p_creditLedgerAccountID) to be " .
		  "credited does not exist."
	    );
	 }
	 if (!$this->isExistingLedgerAccount($p_debitLedgerAccountID)) {
	    throw new Exception(
	       "Referenced LedgerAccount ($p_debitLedgerAccountID) to be " .
		  "debited does not exist."
	    );
	 }
	 if (!SchemaDef::IsValidAmount($p_amount)) {
	    throw new Exception("Invalid amount ($p_amount)");
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
      private function isExistingLedgerAccount($p_ledgerAccountID) {
        return (
            count(
               $this->r_cloudBankServer->execQuery(
                  'SELECT 1 FROM ledger_account WHERE id = :id',
		  array(':id' => $p_ledgerAccountID)
               )
            ) > 0
         );
      }

      private $r_cloudBankServer;
   }
?>
