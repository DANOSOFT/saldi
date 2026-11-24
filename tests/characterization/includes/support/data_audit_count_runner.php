<?php
// tests/characterization/includes/support/data_audit_count_runner.php
//
// Child process for DataAuditTransactionTest: one write through the activity log's hooks (includes/dataAudit.php) in a
// transaction, then the end of the request. A counted write is written to the activity log by a shutdown function, so the
// test reads the entries this process prints, one JSON array per line, after it has ended.
//
// argv: 1 = the SQL fixture database (TestDatabase::sqlFixture()) to connect to; the connection settings come from the
//           same SALDI_CHAR_* environment as the test's. 2 = the INSERT to run, on a TEMP adresser table.
//
// History:
// 20261124 CL/ASR Created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php data_audit_count_runner.php <database> <insert>\n");
    exit(2);
}
require __DIR__ . '/../../support/database/TestDatabase.php';
$conn = TestDatabase::connect($argv[1]);
$GLOBALS['db_type'] = 'postgresql';

function audit_ready(): bool
{
    return true;
}

function audit_log(string $handling, string $detaljer = '', string $objektType = '', string $objektId = '', string $kilde = 'ui', bool $system = false): void
{
    echo json_encode([$handling, $detaljer, $objektType, $objektId], JSON_UNESCAPED_UNICODE), "\n";
}

require __DIR__ . '/../../../../includes/dataAudit.php';

foreach (["SET search_path TO pg_temp", "CREATE TEMP TABLE adresser (id serial PRIMARY KEY, kontonr text, firmanavn text, art text)", "BEGIN"] as $sql) {
    if (@pg_query($conn, $sql) === false) {
        fwrite(STDERR, pg_last_error($conn) . "\n");
        exit(1);
    }
}
$ctx = data_audit_before($argv[2], false, $conn);
$res = @pg_query($conn, $argv[2]);
if ($res === false) {
    fwrite(STDERR, pg_last_error($conn) . "\n");
    exit(1);
}
if ($ctx) {
    data_audit_after($ctx, $res);
}
pg_query($conn, "COMMIT");
echo json_encode(['rows', (int) pg_fetch_result(pg_query($conn, "SELECT count(*) FROM adresser"), 0, 0)]), "\n";
