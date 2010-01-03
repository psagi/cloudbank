<?php
   require_once('CloudBankServer.php');
   require_once('Event.php');
   include('SCA/SCA.php');

   /**
      @service
      @binding.soap
   */
   class LedgerAccount {
      const Account = 'Account';
      const Category = 'Category';
      const Beginning = 'Beginning'; 
      const BeginningEvntDesc = 'Beginning';
      const BeginningAccntName = 'Beginning';

      /**
	 @param string $p_name	The name of the account
	 @param string $p_date	The date of creation of the account (YYYY-MM-DD)
	 @param float $p_beginningBalance	\
	    The beginning balance of the account 
	 @return bool		Success
      */
      public static function CreateAccount(
	 $p_name, $p_date, $p_beginningBalance
      ) {
	 CloudBankServer::Singleton()->beginTransaction();
	 $v_accntID = self::CreateLedgerAccount($p_name, self::Account);
	 Event::CreateEvent_internal(
	    $p_date, self::BeginningEvntDesc, $v_accntID,
	    self::GetBeginningAccountID(), $p_beginningBalance
	 );
	 CloudBankServer::Singleton()->commitTransaction();
	 return true;
      }

      /**
	 @param string $p_name	The name of the category
	 @return bool		Success
      */
      public static function CreateCategory($p_name) {
	 CloudBankServer::Singleton()->beginTransaction();
	 self::CreateLedgerAccount($p_name, self::Category);
	 CloudBankServer::Singleton()->commitTransaction();
	 return true;
      }
      
      /* Note that although this method is not annotated, SCA generates an
	 operation for it in the WSDL. However this operation is probably
	 unusable due to the missing annotations - and so type definitions in
	 the WSDL.
      */
      public static function CreateBeginningAccount() {
	 CloudBankServer::Singleton()->beginTransaction();
	 self::CreateLedgerAccount(self::BeginningAccntName, self::Beginning);
	 CloudBankServer::Singleton()->commitTransaction();
      }

      private static function GetBeginningAccountID() {
	 $v_result = (
	    CloudBankServer::Singleton()->execQuery(
	       'SELECT id FROM ledger_account WHERE type = :type',
	       array(':type' => self::Beginning)
	    )
	 );
	 return $v_result[0]['id'];
      }

      private static function DoesExist($p_name, $p_type) {
	 return (
	    count(
	       CloudBankServer::Singleton()->execQuery(
		  '
		     SELECT 1
			FROM ledger_account
			WHERE name = :name AND type = :type
		  ', array(':name' => $p_name, ':type' => $p_type)
	       )
	    ) > 0
	 );
      }

      private static function CreateLedgerAccount($p_name, $p_type) {
	 if (!SchemaDef::IsValidLedgerAccountName($p_name)) {
	    throw new Exception("Invalid LedgerAccount name ($p_name)");
	 }
	 if (self::DoesExist($p_name, $p_type)) {
	    throw
	       new Exception("LedgerAccount ($p_name, $p_type) already exists.")
	    ;
	 }
	 $v_accountID = CloudBankServer::UUID();
	 CloudBankServer::Singleton()->execQuery(
	    '
	       INSERT 
		  INTO ledger_account(id, name, type) VALUES (:id, :name, :type)
	    ',
	    array(':id' => $v_accountID, ':name' => $p_name, ':type' => $p_type)
	 );
	 return $v_accountID;
      }
   }
?>
