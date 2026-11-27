<?php
// tests/characterization/support/run_pos_currency_count.php
//
// Child-process runner for PosCurrencyCashCountTest: the cash count of a till that takes a foreign currency.
// It runs kasseoptalling() (the count screen) and posbogfor() (posting the approved count), lifted from the
// shipped debitor/pos_ordre.php, against the test class's own tenant. pos_ordre.php itself needs a till login and
// draws the whole till, so the runner loads what the two functions call the way pos_ordre.php does.
// kasseoptalling() ends with exit and writes ../temp/<db>/, so it runs in a scratch directory with its own temp/
// and a link to the repository's importfiler, and its page is read when the script shuts down.
//
// argv: 1 = tenant database
//       2 = count | close
//       3 = JSON: count {"kasse":1,"optalt":<base currency counted>,"optval":[<foreign currency counted>, ...]}
//                 close {"kasse":1,"post":[[name, value], ...]} (the count form's fields, as the browser posts them)
//
// Prints one JSON line on stdout:
//   count - fields: the count form's input fields as [name, value] pairs
//   close - posbogfor: what posbogfor() returned
//
// History:
// 20261117 CL/ASR Created for POS-F2, POS-F3 and POS-F8 (foreign-currency cash count).

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_pos_currency_count.php <tenant_db> <count|close> <json>\n");
    exit(2);
}
$tenantDb = $argv[1];
$chartestMode = $argv[2];
$chartestArgs = json_decode($argv[3], true);
if (!is_array($chartestArgs)) {
    fwrite(STDERR, "argument 3 is not a JSON object\n");
    exit(2);
}

require __DIR__ . '/bootstrap_ordrefunc.php';

$chartestRoot = dirname(__DIR__, 3);
$chartestScratch = sys_get_temp_dir() . '/chartest_poscur_' . getmypid();
mkdir($chartestScratch . '/page', 0700, true);
mkdir($chartestScratch . '/temp/' . $tenantDb, 0700, true);
symlink($chartestRoot . '/importfiler', $chartestScratch . '/importfiler');
chdir($chartestScratch . '/page');
register_shutdown_function(static function () use ($chartestScratch): void {
    exec('rm -rf ' . escapeshellarg($chartestScratch));
});

include_once $chartestRoot . '/debitor/pos_ordre_includes/helperMethods/helperFunc.php';
include_once $chartestRoot . '/debitor/pos_ordre_includes/boxCountMethods/boxCount.php';
include_once $chartestRoot . '/debitor/pos_ordre_includes/boxCountMethods/printBoxCount.php';
include_once $chartestRoot . '/debitor/pos_ordre_includes/boxCountMethods/boxCountText.php';

// posbogfor() and kasseoptalling(), which follows it, as shipped, up to kasseoptalling()'s closing brace in column 1;
// __DIR__ in them is debitor/.
$chartestSource = file_get_contents($chartestRoot . '/debitor/pos_ordre.php');
$chartestStart = strpos($chartestSource, 'function posbogfor(');
$chartestEnd = strpos($chartestSource, "\n}\n", (int)strpos($chartestSource, 'function kasseoptalling(')) + 2;
if ($chartestStart === false || $chartestEnd === 2) {
    fwrite(STDERR, "posbogfor()/kasseoptalling() not found in debitor/pos_ordre.php\n");
    exit(2);
}
// Included from a file rather than eval()'d: posbogfor() writes __FILE__ into SQL, and eval()'s __FILE__ has a quote.
$chartestLifted = $chartestScratch . '/pos_ordre_count.php';
file_put_contents($chartestLifted, "<?php\n" . str_replace('__DIR__', var_export($chartestRoot . '/debitor', true), substr($chartestSource, $chartestStart, $chartestEnd - $chartestStart)));
include $chartestLifted;

$sprog_id = 1;
$kasse = (int)($chartestArgs['kasse'] ?? 1);
$id = 0;
$bordnr = '';
$ifs = 13;
$log = NULL;
$vis_saet = '';
$_SERVER['SERVER_NAME'] = 'chartest.local';
$_SERVER['PHP_SELF'] = '/saldi/debitor/pos_ordre.php';
$r = db_fetch_array(db_select("select box1, box2 from grupper where art = 'RA' and kodenr = '$regnaar'", __FILE__ . " linje " . __LINE__));
$chartestRegnstart = $r['box2'] . '-' . $r['box1'] . '-01';

if ($chartestMode === 'count') {
    $_GET = array();
    $_POST = array('calculate' => 1);
    $optalt = (float)$chartestArgs['optalt'];
    $chartestOptval = array_map('floatval', $chartestArgs['optval']);
    foreach ($chartestOptval as $x => $value) {
        $_POST['optval'][$x] = dkdecimal($value, 2);
    }
    // kasseoptalling() ends the request with exit: the page is read from the output buffer at shutdown.
    register_shutdown_function(static function (): void {
        $html = '';
        while (ob_get_level() > 0) {
            $html = ob_get_clean() . $html;
        }
        $form = substr($html, (int)strpos($html, 'name="optael"'));
        preg_match_all("/<input[^>]*?name\\s*=\\s*(?:name=)?['\"]([^'\"]+)['\"][^>]*?value\\s*=\\s*['\"]([^'\"]*)['\"]/i", $form, $m, PREG_SET_ORDER);
        $fields = array();
        foreach ($m as $match) {
            $fields[] = array($match[1], $match[2]);
        }
        fwrite(STDOUT, json_encode(array('fields' => $fields)) . "\n");
    });
    ob_start();
    kasseoptalling($kasse, $optalt, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, $optalt, $chartestOptval);
    exit;
}
if ($chartestMode === 'close') {
    $chartestQuery = array();
    foreach ($chartestArgs['post'] as $pair) {
        $chartestQuery[] = urlencode($pair[0]) . '=' . urlencode($pair[1]);
    }
    parse_str(implode('&', $chartestQuery), $_POST);
    $_POST['optael'] = 'Godkend';
    $_GET = array();
    ob_start();
    $chartestPosted = posbogfor($kasse, $chartestRegnstart, 0, null, false);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    fwrite(STDOUT, json_encode(array('posbogfor' => $chartestPosted)) . "\n");
    exit;
}
fwrite(STDERR, "unknown mode $chartestMode\n");
exit(2);
