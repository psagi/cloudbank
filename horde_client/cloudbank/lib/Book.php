<?php
   require_once('SCA/SCA.php');
   require_once('lib/CloudBankConsts.php');

   class Book {
      const MonthFormat = 'Y-m';
      const DateFormat = '%Y-%m-%d';

      public static function Singleton() {
	 static $v_instance = NULL;
	 if (!isset($v_instance)) {
	    $v_instance = new Book;
	 }
	 return $v_instance;
      }
      public static function SortResultSet(
	 &$p_resultSet, $p_colName, $p_isDescending = FALSE
      ) {
	 if (empty($p_resultSet)) return;
	 array_multisort(
	    self::GetColValues($p_resultSet, $p_colName),
	    ($p_isDescending ? SORT_DESC : SORT_ASC), $p_resultSet
	 );
      }
      public static function VariablesToSDO(
	 $p_variables, $p_rootDO, $p_mapping
      ) {
	 foreach($p_mapping as $p_variableName => $p_attributeName) {
	    $p_rootDO[$p_attributeName] = $p_variables->get($p_variableName);
	 }
	 return $p_rootDO;
      }
      public static function PopulateEventForm(&$p_variables) {
	 $p_variables->set(
	    'is_income',
	    (
	       ($p_variables->get('amount') >= 0) ==
	       (
		  $p_variables->get('account_type') ==
		  CloudBankConsts::LedgerAccountType_Account
	       )
	    )
	 );
	 $p_variables->set(
	    'amount', self::FormatNumber(abs($p_variables->get('amount')))
	 );
	 $v_quantity = $p_variables->get('quantity');
	 if ($v_quantity != '') {
	    $p_variables->set('quantity', self::FormatNumber(abs($v_quantity)));
	 }
	 self::CopyToOldVars(
	    $p_variables,  
	    array(
	       'date_str', 'description', 'is_income', 'other_account_id',
	       'quantity', 'amount',
	       'is_cleared', 'statement_item_id'
	    )
	 );
      }
      public static function PreviousMonth($p_month_str) {
	 if ($p_month_str == 'all') return $p_month_str;
	 $v_month = new DateTime($p_month_str);
	 $v_month->modify('-1 month');
	 return $v_month->format(self::MonthFormat);
      }
      public static function XtractMessage($p_cloudBankException) {
	 /* This is a work around to get the error message from the exception
	    coming from the seriously broken SOAPFault implementation of SCA_SDO
	 */
	 $v_exceptionMessage = $p_cloudBankException->getMessage();
	 if ($v_xmlOpeningTagPos = strpos($v_exceptionMessage, '<?xml')) {
	    $v_soapFaultXML = (
	       new
		  SimpleXMLElement(
	   	     substr($v_exceptionMessage, $v_xmlOpeningTagPos)
		  )
	       );
	    $v_soapFaultXMLNamespaces = $v_soapFaultXML->getNameSpaces();
	    $v_message = (string)(
	       $v_soapFaultXML->
		  children($v_soapFaultXMLNamespaces['SOAP-ENV'])->Body->Fault->
		  children()->faultstring
	    );
	 }
	 else $v_message = $v_exceptionMessage;
	 return $v_message;
      }
      public static function FormatAmount($p_amount) {
	 Horde::log("Book::FormatAmount($p_amount)", 'DEBUG');
	 return (
	    is_numeric($p_amount) ? money_format('%!.2n', $p_amount) : NULL
	 );
      }
      private static function CopyArray($p_array) {
	 if (is_scalar($p_array)) $v_retval = $p_array;
	 else {
	    foreach($p_array AS $p_key => $p_value) {
	       $v_retval[$p_key] = self::CopyArray($p_value);
	    }
	 }
	 return $v_retval;
      } 
      private static function GetColValues($p_resultSet, $p_colName) {
	 foreach ($p_resultSet as $v_record) {
	    $v_colValues[] = $v_record[$p_colName];
	 }
	 return $v_colValues;
      }
      private static function FormatNumber($p_amount) {
	 return money_format('%!^.2n', $p_amount);
      }
      private static function FormatAmounts(
	 &$p_resultSet, $v_amountFieldName = 'amount'
      ) {
	 foreach ($p_resultSet as &$v_record) {
	    $v_record[$v_amountFieldName . '_fmt'] = (
	       self::FormatAmount($v_record[$v_amountFieldName])
	    );
	 }
      }
      private static function ReformatNumber2CLocale($p_number) {
	 Horde::log("Book::ReformatNumber2CLocale($p_number)", 'DEBUG');
	 $v_localeconv = localeconv();
	 $v_reformattedNumber = (
	    str_replace($v_localeconv['decimal_point'], '.', $p_number)
	 );
	 Horde::log(
	    (
	       "Book::ReformatNumber2CLocale(): \$v_reformattedNumber = " .
	       $v_reformattedNumber
	    ), 'DEBUG'
	 );
	 return $v_reformattedNumber;
      }
      private static function FixAmount(
	 $p_variables, $p_amountVarName, $p_isIncomeVarName
      ) {
	 $v_amount = $p_variables->get($p_amountVarName);
	 if (empty($v_amount)) return $v_amount;
	 return (
	    self::ReformatNumber2CLocale(
	       self::ReformatNumber2CLocale($v_amount) *
	       ($p_variables->get($p_isIncomeVarName) ? 1 : -1)
	    )
	 );
      }
      private static function CopyToOldVars(&$p_variables, $p_varNames) {
	 foreach($p_varNames as $v_variableName) {
	    $p_variables->set(
	       'old_' . $v_variableName, $p_variables->get($v_variableName)
	    );
	 }
      }
      private static function GetAttribute(
	 $p_balances, $p_id, $p_attributeName
      ) {
	 foreach($p_balances as $v_balance) {
	    if ($v_balance['id'] == $p_id) return $v_balance[$p_attributeName];
	 }      
	 return NULL;
      }
      
      public function populateAccountForm(&$p_variables) {
	 if (
	    $p_variables->get('account_type') ==
	    CloudBankConsts::LedgerAccountType_Account
	 ) {
	    $p_variables->set(
	       'beginning_balance',
	       self::FormatNumber($p_variables->get('beginning_balance'))
	    );
	    $v_account_SDO = (
	       $this->r_ledgerAccountService->getAccount(
		  $p_variables->get('account_id')
	       )
	    );
	    $p_variables->set(
	       'is_local_currency', $v_account_SDO['is_local_currency']
	    );
	    $p_variables->set('rate', $v_account_SDO['rate']);
	    $p_variables->set(
	       'beginning_quantity', $v_account_SDO['beginning_quantity']
	    );
	    $v_vars_arr = (
	       array(
		  'name', 'beginning_quantity', 'beginning_balance',
		  'is_local_currency', 'rate'
	       )
	    );
	 }
	 else { $v_vars_arr = array('name'); }
	 self::CopyToOldVars(
	    $p_variables,
	    $v_vars_arr
	 );
      }
      public function getAccountOrCategoryBalance($p_id) {
	 $v_balance_SDO = $this->r_ledgerAccountService->getBalance($p_id);
	 Horde::log(
	    (
	       "Book::getAccountOrCategoryBalance(): \$v_balance_SDO = " .
		  var_export($v_balance_SDO, TRUE)
	    ), 'DEBUG'
	 );
	 return (
	    array(
	       'balance' => $v_balance_SDO->balance,
	       'total_quantity' =>
		  $v_balance_SDO->total_quantity
	    )
	 );
      }
      public function getClearedOrMatchedBalance($p_id) {
	 return $this->r_ledgerAccountService->getBalance($p_id, TRUE)->balance;
      }
      public function getAccountsOrCategoriesWBalance($p_type) {
	 $v_accountsOrCategories = (
	    $p_type == CloudBankConsts::LedgerAccountType_Account ? 
	       $this->getAccounts() :
	       $this->getCategories()
	 );
	 $v_balances = $this->getBalances($p_type);
	 $v_edit_icon = (
	    Horde_Themes_Image::tag('edit.png', array('alt' => 'Edit'))
	 );
	 $v_delete_icon = (
	    Horde_Themes_Image::tag('delete.png', array('alt' => 'Delete'))
	 );
	 foreach ($v_accountsOrCategories as &$v_accountOrCategory) {
	    $v_accountOrCategory['balance'] = (
	       self::FormatAmount(
		  self::GetAttribute(
		     $v_balances, $v_accountOrCategory['id'], 'balance'
		  )
	       )
	    );
	    $v_accountOrCategory['total_quantity'] = (
	       self::FormatAmount(
		  self::GetAttribute(
		     $v_balances, $v_accountOrCategory['id'], 'total_quantity'
		  )
	       )
	    );
	    $v_accountOrCategory['type'] = $p_type;
	    $v_accountOrCategory['edit_icon'] = $v_edit_icon;
	    $v_accountOrCategory['delete_icon'] = $v_delete_icon;
	 }
	 return $v_accountsOrCategories;
      }
      public function getAccountsTotal() {
	 return (
	    self::FormatAmount(
	       $this->r_ledgerAccountService->getAccountsTotal()
	    )
	 );
      }
      public function getCategoriesTotal() {
	 return (
	    self::FormatAmount(
	       $this->r_ledgerAccountService->getCategoriesTotal()
	    )
	 );
      }
      public function getAccountsAndCategories() {
	 $v_accountsAndCategories = (
	    array_merge($this->getAccounts(), $this->getCategories())
	 );
	 foreach ($v_accountsAndCategories as $v_record) {
	    $v_accountsAndCategories_iDNameMap[$v_record['id']] = (
	       $v_record['name']
	    );
	 }
	 asort($v_accountsAndCategories_iDNameMap);
	 return $v_accountsAndCategories_iDNameMap;
      }
      public function getAccountOrCategoryName($p_id, $p_type) {
	 switch ($p_type) {
	    case CloudBankConsts::LedgerAccountType_Account :
	       $v_accountOrCategory = (
		  $this->r_ledgerAccountService->getAccount($p_id)
	       );
	       break;
	    case CloudBankConsts::LedgerAccountType_Category :
	       $v_accountOrCategory = (
		  $this->r_ledgerAccountService->getCategory($p_id)
	       );
	       break;
	    default :
	       throw new Exception("Invalid type ($p_type)");
	       break;
	 }
	 return $v_accountOrCategory['name'];
      }
      public function getAccountOrCategoryIcon($p_type) {
	 switch ($p_type) {
	    case CloudBankConsts::LedgerAccountType_Account :
	       $v_iconFile = 'account.png';
	       break;
	    case CloudBankConsts::LedgerAccountType_Category :
	       $v_iconFile = 'category.png';
	       break;
	    default :
	       throw new Exception("Invalid type ($p_type)");
	       break;
	 }
	 return Horde_Themes_Image::tag($v_iconFile, array('alt' => $p_type));
      }
      public function createAccount($p_variables) {
	 $v_p_variables_dump = var_export($p_variables, TRUE);
	 Horde::log("Book::createAccount($v_p_variables_dump)", 'DEBUG');
	 $this->r_ledgerAccountService->createAccount(
	    $p_variables->get('name'), strftime(self::DateFormat),
	    self::ReformatNumber2CLocale(
	       $p_variables->get('beginning_balance')
	    ), ($p_variables->get('is_local_currency') == TRUE),
	    self::ReformatNumber2CLocale($p_variables->get('rate')),
	    self::ReformatNumber2CLocale(
	       $p_variables->get('beginning_quantity')
	    )
	 );
      }
      public function modifyAccount($p_variables) {
	 $p_variables->set(
	    'old_beginning_quantity',
	    self::ReformatNumber2CLocale(
	       $p_variables->get('old_beginning_quantity')
	    )
	 );
	 $p_variables->set(
	    'old_beginning_balance',
	    self::ReformatNumber2CLocale(
	       $p_variables->get('old_beginning_balance')
	    )
	 );
	 $p_variables->set(
	    'old_rate',
	    self::ReformatNumber2CLocale($p_variables->get('old_rate'))
	 );
	 $v_oldAccount_SDO = (
	    self::VariablesToSDO(
	       $p_variables,
	       $this->r_ledgerAccountService->createDataObject(
		  'http://pety.homelinux.org/CloudBank/LedgerAccountService',
		  'Account'
	       ), 
	       array(
		  'account_id' => 'id', 'old_name' => 'name',
		  'old_beginning_quantity' => 'beginning_quantity',
		  'old_beginning_balance' => 'beginning_balance',
		  'old_is_local_currency' => 'is_local_currency',
		  'old_rate' => 'rate'
	       )
	    )
	 );
	 $p_variables->set(
	    'beginning_quantity',
	    self::ReformatNumber2CLocale(
	       $p_variables->get('beginning_quantity')
	    )
	 );
	 $p_variables->set(
	    'beginning_balance',
	    self::ReformatNumber2CLocale($p_variables->get('beginning_balance'))
	 );
	 $p_variables->set(
	    'rate', self::ReformatNumber2CLocale($p_variables->get('rate'))
	 );
	 $v_newAccount_SDO = (
	    self::VariablesToSDO(
	       $p_variables,
	       $this->r_ledgerAccountService->createDataObject(
		  'http://pety.homelinux.org/CloudBank/LedgerAccountService',
		  'Account'
	       ), 
	       array(
		  'account_id' => 'id', 'name' => 'name',
		  'beginning_quantity' => 'beginning_quantity',
		  'beginning_balance' => 'beginning_balance',
		  'is_local_currency' => 'is_local_currency', 'rate' => 'rate'
	       )
	    )
	 );
	 $this->r_ledgerAccountService->modifyAccount(
	    $v_oldAccount_SDO, $v_newAccount_SDO
	 );
      }
      public function deleteAccount($p_account_id) {
	 $this->r_ledgerAccountService->deleteLedgerAccount($p_account_id);
      }
      public function createCategory($p_variables) {
	 $this->r_ledgerAccountService->createCategory(
	    $p_variables->get('name')
	 );
      }
      public function modifyCategory($p_variables) {
	 $v_oldCategory_SDO = (
	    self::VariablesToSDO(
	       $p_variables,
	       $this->r_ledgerAccountService->createDataObject(
		  'http://pety.homelinux.org/CloudBank/LedgerAccountService',
		  'Category'
	       ), array('account_id' => 'id', 'old_name' => 'name')
	    )
	 );
	 $v_newCategory_SDO = (
	    self::VariablesToSDO(
	       $p_variables,
	       $this->r_ledgerAccountService->createDataObject(
		  'http://pety.homelinux.org/CloudBank/LedgerAccountService',
		  'Category'
	       ), array('account_id' => 'id', 'name' => 'name')
	    )
	 );
	 $this->r_ledgerAccountService->modifyCategory(
	    $v_oldCategory_SDO, $v_newCategory_SDO
	 );
      }
      public function getEvents($p_id, $p_type, $p_name, $p_limitMonth) {
	 $v_events_SDO = (
	    $this->r_eventService->getEvents(
	       $p_id, ($p_limitMonth == 'all' ? NULL : $p_limitMonth . '-01')
	    )
	 );
	 $v_events = self::CopyArray($v_events_SDO['Event']);
	 if (empty($v_events)) return $v_events;
	 self::FormatAmounts($v_events, 'amount');
	 self::FormatAmounts($v_events, 'quantity');
	 $v_delete_icon = (
	    Horde_Themes_Image::tag('delete.png', array('alt' => 'Delete'))
	 );
	 foreach ($v_events as &$v_event) {
	    $v_event['id_encoded'] = Cloudbank::EncodeID($v_event['id']);
	    $v_event['account_id'] = $p_id;
	    $v_event['account_type'] = $p_type;
	    $v_event['account_name'] = $p_name;
	    $v_event['limit_month'] = $p_limitMonth;
	    $v_event['delete_icon'] = $v_delete_icon;
	    $v_event['delete_icon_link'] = '';
	    $v_event['is_beginning'] = (
	       $v_event['other_account_type'] ==
	       CloudbankConsts::LedgerAccountType_Beginning
	    );
	 }
	 return $v_events;
      }
      public function createEvent($p_variables) {
	 $v_amount = self::FixAmount($p_variables, 'amount', 'is_income');
	 $v_quantity = self::FixAmount($p_variables, 'quantity', 'is_income');
	 $this->r_eventService->createEvent(
	    $p_variables->get('date_str'), $p_variables->get('description'),
	    $p_variables->get('account_id'),
	    $p_variables->get('other_account_id'), $v_amount,
	    $p_variables->get('statement_item_id'),
	    ($p_variables->get('is_cleared') ? true : false), $v_quantity
	 );
      }
      public function modifyEvent($p_variables) {
	 $v_oldEvent_SDO = (
	    self::VariablesToSDO(
	       $p_variables,
	       $this->r_eventService->createDataObject(
		  'http://pety.homelinux.org/CloudBank/EventService', 'Event'
	       ), 
	       array(
		  'event_id' => 'id', 'old_date_str' => 'date',
		  'old_description' => 'description',
		  'old_other_account_id' => 'other_account_id',
		  'old_other_account_name' => 'other_account_name',
		  'old_other_account_type' => 'other_account_type',
		  'old_quantity' => 'quantity',
		  'old_amount' => 'amount', 'old_is_cleared' => 'is_cleared',
		  'old_statement_item_id' => 'statement_item_id'
	       )
	    )
	 );
	 $v_oldAmount = (
	    self::FixAmount($p_variables, 'old_amount', 'old_is_income')
	 );
	 $v_oldEvent_SDO->amount = $v_oldAmount;
	 $v_oldQuantity = (
	    self::FixAmount($p_variables, 'old_quantity', 'old_is_income')
	 );
	 $v_oldEvent_SDO->quantity = $v_oldQuantity;
	 $v_newEvent_SDO = (
	    self::VariablesToSDO(
	       $p_variables,
	       $this->r_eventService->createDataObject(
		  'http://pety.homelinux.org/CloudBank/EventService', 'Event'
	       ), 
	       array(
		  'event_id' => 'id', 'date_str' => 'date',
		  'description' => 'description',
		  'other_account_id' => 'other_account_id',
		  'other_account_name' => 'other_account_name',
		  'other_account_type' => 'other_account_type',
		  'quantity' => 'quantity',
		  'amount' => 'amount', 'is_cleared' => 'is_cleared',
		  'statement_item_id' => 'statement_item_id'
	       )
	    )
	 );
	 $v_amount = self::FixAmount($p_variables, 'amount', 'is_income');
	 $v_newEvent_SDO->amount = $v_amount;
	 $v_quantity = self::FixAmount($p_variables, 'quantity', 'is_income');
	 $v_newEvent_SDO->quantity = $v_quantity;
	 $this->r_eventService->modifyEvent(
	    $p_variables->get('account_id'), $v_oldEvent_SDO, $v_newEvent_SDO
	 );
      }
      public function updateIsClearedAttribute(
	 $p_eventID, $p_isCleared, $p_accountID
      ) {
	 $v_oldEvent_SDO = (
	    $this->r_eventService->getEvent($p_eventID, $p_accountID)
	 );
	 $v_newEvent_SDO = clone $v_oldEvent_SDO;
	 $v_newEvent_SDO->is_cleared = $p_isCleared;
	 $this->r_eventService->modifyEvent(
	    $p_accountID, $v_oldEvent_SDO, $v_newEvent_SDO
	 );
      }
      public function deleteEvent($p_event_id) {
	 $this->r_eventService->deleteEvent($p_event_id);
      }
      public function importStatement($p_filename) {
	 Horde::log("Book::importStatement($p_filename)", 'DEBUG');
	 if (!($v_file = fopen($p_filename, 'r'))) return FALSE;
	 $v_statement = (
	    $this->r_statementService->createDataObject(
	       'http://pety.dynu.net/CloudBank/StatementService', 'Statement'
	    )
	 );
	 while ($v_line = preg_replace('/\n/', '', fgets($v_file))) {
	    /* SCA can not properly handle non-printable characters when
	       composing the SOAP request so validation must be done here */
	    if (
	       !mb_check_encoding($v_line, 'UTF-8') ||
	       preg_match('/\p{C}/u', $v_line)
	    ) return FALSE;
	    $v_statement->StatementLine->insert($v_line);
	 }
	 fclose($v_file);
//var_dump($v_statement);
	 $this->r_statementService->importStatement($v_statement);
	 return TRUE;
      }
      public function purgeStatement() {
	 $this->r_statementService->purge();
      }
      public function getAccountsForStatement() {
	 $v_accounts_SDO = (
	    $this->r_statementService->findAccountsForStatement()
	 );
	 return (
	    self::CopyArray(
	       $v_accounts_SDO[CloudBankConsts::LedgerAccountType_Account]
	    )
	 );
      }
      public function isThereStatementForAccount($p_id) {
	 return $this->r_statementService->isThereStatementForAccount($p_id);
      }
      public function getUnmatchedStatementItems(
	 $p_id, $p_accountName, $p_limitMonth
      ) {
	 $v_statementItems_SDO = (
	    $this->r_statementService->findUnmatchedItems($p_id)
	 );
	 $v_statementItems = (
	    self::CopyArray($v_statementItems_SDO['StatementItem'])
	 );
	 self::FormatAmounts($v_statementItems);
	 foreach ($v_statementItems as &$v_statementItem) {
	    $v_statementItem['description_short'] = (
	       mb_substr(
		  $v_statementItem['description'], 0,
		  CloudBankConsts::EventDescriptionLength
	       )
	    );
	    $v_statementItem['account_id'] = $p_id;
	    $v_statementItem['account_name'] = $p_accountName;
	    $v_statementItem['account_type'] = (
	       CloudBankConsts::LedgerAccountType_Account
	    );
	    $v_statementItem['limit_month'] = $p_limitMonth;
	 }
	 return $v_statementItems;
      }
      public function matchStatementItems($p_account_id) {
	 $this->r_statementService->match($p_account_id);
      }
      public function clearAllMatchedEvents($p_account_id) {
	 $this->r_statementService->clearAllMatchedEvents($p_account_id);
      }
      public function getOpeningBalance($p_id) {
//echo "DEBUG: getOpeningBalance($p_id)<br>";
	 $v_statementItem_SDO = (
	    $this->r_statementService->findOpeningBalance($p_id)
	 );
	 $v_statementItem = self::CopyArray($v_statementItem_SDO);
//var_dump($v_statementItem);
	 $v_statementItem['amount_fmt'] = (
	    self::FormatAmount($v_statementItem['amount'])
	 );
	 return $v_statementItem;
      }
      public function getClosingBalance($p_id) {
	 $v_statementItem_SDO = (
	    $this->r_statementService->findClosingBalance($p_id)
	 );
	 $v_statementItem = self::CopyArray($v_statementItem_SDO);
//var_dump($v_statementItem);
	 $v_statementItem['amount_fmt'] = (
	    self::FormatAmount($v_statementItem['amount'])
	 );
	 return $v_statementItem;
      }
      public function isLocalCurrencyAccount($p_accountID) {
	 return (
	    $this->r_ledgerAccountService->getAccount($p_accountID)->
	       is_local_currency
	 );
      }
      public function getReconcileToRateAmount($p_accountID) {
	 return (
	    -$this->r_ledgerAccountService->getReconcileToRateAmount(
	       $p_accountID
	    )
	 );
      }

      private function __construct() {
	 global $conf;
	 $v_cloudBankServerLocation = $conf['cloudbank_server_location'] . '/';
	 $this->r_ledgerAccountService = (
	    SCA::getService(
	       'wsdl/LedgerAccountService.wsdl', 'soap',
	       array(
		  'location' => (
		     $v_cloudBankServerLocation . 'LedgerAccountService.php'
		  )
	       )
	    )
	 );
	 $this->r_eventService = (
	    SCA::getService(
	       'wsdl/EventService.wsdl', 'soap',
	       array(
		  'location' => $v_cloudBankServerLocation . 'EventService.php'
	       )
	    )
	 );
	 $this->r_statementService = (
	    SCA::getService(
	       'wsdl/StatementService.wsdl', 'soap',
	       array(
		  'location' => (
		     $v_cloudBankServerLocation . 'StatementService.php'
		  )
	       )
	    )
	 );
      }
      private function getAccounts() {
	 $v_accounts_SDO = $this->r_ledgerAccountService->getAccounts();
	 return (
	    self::CopyArray(
	       $v_accounts_SDO[CloudBankConsts::LedgerAccountType_Account]
	    )
	 );
      }
      private function getBalances($p_type) {
	 $v_accounts_SDO = $this->r_ledgerAccountService->getBalances($p_type);
	 return self::CopyArray($v_accounts_SDO['Balance']);
      }
      private function getCategories() {
	 $v_categories_SDO = $this->r_ledgerAccountService->getCategories();
	 return (
	    self::CopyArray(
	       $v_categories_SDO[CloudBankConsts::LedgerAccountType_Category]
	    )
	 );
      }

      private $r_ledgerAccountService;
      private $r_eventService;
      private $r_statementService;
   }
?>
