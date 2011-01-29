<?php
   require_once('SCA/SCA.php');
   require_once('lib/CloudBankConsts.php');

   class Book {
      const MonthFormat = 'Y-m';

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
      public static function PopulateAccountForm(&$p_variables) {
	 self::CopyToOldVars($p_variables, array('name', 'beginning_balance'));
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
	 $p_variables->set('amount', abs($p_variables->get('amount')));
	 self::CopyToOldVars(
	    $p_variables,  
	    array(
	       'date', 'description', 'is_income', 'other_account_id', 'amount'
	    )
	 );
      }
      public static function PreviousMonth($p_month_str) {
	 $v_month = new DateTime($p_month_str);
	 $v_month->modify('-1 month');
	 return $v_month->format(self::MonthFormat);
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
      private static function FormatAmount($p_amount) {
	 return money_format('%!.2n', $p_amount);
      }
      private static function FormatAmounts(&$p_resultSet) {
	 foreach ($p_resultSet as &$v_record) {
	    $v_record['amount_fmt'] = self::FormatAmount($v_record['amount']);
	 }
      }
      private static function FixAmount(
	 &$p_variables, $p_amountVarName, $p_isIncomeVarName
      ) {
	 $p_variables->set(
	    $p_amountVarName,
	    (
	       $p_variables->get($p_amountVarName) *
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
      private static function GetBalance($p_balances, $p_id) {
	 foreach($p_balances as $v_balance) {
	    if ($v_balance['id'] == $p_id) return $v_balance['balance'];
	 }      
	 return NULL;
      }
      
      public function getAccountOrCategoryBalance($p_id) {
	 return (
	    self::FormatAmount($this->r_ledgerAccountService->getBalance($p_id))
	 );
      }
      public function getAccountsOrCategoriesWBalance($p_type) {
	 global $registry;
	 $v_accountsOrCategories = (
	    $p_type == CloudBankConsts::LedgerAccountType_Account ? 
	       $this->getAccounts() :
	       $this->getCategories()
	 );
	 $v_balances = $this->getBalances($p_type);
	 $v_edit_icon = (
	    Horde::img(
	       'edit.png', 'Edit', '', $registry->getImageDir('cloudbank')
	    )
	 );
	 $v_delete_icon = (
	    Horde::img(
	       'delete.png', 'Delete', '', $registry->getImageDir('cloudbank')
	    )
	 );
	 foreach ($v_accountsOrCategories as &$v_accountOrCategory) {
	    $v_accountOrCategory['balance'] = (
	       self::FormatAmount(
		  self::GetBalance($v_balances, $v_accountOrCategory['id'])
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
	 global $registry;
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
	 return (
	    Horde::img(
	       $v_iconFile, $p_type, '', $registry->getImageDir('cloudbank')
	    )
	 );
      }
      public function createAccount($p_variables) {
	 $this->r_ledgerAccountService->createAccount(
	    $p_variables->get('name'), strftime('%Y-%m-%d'),
	    $p_variables->get('beginning_balance')
	 );
      }
      public function modifyAccount($p_variables) {
	 $v_oldAccount_SDO = (
	    self::VariablesToSDO(
	       $p_variables,
	       $this->r_ledgerAccountService->createDataObject(
		  'http://pety.homelinux.org/CloudBank/LedgerAccountService',
		  'Account'
	       ), 
	       array(
		  'account_id' => 'id', 'old_name' => 'name',
		  'old_beginning_balance' => 'beginning_balance',
	       )
	    )
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
		  'beginning_balance' => 'beginning_balance',
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
	 global $registry;
	 $v_events_SDO = (
	    $this->r_eventService->getEvents($p_id, $p_limitMonth . '-01')
	 );
	 $v_events = self::CopyArray($v_events_SDO['Event']);
	 self::FormatAmounts($v_events);
	 $v_delete_icon = (
	    Horde::img(
	       'delete.png', 'Delete', '', $registry->getImageDir('cloudbank')
	    )
	 );
	 foreach ($v_events as &$v_event) {
	    $v_event['account_id'] = $p_id;
	    $v_event['account_type'] = $p_type;
	    $v_event['account_name'] = $p_name;
	    $v_event['delete_icon'] = $v_delete_icon;
	 }
	 return $v_events;
      }
      public function createEvent($p_variables) {
	 self::FixAmount($p_variables, 'amount', 'is_income');
	 $this->r_eventService->createEvent(
	    $p_variables->get('date'), $p_variables->get('description'),
	    $p_variables->get('account_id'),
	    $p_variables->get('other_account_id'), $p_variables->get('amount')
	 );
      }
      public function modifyEvent($p_variables) {
	 self::FixAmount($p_variables, 'old_amount', 'old_is_income');
	 $v_oldEvent_SDO = (
	    self::VariablesToSDO(
	       $p_variables,
	       $this->r_eventService->createDataObject(
		  'http://pety.homelinux.org/CloudBank/EventService', 'Event'
	       ), 
	       array(
		  'event_id' => 'id', 'old_date' => 'date',
		  'old_description' => 'description',
		  'old_other_account_id' => 'other_account_id',
		  'old_other_account_name' => 'other_account_name',
		  'old_other_account_type' => 'other_account_type',
		  'old_amount' => 'amount'
	       )
	    )
	 );
	 self::FixAmount($p_variables, 'amount', 'is_income');
	 $v_newEvent_SDO = (
	    self::VariablesToSDO(
	       $p_variables,
	       $this->r_eventService->createDataObject(
		  'http://pety.homelinux.org/CloudBank/EventService', 'Event'
	       ), 
	       array(
		  'event_id' => 'id', 'date' => 'date',
		  'description' => 'description',
		  'other_account_id' => 'other_account_id',
		  'other_account_name' => 'other_account_name',
		  'other_account_type' => 'other_account_type',
		  'amount' => 'amount'
	       )
	    )
	 );
	 $this->r_eventService->modifyEvent(
	    $p_variables->get('account_id'), $v_oldEvent_SDO, $v_newEvent_SDO
	 );
      }
      public function deleteEvent($p_event_id) {
	 $this->r_eventService->deleteEvent($p_event_id);
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
   }
?>
      
