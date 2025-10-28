<?php
// Query to find orders missing transactions in current month
// Based on ordredate from ordrer table
@session_start();
$s_id=session_id();

// Include necessary files
include("../includes/connect.php");
include("../includes/std_func.php");
include("../includes/online.php");  // This sets $db from session

// Get fiscal year dates using the fiscalYear function
include_once("../includes/stdFunc/fiscalYear.php");
list($regnstart, $regnslut) = explode(":", fiscalYear($regnaar));

// Fallback if fiscal year dates are not set
if (!isset($regnstart) || !isset($regnslut) || $regnstart < '1970-01-01') {
    $regnstart = date("Y")."-01-01";
    $regnslut = date("Y")."-12-31";
}

// Get current month date range (similar to revenue_month.php logic)
$firstDayOfYear = date('Y-m-d', strtotime($regnstart)); // First day of fiscal year
$lastDayOfYear = date('Y-m-d', strtotime($regnslut)); // Last day of fiscal year

// Get current month day (month and day only)
$currentMonthDay = date('-m-d');

// Calculate today's date for this fiscal year
$currentDateThisYear = date('Y', strtotime($regnstart)) . $currentMonthDay;

$regnskabsår = date('Y', strtotime($regnstart));
if ($currentDateThisYear < $firstDayOfYear) {
    $regnskabsår++;
}

// First day of this month based on fiscal year
$firstDayOfMonth = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, $regnskabsår));

// Current day in fiscal year
$currentDay = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d'), $regnskabsår));

echo "<h2>Orders Missing Transactions in Current Month</h2>";
echo "<p>Date range: $firstDayOfMonth to $currentDay</p>";

// Debug: Check if tables exist
echo "<h3>Debug: Checking table existence</h3>";
$debug_query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('ordrer', 'transaktioner')";
$debug_result = db_select($debug_query, __FILE__ . " linje " . __LINE__);
echo "<p>Available tables:</p><ul>";
while ($debug_row = db_fetch_array($debug_result)) {
    echo "<li>" . $debug_row['table_name'] . "</li>";
}
echo "</ul>";

// Query to find orders that have ordredate in current month but no corresponding transactions
$query = "
SELECT 
    O.id,
    O.ordrenr,
    O.firmanavn,
    O.ordredate,
    O.fakturadate,
    O.sum,
    O.status,
    O.fakturanr,
    O.shop_id,
    O.art,
    COUNT(T.id) as transaction_count
FROM ordrer O
LEFT JOIN transaktioner T ON O.id = T.ordre_id
WHERE O.ordredate >= '$firstDayOfMonth'
AND O.ordredate <= '$currentDay'
AND o.shop_id IS NOT NULL
AND O.art LIKE 'D%'  -- Only debtor orders (sales)
GROUP BY O.id, O.ordrenr, O.firmanavn, O.ordredate, O.fakturadate, O.sum, O.status, O.fakturanr, O.art, O.shop_id
HAVING COUNT(T.id) = 0
ORDER BY O.ordredate DESC, O.ordrenr DESC
";

$result = db_select($query, __FILE__ . " linje " . __LINE__);

if (!$result) {
    echo "<p style='color: red;'>Error executing query: " . pg_last_error() . "</p>";
    echo "<p>Query was: <pre>" . htmlspecialchars($query) . "</pre></p>";
    exit;
}

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Order ID</th>";
echo "<th>Order Number</th>";
echo "<th>Customer</th>";
echo "<th>Order Date</th>";
echo "<th>Invoice Date</th>";
echo "<th>Amount</th>";
echo "<th>Status</th>";
echo "<th>Invoice Number</th>";
echo "<th>Shop ID</th>";
echo "<th>Type</th>";
echo "</tr>";

$missing_count = 0;
$total_missing_amount = 0;
$missing_order_numbers = array();
while ($row = db_fetch_array($result)) {
    $missing_count++;
    $total_missing_amount += $row['sum'];
    $missing_order_numbers[] = $row['ordrenr'];
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['ordrenr'] . "</td>";
    echo "<td>" . htmlspecialchars($row['firmanavn']) . "</td>";
    echo "<td>" . dkdato($row['ordredate']) . "</td>";
    echo "<td>" . ($row['fakturadate'] ? dkdato($row['fakturadate']) : 'Not set') . "</td>";
    echo "<td align='right'>" . dkdecimal($row['sum'], 2) . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . ($row['fakturanr'] ? $row['fakturanr'] : 'Not set') . "</td>";
    echo "<td>" . ($row['shop_id'] ? $row['shop_id'] : 'Not set') . "</td>";
    echo "<td>" . $row['art'] . "</td>";
    echo "</tr>";
}

echo "</table>";

if ($missing_count == 0) {
    echo "<p style='color: green; font-weight: bold;'>No orders missing transactions found for this month!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>Found $missing_count orders missing transactions.</p>";
    echo "<p style='color: red; font-weight: bold;'>Total missing amount: " . dkdecimal($total_missing_amount, 2) . " DKK</p>";
    
    // Display list of missing order numbers
    echo "<h3>Missing Order Numbers:</h3>";
    echo "<p><strong>Order Numbers:</strong> " . implode(', ', $missing_order_numbers) . "</p>";
    
    // Also display as a copyable list
    echo "<h4>Copyable List:</h4>";
    echo "<textarea rows='5' cols='50' readonly style='font-family: monospace; font-size: 12px;'>";
    echo implode("\n", $missing_order_numbers);
    echo "</textarea>";
}

// Additional query to show orders with transactions for comparison
echo "<h3>Orders WITH Transactions in Current Month (for comparison)</h3>";

$query_with_transactions = "
SELECT 
    O.id,
    O.ordrenr,
    O.firmanavn,
    O.ordredate,
    O.fakturadate,
    O.sum,
    O.status,
    O.fakturanr,
	O.shop_id,
    O.art,
    COUNT(T.id) as transaction_count,
    MIN(T.transdate) as first_transaction_date,
    MAX(T.transdate) as last_transaction_date
FROM ordrer O
INNER JOIN transaktioner T ON O.id = T.ordre_id
WHERE O.ordredate >= '$firstDayOfMonth'
AND O.ordredate <= '$currentDay'
AND O.shop_id IS NOT NULL
AND O.art LIKE 'D%'  -- Only debtor orders (sales)
GROUP BY O.id, O.ordrenr, O.firmanavn, O.ordredate, O.fakturadate, O.sum, O.status, O.fakturanr, O.art
ORDER BY O.ordredate DESC, O.ordrenr DESC
LIMIT 10
";
$result_with = db_select($query_with_transactions, __FILE__ . " linje " . __LINE__);

if (!$result_with) {
    echo "<p style='color: red;'>Error executing comparison query: " . pg_last_error() . "</p>";
    echo "<p>Query was: <pre>" . htmlspecialchars($query_with_transactions) . "</pre></p>";
    exit;
}

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Order ID</th>";
echo "<th>Order Number</th>";
echo "<th>Customer</th>";
echo "<th>Order Date</th>";
echo "<th>Amount</th>";
echo "<th>Transaction Count</th>";
echo "<th>First Transaction</th>";
echo "<th>Last Transaction</th>";
echo "</tr>";

while ($row = db_fetch_array($result_with)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['ordrenr'] . "</td>";
    echo "<td>" . htmlspecialchars($row['firmanavn']) . "</td>";
    echo "<td>" . dkdato($row['ordredate']) . "</td>";
    echo "<td align='right'>" . dkdecimal($row['sum'], 2) . "</td>";
    echo "<td align='center'>" . $row['transaction_count'] . "</td>";
    echo "<td>" . dkdato($row['first_transaction_date']) . "</td>";
    echo "<td>" . dkdato($row['last_transaction_date']) . "</td>";
    echo "</tr>";
}

echo "</table>";

?>
