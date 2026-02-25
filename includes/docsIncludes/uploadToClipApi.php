<?php
// --- includes/docsIncludes/uploadToClipApi.php --- 2026-02-23 ---
// API endpoint for uploading a file directly to a kassekladde line via drag-and-drop
// Used by clip drag-and-drop functionality in kassekladde.php
// 20260223 Sawaneh Created for drag-and-drop file upload to clip

@session_start();
$s_id = session_id();

$header = 'nix';
$bg = 'nix';
$title = 'uploadToClipApi';

include("../connect.php");
include("../online.php");

// Clean any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

// Validate required parameters
$sourceId = isset($_POST['sourceId']) ? intval($_POST['sourceId']) : 0;
$kladde_id = isset($_POST['kladde_id']) ? intval($_POST['kladde_id']) : 0;
$bilag = isset($_POST['bilag']) ? $_POST['bilag'] : '';

if (!$sourceId || !$kladde_id) {
    echo json_encode(['success' => false, 'message' => 'Missing sourceId or kladde_id']);
    exit;
}

// Validate file upload
if (!isset($_FILES['uploadedFile']) || $_FILES['uploadedFile']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'No file uploaded';
    if (isset($_FILES['uploadedFile']['error'])) {
        switch ($_FILES['uploadedFile']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = 'File is too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg = 'File was only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = 'No file was uploaded';
                break;
            default:
                $errorMsg = 'Upload error code: ' . $_FILES['uploadedFile']['error'];
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

// Validate file type
$allowedTypes = array('jpg', 'jpeg', 'pdf', 'png');
$fileName = basename($_FILES['uploadedFile']['name']);
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$fileParts = explode("/", $_FILES['uploadedFile']['type']);
$mimeType = isset($fileParts[1]) ? strtolower($fileParts[1]) : '';

if (!in_array($fileExt, $allowedTypes) && !in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)]);
    exit;
}

// Get global_id from settings
$globalId = 1;
$qtxt = "SELECT var_value FROM settings WHERE var_name = 'globalId'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $globalId = $r['var_value'];
}

// Get user ID
$userId = isset($bruger_id) ? intval($bruger_id) : 0;

// Determine document folder
if (file_exists('../../owncloud')) $docFolder = '../../owncloud';
elseif (file_exists('../../bilag')) $docFolder = '../../bilag';
elseif (file_exists('../../documents')) $docFolder = '../../documents';
else $docFolder = '../../bilag';

$docFolder .= "/$db";

// Create directory structure for kassekladde
if (!file_exists($docFolder)) mkdir($docFolder, 0777, true);
if (!file_exists("$docFolder/finance")) mkdir("$docFolder/finance", 0777);
if (!file_exists("$docFolder/finance/$kladde_id")) mkdir("$docFolder/finance/$kladde_id", 0777);
if (!file_exists("$docFolder/finance/$kladde_id/$sourceId")) mkdir("$docFolder/finance/$kladde_id/$sourceId", 0777);

$filePath = "/finance/$kladde_id/$sourceId";

// Sanitize filename
$baseName = pathinfo($fileName, PATHINFO_FILENAME);
$baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $baseName);
if (empty($baseName)) $baseName = 'upload_' . time();

// Convert images to PDF if needed
$finalFileName = $baseName . '.pdf';
$targetFile = "$docFolder/$filePath/$finalFileName";

// Ensure unique filename
$counter = 1;
$originalBaseName = $baseName;
while (file_exists($targetFile)) {
    $baseName = $originalBaseName . '_' . $counter;
    $finalFileName = $baseName . '.pdf';
    $targetFile = "$docFolder/$filePath/$finalFileName";
    $counter++;
}

$uploadSuccess = false;

if (in_array($fileExt, ['jpg', 'jpeg', 'png'])) {
    // Convert image to PDF
    $tempFile = "$docFolder/$filePath/$baseName.$fileExt";
    if (move_uploaded_file($_FILES['uploadedFile']['tmp_name'], $tempFile)) {
        system("convert '$tempFile' '$targetFile'");
        if (file_exists($targetFile)) {
            unlink($tempFile);
            $uploadSuccess = true;
        } else {
            // Fallback: keep original image
            $finalFileName = $baseName . '.' . $fileExt;
            $targetFile = $tempFile;
            $uploadSuccess = true;
        }
    }
} else {
    // PDF file - move directly
    if (move_uploaded_file($_FILES['uploadedFile']['tmp_name'], $targetFile)) {
        $uploadSuccess = true;
    }
}

if ($uploadSuccess && file_exists($targetFile)) {
    // Insert document record into database
    $qtxt = "INSERT INTO documents (global_id, filename, filepath, source, source_id, timestamp, user_id) VALUES ";
    $qtxt .= "('$globalId', '" . db_escape_string($finalFileName) . "', '$filePath', 'kassekladde', '$sourceId', '" . date('U') . "', '$userId')";
    
    if (db_modify($qtxt, __FILE__ . " linje " . __LINE__)) {
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded and attached successfully',
            'filename' => $finalFileName,
            'sourceId' => $sourceId,
            'kladde_id' => $kladde_id,
            'bilag' => $bilag
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'File saved but database entry failed']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
}

exit;
