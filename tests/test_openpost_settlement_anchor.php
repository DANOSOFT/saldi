<?php
// 20260923 CDX/PHR Verify that period filtering preserves the settlement anchor.
require_once __DIR__ . '/../includes/std_func.php';
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
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
$settlementUpdates = [];
eval(substr($writer, $start, $end - $start));
check(count($settlementUpdates) === 2, 'Settlement updates both the anchor and the selected counterpart');
check(preg_match("/where id = '?101'?$/i", $settlementUpdates[0]) === 1 && preg_match("/where id = '?102'?$/i", $settlementUpdates[1]) === 1, 'Out-of-period anchor is included and unselected counterpart is excluded');
