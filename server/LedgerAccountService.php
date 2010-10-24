<?php
   require_once(dirname(__FILE__) . '/../lib/CloudBankConsts.php');
   require_once(dirname(__FILE__) . '/../lib/Debug.php');
   require_once(dirname(__FILE__) . '/../lib/Util.php');
   require_once('CloudBankServer.php');
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
	    Util::ToSDO(
	       $p_resultSet,
	       SCA::createDataObject(
		  'http://pety.homelinux.org/CloudBank/LedgerAccountService',
		  (
		     is_null($p_setTypeName) ?
		     $p_elementTypeName :
		     $p_setTypeName
		  )
	       ), // the root DO has to be created inside the SCA component
	       (is_null($p_setTypeName) ? NULL : $p_elementTypeName), $p_mapping
	    )
	 );
      }

      public function __construct() {
	 $this->r_cloudBankServer = CloudBankServer::Singleton();
      }

      /**
	 @param string $p_name	The name of the account
	 @param string $p_date	The date of creation of the account (YYYY-MM-DD)
	 @param string $p_beginningBalance	\
	    The beginning balance of the account 
	 @return bool		Success
      */
      public function createAccount(
	 $p_name, $p_date, $p_beginningBalance
      ) {
	 $this->r_cloudBankServer->beginTransaction();
	 $v_accntID = $this->createOrUpdateLedgerAccount(
	    $p_name, CloudBankConsts::LedgerAccountType_Account
	 );
	 $this->r_eventService->createOrUpdateEvent(
	    $p_date, self::BeginningEvntDesc, $v_accntID,
	    self::GetBeginningAccountID(), $p_beginningBalance
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
	 $this->createOrUpdateLedgerAccount(
	    $p_name, CloudBankConsts::LedgerAccountType_Category
	 );
	 $this->r_cloudBankServer->commitTransaction();
} catch (Exception $v_exception) {
Debug::Singleton()->log(var_export($v_exception, true));
throw $v_exception;
}
	 return true;
      }

      /**
	 @param string $p_id	The ID of the account
	 @return Account http://pety.homelinux.org/CloudBank/LedgerAccountService
	    Account details
      */
      public function getAccount($p_id) {
	 return (
	    $this->getAccountOrCategory(
	       $p_id, CloudBankConsts::LedgerAccountType_Account
	    )
	 );
      }

      /**
	 @param string $p_id	The ID of the category
	 @return Category http://pety.homelinux.org/CloudBank/LedgerAccountService
	    Category details
      */
      public function getCategory($p_id) {
	 return (
	    $this->getAccountOrCategory(
	       $p_id, CloudBankConsts::LedgerAccountType_Category
	    )
	 );
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
		  ':type' => CloudBankConsts::LedgerAccountType_Account,
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
	 $v_bindArray = (
	    array(':type' => CloudBankConsts::LedgerAccountType_Category)
	 );
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

      /**
	 @param string $p_ledgerAccountID	The ID of the Account/Category
	 @return string				Its balance
      */
      public function getBalance($p_ledgerAccountID) {
	 $v_balance = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT SUM(amount) AS balance
		  FROM account_events
		  WHERE ledger_account_id = :ledgerAccountID
	       ', 
	       array(
		  ':ledgerAccountID' => $p_ledgerAccountID
	       )
	    )
	 );
	 return $v_balance[0]['balance'];
      }

      /**
	 @return string	The total balance of the Accounts
      */
      public function getAccountsTotal() {
	 return $this->getTotal(CloudBankConsts::LedgerAccountType_Account);
      }
      
      /**
	 @return string	The total balance of the Categories
      */
      public function getCategoriesTotal() {
	 return $this->getTotal(CloudBankConsts::LedgerAccountType_Category);
      }
      
      /**
	 @param Account $p_oldAccount http://pety.homelinux.org/CloudBank/LedgerAccountService
	 @param Account $p_newAccount http://pety.homelinux.org/CloudBank/LedgerAccountService
	 @return bool		Success
      */
      public function modifyAccount($p_oldAccount, $p_newAccount) {
	 CloudBankServer::AssertIDsMatch(
	    $p_newAccount['id'], $p_oldAccount['id']
	 );
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertSameAccountAsCurrent($p_oldAccount);
	 $v_beginningEvent = $this->getBeginningEvent($p_oldAccount['id']);
	 $this->r_eventService->createOrUpdateEvent(
	    $v_beginningEvent['date'], $v_beginningEvent['description'],
	    $p_newAccount['id'], $v_beginningEvent['other_account_id'],
	    $p_newAccount['beginning_balance'], $v_beginningEvent['id']
	 );
	 $this->createOrUpdateLedgerAccount(
	    $p_newAccount['name'], CloudBankConsts::LedgerAccountType_Account,
	    $p_newAccount['id']
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      /**
	 @param Category $p_oldCategory http://pety.homelinux.org/CloudBank/LedgerAccountService
	 @param Category $p_newCategory http://pety.homelinux.org/CloudBank/LedgerAccountService
	 @return bool		Success
      */
      public function modifyCategory($p_oldCategory, $p_newCategory) {
	 CloudBankServer::AssertIDsMatch(
	    $p_newCategory['id'], $p_oldCategory['id']
	 );
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertSameCategoryAsCurrent($p_oldCategory);
	 $this->createOrUpdateLedgerAccount(
	    $p_newCategory['name'], CloudBankConsts::LedgerAccountType_Category,
	    $p_newCategory['id']
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      /**
	 @param string $p_ledgerAccountID
	    The ID of the Category/Account to be deleted
	 @return bool		Success
	 
	 WARNING! This operation also deletes all events related to the
	 Category/Account.
      */
      public function deleteLedgerAccount($p_ledgerAccountID) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertAccountOrCategoryExists($p_ledgerAccountID);
	 $this->r_eventService->deleteAllEvents($p_ledgerAccountID);
	 $this->r_cloudBankServer->execQuery(
	    'DELETE FROM ledger_account WHERE id = :id', 
	    array(':id' => $p_ledgerAccountID)
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }
      
      /* Note that although this method is not annotated, SCA generates an
	 operation for it in the WSDL. However this operation is probably
	 unusable due to the missing annotations - and so type definitions in
	 the WSDL.
      */
      public function createBeginningAccount() {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->createOrUpdateLedgerAccount(
	    self::BeginningAccntName,
	    CloudBankConsts::LedgerAccountType_Beginning
	 );
	 $this->r_cloudBankServer->commitTransaction();
      }

      private function getBeginningAccountID() {
	 $v_result = (
	    $this->r_cloudBankServer->execQuery(
	       'SELECT id FROM ledger_account WHERE type = :type',
	       array(':type' => CloudBankConsts::LedgerAccountType_Beginning)
	    )
	 );
	 return $v_result[0]['id'];
      }

      private function doesExistAndNotThis($p_name, $p_type, $p_id) {
	 $v_queryStr = (
	    '
	       SELECT 1
	       FROM ledger_account
	       WHERE name = :name AND type = :type
	    '
	 );
	 $v_bindArray = array(':name' => $p_name, ':type' => $p_type);
	 if (!is_null($p_id)) {
	    $v_queryStr = $v_queryStr . ' AND id <> :id';
	    $v_bindArray[':id'] = $p_id;
	 }
	 return (
	    count(
	       $this->r_cloudBankServer->execQuery($v_queryStr, $v_bindArray)
	    ) > 0
	 );
      }

      private function createOrUpdateLedgerAccount(
	 $p_name, $p_type, $p_id = NULL
      ) {
	 if (!SchemaDef::IsValidLedgerAccountName($p_name)) {
	    throw new Exception("Invalid LedgerAccount name ($p_name)");
	 }
	 if ($this->doesExistAndNotThis($p_name, $p_type, $p_id)) {
	    throw
	       new Exception("LedgerAccount ($p_name, $p_type) already exists.")
	    ;
	 }
	 $v_accountID = (is_null($p_id) ? CloudBankServer::UUID() : $p_id);
	 $v_bindArray = array(':id' => $v_accountID, ':name' => $p_name);
	 if (is_null($p_id)) $v_bindArray[':type'] = $p_type;
	 $this->r_cloudBankServer->execQuery(
	    (
	       is_null($p_id) ? 
	       '
		  INSERT 
		     INTO ledger_account(id, name, type) VALUES (
			:id, :name, :type
		     )
	       ' :
	       'UPDATE ledger_account SET name = :name WHERE id = :id'
		  // NOTE that the type of the LedgerAccount can not be modified
	    ), $v_bindArray
	 );
	 return $v_accountID;
      }
      private function assertAccountOrCategoryExists(
	 $p_id, $p_ledgerAccountType = NULL
      ) {
	 $v_bindArray[':id'] = $p_id;
	 if (is_null($p_ledgerAccountType)) {
	    $v_typeWhereClause = 'type IN (:account, :category)';
	    $v_bindArray[':account'] = (
	       CloudBankConsts::LedgerAccountType_Account
	    );
	    $v_bindArray[':category'] = (
	       CloudBankConsts::LedgerAccountType_Category
	    );
	 }
	 else {
	    $v_typeWhereClause = 'type = :type';
	    $v_bindArray[':type'] = $p_ledgerAccountType;
	 }
	 if (
	    count(
	       $this->r_cloudBankServer->execQuery(
		  '
		     SELECT 1
			FROM ledger_account
			WHERE id = :id AND ' . $v_typeWhereClause . '
		  ', $v_bindArray
	       )
	    ) == 0
	 ) {
	    throw new Exception(
	       "Referenced Account or Category ($p_id) does not exist."
	    );
	 }
      }
      private function assertSameAccountAsCurrent($p_oldAccount) {
	 $v_currentAccount = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT ledger_account_name, amount
		  FROM account_events
		  WHERE
		     ledger_account_id = :accountID AND
		     other_ledger_account_id = :beginningAccountID
	       ',
	       array(
		  ':accountID' => $p_oldAccount['id'],
		  ':beginningAccountID' => $this->getBeginningAccountID()
	       )
	    )
	 );
	 if (
	    !CloudBankServer::IsEqual(
	       $p_oldAccount, $v_currentAccount[0],
	       array(
		  'name' => 'ledger_account_name',
		  'beginning_balance' => 'amount'
	       )
	    )
	 ) {
            throw new Exception(
               "The Account to be modified ({$p_oldAccount['id']}) does not " .
                  "exist or has been modified by another session. Please try " .
                  "again."
            );
	 }
      }
      private function assertSameCategoryAsCurrent($p_oldCategory) {
	 $v_currentCategory = (
	    $this->r_cloudBankServer->execQuery(
	       'SELECT name FROM ledger_account WHERE id = :accountID',
	       array( ':accountID' => $p_oldCategory['id'])
	    )
	 );
	 if (
	    !CloudBankServer::IsEqual(
	       $p_oldCategory, $v_currentCategory[0], array('name' => 'name')
	    )
	 ) {
            throw new Exception(
               "The Category to be modified ({$p_oldCategory['id']}) does " .
		  "not exist or has been modified by another session. Please " .
		  "try again."
            );
	 }
      }
      private function getBeginningEvent($p_accountID) {
	 $v_beginningEvent = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT
		     id, date, description,
		     other_ledger_account_id AS other_account_id
		  FROM account_events
		  WHERE
		     ledger_account_id = :accountID AND
		     other_ledger_account_id = :beginningAccountID
	       ',
	       array(
		  ':accountID' => $p_accountID,
		  ':beginningAccountID' => $this->getBeginningAccountID()
	       )
	    )
	 );
	 return $v_beginningEvent[0];
      }
      private function getTotal($p_type) {
	 $v_total = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT SUM(amount) AS total
		  FROM account_events
		  WHERE ledger_account_type = :type
	       ', array(':type' => $p_type)
	    )
	 );
	 return $v_total[0]['total'];
      }
      private function getAccountOrCategory($p_id, $p_type) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertAccountOrCategoryExists($p_id, $p_type);
	 $v_map = array('id' => 'id', 'name' => 'name');
	 switch ($p_type) {
	    case CloudBankConsts::LedgerAccountType_Account :
	       $v_queryStr = (
		  '
		     SELECT
			ledger_account_id AS id, ledger_account_name AS name,
			amount
		     FROM account_events
		     WHERE ledger_account_id = :id
		  '
	       );
	       $v_map['amount'] = 'beginning_balance';
	       break;
	    case CloudBankConsts::LedgerAccountType_Category :
	       $v_queryStr = (
		  'SELECT id, name FROM ledger_account WHERE id = :id'
	       );
	       break;
	 }
	 $v_ledgerAccounts = (
	    $this->r_cloudBankServer->execQuery(
	       $v_queryStr, array(':id' => $p_id)
	    )
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return self::ToSDO($v_ledgerAccounts, NULL, $p_type, $v_map);
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
