<?php
// 20260923 CDX/PHR Verify that period filtering preserves the settlement anchor.
require_once __DIR__ . '/../includes/std_func.php';
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function db_escape_string($value) { return str_replace("'", "''", $value); }
function db_modify($sql, $context) { $GLOBALS['settlementUpdates'][] = $sql; }
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
$controller = file_get_contents(__DIR__ . '/../includes/udlign_openpost.php');
$start = strpos($controller, '// Preserve selections by database ID');
$end = strpos($controller, '$query = db_select($qtxt', $start);
$rebuild = substr($controller, $start, $end - $start);
// The anchor is an August payment; the selected counterpart is a June invoice.
$_POST = ['candidate_id' => [1 => 102, 2 => 103]];
$udlign = [0 => 'on', 1 => 'on'];
$post_id = [101];
$amount = [-100];
eval($rebuild);
check($udlign === [0 => 'on'], 'Rebuilding June candidates retains the August anchor selection');
check($selectedPostIds === [102 => true], 'Counterpart selections remain tied to database IDs');
// Reproduce the controller candidate loop with one selected and one unselected row.
$rows = [['id'=>102,'amount'=>100], ['id'=>103,'amount'=>25]];
foreach ($rows as $index => $row) {
    $x = $index + 1;
    $post_id[$x] = $row['id'];
    $amount[$x] = $row['amount'];
    $udlign[$x] = isset($selectedPostIds[$row['id']]) ? 'on' : null;
}
$writer = file_get_contents(__DIR__ . '/../includes/alignOpenpostIncludes/doAlign.php');
$start = strpos($writer, 'for ($x=0; $x<=$postantal; $x++)');
$end = strpos($writer, "transaktion('commit')", $start);
$postantal = 2; $udlign_id = 123; $alignDate = '2026-08-20';
$insertInvoiceNumbers = true;
$faktnr = ["Juni.26, customer's reference", 'June invoice'];
$settlementUpdates = [];
eval(substr($writer, $start, $end - $start));
check(count($settlementUpdates) === 2, 'Settlement updates both the anchor and the selected counterpart');
check(preg_match("/where id = '?101'?$/i", $settlementUpdates[0]) === 1 && preg_match("/where id = '?102'?$/i", $settlementUpdates[1]) === 1, 'Out-of-period anchor is included and unselected counterpart is excluded');

check(strpos($settlementUpdates[0], "faktnr='Juni.26, customer''s reference'") !== false, 'Anchor reference is escaped and saved with its settlement status');
check(strpos($settlementUpdates[1], 'faktnr=') === false, 'Counterpart invoice reference is unchanged');
// Exercise the request block for preview actions without executing the page bootstrap.
$start = strpos($controller, '$faktnr[0]=trim');
$end = strpos($controller, "\tif (\$submit=='udlign')", $start);
$prepareReference = substr($controller, $start, $end - $start);
foreach (['find modposter', 'opdater'] as $submit) {
    $settlementUpdates = [];
    $faktnr = ['  Edited reference  '];
    $pendingInvoiceReference = null;
    $invoiceReferenceEdited = false;
    eval($prepareReference);
    check($settlementUpdates === [] && $pendingInvoiceReference === 'Edited reference', "$submit retains the draft without writing to the database");
}
$start = strpos($controller, '$faktnr[0] = $pendingInvoiceReference');
$end = strpos($controller, ';', $start);
$row = ['faktnr'=>'Original database reference'];
eval(substr($controller, $start, $end - $start + 1));
check($faktnr[0] === 'Edited reference', 'Reloading the post preserves the unsaved reference in the form');

$insertInvoiceNumbers = false;
$settlementUpdates = [];
$start = strpos($writer, 'for ($x=0; $x<=$postantal; $x++)');
$end = strpos($writer, "transaktion('commit')", $start);
eval(substr($writer, $start, $end - $start));
check(count($settlementUpdates) === 2 && strpos(implode(' ', $settlementUpdates), 'faktnr=') === false, 'Unchecked option settles both posts without changing invoice references');
$start = strpos($controller, '$insertInvoiceNumbers = ifset');
$end = strpos($controller, ';', $start);
$_POST = [];
eval(substr($controller, $start, $end - $start + 1));
check($insertInvoiceNumbers === false, 'Invoice insertion defaults to unchecked on a fresh request');
$start = strpos($controller, '// Also prepare references for exact matches');
$end = strpos($controller, "if (\$menu=='S')", $start);
$insertInvoiceNumbers = true;
$faktnr = ['Original', 'June invoice', 'Unselected invoice'];
$udlign = [0=>'on',1=>'on'];
eval(substr($controller, $start, $end - $start));
check($faktnr[0] === 'Original, June invoice', 'Opt-in appends only selected references even when matching rendered hidden inputs');

$settlementUpdates = [];
$submit = 'opdater';
$invoiceReferenceEdited = true;
$manualInvoiceReference = "Manual customer's reference";
$faktnr = ['Manual reference, automatically added invoice'];
eval($prepareReference);
check(count($settlementUpdates) === 1 && strpos($settlementUpdates[0], "Manual customer''s reference") !== false && strpos($settlementUpdates[0], 'automatically added') === false, 'Update saves only the manual edit, excluding later automatic additions');
$settlementUpdates = [];
$submit = 'find modposter';
$invoiceReferenceEdited = true;
eval($prepareReference);
check($settlementUpdates === [] && $invoiceReferenceEdited, 'Find counterparts retains a manual edit without saving it');
$submit = 'opdater';
$manualInvoiceReference = '';
eval($prepareReference);
check(count($settlementUpdates) === 1 && strpos($settlementUpdates[0], "faktnr=''") !== false, 'Update can save an intentionally cleared reference');
