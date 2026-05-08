<?php
// --- includes/docsIncludes/linkDocumentsApi.php --- 2025-12-11 ---
// API endpoint for linking documents between kassekladde lines
// Used by drag-and-drop functionality in kassekladde.php

@session_start();
$s_id = session_id();

$header = 'nix';
$bg = 'nix';
$title = 'linkDocumentsApi';

include("../connect.php");
include("../online.php");

// Clean any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

// Get POST parameters
$action = isset($_POST['action']) ? $_POST['action'] : '';
$fromSourceId = isset($_POST['fromSourceId']) ? intval($_POST['fromSourceId']) : 0;
$toSourceId = isset($_POST['toSourceId']) ? intval($_POST['toSourceId']) : 0;
$source = isset($_POST['source']) ? $_POST['source'] : 'kassekladde';

// Validate parameters
if ($action !== 'linkDocuments') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

if (!$fromSourceId || !$toSourceId) {
    echo json_encode(['success' => false, 'message' => 'Missing source or target ID']);
    exit;
}

if ($fromSourceId === $toSourceId) {
    echo json_encode(['success' => false, 'message' => 'Cannot link to the same line']);
    exit;
}

// Get global_id from settings
$globalId = 1;
$qtxt = "SELECT var_value FROM settings WHERE var_name = 'globalId'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $globalId = $r['var_value'];
}

// Get user ID from session
$userId = isset($bruger_id) ? intval($bruger_id) : 0;

// Get all documents from the source line
$sourceEsc = db_escape_string($source);
$qtxt = "SELECT * FROM documents WHERE source = '$sourceEsc' AND source_id = '$fromSourceId'";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

$linkedCount = 0;
$errors = [];

while ($doc = db_fetch_array($q)) {
    // Check if this exact document is already linked to the target line
    $filenameEsc = db_escape_string($doc['filename']);
    $filepathEsc = db_escape_string($doc['filepath']);
    
    $checkQuery = "SELECT id FROM documents WHERE source = '$sourceEsc' AND source_id = '$toSourceId' AND filename = '$filenameEsc' AND filepath = '$filepathEsc'";
    $checkResult = db_select($checkQuery, __FILE__ . " linje " . __LINE__);
    
    if (!db_fetch_array($checkResult)) {
        // Create a new document entry pointing to the same file
        $globalIdEsc = db_escape_string($doc['global_id']);
        $timestamp = time();
        
        $insertQuery = "INSERT INTO documents (global_id, filename, filepath, source, source_id, timestamp, user_id) VALUES ('$globalIdEsc', '$filenameEsc', '$filepathEsc', '$sourceEsc', '$toSourceId', '$timestamp', '$userId')";
        
        if (db_modify($insertQuery, __FILE__ . " linje " . __LINE__)) {
            $linkedCount++;
        } else {
            $errors[] = "Failed to link: " . $doc['filename'];
        }
    } else {
        // Document already linked, count it as success
        $linkedCount++;
    }
}

if ($linkedCount > 0) {
    echo json_encode([
        'success' => true, 
        'message' => 'Documents linked successfully',
        'count' => $linkedCount,
        'fromSourceId' => $fromSourceId,
        'toSourceId' => $toSourceId,
        'refresh' => true
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'No documents found to link',
        'errors' => $errors
    ]);
}
exit;
