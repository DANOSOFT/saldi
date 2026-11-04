<?php
// --- includes/docsIncludes/linkDocumentsApi.php --- 2025-12-11 ---
// API endpoint for linking documents between kassekladde lines
// Used by drag-and-drop functionality in kassekladde.php
//
// 20261026 CL/LH The linking moved to linkDocuments.php (linkLineDocuments()), which kassekladde.php uses too.

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

// Get user ID from session
$userId = isset($bruger_id) ? intval($bruger_id) : 0;

include_once(__DIR__ . '/linkDocuments.php');
$linked = linkLineDocuments($fromSourceId, $toSourceId, $source, $userId);
$linkedCount = $linked['count'];
$errors = $linked['errors'];

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
