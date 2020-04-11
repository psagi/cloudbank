<?php
   require_once(dirname(__FILE__) . '/../lib/CloudBankConsts.php');
   require_once('Debug.php');
   require_once('Util.php');
   require_once('CloudBankServer.php');
   include('SCA/SCA.php');

   /**
      @service
      @binding.soap
      @types http://pety.homelinux.org/CloudBank/LedgerAccountService ../lib/LedgerAccountService.xsd
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
      private static function AssertBeginningQuantityRule(
	 $p_isLocalCurrency, $p_beginningQuantity
      ) {
	 if ($p_isLocalCurrency && !empty($p_beginningQuantity)) {
	    throw (
	       new Exception(
		  "Only Accounts in non-local currency can have beginning " .
		     "quantity"
	       )
	    );
	 }
      }
      /*
	 PHP SDO XML does not implement the default attribute of the XSD. This
	 is to fix this behaviour
      */
      private static function ApplyDefaults(&$p_account) {
	 if (!isset($p_account['is_local_currency'])) {
	    $p_account['is_local_currency'] = TRUE;
	 }
      }

      public function __construct() {
	 $this->r_cloudBankServer = CloudBankServer::Singleton();
      }

      /**
	 @param string $p_name	The name of the account
	 @param string $p_date	The date of creation of the account (YYYY-MM-DD)
	 @param string $p_beginningBalance	\
	    The beginning balance of the account 
	 @param boolean $p_is_local_currency	\
	    False if the balance of the account is not in the local currency \
	    (e.g. foreign currency or securities account)
	 @param string $p_rate	Rate/price on non-local-currency account
	 @param string $p_beginningQuantity	\
	    The beginning quantity balance of the account. Valid only if \
	    account is not in local currency.
	 @return boolean	Success
      */
      public function createAccount(
	 $p_name, $p_date, $p_beginningBalance, $p_is_local_currency = true,
	 $p_rate = NULL, $p_beginningQuantity = NULL 
      ) {
	 self::AssertBeginningQuantityRule(
	    $p_is_local_currency, $p_beginningQuantity
	 );
	 $this->r_cloudBankServer->beginTransaction();
	 $v_accntID = $this->createOrUpdateLedgerAccount(
	    $p_name, CloudBankConsts::LedgerAccountType_Account,
	    $p_is_local_currency, $p_rate
	 );
	 $this->r_eventService->createOrUpdateEvent(
	    $p_date, self::BeginningEvntDesc, $v_accntID,
	    self::GetBeginningAccountID(), $p_beginningBalance, NULL, true,
	    $p_beginningQuantity
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      /**
	 @param string $p_name	The name of the category
	 @return boolean	Success
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
	 return $this->findAccounts();
      }

      public function findAccounts($p_is4StatementOnly = FALSE) {
	 $this->r_cloudBankServer->beginTransaction();
	 $v_accounts = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT 
		     DISTINCT
		     ae.ledger_account_id, ae.ledger_account_name, ae.amount
		  FROM account_events ae
	       ' . ($p_is4StatementOnly ? ', statement_item si' : '') .
	       '
		  WHERE
		     ae.ledger_account_type = :type AND
		     ae.other_ledger_account_id = :beginningAccountID
	       ' . 
	       (
   		  $p_is4StatementOnly ?
  		  ' AND ae.ledger_account_id = si.ledger_account_id' :
   		  ''
  	       ), 
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
	 @return Balance http://pety.homelinux.org/CloudBank/LedgerAccountService
	    The total and the cleared or matched balance of the account/category
      */
      public function getBalance(
	 $p_ledgerAccountID
      ) {
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertAccountOrCategoryExists($p_ledgerAccountID);
	 $v_balance = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT
		     id, balance, cleared_or_matched_balance, total_quantity
		  FROM ledger_account_balances
		  WHERE
		     id = :ledgerAccountID
	       ',
	       array(
		  ':ledgerAccountID' => $p_ledgerAccountID
	       )
	    )
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return self::ToSDO(
	    $v_balance, NULL, 'Balance',
	    array(
	       'id' => 'id', 'balance' => 'balance',
	       'cleared_or_matched_balance' => 'cleared_or_matched_balance',
	       'total_quantity' => 'total_quantity'
	    )
	 );
      }

      /**
	 @param string $p_ledgerAccountID	The ID of the Account
	 @return string
	    The reconciliation amount. (Calculated as the difference between
	    the current balance and the balance calculated based on the total
	    quantity and the rate.
      */
      public function getReconcileToRateAmount($p_ledgerAccountID) {
	 $this->assertAccountOrCategoryExists(
	    $p_ledgerAccountID, CloudBankConsts::LedgerAccountType_Account
	 );
	 $this->assertIsNotLocalCurrencyAccount($p_ledgerAccountID);
	 $v_balance_SDO = $this->getBalance($p_ledgerAccountID);
	 return (
	    (
	       $v_balance_SDO->total_quantity *
	       $this->getAccount($p_ledgerAccountID)->rate
	    ) - $v_balance_SDO->balance
	 );
      }
      /**
	 @param string $p_ledgerAccountType	Either Account or Category
	 @return BalanceSet http://pety.homelinux.org/CloudBank/LedgerAccountService
	 NOTE that this operation is redundant, however needed for having
	 satisfactory performance when building an Account/Category overview
	 screen.
      */
      public function getBalances($p_ledgerAccountType) {
	 $v_balances = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT id, balance, cleared_or_matched_balance, total_quantity
		  FROM ledger_account_balances
		  WHERE type = :ledgerAccountType
	       ', array(':ledgerAccountType' => $p_ledgerAccountType)
	    )
	 );
	 return (
	    self::ToSDO(
	       $v_balances, 'BalanceSet', 'Balance',
	       array(
		  'id' => 'id', 'balance' => 'balance',
		  'cleared_or_matched_balance' => 'cleared_or_matched_balance',
		  'total_quantity' => 'total_quantity'
	       )
	    )
	 );
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
	 @return boolean	Success
      */
      public function modifyAccount($p_oldAccount, $p_newAccount) {
	 Debug::Singleton()->log(
	    "LedgerAccountService::modifyAccount(" .
	       var_export($p_oldAccount, true) . ", " .
	       var_export($p_newAccount, true) . ")"
	 );
	 $v_oldAccount = $p_oldAccount;
	 self::ApplyDefaults($v_oldAccount);
	 $v_newAccount = $p_newAccount;
	 self::ApplyDefaults($v_newAccount);
	 CloudBankServer::AssertIDsMatch(
	    $v_newAccount['id'], $v_oldAccount['id']
	 );
	 self::AssertBeginningQuantityRule(
	    $v_newAccount['is_local_currency'],
	    $v_newAccount['beginning_quantity']
	 );
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertSameAccountAsCurrent($v_oldAccount);
	 $v_beginningEvent = $this->getBeginningEvent($v_oldAccount['id']);
	 $this->createOrUpdateLedgerAccount(
	    $v_newAccount['name'], CloudBankConsts::LedgerAccountType_Account,
	    $v_newAccount['is_local_currency'], $v_newAccount['rate'],
	    $v_newAccount['id']
	 );
	 $this->r_eventService->createOrUpdateEvent(
	    $v_beginningEvent['date'], $v_beginningEvent['description'],
	    $v_newAccount['id'], $v_beginningEvent['other_account_id'],
	    $v_newAccount['beginning_balance'], $v_beginningEvent['statement_item_id'], 
	    $v_beginningEvent['is_cleared'],
	    $v_newAccount['beginning_quantity'],
	    $v_beginningEvent['id'] 
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      /**
	 @param Category $p_oldCategory http://pety.homelinux.org/CloudBank/LedgerAccountService
	 @param Category $p_newCategory http://pety.homelinux.org/CloudBank/LedgerAccountService
	 @return boolean	Success
      */
      public function modifyCategory($p_oldCategory, $p_newCategory) {
	 CloudBankServer::AssertIDsMatch(
	    $p_newCategory['id'], $p_oldCategory['id']
	 );
	 $this->r_cloudBankServer->beginTransaction();
	 $this->assertSameCategoryAsCurrent($p_oldCategory);
	 $this->createOrUpdateLedgerAccount(
	    $p_newCategory['name'], CloudBankConsts::LedgerAccountType_Category,
	    TRUE, NULL, $p_newCategory['id']
	 );
	 $this->r_cloudBankServer->commitTransaction();
	 return true;
      }

      /**
	 @param string $p_ledgerAccountID
	    The ID of the Category/Account to be deleted
	 @return boolean	Success
	 
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
      
      /* Note that although these methods are not annotated, SCA generates an
	 operation for them in the WSDL. However these operations are probably
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

      public function findAccount($p_name, $p_type) {
	 $v_queryStr = (
	    '
	       SELECT id
	       FROM ledger_account
	       WHERE name = :name AND type = :type
	    '
	 );
	 $v_bindArray = array(':name' => $p_name, ':type' => $p_type);
	 $v_result_arr = (
	    $this->r_cloudBankServer->execQuery($v_queryStr, $v_bindArray)
	 );
	 return (
	    empty($v_result_arr) ? NULL : $v_result_arr[0]['id']
	 );
      }

      private function doesExistAndNotThis($p_name, $p_type, $p_id = NULL) {
	 $v_account_id = $this->findAccount($p_name, $p_type);
	 return (is_null($v_account_id) ? FALSE : ($v_account_id <> $p_id));
      }
 
      public function assertAccountOrCategoryExists(
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


      private function getBeginningAccountID() {
	 $v_result = (
	    $this->r_cloudBankServer->execQuery(
	       'SELECT id FROM ledger_account WHERE type = :type',
	       array(':type' => CloudBankConsts::LedgerAccountType_Beginning)
	    )
	 );
	 return $v_result[0]['id'];
      }

      private function createOrUpdateLedgerAccount(
	 $p_name, $p_type, $p_is_local_currency = true, $p_rate = NULL,
	 $p_id = NULL
      ) {
	 if (!SchemaDef::IsValidLedgerAccountName($p_name)) {
	    throw new Exception("Invalid LedgerAccount name ($p_name)");
	 }
	 if (!SchemaDef::IsValidRate($p_rate)) {
	    throw (
	       new Exception(
		  "Invalid rate ($p_rate). Must be empty or a floating point " .
		     "number"
	       )
	    );
	 }
	 if ($this->doesExistAndNotThis($p_name, $p_type, $p_id)) {
	    throw
	       new Exception("LedgerAccount ($p_name, $p_type) already exists.")
	    ;
	 }
	 $v_accountID = (is_null($p_id) ? CloudBankServer::UUID() : $p_id);
	 $v_bindArray = (
	    array(
	       ':id' => $v_accountID, ':name' => $p_name,
	       ':is_local_currency' => $p_is_local_currency, ':rate' => $p_rate
	    )
	 );
	 if (is_null($p_id)) $v_bindArray[':type'] = $p_type;
	 $this->r_cloudBankServer->execQuery(
	    (
	       is_null($p_id) ? 
	       '
		  INSERT 
		     INTO ledger_account(
			id, name, type, is_local_currency, rate
		     )
		     VALUES (
			:id, :name, :type, :is_local_currency, :rate
		     )
	       ' :
	       '
		  UPDATE ledger_account
		     SET
			name = :name, is_local_currency = :is_local_currency,
			rate = :rate
		     WHERE id = :id
	       '
		  // NOTE that the type of the LedgerAccount can not be modified
	    ), $v_bindArray
	 );
	 return $v_accountID;
      }
      private function assertSameAccountAsCurrent($p_oldAccount) {
	 $v_currentAccount = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT
		     la.name, la.is_local_currency, la.rate, ae.quantity,
		     ae.amount
		  FROM ledger_account la, account_events ae
		  WHERE
		     ae.ledger_account_id = la.id AND
		     ae.ledger_account_id = :accountID AND
		     ae.other_ledger_account_id = :beginningAccountID
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
		  'name' => 'name',
		  'is_local_currency' => 'is_local_currency', 'rate' => 'rate',
		  'beginning_quantity' => 'quantity',
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
      private function assertIsNotLocalCurrencyAccount($p_ledgerAccountID) {
	 if (
	    count(
	       $this->r_cloudBankServer->execQuery(
		  '
		     SELECT 1
			FROM ledger_account
			WHERE id = :id AND is_local_currency = 0
		  ', array(':id' => $p_ledgerAccountID)
	       )
	    ) == 0
	 ) {
	    throw new Exception(
	       "Referenced Account ($p_ledgerAccountID) is in local currency."
	    );
	 }
      }
      private function getBeginningEvent($p_accountID) {
	 $v_beginningEvent = (
	    $this->r_cloudBankServer->execQuery(
	       '
		  SELECT
		     id, date, description,
		     other_ledger_account_id AS other_account_id,
		     statement_item_id AS statement_item_id, is_cleared AS is_cleared
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
			la.id, la.name, ae.quantity,
			ae.amount, la.is_local_currency, la.rate
		     FROM ledger_account la, account_events ae
		     WHERE
			la.id = :id AND ae.ledger_account_id = la.id AND
			other_ledger_account_id = :beginning_account_id
		  '
	       );
	       $v_ledgerAccounts = (
		  $this->r_cloudBankServer->execQuery(
		     $v_queryStr,
		     array(':id' => $p_id,
			':beginning_account_id' => (
			   $this->getBeginningAccountID($p_id)
			)
		     )
		  )
	       );
	       $v_map['quantity'] = 'beginning_quantity';
	       $v_map['amount'] = 'beginning_balance';
	       $v_map['is_local_currency'] = 'is_local_currency';
	       $v_map['rate'] = 'rate';
	       break;
	    case CloudBankConsts::LedgerAccountType_Category :
	       $v_queryStr = (
		  'SELECT id, name FROM ledger_account WHERE id = :id'
	       );
	       $v_ledgerAccounts = (
		  $this->r_cloudBankServer->execQuery(
		     $v_queryStr, array(':id' => $p_id)
		  )
	       );
	       break;
	 }
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
