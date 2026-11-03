<?php
// tests/characterization/support/run_order_lines.php
//
// Child-process runner for the order-line and set functions (SD-789): opret_ordrelinje() in its
// three copies, opret_saet() and gendan_saet().
//
// includes/ordrefunc.php is loaded with bootstrap_ordrefunc.php. The copies of opret_ordrelinje()
// in mysale/api.php and rental/rental.php sit in page scripts that cannot be included, so each is
// lifted from the shipped file and defined as opret_ordrelinje_mysale() / opret_ordrelinje_rental().
//
//   php run_order_lines.php <spec json file> <tenant_db>
// Spec: {"regnaar": int, "globals": {name: value}, "steps": [...]}
// Steps: ["line", copy, <the arguments>]   copy: ordrefunc | mysale | rental
//        ["saet", <the arguments of opret_saet()>]
//        ["gendan", ordre_id]
//        ["validate", bom, plan, qty]   validate_saet_lager_plan()
//        ["reread", ordre_id, before_ids]   shopify_reread_new_lines()
// Prints one JSON object on the last line of stdout: one result per step, or the exception a step threw.
//
// History:
// 20261102 CL/SS SD-789: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_order_lines.php <spec json file> <tenant_db>\n");
    exit(2);
}
$spec = json_decode((string)file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$tenantDb = $argv[2];

require __DIR__ . '/bootstrap_ordrefunc.php';
require_once __DIR__ . '/../debitor/support/pos_lift.php';
require_once dirname(__DIR__, 3) . '/includes/shopify/services/orderLines.php';

@mkdir("../temp/$db", 0777, true); // opret_ordrelinje() appends to ../temp/<db>/momslog.log

$repo = dirname(__DIR__, 3);
foreach (['mysale' => 'mysale/api.php', 'rental' => 'rental/rental.php'] as $copy => $file) {
    $code = pos_lift("$repo/$file", ['opret_ordrelinje']);
    $code = preg_replace('/function\s+opret_ordrelinje\s*\(/', "function opret_ordrelinje_$copy(", $code, 1, $renamed);
    if ($renamed !== 1) {
        ob_end_clean();
        fwrite(STDERR, "opret_ordrelinje() is no longer in $file\n");
        exit(2);
    }
    eval($code);
}

$regnaar = (int)$spec['regnaar'];
foreach ($spec['globals'] ?? [] as $name => $value) {
    $GLOBALS[$name] = $value;
}

$results = [];
foreach ($spec['steps'] as $step) {
    $kind = array_shift($step);
    try {
        switch ($kind) {
            case 'line':
                $copy = array_shift($step);
                $fn = $copy === 'ordrefunc' ? 'opret_ordrelinje' : "opret_ordrelinje_$copy";
                $results[] = $fn(...$step);
                break;
            case 'saet':
                $results[] = opret_saet(...$step);
                break;
            case 'gendan':
                $results[] = gendan_saet(...$step);
                break;
            case 'validate':
                $results[] = validate_saet_lager_plan(...$step);
                break;
            case 'reread':
                $results[] = shopify_reread_new_lines(...$step);
                break;
            default:
                ob_end_clean();
                fwrite(STDERR, "unknown step $kind\n");
                exit(2);
        }
    } catch (Throwable $e) {
        $results[] = ['exception' => get_class($e), 'message' => $e->getMessage()];
    }
}

$printed = ob_get_clean();
fwrite(STDOUT, json_encode(['results' => $results, 'printed' => $printed], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
