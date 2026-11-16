<?php
// tests/characterization/support/run_legacy_shop_schema.php
//
// Installs the Shopify schema (WP01 manifest, includes/shopify/schema.php) on the throwaway
// characterization tenant, so the legacy coexistence switches can map an afd to its slot
// through shopify_shops (SD-750).
//
// argv: 1 = the tenant database (CharacterizationEnv::testDb() in the test)
// Prints one JSON line: {version}.
//
// History:
// 20261026 CL/SZ Created (SD-750).
// 20261116 CL/SS Sync of master into hml/shopify: the tenant database comes as argv 1, as in master's runners (#1089 removed assertSafeDatabases()).

require_once __DIR__ . '/CharacterizationEnv.php';
$db = (string)($argv[1] ?? '');
if (!preg_match('/^[a-z0-9_]+$/', $db)) {
	fwrite(STDERR, "usage: php run_legacy_shop_schema.php <tenant_db>\n");
	exit(2);
}

function alert($msg)
{
	fwrite(STDERR, "alert: $msg\n");
	exit(3);
}

$db_type = 'postgresql';
$db_encode = 'UTF8';
$brugernavn = 'chartest';
$sqdb = '';
$db_skriv_id = 0;
$webservice = false;

require_once CharacterizationEnv::repoRoot() . '/includes/db_query.php';
$connection = db_connect(CharacterizationEnv::pgHost(), CharacterizationEnv::pgUser(), CharacterizationEnv::pgPass(), $db);
require_once CharacterizationEnv::repoRoot() . '/includes/shopify/schema.php';

ensure_shopify_schema();
echo json_encode(['version' => shopify_schema_version_installed()]) . "\n";
