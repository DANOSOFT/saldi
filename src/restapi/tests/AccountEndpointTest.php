<?php

/**
 * Account Endpoint Test Script
 * 
 * This script tests all CRUD operations for the Account API endpoint
 * using real HTTP requests to simulate actual API usage.
 */

class AccountEndpointTest
{
    private $baseUrl;
    private $headers;
    private $createdAccountIds = [];

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/accounts/';
        
        // Set your actual authorization headers
        $this->headers = [
            'Content-Type: application/json',
            'Authorization: 4M1SlprEv82hhtl2KSfCFOs4BzLYgAdUD',
            'X-SaldiUser: api',
            'X-DB: test_4'
        ];
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "=== Account API Endpoint Tests ===\n\n";

        try {
            $this->testCreateAccount();
            $this->testGetAllAccounts();
            $this->testGetSingleAccount();
            $this->testCreateDuplicateKontonr();
            $this->testUpdateAccount();
            $this->testUpdateWithDuplicateKontonr();
            $this->testDeleteAccount();
            $this->testCreateAccountMissingFields();
            $this->testGetNonExistentAccount();
            $this->testFilterAccounts();
            $this->testAccountValidation();
            
            echo "\n=== Test Summary ===\n";
            echo "All tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Test creating a new account
     */
    public function testCreateAccount()
    {
        echo "Testing: Create Account\n";
        
        $accountData = [
            'kontonr' => 9999,
            'beskrivelse' => 'Test Account A',
            'kontotype' => 'A',
            'moms' => 25.00,
            'primo' => 1000.00,
            'saldo' => 1500.50,
            'regnskabsaar' => 2024,
            'genvej' => 'TE',
            'valuta' => 'DKK',
            'anvendelse' => 'Test account for API testing'
        ];

        $response = $this->makeRequest('POST', $accountData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdAccountIds[] = $response['data']['id'];
            echo "✓ Account created successfully with ID: " . $response['data']['id'] . "\n";
            
            // Verify the created account has correct data
            if ($response['data']['kontonr'] != $accountData['kontonr']) {
                throw new Exception("Created account has wrong kontonr");
            }
            if ($response['data']['beskrivelse'] != $accountData['beskrivelse']) {
                throw new Exception("Created account has wrong beskrivelse");
            }
        } else {
            throw new Exception("Failed to create account: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting all accounts
     */
    public function testGetAllAccounts()
    {
        echo "Testing: Get All Accounts\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " accounts\n";
            
            // Verify our created account is in the list
            $found = false;
            foreach ($response['data'] as $account) {
                if (in_array($account['id'], $this->createdAccountIds)) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found && !empty($this->createdAccountIds)) {
                throw new Exception("Created account not found in account list");
            }
        } else {
            throw new Exception("Failed to get all accounts");
        }
        
        echo "\n";
    }

    /**
     * Test getting a single account
     */
    public function testGetSingleAccount()
    {
        if (empty($this->createdAccountIds)) {
            echo "Skipping: Get Single Account (no account created)\n\n";
            return;
        }

        echo "Testing: Get Single Account\n";
        
        $accountId = $this->createdAccountIds[0];
        $response = $this->makeRequest('GET', null, "?id=$accountId");
        
        if ($response['success'] && $response['data']['id'] == $accountId) {
            echo "✓ Retrieved account with ID: $accountId\n";
            
            // Verify account data integrity
            if (!isset($response['data']['kontonr']) || !isset($response['data']['beskrivelse'])) {
                throw new Exception("Account data missing required fields");
            }
        } else {
            throw new Exception("Failed to get single account");
        }
        
        echo "\n";
    }

    /**
     * Test creating account with duplicate kontonr
     */
    public function testCreateDuplicateKontonr()
    {
        echo "Testing: Create Account with Duplicate Kontonr\n";
        
        $accountData = [
            'kontonr' => 9999, // Same kontonr as first account
            'beskrivelse' => 'Test Account B',
            'kontotype' => 'B',
            'regnskabsaar' => 2024
        ];

        $response = $this->makeRequest('POST', $accountData);
        
        if (!$response['success'] && (
            strpos($response['message'], 'duplicate') !== false ||
            strpos($response['message'], 'already exists') !== false ||
            strpos($response['message'], 'kontonr') !== false
        )) {
            echo "✓ Correctly rejected duplicate kontonr\n";
        } else {
            echo "⚠ Warning: Duplicate kontonr was allowed (this might be intended behavior)\n";
        }
        
        echo "\n";
    }

    /**
     * Test updating an account
     */
    public function testUpdateAccount()
    {
        if (empty($this->createdAccountIds)) {
            echo "Skipping: Update Account (no account created)\n\n";
            return;
        }

        echo "Testing: Update Account\n";
        
        $accountId = $this->createdAccountIds[0];
        $updateData = [
            'id' => $accountId,
            'beskrivelse' => 'Updated Test Account A',
            'saldo' => 2000.75,
            'anvendelse' => 'This account has been updated via API'
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if ($response['success']) {
            echo "✓ Account updated successfully\n";
            
            // Verify the update by fetching the account again
            $verifyResponse = $this->makeRequest('GET', null, "?id=$accountId");
            if ($verifyResponse['success']) {
                if ($verifyResponse['data']['beskrivelse'] == $updateData['beskrivelse']) {
                    echo "✓ Update verified - beskrivelse changed correctly\n";
                } else {
                    throw new Exception("Update verification failed - beskrivelse not updated");
                }
            }
        } else {
            throw new Exception("Failed to update account: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test updating account with duplicate kontonr (might be allowed)
     */
    public function testUpdateWithDuplicateKontonr()
    {
        if (count($this->createdAccountIds) < 2) {
            // Create a second account for this test
            $accountData = [
                'kontonr' => 9998,
                'beskrivelse' => 'Test Account C',
                'kontotype' => 'C',
                'regnskabsaar' => 2024
            ];
            
            $response = $this->makeRequest('POST', $accountData);
            if ($response['success']) {
                $this->createdAccountIds[] = $response['data']['id'];
            }
        }

        if (count($this->createdAccountIds) < 2) {
            echo "Skipping: Update with Duplicate Kontonr (need 2 accounts)\n\n";
            return;
        }

        echo "Testing: Update Account with Duplicate Kontonr\n";
        
        $accountId = $this->createdAccountIds[1];
        $updateData = [
            'id' => $accountId,
            'kontonr' => 9999 // Kontonr from first account
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if (!$response['success'] && (
            strpos($response['message'], 'duplicate') !== false ||
            strpos($response['message'], 'already exists') !== false
        )) {
            echo "✓ Correctly rejected duplicate kontonr on update\n";
        } else {
            echo "⚠ Warning: Duplicate kontonr update was allowed (this might be intended behavior)\n";
        }
        
        echo "\n";
    }

    /**
     * Test creating account with missing required fields
     */
    public function testCreateAccountMissingFields()
    {
        echo "Testing: Create Account with Missing Required Fields\n";
        
        $accountData = [
            'beskrivelse' => 'Test Account Missing Fields',
            // Missing kontonr and other required fields
        ];

        $response = $this->makeRequest('POST', $accountData);
        
        if (!$response['success'] && (
            strpos($response['message'], 'required') !== false ||
            strpos($response['message'], 'missing') !== false ||
            strpos($response['message'], 'kontonr') !== false
        )) {
            echo "✓ Correctly rejected account with missing fields\n";
        } else {
            throw new Exception("Should have rejected account with missing required fields");
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent account
     */
    public function testGetNonExistentAccount()
    {
        echo "Testing: Get Non-Existent Account\n";
        
        $response = $this->makeRequest('GET', null, "?id=999999");
        
        if (!$response['success'] && (
            strpos($response['message'], 'not found') !== false ||
            strpos($response['message'], 'Account not found') !== false
        )) {
            echo "✓ Correctly returned error for non-existent account\n";
        } else {
            throw new Exception("Should have returned error for non-existent account");
        }
        
        echo "\n";
    }

    /**
     * Test filtering accounts
     */
    public function testFilterAccounts()
    {
        echo "Testing: Filter Accounts\n";
        
        // Test filtering by field
        $response = $this->makeRequest('GET', null, "?field=kontotype&value=A");
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Filter by kontotype=A returned " . count($response['data']) . " accounts\n";
        } else {
            echo "⚠ Warning: Filter functionality may not be implemented\n";
        }
        
        // Test ordering
        $response = $this->makeRequest('GET', null, "?orderBy=kontonr&orderDirection=DESC");
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Ordering by kontonr DESC returned " . count($response['data']) . " accounts\n";
        } else {
            echo "⚠ Warning: Ordering functionality may not be implemented\n";
        }
        
        echo "\n";
    }

    /**
     * Test account validation
     */
    public function testAccountValidation()
    {
        echo "Testing: Account Validation\n";
        
        // Test invalid data types
        $invalidData = [
            'kontonr' => 'not-a-number',
            'beskrivelse' => '',
            'moms' => 'invalid',
            'saldo' => 'not-numeric'
        ];

        $response = $this->makeRequest('POST', $invalidData);
        
        if (!$response['success']) {
            echo "✓ Correctly rejected invalid data types\n";
        } else {
            echo "⚠ Warning: Invalid data validation may need improvement\n";
        }
        
        echo "\n";
    }

    /**
     * Test deleting an account
     */
    public function testDeleteAccount()
    {
        if (empty($this->createdAccountIds)) {
            echo "Skipping: Delete Account (no account created)\n\n";
            return;
        }

        echo "Testing: Delete Account\n";
        
        $accountId = array_pop($this->createdAccountIds); // Remove from cleanup list
        $deleteData = ['id' => $accountId];

        $response = $this->makeRequest('DELETE', $deleteData);
        
        if ($response['success']) {
            echo "✓ Account deleted successfully\n";
            
            // Verify the account is actually deleted
            $verifyResponse = $this->makeRequest('GET', null, "?id=$accountId");
            if (!$verifyResponse['success']) {
                echo "✓ Delete verified - account no longer exists\n";
            } else {
                throw new Exception("Account still exists after delete");
            }
        } else {
            // Add back to cleanup list if delete failed
            $this->createdAccountIds[] = $accountId;
            throw new Exception("Failed to delete account: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Make HTTP request to the API
     */
    private function makeRequest($method, $data = null, $urlSuffix = '')
    {
        $url = $this->baseUrl . $urlSuffix;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Disable SSL verification for local testing (remove in production)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if ($data && in_array($method, ['POST', 'PUT', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("cURL Error: $error for URL: $url");
        }
        
        if ($httpCode >= 500) {
            throw new Exception("Server Error (HTTP $httpCode) for URL: $url. Response: $response");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($decodedResponse === null) {
            throw new Exception("Invalid JSON response from $url (HTTP $httpCode): $response");
        }
        
        // Add HTTP status code to response for better debugging
        $decodedResponse['http_code'] = $httpCode;
        
        return $decodedResponse;
    }

    /**
     * Clean up created test data
     */
    private function cleanup()
    {
        echo "\n=== Cleanup ===\n";
        
        if (empty($this->createdAccountIds)) {
            echo "No test accounts to clean up.\n";
            return;
        }
        
        foreach ($this->createdAccountIds as $accountId) {
            try {
                $deleteData = ['id' => $accountId];
                $response = $this->makeRequest('DELETE', $deleteData);
                
                if ($response['success']) {
                    echo "✓ Cleaned up account ID: $accountId\n";
                } else {
                    echo "✗ Failed to cleanup account ID: $accountId - " . ($response['message'] ?? 'Unknown error') . "\n";
                }
            } catch (Exception $e) {
                echo "✗ Error cleaning up account ID $accountId: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Run the tests
try {
    $tester = new AccountEndpointTest();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}