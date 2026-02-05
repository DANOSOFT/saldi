<?php

class AttachmentModel 
{
    private $filename;
    private $filepath;
    private $size;
    private $mimeType;
    private $uploadDate;
    private $metadata;
    private $lastError = null;
    private static $baseUploadDir = '/var/www/html/pblm/bilag/';
    private static $db = null;
    private static $logFile = '/var/www/html/pblm/bilag/attachment_debug.log';
    
    /**
     * Log debug message to file
     * 
     * @param string $message Message to log
     * @param mixed $data Optional data to include
     */
    private static function debugLog($message, $data = null)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}";
        if ($data !== null) {
            $logMessage .= " | Data: " . print_r($data, true);
        }
        $logMessage .= "\n";
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Constructor
     * 
     * @param string|null $filename Optional filename to load existing file
     * @param string|null $db Database identifier from header
     */
    public function __construct($filename = null, $db = null)
    {
        if ($db !== null) {
            self::setDatabase($db);
        }
        
        if ($filename !== null) {
            $this->loadFromFilename($filename);
        }
    }
    
    /**
     * Set the database identifier
     * 
     * @param string $db Database identifier
     */
    public static function setDatabase($db)
    {
        self::$db = $db;
    }
    
    /**
     * Get the upload directory for the current database
     * 
     * @return string Upload directory path
     */
    private static function getUploadDir()
    {
        if (self::$db === null) {
            // Try to get from headers if not set
            $headers = getallheaders();
            if ($headers) {
                $headers = array_change_key_case($headers, CASE_LOWER);
                if (isset($headers['x-db'])) {
                    self::$db = $headers['x-db'];
                }
            }
        }
        
        return self::$baseUploadDir . (self::$db ?: 'default') . '/pulje/';
    }
    
    /**
     * Load file details from filesystem
     * 
     * @param string $filename
     * @return bool Success status
     */
    private function loadFromFilename($filename)
    {
        $uploadDir = self::getUploadDir();
        $filepath = $uploadDir . $filename;
        
        if (file_exists($filepath)) {
            $this->filename = $filename;
            $this->filepath = $filepath;
            $this->size = filesize($filepath);
            $this->mimeType = mime_content_type($filepath);
            $this->uploadDate = date('Y-m-d H:i:s', filemtime($filepath));
            
            // Load metadata if it exists
            $this->loadMetadata($filepath);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Load metadata from text file if it exists
     * Format: filename (without ext), account, amount, date (one per line)
     * 
     * @param string $filepath Path to the main file
     * @return void
     */
    private function loadMetadata($filepath)
    {
        // Get filename without extension and directory
        $pathInfo = pathinfo($filepath);
        $filenameWithoutExt = $pathInfo['filename'];
        $directory = isset($pathInfo['dirname']) ? $pathInfo['dirname'] . '/' : '';
        
        // Create metadata path as filename.info (without the file extension)
        $metadataPath = $directory . $filenameWithoutExt . '.info';
        if (file_exists($metadataPath)) {
            $content = file_get_contents($metadataPath);
            if ($content !== false) {
                // Split by lines
                $lines = explode("\n", trim($content));
                
                // Parse the format: filename, account, amount, date, invoiceNumber, invoiceDescription
                if (count($lines) >= 3) {
                    $this->metadata = [
                        'accountnr' => isset($lines[1]) ? trim($lines[1]) : '',
                        'amount' => isset($lines[2]) ? trim($lines[2]) : '',
                        'date' => isset($lines[3]) ? trim($lines[3]) : '',
                        'invoiceNumber' => isset($lines[4]) ? trim($lines[4]) : '',
                        'invoiceDescription' => isset($lines[5]) ? trim($lines[5]) : ''
                    ];
                } else {
                    // Try to parse as JSON for backward compatibility
                    $metadata = json_decode($content, true);
                    if ($metadata !== null) {
                        $this->metadata = $metadata;
                    }
                }
            }
        }
    }
    
    /**
     * Get all files in the upload directory
     * 
     * @return AttachmentModel[] Array of AttachmentModel objects
     */
    public static function getAllFiles()
    {
        $files = [];
        $uploadDir = self::getUploadDir();
        
        if (!is_dir($uploadDir)) {
            return $files;
        }
        
        $directory = scandir($uploadDir);
        
        foreach ($directory as $file) {
            if ($file !== '.' && $file !== '..' && is_file($uploadDir . $file)) {
                $attachment = new AttachmentModel($file);
                if ($attachment->getFilename()) {
                    $files[] = $attachment;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Save uploaded file
     * 
     * @param array $uploadedFile $_FILES array element
     * @param string|null $customFilename Optional custom filename
     * @return bool Success status
     */
    public function saveUploadedFile($uploadedFile, $customFilename = null)
    {
        self::debugLog('=== saveUploadedFile START ===');
        self::debugLog('Uploaded file info', [
            'name' => isset($uploadedFile['name']) ? $uploadedFile['name'] : 'N/A',
            'type' => isset($uploadedFile['type']) ? $uploadedFile['type'] : 'N/A',
            'size' => isset($uploadedFile['size']) ? $uploadedFile['size'] : 'N/A',
            'tmp_name' => isset($uploadedFile['tmp_name']) ? $uploadedFile['tmp_name'] : 'N/A',
            'error' => isset($uploadedFile['error']) ? $uploadedFile['error'] : 'N/A',
            'customFilename' => $customFilename
        ]);
        
        if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
            self::debugLog('ERROR: Invalid uploaded file - tmp_name missing or not uploaded file');
            $this->lastError = 'Invalid uploaded file - tmp_name missing or not a valid uploaded file';
            return false;
        }
        
        $uploadDir = self::getUploadDir();
        self::debugLog('Upload directory', $uploadDir);
        
        // Ensure upload directory exists with better error handling
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $this->lastError = 'Failed to create upload directory: ' . $uploadDir;
                return false;
            }
        }
        
        // Double-check that directory was created and is writable
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            $this->lastError = 'Upload directory does not exist or is not writable: ' . $uploadDir;
            return false;
        }
        
        // Use custom filename or original filename
        $filename = $customFilename ?: $uploadedFile['name'];
        
        // Check if uploaded file is an image and convert to PDF
        $mimeType = mime_content_type($uploadedFile['tmp_name']);
        self::debugLog('Detected mime type', $mimeType);
        
        $convertedFile = null;
        $isConverted = false;
        
        if ($this->isImage($mimeType)) {
            self::debugLog('File is an image, attempting conversion to PDF');
            $convertedFile = $this->convertImageToPdf($uploadedFile['tmp_name'], $filename);
            if ($convertedFile) {
                // Update filename to have .pdf extension
                $pathInfo = pathinfo($filename);
                $filename = $pathInfo['filename'] . '.pdf';
                $isConverted = true;
                self::debugLog('Image converted to PDF', ['convertedFile' => $convertedFile, 'newFilename' => $filename]);
            } else {
                self::debugLog('ERROR: Image to PDF conversion failed');
            }
        }
        
        // Sanitize filename
        $filename = $this->sanitizeFilename($filename);
        
        // Check if file already exists and generate unique name if needed
        $originalFilename = $filename;
        $counter = 1;
        while (file_exists($uploadDir . $filename)) {
            $pathInfo = pathinfo($originalFilename);
            $filename = $pathInfo['filename'] . '_' . $counter . '.' . $pathInfo['extension'];
            $counter++;
        }
        
        $filepath = $uploadDir . $filename;
        
        $success = false;
        
        self::debugLog('Saving file', ['filepath' => $filepath, 'isConverted' => $isConverted]);
        
        if ($isConverted && $convertedFile) {
            // For converted files, use copy() instead of move_uploaded_file()
            $success = copy($convertedFile, $filepath);
            self::debugLog('Copy result (converted)', $success);
        } else {
            // For original uploaded files, use move_uploaded_file()
            $success = move_uploaded_file($uploadedFile['tmp_name'], $filepath);
            self::debugLog('move_uploaded_file result', $success);
        }
        
        if ($success) {
            $this->filename = $filename;
            $this->filepath = $filepath;
            $this->size = filesize($filepath);
            $this->mimeType = mime_content_type($filepath);
            $this->uploadDate = date('Y-m-d H:i:s');
            
            self::debugLog('=== saveUploadedFile SUCCESS ===', [
                'filename' => $this->filename,
                'size' => $this->size,
                'mimeType' => $this->mimeType
            ]);
            
            // Clean up temporary converted file if it was created
            if ($convertedFile && file_exists($convertedFile)) {
                @unlink($convertedFile);
            }
            
            return true;
        } else {
            self::debugLog('=== saveUploadedFile FAILED ===');
            // Clean up temporary converted file if it was created and save failed
            if ($convertedFile && file_exists($convertedFile)) {
                @unlink($convertedFile);
            }
            $this->lastError = 'Failed to save file to destination: ' . $filepath;
            return false;
        }
    }
    
    /**
     * Save base64 decoded file content
     * 
     * @param string $fileContent Decoded file content
     * @param string|null $customFilename Optional custom filename
     * @param array|null $metadata Optional metadata (date, amount, accountnr)
     * @return bool Success status
     */
    public function saveBase64File($fileContent, $customFilename = null, $metadata = null)
    {
        self::debugLog('=== saveBase64File START ===');
        self::debugLog('Input info', [
            'contentLength' => strlen($fileContent),
            'contentFirst100' => substr($fileContent, 0, 100),
            'customFilename' => $customFilename,
            'metadata' => $metadata
        ]);
        
        if (empty($fileContent)) {
            self::debugLog('ERROR: Empty file content');
            $this->lastError = 'Empty file content provided';
            return false;
        }
        
        $uploadDir = self::getUploadDir();
        
        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $this->lastError = 'Failed to create upload directory: ' . $uploadDir;
                return false;
            }
        }
        
        // Double-check that directory was created and is writable
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            $this->lastError = 'Upload directory does not exist or is not writable: ' . $uploadDir;
            return false;
        }
        
        // Create temporary file to process the content
        $tempFile = tempnam(sys_get_temp_dir(), 'base64_upload_');
        if ($tempFile === false) {
            $this->lastError = 'Failed to create temporary file';
            return false;
        }
        
        // Write content to temporary file
        if (file_put_contents($tempFile, $fileContent) === false) {
            @unlink($tempFile);
            $this->lastError = 'Failed to write content to temporary file';
            return false;
        }
        
        // Detect mime type
        $mimeType = mime_content_type($tempFile);
        self::debugLog('Temp file created', [
            'tempFile' => $tempFile,
            'tempFileSize' => filesize($tempFile),
            'mimeType' => $mimeType
        ]);
        
        // Determine filename
        $filename = $customFilename;
        if (!$filename) {
            // Try to determine extension from mime type
            $extension = $this->getExtensionFromMimeType($mimeType);
            $filename = 'upload_' . date('YmdHis') . '.' . $extension;
        }
        self::debugLog('Filename determined', $filename);
        
        // Check if file is an image and convert to PDF
        $convertedFile = null;
        $isConverted = false;
        
        if ($this->isImage($mimeType)) {
            self::debugLog('Image detected, attempting PDF conversion', [
                'mimeType' => $mimeType,
                'tempFile' => $tempFile,
                'filename' => $filename
            ]);
            $convertedFile = $this->convertImageToPdf($tempFile, $filename);
            if ($convertedFile) {
                // Update filename to have .pdf extension
                $pathInfo = pathinfo($filename);
                $filename = $pathInfo['filename'] . '.pdf';
                $isConverted = true;
                self::debugLog('PDF conversion successful', [
                    'convertedFile' => $convertedFile,
                    'newFilename' => $filename
                ]);
            } else {
                self::debugLog('PDF conversion FAILED - convertedFile is false');
            }
        }
        
        // Sanitize filename
        $filename = $this->sanitizeFilename($filename);
        
        // Check if file already exists and generate unique name if needed
        $originalFilename = $filename;
        $counter = 1;
        while (file_exists($uploadDir . $filename)) {
            $pathInfo = pathinfo($originalFilename);
            $filename = $pathInfo['filename'] . '_' . $counter . '.' . $pathInfo['extension'];
            $counter++;
        }
        
        $filepath = $uploadDir . $filename;
        
        $success = false;
        
        if ($isConverted && $convertedFile) {
            // For converted files, use copy()
            $success = copy($convertedFile, $filepath);
        } else {
            // For original files, use copy() from temp file
            $success = copy($tempFile, $filepath);
        }
        
        if ($success) {
            $this->filename = $filename;
            $this->filepath = $filepath;
            $this->size = filesize($filepath);
            $this->mimeType = mime_content_type($filepath);
            $this->uploadDate = date('Y-m-d H:i:s');
            
            self::debugLog('=== saveBase64File SUCCESS ===', [
                'filename' => $this->filename,
                'size' => $this->size,
                'mimeType' => $this->mimeType
            ]);
            
            // Store metadata in object
            if ($metadata !== null && is_array($metadata)) {
                $this->metadata = $metadata;
            }
            
            // Insert into pool_files database table (same as docPool.php)
            $this->insertToPoolFiles($filename, $metadata);
            
            // Clean up temporary files
            @unlink($tempFile);
            if ($convertedFile && file_exists($convertedFile)) {
                @unlink($convertedFile);
            }
            
            return true;
        } else {
            self::debugLog('=== saveBase64File FAILED ===');
            // Clean up temporary files
            @unlink($tempFile);
            if ($convertedFile && file_exists($convertedFile)) {
                @unlink($convertedFile);
            }
            $this->lastError = 'Failed to save file to destination: ' . $filepath;
            return false;
        }
    }
    
    
    /**
     * Insert file record into pool_files database table
     * This matches the same insert that docPool.php does
     * 
     * @param string $filename The filename to insert
     * @param array|null $metadata Optional metadata (accountnr, amount, date, invoiceNumber, invoiceDescription)
     * @return bool Success status
     */
    private function insertToPoolFiles($filename, $metadata = null)
    {
        // Only insert PDF files to match docPool.php behavior
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
            return true; // Not a PDF, skip silently
        }
        
        try {
            // Ensure we are connected to the correct tenant database
            if (self::$db !== null) {
                global $sqhost, $squser, $sqpass;
                $conn = db_connect($sqhost, $squser, $sqpass, self::$db, __FILE__ . " line " . __LINE__);
                if (!$conn) {
                    self::debugLog('insertToPoolFiles: Failed to connect to database', self::$db);
                    return false;
                }
                self::debugLog('insertToPoolFiles: Connected to database', self::$db);
            } else {
                self::debugLog('insertToPoolFiles: No database set, using default connection');
            }
            
            // Ensure pool_files table exists
            $qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'pool_files'";
            if (!db_fetch_array(db_select($qtxt, __FILE__ . " line " . __LINE__))) {
                // Create table if it doesn't exist
                $qtxt = "CREATE TABLE IF NOT EXISTS pool_files (
                    id serial NOT NULL,
                    filename varchar(255) NOT NULL,
                    subject text,
                    account varchar(50),
                    amount varchar(50),
                    file_date varchar(50),
                    invoice_number varchar(100),
                    description text,
                    updated timestamp DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE(filename)
                )";
                db_modify($qtxt, __FILE__ . " line " . __LINE__);
            }
            
            // Check if file already exists in database
            $qtxt = "SELECT id FROM pool_files WHERE filename = '" . db_escape_string($filename) . "'";
            if (db_fetch_array(db_select($qtxt, __FILE__ . " line " . __LINE__))) {
                // File already exists, skip insert
                self::debugLog('insertToPoolFiles: File already exists in database', $filename);
                return true;
            }
            
            // Extract metadata values
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $subject = $baseName;
            $account = '';
            $amount = '';
            $fileDate = date('Y-m-d H:i:s');
            $invoiceNumber = '';
            $description = '';
            
            if ($metadata !== null && is_array($metadata)) {
                $subject = isset($metadata['subject']) && $metadata['subject'] ? $metadata['subject'] : $baseName;
                $account = isset($metadata['accountnr']) ? $metadata['accountnr'] : '';
                $amount = isset($metadata['amount']) ? $metadata['amount'] : '';
                $fileDate = isset($metadata['date']) && $metadata['date'] ? $metadata['date'] : $fileDate;
                $invoiceNumber = isset($metadata['invoiceNumber']) ? $metadata['invoiceNumber'] : '';
                $description = isset($metadata['invoiceDescription']) ? $metadata['invoiceDescription'] : '';
            }
            
            // Insert into database
            $qtxt = "INSERT INTO pool_files (filename, subject, account, amount, file_date, invoice_number, description) VALUES (
                '" . db_escape_string($filename) . "',
                '" . db_escape_string($subject) . "',
                '" . db_escape_string($account) . "',
                '" . db_escape_string($amount) . "',
                '" . db_escape_string($fileDate) . "',
                '" . db_escape_string($invoiceNumber) . "',
                '" . db_escape_string($description) . "'
            )";
            db_modify($qtxt, __FILE__ . " line " . __LINE__);
            
            self::debugLog('insertToPoolFiles SUCCESS', [
                'filename' => $filename,
                'subject' => $subject,
                'account' => $account,
                'amount' => $amount
            ]);
            
            return true;
        } catch (Exception $e) {
            self::debugLog('insertToPoolFiles FAILED', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get file extension from mime type
     * 
     * @param string $mimeType
     * @return string File extension (without dot)
     */
    private function getExtensionFromMimeType($mimeType)
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
            'image/tiff' => 'tiff',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'text/csv' => 'csv'
        ];
        
        return $mimeToExt[$mimeType] ?? 'bin';
    }
    
    /**
     * Delete the file
     * 
     * @return bool Success status
     */
    public function delete()
    {
        if ($this->filepath && file_exists($this->filepath)) {
            if (!unlink($this->filepath)) {
                $this->lastError = 'Failed to delete file: ' . $this->filepath;
                return false;
            }
            
            // Delete from pool_files database table
            $this->deleteFromPoolFiles($this->filename);
            
            return true;
        }
        $this->lastError = 'File does not exist or filepath is not set';
        return false;
    }
    
    /**
     * Delete file record from pool_files database table
     * 
     * @param string $filename The filename to delete
     * @return bool Success status
     */
    private function deleteFromPoolFiles($filename)
    {
        try {
            // Check if pool_files table exists
            $qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'pool_files'";
            if (!db_fetch_array(db_select($qtxt, __FILE__ . " line " . __LINE__))) {
                return true; // Table doesn't exist, nothing to delete
            }
            
            // Delete the record
            $qtxt = "DELETE FROM pool_files WHERE filename = '" . db_escape_string($filename) . "'";
            db_modify($qtxt, __FILE__ . " line " . __LINE__);
            
            self::debugLog('deleteFromPoolFiles SUCCESS', $filename);
            return true;
        } catch (Exception $e) {
            self::debugLog('deleteFromPoolFiles FAILED', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get file content for download
     * 
     * @return string|false File content or false on failure
     */
    public function getFileContent()
    {
        if ($this->filepath && file_exists($this->filepath)) {
            return file_get_contents($this->filepath);
        }
        return false;
    }
    
    /**
     * Sanitize filename to prevent security issues
     * 
     * @param string $filename
     * @return string Sanitized filename
     */
    private function sanitizeFilename($filename)
    {
        // Remove any path traversal attempts
        $filename = basename($filename);
        
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Ensure filename is not too long
        if (strlen($filename) > 255) {
            $pathInfo = pathinfo($filename);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $filename = substr($pathInfo['filename'], 0, 255 - strlen($extension)) . $extension;
        }
        
        return $filename;
    }
    
    /**
     * Check if mime type is an image
     * 
     * @param string $mimeType
     * @return bool
     */
    private function isImage($mimeType)
    {
        $imageMimeTypes = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/bmp',
            'image/webp',
            'image/tiff'
        ];
        
        return in_array($mimeType, $imageMimeTypes);
    }
    
    /**
     * Convert image to PDF using FPDF-like approach
     * 
     * @param string $imagePath Path to the image file
     * @param string $originalFilename Original filename for reference
     * @return string|false Path to converted PDF file or false on failure
     */
    private function convertImageToPdf($imagePath, $originalFilename)
    {
        try {
            self::debugLog('convertImageToPdf START', [
                'imagePath' => $imagePath,
                'originalFilename' => $originalFilename
            ]);
            
            // Check if GD extension is available
            if (!extension_loaded('gd')) {
                self::debugLog('convertImageToPdf FAILED: GD extension not loaded');
                return false;
            }
            
            // Create image resource based on mime type
            $imageInfo = getimagesize($imagePath);
            if (!$imageInfo) {
                self::debugLog('convertImageToPdf FAILED: getimagesize returned false');
                return false;
            }
            
            $imageWidth = $imageInfo[0];
            $imageHeight = $imageInfo[1];
            $mimeType = $imageInfo['mime'];
            
            self::debugLog('Image info retrieved', [
                'width' => $imageWidth,
                'height' => $imageHeight,
                'mimeType' => $mimeType
            ]);
            
            // Create image resource
            $image = false;
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $image = imagecreatefromjpeg($imagePath);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($imagePath);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($imagePath);
                    break;
                case 'image/bmp':
                    if (function_exists('imagecreatefrombmp')) {
                        $image = imagecreatefrombmp($imagePath);
                    } else {
                        self::debugLog('convertImageToPdf FAILED: imagecreatefrombmp not available');
                        return false;
                    }
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $image = imagecreatefromwebp($imagePath);
                    } else {
                        self::debugLog('convertImageToPdf FAILED: imagecreatefromwebp not available');
                        return false;
                    }
                    break;
                default:
                    self::debugLog('convertImageToPdf FAILED: unsupported mime type', $mimeType);
                    return false;
            }
            
            if (!$image) {
                self::debugLog('convertImageToPdf FAILED: image resource creation failed');
                return false;
            }
            
            self::debugLog('Image resource created successfully');
            
            // Create a simple PDF using basic PDF structure
            $tempPdfPath = tempnam(sys_get_temp_dir(), 'img2pdf_') . '.pdf';
            
            // Convert image to JPEG for embedding in PDF
            $tempJpegPath = tempnam(sys_get_temp_dir(), 'img2pdf_') . '.jpg';
            if (!imagejpeg($image, $tempJpegPath, 90)) {
                self::debugLog('convertImageToPdf FAILED: imagejpeg failed');
                imagedestroy($image);
                return false;
            }
            imagedestroy($image);
            
            self::debugLog('Temporary JPEG created', ['tempJpegPath' => $tempJpegPath]);
            
            // Get JPEG data
            $jpegData = file_get_contents($tempJpegPath);
            if ($jpegData === false) {
                self::debugLog('convertImageToPdf FAILED: could not read temp JPEG');
                @unlink($tempJpegPath);
                return false;
            }
            $jpegSize = strlen($jpegData);
            
            self::debugLog('JPEG data read', ['jpegSize' => $jpegSize]);
            
            // Calculate PDF dimensions (A4 = 595.28 x 841.89 points)
            $pageWidth = 595.28;
            $pageHeight = 841.89;
            
            // Calculate image dimensions to fit page while maintaining aspect ratio
            $aspectRatio = $imageWidth / $imageHeight;
            if ($aspectRatio > $pageWidth / $pageHeight) {
                // Image is wider, fit to width
                $pdfImageWidth = $pageWidth - 40; // 20pt margin on each side
                $pdfImageHeight = $pdfImageWidth / $aspectRatio;
            } else {
                // Image is taller, fit to height
                $pdfImageHeight = $pageHeight - 40; // 20pt margin on each side
                $pdfImageWidth = $pdfImageHeight * $aspectRatio;
            }
            
            // Center the image
            $x = ($pageWidth - $pdfImageWidth) / 2;
            $y = ($pageHeight - $pdfImageHeight) / 2;
            
            // Create basic PDF content
            $pdfContent = $this->createSimplePdf($jpegData, $jpegSize, $imageWidth, $imageHeight, $x, $y, $pdfImageWidth, $pdfImageHeight, $pageWidth, $pageHeight);
            
            // Write PDF to file
            if (file_put_contents($tempPdfPath, $pdfContent) === false) {
                self::debugLog('convertImageToPdf FAILED: could not write PDF file');
                @unlink($tempJpegPath);
                return false;
            }
            
            // Clean up temporary JPEG
            @unlink($tempJpegPath);
            
            self::debugLog('convertImageToPdf SUCCESS', [
                'tempPdfPath' => $tempPdfPath,
                'pdfSize' => strlen($pdfContent)
            ]);
            
            return $tempPdfPath;
            
        } catch (Exception $e) {
            return false;
        } catch (Error $e) {
            return false;
        }
    }
    
    /**
     * Create a simple PDF with embedded JPEG image
     * 
     * @param string $jpegData JPEG image data
     * @param int $jpegSize Size of JPEG data
     * @param int $imageWidth Original image width
     * @param int $imageHeight Original image height
     * @param float $x X position in PDF
     * @param float $y Y position in PDF  
     * @param float $width Width in PDF
     * @param float $height Height in PDF
     * @param float $pageWidth PDF page width
     * @param float $pageHeight PDF page height
     * @return string PDF content
     */
    private function createSimplePdf($jpegData, $jpegSize, $imageWidth, $imageHeight, $x, $y, $width, $height, $pageWidth, $pageHeight)
    {
        $pdf = "%PDF-1.4\n";
        
        // Object 1: Catalog
        $pdf .= "1 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= "/Type /Catalog\n";
        $pdf .= "/Pages 2 0 R\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n\n";
        
        // Object 2: Pages
        $pdf .= "2 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= "/Type /Pages\n";
        $pdf .= "/Kids [3 0 R]\n";
        $pdf .= "/Count 1\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n\n";
        
        // Object 3: Page
        $pdf .= "3 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= "/Type /Page\n";
        $pdf .= "/Parent 2 0 R\n";
        $pdf .= "/MediaBox [0 0 {$pageWidth} {$pageHeight}]\n";
        $pdf .= "/Resources <<\n";
        $pdf .= "/XObject << /Im1 4 0 R >>\n";
        $pdf .= ">>\n";
        $pdf .= "/Contents 5 0 R\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n\n";
        
        // Object 4: Image
        $pdf .= "4 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= "/Type /XObject\n";
        $pdf .= "/Subtype /Image\n";
        $pdf .= "/Width {$imageWidth}\n";
        $pdf .= "/Height {$imageHeight}\n";
        $pdf .= "/ColorSpace /DeviceRGB\n";
        $pdf .= "/BitsPerComponent 8\n";
        $pdf .= "/Filter /DCTDecode\n";
        $pdf .= "/Length {$jpegSize}\n";
        $pdf .= ">>\n";
        $pdf .= "stream\n";
        $pdf .= $jpegData;
        $pdf .= "\nendstream\n";
        $pdf .= "endobj\n\n";
        
        // Object 5: Content stream
        $contentStream = "q\n{$width} 0 0 {$height} {$x} {$y} cm\n/Im1 Do\nQ\n";
        $contentLength = strlen($contentStream);
        
        $pdf .= "5 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= "/Length {$contentLength}\n";
        $pdf .= ">>\n";
        $pdf .= "stream\n";
        $pdf .= $contentStream;
        $pdf .= "\nendstream\n";
        $pdf .= "endobj\n\n";
        
        // Cross-reference table
        $xref = "xref\n";
        $xref .= "0 6\n";
        $xref .= "0000000000 65535 f \n";
        $xref .= sprintf("%010d 00000 n \n", strpos($pdf, "1 0 obj"));
        $xref .= sprintf("%010d 00000 n \n", strpos($pdf, "2 0 obj"));
        $xref .= sprintf("%010d 00000 n \n", strpos($pdf, "3 0 obj"));
        $xref .= sprintf("%010d 00000 n \n", strpos($pdf, "4 0 obj"));
        $xref .= sprintf("%010d 00000 n \n", strpos($pdf, "5 0 obj"));
        
        $pdf .= $xref;
        
        // Trailer
        $pdf .= "trailer\n";
        $pdf .= "<<\n";
        $pdf .= "/Size 6\n";
        $pdf .= "/Root 1 0 R\n";
        $pdf .= ">>\n";
        $pdf .= "startxref\n";
        $pdf .= strlen($pdf) - strlen($xref) . "\n";
        $pdf .= "%%EOF\n";
        
        return $pdf;
    }
    
    /**
     * Convert object to array
     * 
     * @return array Associative array of file properties
     */
    public function toArray()
    {
        $array = [
            'filename' => $this->filename,
            'size' => $this->size,
            'mimeType' => $this->mimeType,
            'uploadDate' => $this->uploadDate
        ];
        
        // Include metadata if available
        if ($this->metadata !== null) {
            $array['metadata'] = $this->metadata;
        }
        
        return $array;
    }
    
    // Getter methods
    public function getFilename() { return $this->filename; }
    public function getFilepath() { return $this->filepath; }
    public function getSize() { return $this->size; }
    public function getMimeType() { return $this->mimeType; }
    public function getUploadDate() { return $this->uploadDate; }
    public function getLastError() { return $this->lastError; }
    
    // Setter methods
    public function setFilename($filename) { $this->filename = $filename; }
    public function setFilepath($filepath) { $this->filepath = $filepath; }
    public function setSize($size) { $this->size = $size; }
    public function setMimeType($mimeType) { $this->mimeType = $mimeType; }
    public function setUploadDate($uploadDate) { $this->uploadDate = $uploadDate; }
}