<?php
// 20261111 CDX/NTR Run the production tenant installer against an already owned empty database.
// 20261111 CDX/NTR Document the production installer adapter contracts.

require_once __DIR__ . '/TestDatabase.php';
require_once __DIR__ . '/../../../TestCredentials.php';

$db = $argv[1] ?? '';
if (!preg_match('/^t[a-f0-9]{24}$/', $db)) {
    throw new RuntimeException('The installer requires a generated test database name');
}
$connection = TestDatabase::connect($db);
$db_type = 'postgresql';
$db_encode = 'UTF8';
$sqdb = TestDatabase::masterDb();
$db_id = 0;
$bruger_id = 0;
$fra_formular = null;
$regnskab = 'Test fixture';
$s_id = '';
require __DIR__ . '/../../../../includes/version.php';

// The helper owns CREATE/DROP. This adapter executes the installer's real SQL
// without loading a machine-specific connect.php or writing application logs.
/**
 * Validate the installer's request without recreating an already owned database.
 * @param string $name Database name requested by the production installer.
 * @return void
 */
function db_create($name) {
    if ($name !== $GLOBALS['db']) {
        throw new RuntimeException('Installer attempted to switch databases');
    }
}
/**
 * Execute a production installer write against the owned test connection.
 * @param string $sql Installer SQL.
 * @param string $source Optional caller label used by the production API.
 * @return string Successful legacy db_modify() status.
 */
function db_modify($sql, $source = '') {
    TestDatabase::query($GLOBALS['connection'], $sql);
    return "0\t";
}
/**
 * Execute a production installer read against the owned test connection.
 * @param string $sql Installer SQL.
 * @param string $source Optional caller label used by the production API.
 * @return PgSql\Result Successful query result.
 */
function db_select($sql, $source = '') {
    return TestDatabase::query($GLOBALS['connection'], $sql);
}
/**
 * Read the next production installer result row.
 * @param PgSql\Result $result Query result from db_select().
 * @return array<string, string|null>|false Next associative row, or false at the end.
 */
function db_fetch_array($result) {
    return pg_fetch_assoc($result);
}
/**
 * Escape an installer value with the owned PostgreSQL connection.
 * @param mixed $value Value converted to a SQL string literal's content.
 * @return string Escaped content without surrounding quotes.
 */
function db_escape_string($value) {
    return pg_escape_string($GLOBALS['connection'], (string)$value);
}
/**
 * Execute the installer's transaction command on its owned connection.
 * @param string $operation Transaction SQL such as BEGIN or COMMIT.
 * @return void
 */
function transaktion($operation) {
    TestDatabase::query($GLOBALS['connection'], $operation);
}
require_once __DIR__ . '/../../../../includes/std_func.php';
require_once __DIR__ . '/../../../../includes/opretfunc.php';
opret_build_tenant('', '', '', $db, 'chartest', TestCredentials::password('database_fixture'), true);
pg_close($connection);
