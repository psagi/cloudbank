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
	    $v_record['amount'] = self::FormatAmount($v_record['amount']);
	 }
      }
      
      public function getAccountBalance($p_id) {
	 return (
	    self::FormatAmount($this->r_ledgerAccountService->getBalance($p_id))
	 );
      }
      public function getAccountsWBalance() {
	 $v_accounts = $this->getAccounts();
	 foreach ($v_accounts as &$v_account) {
	    $v_account['balance'] = $this->getAccountBalance($v_account['id']);
	    $v_account['type'] = CloudBankConsts::LedgerAccountType_Account;
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
      public function getEvents($p_id) {
	 $v_events_SDO = $this->r_eventService->getEvents($p_id);
	 $v_events = self::CopyArray($v_events_SDO['Event']);
	 self::FormatAmounts($v_events);
	 return $v_events;
      }
      public function createEvent($p_variables) {
	 $this->r_eventService->createEvent(
	    $p_variables->get('date'), $p_variables->get('description'),
	    $p_variables->get('account_id'),
	    $p_variables->get('other_account_id'), (
	       ($p_variables->get('is_income') ? 1 : -1) *
	       $p_variables->get('amount')
	    )
	 );
      }
/*
      public function modifyEvent($p_variables) {
	 $this->r_eventService->modifyEvent($v_variables->get('account_id'), 
*/     
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
      
