<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_formularprint.php --- ver 5.0.0 --- 2026.10.02
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Validate the configured disposable tenant instead of requiring a fixed name prefix.

require_once __DIR__ . '/CharacterizationEnv.php';
$scenario = $argv[1];
$orderId = (int)$argv[2];
$db = $argv[3];
if ($db !== CharacterizationEnv::testDb() || !preg_match('/^[a-z0-9_]+$/D', $db)) {
    throw new RuntimeException('Disposable database required');
}
CharacterizationEnv::assertSafeDatabases();
chdir(CharacterizationEnv::repoRoot() . '/debitor');
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/saldi/debitor/formularprint.php';
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
$header = $bg = 'nix';
ob_start();
require __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/var2str.php';
$registry = db_fetch_array(db_select("SELECT id FROM regnskab WHERE db='" . db_escape_string($db) . "'", __FILE__));
$db_id = $db_skriv_id = (int)$registry['id'];
$connection = db_connect($sqhost, $squser, $sqpass, $db);
$regnaar = (int)db_fetch_array(db_select("SELECT max(kodenr) AS id FROM grupper WHERE art='RA'", __FILE__))['id'];
$brugernavn = 'chartest';
$bruger_id = 1;
$sprog_id = 0;
$charset = 'UTF-8';
$formularsprog = 'dansk';
$baseCurrency = 'DKK';
$webservice = 1;
$ansat_id = 0;
$deb_valuta = 'DKK';
$deb_valutakurs = 100;
$ps2pdf = '/usr/bin/ps2pdf';
$pdftk = '/usr/bin/pdftk';
$weasyprint = '';
$exec_path = '/usr/bin';
$returside = 'rapport.php';
if (!is_dir("../temp/$db/1")) {
    mkdir("../temp/$db/1", 0775, true);
}
require_once __DIR__ . '/../../../includes/formfunk.php';
$result = null;
register_shutdown_function(static function () use (&$result, $scenario): void {
    $output = ob_get_contents();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['result' => $result, 'output' => $output, 'scenario' => $scenario], JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
});
if (str_starts_with($scenario, 'ean_')) {
    $baseUrl = 'http://127.0.0.1:' . (int)$argv[4] . '/' . substr($scenario, 4);
    require_once __DIR__ . '/../../../includes/forfaldsdag.php';
    require_once __DIR__ . '/../../../includes/stdFunc/easyUblResponse.php';
    $apiKey = bin2hex(random_bytes(16));
    if (is_file(__DIR__ . '/../../../includes/easyUbl/easyUblInvoice.php')) {
        pg_query_params($connection, "UPDATE settings SET var_value=$1 WHERE var_name='base_url' AND var_grp='easyUBL'", [$baseUrl]);
        require_once __DIR__ . '/../../../includes/easyUbl/easyUblInvoice.php';
    } else {
        $source = file_get_contents(__DIR__ . '/../../../debitor/api.php');
        $source = substr($source, strpos($source, '    /**'));
        $source = preg_replace('~https://easyubl\.net~i', $baseUrl, $source);
        $source = str_replace('https://saldi.dk/locator/locator.php', $baseUrl . '/locator', $source);
        eval($source);
    }
    $result = sendInvoice($orderId, 'invoice');
} elseif (in_array($scenario, ['rykker_email', 'inkasso'], true)) {
    $row = db_fetch_array(db_select("SELECT konto_id FROM ordrer WHERE id=$orderId", __FILE__));
    $result = rykkerprint([(int)$row['konto_id']], [$orderId], 1, 1, 12, $regnaar, $scenario === 'inkasso' ? 1 : 0);
    if (is_array($result) && isset($result['redirect'])) {
        print '<meta http-equiv="refresh" content="0;URL=' . $result['redirect'] . '">';
        exit;
    }
} else {
    $formular = $scenario === 'tilbud_email' ? 1 : 4;
    $mode = $scenario === 'inkasso' ? 'inkasso' : ($scenario === 'web_print' ? 'PDF' : 'email');
    $result = formularprint($orderId, $formular, 0, $charset, $mode);
}
