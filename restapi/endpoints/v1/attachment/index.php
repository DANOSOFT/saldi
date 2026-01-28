<?php

require_once __DIR__ . '/../../../models/attachment/AttachmentModel.php';
require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../core/JWT.php';
require_once __DIR__ . '/../../../core/JWTAuth.php';

class AttachmentEndpoint extends BaseEndpoint
{
    private $userId;
    private $username;
    
    public function __construct()
    {
        parent::__construct();
    }
    
    protected function checkAuthorization()
    {
        try {
            $payload = JWTAuth::validateToken();
            
            if (!$payload) {
                $this->sendResponse(false, null, 'Invalid or expired token', 401);
                return false;
            }
            
            $this->userId = $payload['user_id'];
            $this->username = $payload['username'];
            
            // Set database from tenant (from JWT token or X-Tenant-ID header)
            $db = JWTAuth::getTenantDatabase();
            if (!$db) {
                $this->sendResponse(false, null, 'Tenant database not found. Provide database during login or set X-Tenant-ID header.', 400);
                return false;
            }
            
            AttachmentModel::setDatabase($db);
            
            return true;
        } catch (Exception $e) {
            // Catch any exceptions and return proper JSON error
            $this->sendResponse(false, null, 'Authentication error: ' . $e->getMessage(), 401);
            return false;
        } catch (Error $e) {
            // Catch fatal errors too
            $this->sendResponse(false, null, 'Authentication fatal error: ' . $e->getMessage(), 401);
            return false;
        }
    }

    protected function handleGet($id = null)
    {
        try {
            // Check for file parameter first, then fall back to id
            $filename = $_GET['file'] ?? $id ?? null;
            
            if ($filename) {
                // Get single file by filename
                $attachment = new AttachmentModel($filename);
                if ($attachment->getFilename()) {
                    // Check if download is requested
                    if (isset($_GET['download']) && $_GET['download'] === 'true') {
                        $this->downloadFile($attachment);
                        return;
                    }
                    
                    $this->sendResponse(true, $attachment->toArray());
                } else {
                    $this->sendResponse(false, null, 'File not found', 404);
                }
            } else {
                // Get all files in bilag/{db}/pulje
                $attachments = AttachmentModel::getAllFiles();
                
                $items = [];
                foreach ($attachments as $attachment) {
                    $items[] = $attachment->toArray();
                }
                
                $this->sendResponse(true, $items);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handlePost($data)
    {
        try {
            // Check if data is provided
            if (!$data) {
                $this->sendResponse(false, null, 'No data provided', 400);
                return;
            }
            
            // Check if image_base64 is provided
            if (!isset($data->image_base64) || empty($data->image_base64)) {
                $this->sendResponse(false, null, 'No file provided. Expected base64 encoded file in "image_base64" field.', 400);
                return;
            }
            
            // Extract data from new structure
            $base64Data = $data->image_base64;
            $totalAmount = null;
            $invoiceDate = null;
            $invoiceNumber = null;
            $invoiceDescription = null;
            
            // Extract total_amount, invoice_date, invoice_number, and invoice_description from extracted_data
            if (isset($data->extracted_data)) {
                if (isset($data->extracted_data->total_amount)) {
                    $totalAmount = $data->extracted_data->total_amount;
                }
                if (isset($data->extracted_data->invoice_date)) {
                    $invoiceDate = $data->extracted_data->invoice_date;
                }
                if (isset($data->extracted_data->invoice_number)) {
                    $invoiceNumber = $data->extracted_data->invoice_number;
                }
                if (isset($data->extracted_data->invoice_description)) {
                    $invoiceDescription = $data->extracted_data->invoice_description;
                }
            }
            
            // Validate required fields
            if (!$totalAmount) {
                $this->sendResponse(false, null, 'Missing required field: extracted_data.total_amount', 400);
                return;
            }
            
            if (!$invoiceDate) {
                $this->sendResponse(false, null, 'Missing required field: extracted_data.invoice_date', 400);
                return;
            }
            
            // Remove data URI prefix if present (e.g., "data:image/png;base64,")
            if (preg_match('/^data:([^;]+);base64,/', $base64Data, $matches)) {
                $mimeType = $matches[1];
                $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $base64Data);
            }
            
            // Decode base64
            $fileContent = base64_decode($base64Data, true);
            if ($fileContent === false) {
                $this->sendResponse(false, null, 'Invalid base64 file data', 400);
                return;
            }
            
            // Get custom filename if provided (from id field or filename)
            // create random string for end of filename
            $randomString = bin2hex(random_bytes(8));
            $customFilename = null;
            if (isset($data->id)) {
                $customFilename = $data->id;
            } elseif (isset($data->filename)) {
                $customFilename = $data->filename . '_' . $randomString;
            }
            
            // Extract metadata (accountnr, invoiceNumber, and invoiceDescription are optional for now)
            $metadata = [
                'date' => $invoiceDate,
                'amount' => $totalAmount,
                'accountnr' => isset($data->accountnr) ? $data->accountnr : '',
                'invoiceNumber' => $invoiceNumber !== null ? $invoiceNumber : '',
                'invoiceDescription' => $invoiceDescription !== null ? $invoiceDescription : ''
            ];
            
            $attachment = new AttachmentModel();
            $result = $attachment->saveBase64File($fileContent, $customFilename, $metadata);
            
            if ($result) {
                $responseData = $attachment->toArray();
                // Include metadata in response
                $responseData['metadata'] = $metadata;
                $this->sendResponse(true, $responseData, 'File uploaded successfully', 201);
            } else {
                $this->sendResponse(false, null, 'Failed to upload file', 500);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handlePut($data)
    {
        $this->sendResponse(false, null, 'Method Not Allowed', 405);
    }

    protected function handleDelete($data)
    {
        try {
            // Check for file parameter in query string
            $filename = $_GET['file'] ?? null;
            
            // If not in query string, check in request data
            if (!$filename && $data && isset($data->filename)) {
                $filename = $data->filename;
            }
            
            if (!$filename) {
                $this->sendResponse(false, null, 'Filename is required. Use ?file=filename.ext', 400);
                return;
            }
            
            $attachment = new AttachmentModel($filename);
            if (!$attachment->getFilename()) {
                $this->sendResponse(false, null, 'File not found', 404);
                return;
            }
            
            $result = $attachment->delete();
            
            if ($result) {
                $this->sendResponse(true, null, 'File deleted successfully');
            } else {
                $this->sendResponse(false, null, 'Failed to delete file', 500);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Download file and send to browser
     */
    private function downloadFile($attachment)
    {
        $content = $attachment->getFileContent();
        if ($content === false) {
            $this->sendResponse(false, null, 'Unable to read file', 500);
            return;
        }
        
        // Set headers for file download
        header('Content-Type: ' . $attachment->getMimeType());
        header('Content-Disposition: attachment; filename="' . $attachment->getFilename() . '"');
        header('Content-Length: ' . $attachment->getSize());
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Output file content
        echo $content;
        exit;
    }

    /**
     * Get human-readable upload error message
     */
    private function getUploadErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File is too large (exceeds upload_max_filesize)';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File is too large (exceeds MAX_FILE_SIZE)';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
}

// Initialize and handle the request
$endpoint = new AttachmentEndpoint();
$endpoint->handleRequestMethod();