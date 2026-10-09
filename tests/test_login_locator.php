<?php
// Copyright (c) 2026 Danosoft ApS
// 20261008 CDX/PHR Cover locator failures, registration identity and installation URLs.
require_once(__DIR__ . '/../includes/std_func.php');
require_once(__DIR__ . '/../includes/locatorClient.php');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$checks = 0;
function checkLocator($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $GLOBALS['checks']++;
    echo "PASS: $message\n";
}
foreach (array(array(200, 'Illegal IP'), array(500, '"17,test_9,host/app"'), array(200, '<html>Error</html>'), array(0, false), array(302, '"17,test_9,host/app"')) as $case) {
    checkLocator(loginLocatorDecode($case[0], $case[1]) === null, 'Transport/HTTP/non-JSON failures are ignored');
}
$response = loginLocatorDecode(200, '"17,test_9,https://example.invalid/app"');
checkLocator(loginLocatorGlobalId($response, 'test_9') === 17, 'Valid registration supplies a positive integer ID');
checkLocator(loginLocatorGlobalId($response, 'test_9', 17) === 17, 'Existing matching ID is accepted');
foreach (array(null, array('status'=>'error'), 'DB >17< not found', '17,other_db,host', '0,test_9,host', '-1,test_9,host', '17x,test_9,host', '9999999999999999999999,test_9,host', '17,test_9') as $value) {
    checkLocator(loginLocatorGlobalId($value, 'test_9') === 0, 'Malformed or mismatched registration cannot become an ID');
}
checkLocator(loginLocatorGlobalId($response, 'test_9', 42) === 0, 'Locator cannot silently replace an existing ID');
$server = array('SERVER_NAME'=>'example.invalid', 'SCRIPT_NAME'=>'/finans/index/login.php', 'HTTPS'=>'on', 'SERVER_PORT'=>443);
checkLocator(loginLocatorInstallation($server) === 'https://example.invalid/finans', 'Installation includes its application directory');
$server['HTTPS']='off';$server['SERVER_PORT']=8080;
checkLocator(loginLocatorInstallation($server) === 'http://example.invalid:8080/finans', 'HTTP and non-default port are preserved');
$server['SCRIPT_NAME']='/index/login.php';$server['SERVER_PORT']=80;
checkLocator(loginLocatorInstallation($server) === 'http://example.invalid', 'Root installation has no stray index directory');
$server['SCRIPT_NAME']='/other.php';
checkLocator(loginLocatorInstallation($server) === '', 'Unexpected script path is not registered');
checkLocator(loginLocatorLocation('example.invalid/app/') === 'https://example.invalid/app', 'Legacy scheme-less location is supported');
foreach (array(null, array(), 'javascript:alert(1)', 'https://user:pass@example.invalid', 'https://example.invalid/?x=y', 'https://example.invalid/#x', 'https://example.invalid/" onclick="x') as $value) {
    checkLocator(loginLocatorLocation($value) === '', 'Invalid redirect location is rejected');
}
$params=array('userMail'=>'a+b@example.invalid','dbAlias'=>'A & B','dbLocation'=>'https://example.invalid/app');
parse_str(http_build_query($params,'','&',PHP_QUERY_RFC3986),$parsed);
checkLocator($params === $parsed, 'Encoded email, alias and installation survive query transport');
echo "$checks checks passed.\n";
