<?php
// 20261125 CDX/NTR Run the real external-item creation and database locks on an isolated concurrent connection.

require_once __DIR__ . '/../../support/database/TestDatabase.php';
require_once dirname(__DIR__, 4) . '/includes/orderFuncIncludes/externalPriceList.php';

$connection = TestDatabase::connect($argv[1]);
pg_query($connection, 'SET search_path TO ' . pg_escape_identifier($connection, $argv[2]));
pg_query($connection, "SET statement_timeout = '5s'");
$db_type = 'postgres';
$regnaar = 2026;
$stage = $argv[3];
$deadline = microtime(true) + 5;
while (!file_exists($stage . '.go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrent worker start timed out');
    }
    usleep(1000);
}
echo externalPriceListCreateItem(
    ['varenr' => 'A100', 'stregkode' => '', 'trademark' => '', 'beskrivelse' => 'Pump', 'kostpris' => '1250.500', 'salgspris' => '0.005', 'enhed' => 'stk'],
    ['group' => 10, 'groupName' => '']
);
pg_close($connection);

/** @return string Value escaped by the real PostgreSQL connection. */
function db_escape_string($value): string
{
    global $connection;
    return pg_escape_string($connection, (string) $value);
}

/** @return PgSql\Result Production SQL, with a barrier widening the empty-item race. */
function db_select($sql, $source): PgSql\Result
{
    global $connection, $stage;
    $result = pg_query($connection, $sql);
    if (str_starts_with($sql, 'select id from varer where varenr') && pg_num_rows($result) === 0) {
        touch($stage . '.read-' . getmypid());
        $deadline = microtime(true) + 0.5;
        // Without serialization both requests observe an absent item before either inserts it.
        // With the production lock the first proceeds after the bounded wait, then the second reuses its row.
        while (count(glob($stage . '.read-*')) < 2 && microtime(true) < $deadline) {
            usleep(1000);
        }
    }
    return $result;
}

/** @return array|false Next production query row. */
function db_fetch_array($result)
{
    return pg_fetch_assoc($result);
}

/** @return void Execute the real item insert. */
function db_modify($sql, $source): void
{
    global $connection;
    if (pg_query($connection, $sql) === false) {
        throw new RuntimeException('Synthetic item insert failed');
    }
}
