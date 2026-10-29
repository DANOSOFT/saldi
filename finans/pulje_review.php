<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docIncludes/poolReview.php --- patch 4.1.1 --- 2025-06-10 ---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 20250610 Created mobile-friendly document pool review interface with live preview
// 20260916 Sawaneh Declared $permission_key (roles & permissions, phase 3)

@session_start();
$s_id = session_id();
$title = "Document Pool Review";
$css = "../css/pulje_review.css";

include("../includes/connect.php");
$permission_key = 'finans.kassekladde';
include("../includes/online.php");
include("../includes/std_func.php");

print ' <meta name="viewport" content="width=device-width, initial-scale=1.0"> ';

$servername = $_SERVER['SERVER_NAME'];
$docFolder = "bilag/" . $servername;
$docFolder = "bilag";

// Handle AJAX actions
if (isset($_POST['action'])) {
    // Clear any output buffer to prevent HTML headers
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Add separator to split HTML from JSON
    echo "===JSON_START===";
    
    try {
        $action = $_POST['action'];
        
        if ($action === 'loadDocuments') {
            $search = trim(if_isset($_POST['search'], ''));
            $sortBy = if_isset($_POST['sortBy'], 'name');
            $sortOrder = if_isset($_POST['sortOrder'], 'asc');
            
            $documents = getPoolDocuments($search, $sortBy, $sortOrder);
            
            echo json_encode([
                'success' => true,
                'documents' => $documents
            ]);
            
        } elseif ($action === 'deleteDocument') {
            $filename = if_isset($_POST['filename'], '');
            
            if (empty($filename)) {
                throw new Exception('Intet filnavn angivet');
            }
            
            $poolDir = "../" . $docFolder . "/$db/pulje";
            $filePath = $poolDir . "/" . $filename;
            
            if (!file_exists($filePath)) {
                throw new Exception('Filen blev ikke fundet');
            }
            
            if (!unlink($filePath)) {
                throw new Exception('Kunne ikke slette filen');
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Fil slettet: " . $filename
            ]);
            
        } elseif ($action === 'renameDocument') {
            $oldFilename = if_isset($_POST['oldFilename'], '');
            $newFilename = if_isset($_POST['newFilename'], '');
            
            if (empty($oldFilename) || empty($newFilename)) {
                throw new Exception('Manglende filnavne');
            }
            
            // Sanitize new filename
            $fileExt = pathinfo($oldFilename, PATHINFO_EXTENSION);
            $newFilename = sanitizeFileName($newFilename) . '.' . $fileExt;
            
            $poolDir = "../" . $docFolder . "/$db/pulje";
            $oldPath = $poolDir . "/" . $oldFilename;
            $newPath = $poolDir . "/" . $newFilename;
            
            if (!file_exists($oldPath)) {
                throw new Exception('Original fil blev ikke fundet');
            }
            
            if (file_exists($newPath)) {
                throw new Exception('En fil med det navn eksisterer allerede');
            }
            
            if (!rename($oldPath, $newPath)) {
                throw new Exception('Kunne ikke omdøbe filen');
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Fil omdøbt til: " . $newFilename,
                'newFilename' => $newFilename
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    echo "===JSON_END===";
    exit;
}

// Function to get pool documents
function getPoolDocuments($search = '', $sortBy = 'name', $sortOrder = 'asc') {
    global $docFolder, $db;
    
    $poolDir = "../" . $docFolder . "/$db/pulje";
    
    if (!file_exists($poolDir)) {
        return [];
    }
    
    $documents = [];
    $files = glob($poolDir . "/*");
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // Only include allowed file types
            if (!in_array($fileExt, ['pdf', 'jpg', 'jpeg', 'png'])) {
                continue;
            }
            
            // Apply search filter
            if ($search && stripos($filename, $search) === false) {
                continue;
            }
            
            $fileSize = filesize($file);
            $modTime = filemtime($file);
            
            // Try to parse date from filename (yyyymmdd format)
            $documentDate = '';
            if (preg_match('/^(\d{8})-/', $filename, $matches)) {
                $dateStr = $matches[1];
                $year = substr($dateStr, 0, 4);
                $month = substr($dateStr, 4, 2);
                $day = substr($dateStr, 6, 2);
                $documentDate = "$day-$month-$year";
            }
            
            // Get description from filename
            $description = '';
            if (preg_match('/^\d{8}-(.+)\.[^.]+$/', $filename, $matches)) {
                $description = str_replace('_', ' ', $matches[1]);
            } elseif (preg_match('/^(.+)\.[^.]+$/', $filename, $matches)) {
                $description = str_replace('_', ' ', $matches[1]);
            }
            
            $documents[] = [
                'filename' => $filename,
                'description' => $description,
                'documentDate' => $documentDate,
                'fileSize' => $fileSize,
                'modTime' => $modTime,
                'fileExt' => $fileExt
            ];
        }
    }
    
    // Sort documents
    usort($documents, function($a, $b) use ($sortBy, $sortOrder) {
        $comparison = 0;
        
        switch ($sortBy) {
            case 'name':
                $comparison = strcasecmp($a['filename'], $b['filename']);
                break;
            case 'date':
                $comparison = strtotime($a['documentDate'] ?: '1900-01-01') - strtotime($b['documentDate'] ?: '1900-01-01');
                break;
            case 'size':
                $comparison = $a['fileSize'] - $b['fileSize'];
                break;
            case 'modified':
                $comparison = $a['modTime'] - $b['modTime'];
                break;
        }
        
        return $sortOrder === 'desc' ? -$comparison : $comparison;
    });
    
    return $documents;
}

// Sanitize filename function (same as upload)
function sanitizeFileName($fileName) {
    $legalChars = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
    array_push($legalChars,'0','1','2','3','4','5','6','7','8','9','_','-','(',')');
    
    $nfn = trim($fileName);
    $nfn = str_replace('æ','ae',$nfn);
    $nfn = str_replace('Æ','AE',$nfn);
    $nfn = str_replace('ø','oe',$nfn);
    $nfn = str_replace('Ø','OE',$nfn);
    $nfn = str_replace('å','aa',$nfn);
    $nfn = str_replace('Å','AA',$nfn);
    $nfn = str_replace('&','_',$nfn);
    $nfn = str_replace(' ','_',$nfn);
    $nfn = str_replace('.','_',$nfn);
    
    $newFileName = '';
    for ($x=0;$x<strlen($nfn);$x++) {
        $c1=substr($nfn,$x,1);
        $c2=strtolower($c1);
        if (!in_array($c2,$legalChars)) $c1 = '_';
        $newFileName.= $c1;
    }
    
    return trim($newFileName, ' ._');
}

?>

<div class="main-container">
    <!-- Left Panel - Document List -->
    <div class="left-panel">
        <div class="resize-handle"></div>
        <div class="review-container">
            <!--<a href="../includes/documents.php" class="back-btn">← Tilbage til dokumenter</a>-->
            
            <div class="header">
                <h1>📚 Dokument Pulje Oversigt</h1>
                <p>Se og administrer alle dokumenter i puljen</p>
            </div>

            <div class="message success-message" id="successMessage"></div>
            <div class="message error-message" id="errorMessage"></div>

            <div class="stats" id="statsContainer" style="display: none;">
                <div class="stat-item">
                    <span class="stat-number" id="totalFiles">0</span>
                    <span class="stat-label">Filer</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="totalSize">0 MB</span>
                    <span class="stat-label">Total størrelse</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="pdfCount">0</span>
                    <span class="stat-label">PDF filer</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="imageCount">0</span>
                    <span class="stat-label">Billeder</span>
                </div>
            </div>

            <div class="controls">
                <div class="search-box">
                    <div class="search-icon">🔍</div>
                    <input type="text" class="search-input" id="searchInput" placeholder="Søg efter filnavn eller beskrivelse...">
                </div>
                
                <div class="sort-controls">
                    <select class="sort-select" id="sortBy">
                        <option value="name">Navn</option>
                        <option value="date">Dato</option>
                        <option value="size">Størrelse</option>
                        <option value="modified">Ændret</option>
                    </select>
                    
                    <select class="sort-select" id="sortOrder">
                        <option value="asc">↑</option>
                        <option value="desc">↓</option>
                    </select>
                </div>
                
                <div class="controls-mobile-row">
                    <button class="refresh-btn" id="refreshBtn">🔄 Opdater</button>
                    <a href="pulje_upload.php" class="upload-link">📤 Upload</a>
                </div>
            </div>

            <div class="loading" id="loadingIndicator">
                <div class="loading-spinner">⏳</div>
                <div>Indlæser dokumenter...</div>
            </div>

            <div class="documents-grid" id="documentsGrid"></div>

            <div class="empty-state" id="emptyState" style="display: none;">
                <div class="empty-icon">📂</div>
                <h3>Ingen dokumenter fundet</h3>
                <p>Der er ingen dokumenter i puljen eller ingen matchede din søgning.</p>
                <br>
                <a href="poolUpload.php" class="upload-link">📤 Upload første dokument</a>
            </div>
        </div>
    </div>

    <!-- Right Panel - Preview -->
    <div class="right-panel hidden" id="rightPanel">
        <div class="preview-header">
            <div class="preview-title" id="previewTitle">Ingen dokument valgt</div>
            <div class="preview-actions" id="previewActions" style="display: none;">
                <a href="#" class="preview-btn primary" id="previewViewBtn" target="_blank">Åbn i nyt vindue</a>
                <a href="#" class="preview-btn secondary" id="previewDownloadBtn">Download</a>
            </div>
        </div>
        <div class="preview-content" id="previewContent">
            <div class="preview-placeholder">
                <div class="preview-placeholder-icon">📄</div>
                <div>Vælg et dokument for at se forhåndsvisning</div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Fullscreen Modal -->
<div class="mobile-fullscreen-modal" id="mobileFullscreenModal">
    <div class="mobile-fullscreen-header">
        <button class="mobile-back-btn" id="mobileBackBtn">
            ← Tilbage
        </button>
        <div class="mobile-document-title" id="mobileDocumentTitle">Dokument</div>
    </div>
    <div class="mobile-fullscreen-content" id="mobileFullscreenContent">
        <!-- Document content will be loaded here -->
    </div>
</div>

<!-- Rename Modal -->
<div id="renameModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Omdøb dokument</h3>
        <input type="text" class="modal-input" id="newFilename" placeholder="Indtast nyt navn (uden filtype)">
        <div class="modal-buttons">
            <button class="modal-btn secondary" id="cancelRename">Annuller</button>
            <button class="modal-btn primary" id="confirmRename">Omdøb</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Bekræft sletning</h3>
        <p>Er du sikker på at du vil slette dette dokument?</p>
        <p><strong id="deleteFilename"></strong></p>
        <br>
        <div class="modal-buttons">
            <button class="modal-btn secondary" id="cancelDelete">Annuller</button>
            <button class="modal-btn delete-btn" id="confirmDelete">Slet</button>
        </div>
    </div>
</div>

<script>
    var serverData = {
        db: <?php echo json_encode($db); ?>,
    };
</script>
<script src="../javascript/pulje_review.js" defer></script>

</body>
</html>