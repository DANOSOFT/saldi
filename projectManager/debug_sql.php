<?php
/**
 * Debug SQL Injection Check
 * This script shows exactly what SQL is being processed
 */
@session_start();
$s_id=session_id();

// Include the database connection
require_once '../includes/connect.php';
require_once '../includes/online.php';
require_once '../includes/std_func.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>SQL Debug</title></head><body>";
echo "<h1>SQL Injection Check Debug</h1>";

// Test specific statements that might be causing issues
$test_statements = [
    "SELECT version()",
    "CREATE TABLE test_table (id INTEGER)",
    "SELECT COUNT(*) FROM brugere",
    "INSERT INTO test_table (id) VALUES (1)",
];

echo "<h2>Testing individual SQL statements:</h2>";

foreach ($test_statements as $i => $sql) {
    echo "<h3>Test " . ($i + 1) . ":</h3>";
    echo "<code style='background: #f0f0f0; padding: 5px; display: block;'>$sql</code>";
    
    // Check if statement contains semicolon
    if (strpos($sql, ';') !== false) {
        echo "<span style='color: red;'>⚠ Contains semicolon - will trigger injection check</span><br>";
    } else {
        echo "<span style='color: green;'>✓ No semicolon - should pass injection check</span><br>";
    }
    
    try {
        if (strpos($sql, 'SELECT') === 0) {
            $result = db_select($sql, __FILE__ . " line " . __LINE__);
            echo "<span style='color: green;'>✓ SELECT executed successfully</span><br>";
        } else {
            db_modify($sql, __FILE__ . " line " . __LINE__);
            echo "<span style='color: green;'>✓ MODIFY executed successfully</span><br>";
        }
    } catch (Exception $e) {
        echo "<span style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
    echo "<br>";
}

// Now test a statement WITH semicolon to confirm injection check works
echo "<h2>Testing injection check trigger:</h2>";
$bad_sql = "SELECT version();";
echo "<h3>Test with semicolon:</h3>";
echo "<code style='background: #f0f0f0; padding: 5px; display: block;'>$bad_sql</code>";

try {
    $result = db_select($bad_sql, __FILE__ . " line " . __LINE__);
    echo "<span style='color: red;'>⚠ Semicolon statement executed - injection check may not be working</span><br>";
} catch (Exception $e) {
    echo "<span style='color: green;'>✓ Injection check triggered as expected: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

// Clean up test table
try {
    db_modify("DROP TABLE IF EXISTS test_table", __FILE__ . " line " . __LINE__);
    echo "<p>✓ Cleanup completed</p>";
} catch (Exception $e) {
    echo "<p>Cleanup warning: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Recommendations:</h2>";
echo "<ul>";
echo "<li>Use <a href='install_direct.php'>Direct Installation</a> - Completely avoids file parsing</li>";
echo "<li>Check <a href='status.php'>Installation Status</a></li>";
echo "</ul>";

echo "</body></html>";
?>
