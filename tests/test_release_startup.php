<?php
// 20260920 CDX/LUI Cover PHP 8 endpoint loading, order costs, and empty stock reports.
// Run: php tests/test_release_startup.php (no database or shared stack required).
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$root = dirname(__DIR__);
$mode = $argv[1] ?? null;

if ($mode === 'endpoint') {
    // Load the actual class declarations; isolate installation/bootstrap I/O.
    // JWT and logging are the only collaborators replaced. No DB is reached.
    class JWTAuth {
        public static function validateToken() { return false; }
        public static function getTenantDatabase() { return false; }
    }
    function getallheaders() { return ['Host' => 'localhost']; }
    function write_log($text, $db, $level = 'INFO') {}
    $base = file_get_contents($root . '/restapi/core/BaseEndpoint.php');
    eval(substr($base, strpos($base, 'abstract class BaseEndpoint')));
    $source = file_get_contents($root . '/restapi/endpoints/v1/' . $argv[2]);
    $start = strpos($source, 'class ');
    eval(substr($source, $start, strpos($source, '$endpoint = new ') - $start));
    $class = $argv[3];
    foreach (['db', 'userId'] as $property) {
        $reflection = new ReflectionProperty($class, $property);
        if (!$reflection->isProtected() || $reflection->getDeclaringClass()->getName() !== 'BaseEndpoint') {
            throw new RuntimeException("$class does not inherit $property");
        }
    }
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/startup-test';
    (new $class())->handleRequestMethod();
    throw new RuntimeException('Unauthenticated request was not rejected');
}

if ($mode === 'order') {
    require_once $root . '/restapi/models/orders/OrderModel.php';
    function db_select($query, $source) { return true; }
    function db_num_rows($query) { return 1; }
    function db_fetch_array($query) { return $GLOBALS['orderFixture']; }
    function db_escape_string($value) { return addslashes((string)$value); }
    $columns = 'id konto_id firmanavn tlf email momssats addr1 addr2 postnr bynavn land lev_navn lev_addr1 lev_addr2 lev_postnr lev_bynavn lev_land ean cvrnr ordredate notes betalt sum kostpris moms valuta betalingsbet betalingsdage kontonr reference status ordrenr valutakurs fakturadate fakturanr';
    $orderFixture = array_fill_keys(explode(' ', $columns), '');
    foreach (['0.000', '12.345', '-15.250'] as $cost) {
        $orderFixture['kostpris'] = $cost;
        $result = (new OrderModel(7, 'DO'))->toArray();
        if ($result['economic']['costPrice'] !== (float)$cost) {
            throw new RuntimeException('API order cost does not match stored kostpris');
        }
    }
    echo "PASS order costs: zero, fractional and credit\n";
    exit;
}

if ($mode === 'stock') {
    $fixture = sys_get_temp_dir() . '/saldi-stock-' . bin2hex(random_bytes(6));
    mkdir($fixture . '/lager', 0700, true);
    mkdir($fixture . '/includes', 0700);
    mkdir($fixture . '/temp/fixture', 0700, true);
    copy($root . '/lager/minmaxstock.php', $fixture . '/lager/minmaxstock.php');
    file_put_contents($fixture . '/includes/connect.php', '<?php');
    file_put_contents($fixture . '/includes/online.php', '<?php');
    file_put_contents($fixture . '/includes/std_func.php', '<?php require_once ' . var_export($root . '/includes/std_func.php', true) . ';');
    function db_select($query, $source) {
        $group = [['kodenr' => '1', 'beskrivelse' => 'Stock']];
        $item = [['id'=>1, 'varenr'=>'ITEM', 'beskrivelse'=>'Sample item', 'min_lager'=>3, 'max_lager'=>8, 'gruppe'=>'1']];
        $stock = [['vare_id'=>1, 'lok1'=>'A', 'variant_id'=>0, 'lager'=>'1', 'beholdning'=>2]];
        $scenario = $GLOBALS['argv'][2];
        if (strpos($query, "art = 'VG'") !== false) {
            $rows = $scenario === 'no-groups' ? [] : $group;
        } elseif (strpos($query, "art = 'LG'") !== false) {
            $rows = $scenario === 'no-locations' ? [] : $group;
        } elseif (strpos($query, 'from varer ') !== false) {
            $rows = $scenario === 'no-items' ? [] : $item;
        } elseif (strpos($query, 'from lagerstatus ') !== false) {
            $rows = $scenario === 'no-stock' ? [] : $stock;
        } else {
            throw new RuntimeException('Unexpected stock report query: ' . $query);
        }
        return (object)['rows'=>$rows];
    }
    function db_fetch_array($result) { return array_shift($result->rows); }
    $popup = false; $menu = ''; $top_bund = ''; $db = 'fixture'; $bruger_id = 1;
    $bgcolor2 = '#eee'; $bgcolor = '#fff';
    $original = getcwd();
    try {
        chdir($fixture . '/lager');
        ob_start();
        include $fixture . '/lager/minmaxstock.php';
        $html = ob_get_clean();
        $csv = file_get_contents($fixture . '/temp/fixture/minmax1.csv');
        if (strpos($html, 'Beholdning') === false || strpos($csv, 'Afd;Varenr;') !== 0) {
            throw new RuntimeException('Stock report did not render HTML and CSV');
        }
        if (($argv[2] === 'populated') !== (strpos($csv, 'Sample item;2,00;3,00;8,00;6,00') !== false)) {
            throw new RuntimeException('Incorrect stock report rows: ' . $csv);
        }
    } finally {
        chdir($original);
        foreach (['lager/minmaxstock.php','includes/connect.php','includes/online.php','includes/std_func.php','temp/fixture/minmax1.csv'] as $file) {
            if (file_exists($fixture . '/' . $file)) {
                unlink($fixture . '/' . $file);
            }
        }
        foreach (['lager','includes','temp/fixture','temp',''] as $directory) {
            rmdir($fixture . '/' . $directory);
        }
    }
    echo "PASS stock report: " . $argv[2] . "\n";
    exit;
}

/** @return string Captured output from an isolated PHP test process. */
function runReleaseStartupChild(array $arguments): string
{
    $pipes = [];
    $process = proc_open(array_merge([PHP_BINARY, __FILE__], $arguments), [1=>['pipe','w'],2=>['pipe','w']], $pipes);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0 || $errors !== '') {
        throw new RuntimeException(implode(' ', $arguments) . " failed: $output $errors");
    }
    return $output;
}
foreach ([
    ['dashboard/stats.php', 'DashboardStatsEndpoint'],
    ['notifications/register.php', 'NotificationsRegisterEndpoint'],
    ['vat-codes/index.php', 'VatCodesEndpoint'],
] as [$file, $class]) {
    $result = json_decode(runReleaseStartupChild(['endpoint', $file, $class]), true, 512, JSON_THROW_ON_ERROR);
    if ($result['success'] !== false || $result['data'] !== null || $result['message'] === '') {
        throw new RuntimeException("Incorrect unauthorized response from $file");
    }
    echo "PASS inherited endpoint startup and unauthorized JSON: $file\n";
}
echo runReleaseStartupChild(['order']);
foreach (['no-groups', 'no-items', 'no-locations', 'no-stock', 'populated'] as $scenario) {
    echo runReleaseStartupChild(['stock', $scenario]);
}
