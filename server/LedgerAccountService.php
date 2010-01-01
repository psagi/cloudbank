<?php
   require_once('CloudBankServer.php');
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
	 @param string $p_date	The date of creation of the account
	 @param float $p_beginningBalance	\
	    The beginning balance of the account 
	 @return bool		Success
      */
      public static function CreateAccount(
	 $p_name, $p_date, $p_beginningBalance
      ) {
	 $v_ledgerAccount = self::CreateLedgerAccount($p_name, self::Account);
	 $v_beginningEvent = $v_ledgerAccount->createDataObject('event');
	 $v_beginningEvent->date = $p_date;
	 $v_beginningEvent->description = self::BeginningEvntDesc;
	 $v_beginningEvent->credit_ledger_account_id = (
	    $v_ledgerAccount["ledger_account[type=self::Beginning]"]->id
	 );
	 $beginningEvent->amount = $p_beginningBalance;
	 CloudBankServer::Singleton()->applyChanges();

	 return true;
      }

      /**
	 @param string $p_name	The name of the category
	 @return bool		Success
      */
      public static function CreateCategory($p_name) {
	 self::CreateLedgerAccount($p_name, self::Category);
	 CloudBankServer::Singleton()->applyChanges();

	 return true;
      }

      public static function CreateBeginningAccount() {
	 self::CreateLedgerAccount(self::BeginningAccntName, self::Beginning);
	 CloudBankServer::Singleton()->applyChanges();
      }

      private static function UUID() {
	 static $v_UUIDGenerator = NULL;
	 if (!$v_UUIDGenerator) uuid_create(&$v_UUIDGenerator);
	 uuid_make($v_UUIDGenerator, UUID_MAKE_V1);
	 uuid_export($v_UUIDGenerator, UUID_FMT_SIV, &$v_uuid);
	 return $v_uuid;
      }

      private static function CreateLedgerAccount($p_name, $p_type) {
	 $v_ledgerAccount = (
	    CloudBankServer::Singleton()->rootDO()->createDataObject(
	       'ledger_account'
	    )
	 );
	 $v_ledgerAccount->id = self::UUID();
	 $v_ledgerAccount->name = $p_name;
	 $v_ledgerAccount->type = $p_type;

	 return $v_ledgerAccount;
      }
   }
?>
