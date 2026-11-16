<?php
// tests/characterization/support/run_pos_card_posting.php
//
// Child-process runner for PosCardPostingCharacterizationTest (MB-49).
// It runs the calls settlePOS.php makes for a settled POS sale, levering() and then bogfor() (which runs bogfor_nu() when
// the register posts each sale), on the real ordrefunc.php against the caller-owned test tenant.
//
// argv: 1 = tenant database, 2 = ordre_id, or a comma list of ordre_ids that the day-end count posts together.
// Prints one JSON line: bogfor()'s answer, the order's status and report_number, and the order's transaktioner rows.
// For a comma list, bogfor() only finishes each sale (status 3) and bogfor_nu() posts them as posbogfor() does,
// so 'bogfor' is bogfor_nu()'s answer and 'rows' are the rows it wrote.
//
// History:
// 20261112 CL/SS MB-49: created.
// 20261116 CL/SS MB-49: a comma list posts the sales together, as the day-end count does.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_pos_card_posting.php <tenant_db> <ordre_id>\n");
    exit(2);
}
$tenantDb = $argv[1];
$ordreIds = array_map('intval', explode(',', $argv[2]));
$ordreId = $ordreIds[0];
$tempDir = dirname(__DIR__, 3) . '/temp/' . $tenantDb;
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0700, true);
}
require __DIR__ . '/bootstrap_ordrefunc.php';
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ob_end_clean();

$before = (int)db_fetch_array(db_select("select coalesce(max(id), 0) as id from transaktioner", __FILE__ . " linje " . __LINE__))['id'];
transaktion('begin');
foreach ($ordreIds as $oid) {
    $out = ['levering' => levering($oid, 'on', '', '')];
    $out['bogfor'] = bogfor($oid, '');
}
if (count($ordreIds) > 1 && $out['bogfor'] === 'OK') {
    $out['bogfor'] = bogfor_nu(implode(',', $ordreIds), 'Dagsafslutning');
}
if ($out['bogfor'] === 'OK') {
    transaktion('commit');
}
$r = db_fetch_array(db_select("select status, report_number from ordrer where id = '$ordreId'", __FILE__ . " linje " . __LINE__));
$out['status'] = (int)$r['status'];
$out['rows'] = [];
$where = count($ordreIds) > 1 ? "id > '$before'" : "ordre_id = '$ordreId'";
$q = db_select("select kontonr, debet, kredit from transaktioner where $where order by id", __FILE__ . " linje " . __LINE__);
while ($t = db_fetch_array($q)) {
    $out['rows'][] = [(int)$t['kontonr'], round((float)$t['debet'], 2), round((float)$t['kredit'], 2)];
}
foreach (glob("$tempDir/*") ?: [] as $file) {
    unlink($file);
}
rmdir($tempDir);
echo json_encode($out), "\n";
