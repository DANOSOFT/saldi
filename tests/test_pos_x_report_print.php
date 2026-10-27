<?php
// 20261001 MJ SST-825: the POS X-report must reach the print server, like the Z-report.

/**
 * Run with: php tests/test_pos_x_report_print.php
 *
 * Drives the real pos_ordre_includes/posTxtPrint/setTextVar.php, which is the file
 * that decides whether to print at all and which report file to include. That is the
 * level worth testing: the deactivateBonprint gate lives there, and the report files
 * below it are reached only through it.
 *
 * report/xRapport.php had its redirect to saldiprint.php wrapped in
 * `if ($printpopup)`. setTextVar.php is included from inside pos_txt_print(), which
 * declares `global $printserver` but never `global $printpopup` and never assigns it,
 * so inside the report files $printpopup was an undefined local - always false. The
 * X-report therefore wrote nothing at all and hit exit(): a blank page in the till
 * and no receipt. report/zRapport.php prints the same redirect unconditionally, which
 * is why Z worked and X did not.
 *
 * Run in child processes because the report files call exit(), the same way
 * tests/test_pos_cash_checkout.php drives afslut(). The settings lookup, the report
 * writer and the receipt file are simulated; no database, credentials, printer or
 * network are needed.
 *
 * The scope is reproduced faithfully: the child calls a stand-in for pos_txt_print()
 * declaring exactly the globals the real one declares, so $printpopup is undefined
 * inside the include just as it is in production.
 */

if (($argv[1] ?? '') === '--case') {
    $case    = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
    $fixture = $argv[3];
    chdir($fixture . '/debitor');
    error_reporting(E_ALL);
    ini_set('display_errors', 'stderr');

    $_SERVER = array(
        'SERVER_NAME' => 'till.example',
        'PHP_SELF'    => '/saldi/debitor/pos_ordre.php',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTPS'       => $case['https'] ?? '',
    );
    $_COOKIE = isset($case['cookiePrinter'])
        ? array('saldi_printserver' => $case['cookiePrinter'])
        : array();
    $_SESSION = array();

    // --- simulated boundaries -------------------------------------------------

    $GLOBALS['deactivateBonprint'] = $case['deactivateBonprint'] ?? '';

    function db_select($qtxt, $where = '') { return $qtxt; }
    function db_fetch_array($q) {
        if (is_string($q) && strpos($q, 'deactivateBonprint') !== false) {
            return array('var_value' => $GLOBALS['deactivateBonprint']);
        }
        return array();
    }
    function dkdecimal($tal, $decimaler = 2) {
        return number_format((float) $tal, $decimaler, ',', '.');
    }
    function printWarningMessage($reason) { print "<!--warning:$reason-->"; }
    // The report writer the real report files call before emitting the redirect.
    function printReportFunctions($fp, $firmanavn, $cvrnr, $orgNr, $date,
                                  $uniqueShopId, $reportArray, $type, $kasse) {
        fwrite($fp, strtoupper($type) . "\nKasse $kasse\n");
    }

    $GLOBALS['printserver'] = $case['printer'] ?? 'printer.example';
    $GLOBALS['db']          = 'sst825test';
    $GLOBALS['db_id']       = 1;
    $GLOBALS['bruger_id']   = 7;
    $GLOBALS['kasse']       = 1;
    $GLOBALS['firmanavn']   = 'Testbutik';
    $GLOBALS['cvrnr']       = '12345678';
    $GLOBALS['regnaar']     = 1;

    /**
     * Stands in for pos_txt_print(). Declares the same globals the real function
     * declares - notably $printserver but NOT $printpopup - so the include below runs
     * in the scope the bug depends on.
     */
    function pos_txt_print_stub($case, $pfnavn) {
        global $db, $db_id, $firmanavn, $cvrnr, $kasse, $printserver, $regnaar, $bruger_id;

        $type = $case['type'];
        $id   = 42;

        // Values setTextVar.php computes over; a plain cash sale.
        $sum = 100; $moms = 25; $betalt = 125; $modtaget = 125; $modtaget2 = 0;
        $betaling = 'Kontant'; $betaling2 = ''; $indbetaling = 0; $retur = 0;
        $konto_id = 0; $x = 0; $rvnr = 0; $samlet_pris = 0;
        $kontonr = 0; $betalingsbet = 'Kontant';
        $fakturanr = $case['fakturanr'] ?? 0;
        if (isset($case['doNotPrint'])) {
            $doNotPrint = $case['doNotPrint'];
        }

        // Values the report files need.
        $orgNr = ''; $date = '2026-10-01'; $uniqueShopId = 'shop-1'; $reportArray = array();

        $fp = fopen($pfnavn, 'w');
        include('pos_ordre_includes/posTxtPrint/setTextVar.php');
        // Only reached when the gate above decided not to print; the report files exit().
        print "<!--returned-without-printing-->";
    }

    pos_txt_print_stub($case, $fixture . '/temp/receipt.txt');
    exit;
}

// ---------------------------------------------------------------------------

$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/saldi-sst825-' . bin2hex(random_bytes(8));
mkdir($fixture . '/debitor/pos_ordre_includes/report', 0700, true);
mkdir($fixture . '/debitor/pos_ordre_includes/posTxtPrint', 0700, true);
mkdir($fixture . '/temp', 0700);
copy("$root/debitor/pos_ordre_includes/posTxtPrint/setTextVar.php",
     "$fixture/debitor/pos_ordre_includes/posTxtPrint/setTextVar.php");
foreach (array('xRapport.php', 'zRapport.php') as $file) {
    copy("$root/debitor/pos_ordre_includes/report/$file",
         "$fixture/debitor/pos_ordre_includes/report/$file");
}

// 'prints' => false means the gate must stop before any report file runs.
$cases = array(
    'X-report, ordinary print server'  => array('type' => 'xRapport'),
    'Z-report, ordinary print server'  => array('type' => 'zRapport'),
    'X-report, android print server'   => array('type' => 'xRapport', 'printer' => 'android',
                                                'origin' => 'saldiprint://'),
    'Z-report, android print server'   => array('type' => 'zRapport', 'printer' => 'android',
                                                'origin' => 'saldiprint://'),
    'X-report over HTTPS'              => array('type' => 'xRapport', 'https' => 'on'),
    'X-report, printer from cookie'    => array('type' => 'xRapport', 'printer' => '',
                                                'cookiePrinter' => 'cookie-printer.example',
                                                'origin' => 'http://cookie-printer.example'),
    'X-report, localhost print server' => array('type' => 'xRapport', 'printer' => 'localhost',
                                                'origin' => 'http://localhost'),
    // The gate above the report files must still be able to switch printing off.
    'X-report, bonprint deactivated'   => array('type' => 'xRapport',
                                                'deactivateBonprint' => 'on', 'prints' => false),
    'Z-report, bonprint deactivated'   => array('type' => 'zRapport',
                                                'deactivateBonprint' => 'on', 'prints' => false),
    'X-report, already copied'         => array('type' => 'xRapport',
                                                'doNotPrint' => 'copied', 'prints' => false),
    // Z opens the drawer on an invoiced sale; X never does. Pins the difference.
    'Z-report with invoice number'     => array('type' => 'zRapport', 'fakturanr' => 9001,
                                                'drawer' => '1'),
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
            $check($errors === '', "PHP diagnostics on the print path: $errors");

            if (($case['prints'] ?? true) === false) {
                // Printing is switched off: no redirect, and control returns to the
                // caller rather than the report file exiting.
                $check(strpos($html, 'saldiprint') === false,
                    'Printing is switched off but the print server was still called');
                $check(strpos($html, '<!--returned-without-printing-->') !== false,
                    'Gate did not return control to pos_txt_print()');
                if (isset($case['doNotPrint'])) {
                    $check(strpos($html, '<!--warning:copied-->') !== false,
                        'No warning shown for an already-copied receipt');
                }
                echo "PASS  $name\n";
                continue;
            }

            // The symptom: nothing at all reached the browser, so the till showed a
            // blank page and the receipt never left the building.
            $check(trim($html) !== '', 'Nothing was sent to the browser - this is the blank page');
            $check(preg_match('/content="0;URL=([^"]+)"/', $html, $match) === 1,
                'No redirect to the print server');

            $url  = html_entity_decode($match[1]);
            $base = $case['origin'] ?? 'http://printer.example';
            $check(strpos($url, $base . '/saldiprint.php?') === 0, "Wrong print destination: $url");

            parse_str(explode('?', $url, 2)[1], $query);
            $scheme = (($case['https'] ?? '') === 'on') ? 'https' : 'http';
            $origin = "$scheme://till.example/saldi";
            $check($query['url'] === $origin, "Wrong application URL: {$query['url']}");
            $check($query['returside'] === $origin . '/debitor/pos_ordre.php',
                'Does not return to the till screen');
            $check($query['bruger_id'] === '7', 'Operator identity lost');
            $check($query['bon'] !== '', 'Receipt body was not sent to the printer');
            $check(strpos(urldecode($query['bon']), strtoupper($case['type'])) !== false,
                'Receipt body is not the report that was just built');
            $check($query['skuffe'] === ($case['drawer'] ?? '0'),
                "Wrong cash-drawer flag: {$query['skuffe']}");

            // An X-report reads the till; it must never bank anything.
            if ($isX) {
                $check($query['skuffe'] === '0', 'X-report must not open the cash drawer');
                $check(!isset($query['gem']), 'X-report must not ask the print server to save');
            }

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
