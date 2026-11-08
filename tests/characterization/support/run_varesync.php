<?php
// tests/characterization/support/run_varesync.php
//
// Child-process runner for the shop catalog import, api/varesync.php.
// Indstillinger runs it as varesync(1) (Hent varer) or varesync(2) (Opdater fra
// shop) from systemdata/, through api_valg() in systemdata/sys_div_func.php.
//
// varesync() asks the shop to write shop_products.csv and shop_variants.csv
// (curl), then downloads them into temp/<db>/ with wget. This runner puts
// stand-ins for both first on PATH: curl does nothing, and wget copies the file
// its last argument names from the fixture directory into the current
// directory, as the download would. The variant file's --spider request calls
// /usr/bin/wget itself; it goes to the API URL the suite configures, which
// refuses the connection at once.
//
// argv: 1 = fixture directory holding shop_products.csv and shop_variants.csv
//       2 = valg (1 creates new items, 2 updates existing ones)
//       3 = tenant db (default saldi_chartest)
//
// Prints one JSON line on stdout:
//   output - what varesync() printed
//
// History:
// 20261105 CL/LH Created for VaresyncCatalogImportTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_varesync.php <fixture dir> <valg> [tenant_db]\n");
    exit(2);
}
$chartestFixtures = realpath($argv[1]);
$chartestValg = $argv[2];
$chartestTenantDb = $argv[3] ?? 'saldi_chartest';
if ($chartestFixtures === false) {
    fwrite(STDERR, "run_varesync.php: no fixture directory {$argv[1]}\n");
    exit(2);
}

// The legacy code's own warnings stay off stdout; an uncaught error still ends the process with exit 255.
error_reporting(E_ERROR | E_PARSE);

$_SERVER['REQUEST_URI'] = '/saldi/systemdata/diverse.php?sektion=api_valg&varesync=' . $chartestValg;
$_SERVER['PHP_SELF'] = '/saldi/systemdata/diverse.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

$chartestBin = sys_get_temp_dir() . '/chartest-varesync-' . getmypid();
if (!is_dir($chartestBin) && !mkdir($chartestBin, 0700)) {
    fwrite(STDERR, "run_varesync.php: cannot create $chartestBin\n");
    exit(2);
}
file_put_contents("$chartestBin/curl", "#!/bin/sh\nexit 0\n");
file_put_contents("$chartestBin/wget", "#!/bin/sh\nfor last; do :; done\ncp \"\$CHARTEST_SHOP_FILES/\$(basename \"\$last\")\" .\n");
chmod("$chartestBin/curl", 0700);
chmod("$chartestBin/wget", 0700);
putenv("PATH=$chartestBin:" . getenv('PATH'));
putenv("CHARTEST_SHOP_FILES=$chartestFixtures");

$chartestRoot = dirname(__DIR__, 3);
$chartestTemp = "$chartestRoot/temp/$chartestTenantDb";
$chartestOwnsTemp = !is_dir($chartestTemp);
if ($chartestOwnsTemp) {
    mkdir($chartestTemp, 0750, true);
}

// Also after an uncaught error or one of varesync()'s exit calls: the stand-ins go, and so does what
// varesync() leaves in temp/<db>/ (the whole directory when this run created it).
register_shutdown_function(static function () use ($chartestBin, $chartestTemp, $chartestOwnsTemp): void {
    foreach (['curl', 'wget'] as $tool) {
        if (file_exists("$chartestBin/$tool")) {
            unlink("$chartestBin/$tool");
        }
    }
    if (is_dir($chartestBin)) {
        rmdir($chartestBin);
    }
    // db_query.php's own logs (.ht_select.log and the like) are dotfiles, so not glob('*').
    $names = $chartestOwnsTemp
        ? array_diff(scandir($chartestTemp) ?: [], ['.', '..'])
        : ['shop_products.csv', 'shop_variants.csv', 'productList.txt', 'restApiVarer.log', 'varesync.log'];
    foreach ($names as $name) {
        if (is_file("$chartestTemp/$name")) {
            unlink("$chartestTemp/$name");
        }
    }
    if ($chartestOwnsTemp && is_dir($chartestTemp)) {
        rmdir($chartestTemp);
    }
});

chdir("$chartestRoot/systemdata"); // varesync() works in ../temp/<db>/, as it does from diverse.php

ob_start();
include __DIR__ . '/../../../includes/connect.php'; // connects to master, defines db_* + $sqhost/$squser/...
include __DIR__ . '/../../../includes/std_func.php';
$db = $chartestTenantDb;
$connection = db_connect($sqhost, $squser, $sqpass, $db);
$r = db_fetch_array(db_select("select max(kodenr) as kodenr from grupper where art = 'RA'", __FILE__ . " linje " . __LINE__));
$regnaar = (int)$r['kodenr'];
$brugernavn = 'chartest';
$bruger_id = 1;
$sprog_id = 0;
include __DIR__ . '/../../../api/varesync.php';
ob_end_clean();

ob_start();
varesync($chartestValg);
$chartestOutput = ob_get_clean();
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $chartestOutput . "\n");
}

fwrite(STDOUT, json_encode(['output' => $chartestOutput], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
