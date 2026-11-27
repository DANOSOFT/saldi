<?php
// tests/characterization/support/run_legacy_shop_schema.php
//
// Installs the Shopify schema (WP01 manifest, includes/shopify/schema.php) on the throwaway
// characterization tenant, so the legacy coexistence switches can map an afd to its slot
// through shopify_shops (SD-750). The caller passes its active owned database as argv[1].
//
// Prints one JSON line: {version}.
//
// History:
// 20261026 CL/SZ Created (SD-750).
// 20261123 CDX/NTR Use and verify the parent test class's generated tenant.

require_once __DIR__ . '/CharacterizationEnv.php';
$db = $argv[1] ?? '';
CharacterizationEnv::assertOwnedTenant($db);

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
