<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docIncludes/poolUpload.php --- patch 4.1.1 --- 2025-06-10 ---
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
// 20250610 Created mobile-friendly document pool upload interface

@session_start();
$s_id = session_id();
$title = "Document Pool Upload";
$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print ' <meta name="viewport" content="width=device-width, initial-scale=1.0"> ';

$servername = $_SERVER['SERVER_NAME'];
$docFolder = "bilag/" . $servername;
$docFolder = "bilag";

// Handle AJAX upload
if (isset($_POST['action']) && $_POST['action'] === 'uploadToPool') {
    // Clear any output buffer to prevent HTML headers
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Add separator to split HTML from JSON
    echo "===JSON_START===";
    
    try {
        if (!isset($_FILES['uploadedFile'])) {
            throw new Exception('Ingen fil modtaget');
        }
        
        $file = $_FILES['uploadedFile'];
        
        // Validate file type
        $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, $allowedTypes)) {
            throw new Exception('Kun PDF, JPG og PNG filer er tilladt');
        }
        
        // Validate file size (100MB max)
        if ($file['size'] > 100 * 1024 * 1024) {
            throw new Exception('Filstørrelse må maksimalt være 100MB');
        }
        
        // Get description and date
        $description = trim(if_isset($_POST['description'], ''));
        $documentDate = trim(if_isset($_POST['documentDate'], ''));
        
        // Build filename based on date and description
        if ($documentDate && $description) {
            // Convert dd-mm-yyyy to yyyy-mm-dd format
            $dateParts = explode('-', $documentDate);
            if (count($dateParts) === 3) {
                $formattedDate = $dateParts[2] . $dateParts[1] . $dateParts[0];
                $fileName = $formattedDate . '-' . sanitizeFileName($description) . "." . $fileExt;
            } else {
                $fileName = sanitizeFileName($description) . "." . $fileExt;
            }
        } else if ($description) {
            // Use description as filename if no date
            $fileName = sanitizeFileName($description) . "." . $fileExt;
        } else {
            // Use original filename if no description
            $fileName = sanitizeFileName(pathinfo($file['name'], PATHINFO_FILENAME)) . "." . $fileExt;
        }
        
        // Create pool directory if it doesn't exist
        $poolDir = "../" . $docFolder . "/$db/pulje";
        if (!file_exists($poolDir)) {
            mkdir($poolDir, 0777, true);
        }
        
        $targetFile = $poolDir . "/" . $fileName;
        
        // Check if file already exists
        if (file_exists($targetFile)) {
            // Add timestamp to make it unique
            $name = pathinfo($fileName, PATHINFO_FILENAME);
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $fileName = $name . "_" . date('YmdHis') . "." . $ext;
            $targetFile = $poolDir . "/" . $fileName;
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
            throw new Exception('Kunne ikke gemme fil');
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Fil uploadet til pulje som: " . $fileName,
            'filename' => $fileName
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    echo "===JSON_END===";
    exit;
}

// Sanitize filename function (modified to remove dots)
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
    $nfn = str_replace('.','_',$nfn); // Remove dots
    
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

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    .upload-container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        padding: 30px;
        margin: 20px auto;
        max-width: 500px;
        animation: slideUp 0.6s ease-out;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .header {
        text-align: center;
        margin-bottom: 30px;
    }

    .header h1 {
        color: #333;
        font-size: 24px;
        margin-bottom: 8px;
        font-weight: 600;
    }

    .header p {
        color: #666;
        font-size: 14px;
    }

    .upload-area {
        border: 2px dashed #ddd;
        border-radius: 15px;
        padding: 40px 20px;
        text-align: center;
        background: #fafafa;
        transition: all 0.3s ease;
        cursor: pointer;
        margin-bottom: 20px;
        position: relative;
    }

    .upload-area:hover {
        border-color: #4a90e2;
        background: #f0f7ff;
        transform: translateY(-2px);
    }

    .upload-area.dragover {
        border-color: #4a90e2;
        background: #f0f7ff;
        transform: scale(1.02);
    }

    .upload-icon {
        font-size: 48px;
        margin-bottom: 15px;
        color: #4a90e2;
    }

    .upload-text {
        color: #333;
        font-size: 16px;
        font-weight: 500;
        margin-bottom: 8px;
    }

    .upload-hint {
        color: #999;
        font-size: 12px;
    }

    .file-input {
        display: none;
    }

    .file-info {
        display: none;
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        border-left: 4px solid #28a745;
    }

    .file-name {
        font-weight: 500;
        color: #333;
        margin-bottom: 5px;
        word-break: break-all;
    }

    .file-size {
        font-size: 12px;
        color: #666;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-row {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
    }

    .form-col {
        flex: 1;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        color: #333;
        font-weight: 500;
        font-size: 14px;
    }

    .form-input {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #eee;
        border-radius: 10px;
        font-size: 14px;
        transition: border-color 0.3s ease;
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: #4a90e2;
    }

    /* Enhanced date input styling */
    .date-input-wrapper {
        position: relative;
    }

    .date-input {
        position: relative;
        padding-left: 45px; /* Space for calendar icon */
    }

    .date-input-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #666;
        font-size: 16px;
        pointer-events: none;
        z-index: 1;
    }

    .date-input.has-value {
        border-color: #28a745;
        background: #f8fff8;
    }

    .date-input.invalid {
        border-color: #dc3545;
        background: #fff5f5;
    }

    .date-validation {
        font-size: 12px;
        margin-top: 5px;
        padding-left: 5px;
        display: none;
    }

    .date-validation.success {
        color: #28a745;
        display: block;
    }

    .date-validation.error {
        color: #dc3545;
        display: block;
    }

    .quick-date-buttons {
        display: flex;
        gap: 5px;
        margin-top: 8px;
        flex-wrap: wrap;
    }

    .quick-date-btn {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 4px 8px;
        font-size: 11px;
        cursor: pointer;
        color: #495057;
        transition: all 0.2s ease;
    }

    .quick-date-btn:hover {
        background: #e9ecef;
        border-color: #adb5bd;
    }

    .quick-date-btn:active {
        background: #dee2e6;
    }

    .filename-preview {
        background: #f0f7ff;
        border: 1px solid #4a90e2;
        border-radius: 8px;
        padding: 10px;
        margin-top: 10px;
        font-size: 13px;
        color: #333;
        display: none;
    }

    .filename-preview strong {
        color: #4a90e2;
        display: block;
        margin-bottom: 5px;
    }

    .upload-btn {
        width: 100%;
        background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
        color: white;
        border: none;
        padding: 15px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .upload-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(74, 144, 226, 0.3);
    }

    .upload-btn:active {
        transform: translateY(0);
    }

    .upload-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .progress-bar {
        display: none;
        width: 100%;
        height: 6px;
        background: #eee;
        border-radius: 3px;
        margin: 15px 0;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #4a90e2, #357abd);
        width: 0%;
        transition: width 0.3s ease;
    }

    .message {
        border-radius: 10px;
        padding: 15px;
        margin-top: 15px;
        text-align: center;
        display: none;
    }

    .success-message {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error-message {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Mobile optimizations */
    @media (max-width: 480px) {
        .upload-container {
            margin: 10px;
            padding: 20px;
        }

        .header h1 {
            font-size: 20px;
        }

        .upload-area {
            padding: 30px 15px;
        }

        .upload-icon {
            font-size: 36px;
        }

        .upload-text {
            font-size: 14px;
        }

        .form-row {
            flex-direction: column;
            gap: 0;
        }

        .quick-date-buttons {
            justify-content: center;
        }
    }

    .back-btn {
        display: inline-block;
        margin-bottom: 20px;
        padding: 8px 16px;
        background: #f8f9fa;
        color: #333;
        text-decoration: none;
        border-radius: 5px;
        font-size: 14px;
        transition: background-color 0.3s;
    }

    .back-btn:hover {
        background: #e9ecef;
        text-decoration: none;
    }
</style>

<div class="upload-container">
    <a href="pulje_review.php" class="back-btn">← Tilbage til dokumenter</a>
    
    <div class="header">
        <h1>📄 Dokument Pulje</h1>
        <p>Upload filer til dokument puljen</p>
    </div>

    <form id="uploadForm">
        <div class="upload-area" id="uploadArea">
            <div class="upload-icon">📁</div>
            <div class="upload-text">Klik for at vælge fil</div>
            <div class="upload-hint">eller træk og slip her</div>
            <input type="file" id="fileInput" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
        </div>

        <div class="file-info" id="fileInfo">
            <div class="file-name" id="fileName"></div>
            <div class="file-size" id="fileSize"></div>
        </div>

        <div class="form-row">
            <div class="form-col">
                <label class="form-label" for="documentDate">📅 Bilagsdato</label>
                <div class="date-input-wrapper">
                    <div class="date-input-icon">📅</div>
                    <input type="tel" class="form-input date-input" id="documentDate" name="documentDate" placeholder="dd-mm-yyyy" maxlength="10" inputmode="numeric" pattern="[0-9\-]*">
                </div>
                <div class="date-validation" id="dateValidation"></div>
                <div class="quick-date-buttons">
                    <button type="button" class="quick-date-btn" data-days="0">I dag</button>
                    <button type="button" class="quick-date-btn" data-days="-1">I går</button>
                    <button type="button" class="quick-date-btn" data-days="-7">7 dage siden</button>
                    <button type="button" class="quick-date-btn" data-days="-30">30 dage siden</button>
                    <button type="button" class="quick-date-btn" id="clearDate">Ryd</button>
                </div>
            </div>
            <div class="form-col">
                <label class="form-label" for="description">📝 Beskrivelse</label>
                <input type="text" class="form-input" id="description" name="description" placeholder="Indtast beskrivelse...">
            </div>
        </div>

        <div class="filename-preview" id="filenamePreview">
            <strong>Filnavn preview:</strong>
            <span id="previewText"></span>
        </div>

        <button type="submit" class="upload-btn" id="uploadBtn">
            📤 Upload til Pulje
        </button>

        <div class="progress-bar" id="progressBar">
            <div class="progress-fill" id="progressFill"></div>
        </div>

        <div class="message success-message" id="successMessage">
            ✅ Fil uploadet til dokument pulje!
        </div>

        <div class="message error-message" id="errorMessage">
            ❌ <span id="errorText">Upload fejlede. Prøv igen.</span>
        </div>
    </form>
</div>

<script>
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const uploadForm = document.getElementById('uploadForm');
    const uploadBtn = document.getElementById('uploadBtn');
    const progressBar = document.getElementById('progressBar');
    const progressFill = document.getElementById('progressFill');
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');
    const documentDate = document.getElementById('documentDate');
    const description = document.getElementById('description');
    const filenamePreview = document.getElementById('filenamePreview');
    const previewText = document.getElementById('previewText');
    const dateValidation = document.getElementById('dateValidation');

    let selectedFile = null;

    // File size formatter
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Sanitize filename preview (client-side) - modified to remove dots
    function sanitizeFileName(name) {
        name = name.replace(/[æÆ]/g, 'ae')
                  .replace(/[øØ]/g, 'oe')
                  .replace(/[åÅ]/g, 'aa')
                  .replace(/[&\s\.]/g, '_')  // Added dot removal
                  .replace(/[^a-zA-Z0-9._()-]/g, '_')
                  .replace(/_+/g, '_')
                  .replace(/^[._]+|[._]+$/g, '');
        return name;
    }

    // Enhanced date validation
    function validateDate(dateStr) {
        if (!dateStr) return { valid: true, message: '' };
        
        const regex = /^(\d{2})-(\d{2})-(\d{4})$/;
        const match = dateStr.match(regex);
        
        if (!match) {
            return { valid: false, message: 'Format skal være dd-mm-yyyy' };
        }
        
        const day = parseInt(match[1], 10);
        const month = parseInt(match[2], 10);
        const year = parseInt(match[3], 10);
        
        // Basic range checks
        if (month < 1 || month > 12) {
            return { valid: false, message: 'Måned skal være mellem 01-12' };
        }
        
        if (day < 1 || day > 31) {
            return { valid: false, message: 'Dag skal være mellem 01-31' };
        }
        
        if (year < 1900 || year > new Date().getFullYear() + 10) {
            return { valid: false, message: 'År skal være mellem 1900 og ' + (new Date().getFullYear() + 10) };
        }
        
        // Create date and check if it's valid
        const date = new Date(year, month - 1, day);
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            return { valid: false, message: 'Ugyldig dato' };
        }
        
        // Check if date is in the future (with some tolerance)
        const today = new Date();
        const maxFutureDate = new Date(today.getFullYear() + 1, today.getMonth(), today.getDate());
        if (date > maxFutureDate) {
            return { valid: false, message: 'Dato må ikke være mere end 1 år frem' };
        }
        
        return { valid: true, message: 'Gyldig dato ✓' };
    }

    // Format date as user types
    function formatDateInput(value) {
        // Remove all non-digits
        let digits = value.replace(/\D/g, '');
        
        // Limit to 8 digits (ddmmyyyy)
        digits = digits.substring(0, 8);
        
        // Add dashes
        let formatted = '';
        if (digits.length >= 2) {
            formatted = digits.substring(0, 2);
        } else {
            formatted = digits;
        }
        
        if (digits.length >= 4) {
            formatted += '-' + digits.substring(2, 4);
        } else if (digits.length > 2) {
            formatted += '-' + digits.substring(2);
        }
        
        if (digits.length > 4) {
            formatted += '-' + digits.substring(4);
        }
        
        return formatted;
    }

    // Date input handlers
    documentDate.addEventListener('input', function(e) {
        const cursorPos = e.target.selectionStart;
        const oldValue = e.target.value;
        const newValue = formatDateInput(e.target.value);
        
        e.target.value = newValue;
        
        // Maintain cursor position
        const newCursorPos = cursorPos + (newValue.length - oldValue.length);
        e.target.setSelectionRange(newCursorPos, newCursorPos);
        
        validateDateInput();
        updateFilenamePreview();
    });

    documentDate.addEventListener('blur', validateDateInput);
    documentDate.addEventListener('keydown', function(e) {
        // Allow backspace, delete, tab, escape, enter
        if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
            // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            (e.keyCode === 65 && e.ctrlKey === true) ||
            (e.keyCode === 67 && e.ctrlKey === true) ||
            (e.keyCode === 86 && e.ctrlKey === true) ||
            (e.keyCode === 88 && e.ctrlKey === true) ||
            // Allow home, end, left, right
            (e.keyCode >= 35 && e.keyCode <= 39)) {
            return;
        }
        // Ensure that it is a number and stop the keypress if not
        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
            e.preventDefault();
        }
    });

    function validateDateInput() {
        const dateValue = documentDate.value;
        const validation = validateDate(dateValue);
        
        documentDate.classList.remove('has-value', 'invalid');
        dateValidation.classList.remove('success', 'error');
        
        if (dateValue) {
            if (validation.valid) {
                documentDate.classList.add('has-value');
                dateValidation.textContent = validation.message;
                if (validation.message) {
                    dateValidation.classList.add('success');
                }
            } else {
                documentDate.classList.add('invalid');
                dateValidation.textContent = validation.message;
                dateValidation.classList.add('error');
            }
        }
        
        return validation.valid;
    }

    // Quick date buttons
    function formatDateForInput(date) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}-${month}-${year}`;
    }

    document.querySelectorAll('.quick-date-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (this.id === 'clearDate') {
                documentDate.value = '';
                validateDateInput();
                updateFilenamePreview();
                return;
            }
            
            const daysOffset = parseInt(this.dataset.days);
            const date = new Date();
            date.setDate(date.getDate() + daysOffset);
            
            documentDate.value = formatDateForInput(date);
            validateDateInput();
            updateFilenamePreview();
        });
    });

    // Description input handler
    description.addEventListener('input', updateFilenamePreview);

    // Update filename preview
    function updateFilenamePreview() {
        if (!selectedFile) return;
        
        const dateValue = documentDate.value;
        const descValue = description.value.trim();
        const fileExt = selectedFile.name.split('.').pop().toLowerCase();
        
        let preview = '';
        
        if (dateValue && descValue) {
            // Convert dd-mm-yyyy to yyyymmdd
            const dateParts = dateValue.split('-');
            if (dateParts.length === 3 && dateParts[0].length === 2 && dateParts[1].length === 2 && dateParts[2].length === 4) {
                const formattedDate = dateParts[2] + dateParts[1] + dateParts[0];
                preview = formattedDate + '-' + sanitizeFileName(descValue) + '.' + fileExt;
            } else {
                preview = sanitizeFileName(descValue) + '.' + fileExt;
            }
        } else if (descValue) {
            preview = sanitizeFileName(descValue) + '.' + fileExt;
        } else {
            preview = sanitizeFileName(selectedFile.name.replace(/\.[^/.]+$/, "")) + '.' + fileExt;
        }
        
        previewText.textContent = preview;
        filenamePreview.style.display = 'block';
    }

    // Click handler for upload area
    uploadArea.addEventListener('click', () => {
        fileInput.click();
    });

    // Drag and drop handlers
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleFileSelect();
        }
    });

    // File input change handler
    fileInput.addEventListener('change', handleFileSelect);

    function handleFileSelect() {
        const file = fileInput.files[0];
        if (!file) return;

        // Validate file type
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        if (!allowedTypes.includes(file.type)) {
            showError('Vælg venligst en PDF, JPG eller PNG fil.');
            return;
        }

        // Validate file size (max 100MB)
        if (file.size > 100 * 1024 * 1024) {
            showError('Filstørrelse må maksimalt være 100MB.');
            return;
        }

        selectedFile = file;

        // Show file info with original filename (without extension)
        const originalName = file.name.replace(/\.[^/.]+$/, ""); // Remove extension
        fileName.textContent = originalName;
        fileSize.textContent = formatFileSize(file.size);
        fileInfo.style.display = 'block';
        
        // Update upload area
        uploadArea.innerHTML = `
            <div class="upload-icon">✅</div>
            <div class="upload-text">Fil valgt</div>
            <div class="upload-hint">Klik for at ændre fil</div>
        `;
        
        updateFilenamePreview();
        hideMessages();
    }

    // Form submission
    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const file = fileInput.files[0];
        if (!file) {
            showError('Vælg venligst en fil at uploade.');
            return;
        }

        // Validate date if provided
        const dateValue = documentDate.value;
        if (dateValue && !validateDate(dateValue).valid) {
            showError('Ret venligst datoen før upload');
            documentDate.focus();
            return;
        }

        uploadBtn.disabled = true;
        uploadBtn.textContent = '⏳ Uploader...';
        progressBar.style.display = 'block';
        hideMessages();

        // Animate progress
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            progressFill.style.width = progress + '%';
            
            if (progress >= 90) {
                clearInterval(progressInterval);
            }
        }, 200);

        // Create FormData
        const formData = new FormData();
        formData.append('uploadedFile', file);
        formData.append('description', description.value);
        formData.append('documentDate', documentDate.value);
        formData.append('action', 'uploadToPool');

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const responseText = await response.text();
            
            // Extract JSON from response with separators
            let jsonText = responseText;
            const jsonStart = responseText.indexOf('===JSON_START===');
            const jsonEnd = responseText.indexOf('===JSON_END===');
            
            if (jsonStart !== -1 && jsonEnd !== -1) {
                jsonText = responseText.substring(jsonStart + 16, jsonEnd);
            }
            
            const result = JSON.parse(jsonText);
            
            // Complete progress
            clearInterval(progressInterval);
            progressFill.style.width = '100%';
            
            setTimeout(() => {
                if (result.success) {
                    showSuccess(result.message);
                    resetForm();
                } else {
                    showError(result.message || 'Upload fejlede');
                    resetButton();
                }
            }, 500);

        } catch (error) {
            clearInterval(progressInterval);
            showError('Netværksfejl: ' + error.message);
            resetButton();
        }
    });

    function showSuccess(message) {
        successMessage.innerHTML = '✅ ' + message;
        successMessage.style.display = 'block';
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }

    function showError(message) {
        errorText.textContent = message;
        errorMessage.style.display = 'block';
    }

    function hideMessages() {
        successMessage.style.display = 'none';
        errorMessage.style.display = 'none';
    }

    function resetForm() {
        fileInput.value = '';
        documentDate.value = '';
        description.value = '';
        selectedFile = null;
        fileInfo.style.display = 'none';
        filenamePreview.style.display = 'none';
        
        // Reset date input styling
        documentDate.classList.remove('has-value', 'invalid');
        dateValidation.classList.remove('success', 'error');
        dateValidation.textContent = '';
        
        uploadArea.innerHTML = `
            <div class="upload-icon">📁</div>
            <div class="upload-text">Klik for at vælg fil</div>
            <div class="upload-hint">eller træk og slip her</div>
        `;
        
        resetButton();
        progressBar.style.display = 'none';
        progressFill.style.width = '0%';
    }

    function resetButton() {
        uploadBtn.disabled = false;
        uploadBtn.textContent = '📤 Upload til Pulje';
    }
</script>