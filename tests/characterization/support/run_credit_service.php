<?php
// tests/characterization/support/run_credit_service.php
//
// Child-process runner for includes/orderFuncIncludes/creditService.php (SD-833).
//
// Scenarios (argv 1):
//   remaining <orig_id> <except_credit_id> [copied] - credit_remaining() inside a transaction; copied=1 counts only
//                                          the lines the Kredit copy takes
//   choice <line_id> <dekort_pct> <restock 0|1> - credit_apply_line_choice()
//   hold <credit_id>                     - credit_hold_to_remaining()
//   raise_and_hold <credit_id> <qty> <sleep_ms> - as debitor/ordre.php saves a draft: set every credit line to
//                                          -<qty>, hold the note to what is left, wait, then commit
//   left <orig_id>                       - credit_left_on_invoice()
//   hold_and_deliver <credit_id>         - hold the note, then levering() with the leveres the lines carry
//
// The LAST argument is the tenant db. Prints a single JSON object on the LAST line of stdout.
//
// History:
// 20261118 CL/SS SD-833 Created.
// 20261121 CL/SS SD-833 review: scenarios for what is left on an invoice and for a delivery after the hold.
// 20261123 CL/SS SD-833 review: remaining can count only the lines the Kredit copy takes.

if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 3) {
    fwrite(STDERR, "usage: php run_credit_service.php <scenario> <args...> <tenant_db>\n");
    exit(2);
}
$argv_all = $argv;
$scenario = $argv_all[1];
$tenantDb = array_pop($argv_all);
$args = array_slice($argv_all, 2);

require __DIR__ . '/bootstrap_ordrefunc.php';
require_once __DIR__ . '/../../../includes/orderFuncIncludes/creditService.php';

$out = ['scenario' => $scenario];
if ($scenario === 'remaining') {
    transaktion('begin');
    $out['remaining'] = credit_remaining((int)$args[0], (int)($args[1] ?? 0), true, (bool)(int)($args[2] ?? 0));
    transaktion('commit');
} elseif ($scenario === 'choice') {
    transaktion('begin');
    credit_apply_line_choice((int)$args[0], (float)$args[1], (bool)(int)$args[2]);
    transaktion('commit');
} elseif ($scenario === 'hold') {
    transaktion('begin');
    $out['changed'] = credit_hold_to_remaining((int)$args[0]);
    transaktion('commit');
} elseif ($scenario === 'raise_and_hold') {
    $creditId = (int)$args[0];
    $qty = (float)$args[1];
    transaktion('begin');
    db_modify("update ordrelinjer set antal = '" . (-$qty) . "' where ordre_id = '$creditId' and kred_linje_id > 0", __FILE__ . " linje " . __LINE__);
    $out['changed'] = credit_hold_to_remaining($creditId);
    usleep((int)$args[2] * 1000);
    transaktion('commit');
} elseif ($scenario === 'left') {
    $out['left'] = credit_left_on_invoice((int)$args[0]);
} elseif ($scenario === 'hold_and_deliver') {
    transaktion('begin');
    $out['changed'] = credit_hold_to_remaining((int)$args[0]);
    $out['levering'] = levering((int)$args[0], NULL, NULL);
    transaktion('commit');
} else {
    ob_end_clean();
    fwrite(STDERR, "unknown scenario $scenario\n");
    exit(2);
}

ob_end_clean();
fwrite(STDOUT, json_encode($out) . "\n");
