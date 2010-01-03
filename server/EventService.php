<?php
   require_once('CloudBankServer.php');
   include('SCA/SCA.php');

   /**
      @service
      @binding.soap
   */
   class Event {
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
      public static function CreateEvent(
	 $p_date, $p_description, $p_creditLedgerAccountID,
	 $p_debitLedgerAccountID, $p_amount
      ) {
	 CloudBankServer::Singleton()->beginTransaction();
// Check input here!!!	
	 self::CreateEvent_internal(
	    $p_date, $p_description, $p_creditLedgerAccountID,
	    $p_debitLedgerAccountID, $p_amount
	 );
	 CloudBankServer::Singleton()->commitTransaction();
	 return true;
      }

      public static function CreateEvent_internal(
	 $p_date, $p_description, $p_creditLedgerAccountID,
	 $p_debitLedgerAccountID, $p_amount
      ) {
	 if (!SchemaDef::IsValidDate($p_date)) {
	    throw new Exception("Invalid Event date ($p_date)");
	 }
	 CloudBankServer::Singleton()->execQuery(
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
   }
?>
