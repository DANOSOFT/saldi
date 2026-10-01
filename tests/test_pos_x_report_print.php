<?php
// 20261001 MJ SST-825: the POS X-report must reach the print server, like the Z-report.

/**
 * Run with: php tests/test_pos_x_report_print.php
 *
 * debitor/pos_ordre_includes/report/xRapport.php and its zRapport.php sibling are
 * included by pos_ordre_includes/posTxtPrint/setTextVar.php, from inside
 * pos_txt_print(). That function declares `global $printserver` but never
 * `global $printpopup` and never assigns it, so inside the report files
 * $printpopup is an undefined local - always falsy. xRapport.php had its redirect
 * to saldiprint.php wrapped in `if ($printpopup)`, so on that path it emitted
 * nothing at all and then hit exit(): a blank page and no receipt. zRapport.php
 * prints the same redirect unconditionally, which is why Z worked and X did not.
 *
 * Both files are exercised in child processes because they call exit(), the same
 * way tests/test_pos_cash_checkout.php drives afslut(). The report builder, the
 * settings lookup and the receipt file are simulated; no database, credentials or
 * printer are needed.
 *
 * The scope is reproduced faithfully: the child calls a stand-in for
 * pos_txt_print() that declares exactly the globals the real one does, so
 * $printpopup is undefined inside the include just as it is in production.
 */

if (($argv[1] ?? '') === '--case') {
    $case    = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
    $fixture = $argv[3];
    chdir($fixture . '/debitor');
    error_reporting(E_ALL);
    ini_set('display_errors', 'stderr');

    $printserver_setting = $case['printer'] ?? 'printer.example';
    $_SERVER = array(
        'SERVER_NAME' => 'till.example',
        'PHP_SELF'    => '/saldi/debitor/pos_ordre.php',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTPS'       => $case['https'] ?? '',
    );
    $_COOKIE = isset($case['cookiePrinter'])
        ? array('saldi_printserver' => $case['cookiePrinter'])
        : array();

    // The report writer the real file calls before emitting the redirect.
    function printReportFunctions($fp, $firmanavn, $cvrnr, $orgNr, $date,
                                  $uniqueShopId, $reportArray, $type, $kasse) {
        fwrite($fp, "X-RAPPORT\nKasse $kasse\n");
    }

    $GLOBALS['printserver'] = $printserver_setting;
    $GLOBALS['db']          = 'sst825test';
    $GLOBALS['bruger_id']   = 7;
    $GLOBALS['kasse']       = 1;
    $GLOBALS['firmanavn']   = 'Testbutik';
    $GLOBALS['cvrnr']       = '12345678';
    $GLOBALS['regnaar']     = 1;

    /**
     * Stands in for pos_txt_print(). It declares the same globals the real function
     * declares - notably $printserver but NOT $printpopup - so the include below
     * runs in the scope the bug depends on.
     */
    function pos_txt_print_stub($type, $pfnavn, $id) {
        global $db, $db_encode, $db_id, $difkto;
        global $firmanavn, $cvrnr;
        global $kasse;
        global $postnr, $printserver;
        global $ref, $regnaar, $reportNumber;
        global $bruger_id;

        $orgNr        = '';
        $date         = '2026-10-01';
        $uniqueShopId = 'shop-1';
        $reportArray  = array();
        $kontonr      = 0;
        $betalingsbet = 'Kontant';
        $fakturanr    = 0;

        $fp = fopen($pfnavn, 'w');
        $filnavn = "pos_ordre_includes/report/$type.php";
        include($filnavn);
    }

    $receipt = $fixture . '/temp/receipt.txt';
    pos_txt_print_stub($case['type'], $receipt, 42);
    exit;
}

// ---------------------------------------------------------------------------

$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/saldi-sst825-' . bin2hex(random_bytes(8));
mkdir($fixture . '/debitor/pos_ordre_includes/report', 0700, true);
mkdir($fixture . '/temp', 0700);
foreach (array('xRapport.php', 'zRapport.php') as $file) {
    copy("$root/debitor/pos_ordre_includes/report/$file",
         "$fixture/debitor/pos_ordre_includes/report/$file");
}

$cases = array(
    // Both reports take the same path and must both reach the print server.
    'X-report, ordinary print server' => array('type' => 'xRapport'),
    'Z-report, ordinary print server' => array('type' => 'zRapport'),
    'X-report, android print server'  => array('type' => 'xRapport', 'printer' => 'android',
                                               'origin' => 'saldiprint://'),
    'Z-report, android print server'  => array('type' => 'zRapport', 'printer' => 'android',
                                               'origin' => 'saldiprint://'),
    'X-report over HTTPS'             => array('type' => 'xRapport', 'https' => 'on'),
    'X-report, printer from cookie'   => array('type' => 'xRapport', 'printer' => '',
                                               'cookiePrinter' => 'cookie-printer.example',
                                               'origin' => 'http://cookie-printer.example'),
);

$failed = 0;
try {
    foreach ($cases as $name => $case) {
        $process = proc_open(array(PHP_BINARY, __FILE__, '--case', json_encode($case), $fixture),
            array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        $html   = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        try {
            $check = static function ($condition, $message) {
                if (!$condition) {
                    throw new RuntimeException($message);
                }
            };
            $isX = $case['type'] === 'xRapport';

            $check($status === 0, "Child exited $status: $html$errors");

            // The symptom: nothing at all reached the browser, so the till showed a
            // blank page and the receipt never left the building.
            $check(trim($html) !== '', 'Nothing was sent to the browser - this is the blank page');
            $check(preg_match('/content="0;URL=([^"]+)"/', $html, $match) === 1,
                'No redirect to the print server');

            $url  = html_entity_decode($match[1]);
            $base = $case['origin'] ?? 'http://printer.example';
            $check(strpos($url, $base . '/saldiprint.php?') === 0,
                "Wrong print destination: $url");

            parse_str(explode('?', $url, 2)[1], $query);
            $scheme = (($case['https'] ?? '') === 'on') ? 'https' : 'http';
            $origin = "$scheme://till.example/saldi";
            $check($query['url'] === $origin, "Wrong application URL: {$query['url']}");
            $check($query['returside'] === $origin . '/debitor/pos_ordre.php',
                'Does not return to the till screen');
            $check($query['bruger_id'] === '7', 'Operator identity lost');
            $check($query['bon'] !== '', 'Receipt body was not sent to the printer');
            $check(strpos(urldecode($query['bon']), 'X-RAPPORT') !== false,
                'Receipt body is not the report that was just built');

            // An X-report reads the till; it must not open the drawer, and must not
            // ask the print server to store anything.
            if ($isX) {
                $check($query['skuffe'] === '0', 'X-report must not open the cash drawer');
                $check(!isset($query['gem']), 'X-report must not ask the print server to save');
            }

            $check($errors === '', "PHP diagnostics on the print path: $errors");
            echo "PASS  $name\n";
        } catch (Throwable $error) {
            $failed++;
            echo "FAIL  $name: {$error->getMessage()}\n";
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

echo $failed ? "\n$failed case(s) failed\n" : "\nall cases passed\n";
exit($failed ? 1 : 0);
