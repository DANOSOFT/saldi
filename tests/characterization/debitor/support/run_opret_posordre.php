<?php

// tests/characterization/debitor/support/run_opret_posordre.php
//
// Child-process runner for opret_posordre() in debitor/pos_ordre_includes/saleHeaderFunctions.php
// (SD-772).
//
// PosSaleHeaderTest (SD-758) could not call this function - it writes a lock file under
// ../temp, calls hent_shop_ordrer(), prints redirects and exit()s - so it reads the INSERT
// out of the shipped source as text instead. That pin breaks on a reformat and stays green
// on a behaviour change, which is the wrong way round for a test meant to protect S12.
//
// A child process removes all three obstacles: the lock file gets a real temp directory, the
// shop fetch and the order-number allocator are stood in for, and an exit() is just the end
// of the process. The function then runs unchanged and the row it writes can be read back
// instead of being matched against a string.
//
// argv: 1 = path to a JSON spec:
//   kasse, konto_id   the call's two arguments
//   vars              the globals pos_ordre.php has set: db, regnaar, brugernavn, bruger_id,
//                     baseCurrency, firmanavn, kontonr, notes, bordnr, varenr_ny
//   get, post         $_GET and $_POST (bordnr and flyt_til steer the function)
//   grupper, ordrer, formularer   seed rows
//   lock              seconds to put in ../temp/<db>/<kasse>.tid before the call, relative to
//                     now; omitted means no lock file at all
//   order_number      what the order-number allocator returns
//
// Prints one JSON line: the return value, every table afterwards, the SQL in order, the
// stood-in calls in order, the warnings raised, anything printed, and the lock file's state:
// lock is its timestamp less the second the call started, elapsed the seconds the call took.
//
// History:
// 20261026 CL/MJ SD-772: created (M-PREP for S12).
// 20261027 CL/LH SD-772 review: the lock is reported against the second the call started, with
//                the call's own duration beside it, instead of against the clock at shutdown.

$spec = json_decode((string)file_get_contents($argv[1] ?? ''), true);
if (!is_array($spec)) {
    fwrite(STDERR, "usage: php run_opret_posordre.php <spec.json>\n");
    exit(2);
}

const OPRET_POSORDRE_TABLES = ['ordrer', 'grupper', 'pos_events', 'formularer'];

$GLOBALS['opretSpec'] = $spec;
$GLOBALS['opretSql'] = [];
$GLOBALS['opretCalls'] = [];
$GLOBALS['opretWarnings'] = [];
$GLOBALS['opretResult'] = null;
$GLOBALS['opretReached'] = 'start';
$GLOBALS['opretCalledAt'] = null;

// ------------------------------------------------------------ the page's working directory
//
// opret_posordre() reaches the lock file as "../temp/$db/$kasse.tid", relative to the page
// it is included into, so the process has to stand where debitor/pos_ordre.php stands.

$db = $spec['vars']['db'] ?? 'sd772test';
$work = sys_get_temp_dir() . '/sd772-opret-' . bin2hex(random_bytes(6));
mkdir($work . '/page', 0777, true);
mkdir($work . '/temp/' . $db, 0777, true);
$lockFile = $work . '/temp/' . $db . '/' . ($spec['kasse'] ?? 1) . '.tid';
if (array_key_exists('lock', $spec)) {
    // lock_kasse puts the lock on a different till than the one being rung up, which is how
    // a test shows the lock is per terminal rather than per installation.
    $lockOwner = $work . '/temp/' . $db . '/' . ($spec['lock_kasse'] ?? $spec['kasse'] ?? 1) . '.tid';
    file_put_contents($lockOwner, (string)(time() + (int)$spec['lock']));
}
chdir($work . '/page');

// ---------------------------------------------------------------------------- the database

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$GLOBALS['opretDb'] = $pdo;

$pdo->exec("CREATE TABLE ordrer (id INTEGER PRIMARY KEY AUTOINCREMENT, ordrenr TEXT,
    konto_id INTEGER, kontonr TEXT, firmanavn TEXT, addr1 TEXT, addr2 TEXT, postnr TEXT,
    bynavn TEXT, land TEXT, betalingsdage INTEGER, betalingsbet TEXT, cvrnr TEXT, ean TEXT,
    institution TEXT, email TEXT, mail_fakt TEXT, notes TEXT, art TEXT, ordredate TEXT,
    momssats TEXT, hvem TEXT, tidspkt TEXT, ref TEXT, valuta TEXT, sprog TEXT, kontakt TEXT,
    pbs TEXT, status TEXT, nr TEXT, felt_5 TEXT, afd INTEGER)");
$pdo->exec("CREATE TABLE grupper (id INTEGER PRIMARY KEY AUTOINCREMENT, art TEXT, kodenr TEXT,
    fiscal_year TEXT, box1 TEXT, box2 TEXT, box3 TEXT, box4 TEXT, box5 TEXT, box6 TEXT,
    box7 TEXT, box8 TEXT, box9 TEXT, box10 TEXT, box13 TEXT)");
$pdo->exec("CREATE TABLE pos_events (id INTEGER PRIMARY KEY AUTOINCREMENT, ev_type TEXT,
    ev_time TEXT, cash_register_id TEXT, employee_id TEXT, order_id TEXT, file TEXT, line TEXT)");
$pdo->exec("CREATE TABLE formularer (id INTEGER PRIMARY KEY AUTOINCREMENT, formular INTEGER,
    art TEXT, beskrivelse TEXT, sprog TEXT)");

function opret_posordre_seed(PDO $pdo, string $table, array $rows): void
{
    foreach ($rows as $row) {
        $values = array_map(static function ($value) {
            return $value === null ? 'NULL' : "'" . str_replace("'", "''", (string)$value) . "'";
        }, array_values($row));
        $pdo->exec("INSERT INTO $table (" . implode(',', array_keys($row)) . ') VALUES ('
            . implode(',', $values) . ')');
    }
}

opret_posordre_seed($pdo, 'grupper', $spec['grupper'] ?? []);
opret_posordre_seed($pdo, 'ordrer', $spec['ordrer'] ?? []);
opret_posordre_seed($pdo, 'formularer', $spec['formularer'] ?? []);

// ------------------------------------------------ the functions opret_posordre calls

function db_select($qtxt, $where = '')
{
    $GLOBALS['opretSql'][] = $qtxt;
    return $GLOBALS['opretDb']->query($qtxt);
}

function db_fetch_array($query)
{
    if (!$query instanceof PDOStatement) {
        return false;
    }
    return $query->fetch(PDO::FETCH_ASSOC);
}

function db_modify($qtxt, $where = '')
{
    $GLOBALS['opretSql'][] = $qtxt;
    $GLOBALS['opretDb']->exec($qtxt);
    return "0\tquery accepted";
}

function db_escape_string($text)
{
    return str_replace("'", "''", (string)$text);
}

/** The Shopify order intake. Whether and when it is called is the behaviour; what it does is not. */
function hent_shop_ordrer($shop_ordre_id, $from_date)
{
    $GLOBALS['opretCalls'][] = ['hent_shop_ordrer', (string)$shop_ordre_id, (string)$from_date];
}

function get_next_order_number($art = 'DO', $manageTransaction = true)
{
    $GLOBALS['opretCalls'][] = ['get_next_order_number', (string)$art];
    return $GLOBALS['opretSpec']['order_number'] ?? 7001;
}

/** Only reached when the caller passes no cash register; pos_ordre.php resolves it from the session. */
function find_kasse($kasse)
{
    $GLOBALS['opretCalls'][] = ['find_kasse', (string)$kasse];
    return $GLOBALS['opretSpec']['found_kasse'] ?? 0;
}

/** The navigation stack's query string; it only ends up inside the redirects. */
function nav_popup_query(array $get, array $post): string
{
    return '';
}

function findtekst($text, $language)
{
    return explode('|', (string)$text, 2)[1] ?? (string)$text;
}

/** includes/std_func.php prints a javascript alert; here it is recorded so a test can read it. */
function alert($message)
{
    $GLOBALS['opretCalls'][] = ['alert', (string)$message];
}

// The real emptiness rules: pos_terminal_sale_defaults() leans on ifset()'s treatment of a
// missing index, and if_isset() evaluates its argument before the call, which is why an
// unset $_GET['bordnr'] warns. Both are part of what is pinned.
require_once __DIR__ . '/pos_lift.php';
pos_lift_into([
    dirname(__DIR__, 4) . '/includes/std_func.php' => ['ifset', 'ifset_resolve_default', 'if_isset'],
]);

// --------------------------------------------------------------------------- reporting

set_error_handler(static function (int $no, string $message, string $file = '', int $line = 0) {
    $GLOBALS['opretWarnings'][] = $message . ' in ' . basename($file) . ':' . $line;
    return true;
});

ob_start();
register_shutdown_function(static function () use ($lockFile, $work) {
    $printed = ob_get_clean();
    $pdo = $GLOBALS['opretDb'];
    $tables = [];
    foreach (OPRET_POSORDRE_TABLES as $table) {
        $tables[$table] = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode([
        'result' => $GLOBALS['opretResult'],
        'reached' => $GLOBALS['opretReached'],
        'tables' => $tables,
        'sql' => $GLOBALS['opretSql'],
        'calls' => $GLOBALS['opretCalls'],
        'warnings' => $GLOBALS['opretWarnings'],
        'printed' => $printed,
        'lock' => file_exists($lockFile)
            ? (int)file_get_contents($lockFile) - ($GLOBALS['opretCalledAt'] ?? time()) : null,
        'elapsed' => $GLOBALS['opretCalledAt'] === null ? null : time() - $GLOBALS['opretCalledAt'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n";

    // The fixture is this process's own temp directory; nothing outside $work is touched.
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($work);
});

// ------------------------------------------------- opret_posordre() in pos_ordre.php's scope

$vars = ($spec['vars'] ?? []) + [
    'db' => $db,
    'regnaar' => 2026,
    'brugernavn' => 'sd772',
    'bruger_id' => 7,
    'baseCurrency' => 'DKK',
    'firmanavn' => '',
    'kontonr' => 0,
    'notes' => '',
    'bordnr' => '',
    'varenr_ny' => '',
    'momssats' => 0,
];
foreach ($vars as $name => $value) {
    $GLOBALS[$name] = $value;
}
$_GET = $spec['get'] ?? [];
$_POST = $spec['post'] ?? [];

require dirname(__DIR__, 4) . '/debitor/pos_ordre_includes/saleHeaderFunctions.php';

$GLOBALS['opretReached'] = 'calling';
$GLOBALS['opretCalledAt'] = time();
$GLOBALS['opretResult'] = opret_posordre($spec['konto_id'] ?? 0, $spec['kasse'] ?? 1);
$GLOBALS['opretReached'] = 'returned';
