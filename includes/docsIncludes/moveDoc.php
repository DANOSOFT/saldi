<?php
// --- includes/docsIncludes/moveDoc.php -----patch 4.1.0 ----2024-03-05---
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
// Copyright (c) 2024-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20230707 LOE Added kassekladde part
// 20230724 LOE made some modifications to include alert also
// 20240305 PHR Varioous corrections


if ($moveDoc) {
	// Decode the URL-encoded path
	$moveDoc = urldecode($moveDoc);
	
	// Normalize the path - remove double slashes
	$moveDoc = str_replace('//', '/', $moveDoc);
	$moveDoc = preg_replace('#/+#', '/', $moveDoc); // Remove multiple slashes
	$tmpA = explode("/",$moveDoc);

	$x = count($tmpA)-1;
	$h = count($tmpA)-3;

	$bilag_id = $tmpA[$h];
	$fileName = $tmpA[$x];
	
	// Security check: Block if locked and older than 24h
	$locked = 0;
	if ($source == 'creditor') {
		$qtxt = "select status from ordrer where id = '$sourceId'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
		// Handle potential schema ambiguity (art vs status)
		$statusVal = isset($r['art']) ? $r['art'] : (isset($r['status']) ? $r['status'] : 0);
		($statusVal >= '3')?$locked='1':$locked='0'; 
	} elseif ($source == 'kassekladde') {
		if ($kladde_id) {
			$qtxt = "select bogfort from kladdeliste where id = '$kladde_id'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			($r['bogfort'] == 'V')?$locked='1':$locked='0';
		}
	}
	
	$qtxt = "select timestamp from documents where source = '$source' and source_id = '$sourceId' and filename = '".db_escape_string($fileName)."'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$docTimestamp = $r ? $r['timestamp'] : 0;
	
	if ($locked == 1 && (date('U') - $docTimestamp > 86400)) {
		echo '<script type="text/javascript">';
		echo "alert('Handling afvist: Linjen er bogført/låst og bilaget er ældre end 24 timer.');";
		echo "window.history.back();";
		echo '</script>';
		exit;
	}

	$new = '';

	for ($i=0;$i<count($tmpA)-4;$i++) {
		if ($tmpA[$i]) $new.= $tmpA[$i]."/";
	}
	$new.= "pulje";
	if (!file_exists($new)) mkdir($new, 0777);
	$new.= "/$tmpA[$x]";
	$new = str_replace(' ','',$new);
	
	// Move the actual file to pulje
	if (file_exists($moveDoc) && !file_exists($new)) {
		$moveResult = rename($moveDoc, $new);
		if (!$moveResult) {
			echo '<script type="text/javascript">';
			echo "alert('Kunne ikke flytte filen til pulje.');";
			echo "window.history.back();";
			echo '</script>';
			exit;
		}
	} elseif (file_exists($new)) {
		// File already exists in pulje
		echo '<script type="text/javascript">';
		echo "alert('Filen findes allerede i pulje.');";
		echo "window.history.back();";
		echo '</script>';
		exit;
	}
	
	// Insert into pool_files database table
	$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'pool_files'";
	if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$baseName = pathinfo($fileName, PATHINFO_FILENAME);
		$fileDate = date("Y-m-d H:i:s");
		
		// Check if entry already exists
		$qtxt = "SELECT id FROM pool_files WHERE filename = '". db_escape_string($fileName) ."'";
		if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$qtxt = "INSERT INTO pool_files (filename, subject, account, amount, file_date, invoice_number, description) VALUES (
				'". db_escape_string($fileName) ."',
				'". db_escape_string($baseName) ."',
				'',
				'',
				'". db_escape_string($fileDate) ."',
				'',
				''
			)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}
	
	// Call the extraction API to get invoice data
	$extractionSuccess = false;
	$puljePath = dirname($new);
	$extractFilePath = $new;
	
	if (file_exists($extractFilePath) && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf') {
		// Include the extraction API
		include_once("docsIncludes/invoiceExtractionApi.php");
		
		if (function_exists('extractInvoiceData')) {
			$invoiceId = 'move-' . pathinfo($fileName, PATHINFO_FILENAME) . '-' . time();
			$extractResult = extractInvoiceData($extractFilePath, $invoiceId);
			
			if ($extractResult !== null) {
				// Update pool_files with extracted data
				$updateFields = [];
				if (!empty($extractResult['amount'])) {
					$updateFields[] = "amount = '". db_escape_string($extractResult['amount']) ."'";
				}
				if (!empty($extractResult['date'])) {
					// Normalize date format (handles Danish months like "17.oktober.2025")
					$dateStr = $extractResult['date'];
					$danishMonths = [
						'januar' => '01', 'jan' => '01',
						'februar' => '02', 'feb' => '02',
						'marts' => '03', 'mar' => '03',
						'april' => '04', 'apr' => '04',
						'maj' => '05',
						'juni' => '06', 'jun' => '06',
						'juli' => '07', 'jul' => '07',
						'august' => '08', 'aug' => '08',
						'september' => '09', 'sep' => '09', 'sept' => '09',
						'oktober' => '10', 'okt' => '10', 'oct' => '10',
						'november' => '11', 'nov' => '11',
						'december' => '12', 'dec' => '12'
					];
					$lowerDate = strtolower($dateStr);
					$foundMonth = false;
					foreach ($danishMonths as $monthName => $monthNum) {
						if (stripos($lowerDate, $monthName) !== false) {
							if (preg_match('/(\d{1,2})[.\s\-]+' . preg_quote($monthName, '/') . '[.\s\-]+(\d{4}|\d{2})/i', $dateStr, $matches)) {
								$day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
								$year = $matches[2];
								if (strlen($year) == 2) {
									$year = ($year > 50 ? '19' : '20') . $year;
								}
								$dateStr = $year . '-' . $monthNum . '-' . $day;
								$foundMonth = true;
								break;
							}
						}
					}
					if (!$foundMonth) {
						// Try dd.mm.yyyy format
						if (preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $dateStr, $matches)) {
							$dateStr = $matches[3] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
						} else {
							$timestamp = strtotime($dateStr);
							if ($timestamp !== false && $timestamp > 0) {
								$dateStr = date('Y-m-d', $timestamp);
							}
						}
					}
					$updateFields[] = "file_date = '". db_escape_string($dateStr) ."'";
				}
				if (!empty($extractResult['vendor'])) {
					$updateFields[] = "subject = '". db_escape_string($extractResult['vendor']) ."'";
				}
				if (!empty($extractResult['invoiceNumber'])) {
					$updateFields[] = "invoice_number = '". db_escape_string($extractResult['invoiceNumber']) ."'";
				}
				if (!empty($extractResult['description'])) {
					$updateFields[] = "description = '". db_escape_string($extractResult['description']) ."'";
				}
				
				if (!empty($updateFields)) {
					$qtxt = "UPDATE pool_files SET ". implode(", ", $updateFields) .", updated = CURRENT_TIMESTAMP WHERE filename = '". db_escape_string($fileName) ."'";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					$extractionSuccess = true;
				}
			}
		}
	}
	
	// Delete from documents database
	$qtxt = "delete from documents where source = '$source' and source_id = '$sourceId' ";
	$qtxt.= "and filename = '".db_escape_string($fileName)."'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	
	// Check if there are any remaining documents for this sourceId
	$qtxt = "select id,filename,filepath from documents where source = '$source' and source_id = '$sourceId' order by id limit 1";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	
	if ($q && ($r = db_fetch_array($q))) {
		// There's another document, redirect to show it
		$nextDoc = "$docFolder/$db/$r[filepath]/$r[filename]";
		$redirectUrl = "documents.php?$params&showDoc=".urlencode($nextDoc);
	} else {
		// No more documents, redirect to docPool to choose a new bilag
		if ($source == 'kassekladde' && isset($kladde_id) && isset($fokus)) {
			// Ensure we redirect to docPool view (openPool=1) with all necessary parameters
			$poolParams = "openPool=1".
				"&kladde_id=".urlencode($kladde_id).
				"&bilag=".urlencode($bilag).
				"&fokus=".urlencode($fokus).
				"&poolFile=".urlencode($poolFile).
				"&docFolder=".urlencode($docFolder).
				"&sourceId=".urlencode($sourceId).
				"&source=".urlencode($source);
			$redirectUrl = "documents.php?$poolParams";
		} else {
			// Fallback: redirect to documents list without showDoc
			$redirectUrl = "documents.php?$params";
		}
	}
	
	echo '<script type="text/javascript">';
	echo "alert('$fileName successfully moved to pool!');";
	echo "window.location.href = '$redirectUrl';";
	echo '</script>';
	exit;
}
?>
