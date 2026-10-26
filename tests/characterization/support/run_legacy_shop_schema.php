<?php
// tests/characterization/support/run_legacy_shop_schema.php
//
// Installs the Shopify schema (WP01 manifest, includes/shopify/schema.php) on the throwaway
// characterization tenant, so the legacy coexistence switches can map an afd to its slot
// through shopify_shops (SD-750). Refuses any database but CharacterizationEnv::testDb().
//
// Prints one JSON line: {version}.
//
// History:
// 20261026 CL/SZ Created (SD-750).

require_once __DIR__ . '/CharacterizationEnv.php';
CharacterizationEnv::assertSafeDatabases();

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
$connection = db_connect(CharacterizationEnv::pgHost(), CharacterizationEnv::pgUser(), CharacterizationEnv::pgPass(), CharacterizationEnv::testDb());
require_once CharacterizationEnv::repoRoot() . '/includes/shopify/schema.php';

ensure_shopify_schema();
echo json_encode(['version' => shopify_schema_version_installed()]) . "\n";
