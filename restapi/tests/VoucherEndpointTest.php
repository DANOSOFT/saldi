<?php

/**
 * Voucher Endpoint Test Script
 * 
 * This script tests all operations for the Voucher API endpoint
 * using JWT authentication, including validation, business logic, and error handling.
 */

class VoucherEndpointTest
{
    private $baseUrl;
    private $loginUrl;
    private $tenantsUrl;
    private $accessToken;
    private $tenantId;
    private $createdVoucherIds = [];
    private $testImagePath;

    public function __construct($preConfiguredToken = null)
    {
        // Configure your API base URLs
        // Note: login.php needs .php extension, but vouchers uses routing (no .php)
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/vouchers';
        $this->loginUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/auth/login.php';
        $this->tenantsUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/user/tenants';
        
        // Set tenant ID (will be auto-detected after login if not provided)
        $this->tenantId = null; // Will be set after login or can be set manually
        
        // Use pre-configured token if provided (skip login)
        if ($preConfiguredToken) {
            $this->accessToken = $preConfiguredToken;
            echo "Using pre-configured JWT token (skipping login)\n";
            // Still try to auto-detect tenant
            if (!$this->tenantId) {
                $this->detectTenant();
            }
            echo "\n";
        }
        
        // Create a test image file for uploads
        $this->createTestImage();
    }

    /**
     * Create a simple test image file
     */
    private function createTestImage()
    {
        $this->testImagePath = sys_get_temp_dir() . '/test_voucher_' . uniqid() . '.png';
        
        // Create a simple 100x100 PNG image
        $img = imagecreatetruecolor(100, 100);
        $bgColor = imagecolorallocate($img, 255, 255, 255);
        $textColor = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $bgColor);
        imagestring($img, 5, 10, 40, 'Test Voucher', $textColor);
        imagepng($img, $this->testImagePath);
        imagedestroy($img);
    }

    /**
     * Clean up test image file
     */
    private function cleanupTestImage()
    {
        if (file_exists($this->testImagePath)) {
            @unlink($this->testImagePath);
        }
    }

    /**
     * Login and get JWT token
     */
    private function login($username, $password, $database = null)
    {
        echo "Logging in as: $username\n";
        
        $loginData = [
            'username' => $username,
            'password' => $password,
			"database" => "test_3"
        ];
        
        // Add database parameter if provided (can be database name or tenant ID)
        if ($database) {
            $loginData['database'] = $database;
            echo "  With database/tenant: $database\n";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->loginUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Curl error during login: ' . $error);
        }
        
        curl_close($ch);

        // Debug: Show raw response if JSON decode fails
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = 'JSON decode error during login: ' . json_last_error_msg();
            $errorMsg .= "\nHTTP Code: $httpCode";
            $errorMsg .= "\nResponse (first 500 chars): " . substr($response, 0, 500);
            $errorMsg .= "\n\nTIP: If login endpoint returns 404, use a pre-configured token instead:";
            $errorMsg .= "\n  new VoucherEndpointTest('your_jwt_token_here')";
            throw new Exception($errorMsg);
        }

        if ($decodedResponse['success'] && isset($decodedResponse['data']['access_token'])) {
            $this->accessToken = $decodedResponse['data']['access_token'];
            echo "✓ Login successful\n";
            echo "  User: " . $decodedResponse['data']['user']['username'] . "\n";
            echo "  User ID: " . $decodedResponse['data']['user']['id'] . "\n";
            
            // If tenant info is in response (database parameter was provided), use it
            if (isset($decodedResponse['data']['tenant'])) {
                $tenant = $decodedResponse['data']['tenant'];
                $this->tenantId = $tenant['id'];
                echo "  ✓ Tenant from login: {$tenant['name']} (ID: {$tenant['id']}, DB: {$tenant['db']})\n";
            } elseif (!$this->tenantId) {
                // Auto-detect tenant if not set and not provided in login
                $this->detectTenant();
            }
            
            echo "\n";
            return true;
        } else {
            throw new Exception("Login failed: " . ($decodedResponse['message'] ?? 'Unknown error') . " (HTTP $httpCode)");
        }
    }

    /**
     * Get available tenants for the logged-in user and use the first one
     */
    private function detectTenant()
    {
        if (!$this->accessToken) {
            return;
        }
        
        echo "  Fetching available tenants...\n";
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->tenantsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            curl_close($ch);
            echo "  ⚠ Warning: Could not fetch tenants: " . curl_error($ch) . "\n";
            echo "  ⚠ You may need to set tenant ID manually: \$tester->setTenantId(1);\n";
            return;
        }
        
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
        
        if ($decodedResponse && $decodedResponse['success'] && !empty($decodedResponse['data'])) {
            // Use the first available tenant
            $firstTenant = $decodedResponse['data'][0];
            $this->tenantId = $firstTenant['id'];
            echo "  ✓ Using tenant: {$firstTenant['name']} (ID: {$this->tenantId}, DB: {$firstTenant['db']})\n";
        } else {
            echo "  ⚠ Warning: No tenants found or error fetching tenants\n";
            echo "  ⚠ You need to set tenant ID manually: \$tester->setTenantId(1);\n";
        }
    }

    /**
     * Manually set tenant ID (useful if auto-detection fails)
     */
    public function setTenantId($tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "=== Voucher API Endpoint Tests (JWT Authentication) ===\n\n";

        try {
            // Login to get JWT token (only if not using pre-configured token)
            if (!$this->accessToken) {
                // NOTE: Login authenticates against the MASTER database (not tenant-specific)
                // After login, the test will automatically fetch available tenants and use the first one
                // 
                // If you get "Invalid username or password" error:
                //   1. Update the credentials below with correct values
                //   2. Or skip login by using a pre-configured token:
                //      $tester = new VoucherEndpointTest('your_existing_jwt_token');
                //
                // To get a token manually (if login works):
                //   curl -X POST https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/auth/login.php \
                //     -H "Content-Type: application/json" \
                //     -d '{"username":"your_username","password":"your_password","database":"database_name"}'
                //   Note: "database" can be database name or tenant ID (optional)
                //
                // You can also specify database during login:
                //   $this->login('api', 'password', 'database_name'); // or tenant ID: 1
                //
                $this->login('api', 'api'); // ⚠️ UPDATE: Replace with actual password
                // Or with database: $this->login('api', 'password', 'database_name');
            }
            
            // Ensure we have a tenant ID before proceeding
            if (!$this->tenantId) {
                throw new Exception("No tenant ID set. Either login to auto-detect, or set manually: \$tester->setTenantId(1);");
            }
            
            // Test authentication
            $this->testInvalidToken();
            $this->testMissingToken();
            
            // Test voucher operations
            $this->testUploadVoucher();
            $this->testUploadVoucherWithMetadata();
            $this->testGetAllVouchers();
            $this->testGetSingleVoucher();
            $this->testGetVoucherImage();
            $this->testGetVoucherThumbnail();
            $this->testGetNonExistentVoucher();
            $this->testUploadVoucherMissingFile();
            $this->testUploadVoucherInvalidDate();
            $this->testGetVouchersWithPagination();
            
            echo "\n=== Test Summary ===\n";
            echo "All tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Test failed with error: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Test with invalid JWT token
     */
    public function testInvalidToken()
    {
        echo "Testing: Invalid JWT Token\n";
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer invalid_token_here',
            'X-Tenant-ID: ' . $this->tenantId
        ];
        
        $response = $this->makeRequest('GET', null, '', $headers);

        if (!$response['success'] && strpos($response['message'], 'Invalid or expired token') !== false) {
            echo "✓ Correctly rejected invalid token\n\n";
        } else {
            throw new Exception("Should have rejected invalid token");
        }
    }

    /**
     * Test with missing JWT token
     */
    public function testMissingToken()
    {
        echo "Testing: Missing JWT Token\n";
        
        $headers = [
            'Content-Type: application/json',
            'X-Tenant-ID: ' . $this->tenantId
        ];
        
        $response = $this->makeRequest('GET', null, '', $headers);
        
        if (!$response['success'] && ($response['message'] === 'Invalid or expired token' || strpos($response['message'], 'token') !== false)) {
            echo "✓ Correctly rejected missing token\n\n";
        } else {
            echo "⚠ Warning: Expected token rejection but got: " . ($response['message'] ?? 'Unknown') . "\n\n";
        }
    }

    /**
     * Test uploading a voucher with minimal data
     */
    public function testUploadVoucher()
    {
        echo "Testing: Upload Voucher (Minimal Data)\n";
        
        if (!file_exists($this->testImagePath)) {
            throw new Exception("Test image file not found: " . $this->testImagePath);
        }
        
        $postData = [
            'file' => new CURLFile($this->testImagePath, 'image/png', 'test_voucher.png'),
            'beskrivelse' => 'Test voucher upload',
            'dato' => date('Y-m-d'),
            'belob' => 100.50,
            'kategori' => 'kladde'
        ];
        
        echo "  Making POST request to upload voucher...\n";
        $response = $this->makeMultipartRequest('POST', $postData);
        
        echo "  Response received. Success: " . ($response['success'] ? 'true' : 'false') . "\n";
        echo "  Response message: " . ($response['message'] ?? 'N/A') . "\n";
        echo "  Response data type: " . gettype($response['data'] ?? null) . "\n";
        if (is_array($response['data'])) {
            echo "  Response data count: " . count($response['data']) . "\n";
            if (!empty($response['data'])) {
                echo "  Response data keys: " . implode(', ', array_keys($response['data'])) . "\n";
            }
        }
        echo "  Full response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
        
        // Check for success with empty data (indicates VoucherModel issue)
        if ($response['success'] && (empty($response['data']) || (is_array($response['data']) && count($response['data']) === 0))) {
            throw new Exception("Upload appeared to succeed but returned empty data. This suggests VoucherModel->toArray() is returning empty. " .
                "Check that VoucherModel exists and properly populates data after save(). Response: " . json_encode($response));
        }
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdVoucherIds[] = $response['data']['id'];
            echo "✓ Voucher uploaded successfully with ID: " . $response['data']['id'] . "\n";
            echo "  Filename: " . ($response['data']['filename'] ?? 'N/A') . "\n";
            echo "  Description: " . ($response['data']['beskrivelse'] ?? 'N/A') . "\n\n";
        } elseif ($response['success'] && is_array($response['data']) && !empty($response['data'])) {
            // Handle case where data might be an array with the voucher as first element
            $voucher = is_array($response['data']) && isset($response['data'][0]) ? $response['data'][0] : $response['data'];
            if (isset($voucher['id'])) {
                $this->createdVoucherIds[] = $voucher['id'];
                echo "✓ Voucher uploaded successfully with ID: " . $voucher['id'] . "\n";
                echo "  Filename: " . ($voucher['filename'] ?? 'N/A') . "\n";
                echo "  Description: " . ($voucher['beskrivelse'] ?? 'N/A') . "\n\n";
            } else {
                throw new Exception("Failed to upload voucher: Response indicates success but no voucher ID found. Response: " . json_encode($response));
            }
        } else {
            $errorMsg = $response['message'] ?? 'Unknown error';
            if (empty($errorMsg)) {
                $errorMsg = 'No error message provided. Response: ' . json_encode($response);
            }
            throw new Exception("Failed to upload voucher: " . $errorMsg);
        }
    }

    /**
     * Test uploading a voucher with full metadata
     */
    public function testUploadVoucherWithMetadata()
    {
        echo "Testing: Upload Voucher (Full Metadata)\n";
        
        if (!file_exists($this->testImagePath)) {
            throw new Exception("Test image file not found: " . $this->testImagePath);
        }
        
        $postData = [
            'file' => new CURLFile($this->testImagePath, 'image/png', 'test_voucher_full.png'),
            'beskrivelse' => 'Test voucher with full metadata',
            'dato' => date('Y-m-d', strtotime('-5 days')),
            'belob' => 250.75,
            'kategori' => 'godkendt'
        ];
        
        $response = $this->makeMultipartRequest('POST', $postData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdVoucherIds[] = $response['data']['id'];
            echo "✓ Voucher uploaded with full metadata, ID: " . $response['data']['id'] . "\n";
            echo "  Amount: " . ($response['data']['belob'] ?? 'N/A') . "\n";
            echo "  Date: " . ($response['data']['dato'] ?? 'N/A') . "\n";
            echo "  Category: " . ($response['data']['kategori'] ?? 'N/A') . "\n\n";
        } else {
            throw new Exception("Failed to upload voucher with metadata: " . ($response['message'] ?? 'Unknown error'));
        }
    }

    /**
     * Test getting all vouchers
     */
    public function testGetAllVouchers()
    {
        echo "Testing: Get All Vouchers\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " vouchers\n";
            if (count($response['data']) > 0) {
                echo "  First voucher ID: " . ($response['data'][0]['id'] ?? 'N/A') . "\n";
            }
        } else {
            throw new Exception("Failed to get all vouchers");
        }
        
        echo "\n";
    }

    /**
     * Test getting vouchers with pagination
     */
    public function testGetVouchersWithPagination()
    {
        echo "Testing: Get Vouchers with Pagination\n";
        
        $response = $this->makeRequest('GET', null, '?limit=5&offset=0');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " vouchers (limit: 5, offset: 0)\n";
        } else {
            throw new Exception("Failed to get vouchers with pagination");
        }
        
        echo "\n";
    }

    /**
     * Test getting a single voucher
     */
    public function testGetSingleVoucher()
    {
        if (empty($this->createdVoucherIds)) {
            echo "Skipping: Get Single Voucher (no voucher created)\n\n";
            return;
        }

        echo "Testing: Get Single Voucher\n";
        
        $voucherId = $this->createdVoucherIds[0];
        $response = $this->makeRequest('GET', null, "?id=$voucherId");

        if ($response['success'] && isset($response['data']['id']) && $response['data']['id'] == $voucherId) {
            echo "✓ Retrieved voucher with ID: $voucherId\n";
            echo "  Description: " . ($response['data']['beskrivelse'] ?? 'N/A') . "\n";
            echo "  Amount: " . ($response['data']['belob'] ?? 'N/A') . "\n";
        } else {
            throw new Exception("Failed to get single voucher");
        }
        
        echo "\n";
    }

    /**
     * Test getting voucher image
     */
    public function testGetVoucherImage()
    {
        if (empty($this->createdVoucherIds)) {
            echo "Skipping: Get Voucher Image (no voucher created)\n\n";
            return;
        }

        echo "Testing: Get Voucher Image\n";
        
        $voucherId = $this->createdVoucherIds[0];
        $response = $this->makeRequest('GET', null, "$voucherId/image", null, true);

        // For image requests, we expect binary data, not JSON
        if (is_string($response) && strlen($response) > 0) {
            echo "✓ Retrieved image data (" . strlen($response) . " bytes)\n";
        } else {
            // If it's JSON, it might be an error
            if (is_array($response) && !$response['success']) {
                echo "⚠ Image not available: " . ($response['message'] ?? 'Unknown error') . "\n";
            } else {
                throw new Exception("Failed to get voucher image");
            }
        }
        
        echo "\n";
    }

    /**
     * Test getting voucher thumbnail
     */
    public function testGetVoucherThumbnail()
    {
        if (empty($this->createdVoucherIds)) {
            echo "Skipping: Get Voucher Thumbnail (no voucher created)\n\n";
            return;
        }

        echo "Testing: Get Voucher Thumbnail\n";
        
        $voucherId = $this->createdVoucherIds[0];
        $response = $this->makeRequest('GET', null, "$voucherId/thumbnail", null, true);

        // For thumbnail requests, we expect binary data, not JSON
        if (is_string($response) && strlen($response) > 0) {
            echo "✓ Retrieved thumbnail data (" . strlen($response) . " bytes)\n";
        } else {
            // If it's JSON, it might be an error
            if (is_array($response) && !$response['success']) {
                echo "⚠ Thumbnail not available: " . ($response['message'] ?? 'Unknown error') . "\n";
            } else {
                throw new Exception("Failed to get voucher thumbnail");
            }
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent voucher
     */
    public function testGetNonExistentVoucher()
    {
        echo "Testing: Get Non-Existent Voucher\n";
        
        $response = $this->makeRequest('GET', null, '?id=999999');
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Correctly returned error for non-existent voucher\n\n";
        } else {
            throw new Exception("Should have returned error for non-existent voucher");
        }
    }

    /**
     * Test uploading voucher without file
     */
    public function testUploadVoucherMissingFile()
    {
        echo "Testing: Upload Voucher (Missing File)\n";
        
        $postData = [
            'beskrivelse' => 'Test without file',
            'dato' => date('Y-m-d'),
            'belob' => 50.00
        ];
        
        $response = $this->makeMultipartRequest('POST', $postData);
        
        if (!$response['success'] && strpos($response['message'], 'No file uploaded') !== false) {
            echo "✓ Correctly rejected upload without file\n\n";
        } else {
            throw new Exception("Should have rejected upload without file");
        }
    }

    /**
     * Test uploading voucher with invalid date format
     */
    public function testUploadVoucherInvalidDate()
    {
        echo "Testing: Upload Voucher (Invalid Date Format)\n";
        
        if (!file_exists($this->testImagePath)) {
            throw new Exception("Test image file not found: " . $this->testImagePath);
        }
        
        $postData = [
            'file' => new CURLFile($this->testImagePath, 'image/png', 'test_invalid_date.png'),
            'beskrivelse' => 'Test invalid date',
            'dato' => 'invalid-date-format',
            'belob' => 50.00
        ];
        
        $response = $this->makeMultipartRequest('POST', $postData);
        
        if (!$response['success'] && strpos($response['message'], 'Invalid date format') !== false) {
            echo "✓ Correctly rejected invalid date format\n\n";
        } else {
            throw new Exception("Should have rejected invalid date format");
        }
    }

    /**
     * Make HTTP request to API endpoint (JSON)
     */
    private function makeRequest($method, $data = null, $urlSuffix = '', $customHeaders = null, $expectBinary = false)
    {
        // Remove trailing slash from baseUrl if urlSuffix starts with /
        $baseUrl = rtrim($this->baseUrl, '/');
        $urlSuffix = ltrim($urlSuffix, '/');
        $url = $baseUrl . ($urlSuffix ? '/' . $urlSuffix : '');
        
        $headers = $customHeaders ?: [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken,
            'X-Tenant-ID: ' . $this->tenantId
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Limit redirects
        
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        // If expecting binary data (images), return raw response
        if ($expectBinary) {
            return $response;
        }
        
        // Try to decode JSON
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error: ' . json_last_error_msg() . ". Raw response: " . substr($response, 0, 200));
        }
        
        return $decodedResponse;
    }

    /**
     * Make multipart/form-data request for file uploads
     */
    private function makeMultipartRequest($method, $data)
    {
        $url = rtrim($this->baseUrl, '/');
        
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'X-Tenant-ID: ' . $this->tenantId
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Limit redirects
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            echo "  [DEBUG] Curl error: $error\n";
            throw new Exception('Curl error: ' . $error);
        }
        
        curl_close($ch);
        
        // Debug: Show response for troubleshooting
        echo "  [DEBUG] HTTP Code: $httpCode\n";
        echo "  [DEBUG] Response length: " . strlen($response) . " bytes\n";
        if ($httpCode >= 400 || empty($response)) {
            echo "  [DEBUG] Error response (first 500 chars): " . substr($response, 0, 500) . "\n";
        } else {
            echo "  [DEBUG] Response (first 500 chars): " . substr($response, 0, 500) . "\n";
        }
        
        // Try to decode JSON
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "  [DEBUG] JSON decode error: " . json_last_error_msg() . "\n";
            throw new Exception('JSON decode error: ' . json_last_error_msg() . ". HTTP Code: $httpCode. Raw response: " . substr($response, 0, 500));
        }
        
        echo "  [DEBUG] Decoded response type: " . gettype($decodedResponse) . "\n";
        if (is_array($decodedResponse)) {
            echo "  [DEBUG] Decoded response keys: " . implode(', ', array_keys($decodedResponse)) . "\n";
        }
        
        return $decodedResponse;
    }

    /**
     * Clean up created test data
     */
    private function cleanup()
    {
        echo "\n=== Cleanup ===\n";
        
        $this->cleanupTestImage();
        
        if (empty($this->createdVoucherIds)) {
            echo "No vouchers to clean up.\n";
            return;
        }

        echo "Created voucher IDs during testing:\n";
        foreach ($this->createdVoucherIds as $voucherId) {
            echo "- Voucher ID: $voucherId\n";
        }
        
        echo "\nNote: Vouchers are stored in the database and file system.\n";
        echo "Please manually clean up test vouchers if needed.\n";
    }
}

// Run the tests
// 
// IMPORTANT: The vouchers endpoint requires JWT authentication and a tenant ID.
//
// How it works:
//   1. Login authenticates against the MASTER database (users are stored centrally)
//   2. After login, the test automatically fetches available tenants via /user/tenants
//   3. The test uses the first available tenant automatically
//   4. The X-Tenant-ID header tells the API which tenant database to access
//
// You have three options:
//
// Option 1: Use login endpoint (RECOMMENDED - auto-detects tenant)
//   - Update username/password in runAllTests() method (line ~141)
//   - Automatically fetches and uses first available tenant
//   $tester = new VoucherEndpointTest();
//   $tester->runAllTests();
//
// Option 2: Use pre-configured token with auto-detection
//   - Get token from a previous login
//   - Still auto-detects tenant from /user/tenants
//   $tester = new VoucherEndpointTest('your_jwt_token_here');
//   $tester->runAllTests();
//
// Option 3: Use pre-configured token with manual tenant ID
//   - Get token from a previous login
//   - Manually set tenant ID if auto-detection fails
//   $tester = new VoucherEndpointTest('your_jwt_token_here');
//   $tester->setTenantId(1); // Set specific tenant ID
//   $tester->runAllTests();
//
// To get a JWT token manually:
//   curl -X POST https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/auth/login.php \
//     -H "Content-Type: application/json" \
//     -d '{"username":"api","password":"your_password"}'

// Default: Try login (update password in runAllTests method)
$tester = new VoucherEndpointTest();
$tester->runAllTests();

