<?php
// tests/characterization/includes/support/named_lock_runner.php
//
// Child-process runner for NamedLockWaitTest: takes the opening-balance carry's lock (includes/updateFollowingOpeningBalances.php)
// or the journal save lock (finans/kassekladde_includes/journalSaveLock.php) on MySQL or MariaDB, against a stand-in for the
// database that answers GET_LOCK() as those servers do. CI has no MySQL or MariaDB, and the PHP there has no mysqli, so the
// mysqli functions the carry's lock calls are defined here. The callers end the request (exit) when they give up, so they
// run in a process of their own.
//
// argv: 1 = engine, 'mysql' or 'mariadb': GET_LOCK() with a negative timeout waits until the lock is free on MySQL (here: 1),
//           and answers NULL at once without a lock on MariaDB (warning 1411, MDEV-4017).
//       2 = what: 'carry-before' (openingCarryLockBeforeTransaction(true), as the POS day close and a journal call it),
//           'carry-before-exit' (openingCarryLockBeforeTransaction(), as the year card and Opret call it), 'carry-in-transaction'
//           (openingCarryLock()), 'journal-save' (kkJournalLock(7)) or 'carry-before-reconnect': the lock taken before the
//           transaction on one connection, then a new connection in its place (what genberegn() does when it connects to the
//           master database and back), then openingCarryUnlock(), as finans/bogfor.php and the year card hold the lock through
//           their recalculations after the commit.
//       3 = JSON list of what GET_LOCK() with a timeout of 0 or more answers, one per call: '1' (this session has the lock),
//           '0' (another session still held it when the timeout ran out) or null (an error). When the list runs out: '1'.
//
// Prints one JSON object on the last line, also when the caller ends the request: the statements sent, whether the lock is
// held, the exception thrown, the alerts, the transaction statements and whether the request was ended. Its events are the
// statements sent through mysqli_query() with the connection each went to, and the connections closed meanwhile.
//
// History:
// 20261119 CL/ASR Created (p1-yearend-F3, MB-81).
// 20261120 CL/ASR carry-before-reconnect, and the events: which connection a statement went to and when a connection was closed (p1-pos-close-N1).

if ($argc < 4) {
    fwrite(STDERR, "usage: php named_lock_runner.php <mysql|mariadb> <carry-before|carry-before-exit|carry-in-transaction|journal-save> <answers json>\n");
    exit(2);
}
if (extension_loaded('mysqli')) {
    echo json_encode(['skip' => 'the mysqli extension is loaded, so its functions cannot be stood in for here']) . "\n";
    exit(0);
}

$GLOBALS['lockRun'] = [
    'engine' => $argv[1],
    'answers' => (array) json_decode($argv[3], true),
    'queries' => [],
    'alerts' => [],
    'transaktion' => [],
    'exception' => null,
    'message' => null,
    'exited' => true, // cleared when the caller returns instead of ending the request
    'events' => [],
];
$db_type = 'mysqli';
$connection = 'stand-in';
$sprog_id = 1;

/** What the stand-in server answers to $sql: GET_LOCK() as the engine does, RELEASE_LOCK() 1. */
function lock_run_answer($sql) {
    $GLOBALS['lockRun']['queries'][] = $sql;
    if (preg_match('/get_lock\(.*,\s*(-?\d+)\)/i', $sql, $m)) {
        if ((int) $m[1] < 0) {
            return $GLOBALS['lockRun']['engine'] === 'mariadb' ? null : '1';
        }
        return $GLOBALS['lockRun']['answers'] ? array_shift($GLOBALS['lockRun']['answers']) : '1';
    }
    if (stripos($sql, 'release_lock(') !== false) {
        return '1';
    }
    return null;
}

/** A connection of carry-before-reconnect: a mysqli connection is closed when the last reference to it goes. */
final class LockRunConnection {
    /** @var string */
    public $name;

    public function __construct($name) {
        $this->name = $name;
    }

    public function __destruct() {
        $GLOBALS['lockRun']['events'][] = 'closed ' . $this->name;
    }
}

final class mysqli_result {
    /** @var array<int, mixed> */
    public $row;

    public function __construct(array $row) {
        $this->row = $row;
    }
}

function mysqli_query($connection, $sql) {
    $GLOBALS['lockRun']['events'][] = ($connection instanceof LockRunConnection ? $connection->name : (string) $connection) . ': ' . $sql;
    return new mysqli_result([lock_run_answer($sql)]);
}

function mysqli_fetch_row($result) {
    return $result->row;
}

function mysqli_free_result($result) {
}

function mysqli_error($connection) {
    return '';
}

function mysqli_errno($connection) {
    return 0;
}

// db_select() and db_fetch_array() of includes/db_query.php, as kkJournalLock() reads GET_LOCK() through them.
function db_select($sql, $spor = '', $global = false) {
    $value = lock_run_answer($sql);
    return ['locked' => $value, 0 => $value];
}

function db_fetch_array($result) {
    return is_array($result) ? $result : false;
}

function transaktion($statement) {
    $GLOBALS['lockRun']['transaktion'][] = $statement;
}

function alert($text) {
    $GLOBALS['lockRun']['alerts'][] = (string) $text;
}

function findtekst($textId, $languageId) {
    return substr((string) $textId, strpos((string) $textId, '|') + 1);
}

ob_start();
register_shutdown_function(static function () {
    $page = ob_get_clean();
    preg_match_all('/<script>alert\((".*?")\);<\/script>/', (string) $page, $m);
    foreach ($m[1] as $json) {
        $GLOBALS['lockRun']['alerts'][] = (string) json_decode($json);
    }
    $run = $GLOBALS['lockRun'];
    $held = function_exists('openingCarryLockState') ? openingCarryLockState()['held'] : false;
    if ($run['what'] === 'journal-save') {
        $held = !empty($GLOBALS['kkJournalLocked']);
    }
    unset($run['answers'], $run['engine']);
    echo "\n" . json_encode($run + ['held' => $held]) . "\n";
});

$root = dirname(__DIR__, 4);
$what = $argv[2];
$GLOBALS['lockRun']['what'] = $what;
try {
    if ($what === 'journal-save') {
        require $root . '/finans/kassekladde_includes/journalSaveLock.php';
        kkJournalLock(7);
    } else {
        require $root . '/includes/updateFollowingOpeningBalances.php';
        if ($what === 'carry-before') {
            openingCarryLockBeforeTransaction(true);
        } elseif ($what === 'carry-before-exit') {
            openingCarryLockBeforeTransaction();
        } elseif ($what === 'carry-in-transaction') {
            openingCarryLock();
        } elseif ($what === 'carry-before-reconnect') {
            $connection = new LockRunConnection('first');
            openingCarryLockBeforeTransaction(true);
            // genberegn() with the posting count includes connect.php and online.php, whose db_connect() opens a new
            // connection to the company database on MySQL and MariaDB and puts it in $connection.
            $connection = new LockRunConnection('second');
            openingCarryUnlock();
        } else {
            fwrite(STDERR, "unknown what $what\n");
            exit(2);
        }
    }
} catch (Throwable $e) {
    $GLOBALS['lockRun']['exception'] = get_class($e);
    $GLOBALS['lockRun']['message'] = $e->getMessage();
}
$GLOBALS['lockRun']['exited'] = false;
