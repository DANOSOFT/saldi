<?php
// tests/characterization/support/run_pos_day_close.php
//
// Child-process runner for PosTenderDayCloseTest: drives the real POS posting code against a
// test tenant. bogfor_nu() comes from includes/ordrefunc.php through bootstrap_ordrefunc.php;
// posbogfor() is lifted from debitor/pos_ordre.php, as tests/test_cash_count_transaction.php does,
// because the page itself cannot be included outside a POS session.
//
// Modes (argv 2):
//   post <ordre_id>                      - bogfor_nu($id, ''), the call bogfor() makes for an immediately posted sale
//   count <kasse> <optalt> [currency]    - findBoxSale(), the figures the cash count shows (base currency by default)
//   close <kasse> <regnstart> <kassediff> [json] - posbogfor(), the approved cash count (day close); json holds more
//                                         count-form fields, e.g. {"valuta":["EUR"],"ValutaTilgang":["20"]}
//   carry <fiscal year> [json] [rr]      - updateFollowingOpeningBalances(<year>) in delta mode, the call Gem/opdatér on the
//                                         next year's Åbningsbalance (systemdata/regnskabskort.php) makes when <year>'s chart
//                                         has no Sideskift account; prints the years updated and why the walk stopped. json
//                                         holds postings made first in the same transaction, [[kontonr, debet, kredit,
//                                         transdate], ...], as finans/bogfor.php posts a journal into <year>: the rows, then
//                                         kontoplan.saldo of each account in <year>, then the carry. With rr the transaction
//                                         is REPEATABLE READ, as a transaction on MySQL and MariaDB is. Like finans/bogfor.php
//                                         it does not catch OpeningCarryConflict, so a conflict ends with the carry's alert.
//                                         It takes the carry's lock only in the carry (openingCarryLock()), as finans/bogfor.php
//                                         did before it took openingCarryLockBeforeTransaction() (JournalOpeningCarryLockTest)
//   create                               - settings_fiscal_year_create() for the year after the newest, as Indstillinger »
//                                         Regnskabsår » Opret runs it (genberegn() of the year before without the master
//                                         database's posting count, which needs a session)
//   genberegn <fiscal year>              - genberegn(<year>) inside a transaction, as an invoice runs it before its commit
//                                         (includes/ordrefunc.php bogfor() -> bogfor_nu())
//
// Prints a single JSON object on the LAST line of stdout, also when the posting code exits. Its alerts are the page's
// alert(<json>) messages; plainAlerts are std_func.php's alert('...'), which db_modify() and db_select() print when a
// statement fails without a browser session (the generic 'Uforudset hændelse' text of the database error box).
//
// With SALDI_POS_HOLD=1 in the environment, close, carry and create stop just before their commit, and genberegn after
// its kontoplan updates, just before it updates the year's grupper RA row: they write "##HELD## {"pid": <backend pid>}"
// to stdout and go on only when a line arrives on stdin. With SALDI_POS_HOLD=begin, close stops just before its
// transaction begins (after its check for sales from an earlier fiscal year). A test runs two sessions against each
// other this way. SALDI_PG_SET="name=value,..." sets PostgreSQL settings for the session first (deadlock_timeout,
// lock_timeout), so a test decides which of two deadlocked sessions PostgreSQL ends, or how long a lock is waited for.
//
// History:
// 20261117 CL/ASR Created.
// 20261118 CL/ASR count takes an optional currency, and close optional count-form fields as JSON, for the foreign-currency cases.
// 20261119 CL/ASR carry: the bookkeeper's later carry of a sale whose year's result had no account when the close posted it.
// 20261119 CL/ASR SALDI_POS_HOLD holds close and carry before their commit, and carry can post first, for the two-session carry tests.
//                 carry posts like finans/bogfor.php (also kontoplan.saldo) and can run in REPEATABLE READ; create and genberegn, and a hold before the close's transaction.
//                 carry no longer catches OpeningCarryConflict, as finans/bogfor.php does not; SALDI_PG_SET sets deadlock_timeout or lock_timeout for a session.
//                 plainAlerts: std_func.php's alert('...'), the database error box's text without a session.
// 20261119 CL/ASR carry's description says it takes the carry's lock only in the carry, the fallback a caller without the lock before its transaction gets, since finans/bogfor.php now takes it before its transaction (p1-yearend-F4).

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_pos_day_close.php <tenant_db> <post|count|close> [args]\n");
    exit(2);
}
$tenantDb = $argv[1];
$mode = $argv[2];

require __DIR__ . '/bootstrap_ordrefunc.php';
// bogfor_nu() appends to ../temp/<db>/hmlog.log; the test removes this directory again.
$tempDir = dirname(__DIR__, 3) . '/temp/' . $tenantDb;
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0770, true);
}
if (empty($db_type)) {
    $db_type = 'postgresql';
}
$afd = 0;
$reportNumber = 0;
$vis_saet = '';
$tracelog = null;

$hold = getenv('SALDI_POS_HOLD') === '1';
$holdAtBegin = getenv('SALDI_POS_HOLD') === 'begin';
/** With SALDI_POS_HOLD=1: tell the test this session is about to commit, and wait for its go-ahead on stdin. */
function run_pos_day_close_hold(): void
{
    global $connection;
    fwrite(STDOUT, '##HELD## ' . json_encode(array('pid' => pg_get_pid($connection))) . "\n");
    fflush(STDOUT);
    fgets(STDIN);
}

$source = file_get_contents(dirname(__DIR__, 3) . '/debitor/pos_ordre.php');
$start = strpos($source, 'function posbogfor(');
$end = strpos($source, 'function kasseoptalling(', $start);
$posbogfor = str_replace(
    array('__DIR__', '__FILE__'),
    array(var_export(dirname(__DIR__, 3) . '/debitor', true), var_export(dirname(__DIR__, 3) . '/debitor/pos_ordre.php', true)),
    substr($source, $start, $end - $start)
);
if ($hold) {
    // posbogfor() commits once, after its postings and the carry.
    if (substr_count($posbogfor, "transaktion('commit');") !== 1) {
        fwrite(STDERR, "posbogfor() no longer has exactly one commit to hold\n");
        exit(2);
    }
    $posbogfor = str_replace("transaktion('commit');", "run_pos_day_close_hold(); transaktion('commit');", $posbogfor);
}
if ($holdAtBegin) {
    // posbogfor() begins its transaction once, after its check for sales from an earlier fiscal year.
    if (substr_count($posbogfor, 'if (!beginCashCountPosting(') !== 1) {
        fwrite(STDERR, "posbogfor() no longer begins its transaction at exactly one place to hold\n");
        exit(2);
    }
    $posbogfor = str_replace('if (!beginCashCountPosting(', 'run_pos_day_close_hold(); if (!beginCashCountPosting(', $posbogfor);
}
eval($posbogfor);
require_once dirname(__DIR__, 3) . '/debitor/pos_ordre_includes/boxCountMethods/findBoxSale.php';

// SALDI_PG_SET: session settings, e.g. "deadlock_timeout=3s" (needs a superuser, as the test stack's user is) or
// "lock_timeout=300ms".
foreach (array_filter(explode(',', (string)getenv('SALDI_PG_SET'))) as $setting) {
    [$name, $value] = array_map('trim', explode('=', $setting, 2)) + array(1 => '');
    if (!preg_match('/^[a-z_]+$/', $name) || !preg_match('/^[0-9a-z]+$/', $value)) {
        fwrite(STDERR, "SALDI_PG_SET: bad setting $setting\n");
        exit(2);
    }
    db_modify("set $name = '$value'", __FILE__ . ' line ' . __LINE__);
}

$out = array('mode' => $mode);
register_shutdown_function(function () use (&$out) {
    while (ob_get_level() > 0) {
        $out['html'] = ($out['html'] ?? '') . ob_get_clean();
    }
    if (preg_match_all('/alert\((".*?")\);/', (string)($out['html'] ?? ''), $m)) {
        $out['alerts'] = array_map('json_decode', $m[1]);
    }
    if (preg_match_all("/alert\\('(.*?)'\\);<\\/script>/s", (string)($out['html'] ?? ''), $m)) {
        $out['plainAlerts'] = $m[1];
    }
    unset($out['html']);
    fwrite(STDOUT, json_encode($out) . "\n");
});
ob_start();
if ($mode === 'post') {
    transaktion('begin');
    $out['svar'] = bogfor_nu((int)$argv[3], '');
    transaktion($out['svar'] === 'OK' ? 'commit' : 'rollback');
} elseif ($mode === 'count') {
    $sale = findBoxSale((int)$argv[3], (float)$argv[4], $argv[5] ?? $baseCurrency, $ids);
    sort($ids);
    $out += array('byttepenge' => round((float)$sale[0], 2), 'tilgang' => round((float)$sale[1], 2), 'diff' => round((float)$sale[2], 2),
        'kontosum' => round((float)$sale[7], 2), 'ids' => $ids);
} elseif ($mode === 'close') {
    $_GET = array();
    $_POST = array('kassediff' => $argv[5]) + (isset($argv[6]) ? (array)json_decode($argv[6], true) : array());
    $out['exit'] = true; // cleared below when posbogfor() returns instead of exiting
    $out['posbogfor'] = posbogfor((int)$argv[3], $argv[4], 0, null, false);
    $out['exit'] = false;
} elseif ($mode === 'carry') {
    include_once dirname(__DIR__, 3) . '/includes/genberegn.php';
    include_once dirname(__DIR__, 3) . '/includes/updateFollowingOpeningBalances.php';
    transaktion('begin');
    if (($argv[5] ?? '') === 'rr') {
        db_modify('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', __FILE__ . ' line ' . __LINE__);
    }
    $sum = array();
    foreach (isset($argv[4]) ? (array)json_decode($argv[4], true) : array() as [$kontonr, $debet, $kredit, $transdate]) {
        db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id, kasse_nr)
            values ('0', '" . db_escape_string((string)$transdate) . "', 'chartest kladde', '" . (int)$kontonr . "', '0', '" . (float)$debet . "', '" . (float)$kredit . "',
            '0', '0', '" . date('Y-m-d') . "', '" . date('H:i:s') . "', '', '0', '0', '0')", __FILE__ . ' line ' . __LINE__);
        $sum[(int)$kontonr] = ($sum[(int)$kontonr] ?? 0) + (float)$debet - (float)$kredit;
    }
    // As finans/bogfor.php after its rows: each account's kontoplan.saldo in the posted year, read and written back.
    foreach ($sum as $kontonr => $amount) {
        $r = db_fetch_array(db_select("select id, saldo from kontoplan where kontonr = '$kontonr' and regnskabsaar = '" . (int)$argv[3] . "'", __FILE__ . ' line ' . __LINE__));
        if ($r) {
            db_modify("update kontoplan set saldo = '" . ((float)$r['saldo'] + $amount) . "' where id = '" . (int)$r['id'] . "'", __FILE__ . ' line ' . __LINE__);
        }
    }
    $out['exit'] = true; // cleared below when the carry returns instead of exiting
    // As finans/bogfor.php: no handler for OpeningCarryConflict, so a conflict ends here with the carry's own alert.
    $out['updated'] = updateFollowingOpeningBalances((int)$argv[3], OPENING_CARRY_DELTA, $stop);
    $out['stop'] = $stop;
    if ($hold) {
        run_pos_day_close_hold();
    }
    transaktion('commit');
    $out['exit'] = false;
} elseif ($mode === 'create') {
    if (!class_exists('SettingsService', false)) {
        eval('final class SettingsService { public static function auditRow(...$args): void {} }');
    }
    include_once dirname(__DIR__, 3) . '/includes/genberegn.php';
    $hooks = dirname(__DIR__, 3) . '/includes/settings/rowHooks.php';
    require_once $hooks;
    $src = (string)file_get_contents($hooks);
    $start = strpos($src, 'function settings_fiscal_year_create(');
    $create = substr($src, $start, strpos($src, "\n}\n", $start) + 3 - $start);
    foreach (array('genberegn($prev);' => 'genberegn($prev, false);', "transaktion('commit');" => ($hold ? "run_pos_day_close_hold(); " : '') . "transaktion('commit');") as $from => $to) {
        if (substr_count($create, $from) !== 1) {
            fwrite(STDERR, "settings_fiscal_year_create() no longer has exactly one $from\n");
            exit(2);
        }
        $create = str_replace($from, $to, $create);
    }
    eval(str_replace(array('function settings_fiscal_year_create(', '__DIR__'),
        array('function run_pos_day_close_fiscal_year_create(', var_export(dirname($hooks), true)), $create));
    $sug = settings_fiscal_year_suggestion();
    $out['exit'] = true; // cleared below when the creation returns instead of exiting
    $out['create'] = run_pos_day_close_fiscal_year_create($sug['start_month'], $sug['start_year'], $sug['end_month'], $sug['end_year'], '', true);
    $out['exit'] = false;
} elseif ($mode === 'genberegn') {
    $src = (string)file_get_contents(dirname(__DIR__, 3) . '/includes/genberegn.php');
    $raRow = "\$qtxt=\"update grupper set box6 = '\$r[transantal]' where art = 'RA' and kodenr = '\$regnskabsaar'\";";
    if (substr_count($src, $raRow) !== 1) {
        fwrite(STDERR, "genberegn() no longer updates the year's RA row at exactly one place to hold\n");
        exit(2);
    }
    if ($hold) {
        $src = str_replace($raRow, 'run_pos_day_close_hold(); ' . $raRow, $src);
    }
    eval(preg_replace('/^<\?php/', '', str_replace(array("function_exists('genberegn')", 'function genberegn(', '__DIR__'),
        array("function_exists('run_pos_day_close_genberegn')", 'function run_pos_day_close_genberegn(', var_export(dirname(__DIR__, 3) . '/includes', true)), $src)));
    $out['exit'] = true; // cleared below when genberegn() returns instead of exiting
    transaktion('begin');
    run_pos_day_close_genberegn((int)$argv[3], false);
    transaktion('commit');
    $out['exit'] = false;
} else {
    fwrite(STDERR, "unknown mode $mode\n");
    exit(2);
}
