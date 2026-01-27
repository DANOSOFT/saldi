<?php
/**
 * Sync All Products to Webshops
 * 
 * This script synchronizes all products and their stock quantities to all configured webshops.
 * Based on the sync_shop_vare function from std_func.php
 * 
 * Usage: php sync_all_products.php
 * Or run from web browser: https://yourdomain.com/sync_all_products.php
 */

@session_start();
$s_id = session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

// Set execution time limit for large datasets
set_time_limit(0);

// Initialize variables
$total_products = 0;
$total_synced = 0;
$total_errors = 0;
$start_time = time();

echo "<h2>Syncing All Products to Webshops</h2>";
echo "<p>Started at: " . date('Y-m-d H:i:s') . "</p>";

// Get API configuration
$qtxt = "select box4, box5, box6 from grupper where art='API'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$api_fil = trim($r['box4']);
$api_fil2 = trim($r["box5"]);
$api_fil3 = trim($r["box6"]);

if (!$api_fil) {
    echo "<p style='color: red;'>Error: No API configuration found. Please configure API settings in grupper table.</p>";
    exit;
}

echo "<p>API Endpoints configured:</p>";
echo "<ul>";
echo "<li>Primary API: " . ($api_fil ? $api_fil : "Not configured") . "</li>";
echo "<li>Secondary API: " . ($api_fil2 ? $api_fil2 : "Not configured") . "</li>";
echo "<li>Tertiary API: " . ($api_fil3 ? $api_fil3 : "Not configured") . "</li>";
echo "</ul>";

// Create log file
$log_file = "../temp/$db/sync_all_products_" . date('Y-m-d_H-i-s') . ".log";

$log = fopen($log_file, "w");
fwrite($log, "=== SYNC ALL PRODUCTS STARTED ===\n");
fwrite($log, "Time: " . date('Y-m-d H:i:s') . "\n");
fwrite($log, "API Primary: $api_fil\n");
fwrite($log, "API Secondary: $api_fil2\n");
fwrite($log, "API Tertiary: $api_fil3\n\n");

// Get all products that should be synced
$qtxt = "SELECT DISTINCT v.id, v.varenr, v.kostpris, v.salgspris, v.m_type, v.m_rabat, 
                v.retail_price, v.colli_webfragt, v.stregkode, v.gruppe, v.delvare
         FROM varer v 
         INNER JOIN grupper g ON v.gruppe = g.kodenr 
         WHERE g.art = 'VG' AND g.fiscal_year = $regnaar AND g.box8 IS NOT NULL AND g.box8 != ''
         ORDER BY v.id";

echo "<p>Fetching products to sync...</p>";
$products_query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$total_products = db_num_rows($products_query);

echo "<p>Found $total_products products to sync</p>";
echo "<div style='border: 1px solid #ccc; padding: 10px; max-height: 400px; overflow-y: auto;'>";

// Process each product
while ($product = db_fetch_array($products_query)) {
    $vare_id = $product['id'];
    $varenr = $product['varenr'];
    $kostpris = $product['kostpris'];
    $salgspris = $product['salgspris'];
    $m_type = $product['m_type'];
    $m_rabat = $product['m_rabat'];
    $retail_price = $product['retail_price'];
    $webFragt = $product['colli_webfragt'];
    $stregkode = $product['stregkode'];
    $gruppe = $product['gruppe'];
    $delvare = $product['delvare'];
    
    echo "<p>Processing product ID: $vare_id (varenr: $varenr)</p>";
    fwrite($log, "Processing product ID: $vare_id (varenr: $varenr)\n");
    
    // Get shop mapping
    $qtxt = "SELECT shop_id FROM shop_varer WHERE saldi_id = '$vare_id'";
    $shop_result = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    $shop_id = $shop_result ? $shop_result['shop_id'] : 0;
    
    // If no shop mapping and varenr is numeric, use varenr as shop_id
    if (!$shop_id && is_numeric($varenr)) {
        $shop_id = $varenr;
    }
    
    // Get stock information for all warehouses
    $qtxt = "SELECT lager, SUM(beholdning) as total_stock FROM lagerstatus WHERE vare_id = '$vare_id' GROUP BY lager";
    $stock_query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    
    $warehouses = array();
    while ($stock_row = db_fetch_array($stock_query)) {
        $warehouses[$stock_row['lager']] = $stock_row['total_stock'];
    }
    
    // If no warehouses found, try to get total stock
    if (empty($warehouses)) {
        $qtxt = "SELECT SUM(beholdning) as total_stock FROM lagerstatus WHERE vare_id = '$vare_id'";
        $total_result = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        $total_stock = $total_result ? $total_result['total_stock'] : 0;
        $warehouses[0] = $total_stock; // Default warehouse
    }
    
    // Sync to each warehouse
    foreach ($warehouses as $lager => $stock) {
        $stock = (int)$stock;
        $rand = rand();
        
        echo "<span style='margin-left: 20px;'>Warehouse $lager: Stock = $stock</span><br>";
        fwrite($log, "  Warehouse $lager: Stock = $stock\n");
        
        // Calculate total stock across all warehouses
        $total_stock = array_sum($warehouses);
        
        // Build API URLs for each configured API
        $apis = array();
        if ($api_fil) $apis[] = $api_fil;
        if ($api_fil2) $apis[] = $api_fil2;
        if ($api_fil3) $apis[] = $api_fil3;
        
        foreach ($apis as $api_url) {
            // Update stock
            $stock_url = "$api_url?update_stock=$shop_id&stock=$stock&totalStock=$total_stock";
            $stock_url .= "&stockno=$lager&costPrice=$kostpris&salesPrice=$salgspris&discountType=$m_type&discount=$m_rabat";
            $stock_url .= "&itemNo=" . urlencode($varenr) . "&rand=$rand&retailPrice=$retail_price&webFragt=$webFragt&barcode=$stregkode";
            
            // Update price
            $price_url = "$api_url?update_price=$shop_id&salesPrice=$salgspris&discountType=$m_type&discount=$m_rabat";
            $price_url .= "&itemNo=" . urlencode($varenr) . "&rand=$rand&costPrice=$kostpris&retailPrice=$retail_price&webFragt=$webFragt&barcode=$stregkode";
            
            // Update SKU/cost price
            $sku_url = "$api_url?sku=" . urlencode($varenr) . "&costPrice=$kostpris&rand=$rand";
            
            echo "<span style='margin-left: 40px; color: #666;'>Syncing to: " . parse_url($api_url, PHP_URL_HOST) . "</span><br>";
            fwrite($log, "    API: $api_url\n");
            fwrite($log, "    Stock URL: $stock_url\n");
            fwrite($log, "    Price URL: $price_url\n");
            fwrite($log, "    SKU URL: $sku_url\n");
            
            // Execute API calls
            $api_success = true;
            
            // Stock update
            $stock_cmd = "nohup curl '$stock_url' > ../temp/$db/curl_stock.txt 2>&1 &";
            $stock_result = shell_exec($stock_cmd);
            fwrite($log, "    Stock command: $stock_cmd\n");
            fwrite($log, "    Stock result: " . ($stock_result ? $stock_result : "No output") . "\n");
            
            // Price update
            $price_cmd = "nohup curl '$price_url' > ../temp/$db/curl_price.txt 2>&1 &";
            $price_result = shell_exec($price_cmd);
            fwrite($log, "    Price command: $price_cmd\n");
            fwrite($log, "    Price result: " . ($price_result ? $price_result : "No output") . "\n");
            
            // SKU update
            $sku_cmd = "nohup curl '$sku_url' > ../temp/$db/curl_sku.txt 2>&1 &";
            $sku_result = shell_exec($sku_cmd);
            fwrite($log, "    SKU command: $sku_cmd\n");
            fwrite($log, "    SKU result: " . ($sku_result ? $sku_result : "No output") . "\n");
            
            // Check if curl is available
            $curl_check = shell_exec("which curl 2>/dev/null");
            if (!$curl_check) {
                echo "<span style='margin-left: 40px; color: red;'>✗ Error: curl command not found</span><br>";
                fwrite($log, "    ✗ Error: curl command not found\n");
                $total_errors++;
                $api_success = false;
            } else {
                echo "<span style='margin-left: 40px; color: green;'>✓ API calls sent (curl found at: " . trim($curl_check) . ")</span><br>";
                fwrite($log, "    ✓ API calls sent successfully (curl: " . trim($curl_check) . ")\n");
            }
        }
        
        // Handle parts/assemblies if this is a delvare
        if ($delvare) {
            echo "<span style='margin-left: 20px; color: orange;'>Processing assembly parts...</span><br>";
            fwrite($log, "  Processing assembly parts\n");
            
            $qtxt = "SELECT * FROM styklister WHERE vare_id = '$vare_id'";
            $parts_query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            
            while ($part = db_fetch_array($parts_query)) {
                $part_id = $part['indgaar_i'];
                
                // Get part details
                $qtxt = "SELECT varenr, kostpris FROM varer WHERE id = '$part_id'";
                $part_result = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
                
                if ($part_result) {
                    $part_varenr = $part_result['varenr'];
                    $part_kostpris = $part_result['kostpris'];
                    
                    // Get part shop mapping
                    $qtxt = "SELECT shop_id FROM shop_varer WHERE saldi_id = '$part_id'";
                    $part_shop_result = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
                    $part_shop_id = $part_shop_result ? $part_shop_result['shop_id'] : 0;
                    
                    // Get part stock
                    list($part_total_stock, $part_stock) = explode('|', getAvailable($part_id, $lager));
                    
                    echo "<span style='margin-left: 40px;'>Part $part_id (varenr: $part_varenr): Stock = $part_stock</span><br>";
                    fwrite($log, "    Part $part_id (varenr: $part_varenr): Stock = $part_stock\n");
                    
                    // Sync part to each API
                    foreach ($apis as $api_url) {
                        $part_stock_url = "$api_url?update_stock=$part_shop_id&stock=$part_stock&totalStock=$part_total_stock";
                        $part_stock_url .= "&stockno=$lager&costPrice=$part_kostpris&itemNo=" . urlencode($part_varenr) . "&sku=" . urlencode($part_varenr);
                        $part_stock_url .= "&file=" . __FILE__ . "&line=" . __LINE__;
                        
                        $part_cost_url = "$api_url?costPrice=$part_kostpris&sku=" . urlencode($part_varenr);
                        
                        // Part stock update
                        $part_stock_cmd = "nohup curl '$part_stock_url' > ../temp/$db/curl_part_stock.txt 2>&1 &";
                        $part_stock_result = shell_exec($part_stock_cmd);
                        fwrite($log, "      Part stock command: $part_stock_cmd\n");
                        fwrite($log, "      Part stock result: " . ($part_stock_result ? $part_stock_result : "No output") . "\n");
                        
                        // Part cost update
                        $part_cost_cmd = "nohup curl '$part_cost_url' > ../temp/$db/curl_part_cost.txt 2>&1 &";
                        $part_cost_result = shell_exec($part_cost_cmd);
                        fwrite($log, "      Part cost command: $part_cost_cmd\n");
                        fwrite($log, "      Part cost result: " . ($part_cost_result ? $part_cost_result : "No output") . "\n");
                        
                        echo "<span style='margin-left: 60px; color: green;'>✓ Part synced</span><br>";
                        fwrite($log, "      ✓ Part synced successfully\n");
                    }
                }
            }
        }
    }
    
    $total_synced++;
    
    // Add small delay to prevent overwhelming the APIs
    usleep(100000); // 0.1 second delay
    
    // Flush output to show progress in real-time
    if (ob_get_level()) {
        ob_flush();
        flush();
    }
}

echo "</div>";

// Calculate execution time
$end_time = time();
$execution_time = $end_time - $start_time;

// Write final log
fwrite($log, "\n=== SYNC COMPLETED ===\n");
fwrite($log, "End time: " . date('Y-m-d H:i:s') . "\n");
fwrite($log, "Total products processed: $total_products\n");
fwrite($log, "Total products synced: $total_synced\n");
fwrite($log, "Total errors: $total_errors\n");
fwrite($log, "Execution time: $execution_time seconds\n");
fclose($log);

// Display summary
echo "<h3>Sync Summary</h3>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Metric</th><th>Value</th></tr>";
echo "<tr><td>Total Products Processed</td><td>$total_products</td></tr>";
echo "<tr><td>Total Products Synced</td><td>$total_synced</td></tr>";
echo "<tr><td>Total Errors</td><td>$total_errors</td></tr>";
echo "<tr><td>Execution Time</td><td>$execution_time seconds</td></tr>";
echo "<tr><td>Log File</td><td><a href='$log_file' target='_blank'>$log_file</a></td></tr>";
echo "</table>";

if ($total_errors > 0) {
    echo "<p style='color: red;'>⚠️ $total_errors errors occurred during sync. Please check the log file for details.</p>";
} else {
    echo "<p style='color: green;'>✅ All products synced successfully!</p>";
}

echo "<p><strong>Note:</strong> API calls are executed asynchronously. It may take a few minutes for all changes to be reflected in the webshops.</p>";
echo "<p><a href='javascript:history.back()'>← Go Back</a></p>";

?>
