<?php
// tests/test_sst809_kontokort_pagination.php
//
// SST-809: Simuleret kontokort - the printout does not match what is on screen.
//
// The report in finans/rapport_includes/kontokort.php is paginated server side,
// so the only rows that exist in the DOM are the ones written for the requested
// page. Whether a browser print can ever show the whole account therefore
// depends on one thing: that the report agrees with itself about how many rows
// there are. This test pins that down.
//
// It renders the real kontokort() against an in-memory SQLite fixture and
// asserts the invariants the ticket's acceptance criteria describe:
//
//   * walking every page and concatenating the rows yields exactly the rows of
//     the un-paginated render - same rows, same order, no gaps, no duplicates
//   * the footer's "X-Y af Z" total equals the number of rows actually rendered
//   * an account with an opening balance but no movements still appears
//   * the running Saldo is primo + sum(debet - kredit) on every line
//
// and it does so for each combination of the two options that inject extra
// rows after the row count has been taken: Simulering and Lagerbevaegelser.
//
// No database and no session: db_select()/db_fetch_array() are replaced with
// doubles over PDO SQLite, so the SQL is still executed by a real SQL engine.
// db_fetch_array() casts every column to a string because that is what
// pg_fetch_array() does - a numeric(15,3) zero arrives as the string "0.000",
// which is truthy in PHP. That detail is the whole of one of the bugs here, so
// the double has to reproduce it.
//
// Run:  php tests/test_sst809_kontokort_pagination.php

// ---------------------------------------------------------------------------
// assertions
// ---------------------------------------------------------------------------

$GLOBALS['sst809_checks'] = 0;
$GLOBALS['sst809_fails']  = array();

function check($cond, $what)
{
    $GLOBALS['sst809_checks']++;
    if (!$cond) {
        $GLOBALS['sst809_fails'][] = $what;
        echo "  FAIL  $what\n";
    }
    return (bool) $cond;
}

function check_same($expected, $actual, $what)
{
    $same = ($expected === $actual);
    if (!$same) {
        $what .= "\n          expected: " . var_export($expected, true)
               . "\n          actual:   " . var_export($actual, true);
    }
    return check($same, $what);
}

// ---------------------------------------------------------------------------
// db doubles, over a real SQL engine
// ---------------------------------------------------------------------------

$GLOBALS['sst809_pdo'] = new PDO('sqlite::memory:', null, null, array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
));

// Columns the real schema declares as numeric(15,3)/numeric(15,2). Postgres
// returns them as zero-padded decimal strings; SQLite hands back PHP floats.
// Formatting them back to a fixed scale is what makes "0.000" show up here the
// same way it shows up in production.
$GLOBALS['sst809_numeric_cols'] = array(
    'debet' => 3, 'kredit' => 3, 'primo' => 2, 'kurs' => 3,
    'valutakurs' => 3, 'kostpris' => 3, 'antal' => 3, 'moms' => 3,
);

function db_select($qtxt, $where = '')
{
    $st = $GLOBALS['sst809_pdo']->query($qtxt);
    if ($st === false) {
        // Mirrors the real wrapper: a failing query yields a falsy handle
        // rather than an exception (findtekst() relies on this to fall back to
        // its inline default when the tekster table is absent).
        return false;
    }
    return $st;
}

function db_fetch_array($st)
{
    if (!$st || $st === true) {
        return false;
    }
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return false;
    }
    $out = array();
    $i   = 0;
    foreach ($row as $col => $val) {
        if ($val === null) {
            $out[$col] = null;
        } elseif (isset($GLOBALS['sst809_numeric_cols'][$col]) && is_numeric($val)) {
            $out[$col] = number_format((float) $val, $GLOBALS['sst809_numeric_cols'][$col], '.', '');
        } else {
            $out[$col] = (string) $val;
        }
        $out[$i++] = $out[$col];
    }
    return $out;
}

function db_escape_string($s)
{
    return str_replace("'", "''", (string) $s);
}

function db_modify($qtxt, $where = '')
{
    return $GLOBALS['sst809_pdo']->exec($qtxt);
}

function db_connect($h = '', $u = '', $p = '', $d = '')
{
    return true;
}

function db_close($c = null)
{
    return true;
}

function db_free_result($r = null)
{
    return true;
}

function db_num_rows($st)
{
    return $st ? count($st->fetchAll(PDO::FETCH_ASSOC)) : 0;
}

// ---------------------------------------------------------------------------
// fixture
// ---------------------------------------------------------------------------

function sst809_seed()
{
    $pdo = $GLOBALS['sst809_pdo'];

    $pdo->exec("create table adresser (id integer, art text, firmanavn text, cvrnr text)");
    $pdo->exec("create table grupper (kodenr text, art text, box1 text, box2 text, box3 text,
                                      box4 text, box8 text, box11 text, box13 text)");
    $pdo->exec("create table kontoplan (kontonr integer, regnskabsaar integer, beskrivelse text,
                                        kontotype text, moms text, valuta text, valutakurs real,
                                        primo real)");
    $pdo->exec("create table transaktioner (id integer primary key, kontonr integer, bilag integer,
                                           transdate text, beskrivelse text, debet real, kredit real,
                                           kladde_id integer, valuta text, valutakurs real,
                                           afd integer, ansat integer, projekt text, pos integer)");
    $pdo->exec("create table simulering (id integer primary key, kontonr integer, bilag integer,
                                        transdate text, beskrivelse text, debet real, kredit real,
                                        kladde_id integer, valuta text, valutakurs real,
                                        afd integer, ansat integer, projekt text)");
    $pdo->exec("create table valuta (gruppe text, kurs real, valdate text)");
    $pdo->exec("create table varer (id integer, gruppe text, kostpris real)");
    $pdo->exec("create table batch_kob (vare_id integer, ordre_id integer, antal real, kobsdate text)");
    $pdo->exec("create table batch_salg (vare_id integer, ordre_id integer, antal real, salgsdate text)");
    $pdo->exec("create table ordrer (id integer, fakturanr integer)");

    $pdo->exec("insert into adresser (id, art, firmanavn, cvrnr) values (1, 'S', 'Testfirma ApS', '42390895')");

    // Fiscal year 1 = 01/2026 - 12/2026.
    $pdo->exec("insert into grupper (kodenr, art, box1, box2, box3, box4)
                values ('1', 'RA', '01', '2026', '12', '2026')");

    // One stock group, so Lagerbevaegelser has something to inject.
    //   box1 = varelager_i (1400), box2 = varelager_u (1410), box3 = varekob (1500)
    $pdo->exec("insert into grupper (kodenr, art, box1, box2, box3, box8, box11, box13)
                values ('10', 'VG', '1400', '1410', '1500', 'on', '', '')");

    $accounts = array(
        // kontonr, beskrivelse, primo
        array(1000, 'Kasse',      0.0),
        array(1100, 'Bank',       5000.0),   // opening balance, no movements
        array(1200, 'Debitorer',  0.0),
        array(1250, 'EUR-bank',   0.0, 'EUR'),   // currency account: exercises the conversion branch
        array(1300, 'Nulkonto',   0.0),
        array(1400, 'Varelager',  0.0),
        array(1410, 'Vareforbrug', 0.0),
        array(1500, 'Varekoeb',   0.0),
        array(1600, 'Sidste konto', 250.0),  // primo only, and sorts after every row
    );
    foreach ($accounts as $a) {
        $pdo->exec("insert into kontoplan (kontonr, regnskabsaar, beskrivelse, kontotype, moms,
                                           valuta, valutakurs, primo)
                    values ({$a[0]}, 1, '{$a[1]}', 'S', '', '" . (isset($a[3]) ? $a[3] : '') . "', 100.0, {$a[2]})");
    }

    // 1000: four ordinary rows, enough to straddle several page sizes.
    $tx = array(
        array(1, 1000, 101, '2026-01-10', 'Indbetaling',  100.0, 0.0),
        array(2, 1000, 102, '2026-02-10', 'Udbetaling',     0.0, 50.0),
        // A row whose two amounts are both zero, on an account that also has
        // ordinary rows. numeric(15,3) renders zero as the string "0.000",
        // which PHP treats as true, so the renderer emits a line the COUNT(*)
        // deliberately excluded - the page then holds one more row than the
        // pagination believes exists. It sits mid-account on purpose, so a
        // miscount shifts every later page boundary.
        array(3, 1000, 103, '2026-02-20', 'Nulpostering',    0.0, 0.0),
        array(4, 1000, 104, '2026-03-10', 'Indbetaling',  300.0, 0.0),
        array(5, 1000, 105, '2026-04-10', 'Udbetaling',     0.0, 75.0),
        // 1200: two real rows; Simulering adds two more between them.
        array(6, 1200, 201, '2026-05-01', 'Faktura 1',     50.0, 0.0),
        array(7, 1200, 202, '2026-06-01', 'Indbetaling',    0.0, 25.0),
        // 1250: a currency account. The first row converts at the rate; the second is a rate
        // adjustment posted in DKK only (valuta = -1), which SST-769 shows as a labelled DKK
        // figure instead of 0,00. Both go through the conversion branch of the print pass.
        array(20, 1250, 251, '2026-04-02', 'EUR faktura',  746.0, 0.0, '', 746.0),
        array(21, 1250, 252, '2026-04-03', 'Kursregulering', 25.0, 0.0, '-1', 100.0),
        // 1300: nothing but a zero-amount row, so the account has ledger
        // activity but no line worth printing. It must still show its header
        // and Primosaldo.
        array(8, 1300, 301, '2026-08-01', 'Nulpostering',   0.0, 0.0),
        // 1400: one real row; Lagerbevaegelser adds a synthetic one in March.
        array(9, 1400, 401, '2026-09-01', 'Varekoeb',     900.0, 0.0),
    );
    foreach ($tx as $t) {
        $pdo->exec("insert into transaktioner (id, kontonr, bilag, transdate, beskrivelse, debet,
                                               kredit, kladde_id, valuta, valutakurs, pos)
                    values ({$t[0]}, {$t[1]}, {$t[2]}, '{$t[3]}', '{$t[4]}', {$t[5]}, {$t[6]},
                            0, '" . (isset($t[7]) ? $t[7] : '') . "', " . (isset($t[8]) ? $t[8] : 100.0) . ", 0)");
    }

    $sim = array(
        array(1, 1200, 901, '2026-05-15', 'Simuleret faktura', 10.0, 0.0),
        array(2, 1200, 902, '2026-07-01', 'Simuleret kredit',   0.0, 5.0),
    );
    foreach ($sim as $s) {
        $pdo->exec("insert into simulering (id, kontonr, bilag, transdate, beskrivelse, debet,
                                            kredit, kladde_id, valuta, valutakurs)
                    values ({$s[0]}, {$s[1]}, {$s[2]}, '{$s[3]}', '{$s[4]}', {$s[5]}, {$s[6]},
                            7, '', 100.0)");
    }

    // Stock movement: 3 units at 100 on 2026-03-05, so account 1400 gains one
    // synthetic "lagertransaktion" row when Lagerbevaegelser is on.
    $pdo->exec("insert into valuta (gruppe, kurs, valdate) values ('EUR', 746.0, '2025-01-01')");
    $pdo->exec("insert into varer (id, gruppe, kostpris) values (1, '10', 100.0)");
    $pdo->exec("insert into ordrer (id, fakturanr) values (1, 777)");
    $pdo->exec("insert into batch_kob (vare_id, ordre_id, antal, kobsdate)
                values (1, 1, 3.0, '2026-03-05')");
}

// ---------------------------------------------------------------------------
// render + parse
// ---------------------------------------------------------------------------

function sst809_render($page, $per_page, $simulering, $lagerbev, $udskriv = null)
{
    global $md, $menu, $db, $sprog_id, $csv, $connection;
    global $bgcolor, $bgcolor4, $bgcolor5, $top_bund;
    global $afd_navn, $ansatte, $ansatte_id, $prj_navn_fra, $prj_navn_til;

    $args = array(
        1,            // regnaar
        '01', '12',   // maaned_fra, maaned_til
        '2026', '2026',
        '1', '31',    // dato_fra, dato_til
        1000, 99999,  // konto_fra, konto_til
        'kontokort',
        '', '',       // ansat_fra, ansat_til
        '',           // afd
        '', '',       // projekt_fra, projekt_til
        $simulering, $lagerbev,
        $page, $per_page,
    );
    if ($udskriv !== null) {
        $args[] = $udskriv;
    }

    ob_start();
    call_user_func_array('kontokort', $args);
    return ob_get_clean();
}

// Pulls the data rows out of the rendered report. A data row is a table row
// whose first cell holds a dd-mm-yyyy date; the account header rows and the
// Primosaldo rows are picked up separately so their presence can be asserted.
function sst809_parse($html)
{
    $out = array('rows' => array(), 'accounts' => array(), 'primo' => array(), 'total' => null);

    if (preg_match('/(\d+)-(\d+)&nbsp;af&nbsp;(\d+)/', $html, $m)) {
        $out['total'] = (int) $m[3];
    }

    // <b>1000</b> : <b>Kasse</b>
    if (preg_match_all('#<b>(\d+)</b>\s*:\s*\n?\s*<b>#', $html, $m)) {
        $out['accounts'] = $m[1];
    }

    if (preg_match_all('#<tr[^>]*>(?:(?!</tr>).)*?Primosaldo.*?</tr>#s', $html, $m)) {
        foreach ($m[0] as $row) {
            if (preg_match_all('#<td[^>]*>(.*?)</td>#s', $row, $c)) {
                $out['primo'][] = trim(strip_tags(end($c[1])));
            }
        }
    }

    if (preg_match_all('#<tr[^>]*>((?:(?!</tr>).)*?)</tr>#s', $html, $m)) {
        foreach ($m[1] as $body) {
            if (!preg_match_all('#<td[^>]*>(.*?)</td>#s', $body, $c)) {
                continue;
            }
            if (count($c[1]) !== 6) {
                continue;
            }
            $cells = array_map(function ($v) {
                return trim(preg_replace('/\s+/', ' ', strip_tags($v)));
            }, $c[1]);
            if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $cells[0])) {
                continue;
            }
            $out['rows'][] = $cells;
        }
    }

    return $out;
}

function dk2f($s)
{
    $s = str_replace(array('.', 'DKK', ' ', "\xc2\xa0"), '', $s);
    return (float) str_replace(',', '.', $s);
}

// ---------------------------------------------------------------------------
// bootstrap
// ---------------------------------------------------------------------------

$root = dirname(__DIR__);

$_SESSION    = array();
$sprog_id    = 1;
$menu        = '';          // plain layout: no top_menu/top_header includes
$db          = 'sst809test';
$bgcolor     = '#ffffff';
$bgcolor4    = '#dddddd';
$bgcolor5    = '#eeeef0';
$top_bund    = '';
$afd_navn    = '';
$ansatte     = '';
$ansatte_id  = '';
$prj_navn_fra = '';
$prj_navn_til = '';
$buttonColor    = '#336699';
$buttonTxtColor = '#ffffff';
$connection  = true;
$md = array(1 => 'Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni', 'Juli',
            'August', 'September', 'Oktober', 'November', 'December');

// kontokort() writes a csv alongside the html; give it somewhere to write.
@mkdir("$root/temp/$db", 0777, true);

require "$root/includes/std_func.php";

sst809_seed();

// The report resolves its includes relative to finans/.
chdir("$root/finans");
ob_start();
require "$root/finans/rapport_includes/kontokort.php";
ob_end_clean();

$ref = new ReflectionFunction('kontokort');
$supports_udskriv = false;
foreach ($ref->getParameters() as $p) {
    if ($p->getName() === 'udskriv') {
        $supports_udskriv = true;
    }
}

echo "kontokort(): " . $ref->getNumberOfParameters() . " parameters"
   . ($supports_udskriv ? ", supports udskriv\n" : ", no udskriv parameter\n");

// ---------------------------------------------------------------------------
// the option matrix
// ---------------------------------------------------------------------------

$modes = array(
    'plain'                  => array(null, null),
    'simulering'             => array('on', null),
    'lagerbev'               => array(null, 'on'),
    'simulering + lagerbev'  => array('on', 'on'),
);

$BIG = 100000;   // one render with everything on the page

foreach ($modes as $label => $opt) {
    list($sim, $lager) = $opt;
    echo "\n--- $label ---\n";

    $full     = sst809_parse(sst809_render(1, $BIG, $sim, $lager));
    $fullrows = $full['rows'];

    echo "  rendered in one page: " . count($fullrows) . " rows"
       . ", footer total " . var_export($full['total'], true) . "\n";

    // (1) The footer total is the number of rows the report can actually show.
    //     This is what drives total_pages, so if it is wrong the last page is
    //     unreachable or empty.
    check_same(count($fullrows), $full['total'],
        "[$label] footer total equals the number of rows rendered");

    // (2) Every account with movements or an opening balance appears, exactly
    //     once. 1100 has an opening balance and no movements: pagination used
    //     to drop it on every page.
    $expect_accounts = array('1000', '1100', '1200', '1250', '1300', '1400', '1600');
    if ($lager) {
        $expect_accounts[] = '1410';
        $expect_accounts[] = '1500';
    }
    sort($expect_accounts);
    $got = $full['accounts'];
    sort($got);
    check_same($expect_accounts, $got,
        "[$label] every account with rows or an opening balance is rendered once");

    // (3) Walking the pages reproduces the un-paginated report exactly.
    foreach (array(1, 2, 3, 5, count($fullrows)) as $per_page) {
        $walked = array();
        $seen_accounts = array();
        // Only the pages the report actually offers. Walking one page past total_pages
        // would let an account that belongs on the last page be "found" on a page the
        // user can never reach, which is how an earlier version of this test missed a
        // trailing primo-only account being dropped from every real page.
        $pages = max(1, (int) ceil(count($fullrows) / $per_page));
        for ($p = 1; $p <= $pages; $p++) {
            $got = sst809_parse(sst809_render($p, $per_page, $sim, $lager));
            foreach ($got['rows'] as $r) {
                $walked[] = $r;
            }
            foreach ($got['accounts'] as $a) {
                $seen_accounts[] = $a;
            }
            check(count($got['rows']) <= $per_page,
                "[$label] per_page=$per_page page $p renders at most $per_page rows");
        }
        check_same($fullrows, $walked,
            "[$label] per_page=$per_page walking every page reproduces the full report");
        // An account whose rows straddle a page boundary repeats its header on
        // each of those pages, which is wanted - only the data rows must not
        // repeat. So this asserts coverage, not uniqueness.
        sort($seen_accounts);
        $uniq = array_values(array_unique($seen_accounts));
        check_same($expect_accounts, $uniq,
            "[$label] per_page=$per_page every account appears on a page the report offers");

        // Past the last page there is nothing at all - no rows and no account headers.
        $over = sst809_parse(sst809_render($pages + 1, $per_page, $sim, $lager));
        check_same(array(), $over['rows'],
            "[$label] per_page=$per_page the page after the last renders no rows");
        check_same(array(), $over['accounts'],
            "[$label] per_page=$per_page the page after the last renders no account headers");
    }

    // (4) The Saldo column is a running balance: each line is the previous
    //     balance plus debet minus kredit, starting from the account's primo.
    $primo = array('1000' => 0.0, '1100' => 5000.0, '1200' => 0.0,
                   '1250' => 0.0, '1300' => 0.0, '1400' => 0.0, '1410' => 0.0,
                   '1500' => 0.0, '1600' => 250.0);
    $running = null;
    $acct = null;
    $bad = 0;
    foreach ($fullrows as $r) {
        // Rows carry "kontonr : beskrivelse" in the text column.
        if (preg_match('/^(\d+)\s*:/', $r[2], $m)) {
            if ($m[1] !== $acct) {
                $acct    = $m[1];
                $running = $primo[$acct];
            }
        }
        // A currency account prints its amounts and balance converted at the row's rate, so
        // the running-sum identity is asserted only on the DKK accounts; the conversion
        // itself is asserted separately below.
        if ($acct === '1250') {
            continue;
        }
        $running += round(dk2f($r[3]), 2) - round(dk2f($r[4]), 2);
        if (abs($running - dk2f($r[5])) > 0.005) {
            $bad++;
        }
    }
    check_same(0, $bad, "[$label] Saldo is primo + running sum(debet - kredit) on every line");

    // (5) A row whose debet and kredit are both zero carries no amount and
    //     moves no balance. The report's own COUNT(*) has excluded such rows
    //     ever since pagination was added, so the renderer must exclude them
    //     too. It did not: numeric(15,3) zero arrives as the string "0.000",
    //     which is truthy in PHP, so the page held a row the pagination did
    //     not know about. Documented behaviour change - the line no longer
    //     appears at all.
    $zero_rendered = 0;
    foreach ($fullrows as $r) {
        if (strpos($r[2], 'Nulpostering') !== false) {
            $zero_rendered++;
        }
    }
    check_same(0, $zero_rendered,
        "[$label] a row with debet and kredit both zero is not rendered");

    // (6) The conversion branch. SST-769 made a rate adjustment (valuta = -1) print its DKK
    //     figure, labelled, instead of 0,00 - and this report now reads every value from one
    //     row record rather than parallel arrays, so the accessors in that branch are worth
    //     pinning: a wrong one would silently convert at another row's rate.
    $eurRows = array();
    foreach ($fullrows as $r) {
        if (strpos($r[2], '1250 :') !== false) {
            $eurRows[] = $r;
        }
    }
    check_same(2, count($eurRows), "[$label] both rows of the currency account are rendered");
    if (count($eurRows) === 2) {
        // 746,00 at a rate of 746 is 100,00 in the account's own currency.
        check(abs(dk2f($eurRows[0][3]) - 100.0) < 0.005,
            "[$label] a currency row's amount is converted at its rate (got {$eurRows[0][3]})");
        // The rate adjustment keeps its DKK amount and is labelled as DKK, not shown as 0,00.
        check(strpos($eurRows[1][3], 'DKK') !== false,
            "[$label] a rate adjustment is labelled DKK (got {$eurRows[1][3]})");
        check(abs(dk2f($eurRows[1][3]) - 25.0) < 0.005,
            "[$label] a rate adjustment keeps its DKK amount (got {$eurRows[1][3]})");
    }
}

// ---------------------------------------------------------------------------
// print mode
// ---------------------------------------------------------------------------

if ($supports_udskriv) {
    echo "\n--- print mode ---\n";
    foreach ($modes as $label => $opt) {
        list($sim, $lager) = $opt;
        $screen = sst809_parse(sst809_render(1, $BIG, $sim, $lager));
        // The print view is reached from page 2 of a 2-rows-per-page screen: if
        // it were still bound to the on-screen pagination it would show 2 rows.
        $printed = sst809_render(2, 2, $sim, $lager, 'on');
        $parsed  = sst809_parse($printed);

        check_same($screen['rows'], $parsed['rows'],
            "[$label] print mode renders the whole report, not the requested page");
        check(strpos($printed, 'overflow-y: auto') === false,
            "[$label] print mode has no scrollable container");
        check(strpos($printed, 'position:fixed') === false
              && strpos($printed, 'position: fixed') === false,
            "[$label] print mode has no fixed-position pagination bar");
        check(strpos($printed, 'Linjer pr. side') === false,
            "[$label] print mode drops the rows-per-page selector");
        check(strpos($printed, '<tfoot') !== false,
            "[$label] print mode puts the footer in <tfoot> so it repeats on every printed page");
    }

    // The paginated view must still carry print rules that neutralise the
    // scroll container and the fixed bar, for a plain Ctrl+P of the screen.
    $screenHtml = sst809_render(1, 50, 'on', null);
    check(strpos($screenHtml, '@media print') !== false,
        "[screen] the paginated view ships @media print rules");
    check(preg_match('/@media print.*?overflow\s*:\s*visible/s', $screenHtml) === 1,
        "[screen] print rules release the scroll container");
    check(preg_match('/@media print.*?kontokort-pagebar[^}]*display\s*:\s*none/s', $screenHtml) === 1,
        "[screen] print rules hide the pagination bar");
    check(preg_match('/@media print.*?white-space\s*:\s*nowrap/s', $screenHtml) === 1,
        "[screen] print rules stop the date column wrapping");
}

// ---------------------------------------------------------------------------
// optional: write the rendered report out as standalone pages, wrapped in the
// same stylesheets the top-menu layout loads, so the print layout can be
// checked in a real browser with print media emulation.
//
//   php tests/test_sst809_kontokort_pagination.php --preview <dir>
//
// <dir> must be reachable over http for the relative css paths to resolve.
// ---------------------------------------------------------------------------

if (isset($argv[1]) && $argv[1] === '--preview' && isset($argv[2])) {
    $dir = rtrim($argv[2], '/');
    @mkdir($dir, 0777, true);

    $wrap = function ($title, $body) {
        return "<!DOCTYPE html>\n<html><head><meta charset=\"utf-8\">\n"
             . "<title>$title</title>\n"
             // top_menu.css is what makes .dataTable table-layout:fixed, which is
             // the rule the column widths have to contend with. Nothing else here
             // affects column layout, and leaving the rest out keeps the preview
             // free of remote fonts.
             . "<link rel=\"stylesheet\" type=\"text/css\" href=\"../../css/top_menu.css\">\n"
             . "</head><body>\n$body\n</body></html>\n";
    };

    // Only the data table matters for column layout.
    $only_table = function ($html) {
        if (preg_match('#<div class="kontokort-data".*$#s', $html, $m)) {
            $html = $m[0];
        }
        return $html;
    };

    $printed = $only_table(sst809_render(1, 50, 'on', null, 'on'));
    file_put_contents("$dir/print.html", $wrap('kontokort print', $printed));

    // The same page with the column widths removed, i.e. six equal columns, to
    // show what the date column did before they were declared.
    $stripped = preg_replace('/ width:\d+%;/', '', $printed);
    $stripped = str_replace('white-space:nowrap;', '', $stripped);
    $stripped = str_replace('.kontokort-dato { white-space: nowrap; }', '', $stripped);
    file_put_contents("$dir/print-nowidths.html", $wrap('kontokort print, no widths', $stripped));

    $screen = $only_table(sst809_render(1, 50, 'on', null));
    file_put_contents("$dir/screen.html", $wrap('kontokort screen', $screen));

    echo "\npreview written to $dir (print.html, print-nowidths.html, screen.html)\n";
}

echo "\n";
$fails = $GLOBALS['sst809_fails'];
echo "checks: " . $GLOBALS['sst809_checks'] . ", failures: " . count($fails) . "\n";
if ($fails) {
    echo "\nfailed:\n";
    foreach ($fails as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "OK\n";
exit(0);
