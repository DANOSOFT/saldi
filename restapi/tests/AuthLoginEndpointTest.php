<?php

/**
 * Auth Login Endpoint Test Script
 * 
 * This script tests the authentication login endpoint for the API
 * using real HTTP requests to simulate actual API usage.
 */

class AuthLoginEndpointTest
{
    private $baseUrl;
    private $headers;

    // Test credentials - should match a valid user in your test database
    private $testUsername = 'api';
    private $testPassword = 'api';
    private $testAccountName = 'test2';

    public function __construct()
    {
        // Configure your API base URL
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/auth/login.php';
        
        // Basic headers for login (no auth needed for login endpoint)
        $this->headers = [
            'Content-Type: application/json'
        ];
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "=== Auth Login API Endpoint Tests ===\n\n";

        try {
            $this->testLoginMissingUsername();
            $this->testLoginMissingPassword();
            $this->testLoginMissingAccountName();
            $this->testLoginInvalidAccountName();
            $this->testLoginInvalidUsername();
            $this->testLoginInvalidPassword();
            $this->testLoginClosedTenant();
            $this->testSuccessfulLogin();
            $this->testGetMethodNotAllowed();
            $this->testTokenFormat();
            
            echo "\n=== Test Summary ===\n";
            echo "All tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    /**
     * Test login without username
     */
    public function testLoginMissingUsername()
    {
        echo "Testing: Login Missing Username\n";
        
        $loginData = [
            'password' => $this->testPassword,
            'account_name' => $this->testAccountName
        ];

        $response = $this->makeRequest('POST', $loginData);
        
        if (!$response['success'] && 
            $response['http_code'] == 400 &&
            stripos($response['message'], 'username') !== false) {
            echo "✓ Correctly rejected login without username\n";
        } else {
            throw new Exception("Should have rejected login without username. Response: " . json_encode($response));
        }
        
        echo "\n";
    }

    /**
     * Test login without password
     */
    public function testLoginMissingPassword()
    {
        echo "Testing: Login Missing Password\n";
        
        $loginData = [
            'username' => $this->testUsername,
            'account_name' => $this->testAccountName
        ];

        $response = $this->makeRequest('POST', $loginData);
        
        if (!$response['success'] && 
            $response['http_code'] == 400 &&
            stripos($response['message'], 'password') !== false) {
            echo "✓ Correctly rejected login without password\n";
        } else {
            throw new Exception("Should have rejected login without password. Response: " . json_encode($response));
        }
        
        echo "\n";
    }

    /**
     * Test login without account_name
     */
    public function testLoginMissingAccountName()
    {
        echo "Testing: Login Missing Account Name\n";
        
        $loginData = [
            'username' => $this->testUsername,
            'password' => $this->testPassword
        ];

        $response = $this->makeRequest('POST', $loginData);
        
        if (!$response['success'] && 
            $response['http_code'] == 400 &&
            (stripos($response['message'], 'account') !== false || 
             stripos($response['message'], 'required') !== false)) {
            echo "✓ Correctly rejected login without account_name\n";
        } else {
            throw new Exception("Should have rejected login without account_name. Response: " . json_encode($response));
        }
        
        echo "\n";
    }

    /**
     * Test login with invalid account_name
     */
    public function testLoginInvalidAccountName()
    {
        echo "Testing: Login Invalid Account Name\n";
        
        $loginData = [
            'username' => $this->testUsername,
            'password' => $this->testPassword,
            'account_name' => 'non_existent_account_xyz123'
        ];

        $response = $this->makeRequest('POST', $loginData);
        
        if (!$response['success'] && 
            $response['http_code'] == 404 &&
            stripos($response['message'], 'not found') !== false) {
            echo "✓ Correctly rejected login with invalid account_name\n";
        } else {
            throw new Exception("Should have rejected login with invalid account_name. Response: " . json_encode($response));
        }
        
        echo "\n";
    }

    /**
     * Test login with invalid username
     */
    public function testLoginInvalidUsername()
    {
        echo "Testing: Login Invalid Username\n";
        
        $loginData = [
            'username' => 'nonexistentuser_xyz123',
            'password' => $this->testPassword,
            'account_name' => $this->testAccountName
        ];

        $response = $this->makeRequest('POST', $loginData);
        
        if (!$response['success'] && 
            $response['http_code'] == 401 &&
            stripos($response['message'], 'invalid') !== false) {
            echo "✓ Correctly rejected login with invalid username\n";
        } else {
            throw new Exception("Should have rejected login with invalid username. Response: " . json_encode($response));
        }
        
        echo "\n";
    }

    /**
     * Test login with invalid password
     */
    public function testLoginInvalidPassword()
    {
        echo "Testing: Login Invalid Password\n";
        
        $loginData = [
            'username' => $this->testUsername,
            'password' => 'wrong_password_xyz123',
            'account_name' => $this->testAccountName
        ];

        $response = $this->makeRequest('POST', $loginData);
        
        if (!$response['success'] && 
            $response['http_code'] == 401 &&
            stripos($response['message'], 'invalid') !== false) {
            echo "✓ Correctly rejected login with invalid password\n";
        } else {
            throw new Exception("Should have rejected login with invalid password. Response: " . json_encode($response));
        }
        
        echo "\n";
    }

    /**
     * Test login with closed tenant account
     * Note: This test requires a tenant with lukket='on' in the regnskab table
     */
    public function testLoginClosedTenant()
    {
        echo "Testing: Login Closed Tenant\n";
        
        // This test assumes there's a closed tenant in the system
        // If not available, skip the test
        $loginData = [
            'username' => $this->testUsername,
            'password' => $this->testPassword,
            'account_name' => 'closed_account_test' // Should be a closed account
        ];

        $response = $this->makeRequest('POST', $loginData);
        
        // This might fail with 404 if the closed account doesn't exist, or 403 if it's closed
        if (!$response['success'] && 
            ($response['http_code'] == 403 || $response['http_code'] == 404)) {
            echo "✓ Correctly handled closed/missing tenant (HTTP {$response['http_code']})\n";
        } else if ($response['success']) {
            echo "⚠ Warning: Login succeeded - closed tenant test account may not be set up\n";
        } else {
            echo "⚠ Warning: Unexpected response for closed tenant test\n";
        }
        
        echo "\n";
    }

    /**
     * Test successful login
     */
    public function testSuccessfulLogin()
    {
        echo "Testing: Successful Login\n";
        
        $loginData = [
            'username' => $this->testUsername,
            'password' => $this->testPassword,
            'account_name' => $this->testAccountName
        ];

        $response = $this->makeRequest('POST', $loginData);
        
        if ($response['success'] && $response['http_code'] == 200) {
            echo "✓ Login successful\n";
            
            // Verify response structure
            $data = $response['data'];
            
            // Check access_token
            if (!isset($data['access_token']) || empty($data['access_token'])) {
                throw new Exception("Missing or empty access_token in response");
            }
            echo "✓ access_token present\n";
            
            // Check refresh_token
            if (!isset($data['refresh_token']) || empty($data['refresh_token'])) {
                throw new Exception("Missing or empty refresh_token in response");
            }
            echo "✓ refresh_token present\n";
            
            // Check token_type
            if (!isset($data['token_type']) || $data['token_type'] !== 'Bearer') {
                throw new Exception("Missing or incorrect token_type");
            }
            echo "✓ token_type is 'Bearer'\n";
            
            // Check expires_in
            if (!isset($data['expires_in']) || !is_numeric($data['expires_in'])) {
                throw new Exception("Missing or invalid expires_in");
            }
            echo "✓ expires_in is present and numeric ({$data['expires_in']}s)\n";
            
            // Check user object
            if (!isset($data['user']) || !is_array($data['user'])) {
                throw new Exception("Missing user object in response");
            }
            if (!isset($data['user']['id']) || !isset($data['user']['username'])) {
                throw new Exception("Missing user id or username");
            }
            echo "✓ user object present with id and username\n";
            
            // Check tenant object
            if (!isset($data['tenant']) || !is_array($data['tenant'])) {
                throw new Exception("Missing tenant object in response");
            }
            if (!isset($data['tenant']['id']) || !isset($data['tenant']['name']) || !isset($data['tenant']['db'])) {
                throw new Exception("Missing tenant id, name, or db");
            }
            echo "✓ tenant object present with id, name, and db\n";
            
            // Verify is_admin is boolean
            if (!isset($data['user']['is_admin'])) {
                throw new Exception("Missing is_admin in user object");
            }
            echo "✓ is_admin flag present (value: " . ($data['user']['is_admin'] ? 'true' : 'false') . ")\n";
            
        } else {
            throw new Exception("Login failed: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test GET method is not allowed
     */
    public function testGetMethodNotAllowed()
    {
        echo "Testing: GET Method Not Allowed\n";
        
        $response = $this->makeRequest('GET');
        
        if (!$response['success'] && $response['http_code'] == 405) {
            echo "✓ Correctly rejected GET request with 405 Method Not Allowed\n";
        } else {
            throw new Exception("Should have rejected GET request. Response: " . json_encode($response));
        }
        
        echo "\n";
    }

    /**
     * Test that tokens are valid JWT format
     */
    public function testTokenFormat()
    {
        echo "Testing: JWT Token Format\n";
        
        $loginData = [
            'username' => $this->testUsername,
            'password' => $this->testPassword,
            'account_name' => $this->testAccountName
        ];

        $response = $this->makeRequest('POST', $loginData);
        
        if (!$response['success']) {
            echo "⚠ Skipping: Could not verify token format (login failed)\n\n";
            return;
        }
        
        $accessToken = $response['data']['access_token'];
        $refreshToken = $response['data']['refresh_token'];
        
        // JWT tokens have 3 parts separated by dots
        $accessParts = explode('.', $accessToken);
        $refreshParts = explode('.', $refreshToken);
        
        if (count($accessParts) === 3) {
            echo "✓ access_token has valid JWT structure (3 parts)\n";
            
            // Verify header and payload are base64 decodable
            $header = json_decode(base64_decode($accessParts[0]), true);
            $payload = json_decode(base64_decode($accessParts[1]), true);
            
            if ($header && isset($header['typ']) && $header['typ'] === 'JWT') {
                echo "✓ access_token header indicates JWT type\n";
            }
            
            if ($payload && isset($payload['user_id']) && isset($payload['exp'])) {
                echo "✓ access_token payload contains user_id and exp claims\n";
            }
        } else {
            throw new Exception("access_token does not have valid JWT structure");
        }
        
        if (count($refreshParts) === 3) {
            echo "✓ refresh_token has valid JWT structure (3 parts)\n";
        } else {
            throw new Exception("refresh_token does not have valid JWT structure");
        }
        
        echo "\n";
    }

    /**
     * Make HTTP request to the API
     */
    private function makeRequest($method, $data = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Disable SSL verification for local testing (remove in production)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if ($data && $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("cURL Error: $error");
        }
        
        if ($httpCode >= 500) {
            throw new Exception("Server Error (HTTP $httpCode). Response: $response");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($decodedResponse === null) {
            throw new Exception("Invalid JSON response (HTTP $httpCode): $response");
        }
        
        // Add HTTP status code to response for better debugging
        $decodedResponse['http_code'] = $httpCode;
        
        return $decodedResponse;
    }
}

// Run the tests
try {
    $tester = new AuthLoginEndpointTest();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
