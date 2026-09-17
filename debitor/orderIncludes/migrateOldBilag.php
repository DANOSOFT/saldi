<?php
// --- debitor/orderIncludes/migrateOldBilag.php --- patch 5.0.0 --- 2026-06-03 ---
// Migrerer gamle bilag fra bilag.php-systemet til documents-systemet.
// Kald når $id og $dokument er sat (efter ordre-data er loadet).
// Gør ingenting hvis $dokument er tom, eller migrering allerede er sket.
//20260603 CL/PHR Gammel bilag.php-håndtering i debitorordrer erstattet med documents.php
//                (source=debitorOrdrer). Denne fil migrerer eksisterende bilag ved første
//                åbning af ordren: finder bilag_{id}[_*] i bilag/$db/bilag/ordrer/,
//                kopierer til $docFolder/$db/debitor/orders/$id/ og indsætter i documents-tabellen.

if (empty($dokument) || empty($id)) return;

// Tjek om der allerede er dokumenter i det nye system
$qtxt = "SELECT id FROM documents WHERE source = 'debitorOrdrer' AND source_id = '$id' LIMIT 1";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) return;

// Find docFolder (samme prioritet som documents.php)
$sth = dirname(dirname(dirname(__FILE__)));
if      (file_exists("$sth/documents")) $docFolder = "$sth/documents";
elseif  (file_exists("$sth/owncloud"))  $docFolder = "$sth/owncloud";
elseif  (file_exists("$sth/bilag"))     $docFolder = "$sth/bilag";
else return;

// Find gamle filer på disk: bilag/{db}/bilag/ordrer/bilag_{id}[_*]
$oldDir = "$sth/bilag/$db/bilag/ordrer";
$oldFiles = glob("$oldDir/bilag_{$id}") ?: array();
$oldFiles = array_merge($oldFiles, glob("$oldDir/bilag_{$id}_*") ?: array());
sort($oldFiles); // Kronologisk rækkefølge (timestamp i filnavn)

if (empty($oldFiles)) return;

// Originale filnavne fra ordrer.dokument (kan være |-separeret)
$originalNames = array_filter(array_map('trim', explode('|', $dokument)));

// Opret destination-mappe
$destDir = "$docFolder/$db/debitor/orders/$id";
if (!file_exists("$docFolder/$db"))                    mkdir("$docFolder/$db", 0755, true);
if (!file_exists("$docFolder/$db/debitor"))            mkdir("$docFolder/$db/debitor", 0755);
if (!file_exists("$docFolder/$db/debitor/orders"))     mkdir("$docFolder/$db/debitor/orders", 0755);
if (!file_exists($destDir))                            mkdir($destDir, 0755);

// Hent globalId
$globalId = 1;
$qtxt = "SELECT var_value FROM settings WHERE var_name = 'globalId'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $globalId = $r['var_value'];

$filePath = "/debitor/orders/$id";
$movedCount = 0;

foreach ($oldFiles as $i => $srcFile) {
    // Brug originalt filnavn hvis tilgængeligt, ellers brug det genererede navn
    if (isset($originalNames[$i])) {
        $destName = $originalNames[$i];
    } else {
        // Fallback: brug originalt navn fra dokument hvis kun ét, eller nummerér
        $destName = count($originalNames) === 1 ? reset($originalNames) : basename($srcFile);
    }

    $destFile = "$destDir/$destName";

    // Undgå overskrivning ved navnekollision
    if (file_exists($destFile)) {
        $base = pathinfo($destName, PATHINFO_FILENAME);
        $ext  = pathinfo($destName, PATHINFO_EXTENSION);
        $destName = $base . '_' . ($i + 1) . ($ext ? ".$ext" : '');
        $destFile = "$destDir/$destName";
    }

    if (!copy($srcFile, $destFile)) continue;
    chmod($destFile, 0644);

    $qtxt  = "INSERT INTO documents (global_id, filename, filepath, source, source_id, timestamp, user_id) VALUES ";
    $qtxt .= "('" . db_escape_string($globalId) . "', '" . db_escape_string($destName) . "', '" . db_escape_string($filePath) . "', 'debitorOrdrer', '$id', '" . date('U') . "', '" . (isset($bruger_id) ? $bruger_id : 0) . "')";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);

    $movedCount++;
}

if ($movedCount > 0) {
    // Ryd dokument-feltet nu migreringen er sket
    db_modify("UPDATE ordrer SET dokument = '' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
    $dokument = '';
}
