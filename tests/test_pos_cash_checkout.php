<?php
// 20260914 CDX/LH Regress MB-48: completed cash sales must leave the change screen.

/**
 * Run with: php tests/test_pos_cash_checkout.php
 *
 * Exercises the real afslut() in child processes because it calls exit(). Database,
 * voucher, settlement and receipt-printing boundaries are simulated; no database,
 * credentials or printer are needed. Settlement must commit before navigation.
 */

if (($argv[1] ?? '') === '--case') {
    $case = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
    $fixture = $argv[3];
    chdir($fixture . '/debitor');
    error_reporting(E_ALL);
    ini_set('display_errors', 'stderr');
    $events = [];
    $afd = 0;
    $bruger_id = 7;
    $brugernavn = 'mb48test';
    $db = 'mb48test';
    $indbetaling = 0;
    $kasse = 1;
    $momssats = 25;
    $regnaar = 1;
    $retur = 75;
    $tracelog = null;
    $_SERVER = [
        'SERVER_NAME' => 'checkout.example',
        'PHP_SELF' => '/saldi/debitor/pos_ordre.php',
        'REMOTE_ADDR' => '127.0.0.1',
    ];
    if (isset($case['https'])) {
        $_SERVER['HTTPS'] = $case['https'];
    }
    $_COOKIE = isset($case['cookiePrinter']) ? ['saldi_printserver' => $case['cookiePrinter']] : [];

    function posvaluta($amount) { return "$amount\t$amount\tDKK\t100"; }
    function betaling(...$args) { return 20; }
    function pos_afrund($amount, ...$args) { return round($amount, 2); }
    function afrund($amount, $decimals) { return round($amount, $decimals); }
    function db_select($sql, ...$args) {
        global $case;
        if (str_contains($sql, 'from ordrer')) {
            return new ArrayIterator([[
                'sum' => 20, 'moms' => 5, 'konto_id' => $case['account'] ?? 0,
                'status' => 0, 'fakturanr' => 0, 'momssats' => 25, 'betalingsbet' => 'Kontant',
            ]]);
        }
        if (str_contains($sql, 'from pos_betalinger')) {
            return new ArrayIterator([]);
        }
        if (str_contains($sql, 'select box3,box4,box5,box6')) {
            return new ArrayIterator([[
                'box3' => $case['printer'] ?? 'printer.example',
                'box4' => '', 'box5' => '', 'box6' => '',
            ]]);
        }
        if (str_contains($sql, "art='POSBUT'")) {
            return new ArrayIterator([['box9' => '']]);
        }
        if (str_contains($sql, 'box10')) {
            return new ArrayIterator(!empty($case['autoPrint']) ? [['id' => 1]] : []);
        }
        throw new RuntimeException('Unexpected query: ' . $sql);
    }
    function db_fetch_array($rows) {
        if (!$rows->valid()) {
            return false;
        }
        $row = $rows->current();
        $rows->next();
        return $row;
    }
    function db_modify($sql, ...$args) {
        if (!str_starts_with($sql, 'insert into pos_events')) {
            throw new RuntimeException('Unexpected write: ' . $sql);
        }
    }
    function transaktion($action) { $GLOBALS['events'][] = $action; }
    function pos_txt_print(...$args) {
        $GLOBALS['events'][] = 'receipt';
        exit;
    }

    ob_start();
    register_shutdown_function(function () use (&$events) {
        echo json_encode([
            'html' => ob_get_clean(), 'events' => $events,
            'serverName' => $_SERVER['SERVER_NAME'],
        ], JSON_THROW_ON_ERROR);
    });
    require __DIR__ . '/../debitor/pos_ordre_includes/exitFunc/exit.php';
    afslut(42, $case['payment'] ?? 'Kontant', $case['payment2'] ?? '',
        $case['received'] ?? 100, $case['received2'] ?? 0, 0, '', '');
    exit;
}

$fixture = sys_get_temp_dir() . '/saldi-mb48-' . bin2hex(random_bytes(8));
mkdir($fixture . '/debitor/pos_ordre_includes/voucherFunc', 0700, true);
mkdir($fixture . '/temp', 0700);
file_put_contents($fixture . '/debitor/pos_ordre_includes/voucherFunc/voucherPay.php',
    '<?php function voucherPay(...$args) {}');
file_put_contents($fixture . '/debitor/settlePOS.php',
    '<?php $GLOBALS["events"][] = "settled"; $svar = "OK"; $sum = 20; $moms = 5;');

$cases = [
    'cash with change' => [],
    'exact cash' => ['received' => 25],
    'split card and cash' => ['payment' => 'Betalingskort', 'payment2' => 'Kontant', 'received' => 10, 'received2' => 90],
    'card with change' => ['payment' => 'Betalingskort'],
    'exact card' => ['payment' => 'Betalingskort', 'received' => 25, 'drawer' => false],
    'account customer' => ['account' => 9, 'drawer' => false],
    'automatic receipts' => ['autoPrint' => true],
    'Android printer' => ['printer' => 'android'],
    'cookie printer fallback' => ['printer' => '', 'cookiePrinter' => 'cookie-printer.example'],
    'localhost printer fallback' => ['printer' => ''],
    'HTTPS checkout' => ['https' => 'on'],
    'explicit HTTP checkout' => ['https' => 'off'],
];
$failed = 0;
try {
    foreach ($cases as $name => $case) {
        $process = proc_open([PHP_BINARY, __FILE__, '--case', json_encode($case), $fixture],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        $result = json_decode($output, true);
        try {
            $check = static function ($condition, $message) {
                if (!$condition) {
                    throw new RuntimeException($message);
                }
            };
            $check($status === 0 && is_array($result), 'Child failed: ' . $output . $errors);
            $check(array_slice($result['events'], 0, 2) === ['settled', 'commit'], 'Sale did not commit');
            if (!empty($case['autoPrint'])) {
                $check(in_array('receipt', $result['events'], true), 'Receipt printing was bypassed');
            } else {
                $check(preg_match('/content="0;URL=([^"]+)"/', $result['html'], $match) === 1,
                    'No redirect after settlement');
                $url = html_entity_decode($match[1]);
                if ($case['drawer'] ?? true) {
                    $printer = ($case['printer'] ?? 'printer.example') ?: ($case['cookiePrinter'] ?? 'localhost');
                    $base = $printer === 'android' ? 'saldiprint://' : 'http://' . $printer;
                    $check(str_starts_with($url, $base . '/saldiprint.php?'), 'Wrong printer destination');
                    parse_str(explode('?', $url, 2)[1], $query);
                    $origin = (($case['https'] ?? '') === 'on' ? 'https' : 'http') . '://checkout.example/saldi';
                    $check($query['url'] === $origin, 'Wrong application URL');
                    $check($query['returside'] === $origin . '/debitor/pos_ordre.php', 'No return to next sale');
                    $check($query['skuffe'] === '1' && $query['bon'] === '', 'Must open drawer without printing a receipt');
                    $check($query['id'] === '42' && $query['bruger_id'] === '7', 'Checkout identity lost');
                } else {
                    $check($url === 'pos_ordre.php?id=42', 'Non-drawer navigation changed');
                }
            }
            $check($result['serverName'] === 'checkout.example', 'Checkout mutated SERVER_NAME');
            $check($errors === '', 'PHP warnings: ' . $errors);
            echo "PASS $name\n";
        } catch (Throwable $error) {
            $failed++;
            echo "FAIL $name: {$error->getMessage()}\n";
        }
    }
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,
        FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($fixture);
}
exit($failed ? 1 : 0);
