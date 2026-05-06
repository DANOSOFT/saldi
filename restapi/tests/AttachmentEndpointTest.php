<?php

/**
 * Attachment Endpoint Test Script
 * 
 * This script tests the attachment upload endpoint
 */

class AttachmentEndpointTest
{
    private $authUrl;
    private $attachmentUrl;
    private $headers;
    private $accessToken;

    // Test credentials
    private $testUsername = 'api';
    private $testPassword = 'api';
    private $testAccountName = 'test2';

    public function __construct()
    {
        $this->authUrl = 'http://localhost/pblm/restapi/endpoints/v1/auth/login.php';
        $this->attachmentUrl = 'http://localhost/pblm/restapi/endpoints/v1/attachment/index.php';
        
        $this->headers = [
            'Content-Type: application/json'
        ];
    }

    public function run()
    {
        echo "=== Attachment Endpoint Test ===\n\n";

        try {
            // Step 1: Login
            $this->login();

            // Step 2: Upload File
            $this->testUploadFile();

            echo "\n=== Test Summary ===\n";
            echo "Test completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    private function login()
    {
        echo "Step 1: Authenticating...\n";

        $loginData = [
            'username' => $this->testUsername,
            'password' => $this->testPassword,
            'account_name' => $this->testAccountName
        ];

        $response = $this->makeRequest('POST', $this->authUrl, $loginData);
        
        if ($response['success']) {
            $this->accessToken = $response['data']['access_token'];
            $this->headers[] = 'Authorization: Bearer ' . $this->accessToken;
            // Also add X-Tenant-ID header if needed, though JWT usually handles it
            if (isset($response['data']['tenant']['db'])) {
                $this->headers[] = 'X-Tenant-ID: ' . $response['data']['tenant']['db'];
            }
            echo "✓ Login successful. Token received.\n\n";
        } else {
            throw new Exception("Login failed: " . ($response['message'] ?? 'Unknown error'));
        }
    }

    private function testUploadFile()
    {
        echo "Step 2: Uploading File...\n";

        // Create a minimal valid PDF base64
        $base64Pdf = 'JVBERi0xLjQKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nF2MwQuCMRCH7/sV8+2wCOu6614Q8SAeFPEg6tH2UFtqS/8/P3gQHmam3zfMMLyrg1t0g67hO/RCDc9oD/f5DqM/aI/mGf0x+Q2mY/0LcxTDO2X6hczCoFMSJHwkChJqQy06K5aGCEqvzBOyMvMknCIn5UReLzT9AM2NI9kKZW5kc3RyZWFtCmVuZG9iagoKMyAwIG9iago3NAplbmRvYmoKCjUgMCBvYmoKPDwKPj4KZW5kb2JqCgo2IDAgb2JqCjw8L0ZvbnQgNSAwIFIvUHJvY1NldFsvUERGL1RleHRdPj4KZW5kb2JqCgoxIDAgb2JqCjw8L1R5cGUvUGFnZS9QYXJlbnQgNCAwIFIvUmVzb3VyY2VzIDYgMCBSL01lZGlhQm94WzAgMCA1OTUuMjggODQxLjg5XS9Hcm91cDw8L1MvVHJhbnNwYXJlbmN5L0NTL0RldmljZVJHQi9JIHRydWU+Pi9Db250ZW50cyAyIDAgUj4+CmVuZG9iagoKNCAwIG9iago8PC9UeXBlL1BhZ2VzL1Jlc291cmNlcyA2IDAgUi9NZWRpYUJveFsgMCAwIDU5NS4yOCA4NDEuODkgXS9LaWRzWyAxIDAgUiBdL0NvdW50IDE+PgplbmRvYmoKCjcgMCBvYmoKPDwvVHlwZS9DYXRhbG9nL1BhZ2VzIDQgMCBSPj4KZW5kb2JqCgo4IDAgb2JqCjw8L1MvRD4+CmVuZG9iagoKOXYgMCBvYmoKPDwvQ3JlYXRvcihwZGYybWFrZSkvUHJvZHVjZXIocGRmMm1ha2UpL0NyZWF0aW9uRGF0ZShEOjIwMjUwMjA1MTAxMTE1Wik+PgplbmRvYmoKCnhyZWYKMCAxMAowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyMjAgMDAwMDAgbiAKMDAwMDAwMDAxNSAwMDAwMCBuIAowMDAwMDAwMTgzIDAwMDAwIG4gCjAwMDAwMDAzNjMgMDAwMDAgbiAKMDAwMDAwMDIwMSAwMDAwMCBuIAowMDAwMDAwMjIzIDAwMDAwIG4gCjAwMDAwMDA0NjAgMDAwMDAgbiAKMDAwMDAwMDUwNSAwMDAwMCBuIAowMDAwMDAwNTM2IDAwMDAwIG4gCnRyYWlsZXIKPDwvU2l6ZSAxMC9Sb290IDcgMCBSL0luZm8gOSAwIFIvSURbPDU4ODJDN0FFQkMwMUM4OUYxMzA2ODdFOUQ1QzU4RkE2Pjw1ODgyQzdBRUJDMDFDODlGMTMwNjg3RTlENUM1OEZBNj5dPj4Kc3RhcnR4cmVmCjYzOQolJUVPRgo=';

        $uploadData = [
            'image_base64' => $base64Pdf,
            'filename' => 'test_upload_api.pdf',
            'accountnr' => 'test_account',
            'extracted_data' => [
                'total_amount' => '456.78',
                'invoice_date' => date('Y-m-d'),
                'invoice_number' => 'INV-TEST-PDF-001',
                'invoice_description' => 'Test PDF upload via API'
            ]
        ];

        $response = $this->makeRequest('POST', $this->attachmentUrl, $uploadData);

        if ($response['success']) {
            echo "✓ File uploaded successfully.\n";
            echo "Response Data: " . print_r($response['data'], true) . "\n";
            
            // Check if metadata matches what we sent
            if (isset($response['data']['metadata'])) {
                $meta = $response['data']['metadata'];
                echo "✓ Metadata verified.\n";
            }
        } else {
            throw new Exception("Upload failed: " . ($response['message'] ?? 'Unknown error') . " HTTP Code: " . $response['http_code']);
        }
    }

    private function makeRequest($method, $url, $data = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Disable SSL verification for local testing
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
        
        $decodedResponse = json_decode($response, true);
        
        if ($decodedResponse === null) {
            // Allow for non-JSON responses (like HTML error pages) to be seen for debugging
            if ($httpCode >= 400) {
                 throw new Exception("Server Error (HTTP $httpCode). Response: " . substr($response, 0, 500));
            }
            throw new Exception("Invalid JSON response (HTTP $httpCode)");
        }
        
        $decodedResponse['http_code'] = $httpCode;
        
        return $decodedResponse;
    }
}

// Run the test
$test = new AttachmentEndpointTest();
$test->run();
