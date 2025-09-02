<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/docPool.php --- ver 4.1.1 --- 2025-08-24 --- 
// LICENSE
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
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20250510 PHR Added 'w' to $legalChars
// 20250519 PHR '&' replaced by '_' in filenames 
// 20250823 LOE Applied if_isset properly to prevent excessive error logging plus other improvements
// 20250824 LOE _docPoolData.php added to this file for improved data handling, also checks that file is .pdf before setting default. Update .info subject 
// 20250827 LOE fixed error of rm: cannot remove '*': No such file or directory  cp: cannot stat '../..error. Also User can now add subject and amount to shown poolfiles

function docPool($sourceId,$source,$kladde_id,$bilag,$fokus,$poolFile,$docFolder,$docFocus){
	global $bruger_id,$db,$exec_path;
	global $params,$regnaar,$sprog_id,$userId;
	
	$afd =  $beskrivelse = $debet = $dato = $fakturanr = $kredit = $projekt = $readOnly = $sag = $sum = NULL;

	(isset($_POST['unlink']) && $_POST['unlink'])?$unlink=1:$unlink=0;
	(isset($_POST['rename']) && $_POST['rename'])?$rename=1:$rename=0;
	(isset($_POST['unlinkFile']) && $_POST['unlinkFile'])?$unlinkFile=$_POST['unlinkFile']:$unlinkFile=NULL;
	
	$insertFile   = if_isset($_POST,NULL,'insertFile');
	$newFileName  = if_isset($_POST,NULL,'newFileName');
	$descFile     = if_isset($_POST,NULL,'descFile');
	$newSubject   = if_isset($_POST,NULL,'newSubject');
	$newAccount	= if_isset($_POST,NULL,'newAccount');
	$newAmount	= if_isset($_POST,NULL,'newAmount');

	$afd         = if_isset($_POST,NULL,'afd');
	$bilag       = if_isset($_POST,NULL,'bilag');
	$beskrivelse = if_isset($_POST,NULL,'beskrivelse');
	$dato        = if_isset($_POST,NULL,'dato');
	$debet       = if_isset($_POST,NULL,'debet');
	$fakturanr   = if_isset($_POST,NULL,'fakturanr');
	$kredit      = if_isset($_POST,NULL,'kredit');
	$projekt     = if_isset($_POST,NULL,'projekt');
	$sag         = if_isset($_POST,NULL,'sag');
	$sum         = if_isset($_POST,NULL,'sum');

	if ($insertFile && $poolFile) {
		include ("docsIncludes/insertDoc.php");
#		Echo "Indsltter $poolFile<br>";
		exit;
	}
	if ($sourceId) {
		$qtxt = "select * from kassekladde where id = '$sourceId'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			if (!$afd)         $afd         = $r['afd']; 
			if (!$bilag)       $bilag       = $r['bilag']; 
			if (!$beskrivelse) $beskrivelse = $r['beskrivelse']; 
			if (!$dato)        $dato        = dkdato($r['transdate']);
			if (!$debet)       $debet       = $r['debet']; 
			if (!$fakturanr)   $fakturanr   = $r['faktura']; 
			if (!$kredit)      $kredit      = $r['kredit']; 
			if (!$projekt)     $projekt     = if_isset($r,NULL,'projekt'); 
			if (!$sag)         $sag         = if_isset($r,NULL,'sag'); 
			if (!$sum)         $sum         = dkdecimal(if_isset($r,0,'amount')); 
		}
	}

	if ($rename && $newFileName && $newFileName != $poolFile || ($rename &&($newAccount||$newAmount||$newSubject))) {
	error_log("RenammmmmmmiiiiiiingggggMMMMMMMMMM7777: $rename, and $newAccount, $newAmount, $newSubject");	
	  $legalChars = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
		array_push($legalChars,'0','1','2','3','4','5','6','7','8','9','_','-','.','(',')');
		$nfn = trim($newFileName);
		$nfn = str_replace('æ','ae',$nfn);
		$nfn = str_replace('Æ','AE',$nfn);
		$nfn = str_replace('ø','oe',$nfn);
		$nfn = str_replace('Ø','OE',$nfn);
		$nfn = str_replace('å','aa',$nfn);
		$nfn = str_replace('Å','AA',$nfn);
		$newFileName = '';
		for ($x=0;$x<strlen($nfn);$x++) {

			$c1=substr($nfn,$x,1);
			$c2=strtolower($c1);
			if (!in_array($c2,$legalChars)) $c1 = '_';
			$newFileName.= $c1;
		}
		// $tmpA = explode('.',$poolFile);
		// if (count($tmpA) > 1) $ext = end($tmpA);
		// else $ext = NULL;
		// $newFileName= trim($newFileName,' ._');
		// $tmpA = explode('.',$newFileName);
		// if (count($tmpA) > 1) $newExt = end($tmpA);
		// else $newExt = NULL;
		// if (strtolower($ext) != strtolower($newExt)) $newFileName.= ".$ext";
		// $newFileName= trim($newFileName,' ._');
		// rename($docFolder."/$db/pulje/$poolFile",$docFolder."/$db/pulje/$newFileName");
		// $poolFile = $newFileName;
		##########also rename other file with the same base names
		//  Get extension of the original pool file
			// Ensure the new filename is clean and has the correct extension
			#below rename the filename for both the pdf and the subject of .info file
			$ext = pathinfo($poolFile, PATHINFO_EXTENSION);
				$origBase = pathinfo($poolFile, PATHINFO_FILENAME);

				// Clean the new file name
				$newFileName = trim($newFileName, ' ._');
				$newExt = pathinfo($newFileName, PATHINFO_EXTENSION);

				// If extension missing or mismatched, add original
				if (strtolower($ext) !== strtolower($newExt)) {
					$newFileName .= ".$ext";
				}
				$newBase = pathinfo($newFileName, PATHINFO_FILENAME);

				// Define the pulje directory path
				$puljePath = "$docFolder/$db/pulje";
				

				$renamedPoolFile = $poolFile;

				if (!is_dir($puljePath)) {
					error_log("❌ Directory does not exist: $puljePath");
				} else {
					$allFiles = scandir($puljePath);
					foreach ($allFiles as $file) {
						if (in_array($file, ['.', '..'])) continue;
						if(!in_array($newExt, ['pdf', 'info'])) continue;

						$fileBase = pathinfo($file, PATHINFO_FILENAME);
						$fileExt  = pathinfo($file, PATHINFO_EXTENSION);
						
						// Rename all files with the same base name (e.g., PDF and .info)
						if ($fileBase === $origBase) {
							$oldPath = "$puljePath/$file";
							$newPath = "$puljePath/$newBase.$fileExt";

							if (!file_exists($oldPath)) {
								error_log("⚠️ Skipped missing file: $oldPath");
								continue;
							}

							if (!is_writable(dirname($newPath))) {
								error_log("❌ Cannot write to: " . dirname($newPath));
								continue;
							}

							// Rename the file
							#if($oldPath != $newPath){
								if (rename($oldPath, $newPath)) {
									error_log("++*++ Renamed: $oldPath → $newPath");

									// Update the poolFile variable
									if ($file === $poolFile) {
										$renamedPoolFile = "$newBase.$fileExt";
									}
									// ✅ If this is the .info file, update subject, account, and amount
										if (strtolower($fileExt) === 'info') {
										// Set subject to newBase if it's empty
														if (empty($newSubject)) {
															$newSubject = $newBase; 
														}
		
											// Build new contents 
											$infoLines = [
												$newSubject ?? '',
												$newAccount ?? '',
												$newAmount ?? ''
											];

											// Write to the file
											if (file_put_contents($newPath, implode(PHP_EOL, $infoLines) . PHP_EOL) !== false) {
												
											} else {
												error_log("❌ Failed to update .info file: $newPath");
											}
										}
									//
								} else {
									error_log("❌ Rename failed: $oldPath → $newPath");
								}
						    #}
						}
					}

					// ✅ Example: Check if PDF exists before further use 
					$pdfPath = "$puljePath/$newBase.pdf";
					if (!file_exists($pdfPath)) {
						#error_log("⚠️ PDF file does not exist after renaming: $pdfPath");
					} else {
						// e.g. copy to saldibilag if needed
						$targetPath = str_replace('/bilag/', '/saldibilag/', $pdfPath); 
						
						//Remove only the first occurrence of '..'
						$targetPath = preg_replace('/\.\./', '', $targetPath, 1); 
						
						if (!file_exists($targetPath)) {
							if (copy($pdfPath, $targetPath)) {
								#error_log("✅ Copied PDF to: $targetPath");
							} else {
								error_log("❌ Failed to copy PDF to: $targetPath");
							}
						}else{
							#error_log("File already exists: $targetPath");
						}
					}

					// ✅ Safe file deletion (instead of `rm '*'`)
					/*
					$cleanupFiles = glob("$puljePath/tmp_*"); // example pattern
					foreach ($cleanupFiles as $fileToRemove) {
						if (is_file($fileToRemove)) {
							unlink($fileToRemove);
							error_log("🗑️ Deleted: $fileToRemove");
						}
					}
					*/

					// Update poolFile to reflect renamed main file
					$poolFile = $renamedPoolFile;
				}

				// ✅ Prevent undefined variable warnings
				$modDate = $modDate ?? '';



		###############
	}
	if ($unlink && $unlinkFile) {
		
		#if ($descFile) unlink("../".$docFolder."/$db/pulje/$descFile");
		if ($unlinkFile) {
			
				$puljePath = "$docFolder/$db/pulje";
				$origBase = pathinfo($unlinkFile, PATHINFO_FILENAME); 

				
				// Define the extensions you want to delete
				$extensionsToDelete = ['pdf', 'info'];

				foreach ($extensionsToDelete as $ext) {
					$fileToDelete = "$puljePath/$origBase.$ext";
					if (is_file($fileToDelete)) {
						if (unlink($fileToDelete)) {
							error_log("Deleted: $fileToDelete");
						} else {
							error_log("Failed to delete: $fileToDelete");
						}
					} else {
						error_log("File not found: $fileToDelete");
					}
				}


		}elseif (isset($_POST['poolFile'])) {
			$poolFile=if_isset($_POST['poolFile']);

			if ($poolFile) unlink("../".$docFolder."/$db/pulje/$poolFile"); 
			
		}
#exit;
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/documents.php?$params&openPool=1\">";
		exit;
	}
	if ($insertFile) {
				include("docsIncludes/uploadDoc.php");
		exit;
	}
	$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
	$google_docs=$r['box7'];

#	if ($sourceId && $source == 'kassekladde') {
	if ($source == 'kassekladde' && $kladde_id) {
		$qtxt = "select bogfort from kladdeliste where id='$kladde_id'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		($r['bogfort'] != '-')?$readOnly=1:$readOnly=0;
	} elseif ($sourceId && $source == 'creditorOrder') {
		$qtxt = "select status from ordrer where id='$sourceId'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		($r['status'] >= '3')?$readOnly=1:$readOnly=0;
	}
	
	$dir=$docFolder."/".$db."/pulje";
	$url="://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
	$url=str_replace("/includes/documents.php","/temp/$db/pulje/",$url);
	$httpS = if_isset($_SERVER,NULL,'HTTPS');
	if ($httpS) $url="s".$url;
	$url="http".$url;
	#$poolFile = null;
	$latestTime = 0;
	if (!$poolFile) {
		if (is_dir($dir)) {
			if ($dh = opendir($dir)) {
				while (($file = readdir($dh)) !== false) {
					// Check for .pdf file (case-insensitive), skip hidden files
					if (substr($file, 0, 1) != '.' && preg_match('/\.pdf$/i', $file)) {
						$filePath = rtrim($dir, '/') . '/' . $file;
						$modTime = filemtime($filePath);

						if ($modTime > $latestTime) {
							$latestTime = $modTime;
							$poolFile = $file;
						}
					}
				}
				closedir($dh);
			}
		}
	}

	// $poolParams =
	// "openPool=1&".
	// "docFolder=$docFolder&".
	// "poolFile=$poolFile&".
	// "fokus=$fokus&".
	// "bilag=$bilag";

	$poolParams =
	"openPool=1"."&".
	"kladde_id=$kladde_id"."&".
	"bilag=$bilag"."&".
	"fokus=$fokus"."&".
	"poolFile=$poolFile"."&".
	"docFolder=$docFolder"."&".
	"sourceId=$sourceId"."&".
	"source=$source";

	print "<form name=\"gennemse\" action=\"documents.php?$params&$poolParams\" method=\"post\">\n";

#####
print "<tr><td width=15% height=\"70%\" align=center>";
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>\n";

print "<tr><td id='fileListContainer'>Loading files...</td></tr>";

print "</tbody></table></td></tr>\n";



// $combinedParams = $params . '&' . $poolParams; 
$encodedDir = urlencode($dir);
$poolFileJs = json_encode($poolFile); // safely escapes quotes
$JsSum = json_encode($sum); // safely escapes quotes

print <<<JS
<script>
(() => {
    let docData = [];
    let currentSort = { field: 'date', asc: false };
    const containerId = 'fileListContainer';
	 const poolFile = {$poolFileJs};
	 const totalSum = {$JsSum};

    async function fetchFiles() {
        const dir = '{$encodedDir}'; 
      

        try {
            const response = await fetch('_docPoolData.php?dir=' + dir + '&poolParams=' + encodeURIComponent('{$poolParams}'));
            const data = await response.json();

            if (data.error) {
                document.getElementById(containerId).innerHTML = '<div style="color:red;">' + escapeHTML(data.error) + '</div>';
                return;
            }

            docData = data;
            renderFiles();
        } catch (err) {
            document.getElementById(containerId).innerHTML = '<div style="color:red;">Error loading files</div>';
            console.error(err);
        }
    }

    function renderFiles() {
        if (!docData.length) {
            document.getElementById(containerId).innerHTML = '<em>No files found.</em>';
            return;
        }

        let html = "<table style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 14px; border: 1px solid #ddd;'>"; 
			html += "<thead style='background:#f4f4f4;'>" +
				"<tr>" +
					"<th onclick=\"sortFiles('subject')\" style='cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left;'>Subject&#9660;</th>" +
					"<th onclick=\"sortFiles('account')\" style='cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left;'>Account&#9660;</th>" +
					"<th onclick=\"sortFiles('amount')\" style='cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left;'>Amount&#9660;</th>" +
					"<th onclick=\"sortFiles('date')\" style='cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left;'>Date&#9660;</th>" +
					"<th style='padding:8px; border:1px solid #ddd; text-align:left;'>File</th>" +
				"</tr></thead><tbody>";

			for (const row of docData) {
    			const dateFormatted = escapeHTML(row.date); 
    			const fileLinkText = "View PDF";
                 //It is assumed here that the last file is always the active one from the Url//
				// Parse URL and get all 'poolFile' params
				const url = new URL(row.href, window.location.origin);
				const allPoolFiles = url.searchParams.getAll('poolFile');

				// Get the last 'poolFile' value
				const lastPoolFile = allPoolFiles.length ? allPoolFiles[allPoolFiles.length - 1] : null;

				// Compare with PHP-passed poolFile
				const isMatch = lastPoolFile === poolFile;

				console.log("Comparing last poolFile:", lastPoolFile, "to PHP poolFile:", poolFile, "=>", isMatch);

				// Apply highlight style if match
				const rowStyle = isMatch
					? "background-color: #ffebcc; font-weight: bold;"
					: "border-bottom:1px solid #ddd;";

			//

				let normalizedTotal = parseFloat(
					totalSum.replace(/\./g, '').replace(',', '.')
				);

				let normalizedAmount = parseFloat(row.amount);

				let boldAmount = (normalizedAmount === normalizedTotal)
					? "<strong>" + escapeHTML(row.amount) + "</strong>"
					: escapeHTML(row.amount);


			//

				html += "<tr style='" + rowStyle + "'>" +
					"<td style='padding:8px; border:1px solid #ddd;'>" + escapeHTML(row.subject) + "</td>" +
					"<td style='padding:8px; border:1px solid #ddd;'>" + escapeHTML(row.account) + "</td>" +
					"<td style='padding:8px; border:1px solid #ddd;'>" + boldAmount + "</td>" +
					"<td style='padding:8px; border:1px solid #ddd;'>" + dateFormatted + "</td>" +
					"<td style='padding:8px; border:1px solid #ddd;'>" +
						"<a href='" + row.href + "' id='" + row.hreftxt + "' style='color:#007BFF; text-decoration:none;' onmouseover=\"this.style.textDecoration='underline'\" onmouseout=\"this.style.textDecoration='none'\">" +
						fileLinkText + "</a></td>" +
				"</tr>";

			}



			html += "</tbody></table>";

			// hover style 
			html += "<style>\
				table tbody tr:hover { background-color: #edf3e8ff; }\
			</style>";

		

        document.getElementById(containerId).innerHTML = html;
    }

	
		function sortFiles(field) {
			const asc = currentSort.field === field ? !currentSort.asc : true;

			docData.sort((a, b) => {
				let valA = a[field];
				let valB = b[field];

				if (field === 'amount') {
						valA = parseFloat(valA) || 0;
						valB = parseFloat(valB) || 0;
				} else if (field === 'date') {
						valA = new Date(valA).getTime() || 0;
						valB = new Date(valB).getTime() || 0;
				} else {
						if (typeof valA === 'string') valA = valA.toLowerCase();
						if (typeof valB === 'string') valB = valB.toLowerCase();
				}

				if (valA === valB) return 0;
				return asc ? (valA > valB ? 1 : -1) : (valA < valB ? 1 : -1);
			});

			currentSort = { field, asc };
			renderFiles();
		}


    function escapeHTML(str) {
        if (typeof str !== 'string') return str;
        return str.replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[m]);
    }

    fetchFiles();
    window.sortFiles = sortFiles;
})();
</script>
JS;

#####
	// if ($poolFile) { //#*rm: cannot remove '*': No such file or directory
	//# cp: cannot stat '../..error .... when documents.php loads*//
	// 	$tmp="../".$docFolder."/$db/pulje/$poolFile";
	// 	if (!is_dir("../temp/$db/pulje")) mkdir("../temp/$db/pulje");
	// 	system("cd ../temp/$db/pulje\nrm *\ncp $tmp .\n");
	// } else {
	// 	$ccalert= __line__." ".findtekst('1416|Ingen bilag i puljen', $sprog_id);
	// 	print "<BODY onLoad=\"javascript:alert('$ccalert')\">\n";
	// 	$tmp="documents.php?$params";
	// 	print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
	// 	exit;
	// }
	if ($poolFile) {
		$tmp = "../" . $docFolder . "/$db/pulje/$poolFile";

		if (!is_dir("../temp/$db/pulje")) {
			mkdir("../temp/$db/pulje", 0777, true);
		}

		// Remove all files safely (ignore error if empty)
		system("cd ../temp/$db/pulje && rm -f -- *");

		// Copy file only if it exists
		if (file_exists($tmp)) {
			system("cp " . escapeshellarg($tmp) . " .");
		} 
	}

	print "</td></tr>\n";



	print "<tr><td width=100% align=center><br></td></tr>\n";
	print "</table></td>\n";
	print "<td rowspan=\"2\" width=85% height=\"100%\" align=center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	print	"<tr><td width=100% align=center>";
	$corrected = 0;
	$ext = pathinfo($poolFile, PATHINFO_EXTENSION);
	$fullName = "$docFolder/$db/pulje/$poolFile";
	
#	cho __line__." $fullName<br>";
	$descFile = $newName = str_replace($ext,'.desc',$fullName);
	if (strpos($fullName,'æ')) {
		$newName = str_replace('æ','ae',$fullName);
		$poolFile = str_replace('æ','ae',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (strpos($fullName,'ø')) {
		$newName = str_replace('ø','oe',$fullName);
		$poolFile = str_replace('ø','oe',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (strpos($fullName,'å')) {
		$newName = str_replace('å','aa',$fullName);
		$poolFile = str_replace('å','aa',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}

	if (strpos($fullName,'Æ')) {
		$newName = str_replace('Æ','AE',$fullName);
		$poolFile = str_replace('Æ','AE',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (strpos($fullName,'Ø')) {
		$newName = str_replace('Ø','OE',$fullName);
		$poolFile = str_replace('Ø','OE',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (strpos($fullName,'Å')) {
		$newName = str_replace('Å','AA',$fullName);
		$poolFile = str_replace('Å','AA',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
/*
	if (strpos($fullName,'(')) {
		$newName = str_replace('(','_',$fullName);
		$poolFile = str_replace('(','_',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (strpos($fullName,')')) {
		$newName = str_replace(')','_',$fullName);
		$poolFile = str_replace(')','_',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
*/
	if (strpos($fullName,'?')) {
		$newName = str_replace('?','_',$fullName);
		$poolFile = str_replace('ø','oe',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (!$ext) {
		$fileType = strtolower(file_get_contents($fullName, FALSE, NULL, 0, 4));
		if ($fileType == '%pdf') $newName = $fullName.'.pdf';
		else $newName = $fullName;
		$newName = str_replace('ø','oe',$newName);
		$poolFile = str_replace('ø','oe',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		if (file_exists($newName) && $newName != $fullName) {
			$fullName = $newName ;
			if ($fileType == '%pdf') $poolFile = $poolFile.'.pdf';
			$corrected = 1;
		} else $corrected = 0;
	}
	if (strtolower($ext) != 'pdf') {
		$choices = array('bmp','jpg','jpeg','png','tif','tiff');
#		$tmp=str_replace($fullName);
		if (in_array(strtolower($ext),$choices)) {
			$fs = filesize($fullName);
			if ($fs > 500000) {
				$reduce = round (50000000 / $fs, 0);
				exec("$exec_path/mogrify -resize $reduce% $fullName");
			}
			$newName =  str_replace($ext,'pdf',$fullName);
			$tmp = str_replace($ext,'pdf',$poolFile);
			if (file_exists("$docFolder/$db/pulje/$tmp")) $poolFile = $tmp;
			exec("$exec_path/convert $fullName $newName");
			if (file_exists($newName))  {
				if (filesize($newName) > 10) {
					unlink($fullName);
					$fullName = $newName;
					$corrected = 1;
				} else {
					unlink($newName);
					$corrected = 0;
				}
			}
		}
	}
	if (!file_exists($fullName) && file_exists("$docFolder/$db/pulje/$poolFile")) {
		$fullName = "$docFolder/$db/pulje/$poolFile";
		$corrected = '0';
	}
#	if ($bruger_id == '-1') {
#		echo $corrected;
#		exit;
#	}
	if ($corrected == '1') {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/documents.php?$params&openPool=1&poolFile=$poolFile\">";
	}
	if ($poolFile) {
		if ($google_docs) $src="http://docs.google.com/viewer?url=$fullName&embedded=true";
		else $src=$tmp;
		print "<iframe style=\"width:100%;height:100%\" src=\"$fullName\" frameborder=\"0\">";
		print "</iframe></td></tr>\n";
	}
	print "</tbody></table></td></tr>\n";
	print "<tr><td><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	$tmp="../".$dir."/".$descFile.".desc";
	if (file_exists($tmp)) {
		system("cd ../temp/$db/pulje\ncp $tmp .\n");
		$fp=fopen("../temp/$db/pulje/$descFile.desc","r");
		while($linje=trim(fgets($fp))) {
			if (strtolower(substr($linje,0,6))=='bilag:') $bilag=trim(substr($linje,6));
			if (strtolower(substr($linje,0,5))=='dato:') $dato=trim(substr($linje,5));
			if (strtolower(substr($linje,0,12))=='beskrivelse:') $beskrivelse=trim(substr($linje,12));
			if (strtolower(substr($linje,0,6))=='debet:') $debet=trim(substr($linje,6));
			if (strtolower(substr($linje,0,7))=='kredit:') $kredit=trim(substr($linje,7));
			if (strtolower(substr($linje,0,10))=='fakturanr:') $fakturanr=trim(substr($linje,10));
			if (strtolower(substr($linje,0,4))=='sum:') $sum=trim(substr($linje,4));
			if (strtolower(substr($linje,0,4))=='sag:') $sag=trim(substr($linje,4));
			if (strtolower(substr($linje,0,4))=='afd:') $afd=trim(substr($linje,4));
			if (strtolower(substr($linje,0,8))==='projekt:') $projekt=trim(substr($linje,8));
		}
	}
	if ($source == 'kassekladde' && !$bilag && $bilag!='0') {
		$r=db_fetch_array(db_select("select max(bilag) as bilag from kassekladde where kladde_id='$sourceId'",__FILE__ . " linje " . __LINE__));
		$bilag=$r['bilag']+1;
	}
	#####get the corresponding .info content of the pdf file
	if ($fullName) {
		//Check if the fullname exists or already deleted:

			if (!file_exists($fullName)) {
			
				
				// Attempt to get the first .pdf file in the same directory
				$directory = dirname($fullName);
				$pdfFiles = glob($directory . '/*.pdf');

				if (!empty($pdfFiles)) {
					$fullName = $pdfFiles[0]; // First PDF found 

					$poolFile = basename($pdfFiles[0]);
					
				} else {
					error_log("❌ No PDF files found in directory: $directory");
					$fullName = null; // Optional: unset it if nothing is found
				}
			}

		// Ensure it's a .pdf file
		if (strtolower(pathinfo($fullName, PATHINFO_EXTENSION)) === 'pdf') {
			$baseName = pathinfo($fullName, PATHINFO_FILENAME);
			$directory = dirname($fullName);
			$infoFile = "$directory/$baseName.info";

			if (file_exists($infoFile)) {
				// Check that it's not empty
				if (filesize($infoFile) > 0) {
					$lines = file($infoFile, FILE_IGNORE_NEW_LINES);

					// Default values
					$Subject = $lines[0] ?? '';
					$Account = $lines[1] ?? '';
					$Amount  = $lines[2] ?? '';
					
				} else {
					$Subject = if_isset($newSubject,NULL);
					$Account = if_isset($newAccount,NULL);
					$Amount  = if_isset($newAmount,NULL);

				}
			} 
		} else {
			error_log("Invalid file extension (expected .pdf): $fullName");
		}
	}

	#####################################end .info part
	
	if (!$dato) $dato=date("d-m-Y");
     //#################
	print <<<HTML

<script>
function updateSubjectFromFilename() {
    const fileInput = document.querySelector('input[name="newFileName"]');
    const subjectInput = document.querySelector('input[name="newSubject"]');
    if (fileInput && subjectInput) {
        let filename = fileInput.value.split(/[\\/]/).pop(); // handles both \ and /
        let baseName = filename.replace(/\.[^/.]+$/, ""); // remove extension

        // Always update subject to basename when filename changes
        subjectInput.value = baseName;
    }
}

function updateFilenameFromSubject() {
    const fileInput = document.querySelector('input[name="newFileName"]');
    const subjectInput = document.querySelector('input[name="newSubject"]');
    if (fileInput && subjectInput) {
        let filename = fileInput.value;
        let extension = "";

        // Extract extension if any
        const lastDot = filename.lastIndexOf(".");
        if (lastDot !== -1) {
            extension = filename.substring(lastDot);
        }

        // Always update filename's basename with the subject's value when subject changes
        fileInput.value = subjectInput.value + extension;
    }
}
</script>

<tr>
    <td>Filnavn</td>
    <td><input type="text" style="width:150px"
        name="newFileName" value="$poolFile" oninput="updateSubjectFromFilename()"></td>
</tr>
<tr>
    <td>Subject</td>
    <td><input type="text" style="width:150px"
        name="newSubject" value="$Subject" oninput="updateFilenameFromSubject()"></td>
</tr>
<tr>
    <td>Account</td>
    <td><input type="text" style="width:150px"
        name="newAccount" value="$Account"></td>
</tr>
<tr>
    <td>Amount</td>
    <td><input type="text" style="width:150px"
        name="newAmount" value="$Amount"></td>
</tr>
<tr>
    <td colspan="2"><input style="width:100%" type="submit"
        name="rename" value="Ret filnavn"></td>
</tr>

HTML;

		if(empty($sum) && empty($beskrivelse)) {
			if($Amount) $sum = $Amount;  //set these for updating the previous data if needed
			if($Subject) $beskrivelse = $Subject;
			if($Date) $dato = $Date;
		}
	//##################
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\"
	name=\"insertFile\" value=\"".findtekst('1415|Indsæt', $sprog_id)."\"</tr>\n";
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\"
	name=\"unlink\" value=\"".findtekst('1099|Slet', $sprog_id)."\" onclick=\"return confirm('Er du sikker på at du vil slette?')\"></tr>\n";
	print "<tr><td>Bilag&nbsp;</td>";
	if ($readOnly) print "<td>$bilag</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"bilag\" value=\"$bilag\"</td></tr>\n";
	print "<tr><td>Dato&nbsp;</td>";
	if ($readOnly) print "<td> $dato</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"dato\" value=\"$dato\"</td></tr>\n";
	print "<tr><td>Beskrivelse&nbsp;</td>";
	if ($readOnly) print "<td> $beskrivelse</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"beskrivelse\" value=\"$beskrivelse\"</td></tr>\n";
	print "<tr><td>Debet&nbsp;</td>";
	if ($readOnly) print "<td> $debet</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"debet\" value=\"$debet\"</td></tr>\n";
	print "<tr><td>Kredit&nbsp;</td>";
	if ($readOnly) print "<td> $kredit</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"kredit\" value=\"$kredit\"</td></tr>\n";
	print "<tr><td>Fakturanr&nbsp;</td>";
	if ($readOnly) print "<td> $fakturanr</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"fakturanr\" value=\"$fakturanr\"</td></tr>\n";
	 print "<tr><td>Sum&nbsp;</td>";
	if ($readOnly) print "<td> $sum</td><tr>";
	else  print "<td><input type=\"text\" id=\"sumInput\" style=\"width:150px\" name=\"sum\" value=\"$sum\"></td></tr>\n";
	print "<tr><td>Sag&nbsp;</td>";
	if ($readOnly) print "<td> $sag</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"sag\" value=\"$sag\"</td></tr>\n";
	print "<tr><td>Afd&nbsp;</td>";
	if ($readOnly) print "<td> $afd</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"afd\" value=\"$afd\"</td></tr>\n";
	print "<tr><td>Projekt&nbsp;</td>";
	if ($readOnly) print "<td> $projekt</td><tr>";
	else print "<td><input type=\"text\" style=\"width:150px\" name=\"projekt\" value=\"$projekt\"</td></tr>\n";
	print "</tbody></table></td></tr>\n";
	print "<input type=\"hidden\" style=\"width:150px\" name=\"unlinkFile\" value=\"$fullName\"</td></tr>\n";
	print "<input type=\"hidden\" style=\"width:150px\" name=\"descFile\" value=\"$descFile\"</td></tr>\n";
	print "</form>";
	if($docFocus) {
	print "<script language=\"javascript\">";
	print "document.gennemse.$docFocus.focus();";
	print "</script>";
	}else{
		alert("Please click 'View' to proceed");
	}

	exit;


	

} # endfunc gennemse
?>
