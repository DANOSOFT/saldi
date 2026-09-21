<?php
// 20260921 CDX/LH Exercise production dispatch with strict errors and denied-write adapters.
namespace SaldiDispatchTest;
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) {
    throw new \RuntimeException($message);
});
function access_check() {
    $GLOBALS['dispatchAuthCalls']++;
    return $GLOBALS['dispatchAuthResult'];
}
function db_modify($sql, $location = '') {
    throw new \RuntimeException('Dispatch attempted an unexpected write: ' . $sql);
}
function db_select($sql, $location = '') {
    throw new \RuntimeException('Dispatch attempted an unexpected query: ' . $sql);
}
require_once(__DIR__ . '/../includes/std_func.php');
$source = file_get_contents(__DIR__ . '/../api/rest_api.php');
$start = strpos($source, '$possible_url = array(');
$end = strpos($source, 'function chk4utf8', $start);
if ($start === false || $end === false) {
    throw new \RuntimeException('Production dispatch boundaries not found');
}
$dispatch = 'namespace SaldiDispatchTest; ' . substr($source, $start, $end - $start);
$work = sys_get_temp_dir() . '/saldi-dispatch-' . getmypid();
mkdir($work . '/api', 0700, true);
mkdir($work . '/temp/dispatch_fixture', 0700, true);
chdir($work . '/api');
$db = 'dispatch_fixture';
try {
    foreach ([[], ['action' => null], ['action' => ''], ['action' => 'TESTLAB_UNKNOWN'], ['action' => 'update_table'], ['action' => 'insert_into_table']] as $request) {
        foreach (['OK', 'Access denied (key)', 'Access denied (user)'] as $authorization) {
            $_GET = $request;
            $GLOBALS['dispatchAuthCalls'] = 0;
            $GLOBALS['dispatchAuthResult'] = $authorization;
            $expected = isset($request['action']) ? ($authorization === 'OK' ? 'Illegal action (' . $request['action'] . ')' : $authorization) : null;
            ob_start();
            try {
                eval($dispatch);
            } finally {
                $output = ob_get_clean();
            }
            if ($value !== $expected || $output !== '' || $GLOBALS['dispatchAuthCalls'] !== (int)isset($request['action'])) {
                throw new \RuntimeException('Incorrect response, auth gate or unexpected output for ' . json_encode($request));
            }
            echo 'PASS: strict production dispatch ' . json_encode($request) . ' / ' . $authorization . "\n";
        }
    }
} finally {
    if (file_exists($work . '/temp/dispatch_fixture/rest_api.log')) {
        unlink($work . '/temp/dispatch_fixture/rest_api.log');
    }
    chdir(sys_get_temp_dir());
    rmdir($work . '/temp/dispatch_fixture');
    rmdir($work . '/temp');
    rmdir($work . '/api');
    rmdir($work);
}
