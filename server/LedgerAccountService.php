<?php
   require_once('CloudBankServer.php');
   require_once('Debug.php');
   include('SCA/SCA.php');

   /**
      @service
      @binding.soap
      @types http://pety.homelinux.org/CloudBank/LedgerAccountService LedgerAccountService.xsd
   */
      // Note that annotations can not contain linebreaks.
   class LedgerAccountService {
      const BeginningEvntDesc = 'Beginning';
      const BeginningAccntName = 'Beginning';

      private static function ToSDO(
	 $p_resultSet, $p_setTypeName, $p_elementTypeName, $p_mapping
      ) {
	 return (
	    CloudBankServer::ToSDO(
	       $p_resultSet,
	       SCA::createDataObject(
		  'http://pety.homelinux.org/CloudBank/LedgerAccountService',
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
	 @param string $p_name	The name of the account
	 @param string $p_date	The date of creation of the account (YYYY-MM-DD)
	 @param float $p_beginningBalance	\
	    The beginning balance of the account 
	 @return bool		Success
      */
      public function createAccount(
	 $p_name, $p_date, $p_beginningBalance
      ) {
	 $this->r_cloudBankServer->beginTransaction();
	 $v_accntID = $this->createLedgerAccount(
	    $p_name, SchemaDef::LedgerAccountType_Account
	 );
	 CloudBankServer::SwapIf(
	    ($p_beginningBalance < 0), $v_accntID,
	    self::GetBeginningAccountID(), $v_debitLedgerAccountID,
	    $v_creditLedgerAccountID
	 );
	 $this->r_eventService->createEvent(
	    $p_date, self::BeginningEvntDesc, $v_debitLedgerAccountID,
	    $v_creditLedgerAccountID, abs($p_beginningBalance)
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      /**
	 @param string $p_name	The name of the category
	 @return bool		Success
      */
      public function createCategory($p_name) {
try {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->createLedgerAccount(
	    $p_name, SchemaDef::LedgerAccountType_Category
	 );
	 $this->r_cloudBankServer->commitTransaction();
} catch (Exception $v_exception) {
Debug::Singleton()->log(var_export($v_exception, true));
throw $v_exception;
}
	 return true;
      }

      /**
	 @return AccountSet http://pety.homelinux.org/CloudBank/LedgerAccountService
	    Set of Accounts
      */
      public function getAccounts() {
	 $this->r_cloudBankServer->beginTransaction();
	 $v_accounts = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT ledger_account_id, ledger_account_name, amount
		  FROM account_events
		  WHERE
		     ledger_account_type = :type AND
		     other_ledger_account_id = :beginningAccountID
	       ', 
	       array(
		  ':type' => SchemaDef::LedgerAccountType_Account,
		  ':beginningAccountID' => $this->getBeginningAccountID()
	       )
	    )
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return (
	    self::ToSDO(
	       $v_accounts, 'AccountSet', 'Account',
	       array(
		  'ledger_account_id' => 'id', 'ledger_account_name' => 'name',
		  'amount' => 'beginning_balance'
	       )
	    )
	 );
      }

      /**
	 @return CategorySet http://pety.homelinux.org/CloudBank/LedgerAccountService
	    Set of Categories
      */
      public function getCategories() {
	 $v_bindArray = array(':type' => SchemaDef::LedgerAccountType_Category);
	 $v_categories = (
	    $this->r_cloudBankServer->execQuery(
	       'SELECT id, name FROM ledger_account WHERE type = :type',
	       $v_bindArray
	    )
	 );
	 return (
	    self::ToSDO(
	       $v_categories, 'CategorySet', 'Category',
	       array('id' => 'id', 'name' => 'name')
	    )
	 );
      }
      
      /* Note that although this method is not annotated, SCA generates an
	 operation for it in the WSDL. However this operation is probably
	 unusable due to the missing annotations - and so type definitions in
	 the WSDL.
      */
      public function createBeginningAccount() {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->createLedgerAccount(
	    self::BeginningAccntName, SchemaDef::LedgerAccountType_Beginning
	 );
	 $this->r_cloudBankServer->commitTransaction();
      }

      private function getBeginningAccountID() {
	 $v_result = (
	    $this->r_cloudBankServer->execQuery(
	       'SELECT id FROM ledger_account WHERE type = :type',
	       array(':type' => SchemaDef::LedgerAccountType_Beginning)
	    )
	 );
	 return $v_result[0]['id'];
      }

      private function doesExist($p_name, $p_type) {
	 return (
	    count(
	       $this->r_cloudBankServer->execQuery(
		  '
		     SELECT 1
			FROM ledger_account
			WHERE name = :name AND type = :type
		  ', array(':name' => $p_name, ':type' => $p_type)
	       )
	    ) > 0
	 );
      }

      private function createLedgerAccount($p_name, $p_type) {
	 if (!SchemaDef::IsValidLedgerAccountName($p_name)) {
	    throw new Exception("Invalid LedgerAccount name ($p_name)");
	 }
	 if ($this->doesExist($p_name, $p_type)) {
	    throw
	       new Exception("LedgerAccount ($p_name, $p_type) already exists.")
	    ;
	 }
	 $v_accountID = CloudBankServer::UUID();
	 $this->r_cloudBankServer->execQuery(
	    '
	       INSERT 
		  INTO ledger_account(id, name, type) VALUES (:id, :name, :type)
	    ',
	    array(':id' => $v_accountID, ':name' => $p_name, ':type' => $p_type)
	 );
	 return $v_accountID;
      }
      private function __clone() { }

      /**
	 @reference
	 @binding.php EventService.php
      */
      public $r_eventService;
	 /* So methods of Event can be accessed (instead of an include,
	    which does not work due to the SCA include. And it has to be public
	    in order SCA to be able to manipulate it. */
      private $r_cloudBankServer;
   }
?>
