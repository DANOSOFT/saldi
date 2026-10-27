<?php

// tests/characterization/debitor/support/run_vis_pos_linjer.php
//
// Child-process runner for vis_pos_linjer() in
// debitor/pos_ordre_includes/showPosLines/showPosLinesFunc.php.
//
// The question this answers: SD-758 gives a POS sale the creating terminal's department and
// brand. vis_pos_linjer() redraws an open sale on every page load and ends with an
// "update ordrer set ... afd='$afd',felt_5='$kasse'" (line 208), where both globals belong to
// the terminal doing the drawing, not the one that created the sale. Whether that actually
// moves a sale between departments had only been read in the code, never run.
//
// The function cannot be called in-process: it include()s three siblings, writes
// ../temp/<db>/customerDisplay.txt relative to the page, and prints a screenful of HTML. A
// child process with a real temp directory and in-memory SQLite lets it run unchanged.
//
// argv: 1 = path to a JSON spec:
//   args              id, momssats, pris_ny, show - the call's arguments. $status is ignored
//                     by the function, which rebinds it to the global and then reads the
//                     order row, so the spec sets the status on the order instead.
//   vars              the globals pos_ordre.php has set for the terminal doing the drawing,
//                     above all afd and kasse
//   get, post         $_GET and $_POST
//   ordrer, ordrelinjer, varer, grupper, settings, pos_betalinger, valuta   seed rows
//
// Prints one JSON line: the return value, every table afterwards, the SQL in order, the
// stood-in calls, the warnings raised and anything printed.
//
// History:
// 20261026 CL/MJ SD-772 follow-up: created to settle the department question on SD-758.

$spec = json_decode((string)file_get_contents($argv[1] ?? ''), true);
if (!is_array($spec)) {
    fwrite(STDERR, "usage: php run_vis_pos_linjer.php <spec.json>\n");
    exit(2);
}

const VIS_POS_LINJER_TABLES = ['ordrer', 'ordrelinjer', 'pos_betalinger'];

$GLOBALS['visSpec'] = $spec;
$GLOBALS['visSql'] = [];
$GLOBALS['visCalls'] = [];
$GLOBALS['visWarnings'] = [];
$GLOBALS['visResult'] = null;
$GLOBALS['visReached'] = 'start';

// ------------------------------------------------------------ the page's working directory
//
// productLines.php writes ../temp/$db/customerDisplay.txt, so the process has to stand where
// debitor/pos_ordre.php stands.

$db = $spec['vars']['db'] ?? 'sd772test';
$work = sys_get_temp_dir() . '/sd772-vis-' . bin2hex(random_bytes(6));
mkdir($work . '/page', 0777, true);
mkdir($work . '/temp/' . $db, 0777, true);
chdir($work . '/page');

// ---------------------------------------------------------------------------- the database

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$GLOBALS['visDb'] = $pdo;

$pdo->exec("CREATE TABLE ordrer (id INTEGER PRIMARY KEY AUTOINCREMENT, ordrenr TEXT,
    konto_id INTEGER, kontonr TEXT, firmanavn TEXT, notes TEXT, art TEXT, ordredate TEXT,
    momssats TEXT, hvem TEXT, tidspkt TEXT, ref TEXT, valuta TEXT, sprog TEXT, kontakt TEXT,
    pbs TEXT, status TEXT, nr TEXT, felt_5 TEXT, afd INTEGER, sum TEXT, moms TEXT)");
$pdo->exec("CREATE TABLE ordrelinjer (id INTEGER PRIMARY KEY AUTOINCREMENT, ordre_id INTEGER,
    vare_id INTEGER, posnr INTEGER, varenr TEXT, beskrivelse TEXT, pris TEXT, kostpris TEXT,
    antal TEXT, lager TEXT, folgevare TEXT, tilfravalg TEXT, rabatgruppe TEXT, rabat TEXT,
    rabatart TEXT, m_rabat TEXT, discounttxt TEXT, momsfri TEXT, momssats TEXT, saet TEXT,
    samlevare TEXT, leveret TEXT, vat_price TEXT, lev_varenr TEXT)");
$pdo->exec("CREATE TABLE varer (id INTEGER PRIMARY KEY AUTOINCREMENT, varenr TEXT,
    beskrivelse TEXT, salgspris TEXT, gruppe TEXT, beholdning TEXT, min_lager TEXT)");
$pdo->exec("CREATE TABLE grupper (id INTEGER PRIMARY KEY AUTOINCREMENT, art TEXT, kodenr TEXT,
    fiscal_year TEXT, beskrivelse TEXT, box1 TEXT, box2 TEXT, box3 TEXT, box4 TEXT, box5 TEXT,
    box6 TEXT, box7 TEXT, box8 TEXT, box9 TEXT, box10 TEXT, box13 TEXT, box14 TEXT)");
$pdo->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY AUTOINCREMENT, var_name TEXT,
    var_grp TEXT, var_value TEXT, pos_id TEXT, user_id TEXT)");
$pdo->exec("CREATE TABLE pos_betalinger (id INTEGER PRIMARY KEY AUTOINCREMENT, ordre_id INTEGER,
    amount TEXT, betalingstype TEXT, valuta TEXT, valutakurs TEXT)");
$pdo->exec("CREATE TABLE valuta (id INTEGER PRIMARY KEY AUTOINCREMENT, valdate TEXT,
    gruppe TEXT, kurs TEXT)");

function vis_pos_linjer_seed(PDO $pdo, string $table, array $rows): void
{
    foreach ($rows as $row) {
        $values = array_map(static function ($value) {
            return $value === null ? 'NULL' : "'" . str_replace("'", "''", (string)$value) . "'";
        }, array_values($row));
        $pdo->exec("INSERT INTO $table (" . implode(',', array_keys($row)) . ') VALUES ('
            . implode(',', $values) . ')');
    }
}

foreach (['grupper', 'varer', 'settings', 'ordrer', 'ordrelinjer', 'pos_betalinger', 'valuta'] as $table) {
    vis_pos_linjer_seed($pdo, $table, $spec[$table] ?? []);
}

// -------------------------------------------------------- the functions vis_pos_linjer calls

function db_select($qtxt, $where = '')
{
    $GLOBALS['visSql'][] = $qtxt;
    return $GLOBALS['visDb']->query($qtxt);
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
    $GLOBALS['visSql'][] = $qtxt;
    $GLOBALS['visDb']->exec($qtxt);
    return "0\tquery accepted";
}

function db_escape_string($text)
{
    return str_replace("'", "''", (string)$text);
}

function findtekst($text, $language = null)
{
    return explode('|', (string)$text, 2)[1] ?? (string)$text;
}

/** Presentation only: the stylesheet lookup in pos_ordre.php. */
function find_stil($type, $nr, $fs)
{
    return '';
}

/** Prints the rounding line; recorded so a test can see it was reached. */
function setRoundUpText($status, $difkto, $rest, $betvaluta, $afrundet)
{
    $GLOBALS['visCalls'][] = ['setRoundUpText', (string)$status, (string)$afrundet];
}

function kundedisplay($beskrivelse, $pris, $ryd, $antal = null)
{
    $GLOBALS['visCalls'][] = ['kundedisplay', (string)$beskrivelse, (string)$pris];
}

/** Rebuilds set lines; only reached on the total-price path, which these specs do not take. */
function gendan_saet($id)
{
    $GLOBALS['visCalls'][] = ['gendan_saet', (string)$id];
}

function opret_ordrelinje(...$args)
{
    $GLOBALS['visCalls'][] = ['opret_ordrelinje', (string)($args[0] ?? '')];
    return 0;
}

function fejl($id, $fejltekst)
{
    $GLOBALS['visCalls'][] = ['fejl', (string)$id, (string)$fejltekst];
}

function alert($message)
{
    $GLOBALS['visCalls'][] = ['alert', (string)$message];
}

/** sum.php formats the total per country; the company's own country is a boundary here. */
function getCountry()
{
    return $GLOBALS['visSpec']['country'] ?? 'Denmark';
}

// The arithmetic and the emptiness rules are part of what is being observed, so they are
// lifted from where they ship rather than stood in for.
require_once __DIR__ . '/pos_lift.php';
$root = dirname(__DIR__, 4);
pos_lift_into([
    $root . '/includes/std_func.php' => [
        'afrund', 'ifset', 'ifset_resolve_default', 'if_isset', 'get_settings_value',
    ],
    $root . '/includes/funktioner.php' => ['dkdecimal', 'usdecimal'],
    $root . '/includes/ordrefunc.php' => ['pos_afrund', 'grupperabat'],
]);

// --------------------------------------------------------------------------- reporting

set_error_handler(static function (int $no, string $message, string $file = '', int $line = 0) {
    $GLOBALS['visWarnings'][] = $message . ' in ' . basename($file) . ':' . $line;
    return true;
});

ob_start();
register_shutdown_function(static function () use ($work) {
    $printed = ob_get_clean();
    $pdo = $GLOBALS['visDb'];
    $tables = [];
    foreach (VIS_POS_LINJER_TABLES as $table) {
        $tables[$table] = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode([
        'result' => $GLOBALS['visResult'],
        'reached' => $GLOBALS['visReached'],
        'tables' => $tables,
        'sql' => $GLOBALS['visSql'],
        'calls' => $GLOBALS['visCalls'],
        'warnings' => $GLOBALS['visWarnings'],
        'printed' => $printed,
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

// ------------------------------------------- vis_pos_linjer() in pos_ordre.php's scope

$vars = ($spec['vars'] ?? []) + [
    'db' => $db,
    'db_id' => 1,
    'regnaar' => 2026,
    'brugernavn' => 'sd772',
    'baseCurrency' => 'DKK',
    'afd' => 0,
    'afd_lager' => '',
    'afd_navn' => '',
    'afslut' => '',
    'bgcolor' => '#fff',
    'bgcolor5' => '#eee',
    'bordnr' => '',
    'betvaluta' => 'DKK',
    'betvalkurs' => 100,
    'betvalsum' => 0,
    'del_bord' => '',
    'difkto' => 0,
    'discounttxt' => [],
    'fokus' => 'varenr_ny',
    'ifs' => 10,
    'kasse' => 1,
    'koekken' => '',
    'kundedisplay' => '',
    'lager_ny' => '',
    'lagerantal' => '',
    'lagernavn' => [],
    'lagernr' => [],
    'rabatart' => [],
    'sprog_id' => 1,
    'status' => 0,
    'svnr' => 'S',
    'tilfravalgNy' => '',
    'varelinjer' => 0,
    'varenr_ny' => '',
    'vis_saet' => '',
];
foreach ($vars as $name => $value) {
    $GLOBALS[$name] = $value;
}
$_GET = $spec['get'] ?? [];
$_POST = $spec['post'] ?? [];

require $root . '/debitor/pos_ordre_includes/showPosLines/showPosLinesFunc.php';

$args = $spec['args'] ?? [];
$GLOBALS['visReached'] = 'calling';
$GLOBALS['visResult'] = vis_pos_linjer(
    $args['id'] ?? 1,
    $args['momssats'] ?? 25,
    $args['status'] ?? 0,
    $args['pris_ny'] ?? '',
    $args['show'] ?? 1
);
$GLOBALS['visReached'] = 'returned';
