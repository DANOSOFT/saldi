<?php
// 20260920 CDX/LUI Exercise legacy stock page entry, explicit cost writes and CSRF rejection.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$scenario = $argv[1] ?? '';
if ($scenario === '') {
    foreach (['units-get', 'units-post', 'materials-post', 'bom-empty', 'import-get', 'cost-get', 'cost-post', 'cost-forged', 'balance-unassigned', 'balance-selected', 'balance-latin1', 'balance-invalid'] as $case) {
        $pipes = [];
        $child = proc_open([PHP_BINARY, __FILE__, $case], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($child) !== 0 || $errors !== '') {
            throw new RuntimeException("$case failed: $output $errors");
        }
        echo $output;
    }
    exit;
}
$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/saldi-legacy-pages-' . bin2hex(random_bytes(6));
mkdir($fixture . '/lager', 0700, true);
mkdir($fixture . '/includes', 0700);
$pages = ['enheder.php', 'fuld_stykliste.php', 'opdater_kostpriser.php', 'vareimport.php', 'beholdningsliste.php'];
foreach ($pages as $file) {
    copy($root . '/lager/' . $file, $fixture . '/lager/' . $file);
}
copy($root . '/includes/legacyItemImport.php', $fixture . '/includes/legacyItemImport.php');
copy($root . '/includes/fuld_stykliste.php', $fixture . '/includes/fuld_stykliste.php');
file_put_contents($fixture . '/includes/connect.php', '<?php');
file_put_contents($fixture . '/includes/std_func.php', '<?php require_once ' . var_export($root . '/includes/std_func.php', true) . ';');
file_put_contents($fixture . '/includes/online.php', '<?php require_once __DIR__ . "/std_func.php"; $_SESSION["cost_update_csrf"] = $GLOBALS["fixtureToken"];');
$fixtureToken = bin2hex(random_bytes(32));
$font = ''; $top_bund = ''; $bgcolor2 = '#eee'; $charset = 'UTF-8';
$brugernavn = "Fixture's user"; $db = 'fixture'; $color = '#000'; $bgcolor = '#fff'; $bgcolor5 = '#ddd';
$writes = [];
function db_escape_string($value) { return str_replace("'", "''", (string)$value); }
function db_select($query, $source) {
    if ($query === 'select * from enheder order by betegnelse') {
        $rows = [['id'=>1, 'betegnelse'=>'stk', 'beskrivelse'=>'Pieces']];
    } elseif ($query === 'select * from materialer order by beskrivelse') {
        $rows = [['id'=>1, 'beskrivelse'=>'Steel', 'densitet'=>7.85], ['id'=>2, 'beskrivelse'=>'Water', 'densitet'=>1]];
    } elseif ($query === 'select vare_id, kostpris from vare_lev') {
        $rows = [['vare_id'=>1, 'kostpris'=>2.75], ['vare_id'=>2, 'kostpris'=>14.50]];
    } elseif (str_starts_with($query, 'select id from enheder where betegnelse = ')) {
        $rows = [];
    } elseif ($query === "select ansat_id from brugere where brugernavn = 'Fixture''s user'") {
        $rows = [['ansat_id'=>null]];
    } elseif ($query === "SELECT beskrivelse FROM grupper WHERE art='LG' AND kodenr=7") {
        $rows = [['beskrivelse'=>$GLOBALS['scenario'] === 'balance-latin1' ? iconv('UTF-8', 'ISO-8859-1', 'Fjernlager Ærø & <syd>') : 'Fixture warehouse']];
    } elseif ($query === "SELECT beskrivelse FROM grupper WHERE art='LG' AND kodenr=999") {
        $rows = [];
    } elseif ($query === "select * from varer where lukket != '1' order by varenr") {
        $rows = [['id'=>3, 'varenr'=>'OWNED-ITEM', 'beskrivelse'=>'Owned stock', 'salgspris'=>12.5, 'beholdning'=>null]];
    } elseif ($query === 'select * from batch_kob where vare_id=3 and rest>0 and lager=7') {
        $rows = [];
    } elseif ($query === 'select beholdning from lagerstatus where vare_id=3 and lager=7') {
        $rows = [['beholdning'=>4.5]];
    } else {
        throw new RuntimeException('Unexpected SQL: ' . $query);
    }
    return (object)['rows'=>$rows];
}
function db_fetch_array($result) { return array_shift($result->rows); }
function db_modify($query, $source) { $GLOBALS['writes'][] = $query; }
$_SERVER['REQUEST_METHOD'] = str_contains($scenario, 'post') || $scenario === 'cost-forged' ? 'POST' : 'GET';
$_GET = []; $_POST = [];
if ($scenario === 'units-post') {
    $_POST = ['enheder'=>'Gem', 'enh_betegnelse'=>[0=>'meter'], 'enh_beskrivelse'=>[0=>"Owner's unit"]];
} elseif ($scenario === 'materials-post') {
    $_POST = ['materialer'=>'Gem', 'mat_id'=>2, 'mat_beskrivelse'=>[2=>'Fresh water'], 'mat_densitet'=>[2=>'1,00']];
} elseif ($scenario === 'cost-post') {
    $_POST = ['csrf_token'=>$fixtureToken];
}
if ($scenario === 'balance-latin1') { $charset = 'ISO-8859-1'; }
if (in_array($scenario, ['balance-selected','balance-latin1'], true)) { $_GET = ['lager'=>7]; }
if ($scenario === 'balance-invalid') { $_GET = ['lager'=>999]; }
$page = str_starts_with($scenario, 'cost-') ? 'opdater_kostpriser.php' : (
    $scenario === 'bom-empty' ? 'fuld_stykliste.php' : ($scenario === 'import-get' ? 'vareimport.php' : 'enheder.php'));
if (str_starts_with($scenario, 'balance-')) { $page = 'beholdningsliste.php'; }
$originalDirectory = getcwd();
ob_start();
register_shutdown_function(function () use ($fixture, $pages, $scenario, $originalDirectory) {
    $last = error_get_last();
    $html = ob_get_clean();
    chdir($originalDirectory);
    foreach ($pages as $file) { unlink($fixture . '/lager/' . $file); }
    foreach (['connect.php','online.php','std_func.php','fuld_stykliste.php','legacyItemImport.php'] as $file) { unlink($fixture . '/includes/' . $file); }
    rmdir($fixture . '/lager'); rmdir($fixture . '/includes'); rmdir($fixture);
    if ($last && in_array($last['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])) { return; }
    if ($scenario === 'balance-latin1' && !str_contains($html, iconv('UTF-8', 'ISO-8859-1', 'Fjernlager Ærø &amp; &lt;syd&gt;'))) {
        throw new RuntimeException('Latin-1 warehouse name was lost or not escaped');
    }
    $writes = $GLOBALS['writes'];
    $expectedCount = in_array($scenario, ['units-post','materials-post']) ? 1 : ($scenario === 'cost-post' ? 2 : 0);
    if (count($writes) !== $expectedCount || !str_contains($html, '</html>')) {
        throw new RuntimeException("$scenario unexpected writes or incomplete rendering: " . json_encode($writes));
    }
    if ($scenario === 'cost-forged' && http_response_code() !== 403) {
        throw new RuntimeException('Forged cost update was not rejected');
    }
    if ($scenario === 'cost-post' && $writes !== ['update varer set kostpris = 2.75 where id = 1','update varer set kostpris = 14.5 where id = 2']) {
        throw new RuntimeException('Supplier cost update changed values or targets');
    }
    if ($scenario === 'units-post' && !str_contains($writes[0], "Owner''s unit")) {
        throw new RuntimeException('Unit description was not escaped');
    }
    if (str_starts_with($scenario, 'balance-') && (str_contains($html, 'logud.php') || !str_contains($html, 'Beholdningsliste'))) {
        throw new RuntimeException('Stock balance lost authentication or did not render');
    }
    if ($scenario === 'balance-selected' && (!str_contains($html, 'OWNED-ITEM') || !str_contains($html, '4,50') || !str_contains($html, '?lager=7&sort=beskrivelse'))) {
        throw new RuntimeException('Selected warehouse stock or sorting target changed');
    }
    if ($scenario === 'balance-unassigned' && !str_contains($html, 'ikke valgt eller tilknyttet')) {
        throw new RuntimeException('Missing assignment did not explain next action');
    }
    if ($scenario === 'balance-invalid' && !str_contains($html, 'valgte lager findes ikke')) {
        throw new RuntimeException('Unknown warehouse was silently accepted');
    }
    echo "PASS legacy stock page: $scenario\n";
});
chdir($fixture . '/lager');
include $fixture . '/lager/' . $page;
