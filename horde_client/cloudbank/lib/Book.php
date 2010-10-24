<?php
   require_once('SCA/SCA.php');
   require_once('lib/CloudBankConsts.php');

   class Book {
      public static function Singleton() {
	 static $v_instance = NULL;
	 if (!isset($v_instance)) {
	    $v_instance = new Book;
	 }
	 return $v_instance;
      }
      public static function SortResultSet(&$p_resultSet, $p_colName) {
	 array_multisort(
	    self::GetColValues($p_resultSet, $p_colName), $p_resultSet
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
      
      public function getAccountBalance($p_id) {
	 return (
	    self::FormatAmount($this->r_ledgerAccountService->getBalance($p_id))
	 );
      }
      public function getAccountsWBalance() {
	 global $registry;
	 $v_accounts = $this->getAccounts();
	 $v_edit_icon = (
	    Horde::img(
	       'edit.png', 'Edit', '', $registry->getImageDir('cloudbank')
	    )
	 );
	 foreach ($v_accounts as &$v_account) {
	    $v_account['balance'] = $this->getAccountBalance($v_account['id']);
	    $v_account['type'] = CloudBankConsts::LedgerAccountType_Account;
	    $v_account['edit_icon'] = $v_edit_icon;
	 }
	 return $v_accounts;
      }
      public function getAccountsTotal() {
	 return (
	    self::FormatAmount(
	       $this->r_ledgerAccountService->getAccountsTotal()
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
      public function getEvents($p_id, $p_type, $p_name) {
	 $v_events_SDO = $this->r_eventService->getEvents($p_id);
	 $v_events = self::CopyArray($v_events_SDO['Event']);
	 self::FormatAmounts($v_events);
	 foreach ($v_events as &$v_event) {
	    $v_event['account_id'] = $p_id;
	    $v_event['account_type'] = $p_type;
	    $v_event['account_name'] = $p_name;
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
      private function __construct() {
	 $this->r_ledgerAccountService = (
	    SCA::getService('LedgerAccountService.wsdl')
	 );
	 $this->r_eventService = (SCA::getService('EventService.wsdl'));
      }
      private function getAccounts() {
	 $v_accounts_SDO = $this->r_ledgerAccountService->getAccounts();
	 return (
	    self::CopyArray(
	       $v_accounts_SDO[CloudBankConsts::LedgerAccountType_Account]
	    )
	 );
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
      
