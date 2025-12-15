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
include("../includes/topline_settings.php");
include("docsIncludes/invoiceExtractionApi.php");
if (!isset($userId) || !$userId) $userId = $bruger_id;

$fokus=$dokument = $openPool=$docFocus=$deleteDoc=$showDoc= $poolFile=$moveDoc=$kladde_id=$bilag=$source=$sourceId=$unlinkDoc=null;
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
		$unlinkDoc  = if_isset($_GET, NULL,'unlinkDoc');
		$moveDoc  	= if_isset($_GET, NULL,'moveDoc');
		$kladde_id  = if_isset($_GET, NULL,'kladde_id');
		$dokument   = if_isset($_GET, NULL,'dokument');
		$openPool    = if_isset($_GET, NULL,'openPool');
		$poolFile    = if_isset($_GET, NULL,'poolFile');
	}
	if (isset($_POST['sourceId']) || isset($_POST['source'])) {
		$sourceId  = isset($_POST['sourceId']) ? $_POST['sourceId'] : $sourceId;
		$source    = isset($_POST['source']) ? $_POST['source'] : $source;	
		$kladde_id = isset($_POST['kladde_id']) ? $_POST['kladde_id'] : $kladde_id;
		$bilag     = isset($_POST['bilag']) ? $_POST['bilag'] : $bilag;
		$fokus     = isset($_POST['fokus']) ? $_POST['fokus'] : $fokus;
		$openPool  = isset($_POST['openPool']) ? $_POST['openPool'] : $openPool;
	}
}
$params = "kladde_id=$kladde_id&bilag=$bilag&source=$source&sourceId=$sourceId&fokus=$fokus";

// Handle AJAX file uploads BEFORE any HTML output (for drag and drop)
// Check if this is an AJAX file upload request
if (isset($_FILES) && isset($_FILES['uploadedFile']['name']) && !empty($_FILES['uploadedFile']['name'])) {
	// Check if it's an AJAX request (XMLHttpRequest)
	$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
		|| (isset($_POST['openPool']) && $_POST['openPool']);
	
	// Clean any existing output buffer for AJAX requests
	if ($isAjax || $openPool) {
		while (ob_get_level()) {
			ob_end_clean();
		}
	}
	
	if ($isAjax || $openPool) {
		$allowedTypes = array('jpg','jpeg','pdf','png');
		$fileName = basename($_FILES['uploadedFile']['name']);
		
		// Get file type from MIME type
		$fileParts = explode("/",$_FILES['uploadedFile']['type']);
		$mimeType = isset($fileParts[1]) ? strtolower($fileParts[1]) : '';
		
		// Also get file extension as fallback
		$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		
		// Check if either MIME type or extension is allowed
		$isAllowedType = in_array($mimeType, $allowedTypes) || in_array($fileExt, $allowedTypes);
		
		if ($isAllowedType) {
			// Determine docFolder early
			if (file_exists('../owncloud')) $docFolder = '../owncloud';
			elseif (file_exists('../bilag')) $docFolder = '../bilag';
			elseif (file_exists('../documents')) $docFolder = '../documents';
			else $docFolder = '../bilag'; // Default fallback
			
			// Create folder if it doesn't exist
			if (!file_exists($docFolder)) {
				mkdir($docFolder, 0755, true); 
			}
			
			$poolDir = "$docFolder/$db/pulje";
			if (!is_dir($poolDir)) {
				mkdir($poolDir, 0755, true);
			}
			
			// Sanitize filename
			$baseName = pathinfo($fileName, PATHINFO_FILENAME);
			$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
			// Remove .pdf suffix from baseName if present (handles files like "document.pdf.jpg")
			$baseName = preg_replace('/\.pdf$/i', '', $baseName);
			$baseName = sanitize_filename($baseName);
			$targetFile = "$poolDir/$baseName.pdf";
			
			// Try to extract invoice data via API
			$extractedData = null;
			
			// Convert images to PDF if needed
			if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
				$tempFile = "$poolDir/$baseName.$ext";
				if (move_uploaded_file($_FILES['uploadedFile']['tmp_name'], $tempFile)) {
					// Extract data from ORIGINAL image before converting to PDF
					error_log("documents.php (AJAX): Calling extractInvoiceData for ORIGINAL image: $tempFile");
					$invoiceId = 'invoice-' . time() . '-' . rand(1000, 9999);
					$extractedData = extractInvoiceData($tempFile, $invoiceId);
					if ($extractedData) {
						error_log("documents.php (AJAX): API extraction successful, amount=" . ($extractedData['amount'] ?? 'null') . ", date=" . ($extractedData['date'] ?? 'null'));
					} else {
						error_log("documents.php (AJAX): API extraction returned null for file: $tempFile");
					}
					
					// Now convert to PDF
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
				// Extract data from PDF
				if (file_exists($targetFile)) {
					error_log("documents.php (AJAX): Calling extractInvoiceData for PDF: $targetFile");
					$invoiceId = 'invoice-' . time() . '-' . rand(1000, 9999);
					$extractedData = extractInvoiceData($targetFile, $invoiceId);
					if ($extractedData) {
						error_log("documents.php (AJAX): API extraction successful, amount=" . ($extractedData['amount'] ?? 'null') . ", date=" . ($extractedData['date'] ?? 'null'));
					} else {
						error_log("documents.php (AJAX): API extraction returned null for file: $targetFile");
					}
				}
			}
			
			// Create .info file AFTER API call
			if (file_exists($targetFile)) {
				$infoFile = "$poolDir/$baseName.info";
				
				// Prepare .info file content
				$subject = $baseName;
				$account = '';
				$amount = '';
				$date = '';
				
				// Use extracted data if available
				if ($extractedData !== null) {
					if (isset($extractedData['amount']) && !empty($extractedData['amount'])) {
						$amount = $extractedData['amount'];
					}
					if (isset($extractedData['date']) && !empty($extractedData['date'])) {
						$date = $extractedData['date'];
					}
				}
				
				// Create .info file (only if it doesn't exist)
				if (!file_exists($infoFile)) {
					$infoContent = $subject . PHP_EOL . $account . PHP_EOL . $amount . PHP_EOL . $date . PHP_EOL;
					file_put_contents($infoFile, $infoContent);
					chmod($infoFile, 0666);
				}
				
				// Return JSON response for AJAX
				header('Content-Type: application/json');
				echo json_encode([
					'success' => true,
					'message' => 'File uploaded successfully',
					'filename' => $baseName . '.pdf',
					'extracted' => $extractedData
				]);
				exit;
			} else {
				header('Content-Type: application/json');
				echo json_encode(['success' => false, 'message' => 'Failed to save file']);
				exit;
			}
		} else {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'Invalid file type']);
			exit;
		}
	}
}

// Render the header before any content
if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	
	// Determine back URL based on source
	if ($source == "kassekladde") {
		$backUrl = "../finans/kassekladde.php?kladde_id=$kladde_id&id=$sourceId&fokus=$fokus";
	} elseif ($source == "debitorOrdrer") {
		$backUrl = "../debitor/ordre.php?id=$sourceId&fokus=$fokus";
	} elseif ($source == "creditorOrder") {
		$backUrl = "../kreditor/ordre.php?id=$sourceId&fokus=$fokus";
	} else {
		$backUrl = "../debitor/historikkort.php?id=$sourceId&fokus=$fokus";
	}
	
	$headerTitle = findtekst('1408|Dokumenter', $sprog_id);
	
	print "<div id='header'>";
	print "<div class='headerbtnLft headLink'><a href='$backUrl' accesskey='L' title='".findtekst('30|Tilbage', $sprog_id)."'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst('30|Tilbage', $sprog_id) . "</a></div>";
	print "<div class='headerTxt'>$headerTitle</div>";
	print "<div class='headerbtnRght headLink'></div>";
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu == 'S') {
	// Sidebar menu - use topLineDocuments.php if it exists, otherwise create inline
	if (file_exists('docsIncludes/topLineDocuments.php')) {
		include_once './docsIncludes/topLineDocuments.php';
	} else {
		// Create inline sidebar-style header
		if ($source == "kassekladde") {
			$backUrl = "../finans/kassekladde.php?kladde_id=$kladde_id&id=$sourceId&fokus=$fokus";
		} elseif ($source == "debitorOrdrer") {
			$backUrl = "../debitor/ordre.php?id=$sourceId&fokus=$fokus";
		} elseif ($source == "creditorOrder") {
			$backUrl = "../kreditor/ordre.php?id=$sourceId&fokus=$fokus";
		} else {
			$backUrl = "../debitor/historikkort.php?id=$sourceId&fokus=$fokus";
		}
		
		print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0' style='margin-bottom: 10px;'><tbody><tr>";
		print "<td width='10%' style='$topStyle'><a href='$backUrl' accesskey='L'><button style='$buttonStyle; width:100%; cursor: pointer;'>".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";
		print "<td width='80%' style='$topStyle' align='center'>".findtekst('1408|Dokumenter', $sprog_id)."</td>";
		print "<td width='10%' style='$topStyle' align='center'></td>";
		print "</tr></tbody></table>";
	}
} else {
	// Old/classic header style
	if ($source == "kassekladde") {
		$backUrl = "../finans/kassekladde.php?kladde_id=$kladde_id&id=$sourceId&fokus=$fokus";
	} elseif ($source == "debitorOrdrer") {
		$backUrl = "../debitor/ordre.php?id=$sourceId&fokus=$fokus";
	} elseif ($source == "creditorOrder") {
		$backUrl = "../kreditor/ordre.php?id=$sourceId&fokus=$fokus";
	} else {
		$backUrl = "../debitor/historikkort.php?id=$sourceId&fokus=$fokus";
	}
	
	if (!isset($top_bund)) $top_bund = "";
	
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0' style='margin-bottom: 10px;'><tbody><tr>";
	print "<td width='10%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><a href='$backUrl' accesskey='L'>".findtekst('30|Tilbage', $sprog_id)."</a></font></td>";
	print "<td width='80%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'>".findtekst('1408|Dokumenter', $sprog_id)."</font></td>";
	print "<td width='10%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><br></font></td>";
	print "</tr></tbody></table>";
}

print "<div align=\"center\">";

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

// ---------- Left table start ---------
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
// Handle file uploads for pool view
// Allow uploads when sourceId is set OR when openPool is set (for new pool uploads)
if (isset($_FILES) && isset($_FILES['uploadedFile']['name']) && ($sourceId || $openPool)) {
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
		// Remove .pdf suffix from baseName if present (handles files like "document.pdf.jpg")
		$baseName = preg_replace('/\.pdf$/i', '', $baseName);
		$baseName = sanitize_filename($baseName);
		$targetFile = "$poolDir/$baseName.pdf";
		
		// Try to extract invoice data BEFORE converting to PDF (API works better with original images)
		$extractedData = null;
		
		// Convert images to PDF if needed
		if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
			$tempFile = "$poolDir/$baseName.$ext";
			if (move_uploaded_file($_FILES['uploadedFile']['tmp_name'], $tempFile)) {
				// Extract data from ORIGINAL image before converting to PDF
				error_log("documents.php (block2): Calling extractInvoiceData for ORIGINAL image: $tempFile");
				$invoiceId = 'invoice-' . time() . '-' . rand(1000, 9999);
				$extractedData = extractInvoiceData($tempFile, $invoiceId);
				if ($extractedData) {
					error_log("documents.php (block2): API extraction successful, amount=" . ($extractedData['amount'] ?? 'null') . ", date=" . ($extractedData['date'] ?? 'null'));
				} else {
					error_log("documents.php (block2): API extraction returned null for file: $tempFile");
				}
				
				// Now convert to PDF
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
			// Extract data from PDF
			if (file_exists($targetFile)) {
				error_log("documents.php (block2): Calling extractInvoiceData for PDF: $targetFile");
				$invoiceId = 'invoice-' . time() . '-' . rand(1000, 9999);
				$extractedData = extractInvoiceData($targetFile, $invoiceId);
				if ($extractedData) {
					error_log("documents.php (block2): API extraction successful, amount=" . ($extractedData['amount'] ?? 'null') . ", date=" . ($extractedData['date'] ?? 'null'));
				} else {
					error_log("documents.php (block2): API extraction returned null for file: $targetFile");
				}
			}
		}
		
		// Create .info file
		if (file_exists($targetFile)) {
			$infoFile = "$poolDir/$baseName.info";
			
			// Prepare .info file content
			// Format: subject (line 1), account (line 2), amount (line 3), date (line 4)
			$subject = $baseName;
			$account = '';
			$amount = '';
			$date = '';
			
			// Use extracted data if available
			if ($extractedData !== null) {
				if (isset($extractedData['amount']) && !empty($extractedData['amount'])) {
					$amount = $extractedData['amount'];
				}
				if (isset($extractedData['date']) && !empty($extractedData['date'])) {
					$date = $extractedData['date'];
				}
			}
			
			// Create or update .info file
			if (!file_exists($infoFile)) {
				$infoContent = $subject . PHP_EOL . $account . PHP_EOL . $amount . PHP_EOL . $date . PHP_EOL;
				file_put_contents($infoFile, $infoContent);
				chmod($infoFile, 0666);
			} else {
				// Update existing .info file with extracted data if available
				if ($extractedData !== null) {
					$lines = file($infoFile, FILE_IGNORE_NEW_LINES);
					// Preserve existing subject and account if they exist
					$subject = isset($lines[0]) && !empty($lines[0]) ? $lines[0] : $baseName;
					$account = isset($lines[1]) ? $lines[1] : '';
					// Update amount and date from API if extracted
					if (!empty($amount)) {
						$lines[2] = $amount;
					} elseif (!isset($lines[2])) {
						$lines[2] = '';
					}
					if (!empty($date)) {
						$lines[3] = $date;
					} elseif (!isset($lines[3])) {
						$lines[3] = '';
					}
					// Ensure we have 4 lines
					while (count($lines) < 4) {
						$lines[] = '';
					}
					$infoContent = implode(PHP_EOL, array_slice($lines, 0, 4)) . PHP_EOL;
					file_put_contents($infoFile, $infoContent);
				}
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

// Handle linking bilag from another line
$linkBilag = isset($_GET['linkBilag']) && $_GET['linkBilag'] == '1';
$doLink = isset($_GET['doLink']) && $_GET['doLink'] == '1';
$linkDocId = isset($_GET['linkDocId']) ? intval($_GET['linkDocId']) : 0;

if ($doLink && $linkDocId && $sourceId && $source == 'kassekladde') {
	// Get the original document info
	$qtxt = "SELECT * FROM documents WHERE id = '$linkDocId'";
	$origDoc = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	
	if ($origDoc) {
		// Check if this exact document is already linked to this source_id
		$checkQtxt = "SELECT id FROM documents WHERE source = 'kassekladde' AND source_id = '$sourceId' AND filename = '" . db_escape_string($origDoc['filename']) . "' AND filepath = '" . db_escape_string($origDoc['filepath']) . "'";
		$existing = db_fetch_array(db_select($checkQtxt, __FILE__ . " linje " . __LINE__));
		
		if (!$existing) {
			// Create a new document entry pointing to the same file
			$qtxt = "INSERT INTO documents (global_id, filename, filepath, source, source_id, timestamp, user_id) VALUES ";
			$qtxt .= "('" . db_escape_string($origDoc['global_id']) . "', '" . db_escape_string($origDoc['filename']) . "', '" . db_escape_string($origDoc['filepath']) . "', 'kassekladde', '$sourceId', '" . date('U') . "', '$userId')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		
		// Redirect back to the document viewer
		$redirectUrl = "documents.php?source=$source&sourceId=$sourceId&kladde_id=$kladde_id&fokus=$fokus";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$redirectUrl\">";
		exit;
	}
}

if ($linkBilag && $source == 'kassekladde') {
	// Show page to select bilag from other lines
	global $menu, $buttonColor, $buttonTxtColor, $sprog_id;
	if (!isset($buttonColor)) $buttonColor = '#114691';
	if (!isset($buttonTxtColor)) $buttonTxtColor = '#ffffff';
	
	// Get bilag from kassekladde if not set
	if (empty($bilag) && $sourceId) {
		$qtxt_bilag = "SELECT bilag FROM kassekladde WHERE id = '$sourceId'";
		$bilag_row = db_fetch_array(db_select($qtxt_bilag, __FILE__ . " linje " . __LINE__));
		if ($bilag_row) $bilag = $bilag_row['bilag'];
	}
	
	$backUrl = "documents.php?source=$source&sourceId=$sourceId&kladde_id=$kladde_id&fokus=$fokus";
	
	print "<style>
		body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
		.header { background: $buttonColor; color: $buttonTxtColor; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
		.header h2 { margin: 0; }
		.doc-list { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
		.doc-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #eee; position: relative; cursor: pointer; }
		.doc-item:hover { background: #f0f7ff; }
		.doc-info { flex: 1; }
		.doc-name { font-weight: bold; color: #333; }
		.doc-meta { font-size: 12px; color: #666; margin-top: 4px; }
		.link-btn { background: $buttonColor; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; flex-shrink: 0; }
		.link-btn:hover { opacity: 0.9; }
		.empty-msg { padding: 40px; text-align: center; color: #666; }
		.search-box { margin-bottom: 15px; }
		.search-box input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
		
		/* Preview popup styles */
		.preview-popup {
			display: none;
			position: fixed;
			z-index: 1000;
			background: white;
			border-radius: 8px;
			box-shadow: 0 4px 20px rgba(0,0,0,0.3);
			padding: 10px;
			max-width: 500px;
			max-height: 600px;
			overflow: hidden;
		}
		.preview-popup.active { display: block; }
		.preview-popup iframe, .preview-popup embed {
			width: 480px;
			height: 550px;
			border: none;
			border-radius: 4px;
		}
		.preview-popup .preview-header {
			padding: 8px;
			background: $buttonColor;
			color: white;
			border-radius: 4px 4px 0 0;
			margin: -10px -10px 10px -10px;
			font-size: 12px;
			font-weight: bold;
		}
		.preview-loading {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 480px;
			height: 550px;
			background: #f5f5f5;
			color: #666;
			font-size: 14px;
		}
	</style>";
	
	// Preview popup container
	print "<div id='previewPopup' class='preview-popup'>";
	print "<div class='preview-header' id='previewTitle'>Forhåndsvisning</div>";
	print "<div id='previewContent'><div class='preview-loading'>Indlæser...</div></div>";
	print "</div>";
	
	print "<div class='header'>";
	print "<h2><i class='fa fa-link'></i> Link bilag fra anden linje</h2>";
	print "</div>";
	
	print "<div class='search-box'>";
	print "<input type='text' id='searchBilag' placeholder='Søg efter bilag...' onkeyup='filterBilag()'>";
	print "</div>";
	
	print "<div class='doc-list' id='bilagList'>";
	
	// Get all documents from other kassekladde lines (not the current one)
	// Only show bilag from the last month
	$oneMonthAgo = time() - (30 * 24 * 60 * 60); // 30 days in seconds
	
	$qtxt = "SELECT d.id, d.filename, d.filepath, d.timestamp, d.source_id, k.bilag as bilag_nr, k.beskrivelse
			 FROM documents d 
			 LEFT JOIN kassekladde k ON d.source_id = k.id 
			 WHERE d.source = 'kassekladde' 
			 AND d.source_id != '$sourceId' 
			 AND d.timestamp >= '$oneMonthAgo'
			 AND d.id IN (
			     SELECT MAX(d2.id) FROM documents d2 
			     WHERE d2.source = 'kassekladde' 
			     AND d2.timestamp >= '$oneMonthAgo'
			     GROUP BY d2.filepath, d2.filename
			 )
			 ORDER BY d.timestamp DESC 
			 LIMIT 100";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	
	$count = 0;
	while ($r = db_fetch_array($q)) {
		$count++;
		$docId = $r['id'];
		$filename = htmlspecialchars($r['filename'], ENT_QUOTES);
		$bilagNr = $r['bilag_nr'] ? $r['bilag_nr'] : 'N/A';
		$beskrivelse = $r['beskrivelse'] ? htmlspecialchars(substr($r['beskrivelse'], 0, 50), ENT_QUOTES) : '';
		$dateStr = date('d-m-Y H:i', $r['timestamp']);
		
		// Build the file path for preview
		$filePath = $docFolder . '/' . $db . '/' . $r['filepath'] . '/' . $r['filename'];
		$filePath = str_replace('//', '/', $filePath);
		$filePathEncoded = htmlspecialchars($filePath, ENT_QUOTES);
		
		$linkUrl = "documents.php?doLink=1&linkDocId=$docId&kladde_id=" . urlencode($kladde_id) . "&bilag=" . urlencode($bilag) . "&fokus=" . urlencode($fokus) . "&sourceId=" . urlencode($sourceId) . "&source=" . urlencode($source);
		
		print "<div class='doc-item' data-search='" . strtolower($filename . ' ' . $bilagNr . ' ' . $beskrivelse) . "' data-filepath='$filePathEncoded' data-filename='$filename' onmouseenter='showPreview(this, event)' onmouseleave='hidePreview()' onmousemove='movePreview(event)'>";
		print "<div class='doc-info'>";
		print "<div class='doc-name'><i class='fa fa-file-pdf-o'></i> $filename</div>";
		print "<div class='doc-meta'>Bilag #$bilagNr | $beskrivelse | $dateStr</div>";
		print "</div>";
		print "<a href='$linkUrl' class='link-btn' onclick=\"return confirm('Link dette bilag til den aktuelle linje?')\"><i class='fa fa-link'></i> Link</a>";
		print "</div>";
	}
	
	if ($count == 0) {
		print "<div class='empty-msg'>Ingen bilag fundet fra andre linjer</div>";
	}
	
	print "</div>";
	
	print "<script>
	var previewTimeout = null;
	var currentPreviewPath = null;
	
	function filterBilag() {
		var input = document.getElementById('searchBilag').value.toLowerCase();
		var items = document.querySelectorAll('.doc-item');
		items.forEach(function(item) {
			var searchText = item.getAttribute('data-search');
			if (searchText.indexOf(input) > -1) {
				item.style.display = 'flex';
			} else {
				item.style.display = 'none';
			}
		});
	}
	
	function showPreview(element, event) {
		var filepath = element.getAttribute('data-filepath');
		var filename = element.getAttribute('data-filename');
		
		if (!filepath) return;
		
		// Clear any existing timeout
		if (previewTimeout) clearTimeout(previewTimeout);
		
		// Delay showing preview slightly to avoid flickering
		previewTimeout = setTimeout(function() {
			var popup = document.getElementById('previewPopup');
			var content = document.getElementById('previewContent');
			var title = document.getElementById('previewTitle');
			
			// Only reload if different file
			if (currentPreviewPath !== filepath) {
				currentPreviewPath = filepath;
				title.textContent = filename;
				
				// Check file extension
				var ext = filepath.split('.').pop().toLowerCase();
				
				if (ext === 'pdf') {
					content.innerHTML = '<embed src=\"' + filepath + '\" type=\"application/pdf\" style=\"width:480px;height:550px;\">';
				} else if (['jpg', 'jpeg', 'png', 'gif'].indexOf(ext) !== -1) {
					content.innerHTML = '<img src=\"' + filepath + '\" style=\"max-width:480px;max-height:550px;display:block;margin:0 auto;\">';
				} else {
					content.innerHTML = '<div class=\"preview-loading\">Forhåndsvisning ikke tilgængelig</div>';
				}
			}
			
			popup.classList.add('active');
			movePreview(event);
		}, 300);
	}
	
	function hidePreview() {
		if (previewTimeout) {
			clearTimeout(previewTimeout);
			previewTimeout = null;
		}
		var popup = document.getElementById('previewPopup');
		popup.classList.remove('active');
	}
	
	function movePreview(event) {
		var popup = document.getElementById('previewPopup');
		if (!popup.classList.contains('active')) return;
		
		var x = event.clientX + 20;
		var y = event.clientY - 100;
		
		// Keep within viewport
		var rect = popup.getBoundingClientRect();
		var viewportWidth = window.innerWidth;
		var viewportHeight = window.innerHeight;
		
		// If would go off right edge, show on left side of cursor
		if (x + 520 > viewportWidth) {
			x = event.clientX - 520;
		}
		
		// If would go off bottom, adjust y
		if (y + 620 > viewportHeight) {
			y = viewportHeight - 630;
		}
		
		// Don't go above viewport
		if (y < 10) y = 10;
		
		popup.style.left = x + 'px';
		popup.style.top = y + 'px';
	}
	</script>";
	
	print "</body></html>";
	exit;
}

// Check if showing a document FIRST - this takes priority over openPool
// For kassekladde: if sourceId is set and has documents, show document viewer (not pool)
// UNLESS openPool is explicitly requested (user wants to add more bilag)
$openPoolRequested = (isset($_GET['openPool']) && $_GET['openPool'] == '1') || $openPool;
if ($source == 'kassekladde' && $sourceId && !$openPoolRequested) {
	// Check if there are any documents for this sourceId
	$qtxt = "select id,filename,filepath from documents where source = 'kassekladde' and source_id = '$sourceId' order by id limit 1";
	$docRow = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	
	// If documents exist OR showDoc is set, show document viewer
	if ($docRow || $showDoc) {
		// If showDoc is not set but we have a document, set it to the first document
		if (!$showDoc && $docRow) {
			// Construct the path properly (remove leading slash from filepath if present)
			$filepath = ltrim($docRow['filepath'], '/');
			$showDoc = rtrim($docFolder, '/') . '/' . $db . '/' . $filepath . '/' . $docRow['filename'];
			$showDoc = str_replace('//', '/', $showDoc); // Remove any double slashes
		}
		
		// Show document viewer with list of attachments
		// Add top banner with back button (like docPool)

	global $menu, $buttonColor, $buttonTxtColor;
	if (!isset($top_bund)) $top_bund = "";
	if (!isset($buttonColor)) $buttonColor = '#f1f1f1';
	if (!isset($buttonTxtColor)) $buttonTxtColor = '#000000';
	
	// Determine back URL
	$backUrl = "../finans/kassekladde.php?kladde_id=$kladde_id&id=$sourceId&fokus=$fokus";
	
	// Print header banner
	print "<table id='topBarHeader' width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" style=\"margin-bottom: 10px; margin-top: 10px;\"><tbody>";
	if ($menu=='S') {
		print "<tr>";
		print "<td width='10%' style='border-radius: 5px;'><a href='$backUrl' accesskey='L'><button style='$buttonStyle; width:100%; cursor: pointer;'>".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";
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
		#leftPanel table tbody tr:hover td {
			
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
	elseif ($unlinkDoc) {
		// Only delete the database reference, not the actual file
		$unlinkDocId = intval($unlinkDoc);
		if ($unlinkDocId) {
			$qtxt = "DELETE FROM documents WHERE id = '$unlinkDocId' AND source = '$source' AND source_id = '$sourceId'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		// Check if there are any remaining documents
		$qtxt = "SELECT id FROM documents WHERE source = '$source' AND source_id = '$sourceId' LIMIT 1";
		$remainingDoc = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		
		if ($remainingDoc) {
			// Still have documents, go back to document viewer
			$redirectUrl = "documents.php?source=$source&sourceId=$sourceId&kladde_id=$kladde_id&fokus=$fokus";
		} else {
			// No more documents, go to document pool
			$redirectUrl = "documents.php?openPool=1&source=$source&sourceId=$sourceId&kladde_id=$kladde_id&bilag=$bilag&fokus=$fokus";
		}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$redirectUrl\">";
		exit;
	}
	include("docsIncludes/listDocs.php");
	
	// Add link to docpool to add more bilag
	// Get bilag number from kassekladde if not set
	if (empty($bilag) && $sourceId) {
		$qtxt_bilag = "SELECT bilag FROM kassekladde WHERE id = '$sourceId'";
		$bilag_row = db_fetch_array(db_select($qtxt_bilag, __FILE__ . " linje " . __LINE__));
		if ($bilag_row) $bilag = $bilag_row['bilag'];
	}
	$poolUrl = "documents.php?openPool=1&kladde_id=" . urlencode($kladde_id) . "&bilag=" . urlencode($bilag) . "&fokus=" . urlencode($fokus) . "&sourceId=" . urlencode($sourceId) . "&source=" . urlencode($source);
	$linkUrl = "documents.php?linkBilag=1&kladde_id=" . urlencode($kladde_id) . "&bilag=" . urlencode($bilag) . "&fokus=" . urlencode($fokus) . "&sourceId=" . urlencode($sourceId) . "&source=" . urlencode($source);
	print "<div style='margin-top: 15px; padding: 10px; text-align: center; display: flex; flex-direction: column; gap: 10px;'>";
	print "<a href='$poolUrl' style='display: inline-block; padding: 10px 20px; background-color: $buttonColor; color: $buttonTxtColor; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1);' onmouseover='this.style.opacity=\"0.9\"' onmouseout='this.style.opacity=\"1\"'>";
	print "<i class='fa fa-plus'></i> " . findtekst('2592|Dokumentpulje', $sprog_id);
	print "</a>";
	print "<a href='$linkUrl' style='display: inline-block; padding: 10px 20px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1);' onmouseover='this.style.opacity=\"0.9\"' onmouseout='this.style.opacity=\"1\"'>";
	print "<i class='fa fa-link'></i> Link bilag fra anden linje";
	print "</a>";
	print "</div>";
	
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
	} // End if ($docRow || $showDoc)
} // End if ($source == 'kassekladde' && $sourceId)

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
									// Remove .pdf suffix from base if present (handles files like "document.pdf.jpg")
									$base = preg_replace('/\.pdf$/i', '', $base);
									$base = sanitize_filename($base);
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
	// if folder bilag/$db/pulje dosent exist, create it
	if (!is_dir($docFolder."/$db/pulje")) {
		mkdir($docFolder."/$db/pulje", 0755, true);
	}
	include ("docsIncludes/docPool.php");
	docPool($sourceId,$source,$kladde_id,$bilag,$fokus,$poolFile,$docFolder,$docFocus);
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
elseif ($unlinkDoc) {
	// Only delete the database reference, not the actual file
	$unlinkDocId = intval($unlinkDoc);
	if ($unlinkDocId) {
		$qtxt = "DELETE FROM documents WHERE id = '$unlinkDocId' AND source = '$source' AND source_id = '$sourceId'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	// Check if there are any remaining documents
	$qtxt = "SELECT id FROM documents WHERE source = '$source' AND source_id = '$sourceId' LIMIT 1";
	$remainingDoc = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	
	if ($remainingDoc) {
		// Still have documents, go back to document viewer
		$redirectUrl = "documents.php?source=$source&sourceId=$sourceId&kladde_id=$kladde_id&fokus=$fokus";
	} else {
		// No more documents, go to document pool
		$redirectUrl = "documents.php?openPool=1&source=$source&sourceId=$sourceId&kladde_id=$kladde_id&bilag=$bilag&fokus=$fokus";
	}
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$redirectUrl\">";
	exit;
}
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

// Close content div if using modern menu
if ($menu == 'T') {
	print "</div>"; // Close content-noside div
}

print "</body></html>";
?>