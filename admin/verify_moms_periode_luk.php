<?php
// admin/verify_moms_periode_luk.php
//
// SD-646 rollout verification step: reports the moms_periode_luk
// table/function/trigger status for every tenant database registered in the
// master regnskab table, using the same moms_periode_luk_schema_status()
// (includes/std_func.php) that includes/betweenUpdates.php's login-time
// repair and finans/moms_periode.php's UI guard both rely on.
//
// CLI-only - this tool has no session/rights check of its own, since it
// needs to inspect every tenant regardless of who is logged in where.
// Run from the repo root:
//   php admin/verify_moms_periode_luk.php
//
// Exit code is non-zero if any tenant is NOT MIGRATED or PARTIAL, so this can
// gate a deploy script.
//
// History:
// 20260815 CL/SZ SD-646: created.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This tool is CLI-only.\n");
}

// get_relative() (includes/db_query.php) derives its "../" depth from
// REQUEST_URI's slash count - unset under CLI, which would otherwise leave
// db_select()/db_modify()'s temp/ logging path resolving one level too
// shallow. Matches the same fix used by tests/characterization's CLI runners.
$_SERVER['REQUEST_URI'] = '/saldi/admin/verify_moms_periode_luk.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

chdir(__DIR__); // so the ../includes/... relative includes below resolve like every other admin/*.php page
include('../includes/connect.php');  // establishes $connection (master db), $sqhost/$squser/$sqpass
include('../includes/std_func.php'); // moms_periode_luk_schema_status()

$master_connection = $connection;

$tenants = db_select(
    "SELECT regnskab, db FROM regnskab WHERE db IS NOT NULL AND db != '' ORDER BY regnskab",
    __FILE__ . " linje " . __LINE__
);

$total = 0;
$ok = 0;
$partial = 0;
$not_migrated = 0;
$connect_failed = 0;
$errors = 0;

printf("%-30s %-20s %s\n", 'Regnskab', 'DB', 'Status');
echo str_repeat('-', 80) . "\n";

while ($row = db_fetch_array($tenants)) {
    $regnskabNavn = (string)$row['regnskab'];
    $tenantDb = (string)$row['db'];
    $total++;

    $connection = @db_connect($sqhost, $squser, $sqpass, $tenantDb);
    $db = $tenantDb;
    if (!$connection) {
        printf("%-30s %-20s %s\n", $regnskabNavn, $tenantDb, 'CONNECT FAILED');
        $connect_failed++;
        continue;
    }

    // One tenant's environment hiccup (e.g. an unwritable per-tenant temp/
    // logging directory - a known sharp edge of db_select()/db_modify(),
    // unrelated to this tool) must not abort the report for every other
    // tenant, so this loop keeps going and records it as an error instead.
    try {
        $status = moms_periode_luk_schema_status();
        if ($status['table'] && $status['function'] && $status['trigger']) {
            printf("%-30s %-20s %s\n", $regnskabNavn, $tenantDb, 'OK');
            $ok++;
        } elseif (!$status['table'] && !$status['function'] && !$status['trigger']) {
            printf("%-30s %-20s %s\n", $regnskabNavn, $tenantDb, 'NOT MIGRATED (self-heals at next login)');
            $not_migrated++;
        } else {
            $missingParts = [];
            foreach (['table', 'function', 'trigger'] as $part) {
                if (!$status[$part]) $missingParts[] = $part;
            }
            printf("%-30s %-20s %s\n", $regnskabNavn, $tenantDb, 'PARTIAL - missing: ' . implode(', ', $missingParts));
            $partial++;
        }
    } catch (\Throwable $e) {
        printf("%-30s %-20s %s\n", $regnskabNavn, $tenantDb, 'ERROR: ' . $e->getMessage());
        $errors++;
    }
    @pg_close($connection);
}

$connection = $master_connection;

echo str_repeat('-', 80) . "\n";
echo "Total: $total | OK: $ok | Partial: $partial | Not migrated: $not_migrated | Connect failed: $connect_failed | Errors: $errors\n";
if ($partial > 0 || $not_migrated > 0) {
    echo "\nPARTIAL/NOT MIGRATED tenants self-heal the next time a user logs into them\n";
    echo "(moms_periode_luk_ensure_schema() runs at login time via includes/betweenUpdates.php).\n";
    echo "If one is still PARTIAL after a login attempt, check that tenant's PHP error log\n";
    echo "for the 'SD-646:' lines moms_periode_luk_ensure_schema() writes on a failed step.\n";
}

exit(($partial > 0 || $not_migrated > 0 || $connect_failed > 0 || $errors > 0) ? 1 : 0);
