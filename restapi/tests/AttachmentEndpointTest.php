<?php

/**
 * Attachment Endpoint Test Script
 * 
 * This script tests all file operations for the Attachment API endpoint
 * including upload, download, list, delete, and image-to-PDF conversion.
 */

class AttachmentEndpointTest
{
    private $baseUrl;
    private $headers;
    private $uploadedFiles = [];
    private $testFilesDir;

    public function __construct()
    {
        // Configure your API base URL and headers - note the trailing slash
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/attachment/';
        
        // Set your actual authorization headers
        $this->headers = [
            'Authorization: 4M1SlprEv82hhtl2KSfCFOs4BzLYgAdUD',
            'X-SaldiUser: api',
            'X-DB: test_4'
        ];
        
        // Create temporary test files directory
        $this->testFilesDir = sys_get_temp_dir() . '/attachment_test_files_' . uniqid();
        mkdir($this->testFilesDir, 0755, true);
        
        $this->createTestFiles();
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "=== Attachment API Endpoint Tests ===\n\n";

        try {
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
            $this->testDeleteFile();
            $this->testDeleteNonExistentFile();
            $this->testGetNonExistentFile();
            $this->testUploadVariousImageFormats();
            $this->testFilenameSanitization();
            
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
        $response = $this->uploadFile($this->testFilesDir . '/' . $filename);
        
        if ($response['success'] && isset($response['data']['filename'])) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ Text file uploaded successfully: " . $response['data']['filename'] . "\n";
            
            // Verify file properties
            if (!isset($response['data']['size']) || !isset($response['data']['mimeType'])) {
                throw new Exception("Uploaded file missing required metadata");
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
        $response = $this->uploadFile($this->testFilesDir . '/' . $filename);
        
        if ($response['success'] && isset($response['data']['filename'])) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ Image file uploaded: " . $response['data']['filename'] . "\n";
            
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
        
        $files = ['test_data.csv', 'test_config.json'];
        
        foreach ($files as $filename) {
            $response = $this->uploadFile($this->testFilesDir . '/' . $filename);
            
            if ($response['success']) {
                $this->uploadedFiles[] = $response['data']['filename'];
                echo "✓ Uploaded: " . $response['data']['filename'] . "\n";
            } else {
                throw new Exception("Failed to upload $filename: " . ($response['message'] ?? 'Unknown error'));
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
        $response = $this->uploadFileWithCustomName($this->testFilesDir . '/test_document.txt', $customFilename);
        
        if ($response['success']) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ File uploaded with custom name: " . $response['data']['filename'] . "\n";
            
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
        
        // Upload the same file again
        $response = $this->uploadFile($this->testFilesDir . '/test_document.txt');
        
        if ($response['success']) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ Duplicate file uploaded: " . $response['data']['filename'] . "\n";
            
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
        $response = $this->makeRequest('POST');
        
        if (!$response['success'] && (
            strpos($response['message'], 'No file uploaded') !== false ||
            strpos($response['message'], 'file') !== false
        )) {
            echo "✓ Correctly rejected request without file\n";
        } else {
            echo "⚠ Warning: Invalid file upload handling may need improvement\n";
        }
        
        echo "\n";
    }

    /**
     * Test uploading large file
     */
    public function testUploadLargeFile()
    {
        echo "Testing: Upload Large File\n";
        
        $response = $this->uploadFile($this->testFilesDir . '/large_file.txt');
        
        if ($response['success']) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ Large file uploaded successfully: " . $response['data']['filename'] . "\n";
            echo "✓ File size: " . $response['data']['size'] . " bytes\n";
        } else {
            echo "⚠ Warning: Large file upload failed - " . ($response['message'] ?? 'Unknown error') . "\n";
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
        
        $imageFiles = ['test_image.png', 'test_image.gif'];
        
        foreach ($imageFiles as $filename) {
            $response = $this->uploadFile($this->testFilesDir . '/' . $filename);
            
            if ($response['success']) {
                $this->uploadedFiles[] = $response['data']['filename'];
                echo "✓ Uploaded $filename as: " . $response['data']['filename'] . "\n";
                
                // Check if converted to PDF
                if (pathinfo($response['data']['filename'], PATHINFO_EXTENSION) === 'pdf') {
                    echo "✓ $filename converted to PDF\n";
                }
            } else {
                echo "⚠ Warning: Failed to upload $filename - " . ($response['message'] ?? 'Unknown error') . "\n";
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
        
        $response = $this->uploadFile($this->testFilesDir . '/special chars & symbols!.txt');
        
        if ($response['success']) {
            $this->uploadedFiles[] = $response['data']['filename'];
            echo "✓ File with special characters uploaded as: " . $response['data']['filename'] . "\n";
            
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
     * Upload a file
     */
    private function uploadFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception("Test file does not exist: $filePath");
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Add authorization headers
        $headers = $this->headers;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Disable SSL verification for local testing
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Prepare file upload
        $postFields = [
            'file' => new CURLFile($filePath)
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        
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
     * Upload a file with custom filename
     */
    private function uploadFileWithCustomName($filePath, $customFilename)
    {
        if (!file_exists($filePath)) {
            throw new Exception("Test file does not exist: $filePath");
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $postFields = [
            'file' => new CURLFile($filePath),
            'filename' => $customFilename
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
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
    private function makeRequest($method, $data = null, $file = '')
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
        
        // Add headers
        $headers = $this->headers;
        if ($data && in_array($method, ['POST', 'PUT', 'DELETE'])) {
            $headers[] = 'Content-Type: application/json';
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
try {
    $tester = new AttachmentEndpointTest();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
