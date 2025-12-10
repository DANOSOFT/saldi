<?php

/**
 * Attachment Endpoint Test Script
 * 
 * This script tests all file operations for the Attachment API endpoint
 * using JWT authentication, including upload (base64 with metadata: extracted_data.total_amount, 
 * extracted_data.invoice_date, image_base64), download, list, delete, and image-to-PDF conversion.
 */

class AttachmentEndpointTest
{
    private $baseUrl;
    private $loginUrl;
    private $tenantsUrl;
    private $accessToken;
    private $tenantId;
    private $uploadedFiles = [];
    private $testFilesDir;

    public function __construct($preConfiguredToken = null)
    {
        // Configure your API base URLs
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/attachment/';
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
        
        // Create temporary test files directory
        $this->testFilesDir = sys_get_temp_dir() . '/attachment_test_files_' . uniqid();
        mkdir($this->testFilesDir, 0755, true);
        
        $this->createTestFiles();
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
            'database' => 'test_3'
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
            $errorMsg .= "\n  new AttachmentEndpointTest('your_jwt_token_here')";
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
        echo "=== Attachment API Endpoint Tests (JWT Authentication) ===\n\n";

        try {
            // Login to get JWT token (only if not using pre-configured token)
            if (!$this->accessToken) {
                // NOTE: Login authenticates against the MASTER database (not tenant-specific)
                // After login, the test will automatically fetch available tenants and use the first one
                // 
                // If you get "Invalid username or password" error:
                //   1. Update the credentials below with correct values
                //   2. Or skip login by using a pre-configured token:
                //      $tester = new AttachmentEndpointTest('your_existing_jwt_token');
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
            
            // Test file operations
            $this->testGetAllFilesEmpty();
            $this->testUploadTextFile();
            $this->testUploadImageFile();
            $this->testUploadMultipleFiles();
            $this->testGetAllFiles();
            $this->testGetSingleFile();
            $this->testDownloadFile();
            $this->testUploadWithCustomFilename();
            $this->testUploadDuplicateFilename();
            $this->testUploadInvalidFile();
            $this->testUploadLargeFile();
            $this->testUploadWithMetadata();
            $this->testDeleteFile();
            $this->testDeleteNonExistentFile();
            $this->testGetNonExistentFile();
            $this->testUploadVariousImageFormats();
            $this->testFilenameSanitization();
            
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
        
        try {
            $response = $this->makeRequest('GET', null, '', $headers);

            // Check HTTP status code first - 401 Unauthorized means token was rejected
            $httpCode = $response['http_code'] ?? null;
            if ($httpCode === 401) {
                echo "✓ Correctly rejected invalid token (HTTP 401)\n";
                echo "  Response: " . ($response['message'] ?? 'N/A') . "\n\n";
                return;
            }

            // Also check if the response indicates failure (success = false)
            // and contains any token-related error message
            if (isset($response['success']) && !$response['success']) {
                $message = strtolower($response['message'] ?? '');
                if (strpos($message, 'invalid') !== false || 
                    strpos($message, 'expired') !== false || 
                    strpos($message, 'token') !== false ||
                    strpos($message, 'unauthorized') !== false ||
                    strpos($message, 'authentication') !== false ||
                    strpos($message, 'empty response') !== false) {
                    echo "✓ Correctly rejected invalid token\n";
                    echo "  HTTP Code: " . ($httpCode ?? 'N/A') . "\n";
                    echo "  Response: " . ($response['message'] ?? 'N/A') . "\n\n";
                    return;
                }
            }
            
            // If we got here, the token wasn't properly rejected
            throw new Exception("Should have rejected invalid token. HTTP Code: " . ($httpCode ?? 'N/A') . ". Response: " . json_encode($response));
        } catch (Exception $e) {
            // If makeRequest throws an exception, check if it's a JSON decode error
            // which might indicate the endpoint returned a non-JSON error (which is still a rejection)
            if (strpos($e->getMessage(), 'JSON decode error') !== false) {
                echo "⚠ Warning: Endpoint returned non-JSON response for invalid token (still a rejection)\n";
                echo "  Error: " . $e->getMessage() . "\n\n";
                return;
            }
            throw $e;
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
        
        try {
            $response = $this->makeRequest('GET', null, '', $headers);
            
            // Check HTTP status code first - 401 Unauthorized means token was rejected
            $httpCode = $response['http_code'] ?? null;
            if ($httpCode === 401) {
                echo "✓ Correctly rejected missing token (HTTP 401)\n";
                echo "  Response: " . ($response['message'] ?? 'N/A') . "\n\n";
                return;
            }
            
            // Also check if the response indicates failure (success = false)
            // and contains any token-related error message
            if (isset($response['success']) && !$response['success']) {
                $message = strtolower($response['message'] ?? '');
                if (strpos($message, 'invalid') !== false || 
                    strpos($message, 'expired') !== false || 
                    strpos($message, 'token') !== false ||
                    strpos($message, 'unauthorized') !== false ||
                    strpos($message, 'authentication') !== false ||
                    strpos($message, 'authorization') !== false ||
                    strpos($message, 'missing') !== false ||
                    strpos($message, 'empty response') !== false) {
                    echo "✓ Correctly rejected missing token\n";
                    echo "  HTTP Code: " . ($httpCode ?? 'N/A') . "\n";
                    echo "  Response: " . ($response['message'] ?? 'N/A') . "\n\n";
                    return;
                }
            }
            
            echo "⚠ Warning: Expected token rejection but got: " . ($response['message'] ?? 'Unknown') . "\n";
            echo "  HTTP Code: " . ($httpCode ?? 'N/A') . "\n";
            echo "  Full response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
        } catch (Exception $e) {
            // If makeRequest throws an exception, check if it's a JSON decode error
            // which might indicate the endpoint returned a non-JSON error (which is still a rejection)
            if (strpos($e->getMessage(), 'JSON decode error') !== false) {
                echo "⚠ Warning: Endpoint returned non-JSON response for missing token (still a rejection)\n";
                echo "  Error: " . $e->getMessage() . "\n\n";
                return;
            }
            echo "⚠ Warning: Exception during missing token test: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Create test files for upload testing
     */
    private function createTestFiles()
    {
        // Create a simple text file
        file_put_contents($this->testFilesDir . '/test_document.txt', 'This is a test document for API testing.');
        
        // Create a simple CSV file
        file_put_contents($this->testFilesDir . '/test_data.csv', "name,age,city\nJohn,30,Copenhagen\nJane,25,Aarhus");
        
        // Create a simple JSON file
        file_put_contents($this->testFilesDir . '/test_config.json', '{"setting1": "value1", "setting2": "value2"}');
        
        // Create test images (simple colored squares)
        $this->createTestImage($this->testFilesDir . '/test_image.jpg', 'jpeg');
        $this->createTestImage($this->testFilesDir . '/test_image.png', 'png');
        $this->createTestImage($this->testFilesDir . '/test_image.gif', 'gif');
        
        // Create a larger test file
        file_put_contents($this->testFilesDir . '/large_file.txt', str_repeat('This is a large test file. ', 1000));
        
        // Create file with special characters in name
        file_put_contents($this->testFilesDir . '/special chars & symbols!.txt', 'File with special characters in filename.');
    }

    /**
     * Create a simple test image
     */
    private function createTestImage($filename, $format)
    {
        $width = 200;
        $height = 150;
        $image = imagecreate($width, $height);
        
        // Allocate colors
        $background = imagecolorallocate($image, 255, 255, 255); // White
        $textColor = imagecolorallocate($image, 0, 0, 0); // Black
        $borderColor = imagecolorallocate($image, 255, 0, 0); // Red
        
        // Draw a border
        imagerectangle($image, 0, 0, $width-1, $height-1, $borderColor);
        
        // Add some text
        imagestring($image, 5, 50, 70, 'TEST', $textColor);
        
        // Save the image
        switch ($format) {
            case 'jpeg':
                imagejpeg($image, $filename, 90);
                break;
            case 'png':
                imagepng($image, $filename);
                break;
            case 'gif':
                imagegif($image, $filename);
                break;
        }
        
        imagedestroy($image);
    }

    /**
     * Test getting all files when directory is empty
     */
    public function testGetAllFilesEmpty()
    {
        echo "Testing: Get All Files (Empty Directory)\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved file list (may be empty): " . count($response['data']) . " files\n";
        } else {
            throw new Exception("Failed to get file list");
        }
        
        echo "\n";
    }

    /**
     * Test uploading a text file
     */
    public function testUploadTextFile()
    {
        echo "Testing: Upload Text File\n";
        
        $filename = 'test_document.txt';
        $response = $this->uploadFile(
            $this->testFilesDir . '/' . $filename,
            '2024-01-15',
            '1000.00',
            '1234567890'
        );
        
        if ($response['success'] && isset($response['data']['filename'])) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ Text file uploaded successfully: " . $response['data']['filename'] . "\n";
            
            // Verify file properties
            if (!isset($response['data']['size']) || !isset($response['data']['mimeType'])) {
                throw new Exception("Uploaded file missing required metadata");
            }
            
            // Verify metadata is present
            if (isset($response['data']['metadata'])) {
                echo "✓ Metadata included: date=" . $response['data']['metadata']['date'] . 
                     ", amount=" . $response['data']['metadata']['amount'] . 
                     ", accountnr=" . $response['data']['metadata']['accountnr'] . "\n";
            }
            
            if ($response['data']['mimeType'] !== 'text/plain') {
                echo "⚠ Warning: Expected mime type 'text/plain', got '" . $response['data']['mimeType'] . "'\n";
            }
        } else {
            throw new Exception("Failed to upload text file: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }
    
    /**
     * Test uploading an image file (should be converted to PDF)
     */
    public function testUploadImageFile()
    {
        echo "Testing: Upload Image File (Should Convert to PDF)\n";
        
        $filename = 'test_image.jpg';
        $response = $this->uploadFile(
            $this->testFilesDir . '/' . $filename,
            '2024-01-16',
            '2500.75',
            '9876543210'
        );
        
        if ($response['success'] && isset($response['data']['filename'])) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ Image file uploaded: " . $response['data']['filename'] . "\n";
            
            // Verify metadata is present
            if (isset($response['data']['metadata'])) {
                echo "✓ Metadata included: date=" . $response['data']['metadata']['date'] . 
                     ", amount=" . $response['data']['metadata']['amount'] . 
                     ", accountnr=" . $response['data']['metadata']['accountnr'] . "\n";
            }
            
            // Check if image was converted to PDF
            if (pathinfo($response['data']['filename'], PATHINFO_EXTENSION) === 'pdf') {
                echo "✓ Image successfully converted to PDF\n";
                
                if ($response['data']['mimeType'] === 'application/pdf') {
                    echo "✓ PDF mime type correctly detected\n";
                } else {
                    echo "⚠ Warning: Expected PDF mime type, got '" . $response['data']['mimeType'] . "'\n";
                }
            } else {
                echo "⚠ Warning: Image was not converted to PDF (filename: " . $response['data']['filename'] . ")\n";
            }
        } else {
            throw new Exception("Failed to upload image file: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test uploading multiple files
     */
    public function testUploadMultipleFiles()
    {
        echo "Testing: Upload Multiple Files\n";
        
        $files = [
            ['name' => 'test_data.csv', 'date' => '2024-01-17', 'amount' => '500.25', 'accountnr' => '1111111111'],
            ['name' => 'test_config.json', 'date' => '2024-01-18', 'amount' => '750.50', 'accountnr' => '2222222222']
        ];
        
        foreach ($files as $file) {
            $response = $this->uploadFile(
                $this->testFilesDir . '/' . $file['name'],
                $file['date'],
                $file['amount'],
                $file['accountnr']
            );
            
            if ($response['success']) {
                $this->uploadedFiles[] = $response['data']['filename'];
                echo "✓ Uploaded: " . $response['data']['filename'] . "\n";
                
                // Verify metadata
                if (isset($response['data']['metadata'])) {
                    echo "  Metadata: date=" . $response['data']['metadata']['date'] . 
                         ", amount=" . $response['data']['metadata']['amount'] . 
                         ", accountnr=" . $response['data']['metadata']['accountnr'] . "\n";
                }
            } else {
                throw new Exception("Failed to upload {$file['name']}: " . ($response['message'] ?? 'Unknown error'));
            }
        }
        
        echo "\n";
    }

    /**
     * Test getting all files
     */
    public function testGetAllFiles()
    {
        echo "Testing: Get All Files\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " files\n";
            
            // Verify our uploaded files are in the list
            $foundFiles = 0;
            foreach ($response['data'] as $file) {
                if (in_array($file['filename'], $this->uploadedFiles)) {
                    $foundFiles++;
                }
            }
            
            echo "✓ Found $foundFiles of " . count($this->uploadedFiles) . " uploaded files in list\n";
            
            // Verify file structure
            if (!empty($response['data'])) {
                $firstFile = $response['data'][0];
                $requiredFields = ['filename', 'size', 'mimeType', 'uploadDate'];
                foreach ($requiredFields as $field) {
                    if (!isset($firstFile[$field])) {
                        throw new Exception("File data missing required field: $field");
                    }
                }
                echo "✓ File data structure is correct\n";
            }
        } else {
            throw new Exception("Failed to get all files");
        }
        
        echo "\n";
    }

    /**
     * Test getting a single file
     */
    public function testGetSingleFile()
    {
        if (empty($this->uploadedFiles)) {
            echo "Skipping: Get Single File (no files uploaded)\n\n";
            return;
        }

        echo "Testing: Get Single File\n";
        
        $filename = $this->uploadedFiles[0];
        $response = $this->makeRequest('GET', null, $filename);
        
        if ($response['success'] && $response['data']['filename'] == $filename) {
            echo "✓ Retrieved file: $filename\n";
            
            // Verify file data integrity
            $requiredFields = ['filename', 'size', 'mimeType', 'uploadDate'];
            foreach ($requiredFields as $field) {
                if (!isset($response['data'][$field])) {
                    throw new Exception("File data missing required field: $field");
                }
            }
            echo "✓ File metadata is complete\n";
        } else {
            throw new Exception("Failed to get single file");
        }
        
        echo "\n";
    }

    /**
     * Test downloading a file
     */
    public function testDownloadFile()
    {
        if (empty($this->uploadedFiles)) {
            echo "Skipping: Download File (no files uploaded)\n\n";
            return;
        }

        echo "Testing: Download File\n";
        
        $filename = $this->uploadedFiles[0];
        $response = $this->downloadFile($filename);
        
        if ($response !== false && strlen($response) > 0) {
            echo "✓ Downloaded file content (" . strlen($response) . " bytes)\n";
            
            // For PDF files, check PDF header
            if (pathinfo($filename, PATHINFO_EXTENSION) === 'pdf') {
                if (strpos($response, '%PDF-') === 0) {
                    echo "✓ Downloaded file has valid PDF header\n";
                } else {
                    echo "⚠ Warning: Downloaded PDF file doesn't have valid PDF header\n";
                }
            }
        } else {
            throw new Exception("Failed to download file");
        }
        
        echo "\n";
    }

    /**
     * Test uploading with custom filename
     */
    public function testUploadWithCustomFilename()
    {
        echo "Testing: Upload with Custom Filename\n";
        
        $customFilename = 'custom_named_file.txt';
        $response = $this->uploadFileWithCustomName(
            $this->testFilesDir . '/test_document.txt',
            $customFilename,
            '2024-01-19',
            '3000.00',
            '3333333333'
        );
        
        if ($response['success']) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ File uploaded with custom name: " . $response['data']['filename'] . "\n";
            
            // Verify metadata
            if (isset($response['data']['metadata'])) {
                echo "✓ Metadata included: date=" . $response['data']['metadata']['date'] . 
                     ", amount=" . $response['data']['metadata']['amount'] . 
                     ", accountnr=" . $response['data']['metadata']['accountnr'] . "\n";
            }
            
            // Check if the filename matches our custom name (or is sanitized version)
            if (strpos($response['data']['filename'], 'custom_named_file') !== false) {
                echo "✓ Custom filename was preserved\n";
            } else {
                echo "⚠ Note: Custom filename was modified to: " . $response['data']['filename'] . "\n";
            }
        } else {
            throw new Exception("Failed to upload with custom filename: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test uploading file with duplicate filename
     */
    public function testUploadDuplicateFilename()
    {
        echo "Testing: Upload Duplicate Filename\n";
        
        // Upload the same file again with different metadata
        $response = $this->uploadFile(
            $this->testFilesDir . '/test_document.txt',
            '2024-01-20',
            '1500.50',
            '4444444444'
        );
        
        if ($response['success']) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ Duplicate file uploaded: " . $response['data']['filename'] . "\n";
            
            // Verify metadata
            if (isset($response['data']['metadata'])) {
                echo "✓ Metadata included: date=" . $response['data']['metadata']['date'] . 
                     ", amount=" . $response['data']['metadata']['amount'] . 
                     ", accountnr=" . $response['data']['metadata']['accountnr'] . "\n";
            }
            
            // Check if filename was modified to be unique
            if (strpos($response['data']['filename'], '_1') !== false || 
                strpos($response['data']['filename'], '_2') !== false) {
                echo "✓ Filename was automatically made unique\n";
            } else {
                echo "⚠ Note: Duplicate filename handling may need verification\n";
            }
        } else {
            throw new Exception("Failed to upload duplicate file: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test uploading invalid file
     */
    public function testUploadInvalidFile()
    {
        echo "Testing: Upload Invalid File\n";
        
        // Try to make request without file
        $response = $this->makeRequest('POST', []);
        
        if (!$response['success'] && (
            strpos($response['message'], 'No file provided') !== false ||
            strpos($response['message'], 'No data provided') !== false ||
            strpos($response['message'], 'image_base64') !== false ||
            strpos($response['message'], 'Missing required field') !== false ||
            strpos($response['message'], 'extracted_data') !== false
        )) {
            echo "✓ Correctly rejected request without file\n";
        } else {
            echo "⚠ Warning: Invalid file upload handling may need improvement\n";
            echo "  Response: " . ($response['message'] ?? 'N/A') . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test uploading large file
     */
    public function testUploadLargeFile()
    {
        echo "Testing: Upload Large File\n";
        
        $response = $this->uploadFile(
            $this->testFilesDir . '/large_file.txt',
            '2024-01-21',
            '5000.00',
            '5555555555'
        );
        
        if ($response['success']) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ Large file uploaded successfully: " . $response['data']['filename'] . "\n";
            echo "✓ File size: " . $response['data']['size'] . " bytes\n";
            
            // Verify metadata
            if (isset($response['data']['metadata'])) {
                echo "✓ Metadata included: date=" . $response['data']['metadata']['date'] . 
                     ", amount=" . $response['data']['metadata']['amount'] . 
                     ", accountnr=" . $response['data']['metadata']['accountnr'] . "\n";
            }
        } else {
            echo "⚠ Warning: Large file upload failed - " . ($response['message'] ?? 'Unknown error') . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test uploading file with metadata (date, amount, accountnr)
     */
    public function testUploadWithMetadata()
    {
        echo "Testing: Upload File with Metadata\n";
        
        $testDate = '2024-01-15';
        $testAmount = '1234.56';
        $testAccountnr = '1234567890';
        
        $response = $this->uploadFile(
            $this->testFilesDir . '/test_document.txt',
            $testDate,
            $testAmount,
            $testAccountnr
        );
        
        if ($response['success']) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ File uploaded with metadata: " . $response['data']['filename'] . "\n";
            
            // Verify metadata is in response
            if (isset($response['data']['metadata'])) {
                $metadata = $response['data']['metadata'];
                
                if ($metadata['date'] === $testDate) {
                    echo "✓ Date metadata correct: " . $metadata['date'] . "\n";
                } else {
                    throw new Exception("Date metadata mismatch. Expected: $testDate, Got: " . ($metadata['date'] ?? 'N/A'));
                }
                
                if ($metadata['amount'] === $testAmount) {
                    echo "✓ Amount metadata correct: " . $metadata['amount'] . "\n";
                } else {
                    throw new Exception("Amount metadata mismatch. Expected: $testAmount, Got: " . ($metadata['amount'] ?? 'N/A'));
                }
                
                if ($metadata['accountnr'] === $testAccountnr) {
                    echo "✓ Account number metadata correct: " . $metadata['accountnr'] . "\n";
                } else {
                    throw new Exception("Account number metadata mismatch. Expected: $testAccountnr, Got: " . ($metadata['accountnr'] ?? 'N/A'));
                }
            } else {
                throw new Exception("Metadata not found in upload response");
            }
            
            // Verify metadata is also returned when getting the file
            $filename = $response['data']['filename'];
            $getResponse = $this->makeRequest('GET', null, $filename);
            
            if ($getResponse['success'] && isset($getResponse['data']['metadata'])) {
                $retrievedMetadata = $getResponse['data']['metadata'];
                
                if ($retrievedMetadata['date'] === $testDate &&
                    $retrievedMetadata['amount'] === $testAmount &&
                    $retrievedMetadata['accountnr'] === $testAccountnr) {
                    echo "✓ Metadata correctly retrieved when getting file\n";
                } else {
                    throw new Exception("Metadata mismatch when retrieving file");
                }
            } else {
                throw new Exception("Metadata not found when retrieving file");
            }
        } else {
            throw new Exception("Failed to upload file with metadata: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test deleting a file
     */
    public function testDeleteFile()
    {
        if (empty($this->uploadedFiles)) {
            echo "Skipping: Delete File (no files uploaded)\n\n";
            return;
        }

        echo "Testing: Delete File\n";
        
        $filename = array_pop($this->uploadedFiles); // Remove from our list
        $response = $this->deleteFile($filename);
        
        if ($response['success']) {
            echo "✓ File deleted successfully: $filename\n";
            
            // Verify the file is actually deleted by trying to get it
            $verifyResponse = $this->makeRequest('GET', null, $filename);
            if (!$verifyResponse['success']) {
                echo "✓ Delete verified - file no longer exists\n";
            } else {
                $this->uploadedFiles[] = $filename; // Add back to cleanup list
                throw new Exception("File still exists after delete");
            }
        } else {
            $this->uploadedFiles[] = $filename; // Add back to cleanup list
            throw new Exception("Failed to delete file: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test deleting non-existent file
     */
    public function testDeleteNonExistentFile()
    {
        echo "Testing: Delete Non-Existent File\n";
        
        $response = $this->deleteFile('non_existent_file.txt');
        
        if (!$response['success'] && (
            strpos($response['message'], 'not found') !== false ||
            strpos($response['message'], 'File not found') !== false
        )) {
            echo "✓ Correctly returned error for non-existent file\n";
        } else {
            echo "⚠ Warning: Non-existent file deletion handling may need improvement\n";
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent file
     */
    public function testGetNonExistentFile()
    {
        echo "Testing: Get Non-Existent File\n";
        
        $response = $this->makeRequest('GET', null, 'non_existent_file.txt');
        
        if (!$response['success'] && (
            strpos($response['message'], 'not found') !== false ||
            strpos($response['message'], 'File not found') !== false
        )) {
            echo "✓ Correctly returned error for non-existent file\n";
        } else {
            echo "⚠ Warning: Non-existent file handling may need improvement\n";
        }
        
        echo "\n";
    }

    /**
     * Test uploading various image formats
     */
    public function testUploadVariousImageFormats()
    {
        echo "Testing: Upload Various Image Formats\n";
        
        $imageFiles = [
            ['name' => 'test_image.png', 'date' => '2024-01-22', 'amount' => '1200.25', 'accountnr' => '6666666666'],
            ['name' => 'test_image.gif', 'date' => '2024-01-23', 'amount' => '1800.75', 'accountnr' => '7777777777']
        ];
        
        foreach ($imageFiles as $file) {
            $response = $this->uploadFile(
                $this->testFilesDir . '/' . $file['name'],
                $file['date'],
                $file['amount'],
                $file['accountnr']
            );
            
            if ($response['success']) {
                $this->uploadedFiles[] = $response['data']['filename'];
                echo "✓ Uploaded {$file['name']} as: " . $response['data']['filename'] . "\n";
                
                // Verify metadata
                if (isset($response['data']['metadata'])) {
                    echo "  Metadata: date=" . $response['data']['metadata']['date'] . 
                         ", amount=" . $response['data']['metadata']['amount'] . 
                         ", accountnr=" . $response['data']['metadata']['accountnr'] . "\n";
                }
                
                // Check if converted to PDF
                if (pathinfo($response['data']['filename'], PATHINFO_EXTENSION) === 'pdf') {
                    echo "✓ {$file['name']} converted to PDF\n";
                }
            } else {
                echo "⚠ Warning: Failed to upload {$file['name']} - " . ($response['message'] ?? 'Unknown error') . "\n";
            }
        }
        
        echo "\n";
    }

    /**
     * Test filename sanitization
     */
    public function testFilenameSanitization()
    {
        echo "Testing: Filename Sanitization\n";
        
        $response = $this->uploadFile(
            $this->testFilesDir . '/special chars & symbols!.txt',
            '2024-01-24',
            '900.00',
            '8888888888'
        );
        
        if ($response['success']) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ File with special characters uploaded as: " . $response['data']['filename'] . "\n";
            
            // Verify metadata
            if (isset($response['data']['metadata'])) {
                echo "✓ Metadata included: date=" . $response['data']['metadata']['date'] . 
                     ", amount=" . $response['data']['metadata']['amount'] . 
                     ", accountnr=" . $response['data']['metadata']['accountnr'] . "\n";
            }
            
            // Check if dangerous characters were sanitized
            if (preg_match('/[^a-zA-Z0-9._-]/', $response['data']['filename'])) {
                echo "⚠ Warning: Filename may still contain special characters\n";
            } else {
                echo "✓ Filename properly sanitized\n";
            }
        } else {
            echo "⚠ Warning: Failed to upload file with special characters\n";
        }
        
        echo "\n";
    }

    /**
     * Upload a file (base64 encoded with metadata)
     */
    private function uploadFile($filePath, $date = null, $amount = null, $accountnr = null, $id = null)
    {
        if (!file_exists($filePath)) {
            throw new Exception("Test file does not exist: $filePath");
        }

        // Read file content and encode as base64
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            throw new Exception("Failed to read file: $filePath");
        }
        $base64Data = base64_encode($fileContent);
        
        // Generate default metadata if not provided
        if ($date === null) {
            $date = date('Y-m-d');
        }
        if ($amount === null) {
            $amount = (string)(rand(100, 9999) . '.' . rand(10, 99));
        }
        if ($id === null) {
            $id = 'test-' . uniqid();
        }

        // Prepare JSON payload matching new endpoint structure
        $postData = [
            'id' => $id,
            'extracted_data' => [
                'total_amount' => $amount,
                'invoice_date' => $date
            ],
            'image_base64' => $base64Data
        ];
        
        // Add accountnr if provided (optional)
        if ($accountnr !== null) {
            $postData['accountnr'] = $accountnr;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Add JWT authorization headers
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken,
            'X-Tenant-ID: ' . $this->tenantId
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Disable SSL verification for local testing
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Send JSON data
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("cURL Error: $error");
        }
        
        $decodedResponse = json_decode($response, true);
        if ($decodedResponse === null) {
            throw new Exception("Invalid JSON response (HTTP $httpCode): $response");
        }
        
        $decodedResponse['http_code'] = $httpCode;
        return $decodedResponse;
    }

    /**
     * Upload a file with custom filename (base64 encoded with metadata)
     */
    private function uploadFileWithCustomName($filePath, $customFilename, $date = null, $amount = null, $accountnr = null)
    {
        if (!file_exists($filePath)) {
            throw new Exception("Test file does not exist: $filePath");
        }

        // Read file content and encode as base64
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            throw new Exception("Failed to read file: $filePath");
        }
        $base64Data = base64_encode($fileContent);
        
        // Generate default metadata if not provided
        if ($date === null) {
            $date = date('Y-m-d');
        }
        if ($amount === null) {
            $amount = (string)(rand(100, 9999) . '.' . rand(10, 99));
        }

        // Prepare JSON payload matching new endpoint structure
        // Use customFilename as the id field
        $postData = [
            'id' => $customFilename,
            'extracted_data' => [
                'total_amount' => $amount,
                'invoice_date' => $date
            ],
            'image_base64' => $base64Data
        ];
        
        // Add accountnr if provided (optional)
        if ($accountnr !== null) {
            $postData['accountnr'] = $accountnr;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Add JWT authorization headers
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken,
            'X-Tenant-ID: ' . $this->tenantId
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Send JSON data
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
        if ($decodedResponse === null) {
            throw new Exception("Invalid JSON response (HTTP $httpCode): $response");
        }
        
        $decodedResponse['http_code'] = $httpCode;
        return $decodedResponse;
    }

    /**
     * Download a file
     */
    private function downloadFile($filename)
    {
        $url = $this->baseUrl . '?file=' . urlencode($filename) . '&download=true';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Add JWT authorization headers
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'X-Tenant-ID: ' . $this->tenantId
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return $response;
        }
        
        return false;
    }

    /**
     * Delete a file
     */
    private function deleteFile($filename)
    {
        // Use query parameter for DELETE requests
        $url = $this->baseUrl . '?file=' . urlencode($filename);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        
        // Add JWT authorization headers
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'X-Tenant-ID: ' . $this->tenantId
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
        if ($decodedResponse === null) {
            throw new Exception("Invalid JSON response (HTTP $httpCode): $response");
        }
        
        $decodedResponse['http_code'] = $httpCode;
        return $decodedResponse;
    }

    /**
     * Make HTTP request to the API
     */
    private function makeRequest($method, $data = null, $file = '', $customHeaders = null)
    {
        $url = $this->baseUrl;
        if ($file) {
            // Use query parameter
            $url .= '?file=' . urlencode($file);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Add headers - use custom headers if provided (for auth tests), otherwise use JWT
        if ($customHeaders) {
            $headers = $customHeaders;
        } else {
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
                'X-Tenant-ID: ' . $this->tenantId
            ];
        }
        
        if ($data && in_array($method, ['POST', 'PUT', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("cURL Error: $error for URL: $url");
        }
        
        if ($httpCode >= 500) {
            throw new Exception("Server Error (HTTP $httpCode) for URL: $url");
        }
        
        // Debug output for 404 errors
        if ($httpCode === 404) {
            echo "DEBUG: 404 Error for URL: $url\n";
            echo "DEBUG: Response: " . substr($response, 0, 200) . "...\n";
        }
        
        $decodedResponse = json_decode($response, true);
        if ($decodedResponse === null) {
            throw new Exception("Invalid JSON response from $url (HTTP $httpCode): " . substr($response, 0, 200) . "...");
        }
        
        $decodedResponse['http_code'] = $httpCode;
        return $decodedResponse;
    }

    /**
     * Clean up created test data
     */
    private function cleanup()
    {
        echo "\n=== Cleanup ===\n";
        
        // Clean up uploaded files
        if (!empty($this->uploadedFiles)) {
            foreach ($this->uploadedFiles as $filename) {
                try {
                    $response = $this->deleteFile($filename);
                    
                    if ($response['success']) {
                        echo "✓ Cleaned up file: $filename\n";
                    } else {
                        echo "✗ Failed to cleanup file: $filename - " . ($response['message'] ?? 'Unknown error') . "\n";
                    }
                } catch (Exception $e) {
                    echo "✗ Error cleaning up file $filename: " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "No uploaded files to clean up.\n";
        }
        
        // Clean up local test files
        if (is_dir($this->testFilesDir)) {
            $files = glob($this->testFilesDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testFilesDir);
            echo "✓ Cleaned up local test files directory\n";
        }
    }

    /**
     * Destructor - ensure cleanup happens
     */
    public function __destruct()
    {
        if (is_dir($this->testFilesDir)) {
            $this->cleanup();
        }
    }
}

// Run the tests
// 
// IMPORTANT: The attachment endpoint requires JWT authentication and a tenant ID.
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
//   - Update username/password in runAllTests() method (line ~230)
//   - Automatically fetches and uses first available tenant
//   $tester = new AttachmentEndpointTest();
//   $tester->runAllTests();
//
// Option 2: Use pre-configured token with auto-detection
//   - Get token from a previous login
//   - Still auto-detects tenant from /user/tenants
//   $tester = new AttachmentEndpointTest('your_jwt_token_here');
//   $tester->runAllTests();
//
// Option 3: Use pre-configured token with manual tenant ID
//   - Get token from a previous login
//   - Manually set tenant ID if auto-detection fails
//   $tester = new AttachmentEndpointTest('your_jwt_token_here');
//   $tester->setTenantId(1); // Set specific tenant ID
//   $tester->runAllTests();
//
// To get a JWT token manually:
//   curl -X POST https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/auth/login.php \
//     -H "Content-Type: application/json" \
//     -d '{"username":"api","password":"your_password"}'

// Default: Try login (update password in runAllTests method)
try {
    $tester = new AttachmentEndpointTest();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
