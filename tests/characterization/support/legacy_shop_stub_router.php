<?php
// tests/characterization/support/legacy_shop_stub_router.php
//
// Router for the `php -S` stand-in shop used by the legacy shop sync characterization
// (SD-750). Every request is appended as one JSON line to the file named by STUB_LOG, so
// a test can read back exactly what the legacy code sent. A path containing /fail/
// answers 500, so the callers' error counting can be pinned too.
//
// History:
// 20261026 CL/SZ Created (SD-777, sub-task of SD-750).

$path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
parse_str((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $query);
$line = json_encode([
	'method' => $_SERVER['REQUEST_METHOD'],
	'uri'    => $_SERVER['REQUEST_URI'],
	'path'   => $path,
	'query'  => $query,
	'ua'     => $_SERVER['HTTP_USER_AGENT'] ?? '',
], JSON_UNESCAPED_SLASHES);
file_put_contents((string)getenv('STUB_LOG'), $line . "\n", FILE_APPEND | LOCK_EX);
if (str_contains($path, '/fail/')) {
	http_response_code(500);
}
echo 'OK';
