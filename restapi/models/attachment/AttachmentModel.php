<?php

class AttachmentModel 
{
    private $filename;
    private $filepath;
    private $size;
    private $mimeType;
    private $uploadDate;
    private static $baseUploadDir = '/var/www/html/pblm/bilag/';
    private static $db = null;
    
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
            return true;
        }
        
        return false;
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
        if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
            error_log("AttachmentModel: Invalid uploaded file");
            return false;
        }
        
        $uploadDir = self::getUploadDir();
        
        // Ensure upload directory exists with better error handling
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("AttachmentModel: Failed to create upload directory: " . $uploadDir);
                return false;
            }
        }
        
        // Double-check that directory was created and is writable
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            error_log("AttachmentModel: Upload directory is not writable: " . $uploadDir);
            return false;
        }
        
        // Use custom filename or original filename
        $filename = $customFilename ?: $uploadedFile['name'];
        error_log("AttachmentModel: Processing file: " . $filename);
        
        // Check if uploaded file is an image and convert to PDF
        $mimeType = mime_content_type($uploadedFile['tmp_name']);
        error_log("AttachmentModel: Detected mime type: " . $mimeType);
        
        $convertedFile = null;
        $isConverted = false;
        
        if ($this->isImage($mimeType)) {
            error_log("AttachmentModel: Converting image to PDF");
            $convertedFile = $this->convertImageToPdf($uploadedFile['tmp_name'], $filename);
            if ($convertedFile) {
                error_log("AttachmentModel: Image converted successfully to: " . $convertedFile);
                // Update filename to have .pdf extension
                $pathInfo = pathinfo($filename);
                $filename = $pathInfo['filename'] . '.pdf';
                $isConverted = true;
            } else {
                error_log("AttachmentModel: Image conversion failed, proceeding with original file");
                // Continue with original file if conversion fails
            }
        }
        
        // Sanitize filename
        $filename = $this->sanitizeFilename($filename);
        error_log("AttachmentModel: Sanitized filename: " . $filename);
        
        // Check if file already exists and generate unique name if needed
        $originalFilename = $filename;
        $counter = 1;
        while (file_exists($uploadDir . $filename)) {
            $pathInfo = pathinfo($originalFilename);
            $filename = $pathInfo['filename'] . '_' . $counter . '.' . $pathInfo['extension'];
            $counter++;
        }
        
        $filepath = $uploadDir . $filename;
        error_log("AttachmentModel: Final filepath: " . $filepath);
        
        $success = false;
        
        if ($isConverted && $convertedFile) {
            // For converted files, use copy() instead of move_uploaded_file()
            $success = copy($convertedFile, $filepath);
            if (!$success) {
                error_log("AttachmentModel: Failed to copy converted file to: " . $filepath);
            }
        } else {
            // For original uploaded files, use move_uploaded_file()
            $success = move_uploaded_file($uploadedFile['tmp_name'], $filepath);
            if (!$success) {
                error_log("AttachmentModel: Failed to move uploaded file to: " . $filepath);
            }
        }
        
        if ($success) {
            $this->filename = $filename;
            $this->filepath = $filepath;
            $this->size = filesize($filepath);
            $this->mimeType = mime_content_type($filepath);
            $this->uploadDate = date('Y-m-d H:i:s');
            
            // Clean up temporary converted file if it was created
            if ($convertedFile && file_exists($convertedFile)) {
                @unlink($convertedFile);
            }
            
            error_log("AttachmentModel: File saved successfully: " . $filename);
            return true;
        } else {
            // Clean up temporary converted file if it was created and save failed
            if ($convertedFile && file_exists($convertedFile)) {
                @unlink($convertedFile);
            }
            return false;
        }
    }
    
    /**
     * Delete the file
     * 
     * @return bool Success status
     */
    public function delete()
    {
        if ($this->filepath && file_exists($this->filepath)) {
            return unlink($this->filepath);
        }
        return false;
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
            error_log("AttachmentModel: Starting image conversion for: " . $imagePath);
            
            // Check if GD extension is available
            if (!extension_loaded('gd')) {
                error_log("AttachmentModel: GD extension not available");
                return false;
            }
            
            // Create image resource based on mime type
            $imageInfo = getimagesize($imagePath);
            if (!$imageInfo) {
                error_log("AttachmentModel: Could not get image size for: " . $imagePath);
                return false;
            }
            
            $imageWidth = $imageInfo[0];
            $imageHeight = $imageInfo[1];
            $mimeType = $imageInfo['mime'];
            
            error_log("AttachmentModel: Image info - Width: $imageWidth, Height: $imageHeight, Type: $mimeType");
            
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
                        error_log("AttachmentModel: BMP support not available");
                        return false;
                    }
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $image = imagecreatefromwebp($imagePath);
                    } else {
                        error_log("AttachmentModel: WebP support not available");
                        return false;
                    }
                    break;
                default:
                    error_log("AttachmentModel: Unsupported image type: " . $mimeType);
                    return false;
            }
            
            if (!$image) {
                error_log("AttachmentModel: Failed to create image resource");
                return false;
            }
            
            // Create a simple PDF using basic PDF structure
            $tempPdfPath = tempnam(sys_get_temp_dir(), 'img2pdf_') . '.pdf';
            error_log("AttachmentModel: Creating PDF at: " . $tempPdfPath);
            
            // Convert image to JPEG for embedding in PDF
            $tempJpegPath = tempnam(sys_get_temp_dir(), 'img2pdf_') . '.jpg';
            if (!imagejpeg($image, $tempJpegPath, 90)) {
                error_log("AttachmentModel: Failed to create temporary JPEG");
                imagedestroy($image);
                return false;
            }
            imagedestroy($image);
            
            // Get JPEG data
            $jpegData = file_get_contents($tempJpegPath);
            if ($jpegData === false) {
                error_log("AttachmentModel: Failed to read temporary JPEG");
                @unlink($tempJpegPath);
                return false;
            }
            $jpegSize = strlen($jpegData);
            
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
                error_log("AttachmentModel: Failed to write PDF file");
                @unlink($tempJpegPath);
                return false;
            }
            
            // Clean up temporary JPEG
            @unlink($tempJpegPath);
            
            error_log("AttachmentModel: PDF created successfully: " . $tempPdfPath);
            return $tempPdfPath;
            
        } catch (Exception $e) {
            error_log("AttachmentModel: Exception in convertImageToPdf: " . $e->getMessage());
            return false;
        } catch (Error $e) {
            error_log("AttachmentModel: Error in convertImageToPdf: " . $e->getMessage());
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
        return [
            'filename' => $this->filename,
            'size' => $this->size,
            'mimeType' => $this->mimeType,
            'uploadDate' => $this->uploadDate
        ];
    }
    
    // Getter methods
    public function getFilename() { return $this->filename; }
    public function getFilepath() { return $this->filepath; }
    public function getSize() { return $this->size; }
    public function getMimeType() { return $this->mimeType; }
    public function getUploadDate() { return $this->uploadDate; }
    
    // Setter methods
    public function setFilename($filename) { $this->filename = $filename; }
    public function setFilepath($filepath) { $this->filepath = $filepath; }
    public function setSize($size) { $this->size = $size; }
    public function setMimeType($mimeType) { $this->mimeType = $mimeType; }
    public function setUploadDate($uploadDate) { $this->uploadDate = $uploadDate; }
}
