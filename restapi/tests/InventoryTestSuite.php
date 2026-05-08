<?php

/**
 * Inventory API Test Suite Runner
 * 
 * This script runs all inventory-related endpoint tests in sequence
 * and provides a summary of results.
 */

// Include all test classes
require_once 'InventoryProductsEndpointTest.php';
require_once 'InventoryWarehousesEndpointTest.php';
require_once 'InventoryProductGroupsEndpointTest.php';
require_once 'InventoryStatusEndpointTest.php';

class InventoryTestSuite
{
    private $testResults = [];
    private $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Run all inventory tests
     */
    public function runAllTests()
    {
        echo "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                    INVENTORY API TEST SUITE                  ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

        $this->runTest('Products', function() {
            $test = new InventoryProductsEndpointTest();
            $test->runAllTests();
        });

        $this->runTest('Warehouses', function() {
            $test = new InventoryWarehousesEndpointTest();
            $test->runAllTests();
        });

        $this->runTest('Product Groups', function() {
            $test = new InventoryProductGroupsEndpointTest();
            $test->runAllTests();
        });

        $this->runTest('Inventory Status', function() {
            $test = new InventoryStatusEndpointTest();
            $test->runAllTests();
        });

        $this->printSummary();
    }

    /**
     * Run individual test with error handling
     */
    private function runTest($testName, $testFunction)
    {
        echo "┌─────────────────────────────────────────────────────────────┐\n";
        echo "│ Running $testName Tests" . str_repeat(' ', 45 - strlen($testName)) . "│\n";
        echo "└─────────────────────────────────────────────────────────────┘\n";

        $testStartTime = microtime(true);
        $success = false;
        $errorMessage = '';

        try {
            // Capture output
            ob_start();
            $testFunction();
            $output = ob_get_clean();
            
            // Check if test completed successfully
            if (strpos($output, 'completed successfully') !== false) {
                $success = true;
                echo $output;
            } else {
                echo $output;
                $errorMessage = 'Test may not have completed successfully';
            }
        } catch (Exception $e) {
            ob_end_clean();
            $success = false;
            $errorMessage = $e->getMessage();
            echo "❌ $testName tests failed: " . $errorMessage . "\n";
        } catch (Error $e) {
            ob_end_clean();
            $success = false;
            $errorMessage = $e->getMessage();
            echo "❌ $testName tests failed with fatal error: " . $errorMessage . "\n";
        }

        $testEndTime = microtime(true);
        $duration = round($testEndTime - $testStartTime, 2);

        $this->testResults[] = [
            'name' => $testName,
            'success' => $success,
            'duration' => $duration,
            'error' => $errorMessage
        ];

        if ($success) {
            echo "✅ $testName tests completed successfully in {$duration}s\n\n";
        } else {
            echo "❌ $testName tests failed in {$duration}s\n\n";
        }
    }

    /**
     * Print test summary
     */
    private function printSummary()
    {
        $totalDuration = round(microtime(true) - $this->startTime, 2);
        $totalTests = count($this->testResults);
        $passedTests = array_filter($this->testResults, function($result) {
            return $result['success'];
        });
        $failedTests = $totalTests - count($passedTests);

        echo "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                        TEST SUMMARY                          ║\n";
        echo "╠═══════════════════════════════════════════════════════════════╣\n";
        
        foreach ($this->testResults as $result) {
            $status = $result['success'] ? '✅ PASS' : '❌ FAIL';
            $name = str_pad($result['name'], 20);
            $duration = str_pad($result['duration'] . 's', 8);
            echo "║ $status │ $name │ $duration │\n";
            
            if (!$result['success'] && !empty($result['error'])) {
                $error = substr($result['error'], 0, 45);
                echo "║        │ Error: " . str_pad($error, 45) . " │\n";
            }
        }
        
        echo "╠═══════════════════════════════════════════════════════════════╣\n";
        echo "║ Total Tests: " . str_pad($totalTests, 10) . " │ Passed: " . str_pad(count($passedTests), 10) . " │ Failed: " . str_pad($failedTests, 8) . " ║\n";
        echo "║ Total Duration: " . str_pad($totalDuration . 's', 47) . " ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

        if ($failedTests > 0) {
            echo "⚠️  Some tests failed. Please check the error messages above.\n";
            echo "💡 Common issues:\n";
            echo "   - Update API credentials in test files\n";
            echo "   - Check API endpoint URLs\n";
            echo "   - Verify database connection\n";
            echo "   - Ensure test data dependencies exist\n\n";
        } else {
            echo "🎉 All inventory API tests passed successfully!\n\n";
        }
    }

    /**
     * Run quick health check on all endpoints
     */
    public function runHealthCheck()
    {
        echo "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                    API HEALTH CHECK                          ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

        $endpoints = [
            'Products' => 'https://yourdomain.com/restapi/endpoints/v1/products/',
            'Warehouses' => 'https://yourdomain.com/restapi/endpoints/v1/inventory/warehouses/',
            'Product Groups' => 'https://yourdomain.com/restapi/endpoints/v1/inventory/groups/',
            'Inventory Status' => 'https://yourdomain.com/restapi/endpoints/v1/inventory/status/'
        ];

        foreach ($endpoints as $name => $url) {
            $this->checkEndpointHealth($name, $url);
        }

        echo "\n";
    }

    /**
     * Check if endpoint responds
     */
    private function checkEndpointHealth($name, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $status = '❌ FAIL';
        $message = 'Connection failed';

        if ($error) {
            $message = $error;
        } elseif ($httpCode === 200 || $httpCode === 401) {
            // 401 is OK - means endpoint exists but needs auth
            $status = '✅ OK';
            $message = $httpCode === 401 ? 'Endpoint exists (auth required)' : 'Endpoint accessible';
        } elseif ($httpCode === 404) {
            $message = 'Endpoint not found';
        } else {
            $message = "HTTP $httpCode";
        }

        echo str_pad($name, 20) . " │ $status │ $message\n";
    }
}

// Command line interface
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $suite = new InventoryTestSuite();

    // Check command line arguments
    $command = isset($argv[1]) ? $argv[1] : 'test';

    switch ($command) {
        case 'health':
            $suite->runHealthCheck();
            break;
        case 'test':
        default:
            $suite->runAllTests();
            break;
    }
}
