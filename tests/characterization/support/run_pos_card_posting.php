<?php
// tests/characterization/support/run_pos_card_posting.php
//
// Child-process runner for PosCardPostingCharacterizationTest (MB-49).
// It runs the calls settlePOS.php makes for a settled POS sale, levering() and then bogfor() (which runs bogfor_nu() when
// the register posts each sale), on the real ordrefunc.php against the caller-owned test tenant.
//
// argv: 1 = tenant database, 2 = ordre_id.
// Prints one JSON line: bogfor()'s answer, the order's status and report_number, and the order's transaktioner rows.
//
// History:
// 20261112 CL/SS MB-49: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_pos_card_posting.php <tenant_db> <ordre_id>\n");
    exit(2);
}
$tenantDb = $argv[1];
$ordreId = (int)$argv[2];
$tempDir = dirname(__DIR__, 3) . '/temp/' . $tenantDb;
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0700, true);
}
require __DIR__ . '/bootstrap_ordrefunc.php';
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ob_end_clean();

transaktion('begin');
$out = ['levering' => levering($ordreId, 'on', '', '')];
$out['bogfor'] = bogfor($ordreId, '');
if ($out['bogfor'] === 'OK') {
    transaktion('commit');
}
$r = db_fetch_array(db_select("select status, report_number from ordrer where id = '$ordreId'", __FILE__ . " linje " . __LINE__));
$out['status'] = (int)$r['status'];
$out['rows'] = [];
$q = db_select("select kontonr, debet, kredit from transaktioner where ordre_id = '$ordreId' order by id", __FILE__ . " linje " . __LINE__);
while ($t = db_fetch_array($q)) {
    $out['rows'][] = [(int)$t['kontonr'], round((float)$t['debet'], 2), round((float)$t['kredit'], 2)];
}
foreach (glob("$tempDir/*") ?: [] as $file) {
    unlink($file);
}
rmdir($tempDir);
echo json_encode($out), "\n";
