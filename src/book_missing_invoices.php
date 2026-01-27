<?php
// Book Missing Invoices Function
// This script identifies and books orders that should have been booked to transactions but weren't

@session_start();
$s_id=session_id();

// Include necessary files
include("../includes/connect.php");
include("../includes/std_func.php");
include("../includes/online.php");  // This sets $db from session
include("../includes/ordrefunc.php"); // Contains the bogfor() function

// Get fiscal year dates using the fiscalYear function
include_once("../includes/stdFunc/fiscalYear.php");
list($regnstart, $regnslut) = explode(":", fiscalYear($regnaar));

// Fallback if fiscal year dates are not set
if (!isset($regnstart) || !isset($regnslut) || $regnstart < '1970-01-01') {
    $regnstart = date("Y")."-01-01";
    $regnslut = date("Y")."-12-31";
}

// Get current month date range
$firstDayOfYear = date('Y-m-d', strtotime($regnstart));
$lastDayOfYear = date('Y-m-d', strtotime($regnslut));
$currentMonthDay = date('-m-d');
$currentDateThisYear = date('Y', strtotime($regnstart)) . $currentMonthDay;
$regnskabsår = date('Y', strtotime($regnstart));
if ($currentDateThisYear < $firstDayOfYear) {
    $regnskabsår++;
}
$firstDayOfMonth = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, $regnskabsår));
$currentDay = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d'), $regnskabsår));

echo "<h2>Book Missing Invoices</h2>";
echo "<p>Date range: $firstDayOfMonth to $currentDay</p>";

/**
 * Function to book missing invoices
 * @param string $startDate - Start date for search
 * @param string $endDate - End date for search
 * @param bool $dryRun - If true, only shows what would be booked without actually booking
 * @return array - Results of the booking process
 */
function bookMissingInvoices($startDate, $endDate, $dryRun = true) {
    global $regnstart, $regnslut, $regnaar, $regnskabsår;
    
    $results = array(
        'found' => 0,
        'booked' => 0,
        'errors' => 0,
        'details' => array()
    );
    
    // Find orders that should be booked but aren't
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
        O.konto_id,
        O.kontonr,
        O.valuta,
        O.valutakurs,
        O.moms,
        O.momssats,
        COUNT(T.id) as transaction_count
    FROM ordrer O
    LEFT JOIN transaktioner T ON O.id = T.ordre_id
    WHERE O.ordredate >= '$startDate'
    AND O.ordredate <= '$endDate'
    AND O.shop_id IS NOT NULL
    AND O.art LIKE 'D%'  -- Only debtor orders (sales)
    AND O.status >= 3  -- Only invoiced orders (status 3 = faktureret/invoiced)
    GROUP BY O.id, O.ordrenr, O.firmanavn, O.ordredate, O.fakturadate, O.sum, O.status, O.fakturanr, O.shop_id, O.art, O.konto_id, O.kontonr, O.valuta, O.valutakurs, O.moms, O.momssats
    HAVING COUNT(T.id) = 0
    ORDER BY O.ordredate DESC, O.ordrenr DESC
    ";
    
    $result = db_select($query, __FILE__ . " linje " . __LINE__);
    
    if (!$result) {
        $results['errors']++;
        $results['details'][] = "Error executing query: " . pg_last_error();
        return $results;
    }
    
    while ($row = db_fetch_array($result)) {
        $results['found']++;
        
        $orderId = $row['id'];
        $orderNumber = $row['ordrenr'];
        $customerName = $row['firmanavn'];
        $orderDate = $row['ordredate'];
        $invoiceDate = $row['fakturadate'] ? $row['fakturadate'] : $row['ordredate'];
        $amount = $row['sum'];
        $vatAmount = $row['moms'];
        $vatRate = $row['momssats'];
        $invoiceNumber = $row['fakturanr'];
        $customerAccount = $row['kontonr'];
        $currency = $row['valuta'];
        $exchangeRate = $row['valutakurs'];
        
        $detail = array(
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'customer' => $customerName,
            'amount' => $amount,
            'vat' => $vatAmount,
            'invoice_date' => $invoiceDate
        );
        
        if ($dryRun) {
            $results['details'][] = "WOULD BOOK: Order #$orderNumber ($customerName) - Amount: " . dkdecimal($amount, 2) . " DKK";
        } else {
            // Actually book the invoice
            try {
                // Start transaction
                transaktion('begin');
                
                // Use the existing bogfor function to book the order
                $bookingResult = bogfor($orderId, 'on');
                
                if ($bookingResult == 'OK') {
                    // Commit transaction
                    transaktion('commit');
                    $results['booked']++;
                    $results['details'][] = "BOOKED: Order #$orderNumber ($customerName) - Amount: " . dkdecimal($amount, 2) . " DKK";
                } else {
                    // Rollback transaction
                    transaktion('rollback');
                    $results['errors']++;
                    $results['details'][] = "ERROR booking Order #$orderNumber: $bookingResult";
                }
                
            } catch (Exception $e) {
                // Rollback transaction on error
                transaktion('rollback');
                $results['errors']++;
                $results['details'][] = "EXCEPTION booking Order #$orderNumber: " . $e->getMessage();
            }
        }
    }
    
    return $results;
}

// Handle form submission
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    
    if ($action == 'preview') {
        echo "<h3>Preview - Orders that would be booked:</h3>";
        $results = bookMissingInvoices($startDate, $endDate, true);
        
        echo "<p><strong>Found " . $results['found'] . " orders that need to be booked.</strong></p>";
        
        if ($results['found'] > 0) {
            echo "<ul>";
            foreach ($results['details'] as $detail) {
                echo "<li>" . htmlspecialchars($detail) . "</li>";
            }
            echo "</ul>";
            
            // Show form to actually book them
            echo "<form method='post' style='margin-top: 20px;'>";
            echo "<input type='hidden' name='action' value='book'>";
            echo "<input type='hidden' name='start_date' value='$startDate'>";
            echo "<input type='hidden' name='end_date' value='$endDate'>";
            echo "<button type='submit' style='background-color: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>";
            echo "BOOK THESE " . $results['found'] . " ORDERS";
            echo "</button>";
            echo "</form>";
        }
        
    } elseif ($action == 'book') {
        echo "<h3>Booking Orders...</h3>";
        $results = bookMissingInvoices($startDate, $endDate, false);
        
        echo "<p><strong>Booking Results:</strong></p>";
        echo "<ul>";
        echo "<li>Found: " . $results['found'] . " orders</li>";
        echo "<li>Successfully booked: " . $results['booked'] . " orders</li>";
        echo "<li>Errors: " . $results['errors'] . " orders</li>";
        echo "</ul>";
        
        if (count($results['details']) > 0) {
            echo "<h4>Details:</h4>";
            echo "<ul>";
            foreach ($results['details'] as $detail) {
                echo "<li>" . htmlspecialchars($detail) . "</li>";
            }
            echo "</ul>";
        }
    }
} else {
    // Show the form
    echo "<form method='post'>";
    echo "<h3>Select Date Range</h3>";
    echo "<p>";
    echo "<label>Start Date: </label>";
    echo "<input type='date' name='start_date' value='$firstDayOfMonth' required>";
    echo "</p>";
    echo "<p>";
    echo "<label>End Date: </label>";
    echo "<input type='date' name='end_date' value='$currentDay' required>";
    echo "</p>";
    echo "<p>";
    echo "<button type='submit' name='action' value='preview' style='background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;'>Preview Orders</button>";
    echo "</p>";
    echo "</form>";
    
    // Show current month summary
    echo "<h3>Current Month Summary</h3>";
    $currentMonthResults = bookMissingInvoices($firstDayOfMonth, $currentDay, true);
    echo "<p><strong>Orders missing transactions in current month: " . $currentMonthResults['found'] . "</strong></p>";
    
    if ($currentMonthResults['found'] > 0) {
        echo "<h4>Orders that need booking:</h4>";
        echo "<ul>";
        foreach ($currentMonthResults['details'] as $detail) {
            echo "<li>" . htmlspecialchars($detail) . "</li>";
        }
        echo "</ul>";
    }
}

?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}
h2, h3, h4 {
    color: #333;
}
form {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
label {
    display: inline-block;
    width: 100px;
    font-weight: bold;
}
input[type="date"] {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-right: 10px;
}
button {
    font-size: 14px;
}
button:hover {
    opacity: 0.9;
}
ul {
    background-color: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
li {
    margin-bottom: 5px;
}
</style>
