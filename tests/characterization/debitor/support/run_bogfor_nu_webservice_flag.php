<?php
// tests/characterization/debitor/support/run_bogfor_nu_webservice_flag.php
//
// Child-process runner for SalesInvoiceWebserviceFlagTest (D4).
// Supports invoking bogfor_nu($ordreId, $kilde) directly or bogfor($ordreId, $webservice)
// against a caller-owned tenant database and prints a JSON summary on the last line of stdout.
//
// History:
// 20261127 CL/HUG Created for D4 webservice-flag regression test.

if ($argc < 5) {
    fwrite(STDERR, "usage: php run_bogfor_nu_webservice_flag.php <bogfor_nu|bogfor> <ordre_id> <kilde> <tenant_db>\n");
    exit(2);
}

$mode = $argv[1];
$ordreId = (int)$argv[2];
$kilde = $argv[3];
$tenantDb = $argv[4];

require __DIR__ . '/../../support/bootstrap_ordrefunc.php';
error_reporting(E_ALL);

$tempDir = dirname(__DIR__, 4) . '/temp/' . $db;
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

if ($mode === 'bogfor_nu') {
    transaktion('begin');
    $svar = bogfor_nu($ordreId, $kilde);
    if ($svar === 'OK') {
        transaktion('commit');
    } else {
        transaktion('rollback');
    }
} elseif ($mode === 'bogfor') {
    $svar = bogfor($ordreId, $kilde);
} else {
    ob_end_clean();
    fwrite(STDERR, "unknown mode: $mode\n");
    exit(2);
}

$txRows = [];
$q = db_select("SELECT kontonr, debet, kredit FROM transaktioner WHERE ordre_id = '$ordreId' ORDER BY id", __FILE__ . ' linje ' . __LINE__);
while ($r = db_fetch_array($q)) {
    $txRows[] = [
        'kontonr' => (int)$r['kontonr'],
        'debet' => (float)$r['debet'],
        'kredit' => (float)$r['kredit'],
    ];
}
$opRow = db_fetch_array(db_select("SELECT count(*) AS cnt FROM openpost WHERE refnr = '$ordreId'", __FILE__ . ' linje ' . __LINE__));
$ordRow = db_fetch_array(db_select("SELECT status FROM ordrer WHERE id = '$ordreId'", __FILE__ . ' linje ' . __LINE__));

$out = [
    'svar' => $svar,
    'tx_count' => count($txRows),
    'tx_rows' => $txRows,
    'openpost_count' => $opRow ? (int)$opRow['cnt'] : 0,
    'status' => $ordRow ? (int)$ordRow['status'] : null,
    'regnaar' => (int)$regnaar,
];

ob_end_clean();
fwrite(STDOUT, json_encode($out) . "\n");
