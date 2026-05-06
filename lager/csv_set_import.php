<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/csv_set_import.php --- 2026-02-05 ---
// 
// Script to import CSV with set items and extract individual components
// CSV Headers: HMS, ALIAS, Beskrivelse, Kostpris, Salgspris
// 
// Usage: Upload CSV via web interface
//

@session_start();
$s_id = session_id();

$title = "CSV Sæt Import";
$modulnr = 9;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

// Check for CSV download BEFORE any HTML output
if ($_POST && isset($_FILES['csvfile']) && $_FILES['csvfile']['error'] === UPLOAD_ERR_OK && isset($_POST['download'])) {
    $uploadedFile = $_FILES['csvfile']['tmp_name'];
    $results = processCSV($uploadedFile);
    
    // Clean any previous output
    ob_end_clean();
    
    // Send CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=set_items_export.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    outputCSV($results);
    exit;
}

// Normal HTML output
print '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . $title . '</title>
    <link rel="stylesheet" href="../css/standard.css">
    <style>
        .container { margin: 20px auto; max-width: 1200px; }
        .upload-form { margin: 50px auto; width: 500px; text-align: center; padding: 30px; border: 1px solid #ddd; border-radius: 8px; }
        .result-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .result-table th, .result-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .result-table th { background-color: #f0f0f0; }
        .set-row { background-color: #e6f3ff; font-weight: bold; }
        .component-row { background-color: #ffffff; }
        .number { text-align: right; }
        .btn { padding: 10px 20px; margin: 5px; cursor: pointer; border: none; border-radius: 4px; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .summary { margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-radius: 4px; }
    </style>
</head>
<body>';

print '<table width="100%" align="center" border="0" cellspacing="1" cellpadding="0"><tbody>';
print '<tr><td width="10%" ' . $top_bund . '><a href="varer.php">Luk</a></td>';
print '<td width="80%" align="center" ' . $top_bund . '><b>' . $title . '</b></td>';
print '<td width="10%" ' . $top_bund . '></td></tr>';
print '</tbody></table>';

print '<div class="container">';

if ($_POST && isset($_FILES['csvfile']) && $_FILES['csvfile']['error'] === UPLOAD_ERR_OK) {
    $uploadedFile = $_FILES['csvfile']['tmp_name'];
    $results = processCSV($uploadedFile);
    // Display as HTML table
    displayHTMLTable($results);
} else {
    displayUploadForm();
}

print '</div>';
print '</body></html>';

/**
 * Process a CSV file and extract set items with their components
 */
function processCSV($filePath) {
    $results = [];
    
    // Open and parse CSV
    if (($handle = fopen($filePath, "r")) === false) {
        return [['error' => 'Kunne ikke åbne filen.']];
    }
    
    // Read first line to detect delimiter
    $firstLine = fgets($handle);
    rewind($handle);
    
    // Detect delimiter
    $delimiter = ";";
    if (substr_count($firstLine, ",") > substr_count($firstLine, ";")) {
        $delimiter = ",";
    } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ";")) {
        $delimiter = "\t";
    }
    
    // Read header row
    $headers = fgetcsv($handle, 2500, $delimiter);
    
    if (!$headers) {
        fclose($handle);
        return [['error' => 'Kunne ikke læse CSV headers.']];
    }
    
    // Map header names to indices (case-insensitive)
    $headerMap = [];
    foreach ($headers as $idx => $name) {
        $headerMap[strtoupper(trim($name))] = $idx;
    }
    
    // Required columns
    $hmsIdx = $headerMap['HMS'] ?? null;
    $aliasIdx = $headerMap['ALIAS'] ?? null;
    $beskIdx = $headerMap['BESKRIVELSE'] ?? null;
    $kostIdx = $headerMap['KOSTPRIS'] ?? null;
    $salgIdx = $headerMap['SALGSPRIS'] ?? null;
    
    if ($hmsIdx === null) {
        fclose($handle);
        return [['error' => 'HMS kolonne ikke fundet i CSV. Forventede headers: HMS, ALIAS, Beskrivelse, Kostpris, Salgspris']];
    }
    
    // Process each row
    while (($row = fgetcsv($handle, 2500, $delimiter)) !== false) {
        if (empty($row) || !isset($row[$hmsIdx]) || empty(trim($row[$hmsIdx]))) {
            continue;
        }
        
        $hms = trim($row[$hmsIdx]);
        $alias = ($aliasIdx !== null && isset($row[$aliasIdx])) ? trim($row[$aliasIdx]) : '';
        $beskrivelse = ($beskIdx !== null && isset($row[$beskIdx])) ? trim($row[$beskIdx]) : '';
        $kostpris = ($kostIdx !== null && isset($row[$kostIdx])) ? parseNumber($row[$kostIdx]) : 0;
        $salgspris = ($salgIdx !== null && isset($row[$salgIdx])) ? parseNumber($row[$salgIdx]) : 0;
        
        // Find if this item exists in database
        $dbItem = getItemByVarenr($hms);
        
        // Add the set item itself
        $results[] = [
            'type' => 'SÆT',
            'varenr' => $hms,
            'alias' => $alias,
            'beskrivelse' => $dbItem['beskrivelse'] ?? $beskrivelse,
            'kostpris' => (float)($dbItem['kostpris'] ?? $kostpris),
            'salgspris' => (float)($dbItem['salgspris'] ?? $salgspris),
            'retail_price' => (float)($dbItem['retail_price'] ?? 0),
            'enhed' => $dbItem['enhed'] ?? '',
            'netweight' => (float)($dbItem['netweight'] ?? 0),
            'grossweight' => (float)($dbItem['grossweight'] ?? 0),
            'length' => (float)($dbItem['length'] ?? 0),
            'width' => (float)($dbItem['width'] ?? 0),
            'height' => (float)($dbItem['height'] ?? 0),
            'samlevare' => $dbItem['samlevare'] ?? '',
            'varegruppe' => $dbItem['gruppe_navn'] ?? '',
            'antal' => 1,
            'parent_varenr' => '',
            'found_in_db' => $dbItem ? true : false
        ];
        
        // Find set components from database
        if ($dbItem) {
            $components = getSetComponents($dbItem['id']);
            foreach ($components as $comp) {
                $results[] = [
                    'type' => 'DELVARE',
                    'varenr' => $comp['varenr'],
                    'alias' => '',
                    'beskrivelse' => $comp['beskrivelse'],
                    'kostpris' => $comp['kostpris'],
                    'salgspris' => $comp['salgspris'],
                    'retail_price' => $comp['retail_price'],
                    'enhed' => $comp['enhed'],
                    'netweight' => $comp['netweight'],
                    'grossweight' => $comp['grossweight'],
                    'length' => $comp['length'],
                    'width' => $comp['width'],
                    'height' => $comp['height'],
                    'samlevare' => $comp['samlevare'],
                    'varegruppe' => $comp['gruppe_navn'],
                    'antal' => $comp['antal'],
                    'parent_varenr' => $hms,
                    'found_in_db' => true
                ];
            }
        }
    }
    
    fclose($handle);
    return $results;
}

/**
 * Get item by varenr
 */
function getItemByVarenr($varenr) {
    global $regnaar;
    $qtxt = "SELECT v.id, v.varenr, v.beskrivelse, v.kostpris, v.salgspris, v.retail_price,
                    v.enhed, v.netweight, v.grossweight, v.length, v.width, v.height,
                    v.samlevare, v.gruppe, g.beskrivelse as gruppe_navn
             FROM varer v
             LEFT JOIN grupper g ON g.kodenr = v.gruppe AND g.art = 'VG' AND g.fiscal_year = '$regnaar'
             WHERE v.varenr = '" . db_escape_string($varenr) . "'";
    $result = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    $row = db_fetch_array($result);
    
    return $row ?: null;
}

/**
 * Get all component items for a set (including nested sets)
 */
function getSetComponents($setId, $depth = 0) {
    $components = [];
    
    // Prevent infinite recursion
    if ($depth > 10) {
        return $components;
    }
    
    global $regnaar;
    
    // Get all components from styklister
    $qtxt = "SELECT v.id, v.varenr, v.beskrivelse, v.kostpris, v.salgspris, v.retail_price,
                    v.enhed, v.netweight, v.grossweight, v.length, v.width, v.height,
                    v.samlevare, v.gruppe, g.beskrivelse as gruppe_navn, s.antal 
             FROM styklister s 
             JOIN varer v ON s.vare_id = v.id 
             LEFT JOIN grupper g ON g.kodenr = v.gruppe AND g.art = 'VG' AND g.fiscal_year = '$regnaar'
             WHERE s.indgaar_i = '$setId' 
             ORDER BY s.posnr";
    
    $result = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    
    while ($row = db_fetch_array($result)) {
        $components[] = [
            'id' => $row['id'],
            'varenr' => $row['varenr'],
            'beskrivelse' => $row['beskrivelse'],
            'kostpris' => (float)$row['kostpris'],
            'salgspris' => (float)$row['salgspris'],
            'retail_price' => (float)$row['retail_price'],
            'enhed' => $row['enhed'] ?? '',
            'netweight' => (float)($row['netweight'] ?? 0),
            'grossweight' => (float)($row['grossweight'] ?? 0),
            'length' => (float)($row['length'] ?? 0),
            'width' => (float)($row['width'] ?? 0),
            'height' => (float)($row['height'] ?? 0),
            'samlevare' => $row['samlevare'] ?? '',
            'gruppe_navn' => $row['gruppe_navn'] ?? '',
            'antal' => (float)$row['antal']
        ];
        
        // If this component is also a set, get its components recursively
        if ($row['samlevare'] === 'on') {
            $nestedComponents = getSetComponents($row['id'], $depth + 1);
            foreach ($nestedComponents as $nested) {
                $nested['antal'] = $nested['antal'] * (float)$row['antal'];
                $components[] = $nested;
            }
        }
    }
    
    return $components;
}

/**
 * Parse a number from Danish format (comma as decimal separator)
 */
function parseNumber($value) {
    $value = trim($value);
    if (empty($value)) return 0;
    // Handle Danish number format (1.234,56 -> 1234.56)
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);
    return (float)$value;
}

/**
 * Output results as CSV
 */
function outputCSV($results) {
    $output = fopen('php://output', 'w');
    
    // BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header
    fputcsv($output, [
        'Type', 'Varenr', 'Alias', 'Beskrivelse', 'Salgspris', 'Kostpris', 'Vejl. pris',
        'Enhed', 'Nettovægt', 'Bruttovægt', 'L x B x H', 'Stykliste', 'Varegruppe',
        'Antal', 'Parent_Varenr'
    ], ';');
    
    foreach ($results as $row) {
        if (isset($row['error'])) {
            fputcsv($output, ['ERROR', $row['error']], ';');
            continue;
        }
        
        // Format L x B x H
        $dimensions = '';
        if ($row['length'] > 0 || $row['width'] > 0 || $row['height'] > 0) {
            $dimensions = number_format($row['length'], 0) . ' x ' . 
                         number_format($row['width'], 0) . ' x ' . 
                         number_format($row['height'], 0);
        }
        
        fputcsv($output, [
            $row['type'],
            $row['varenr'],
            $row['alias'],
            $row['beskrivelse'],
            number_format($row['salgspris'], 2, ',', '.'),
            number_format($row['kostpris'], 2, ',', '.'),
            number_format($row['retail_price'], 2, ',', '.'),
            $row['enhed'],
            number_format($row['netweight'], 3, ',', '.'),
            number_format($row['grossweight'], 3, ',', '.'),
            $dimensions,
            $row['samlevare'] === 'on' ? 'Ja' : 'Nej',
            $row['varegruppe'],
            number_format($row['antal'], 2, ',', '.'),
            $row['parent_varenr']
        ], ';');
    }
    
    fclose($output);
}

/**
 * Display upload form for web interface
 */
function displayUploadForm() {
    print '<div class="upload-form">
        <h2>Import CSV med sæt-varer</h2>
        <p>CSV filen skal have kolonnerne:<br><strong>HMS, ALIAS, Beskrivelse, Kostpris, Salgspris</strong></p>
        <p><small>HMS er varenummeret. Scriptet finder automatisk delvarer for hvert sæt.</small></p>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="csvfile" accept=".csv,.txt" required><br><br>
            <button type="submit" name="display" value="1" class="btn btn-primary">Vis som tabel</button>
            <button type="submit" name="download" value="1" class="btn btn-secondary">Download CSV</button>
        </form>
    </div>';
}

/**
 * Display results as HTML table
 */
function displayHTMLTable($results) {
    // Count stats
    $setCount = 0;
    $componentCount = 0;
    $notFoundCount = 0;
    
    foreach ($results as $row) {
        if (isset($row['error'])) continue;
        if ($row['type'] === 'SÆT') {
            $setCount++;
            if (empty($row['found_in_db'])) $notFoundCount++;
        } else {
            $componentCount++;
        }
    }
    
    print '<div class="summary">
        <strong>Oversigt:</strong> ' . $setCount . ' sæt, ' . $componentCount . ' delvarer fundet. ';
    if ($notFoundCount > 0) {
        print '<span style="color:orange;">' . $notFoundCount . ' sæt ikke fundet i databasen.</span>';
    }
    print '</div>';
    
    // Download button with data
    print '<form method="post" enctype="multipart/form-data" style="display:inline;">
        <input type="hidden" name="csvfile" value="">
        <a href="csv_set_import.php" class="btn btn-secondary">← Ny import</a>
    </form>';
    
    print '<table class="result-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Varenr</th>
                <th>Beskrivelse</th>
                <th>Salgspris</th>
                <th>Kostpris</th>
                <th>Vejl. pris</th>
                <th>Enhed</th>
                <th>Nettovægt</th>
                <th>Bruttovægt</th>
                <th>L x B x H</th>
                <th>Stykliste</th>
                <th>Varegruppe</th>
                <th>Antal</th>
                <th>Parent</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($results as $row) {
        if (isset($row['error'])) {
            print '<tr style="color: red;"><td colspan="14">FEJL: ' . htmlspecialchars($row['error']) . '</td></tr>';
            continue;
        }
        
        $rowClass = $row['type'] === 'SÆT' ? 'set-row' : 'component-row';
        $notFoundStyle = (empty($row['found_in_db']) && $row['type'] === 'SÆT') ? 'color: orange;' : '';
        
        // Format L x B x H
        $dimensions = '';
        if ($row['length'] > 0 || $row['width'] > 0 || $row['height'] > 0) {
            $dimensions = number_format($row['length'], 0) . ' x ' . 
                         number_format($row['width'], 0) . ' x ' . 
                         number_format($row['height'], 0);
        }
        
        print '<tr class="' . $rowClass . '" style="' . $notFoundStyle . '">';
        print '<td>' . htmlspecialchars($row['type']) . '</td>';
        print '<td>' . htmlspecialchars($row['varenr']) . '</td>';
        print '<td>' . htmlspecialchars($row['beskrivelse']) . '</td>';
        print '<td class="number">' . number_format($row['salgspris'], 2, ',', '.') . '</td>';
        print '<td class="number">' . number_format($row['kostpris'], 2, ',', '.') . '</td>';
        print '<td class="number">' . number_format($row['retail_price'], 2, ',', '.') . '</td>';
        print '<td>' . htmlspecialchars($row['enhed']) . '</td>';
        print '<td class="number">' . number_format($row['netweight'], 3, ',', '.') . '</td>';
        print '<td class="number">' . number_format($row['grossweight'], 3, ',', '.') . '</td>';
        print '<td>' . $dimensions . '</td>';
        print '<td>' . ($row['samlevare'] === 'on' ? 'Ja' : 'Nej') . '</td>';
        print '<td>' . htmlspecialchars($row['varegruppe']) . '</td>';
        print '<td class="number">' . number_format($row['antal'], 2, ',', '.') . '</td>';
        print '<td>' . htmlspecialchars($row['parent_varenr']) . '</td>';
        print '</tr>';
    }
    
    print '</tbody></table>';
}
?>
