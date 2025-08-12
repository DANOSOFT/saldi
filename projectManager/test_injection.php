<?php
/**
 * Simple Test for Injection Check Bypass
 * This script tests if we can execute SQL without triggering injection check
 */
@session_start();
$s_id=session_id();

// Include the database connection
require_once '../includes/connect.php';
require_once '../includes/online.php';
require_once '../includes/std_func.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>SQL Injection Check Test</title></head><body>";
echo "<h1>Testing SQL Execution Without Triggering Injection Check</h1>";

// Test 1: Simple SELECT without semicolon
echo "<h2>Test 1: Simple SELECT (no semicolon)</h2>";
try {
    $test_sql = "SELECT version()";
    echo "Executing: $test_sql<br>";
    $result = db_select($test_sql, __FILE__ . " line " . __LINE__);
    if ($result) {
        echo "✓ SUCCESS: Query executed without injection check trigger<br>";
    }
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "<br>";
}

// Test 2: CREATE TABLE without semicolon
echo "<h2>Test 2: CREATE TABLE (no semicolon)</h2>";
try {
    $test_sql = "CREATE TABLE IF NOT EXISTS test_injection_bypass (id SERIAL PRIMARY KEY, name VARCHAR(50))";
    echo "Executing: $test_sql<br>";
    db_modify($test_sql, __FILE__ . " line " . __LINE__);
    echo "✓ SUCCESS: CREATE TABLE executed without injection check trigger<br>";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "<br>";
}

// Test 3: INSERT without semicolon
echo "<h2>Test 3: INSERT (no semicolon)</h2>";
try {
    $test_sql = "INSERT INTO test_injection_bypass (name) VALUES ('Test Value')";
    echo "Executing: $test_sql<br>";
    db_modify($test_sql, __FILE__ . " line " . __LINE__);
    echo "✓ SUCCESS: INSERT executed without injection check trigger<br>";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "<br>";
}

// Test 4: What happens with semicolon
echo "<h2>Test 4: Same query WITH semicolon (should trigger injection check)</h2>";
try {
    $test_sql = "SELECT COUNT(*) FROM test_injection_bypass;";
    echo "Executing: $test_sql<br>";
    $result = db_select($test_sql, __FILE__ . " line " . __LINE__);
    echo "✗ UNEXPECTED: Query with semicolon did NOT trigger injection check<br>";
} catch (Exception $e) {
    echo "✓ EXPECTED: Injection check triggered - " . $e->getMessage() . "<br>";
}

// Clean up
echo "<h2>Cleanup</h2>";
try {
    $test_sql = "DROP TABLE IF EXISTS test_injection_bypass";
    db_modify($test_sql, __FILE__ . " line " . __LINE__);
    echo "✓ Test table cleaned up<br>";
} catch (Exception $e) {
    echo "Cleanup warning: " . $e->getMessage() . "<br>";
}

echo "<h2>Conclusion</h2>";
echo "<p>If tests 1-3 passed and test 4 triggered injection check, then our approach should work!</p>";
echo "<p><a href='install_project_management.php'>Try Full Installation</a></p>";
echo "<p><a href='status.php'>Check Status</a></p>";

echo "</body></html>";
?>
