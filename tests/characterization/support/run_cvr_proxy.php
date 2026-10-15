<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_cvr_proxy.php --- ver 5.0.0 --- 2026.10.03
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.

// Substitute only curl transport symbols in the actual page source. Authentication,
// validation, options and response handling remain the unmodified web implementation.
require_once __DIR__ . '/../../fixtures/cvr/cvrapi_stub.php';
$_GET = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/saldi/sager/cvrLookupProxy.php';
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[2]);
chdir(__DIR__ . '/../../../sager');
function chartest_curl_init($url) { return $url; }
function chartest_curl_setopt_array($ch, $options) {
    if ($options[CURLOPT_TIMEOUT] !== 10 || $options[CURLOPT_USERAGENT] !== 'saldi.dk - kundeopslag (support@saldi.dk)') {
        throw new RuntimeException('Proxy transport changed');
    }
}
function chartest_curl_exec($ch) {
    parse_str(parse_url($ch, PHP_URL_QUERY), $query);
    $GLOBALS['cvrFixture'] = cvrapi_fixture($query['vat'] ?? $query['phone']);
    return $GLOBALS['cvrFixture']['body'];
}
function chartest_curl_getinfo($ch, $option) { return $GLOBALS['cvrFixture']['status']; }
function chartest_curl_error($ch) { return ''; }
function chartest_curl_close($ch) {}
register_shutdown_function(static function () { echo "\nCHARTEST_STATUS=" . (http_response_code() ?: 200); });
$library = __DIR__ . '/../../../includes/stdFunc/cvrLookup.php';
if (is_file($library)) {
    $code = file_get_contents($library);
    foreach (['init','setopt_array','exec','getinfo','error','close'] as $function) {
        $code = str_replace('curl_' . $function . '(', 'chartest_curl_' . $function . '(', $code);
    }
    eval('?>' . $code);
}
$source = file_get_contents(__DIR__ . '/../../../sager/cvrLookupProxy.php');
$source = str_replace('__DIR__', var_export(realpath(__DIR__ . '/../../../sager'), true), $source);
foreach (['init','setopt_array','exec','getinfo','error','close'] as $function) {
    $source = str_replace('curl_' . $function . '(', 'chartest_curl_' . $function . '(', $source);
}
eval('?>' . $source);
