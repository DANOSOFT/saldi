<?php
// tests/characterization/includes/support/settings_lock_runner.php
//
// Child-process runner for SettingsCompositeRowLockTest: SettingsService::saveRaw() of a setting in grupper DIV/3, such as
// Omgående bogføring (box5 "<sales>;<purchases>", a composite) or Hurtigfakturering (box4, a setting of its own), against a
// stand-in for the database, on MySQL/MariaDB or on PostgreSQL. CI has no MySQL or MariaDB, so what those servers answer
// is played here: a locking read that fails (a lock wait timeout, 1205, after which the transaction goes on there) and
// GET_LOCK() answering 1, 0 or NULL. The definitions are the real registry (systemdata/settingsRegistry.php), which loads
// without a database (tools/check_settings_registry.php).
//
// argv: 1 = $db_type: 'mysqli' or 'postgres'
//       2 = JSON scenario:
//           rows   - the grupper DIV/3 rows there are, each {"id": n, "box5": "..."} with any boxes (none: an older ledger
//                    without the row)
//           deleted - true: the rows are there when the request reads them first (a plain read), and another session has
//                    deleted them before this save's first locking read
//           fail   - which locking reads (select ... for update), counted from 1, fail
//           locks  - what GET_LOCK() or a PostgreSQL advisory lock answers, one per call ('1', '0' or null); when the list
//                    runs out: '1'
//           caller - true: saveRaw() runs inside a transaction of the caller's that took no named lock before it began,
//                    which rolls back when lockFailed() says so, commits otherwise, and then calls releaseLocks()
//           appears - a DIV/3 row {"id": n, "box5": "..."} that another save created and committed while this one waited
//                    for the named lock: it is there from the moment the lock is given
//       3 = setting key, 4 = the stored string to write
//
// Prints one JSON object on the last line: changed (what saveRaw() returned), lockFailed, depth (the transaction depth after
// the call), log (every statement in order: "select: ...", "modify: ...", "transaktion: ..."), rows (DIV/3 after the call:
// the id and each box the row holds, by name) and warnings (PHP warnings, notices and deprecations raised in
// includes/settings/SettingsService.php).
//
// History:
// 20261120 CL/ASR Created (p1-settings-posting-F1r3).
// 20261120 CL/ASR Rows with any box, for a setting of its own in DIV/3 (box4); scenario deleted; PostgreSQL's pg_advisory_lock() and pg_advisory_unlock() (fix round 7, review round 1).

if ($argc < 5) {
    fwrite(STDERR, "usage: php settings_lock_runner.php <mysqli|postgres> <scenario json> <setting key> <stored value>\n");
    exit(2);
}

$db_type = $argv[1];
$regnaar = 1;
$GLOBALS['lockRun'] = [
    'scenario' => (array) json_decode($argv[2], true),
    'log' => [],
    'reads' => 0,
    'rows' => [],
    'warnings' => [],
];
/** A DIV/3 row as the scenario gives it: {"id": n, "box<n>": "...", ...}. */
function lockRunRow(array $row): array {
    return ['id' => (int) $row['id'], 'art' => 'DIV', 'kodenr' => '3', 'kode' => '', 'fiscal_year' => null] + array_diff_key($row, ['id' => true]);
}

foreach ((array) ($GLOBALS['lockRun']['scenario']['rows'] ?? []) as $row) {
    $GLOBALS['lockRun']['rows'][(int) $row['id']] = lockRunRow($row);
}

set_error_handler(static function (int $no, string $text, string $file, int $line): bool {
    if (str_ends_with($file, 'includes/settings/SettingsService.php')) {
        $GLOBALS['lockRun']['warnings'][] = "SettingsService.php:$line: $text";
    }
    return true;
});

final class LockRunResult {
    /** @var array<int, array<string, mixed>> */
    public $rows;

    public function __construct(array $rows) {
        $this->rows = $rows;
    }
}

function db_escape_string($text) {
    return addslashes((string) $text);
}

/** The stand-in server: grupper DIV/3 as the scenario holds it, GET_LOCK() as the scenario answers it. */
function db_select($sql, $spor = '', $global = false) {
    $run = &$GLOBALS['lockRun'];
    $run['log'][] = 'select: ' . $sql;
    $div3 = strpos($sql, 'from grupper where') !== false && strpos($sql, "art = 'DIV' and kodenr = '3'") !== false;
    if (stripos($sql, ' for update') !== false) {
        $run['reads']++;
        if (!empty($run['scenario']['deleted'])) {
            $run['rows'] = [];
            unset($run['scenario']['deleted']);
        }
        if (in_array($run['reads'], (array) ($run['scenario']['fail'] ?? []), true)) {
            return false; // db_select() returns false after its error box
        }
        return new LockRunResult($div3 ? array_values($run['rows']) : []);
    }
    if (stripos($sql, 'get_lock(') !== false || stripos($sql, 'pg_advisory_xact_lock(') !== false || stripos($sql, 'pg_advisory_lock(') !== false) {
        $answer = !empty($run['scenario']['locks']) ? array_shift($run['scenario']['locks']) : '1';
        if ($answer === '1' && !empty($run['scenario']['appears'])) {
            $row = $run['scenario']['appears'];
            $run['rows'][(int) $row['id']] = lockRunRow($row);
            unset($run['scenario']['appears']);
        }
        return new LockRunResult([['locked' => $answer, 0 => $answer]]);
    }
    if (stripos($sql, 'release_lock(') !== false || stripos($sql, 'pg_advisory_unlock(') !== false) {
        return new LockRunResult([['1', 'locked' => '1']]);
    }
    return new LockRunResult($div3 ? array_values($run['rows']) : []);
}

function db_fetch_array($result) {
    if (!$result instanceof LockRunResult || !$result->rows) {
        return false;
    }
    return array_shift($result->rows);
}

function db_modify($sql, $spor = '') {
    $run = &$GLOBALS['lockRun'];
    $run['log'][] = 'modify: ' . $sql;
    if (preg_match("/^insert into grupper \(beskrivelse, kodenr, art, (box\d+)\) values \('[^']*', '3', 'DIV', '([^']*)'\)$/", $sql, $m)) {
        $id = $run['rows'] ? max(array_keys($run['rows'])) + 1 : 1;
        $run['rows'][$id] = lockRunRow(['id' => $id, $m[1] => stripslashes($m[2])]);
    } elseif (preg_match("/^update grupper set (box\d+) = '([^']*)' where art = 'DIV' and kodenr = '3'$/", $sql, $m)) {
        foreach ($run['rows'] as $id => $row) {
            $run['rows'][$id][$m[1]] = stripslashes($m[2]);
        }
    }
    return true;
}

/** As includes/db_query.php counts the depth. */
function transaktion($statement) {
    $GLOBALS['lockRun']['log'][] = 'transaktion: ' . $statement;
    if ($statement === 'begin') {
        $GLOBALS['db_transaktion_depth'] = ($GLOBALS['db_transaktion_depth'] ?? 0) + 1;
    } elseif (!empty($GLOBALS['db_transaktion_depth'])) {
        $GLOBALS['db_transaktion_depth']--;
    }
}

function findtekst($id, $languageId = 1) {
    return (string) $id;
}

function perm_can($key, $level = 'read') {
    return true;
}

$root = dirname(__DIR__, 4);
require $root . '/includes/permissionRegistry.php';
require $root . '/includes/settings/SettingsService.php';

// The registry reads its own rows once; the log holds only what the save sends.
getSettingDefinitions();
$GLOBALS['lockRun']['log'] = [];
$caller = !empty($GLOBALS['lockRun']['scenario']['caller']);
if ($caller) {
    transaktion('begin');
}
$changed = SettingsService::saveRaw($argv[3], $argv[4]);
// Before p1-settings-posting-F1r3 the service had neither: no failed read was reported, and there was nothing to release.
$lockFailed = method_exists('SettingsService', 'lockFailed') ? SettingsService::lockFailed() : false;
if ($caller) {
    transaktion($lockFailed ? 'rollback' : 'commit');
    if (method_exists('SettingsService', 'releaseLocks')) {
        SettingsService::releaseLocks();
    }
}

$run = $GLOBALS['lockRun'];
// The change history's column check (information_schema) is left out: it finds no audit_log here.
$log = array_values(array_filter($run['log'], static fn(string $entry): bool => strpos($entry, 'information_schema') === false));
echo "\n" . json_encode([
    'changed' => $changed,
    'lockFailed' => $lockFailed,
    'depth' => (int) ($GLOBALS['db_transaktion_depth'] ?? 0),
    'log' => $log,
    'rows' => array_values(array_map(static function (array $r): array {
        $boxes = array_filter($r, static fn($v, $k): bool => strpos((string) $k, 'box') === 0, ARRAY_FILTER_USE_BOTH);
        ksort($boxes);
        return ['id' => $r['id']] + $boxes;
    }, $run['rows'])),
    'warnings' => $run['warnings'],
]) . "\n";
