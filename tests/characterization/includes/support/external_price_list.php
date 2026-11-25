<?php
// 20261125 CDX/NTR Isolate the real external-price-list logic with transport and database-boundary doubles.

namespace ExternalPriceListFixture;

final class State
{
    public static \PDO $db;
    public static string $csv;
    public static array $queries;
    public static bool $allowLock;
    public static bool $locked;
    public static bool $failInsert;
    public static bool $insertWasLocked;
    public static $onLock;
}

/** @return void Reset the synthetic catalogue and load the production functions once. */
function boot(): void
{
    State::$db = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    State::$db->sqliteCreateFunction('current_database', static fn(): string => 'synthetic_external_price_list');
    State::$db->exec('CREATE TABLE varer (id INTEGER PRIMARY KEY, varenr TEXT, stregkode TEXT, trademark TEXT, beskrivelse TEXT, kostpris TEXT, salgspris TEXT, enhed TEXT, gruppe INTEGER, lukket TEXT)');
    State::$db->exec("CREATE TABLE grupper (art TEXT, fiscal_year INTEGER, kodenr TEXT); INSERT INTO grupper VALUES ('VG', 2026, '10')");
    State::$csv = '';
    State::$queries = [];
    State::$allowLock = true;
    State::$locked = false;
    State::$failInsert = false;
    State::$insertWasLocked = false;
    State::$onLock = null;
    $GLOBALS['regnaar'] = 2026;
    $GLOBALS['db_type'] = 'postgres';
    $GLOBALS['db'] = 'synthetic_external_price_list';
    if (!function_exists(__NAMESPACE__ . '\externalPriceListEach')) {
        $path = dirname(__DIR__, 4) . '/includes/orderFuncIncludes/externalPriceList.php';
        $source = substr(file_get_contents($path), 5);
        $source = str_replace('function externalPriceListDownload(', 'function unusedExternalPriceListDownload(', $source);
        $source = str_replace('__DIR__', var_export(dirname($path), true), $source);
        eval('namespace ' . __NAMESPACE__ . ';' . $source);
    }
}

/** @return string Synthetic transport response; parsing stays in production code. */
function externalPriceListDownload(string $url, int $maxBytes): string
{
    return State::$csv;
}

/** @return string SQL text escaped by the actual fixture engine. */
function db_escape_string($value): string
{
    return substr(State::$db->quote((string) $value), 1, -1);
}

/** @return \PDOStatement Executed production SQL. */
function db_select($sql, $source): \PDOStatement
{
    State::$queries[] = $sql;
    return State::$db->query($sql);
}

/** @return array|false Next database row. */
function db_fetch_array($result)
{
    return $result->fetch(\PDO::FETCH_ASSOC);
}

/** @return void Execute the production insert with an observable lock state. */
function db_modify($sql, $source): void
{
    State::$insertWasLocked = State::$locked;
    if (State::$failInsert) {
        throw new \RuntimeException('Synthetic insert failure');
    }
    State::$db->exec($sql);
}

/** @return bool Simulate lock acquisition, including a competing completed insertion. */
function db_lock($name): bool
{
    if (!State::$allowLock) {
        return false;
    }
    State::$locked = true;
    if (State::$onLock !== null) {
        (State::$onLock)();
    }
    return true;
}

/** @return void Observe release on every return and exception path. */
function db_unlock($name): void
{
    State::$locked = false;
}
