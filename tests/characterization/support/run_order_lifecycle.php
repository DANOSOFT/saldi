<?php
// tests/characterization/support/run_order_lifecycle.php
//
// Child-process runner for the parts of the order lifecycle that happen
// BEFORE (and after) invoicing: delivery, line creation, crediting.
//
// run_order_invoice.php drives the one sequence remoteBooking runs in
// production (deliver everything, then invoice). This runner exposes the
// individual steps so tests can stop halfway - a partial delivery, a line
// added to a booked order, a credit note against a delivered order.
//
// Scenarios (argv 1):
//   deliver  <ordre_id> <qty>   - set leveres=<qty> on every stock line, then
//                                 levering($id, NULL, NULL): a delivery note,
//                                 no invoice
//   addline  <ordre_id> <vare_id> <antal> <pris> <rabat>
//                               - opret_ordrelinje() the way debitor/ordre.php
//                                 does when a line is typed in
// A return is a `deliver` with a negative quantity - that is how the order
// page books goods back in on a DO order (ordrefunc.php:340), and it keeps
// this runner to the two entry points a human actually reaches.
//
// argv 2..n are scenario arguments; the LAST argument is the tenant db.
// Prints a single JSON object on the LAST line of stdout.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_order_lifecycle.php <scenario> <args...> <tenant_db>\n");
    exit(2);
}
$argv_all = $argv;
$scenario = $argv_all[1];
$tenantDb = array_pop($argv_all);   // last arg is always the tenant db
$args = array_slice($argv_all, 2);

require __DIR__ . '/bootstrap_ordrefunc.php';

$out = ['scenario' => $scenario];

/** Re-read the order header and its lines for the JSON result. */
$snapshot = function (int $ordreId) {
    $r = db_fetch_array(db_select("select status, fakturanr, sum, moms from ordrer where id = '$ordreId'", __FILE__ . " linje " . __LINE__));
    $lines = [];
    $q = db_select("select id, vare_id, antal, leveres, leveret, pris, rabat from ordrelinjer where ordre_id = '$ordreId' and vare_id > 0 order by posnr", __FILE__ . " linje " . __LINE__);
    while ($l = db_fetch_array($q)) {
        $lines[] = [
            'id' => (int)$l['id'],
            'vare_id' => (int)$l['vare_id'],
            'antal' => (float)$l['antal'],
            'leveres' => (float)$l['leveres'],
            'leveret' => (float)$l['leveret'],
            'pris' => (float)$l['pris'],
            'rabat' => (float)$l['rabat'],
        ];
    }
    return [
        'status' => $r['status'],
        'fakturanr' => $r['fakturanr'],
        'sum' => $r['sum'],
        'moms' => $r['moms'],
        'lines' => $lines,
    ];
};

if ($scenario === 'deliver') {
    $ordreId = (int)$args[0];
    $qty = (float)$args[1];
    transaktion('begin');
    db_modify("update ordrelinjer set leveres = '$qty' where ordre_id = '$ordreId' and vare_id > 0", __FILE__ . " linje " . __LINE__);
    $out['levering'] = levering($ordreId, NULL, NULL);
    transaktion('commit');
    $out += $snapshot($ordreId);
} elseif ($scenario === 'addline') {
    $ordreId = (int)$args[0];
    $vareId = (int)$args[1];
    $antal = (float)$args[2];
    $pris = (float)$args[3];
    $rabat = (float)($args[4] ?? 0);

    $r = db_fetch_array(db_select("select varenr, beskrivelse from varer where id = '$vareId'", __FILE__ . " linje " . __LINE__));
    $posnr = 0;
    if ($p = db_fetch_array(db_select("select coalesce(max(posnr),0)+1 as posnr from ordrelinjer where ordre_id = '$ordreId'", __FILE__ . " linje " . __LINE__))) {
        $posnr = (int)$p['posnr'];
    }
    transaktion('begin');
    $out['opret_ordrelinje'] = opret_ordrelinje(
        $ordreId, $vareId, $r['varenr'], $antal, $r['beskrivelse'], $pris, $rabat,
        100, 'DO', '', $posnr, 0, '', '', '', '', 0, 0, '', 0, 0
    );
    transaktion('commit');
    $out += $snapshot($ordreId);
} else {
    ob_end_clean();
    fwrite(STDERR, "unknown scenario $scenario\n");
    exit(2);
}

$v = db_fetch_array(db_select("select sum(beholdning) as beholdning from varer where varenr like 'chartest%'", __FILE__ . " linje " . __LINE__));
$out['chartest_stock_total'] = $v['beholdning'];

ob_end_clean();
fwrite(STDOUT, json_encode($out) . "\n");
