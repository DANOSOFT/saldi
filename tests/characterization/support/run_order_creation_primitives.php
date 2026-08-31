<?php
// tests/characterization/support/run_order_creation_primitives.php
//
// Child-process runner exercising includes/order_creation.php's primitives
// directly (SD-600 scope item 1). This module has no production caller wired
// to it yet (that happens per the migration steps in the SD-600 plan), so
// this is genuinely new coverage of the module in isolation, not a
// characterization of an existing production path.
//
// Scenarios (argv 1):
//   debtor_art_map <order_art>                          - order_creation_debtor_art_for_order_art()
//   allocate_number_override <art> <override>           - order_creation_allocate_number() passthrough
//   allocate_number_real <art>                           - order_creation_allocate_number() -> real get_next_order_number()
//   resolve_debtor_existing <konto_id>                   - finds an existing adresser row by konto_id
//   resolve_debtor_create <order_art> <tlf> <kontonr>    - creates a new debtor (art derived from order_art)
//   insert_unknown_column                                - order_creation_insert() with a bogus column
//   insert_with_sql_filter                               - order_creation_insert()'s $options['sql_filter'] hook
//   insert_reports_ok_false_on_failure                   - order_creation_insert()'s 'ok' result key on a real db_modify() failure
//   create_minimal <konto_id>                            - order_creation_create() for an existing debtor
//
// Any thrown exception is caught and reported as {"error": "..."} rather
// than crashing the child process, so tests can assert on the expected
// failure scenarios (unmapped art, unknown column) too.
//
// Prints a single JSON object on the LAST line of stdout.
//
// History:
// 20260825 CL/SZ SD-600: created.
// 20260825 CL/SZ SD-600: added insert_with_sql_filter scenario.
// 20260825 CL/SZ SD-600: added insert_reports_ok_false_on_failure scenario;
//   create_minimal now also asserts 'ok'.

// tenant_db is always the LAST argument, unconditionally - every scenario
// call from the test suite appends it, regardless of how many scenario-
// specific args come before it.
if ($argc < 3) {
    fwrite(STDERR, "usage: php run_order_creation_primitives.php <scenario> [args...] <tenant_db>\n");
    exit(2);
}
$scenario = $argv[1];
$tenantDb = $argv[$argc - 1];
$argv = array_slice($argv, 0, $argc - 1); // scenario args below only ever see argv[2..] up to (not including) tenant_db

$_GET['sag_id'] = '';
$_GET['konto_id'] = '';
$_GET['tilbud_id'] = '';

require __DIR__ . '/bootstrap_ordrefunc.php';
require_once __DIR__ . '/../../../includes/order_creation.php';

$out = ['scenario' => $scenario];

try {
    switch ($scenario) {
        case 'debtor_art_map':
            $out['result'] = order_creation_debtor_art_for_order_art($argv[2]);
            break;

        case 'allocate_number_override':
            $out['result'] = order_creation_allocate_number($argv[2], (int)$argv[3]);
            break;

        case 'allocate_number_real':
            // get_next_order_number() computes max(ordrenr)+1 at call time and
            // doesn't reserve it - two calls with nothing inserted in between
            // return the SAME number (every real caller immediately consumes
            // it via an INSERT, which is what proves it's sequential).
            $art = $argv[2];
            $first = order_creation_allocate_number($art);
            db_modify("insert into ordrer (ordrenr, art, kontonr) values ('$first', '$art', '0')", __FILE__ . " linje " . __LINE__);
            $second = order_creation_allocate_number($art);
            $out['first'] = $first;
            $out['second'] = $second;
            break;

        case 'resolve_debtor_existing':
            $row = order_creation_resolve_debtor(['konto_id' => (int)$argv[2]], 'DO');
            $out['found'] = $row !== null;
            if ($row) {
                $out['id'] = (int)$row['id'];
                $out['kontonr'] = $row['kontonr'];
                $out['created'] = $row['created'];
            }
            break;

        case 'resolve_debtor_create':
            $orderArt = $argv[2];
            $tlf = $argv[3];
            $kontonr = $argv[4];
            $row = order_creation_resolve_debtor(
                ['tlf' => $tlf],
                $orderArt,
                ['kontonr' => $kontonr, 'firmanavn' => 'chartest primitive debtor', 'tlf' => $tlf],
                ['allow_create_debtor' => true]
            );
            $out['found'] = $row !== null;
            if ($row) {
                $out['id'] = (int)$row['id'];
                $out['kontonr'] = $row['kontonr'];
                $out['art'] = $row['art'];
                $out['created'] = $row['created'];
            }
            break;

        case 'insert_unknown_column':
            order_creation_insert(['not_a_real_column' => 'x', 'art' => 'DO', 'ordrenr' => 1, 'kontonr' => '1']);
            break;

        case 'insert_with_sql_filter':
            $art = 'DO';
            $ordrenr = order_creation_allocate_number($art);
            $filterCalled = false;
            $result = order_creation_insert(
                ['art' => $art, 'ordrenr' => $ordrenr, 'kontonr' => '0', 'notes' => 'plain'],
                ['sql_filter' => function ($qtxt) use (&$filterCalled) {
                    $filterCalled = true;
                    return str_replace("'plain'", "'filtered'", $qtxt);
                }]
            );
            $row = db_fetch_array(db_select("select notes from ordrer where id='" . $result['id'] . "'", __FILE__ . " linje " . __LINE__));
            $out['filter_called'] = $filterCalled;
            $out['notes'] = trim($row['notes']);
            break;

        case 'create_minimal':
            $kontoId = (int)$argv[2];
            $debtor = db_fetch_array(db_select("select kontonr, firmanavn from adresser where id='$kontoId'", __FILE__ . " linje " . __LINE__));
            $result = order_creation_create([
                'art' => 'DO',
                'konto_id' => $kontoId,
                'kontonr' => $debtor['kontonr'],
                'firmanavn' => $debtor['firmanavn'],
            ]);
            $out['id'] = $result['id'];
            $out['ordrenr'] = $result['ordrenr'];
            $out['konto_id'] = $result['konto_id'];
            $out['debtor_created'] = $result['debtor_created'];
            $out['ok'] = $result['ok'];
            break;

        case 'insert_reports_ok_false_on_failure':
            // db_modify() only returns a failure string (rather than exiting the
            // whole process via alert()/exit()) when $webservice is set - see
            // includes/db_query.php. Set it so this scenario can observe a real
            // failure instead of killing the child process.
            global $webservice;
            $webservice = true;
            // ordrer.art is varchar(2) - a longer value fails the INSERT itself
            // (a genuine db_modify() failure, not an order_creation_insert()
            // column-whitelist throw), so 'ok' must come back false.
            $result = order_creation_insert(['art' => 'way-too-long-for-the-art-column', 'ordrenr' => 999999999, 'kontonr' => '0']);
            $out['ok'] = $result['ok'];
            break;

        default:
            fwrite(STDERR, "unknown scenario $scenario\n");
            exit(2);
    }
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

ob_end_clean();
fwrite(STDOUT, json_encode($out) . "\n");
