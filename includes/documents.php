<?php
// --- includes/documents.php -----patch 4.1.1 ----2025-08-22------------
//                           LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
//20230622 - LOE Updated file path and some related modifications.
//20240412 - PHR Various modifications
//20250815 - LOE Create 'bilag' file specifically for kassekladde and , others can be created based  on what is needed

@session_start();
$s_id=session_id();
$css="../css/std.css";

$title="Documents";
print '<script src="../javascript/jquery-3.6.4.min.js"></script>';
print '<link rel="stylesheet" type="text/css" href="../css/dragAndDrop.css">';
$jsFile = '../javascript/dragAndDrop.js';
$version = file_exists($jsFile) ? filemtime($jsFile) : time();
print "<script LANGUAGE=\"javascript\" TYPE=\"text/javascript\" SRC=\"{$jsFile}?v={$version}\"></script>";


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (!isset($userId) || !$userId) $userId = $bruger_id;

print "<div align=\"center\">";
$fokus=$dokument = $openPool=$docFocus=$deleteDoc=$showDoc= $poolFile=$moveDoc=$kladde_id=$bilag=$source=$sourceId=null;
if(($_GET)||($_POST)) {

	$funktion=if_isset($_GET['funktion']);
	if (isset($_GET['sourceId'])) {
		$bilag		  = if_isset($_GET['bilag']);
		$fokus		  = if_isset($_GET['fokus']);
		$docFocus	  = if_isset($_GET['docFocus']);
		$sourceId   = if_isset($_GET['sourceId'],0);
		$source     = if_isset($_GET['source']);
		$showDoc    = if_isset($_GET['showDoc']);
		$deleteDoc  = if_isset($_GET['deleteDoc']);
		$moveDoc  	= if_isset($_GET['moveDoc']);
		$kladde_id  = if_isset($_GET['kladde_id']);
		$dokument   = if_isset($_GET['dokument']);
		$openPool    = if_isset($_GET['openPool']);
		$poolFile    = if_isset($_GET['poolFile']);
	}
	if (isset($_POST['sourceId'])) {
		$sourceId  = $_POST['sourceId'];
		$source    = $_POST['source'];	
		$kladde_id = $_POST['kladde_id'];
	}
}

########################

//++++++++++++++++++++++++++++==
function renameFileWithPermissionFix($oldPath, $newPath) {
	global $db;
    error_log("INPUT oldPath: $oldPath");
    error_log("INPUT newPath: $newPath");
    error_log("---- DEBUG START ----");
    $baseDir = realpath(__DIR__ . '/../../');
	if (!$baseDir) {
    error_log("ERROR: baseDir not found via realpath");
    return false;
	}
	error_log("Base directory: $baseDir");
    // Extract the first directory after 'html' from oldPath
    $firstDirOld = getDbPrefix($db);
    $firstDirNew = getDbPrefix($db);

    // If none found, default to 'saldi' (or whatever your actual directory is)
	error_log("First old Directory : $firstDirOld");
	error_log("First new Directory : $firstDirNew");

    // Strip leading slashes and normalize relative path
    $oldPathNormalized = ltrim($oldPath, '/');
    $newPathNormalized = ltrim($newPath, '/');
	$fileName = basename($newPathNormalized);

    // Prepend directory after html if missing
    if (strpos($oldPathNormalized, $firstDirOld) !== 0) {
    	$oldPathNormalized = $firstDirOld . '/bilag/' . $oldPathNormalized;
	}
	if (strpos($newPathNormalized, $firstDirNew) !== 0) {
		$newPathNormalized = $firstDirNew . '/bilag/' . $newPathNormalized;
	}

    // Build full paths
    $oldFullPath = $baseDir . DIRECTORY_SEPARATOR . $oldPathNormalized;
    $newDirPath  = $baseDir . DIRECTORY_SEPARATOR . dirname($newPathNormalized);
    $newFilePath = $newDirPath . DIRECTORY_SEPARATOR . basename($newPathNormalized);

    error_log("Unresolved Old Full Path: $oldFullPath");
    error_log("Unresolved New Dir Path: $newDirPath");
    error_log("Unresolved New File Path: $newDirPath/$fileName");

    // Normalize paths (remove '/../' etc.)
    $oldFullPath = normalizePath($oldFullPath,$firstDirOld);
    $newDirPath  = normalizePath($newDirPath,$firstDirNew);
    $newFilePath = $newDirPath . DIRECTORY_SEPARATOR . $fileName;

    error_log("Normalized Old Full Path: $oldFullPath");
    error_log("Normalized New Dir Path: $newDirPath");
    error_log("Normalized New File Path: $newFilePath");

    // Check existence
    if (!file_exists($oldFullPath)) {
        error_log("Resolved Old File Absolute Path: NOT FOUND");
        return false;
    }
    if (!is_dir($newDirPath)) {
        error_log("Resolved Target Directory Path: NOT FOUND");
        return false;
    }

    // Permissions
    if (!is_readable($oldFullPath)) {
        error_log("Source file not readable: $oldFullPath");
        return false;
    }
    if (!is_writable($newDirPath)) {
		error_log("Target directory not writable, attempting to change permissions: $newDirPath");
		@chmod($newDirPath, 0777);

		// Re-check if writable after chmod
		if (!is_writable($newDirPath)) {
			error_log("Failed to make target directory writable: $newDirPath");
			return false;
		}
	}
	if (file_exists($newFilePath)) {
		error_log("Target file already exists: $newFilePath");
		return false;
	}

    // Attempt rename
    if (@rename($oldFullPath, $newFilePath)) {
        error_log("Rename succeeded");
        return true;
    }

    error_log("Rename failed, trying copy + unlink fallback...");

    // Fallback
    if (@copy($oldFullPath, $newFilePath)) {
        if (@unlink($oldFullPath)) {
            error_log("Copy + unlink fallback succeeded");
            return true;
        } else {
            error_log("Failed to unlink after copy");
            @unlink($newFilePath);
            return false;
        }
    } else {
        $err = error_get_last();
        error_log("Copy failed: " . ($err['message'] ?? 'Unknown error'));
        return false;
    }
}

// Utility to normalize paths (resolve '../' and './' manually)
function normalizePath($path, $insertAfterHtml = null) {
    $isAbsolute = strpos($path, '/') === 0;
    $parts = [];

    foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($parts);
        } else {
            $parts[] = $segment;
        }
    }

    // If insertAfterHtml is set, find 'html' and ensure it’s followed by that string
    if ($insertAfterHtml !== null) {
        $htmlIndex = array_search('html', $parts);
        if ($htmlIndex !== false) {
            // Ensure the next part is the desired one
            if (isset($parts[$htmlIndex + 1])) {
                $parts[$htmlIndex + 1] = $insertAfterHtml;
            } else {
                // If nothing follows html, insert it
                array_splice($parts, $htmlIndex + 1, 0, $insertAfterHtml);
            }
        }
        // Optional: else do nothing if html is not found
    }

    return ($isAbsolute ? '/' : '') . implode('/', $parts);
}


function getDbPrefix($db) {
    $parts = explode('_', $db);
    return $parts[0];
}




//+++++++++++++++++++++++++++==




if (isset($_GET['renameDoc']) && isset($_POST['newFileName']) && isset($_POST['oldFileName'])) {
	$oldFile = basename($_POST['oldFileName']);
	$newFile = basename($_POST['newFileName']);  // sanitize to avoid directory traversal
	
	if(!$sourceId && $source){
		$sourceId = $_GET['sourceId'] ?? '';
		$source = $_GET['source'] ?? '';  
	}
	

	$docDir = "../bilag/$db/pulje"; 
	$oldPath = "$docDir/$oldFile";
	$newPath = "$docDir/$newFile";
	error_log("OldPatheeeeeeeeeeeeeee: $oldPath, NewPath: $newPath");
	// Rename on disk
	error_log("old path before using relapath: $oldPath");
	#$oldPath = realpath($oldPath);
	error_log("xxxxxxxiiiiiiiiiiiiiiiii: New path : $newPath, Old path: $oldPath");
	#$newPath = realpath(dirname($newPath)) . DIRECTORY_SEPARATOR . basename($newPath);
	if (file_exists($oldPath) && !file_exists($newPath)) {
		if (renameFileWithPermissionFix($oldPath, $newPath)) {
			// Update database after successful rename/copy+unlink
			$oldFileEsc = db_escape_string($oldFile);
			$newFileEsc = db_escape_string($newFile);

			$updateQ = "UPDATE documents 
						SET filename = '$newFileEsc', filepath = 'pulje'
						WHERE filename = '$oldFileEsc' AND source = '$source' AND source_id = '$sourceId'";

			if (db_modify($updateQ, __FILE__ . " linje " . __LINE__)) {
				echo "<div style='color:green;'>Filnavn opdateret: pulje</div>";
			} else {
				echo "<div style='color:red;'>Kunne ikke opdatere databasen.</div>";
			}
		} else {
			echo "<div style='color:red;'>Kunne ikke omdøbe filen på disken.</div>";
		}

	} else {
		echo "<div style='color:red;'>Fil findes ikke, eller nyt navn eksisterer allerede.</div>";
	}
}
########################




$params = "kladde_id=$kladde_id&bilag=$bilag&source=$source&sourceId=$sourceId&fokus=$fokus";

if (isset($_GET['test'])) exit;
#xit;
$qtxt = "select var_value from settings where var_name = 'globalId'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $globalId = $r['var_value'];
#else alert ('Missing global ID');

if (file_exists('../owncloud')) $docFolder = '../owncloud';
elseif (file_exists('../bilag')) $docFolder = '../bilag';
elseif (file_exists('../documents')) $docFolder = '../documents';


if ($source === 'kassekladde' && empty($docFolder)) {  
    $docFolder = "../bilag";
    
    if (!file_exists($docFolder)) {
        mkdir($docFolder, 0755, true); 
    }
}




if ($dokument) {
	if (file_exists("$docFolder/$db/bilag/kladde_$kladde_id/bilag_$sourceId")) {
		include("docsIncludes/convertOldDoc.php");
	}
	# else print "dokument ".findtekst('1740|ikke fundet', $sprog_id);
}
#$openPool,$sourceId,$source,$bilag,$fokus,$poolFile,$docFolder
#echo $poolParams;


// ---------- Main table start ---------
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; 
print "<tr><td colspan= \"3\" height = \"25\" align=\"center\" valign=\"top\">";
// ---------- Header table start ---------
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
include("docsIncludes/header.php");
// ---------- Header table end ---------
print "</tbody></table>";
print "</td></tr><tr><td width = '20%'>";
// ---------- Left table start ---------
print "<table width=\"100%\" height=\"98%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
if ($openPool) {
	include ("docsIncludes/docPool.php");
	docPool($sourceId,$source,$kladde_id,$bilag,$fokus,$poolFile,$docFolder,$docFocus);
	exit;
}
#xit;
if ($moveDoc) include("docsIncludes/moveDoc.php");
elseif ($deleteDoc) include("docsIncludes/deleteDoc.php");
include("docsIncludes/listDocs.php");
include("docsIncludes/uploadDoc.php"); 

// Generate the URL with the query parameters
#$targetPage = "docsIncludes/emailDoc.php?" . $queryParameters;
$poolParams =
	"openPool=1"."&".
	"kladde_id=$kladde_id"."&".
	"bilag=$bilag"."&".
	"fokus=$fokus"."&".
	"poolFile=$poolFile"."&".
	"docFolder=$docFolder"."&".
	"sourceId=$sourceId"."&".
	"source=$source";
$targetPage = "documents.php?" . $poolParams;
#****************
print "<br>".findtekst('2591|Bilag kan sendes til', $sprog_id)."<br>";
print "<a href='mailto:bilag_".$db."@".$_SERVER['SERVER_NAME']."'>";
print "bilag_".$db."@".$_SERVER['SERVER_NAME']."</a><br><br>\n";
print '<a href="' . $targetPage . '">';
print "<button id=\"emailD\">".findtekst('2592|Dokumentpulje',$sprog_id)."</button>";
print '</a><br>';
;



$dropZone = "<div id='dropZone' ondrop='handleDrop(event)' ondragover='handleDragOver(event)' style='width: 200px; height: 150px; border: 2px dashed #ccc; text-align: center; padding: 20px;'>
    <span id='dropText'>".findtekst('2593|Træk og slip PDF-fil her', $sprog_id)."</span>
</div>";

$clipImage = "<span class='clip-image drop-zone-container' title='Drag and Drop the file here'>
    {$dropZone}
</span>";

print $clipImage;




print "</td></tr>";



// Print the JavaScript code used in dragAndDrop.js 
print "<script>
var clipVariables = {
sourceId: $sourceId ,
kladde_id: $kladde_id,
bilag: $bilag,
fokus: '$fokus',
source: '$source'
};
</script>";



// ---------- Left table end ---------
print "</tbody></table>";
print "</td><td width = '80%'>";
// ---------- Right table start ---------
print "<table width=\"100%\" height=\"98%\" border=\"1\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
include("docsIncludes/showDoc.php");
// ---------- Right table end ---------
print "</tbody></table>";
print "</td></tr>";
// ---------- Main table start ---------
print "</tbody></table>";
print "</body></html>";
?>
