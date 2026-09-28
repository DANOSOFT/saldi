<?php
// 20260915 CDX/PHR Verify shop/customer resolution and existing MySale link compatibility.
// 20260917 CL/LH Cover that a newer customer row does not shadow the staff session.
require_once(__DIR__ . '/../mysale/shopLookup.php');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
function db_escape_string($value) { return str_replace("'", "''", $value); }
function db_select($sql, $location, $global = false) {
    $GLOBALS['queries'][] = array($sql, $global);
    if (!$GLOBALS['rows']) throw new RuntimeException('Unexpected query: ' . $sql);
    $rows = array_shift($GLOBALS['rows']);
    if (strpos($sql, "rettigheder <> '0'") !== false) {
        // Mimic the database filter so customer rows never reach the caller.
        $rows = array_values(array_filter($rows, function ($row) { return $row['rettigheder'] !== '0'; }));
    }
    return new ArrayIterator($rows);
}
function db_fetch_array($result) {
    if (!$result->valid()) return false;
    $row = $result->current();
    $result->next();
    return $row;
}
function checkLookup($condition, $label) {
    if (!$condition) throw new RuntimeException($label);
    echo "PASS $label\n";
}
function db_modify($sql, $location, $global = false) {
    $GLOBALS['writes'][] = array($sql, $global);
}
function lookupCase($rows, $number = '', $session = 'staff-session') {
    $GLOBALS['rows'] = $rows;
    $GLOBALS['queries'] = $GLOBALS['connections'] = $GLOBALS['writes'] = array();
    return mySaleShopLookup($session, $number, 'ssl3.saldi.dk', function ($db) {
        $GLOBALS['connections'][] = $db;
        return true;
    });
}
$shop = array('regnskab'=>'LoppeWorld', 'db'=>'fixture_tenant', 'lukket'=>'', 'lukkes'=>null);
$login = array('db'=>'fixture_tenant','rettigheder'=>'111111','logtime'=>time());
foreach (array(false, array_merge($login,array('rettigheder'=>'0')), array_merge($login,array('logtime'=>time()-86401))) as $invalid) {
    $result = lookupCase(array($invalid ? array($invalid) : array()));
    checkLookup($result['redirect']==='../index/index.php' && !$GLOBALS['connections'] && !$GLOBALS['writes'], 'Missing, portal-only or expired session requires login');
}
$result = lookupCase(array(array($login),array($shop)));
checkLookup($result['shop']==='LoppeWorld' && !$GLOBALS['connections'], 'Staff session selects shop before asking for customer');
checkLookup($GLOBALS['queries'][0][1] && strpos($GLOBALS['queries'][0][0], 'ORDER BY logtime DESC LIMIT 1')!==false, 'Uses latest master online session like Saldi');
checkLookup(strpos($GLOBALS['queries'][0][0], "rettigheder <> '0'")!==false, 'Session lookup filters out customer rows in SQL');
checkLookup(count($GLOBALS['writes'])===1 && $GLOBALS['writes'][0][1], 'Refreshes session activity in master database');
$customerRow = array_merge($login, array('rettigheder'=>'0','logtime'=>time()+60));
$result = lookupCase(array(array($customerRow,$login),array($shop)));
checkLookup($result['shop']==='LoppeWorld' && $result['redirect']==='', 'Newer customer row does not shadow the staff session');
foreach (array(array_merge($shop,array('lukket'=>'on')), array_merge($shop,array('lukkes'=>'2020-01-01'))) as $closed) {
    $result = lookupCase(array(array($login),array($closed)), '1000');
    checkLookup($result['shop']==='' && !$GLOBALS['connections'], 'Closed shop cannot expose customer lookup');
}
$result = lookupCase(array(array($login),array($shop)), "1' OR 1=1");
checkLookup($result['error']!=='' && !$GLOBALS['connections'], 'Rejects invalid customer number');
$result = lookupCase(array(array($login),array($shop),array()), '1482');
checkLookup($result['error']!=='' && $result['redirect']==='', 'Unknown customer does not redirect');
$customer = array('id'=>'504', 'kontonr'=>'001482');
$_GET=array('butik'=>'OtherShop','db'=>'other_tenant');
$_POST=array('butik'=>'OtherShop','db'=>'other_tenant');
$result = lookupCase(array(array($login),array($shop),array($customer)), '001482');
checkLookup(hex2bin(substr($result['redirect'],strlen('mysale.php?id=')))==='504|001482@fixture_tenant@ssl3.saldi.dk', 'Commission link uses authenticated tenant despite supplied shop/db');
checkLookup($GLOBALS['connections']===array('fixture_tenant') && strpos($GLOBALS['queries'][2][0], "art='D'")!==false, 'Customer query is restricted to debtors in session tenant');
$result = lookupCase(array(array($login),array($shop),array($customer,$customer)), '001482');
checkLookup($result['error']!=='' && $result['redirect']==='', 'Ambiguous customer is rejected');
lookupCase(array(array()), '', "session'quoted");
checkLookup(strpos($GLOBALS['queries'][0][0], "session''quoted")!==false, 'Session ID is SQL escaped');
ob_start();
mySaleShopLookupForm('"<customer>', array('shop'=>'<shop>', 'error'=>'<error>', 'redirect'=>''));
$html=ob_get_clean();
checkLookup(strpos($html,'&lt;shop&gt;')!==false && strpos($html,'&quot;&lt;customer&gt;')!==false && strpos($html,'butik')===false, 'Form escapes values and offers no shop selection');

$session = array('mySaleLogoutToken'=>'logout-test-token','mySalePw'=>'authenticated-customer',
    'mySaleAcId'=>504,'mySale'=>'2026-01-01|2026-09-15|id','linkLog'=>1,
    'staffPreference'=>'keep','unrelatedSessionData'=>42);
$before = $session;
checkLookup(!mySaleCustomerLogout($session, 'wrong-token') && $session===$before, 'Invalid logout token leaves authentication unchanged');
checkLookup(!mySaleCustomerLogout($session, array('logout-test-token')) && $session===$before, 'Malformed logout token is rejected');
checkLookup(mySaleCustomerLogout($session, 'logout-test-token'), 'Valid logout is accepted');
checkLookup($session===array('staffPreference'=>'keep','unrelatedSessionData'=>42), 'Logout clears customer identity, filters and token while keeping staff state');
checkLookup(!mySaleCustomerLogout($session, 'logout-test-token'), 'Logout token cannot be replayed');
$GLOBALS['writes']=array();
mySaleRemoveCustomerSession('staff-session');
$sql=$GLOBALS['writes'][0][0];
checkLookup($GLOBALS['writes'][0][1] && strpos($sql,"session_id='staff-session'")!==false
    && strpos($sql,"rettigheder='0'")!==false && strpos($sql,"regnskabsaar='0'")!==false,
    'Logout removes only temporary customer online rows in master');
$result=lookupCase(array(array($login),array($shop)));
checkLookup($result['shop']==='LoppeWorld' && $result['redirect']==='', 'Preserved staff login can return to customer-number form');
ob_start();
mySaleCustomerLogoutButton('logout-test-token');
$html=ob_get_clean();
checkLookup(strpos($html,'Log af')!==false && strpos($html,'method="post"')!==false
    && strpos($html,'name="logoutToken"')!==false && strpos($html,'action="mysale.php"')!==false,
    'Logout button posts protected action to customer-number entry');
