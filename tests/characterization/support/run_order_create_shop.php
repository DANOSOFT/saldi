<?php
// tests/characterization/support/run_order_create_shop.php
//
// Child-process runner for the webshop order-creation path (SD-600):
// api/rest_api.php::insert_shop_order(), reached in production only through
// the file's own top-level GET dispatch (api/rest_api.php:1188-1264) behind
// access_check() (api/rest_api.php:1040-1135) - there is no way to call the
// function directly the way the other two paths allow, so this runner drives
// the whole page the way run_bogfor_page.php drives finans/bogfor.php.
//
// rest_api.php ends with `exit(json_encode($value))` (its own production
// contract, not a characterization convention) - $value is insert_shop_order()'s
// raw return value: the new ordrer.id as a numeric STRING on success (it's
// `$r['id']` straight from db_fetch_array(), never cast - api/rest_api.php:477),
// or a human-readable string on rejection (duplicate, bad IP, wrong key, etc).
// That raw value is this runner's entire stdout; the test decodes it itself
// rather than via CharacterizationTestCase::runChildJson() (which requires a
// JSON *object*).
//
// argv: 1 = tenant db (e.g. saldi_chartest)
//       2 = JSON-encoded assoc array of $_GET params for action=insert_shop_order
//
// Prints insert_shop_order()'s raw return value, JSON-encoded, with no
// trailing newline (that is what rest_api.php's own exit() does).
//
// History:
// 20260805 CL/SZ SD-600: created.
// 20260814 CL/SZ SD-600: set REQUEST_URI so get_relative()'s temp/ path depth matches the api/ chdir.
// 20260814 CL/SZ SD-600: corrected this comment - the success return value is a numeric string, not an int.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_order_create_shop.php <tenant_db> <json_get_params>\n");
    exit(2);
}
$tenantDb = $argv[1];
$params = json_decode($argv[2], true);
if (!is_array($params)) {
    fwrite(STDERR, "argv[2] must be a JSON object\n");
    exit(2);
}

error_reporting(E_ERROR | E_PARSE); // rest_api.php is warning-noisy on legacy globals

// access_check() (api/rest_api.php:1099-1134) allows any IP once
// Fixtures::apiAccess() seeds grupper art='API' box2='*'; REMOTE_ADDR only
// needs to be a plausible value, not a specific one.
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

$_GET = $params;
$_GET['action'] = 'insert_shop_order';
$_GET['db'] = $tenantDb;
$_GET['saldiuser'] = $_GET['saldiuser'] ?? 'chartest';

// Matches run_bogfor_page.php / bootstrap_ordrefunc.php: get_relative()
// (includes/db_query.php) derives its "../" depth from REQUEST_URI's slash
// count, not cwd - without this, db_select()'s temp/ logging path resolves
// one level too shallow and fopen() fails on the missing directory.
$_SERVER['REQUEST_URI'] = '/saldi/api/rest_api.php';

chdir(dirname(__DIR__, 3) . '/api'); // rest_api.php uses ../includes/... and ../temp/... relative paths

include 'rest_api.php'; // exits with `exit(json_encode($value))` - that IS this process's stdout
