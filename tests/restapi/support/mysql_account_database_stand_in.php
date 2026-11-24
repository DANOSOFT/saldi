<?php
// tests/restapi/support/mysql_account_database_stand_in.php
//
// A MySQL/MariaDB account database for ModuleRightsMysqlTest, where PHP has no mysqli (as in CI). It stands in for the
// db_*() functions of includes/db_query.php, so it is loaded before restapi/core/BaseEndpoint.php; the rest of
// db_query.php, tbl_exists() included, is the real code. It answers as MySQL does:
// - db_connect() opens a connection with no database selected, and db_select_database() selects one;
// - a query on an account table (brugere, grupper, role_permissions, settings) runs on the database selected on the
//   current connection, and with none selected it fails ("No database selected") and returns false;
// - information_schema.tables lists the tables of the schema the query names, the account database's only.
// The master lookup of the account database (JWTAuth::getTenantDatabase()) is answered on any connection.
// Every query is recorded in $GLOBALS['mysqlStandInQueries'] with the database selected when it ran.
//
// History:
// 20261105 CL/LH Created.

/** The connection db_connect() opens: the database selected on it, null until db_select_database(). */
final class MysqlStandInConnection
{
    /** @var string|null */
    public $database = null;
}

/** A query result: the rows db_fetch_array() hands out one by one. */
final class MysqlStandInResult
{
    /** @var array<int, array<string, mixed>> */
    public $rows;

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }
}

/**
 * The account database the stand-in serves.
 *
 * @param string                $database    Its name (regnskab.db).
 * @param string                $rettigheder brugere.rettigheder of user 7, the token's user.
 * @param string|null           $enforce     settings permissions/enforce ('log', 'deny'), or null for no row.
 * @param array<string, string> $roleLevels  Levels of the role assigned to user 7 (role 3), or [] for no role.
 */
function mysqlStandInAccount(string $database, string $rettigheder, ?string $enforce, array $roleLevels): void
{
    $GLOBALS['mysqlStandIn'] = [
        'database' => $database,
        'tables' => ['brugere', 'grupper', 'roles', 'role_permissions', 'settings'],
        'rettigheder' => $rettigheder,
        'enforce' => $enforce,
        'roleLevels' => $roleLevels,
    ];
    $GLOBALS['mysqlStandInQueries'] = [];
}

function db_connect($l_host, $l_bruger, $l_password, $l_database = "", $l_spor = "")
{
    return $GLOBALS['connection'] = new MysqlStandInConnection();
}

function db_select_database($l_connection, $l_database)
{
    $l_connection->database = $l_database;
    return true;
}

function db_select($qtext, $spor, $global = false)
{
    $account = $GLOBALS['mysqlStandIn'];
    $connection = $GLOBALS['connection'] ?? null;
    $selected = $connection instanceof MysqlStandInConnection ? $connection->database : null;
    $GLOBALS['mysqlStandInQueries'][] = ['sql' => $qtext, 'database' => $selected];

    if (preg_match("/information_schema\.tables WHERE table_schema = '([^']*)' AND table_name = '([^']*)'/", $qtext, $m)) {
        $found = $m[1] === $account['database'] && in_array($m[2], $account['tables'], true);
        return new MysqlStandInResult($found ? [['1' => '1', 0 => '1']] : []);
    }
    if (preg_match("/^select db from regnskab where id='\d+' limit 1$/", $qtext)) {
        return new MysqlStandInResult([['db' => $account['database']]]);
    }
    if ($selected !== $account['database']) {
        return false;
    }
    if (preg_match('/^select rettigheder from brugere where id = (\d+)$/', $qtext, $m)) {
        return new MysqlStandInResult($m[1] === '7' ? [['rettigheder' => $account['rettigheder']]] : []);
    }
    if (preg_match('/^select role_id from brugere where id = (\d+)$/', $qtext, $m)) {
        return new MysqlStandInResult($m[1] === '7' ? [['role_id' => $account['roleLevels'] ? '3' : null]] : []);
    }
    if ($qtext === 'select permission_key, level from role_permissions where role_id = 3') {
        $rows = [];
        foreach ($account['roleLevels'] as $key => $level) {
            $rows[] = ['permission_key' => $key, 'level' => $level];
        }
        return new MysqlStandInResult($rows);
    }
    if ($qtext === "select var_value from settings where var_grp = 'permissions' and var_name = 'enforce'") {
        return new MysqlStandInResult($account['enforce'] === null ? [] : [['var_value' => $account['enforce']]]);
    }
    if (strpos($qtext, "from grupper where art = 'RA'") !== false) {
        $year = date('Y');
        return new MysqlStandInResult([['kodenr' => '1', 'box1' => '1', 'box2' => $year, 'box3' => '12', 'box4' => $year]]);
    }
    return new MysqlStandInResult([]);
}

function db_fetch_array($result)
{
    if (!$result instanceof MysqlStandInResult) {
        return false;
    }
    $row = array_shift($result->rows);
    return $row === null ? false : $row;
}

function db_modify($qtext, $spor, $global = false)
{
    $GLOBALS['mysqlStandInQueries'][] = ['sql' => $qtext, 'database' => null];
    return "0\tquery accepted";
}

function db_escape_string($qtext)
{
    return addslashes((string) $qtext);
}

if (!function_exists('getallheaders')) {
    /** The request's headers; the CLI has no getallheaders(). */
    function getallheaders()
    {
        return $GLOBALS['mysqlStandInHeaders'] ?? [];
    }
}

/** The JWT secret, so loading BaseEndpoint.php does not read or create restapi/.ht_jwt_secret.bin. */
function _jwtLoadSecret(?string $path = null): string
{
    return $GLOBALS['mysqlStandInJwtSecret'];
}
