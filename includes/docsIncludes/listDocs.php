<?php
// --- includes/docsIncludes/listDocs.php-----patch 4.1.1 ----2025-08-23--------
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
// 20220510 PHR Not attatchments from not invoiced orders can now be deleted. 
// 20230705 LOE Made some modifications 20230724+20230801
// 20240117 LOE Minor modification
// 20240305 PHR Varioous corrections
// 20240323 PHR Minor design changes
// 20250815 LOE Further improvements

$fileName = NULL;
isset($_GET['bilag_id'])? $bilag_id = $_GET['bilag_id']: $bilag_id = null;

// Get global variables for styling
global $bgcolor, $bgcolor5, $sprog_id;
if (!isset($bgcolor)) $bgcolor = '#ffffff';
if (!isset($bgcolor5)) $bgcolor5 = '#f9f9f9';

// Ensure Font Awesome is loaded for icons
print "<link href='https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' rel='stylesheet'>\n";

// Check if we're in the new flexbox layout (docPool-style)
$inFlexboxLayout = (isset($showDoc) && isset($source) && $source == 'kassekladde');

if ($inFlexboxLayout) {
	// Get button color for styling
	global $buttonColor;
	if (!isset($buttonColor)) $buttonColor = '#114691';
	$hoverColor = brightenColor($buttonColor, 0.7);
	
	// Add CSS for hover effects
	print "<style>
		.doc-list-row:hover { background-color: $hoverColor !important; }
		.doc-list-row.current-doc { background-color: " . brightenColor($buttonColor, 0.5) . " !important; }
		.doc-list-row.current-doc:hover { background-color: " . brightenColor($buttonColor, 0.4) . " !important; }
	</style>";
	
	// Output table structure like docPool
	print "<table width='100%' border='0' cellspacing='0' cellpadding='0' style='border-collapse: collapse;'>";
	print "<thead>";
	print "<tr style='background-color: $buttonColor; color: white; border-bottom: 2px solid #ddd;'>";
	print "<th style='padding: 8px; text-align: left; border: 1px solid #ddd; font-weight: bold;'>".findtekst('671|Bilag', $sprog_id)."</th>";
	print "<th style='padding: 8px; text-align: center; border: 1px solid #ddd; font-weight: bold; width: 140px;'>Handlinger</th>";
	print "</tr>";
	print "</thead>";
	print "<tbody>";
} else {
	print "<tr><td valign='top' align = 'center'>";
}

/*
if ($dokument) {
	echo "$docFolder/$db/bilag/kladde_$kladde_id/bilag_$bilag_id<br>";
	if (file_exists("$docFolder/$db/bilag/kladde_$kladde_id/bilag_$bilag_id")) {
		if (!file_exists("$docFolder/$db/bilag/kladde_$kladde_id/_$bilag_id")) {
			echo "mkdir (\"$docFolder/finance/$kladde_id/$bilag_id\",0777)<br>";
		}
	} else echo "Ikke fundet";
	
}
*/
if (!isset($sourceId) || $sourceId === '') {
		error_log("no files to list in listDocs.php");
		exit;
}

$qtxt = "select id,filename,filepath from documents where source = '$source' and source_id = '$sourceId' order by id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
if($q !== false){
	$rowIndex = 0;
	while ($r=db_fetch_array($q)) {
		$rowIndex++;
		$docId = $r['id'];
		
		// Check if this is a linked document (same file used by another line)
		$linkCheckQtxt = "SELECT COUNT(*) as cnt FROM documents WHERE filepath = '" . db_escape_string($r['filepath']) . "' AND filename = '" . db_escape_string($r['filename']) . "'";
		$linkCheckRow = db_fetch_array(db_select($linkCheckQtxt, __FILE__ . " linje " . __LINE__));
		$isLinkedDoc = ($linkCheckRow && $linkCheckRow['cnt'] > 1);
		// Normalize path to avoid double slashes
		$filepath = ltrim($r['filepath'], '/'); // Remove leading slash if present
		$href = rtrim($docFolder, '/') . '/' . $db . '/' . $filepath . '/' . $r['filename'];
		$href = str_replace('//', '/', $href); // Remove any double slashes
		if (!$showDoc) {
			$fileName = $r['filename'];
			if ($fileName != trim($fileName)) {
				$newName = trim($fileName);
				rename($showDoc,$newName);
				$qtxt = "UPDATE documents set filename = '$newName' where id = '$docId'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$fileName = $$newName; 
			}
			#else echo "Kan ikke finde den<br>";
#			$check = $r['filepath']."/$fileName";
#			if(isset($fileName) && !file_exists($check)){ //File path of email docs are different
#				$showDoc= substr($check, 3);
#			}else{
			$showDoc  = "$docFolder/$db/$r[filepath]/$fileName"; // 20230705
			if (strtolower(substr($showDoc,-4,0)) !='.') {
				if (strtolower(substr($showDoc,-3)) == 'pdf') {
					$newName = str_replace('pdf ','.pdf',$showDoc);
					rename($showDoc,$newName);
					if (file_exists($newName)) $showDoc = $newName;
				} else {
					$fileType = strtolower(file_get_contents($showDoc, FALSE, NULL, 0, 4));
					if ($fileType == '%pdf') {
						$newName = $showDoc.'.pdf';
						rename($showDoc,$newName);
						if (file_exists($newName)) {
							$showDoc = $newName;
							$newName = $fileName.'.pdf';
							$qtxt = "UPDATE documents set filename = '$newName' where id = '$docId'";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}
					}
				}
			}			
		} else {
			$tmpA = explode("/",$showDoc);
			$x = count($tmpA)-1;
			$fileName = $tmpA[$x];
		}
		$showName = strtolower($r['filename']);
		if (strlen($showName) > 36) $showName = substr($showName,0,33).'...';
		
		// Check if this is the currently shown document
		$currentShowDoc = isset($_GET['showDoc']) ? $_GET['showDoc'] : (isset($showDoc) ? $showDoc : '');
		$isCurrentDoc = ($currentShowDoc && (strpos($currentShowDoc, $r['filename']) !== false || $href == $currentShowDoc));
		
		// Use different colors for current vs other documents
		$highlightColor = brightenColor($buttonColor, 0.5);
		$normalColor = $bgcolor5;
		$rowBgColor = $isCurrentDoc ? $highlightColor : $normalColor;
		$fontWeight = $isCurrentDoc ? 'bold' : 'normal';
		$borderLeft = $isCurrentDoc ? "4px solid $buttonColor" : "1px solid #ddd";
		
		if ($inFlexboxLayout) {
			// Table row format like docPool
			$docHref = "documents.php?$params&showDoc=".urlencode("$href");
			$rowClass = "doc-list-row" . ($isCurrentDoc ? " current-doc" : "");
			
			// For linked documents, show a link icon indicator
			$linkIndicator = $isLinkedDoc ? "<i class='fa fa-link' style='margin-right: 4px;'></i>" : "";
			$docTitle = $isLinkedDoc ? "Linket bilag: " . htmlspecialchars($r['filename'], ENT_QUOTES) : htmlspecialchars($r['filename'], ENT_QUOTES);
			
			print "<tr class='$rowClass' style='background: $rowBgColor; border-bottom: 1px solid #ddd; cursor: pointer;" . ($isCurrentDoc ? " border-left: $borderLeft;" : "") . "' onclick=\"window.location.href='$docHref'\">";
			print "<td style='padding: 8px; border: 1px solid #ddd; font-weight: $fontWeight;" . ($isCurrentDoc ? " border-left: none;" : "") . "' title='$docTitle'>" . ($isCurrentDoc ? "<i class='fa fa-caret-right' style='margin-right: 4px;'></i>" : "") . $linkIndicator . htmlspecialchars($showName, ENT_QUOTES)."</td>";
			print "<td style='padding: 4px; border: 1px solid #ddd; text-align: center;' onclick='event.stopPropagation();'>";
			
			// Delete button - for linked docs, only removes database reference
			if ($isLinkedDoc) {
				$deleteConfirmMsg = "Fjern link til " . htmlspecialchars($r['filename'], ENT_QUOTES) . "? (Filen bevares)";
				print "<a href='documents.php?$params&unlinkDoc=$docId' onclick=\"event.stopPropagation(); return confirm('$deleteConfirmMsg');\" style='margin: 0 4px; padding: 4px 8px; background-color: #fd7e14; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; text-decoration: none; display: inline-block;' title='Fjerner kun linket, ikke filen'>Fjern link</a>";
			} else {
				print "<a href='documents.php?$params&deleteDoc=".urlencode("$href")."' onclick=\"event.stopPropagation(); return confirm('Slet ".htmlspecialchars($r['filename'], ENT_QUOTES)."?');\" style='margin: 0 4px; padding: 4px 8px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; text-decoration: none; display: inline-block;'>Slet</a>";
			}
			
			// Move to pool button - disabled for linked docs
			if ($isLinkedDoc) {
				print "<span style='margin: 0 4px; padding: 4px 8px; background-color: #ccc; color: #666; border: none; border-radius: 4px; font-size: 11px; display: inline-block; cursor: not-allowed;' title='Kan ikke flytte linket bilag'>Flyt til pulje</span>";
			} else {
				print "<a href='documents.php?$params&moveDoc=".urlencode("$href")."' onclick=\"event.stopPropagation(); return confirm('Flyt ".htmlspecialchars($r['filename'], ENT_QUOTES)." til pulje?');\" style='margin: 0 4px; padding: 4px 8px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; text-decoration: none; display: inline-block;'>Flyt til pulje</a>";
			}
			
			print "</td>";
			print "</tr>";
		} else {
			// Original button format
			print "<tr><td valign='top' align = 'center'>";
			if($source == 'kassekladde'){ //20230705
				print "<a href = 'documents.php?$params&showDoc=".urlencode("$href")."'>";
			}else{
				print "<a href = 'documents.php?$params&showDoc=".urlencode("$href")."'>";
			}
			print "<button style = 'width:90%;height:35px;'>". $showName ."</button></a></td></tr>";
		}
	}
}

if ($inFlexboxLayout) {
	print "</tbody></table>";
} else {
	print "<tr><td valign='top' align = 'center'><hr width = '90%'></td></tr>";
}
$locked = 0;
if ($source == 'creditor') {
	$qtxt = "select status from ordrer where id = '$sourceId'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
	($r['art'] >= '3')?$locked='1':$locked='0'; 
} elseif ($source == 'kassekladde') {
	if ($kladde_id) {
		$qtxt = "select bogfort from kladdeliste where id = '$kladde_id'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		($r['bogfort'] == 'V')?$locked='1':$locked='0';
	}
}
if ($sourceId || $sourceId == 0) {
	if (!isset($sourceId) || $sourceId === '') {
		alert("no files to show");
		exit;
	}
	$qtxt = "select art from documents where source = '$source' and source_id = '$sourceId'";
	$qtxt.= "and filename = '".db_escape_string($fileName)."'";
	$qtxt = "select id, timestamp, filepath from documents where source = '$source' and source_id = '$sourceId'";
	$qtxt.= " and filename = '".db_escape_string($fileName)."'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$currentDocId = $r['id'];
		if ($locked == 0 || date('U') - $r['timestamp'] < 60*60*24) {
			if (!$inFlexboxLayout) {
				// Check if this document is linked to other lines (for old layout)
				$docFilepath = isset($r['filepath']) ? $r['filepath'] : '';
				$linkCheckQtxt = "SELECT COUNT(*) as cnt FROM documents WHERE filepath = '" . db_escape_string($docFilepath) . "' AND filename = '" . db_escape_string($fileName) . "'";
				$linkCheckRow = db_fetch_array(db_select($linkCheckQtxt, __FILE__ . " linje " . __LINE__));
				$isLinkedDocOld = ($linkCheckRow && $linkCheckRow['cnt'] > 1);
				
				// Original button format for old layout
				print "<tr><td valign='top' align = 'center'>";
				if ($isLinkedDocOld) {
					// Document is linked to other lines - show "Fjern link" instead of "Slet"
					print "<a href = 'documents.php?$params&unlinkDoc=" . intval($currentDocId) . "' onclick=\"return confirm('Fjern link til $fileName? (Filen bevares)')\">";
					print "<button style = 'width:90%;height:35px; background-color: #fd7e14; color: white;'>Fjern link</button></a>";
				} else {
					// Document is not linked - show normal delete button
					print "<a href = 'documents.php?$params&deleteDoc=".urlencode($showDoc)."' onclick=\"return confirm('Slet $fileName?')\">";
					print "<button style = 'width:90%;height:35px;'>Slet dokument</button></a>";
				}
				print "</td></tr>";
				print "<tr><td valign='top' align = 'center'>";
				if ($isLinkedDocOld) {
					// Document is linked - disable move to pool
					print "<button style = 'width:90%;height:35px; background-color: #ccc; color: #666; cursor: not-allowed;' disabled title='Kan ikke flytte linket bilag'>Flyt dokument til pulje</button>";
				} else {
					// Document is not linked - show normal move button
					print "<a href = 'documents.php?$params&moveDoc=".urlencode($showDoc)."' onclick=\"return confirm('Flyt $fileName til pulje?')\">";
					print "<button style = 'width:90%;height:35px;'>Flyt dokument til pulje</button></a>";
				}
				print "</td></tr>";
			}
			// In flexbox layout, delete/move buttons are already in the table row above
		}
	}
}

if (!$inFlexboxLayout) {
	// Close the original table structure
}
?>