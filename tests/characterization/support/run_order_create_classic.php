<?php
// tests/characterization/support/run_order_create_classic.php
//
// Child-process runner for the classic UI order-creation path (SD-600):
// includes/ordrefunc.php::opret_ordre($sag_id, $konto_id).
//
// opret_ordre() re-reads $sag_id/$konto_id/$tilbud_id from $_GET itself
// (ordrefunc.php:4784-4786), ignoring the values passed as arguments, so the
// caller must set $_GET before calling it - this mirrors debitor/ordre.php
// ?funktion=opret_ordre&sag_id=..&konto_id=.. (the only production entry
// point, always reached from sager/sager.php:2106/2108 with a real sag_id).
//
// Scenario (argv 1):
//   quote <sag_id> <konto_id> [tenant_db] - create a quote/tilbud for an
//     existing case, the only reachable production path.
//
// Prints a single JSON object on the LAST line of stdout.
//
// History:
// 20260805 CL/SZ SD-600: created.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_order_create_classic.php quote <sag_id> <konto_id> [tenant_db]\n");
    exit(2);
}
$scenario = $argv[1];
$sagId = (int)$argv[2];
$kontoId = (int)$argv[3];
$tenantDb = $argv[4] ?? 'saldi_chartest';

if ($scenario !== 'quote') {
    fwrite(STDERR, "unknown scenario $scenario\n");
    exit(2);
}

$_GET['sag_id'] = $sagId;
$_GET['konto_id'] = $kontoId;
$_GET['tilbud_id'] = '';

require __DIR__ . '/bootstrap_ordrefunc.php';

$default_procenttillag = 0;

$id = opret_ordre($sagId, $kontoId);

$id = (int)$id;
$out = ['scenario' => $scenario, 'id' => $id];
if ($id) {
    $r = db_fetch_array(db_select("select * from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
    $out['ordrenr'] = (int)$r['ordrenr'];
    $out['konto_id'] = (int)$r['konto_id'];
    $out['kontonr'] = $r['kontonr'];
    $out['firmanavn'] = $r['firmanavn'];
    $out['art'] = $r['art'];
    $out['status'] = $r['status'];
    $out['sag_id'] = (int)$r['sag_id'];
    $out['tilbudnr'] = $r['tilbudnr'];
    $out['nr'] = $r['nr'];
}

ob_end_clean();
fwrite(STDOUT, json_encode($out) . "\n");
