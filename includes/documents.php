<?php
// --- includes/documents.php -----patch 4.1.1 ----2025-10-10------------
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
//20250824 - LOE Clean up to reduce the error logs with if_isset()
//20250827 - LOE Implement creating .info files for existing pool pdf without it. 
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

	$funktion=if_isset($_GET,NULL,'funktion');
	if (isset($_GET['sourceId'])) {
		$bilag		  = if_isset($_GET, NULL,'bilag');
		$fokus		  = if_isset($_GET, NULL,'fokus');
		$docFocus	  = if_isset($_GET, NULL,'docFocus');
		$sourceId   = if_isset($_GET, NULL,'sourceId');
		$source     = if_isset($_GET, NULL,'source');
		$showDoc    = if_isset($_GET, NULL,'showDoc');
		$deleteDoc  = if_isset($_GET, NULL,'deleteDoc');
		$moveDoc  	= if_isset($_GET, NULL,'moveDoc');
		$kladde_id  = if_isset($_GET, NULL,'kladde_id');
		$dokument   = if_isset($_GET, NULL,'dokument');
		$openPool    = if_isset($_GET, NULL,'openPool');
		$poolFile    = if_isset($_GET, NULL,'poolFile');
	}
	if (isset($_POST['sourceId'])) {
		$sourceId  = $_POST['sourceId'];
		$source    = $_POST['source'];	
		$kladde_id = $_POST['kladde_id'];
	}
}
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

#########
function sanitize_filename($filename) {
    // Replace known extended Latin/Danish characters
    $translit = [
        'æ' => 'ae', 'Æ' => 'Ae',
        'ø' => 'oe', 'Ø' => 'Oe',
        'å' => 'aa', 'Å' => 'Aa',
        'ä' => 'ae', 'Ä' => 'Ae',
        'ö' => 'oe', 'Ö' => 'Oe',
        'ü' => 'ue', 'Ü' => 'Ue',
        'ß' => 'ss',
        'ñ' => 'n',  'Ñ' => 'N',
        'á' => 'a',  'Á' => 'A',
        'é' => 'e',  'É' => 'E',
        'í' => 'i',  'Í' => 'I',
        'ó' => 'o',  'Ó' => 'O',
        'ú' => 'u',  'Ú' => 'U'
    ];
    $filename = strtr($filename, $translit);

    // Fallback transliteration for any remaining special chars
    $filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);

	//if any extra
	$filename = preg_replace('/^(_{0,2}(UTF-8|ISO-8859-1)?_?Q_*)?/i', '', $filename);
    // Remove all but safe characters
    $filename = preg_replace('/[^\w\-\. ]+/', '_', $filename);

    // Trim unwanted characters from ends
    $filename = trim($filename, " \t\n\r\0\x0B.");

    // Separate the name and extension
    $dot_position = strrpos($filename, '.');
    if ($dot_position !== false) {
        $name = substr($filename, 0, $dot_position);
        $ext = substr($filename, $dot_position); // Includes the dot
    } else {
        $name = $filename;
        $ext = '';
    }

    // Truncate the name part if longer than 54 characters
    if (strlen($name) > 54) {
        $name = substr($name, 0, 54);
    }

    return $name . $ext;
}
#######



if ($dokument) {
	if (file_exists("$docFolder/$db/bilag/kladde_$kladde_id/bilag_$sourceId")) {
		include("docsIncludes/convertOldDoc.php");
	}
	# else print "dokument ".findtekst('1740|ikke fundet', $sprog_id);
}
#$openPool,$sourceId,$source,$bilag,$fokus,$poolFile,$docFolder
#echo $poolParams;

// Handle file uploads for pool view
if (isset($_FILES) && isset($_FILES['uploadedFile']['name']) && $sourceId) {
	$fileTypes = array('jpg','jpeg','pdf','png');
	$fileName = basename($_FILES['uploadedFile']['name']);
	list($tmp,$fileType) = explode("/",$_FILES['uploadedFile']['type']);
	if (in_array(strtolower($fileType),$fileTypes)) {
		$poolDir = "$docFolder/$db/pulje";
		if (!is_dir($poolDir)) {
			mkdir($poolDir, 0755, true);
		}
		// Sanitize filename
		$baseName = pathinfo($fileName, PATHINFO_FILENAME);
		$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		$baseName = sanitize_filename($baseName);
		$targetFile = "$poolDir/$baseName.pdf";
		
		// Convert images to PDF if needed
		if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
			$tempFile = "$poolDir/$baseName.$ext";
			if (move_uploaded_file($_FILES['uploadedFile']['tmp_name'], $tempFile)) {
				system("convert '$tempFile' '$targetFile'");
				if (file_exists($targetFile)) {
					unlink($tempFile);
				} else {
					$targetFile = $tempFile; // Fallback to original if conversion fails
				}
			}
		} else {
			// For PDF files, move directly
			move_uploaded_file($_FILES['uploadedFile']['tmp_name'], $targetFile);
		}
		
		// Create .info file
		if (file_exists($targetFile)) {
			$infoFile = "$poolDir/$baseName.info";
			if (!file_exists($infoFile)) {
				file_put_contents($infoFile, $baseName . PHP_EOL);
				chmod($infoFile, 0666);
			}
			// Redirect to pool view after successful upload
			$poolParams =
				"openPool=1"."&".
				"kladde_id=$kladde_id"."&".
				"bilag=$bilag"."&".
				"fokus=$fokus"."&".
				"poolFile=$baseName.pdf"."&".
				"docFolder=$docFolder"."&".
				"sourceId=$sourceId"."&".
				"source=$source";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=documents.php?$poolParams\">";
			exit;
		}
	}
}

// Check for openPool BEFORE printing any table structure
// Make sure we check both the variable and the GET parameter
$openPool = $openPool || (isset($_GET['openPool']) && ($_GET['openPool'] == '1' || $_GET['openPool'] == 1));
if ($openPool) {
	$finalDestination = "$docFolder/$db/pulje";
		#############
		if (is_dir($docFolder)) {
			$dbFolder = "$docFolder/$db";
			if (is_dir($dbFolder)) {
				if (is_dir($finalDestination)) {
					################start convert all .png, jpeg, jpg to .pdf and create .info file for them
							$allowedImageExts = ['jpg', 'jpeg', 'png'];
							$files = scandir($finalDestination);

							foreach ($files as $file) {
								if (substr($file, 0, 1) === '.') continue; // skip hidden files like .DS_Store

								$fullPath = "$finalDestination/$file";
								if (!is_file($fullPath)) continue; // skip directories

								$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
								$base = pathinfo($file, PATHINFO_FILENAME);

								if (in_array($ext, $allowedImageExts)) {
									$base = sanitize_filename($base);
									
									$newFile=sanitize_filename($newFile);
									$newFile = $base . '.pdf';
									$to = "$finalDestination/$newFile";

									// Convert image to PDF using ImageMagick's convert
									system("convert '$fullPath' '$to'");

									if (file_exists($to)) {
										// Delete the original image
										unlink($fullPath);
										$infoFile = "$finalDestination/$base.info";
										
										file_put_contents($infoFile, $base . "\n");

										
										 error_log("Converted $file to PDF and created info file on " . date("Y-m-d H:i:s"));
									}
								}
							}
					clearstatcache(); # Ensure newly created PDFs are visible to glob()
					##############end


					// Get all PDF files in the final destination
					$pdfFiles = glob($finalDestination . '/*.pdf');
					
					if (!empty($pdfFiles)) {
						foreach ($pdfFiles as $pdfPath) {
							$pdfFilename = basename($pdfPath);
							$baseName = pathinfo($pdfFilename, PATHINFO_FILENAME);
							$infoFilePreSan = $finalDestination . '/' . $baseName. '.info';
							$baseNameSan =  sanitize_filename($baseName);
							$infoFile = $finalDestination . '/' . $baseNameSan . '.info';
							
							// Log the PDF file
							error_log("Found PDF file: $pdfFilename and baseName; $baseName and infoFile: $infoFile");

							// Check if .info file exists
							if (!file_exists($infoFile)) {
								error_log("FileInfo: $infoFile ,baseName; $baseName, pdfFilename: $pdfFilename,..."); 
								
									if($baseNameSan!=$baseName){
										error_log("we are processing this..............");
										$modName = $finalDestination.'/'.$baseNameSan.'.pdf';
										rename("$finalDestination/$pdfFilename", "$modName");
										
										if(file_exists($infoFilePreSan)){
											
											rename("$infoFilePreSan", "$infoFile");
											
											

										}
										
										$baseName = $baseNameSan;
									}
									// Attempt to create the file
									if (file_put_contents($infoFile, "") !== false) {
										// Set writable permissions (e.g., 0666 without umask interference)
										chmod($infoFile, 0666);
										// Write the base name to the .info file as the subject
										file_put_contents($infoFile, $baseName . PHP_EOL);
										error_log("Created .info file: $infoFile and set writable permissions.");
									} else {
										error_log("Failed to create .info file: $infoFile in document.php - permissions");
									}
								
							} else {
								error_log(".info file already exists: $infoFile");
							}
						}
					} else {
						error_log("No PDF files found in: $finalDestination");
					}
				} else {
					error_log("Directory does not exist: $finalDestination");
				}
			} else {
				error_log("Directory does not exist: $dbFolder");
			}
		} 

	// Include docPool directly without any table structure
	include ("docsIncludes/docPool.php");
	docPool($sourceId,$source,$kladde_id,$bilag,$fokus,$poolFile,$docFolder,$docFocus);
	exit;
}

// Check if showing a document - use docPool-style layout
if ($showDoc && $source == 'kassekladde') {
	// Add top banner with back button (like docPool)
	include("../includes/topline_settings.php");
	global $menu, $buttonColor, $buttonTxtColor;
	if (!isset($top_bund)) $top_bund = "";
	if (!isset($buttonColor)) $buttonColor = '#f1f1f1';
	if (!isset($buttonTxtColor)) $buttonTxtColor = '#000000';
	
	// Determine back URL
	$backUrl = "../finans/kassekladde.php?kladde_id=$kladde_id&id=$sourceId&fokus=$fokus";
	
	// Print header banner
	print "<table id='topBarHeader' width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" style=\"margin-bottom: 10px;\"><tbody>";
	if ($menu=='S') {
		print "<tr>";
		print "<td width='10%'><a href='$backUrl' accesskey='L'><button style='$buttonStyle; width:100%; cursor: pointer;'>".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";
		print "<td width='80%' style='$topStyle' align='center'>".findtekst('1408|Kassebilag', $sprog_id)."</td>";
		print "<td width='10%' style='$topStyle' align='center'><br></td>";
		print "</tr>";
	} else {
		print "<tr>";
		print "<td width='10%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><a href='$backUrl' accesskey='L' style='cursor: pointer;'>".findtekst('30|Tilbage', $sprog_id)."</a></font></td>";
		print "<td width='80%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'>".findtekst('1408|Kassebilag', $sprog_id)."</font></td>";
		print "<td width='10%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><br></font></td>";
		print "</tr>";
	}
	print "</tbody></table>";
	
	// Add CSS to completely disable all hover effects on top bar (no visual changes at all)
	print "<style>
		/* Disable ALL hover effects on top bar - no color, opacity, or visual changes */
		#topBarHeader tbody tr td a button,
		#topBarHeader tbody tr td a button:hover,
		#topBarHeader tbody tr td a:hover button,
		#topBarHeader tbody tr td a:focus button,
		#topBarHeader tbody tr td a:active button,
		#topBarHeader tbody tr td button,
		#topBarHeader tbody tr td button:hover,
		#topBarHeader tbody tr td button:focus,
		#topBarHeader tbody tr td button:active {
			background-color: $buttonColor !important;
			color: $buttonTxtColor !important;
			opacity: 1 !important;
			transform: none !important;
			cursor: pointer !important;
			border-color: $buttonColor !important;
		}
		/* Disable hover effects on top bar links - no color or text changes */
		#topBarHeader tbody tr td a,
		#topBarHeader tbody tr td a:hover,
		#topBarHeader tbody tr td a:focus,
		#topBarHeader tbody tr td a:active {
			text-decoration: none !important;
			color: inherit !important;
			opacity: 1 !important;
		}
		/* Disable hover effects on top bar cells - maintain exact background color */
		#topBarHeader tbody tr,
		#topBarHeader tbody tr:hover,
		#topBarHeader tbody tr td,
		#topBarHeader tbody tr td:hover {
			background-color: $buttonColor !important;
			opacity: 1 !important;
		}
		/* Pointer cursor for back button only */
		#topBarHeader tbody tr td:first-child a {
			cursor: pointer !important;
		}
		#topBarHeader tbody tr td:first-child a button {
			cursor: pointer !important;
		}
		html, body {
			margin: 0;
			padding: 0;
			height: 100%;
			overflow: hidden;
		}
		#docViewerContainer {
			display: flex;
			width: 100%;
			height: calc(100vh - 60px);
			gap: 0;
			margin: 0;
			padding: 0;
			position: fixed;
			top: 60px;
			left: 0;
			right: 0;
			bottom: 0;
			box-sizing: border-box;
		}
		#leftPanel {
			flex: 0 0 30%;
			min-width: 200px;
			max-width: 80%;
			display: flex;
			flex-direction: column;
			height: 100%;
			position: relative;
			margin: 0;
			padding: 0;
			overflow: hidden;
			box-sizing: border-box;
			border-right: 1px solid #ddd;
		}
		#leftPanelContent {
			flex: 1;
			overflow-y: auto;
			overflow-x: hidden;
			min-height: 0;
			width: 100%;
			margin: 0;
			padding: 0 0 15px 0;
			box-sizing: border-box;
			display: flex;
			flex-direction: column;
		}
		/* Style document list table to match docPool */
		#leftPanel table {
			width: 100%;
			border-collapse: collapse;
			margin: 0;
			display: table;
			table-layout: fixed;
		}
		#leftPanel table thead {
			display: table-header-group;
		}
		#leftPanel table tbody {
			display: table-row-group;
		}
		#leftPanel table thead th:first-child,
		#leftPanel table tbody td:first-child {
			width: calc(100% - 140px);
		}
		#leftPanel table thead th:last-child,
		#leftPanel table tbody td:last-child {
			width: 140px;
		}
		#leftPanel table thead tr {
			background-color: $buttonColor;
			border-bottom: 2px solid #ddd;
		}
		#leftPanel table thead th {
			padding: 8px;
			text-align: left;
			border: 1px solid #ddd;
			font-weight: bold;
			background-color: $buttonColor;
			color: $buttonTxtColor;
		}
		#leftPanel table tbody tr {
			border-bottom: 1px solid #ddd;
			transition: background-color 0.2s;
		}
		#leftPanel table tbody tr:hover {
			background-color: #e8f4f8 !important;
		}
		#leftPanel table tbody tr:hover td {
			background-color: transparent !important;
		}
		#leftPanel table tbody tr td {
			padding: 8px;
			border: 1px solid #ddd;
		}
		#leftPanel table tbody tr td a {
			text-decoration: none;
			color: inherit;
			display: inline-block;
			padding: 4px 8px;
			border-radius: 4px;
			font-size: 11px;
			margin: 0 2px;
		}
		#leftPanel table tbody tr td a[href*='deleteDoc'] {
			background-color: #dc3545;
			color: white;
		}
		#leftPanel table tbody tr td a[href*='deleteDoc']:hover {
			background-color: #c82333;
		}
		#leftPanel table tbody tr td a[href*='moveDoc'] {
			background-color: #6c757d;
			color: white;
		}
		#leftPanel table tbody tr td a[href*='moveDoc']:hover {
			background-color: #5a6268;
		}
		#rightPanel {
			flex: 1;
			min-width: 200px;
			height: 100%;
			display: flex;
			flex-direction: column;
			margin: 0;
			padding: 0;
			overflow: hidden;
			background-color: #f5f5f5;
			box-sizing: border-box;
		}
		#documentViewer {
			flex: 1;
			width: 100%;
			height: 100%;
			display: flex;
			align-items: center;
			justify-content: center;
			margin: 0;
			padding: 0;
			overflow: hidden;
			background-color: #ffffff;
		}
	</style>";
	
	// Modern flexbox layout (like docPool)
	print "<div id='docViewerContainer'>";
	print "<div id='leftPanel'>";
	print "<div id='leftPanelContent'>";
	// Show list of documents for this line
	if ($moveDoc) include("docsIncludes/moveDoc.php");
	elseif ($deleteDoc) include("docsIncludes/deleteDoc.php");
	include("docsIncludes/listDocs.php");
	// NO upload option - removed as requested
	print "</div>"; // leftPanelContent
	print "</div>"; // leftPanel
	print "<div id='rightPanel'>";
	print "<div id='documentViewer'>";
	include("docsIncludes/showDoc.php");
	print "</div>"; // documentViewer
	print "</div>"; // rightPanel
	print "</div>"; // docViewerContainer
	print "</body></html>";
	exit;
}

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
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#########
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
print "<table width=\"100%\" height=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
include("docsIncludes/showDoc.php");
// ---------- Right table end ---------
print "</tbody></table>";
print "</td></tr>";
// ---------- Main table start ---------
print "</tbody></table>";
print "</body></html>";
?>