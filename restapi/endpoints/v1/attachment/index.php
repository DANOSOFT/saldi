<?php

require_once '../../../models/attachment/AttachmentModel.php';
require_once '../../../core/BaseEndpoint.php';

class AttachmentEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
        
        // Set the database for AttachmentModel from headers
        $headers = getallheaders();
        if ($headers) {
            $headers = array_change_key_case($headers, CASE_LOWER);
            if (isset($headers['x-db'])) {
                AttachmentModel::setDatabase($headers['x-db']);
            }
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
            // Check if file was uploaded
            if (!isset($_FILES['file'])) {
                $this->sendResponse(false, null, 'No file uploaded', 400);
                return;
            }
            
            $uploadedFile = $_FILES['file'];
            
            // Check for upload errors
            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                $errorMessage = $this->getUploadErrorMessage($uploadedFile['error']);
                error_log("Upload error: " . $errorMessage);
                $this->sendResponse(false, null, $errorMessage, 400);
                return;
            }
            
            // Get custom filename if provided
            $customFilename = isset($_POST['filename']) ? $_POST['filename'] : null;
            
            $attachment = new AttachmentModel();
            $result = $attachment->saveUploadedFile($uploadedFile, $customFilename);
            
            if ($result) {
                $this->sendResponse(true, $attachment->toArray(), 'File uploaded successfully', 201);
            } else {
                error_log("Failed to save uploaded file: " . $uploadedFile['name']);
                $this->sendResponse(false, null, 'Failed to upload file', 500);
            }
        } catch (Exception $e) {
            error_log("Exception in handlePost: " . $e->getMessage());
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
