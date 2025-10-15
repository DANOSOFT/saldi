<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/docPool.php --- ver 4.1.1 --- 2025-10-09 --- 
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
// 20251007 LOE Refactored the fixed bottom table, added background color and various enhancement.
function docPool($sourceId,$source,$kladde_id,$bilag,$fokus,$poolFile,$docFolder,$docFocus){
	global $bruger_id,$db,$exec_path;
	global $params,$regnaar,$sprog_id,$userId,$bgcolor, $bgcolor5;
	
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
	$newDate	   = if_isset($_POST,NULL,'newDate');

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
		#if(!isset($dato )&& isset($newDate)) {
			$formattedDate = date("d-m-Y", strtotime($newDate));
			$dato = $formattedDate;
			$_POST['dato']=$dato;
		#}
		
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
					$newExt = $ext;
				}
				
				$newBase = pathinfo($newFileName, PATHINFO_FILENAME);

				// Define the pulje directory path
				$puljePath = "$docFolder/$db/pulje";
				

				$renamedPoolFile = $poolFile;

				if (!is_dir($puljePath)) {
					error_log("Directory does not exist: $puljePath");
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
								error_log(" Skipped missing file: $oldPath");
								continue;
							}

							if (!is_writable(dirname($newPath))) {
								error_log(" Cannot write to: " . dirname($newPath)); 
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
												$newAmount ?? '',
												$newDate ?? ''
											]; 

											// Write to the file
											if (file_put_contents($newPath, implode(PHP_EOL, $infoLines) . PHP_EOL) !== false) {
												
											} else {
												error_log("Failed to update .info file: $newPath");
											}
										}
									//
								} else {
									error_log("Rename failed: $oldPath → $newPath");
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
								error_log("Failed to copy PDF to: $targetPath");
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
							#error_log("Deleted: $fileToDelete");
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
						#clearstatcache(); 
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
print "<tr><td width='15%' height='70%' valign='top'>";
#9a9a9a
// print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>\n";
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"padding: 0pt 0pt 1px;\"><tbody>\n";


 print "<tr><td id='fileListContainer'>Loading files...</td></tr>";


print "</tbody></table></td></tr>\n";

print "<style>
 #fileListContainer {
  display: flex;
  flex-direction: column;
  justify-content: flex-start;  
  padding: 0;
}
#fileListContainer > div {
  flex: 1;   
  margin: 0;
  padding: 0;
}

</style>";



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
				console.error(dir + ': ' + data.error);
                return;
            }

            docData = data;
            renderFiles();
        } catch (error) {
            document.getElementById(containerId).innerHTML = '<div style="color:red;">Error loading files</div>';
            console.error(error);
        }
    }

    function renderFiles() {
			if (!docData.length) {
				document.getElementById(containerId).innerHTML = '<em>No files found.</em>';
				return;
			}

		let html = `
  <div style="max-height:500px; overflow-y:auto; margin:0; padding-right:3px;">
    <table style="border-collapse:collapse; width:100%; font-family:Arial, sans-serif; font-size:14px; border:1px solid #ddd; margin:0; padding:0;">
      <thead style="background:#99a998; position:sticky; top:0; z-index:10; margin:0; padding:0;">
        <tr>
					<th onclick="sortFiles('subject')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left;">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Subject</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th onclick="sortFiles('account')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left;">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Account</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th onclick="sortFiles('amount')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left;">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Amount</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th onclick="sortFiles('date')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left;">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Date</span>
							<span>&#9660;</span>
						</div>
					</th>
				</tr>
			</thead>
			<tbody>
		`;


		let activeRows = '';
		let otherRows = '';

		for (const row of docData) {
			const dateFormatted = escapeHTML(row.date.split(' ')[0]);

			const url = new URL(row.href, window.location.origin);
			const allPoolFiles = url.searchParams.getAll('poolFile');
			const lastPoolFile = allPoolFiles.length ? allPoolFiles[allPoolFiles.length - 1] : null;
			const isMatch = lastPoolFile === poolFile;

			const rowStyle = isMatch
				? "background-color: #ffebcc; font-weight: bold;" 
				: "border-bottom:1px solid #ddd;";

			//let normalizedTotal = parseFloat(totalSum.replace(/\./g, '').replace(',', '.'));
			let normalizedTotal = parseFloat(totalSum?.replace(/\./g, '').replace(',', '.') || 0);
			let normalizedAmount = parseFloat(row.amount);
			let boldAmount = (normalizedAmount === normalizedTotal)
				? "<strong>" + escapeHTML(row.amount) + "</strong>"
				: escapeHTML(row.amount);

			const subjectCell = isMatch
				? "<input type='text' name='newSubject' value='" + escapeHTML(row.subject) + "' " +
				"style='width: 100%; border: none; background: transparent; font-family: inherit; font-size: inherit;' " +
				"oninput='updateFilenameFromSubject();' onclick='event.stopPropagation();' onkeydown='handleEnterKey(event)'>"
				: escapeHTML(row.subject);

			const accountCell = isMatch
				? "<input type='text' name='newAccount' value='" + escapeHTML(row.account) + "' " +
				"style='width: 100%; border: none; background: transparent; font-family: inherit; font-size: inherit;' " +
				"onclick='event.stopPropagation();' onkeydown='handleEnterKey(event)'>"
				: escapeHTML(row.account);

			const amountCell = isMatch
				? "<input type='text' name='newAmount' value='" + escapeHTML(row.amount) + "' " +
				"style='width: 100%; border: none; background: transparent; font-family: inherit; font-size: inherit;' " +
				"onclick='event.stopPropagation();' onkeydown='handleEnterKey(event)'>"
				: boldAmount;

			const dateCell = isMatch
				? "<input type='date' name='newDate' value='" + dateFormatted + "' " +
				"style='width: 100%; border: none; background: transparent; font-family: inherit; font-size: inherit;' " +
				"onclick='event.stopPropagation();' onkeydown='handleEnterKey(event)' onchange='submitRowData(event)'>"
				: dateFormatted;

			const rowHTML = "<tr style='" + rowStyle + " cursor: pointer;' onclick=\"window.location.href='" + row.href + "'\">" +
				"<td style='padding:8px; border:1px solid #ddd; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.subject) + "'>" + subjectCell + "</td>" +
				"<td style='padding:8px; border:1px solid #ddd; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.account) + "'>" + accountCell + "</td>" +
				"<td style='padding:8px; border:1px solid #ddd; max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.amount) + "'>" + amountCell + "</td>" +
				"<td style='padding:8px; border:1px solid #ddd; max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.date) + "'>" + dateCell + "</td>" +
				"</tr>";


			if (isMatch) {
				activeRows += rowHTML;
			} else {
				otherRows += rowHTML;
			}
		}


		// Ensure active rows come first
		html += activeRows + otherRows;



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
	
//handle submit on enter key
window.handleEnterKey = function(event) {
  if (event.key === "Enter") {
    event.preventDefault();
    submitRowData();
  }
};


window.submitRowData = function() {
  const form = document.forms['gennemse'];
  if (form) {
    // Copy visible inputs into hidden fields
    const subjectInput = document.querySelector("input[name='newSubject']");
    const accountInput = document.querySelector("input[name='newAccount']");
    const amountInput = document.querySelector("input[name='newAmount']");
    const dateInput = document.querySelector("input[name='newDate']");

    if (subjectInput) document.getElementById("hiddenSubject").value = subjectInput.value;
    if (accountInput) document.getElementById("hiddenAccount").value = accountInput.value;
    if (amountInput) document.getElementById("hiddenAmount").value = amountInput.value;
    if (dateInput) document.getElementById("hiddenDate").value = dateInput.value;

    // Add rename field if not already there
    if (!form.querySelector("input[name='rename']")) {
      const hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'rename';
      hiddenInput.value = 'Ret filnavn';
      form.appendChild(hiddenInput);
    }

    form.submit();
  } else {
    console.error("Form 'gennemse' not found.");
  }
};

// Attach submit listener after DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector("form[name='gennemse']");
  if (form) {
    form.addEventListener("submit", function () {
      const subjectInput = document.querySelector("input[name='newSubject']");
      const accountInput = document.querySelector("input[name='newAccount']");
      const amountInput = document.querySelector("input[name='newAmount']");
      const dateInput = document.querySelector("input[name='newDate']");

      if (subjectInput) document.getElementById("hiddenSubject").value = subjectInput.value;
      if (accountInput) document.getElementById("hiddenAccount").value = accountInput.value;
      if (amountInput) document.getElementById("hiddenAmount").value = amountInput.value;
      if (dateInput) document.getElementById("hiddenDate").value = dateInput.value;
    });
  }
});
//end submit on enter key



    fetchFiles();
    window.sortFiles = sortFiles;



})();





document.querySelector("form").addEventListener("submit", function () {
    const subjectInput = document.querySelector("input[name='newSubject']");
    const accountInput = document.querySelector("input[name='newAccount']");
    const amountInput = document.querySelector("input[name='newAmount']");
    const dateInput = document.querySelector("input[name='newDate']");

    if (subjectInput) document.getElementById("hiddenSubject").value = subjectInput.value;
    if (accountInput) document.getElementById("hiddenAccount").value = accountInput.value;
    if (amountInput) document.getElementById("hiddenAmount").value = amountInput.value;
    if (dateInput) document.getElementById("hiddenDate").value = dateInput.value;
});


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



	#print "<tr><td width=100% align=center><br></td></tr>\n"; 
	print "</table></td>\n";
	print "<td rowspan=\"2\" width=85% height=\"100%\" align=center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"padding: 0pt 0pt 1px;\"><tbody>";
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
   
	print "<tr><td id='fixedCell' style='position: relative; margin-bottom: 150px; width:20%; padding-bottom: 150px;'>";



		// Start of page content
		print "<div id='contentWrapper'>";

		#page content would be echoed/printed here.

		

		// Now output the fixed bottom div
		print "<div id='fixedBottom' style='position: fixed; bottom: 7px; left: 3px; width: 100px;
			 padding: 7px; 
			 box-sizing: border-box; z-index: 1000;'>
			<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'>
				<tbody>"; 

		# adjusts the fixed div width and adds padding to content

			print "<script>
				function updateFixedDiv() {
					const fixedCell = document.getElementById('fixedCell');
					const fixedDiv = document.getElementById('fixedBottom');
					const content = document.getElementById('contentWrapper');
					const fileListContainer = document.getElementById('fileListContainer'); // Container whose width we want to use

					if (!fixedDiv || !fileListContainer) {
						return; // Exit if fixedDiv or fileListContainer is not found
					}

					setTimeout(function() {
						const rect = fileListContainer.getBoundingClientRect();
						const fileListContainerWidth = rect.width; 

						
						fixedDiv.style.width = fileListContainerWidth + 'px';

						// Set the width and position of fixedCell based on the fileListContainer width
						if (fixedCell) {
							fixedCell.style.width = fileListContainerWidth * 0.2 + 'px';  

							const fixedCellRect = fixedCell.getBoundingClientRect();  

							fixedDiv.style.left = fixedCellRect.left + 'px'; 
							fixedDiv.style.width = fixedCellRect.width + 'px'; 
						}

						// Adjust content padding based on the height of fixedDiv
						if (content) {
							content.style.paddingBottom = fixedDiv.offsetHeight + 'px';
						}

					}, 720);  // Delay of 720ms 

				}

				// Run as soon as DOM is ready
				document.addEventListener('DOMContentLoaded', function() {
					updateFixedDiv();
				});

				window.addEventListener('load', function () {
					const style = document.createElement('style');
					style.textContent = `
						#fixedCell input[type='text'] {
							max-width: 80%;
							width: 50%;
							box-sizing: border-box;
						}

						/* Example Media Query for Small Screens (e.g., mobile) */
						@media (max-width: 768px) {
							#fixedCell {
								width: 50% !important;  /* Adjust to 50% width on mobile */
							}
						}

						/* Example Media Query for Large Screens (e.g., desktop) */
						@media (min-width: 1200px) {
							#fixedCell {
								width: 30% !important;  /* Adjust to 30% width on large screens */
							}
						}
					`;
					document.head.appendChild(style);

					updateFixedDiv(); 
				});

				window.addEventListener('resize', function() {
					updateFixedDiv();  
				});
			</script>";


		##################



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
					error_log("No PDF files found in directory: $directory");
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
					$Date	= $lines[3] ?? '';
					
				} else {
					$Subject = if_isset($newSubject,NULL);
					$Account = if_isset($newAccount,NULL);
					$Amount  = if_isset($newAmount,NULL);
					$Date	= if_isset($newDate,NULL);	

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

		if (fileInput) {
			// Always strip .pdf from what the user types
			fileInput.value = fileInput.value.replace(/\.pdf$/i, '');

			if (subjectInput) {
				subjectInput.value = fileInput.value.trim();
			}
		}
	}

	// On page load: remove .pdf from value (if set by server)
	document.addEventListener('DOMContentLoaded', function () {
		const fileInput = document.querySelector('input[name="newFileName"]');
		const subjectInput = document.querySelector('input[name="newSubject"]');

		if (fileInput) {
			fileInput.value = fileInput.value.replace(/\.pdf$/i, '');
			if (subjectInput) {
				subjectInput.value = fileInput.value.trim();
			}
		}
	});

	// Before form submit, add .pdf back
	document.querySelector('form')?.addEventListener('submit', function () {
		const fileInput = document.querySelector('input[name="newFileName"]');
		if (fileInput && !fileInput.value.endsWith('.pdf')) {
			fileInput.value = fileInput.value.trim() + '.pdf';
		}
	});


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



HTML;

			
			$useAlt = false;

			//##################
			print "<div id='activeRowContainer'></div>";

			print "<td colspan=\"2\" style=\"text-align: center; white-space: nowrap;\">";

			print "<input type=\"hidden\" name=\"newSubject\" id=\"hiddenSubject\">";
			print "<input type=\"hidden\" name=\"newAccount\" id=\"hiddenAccount\">";
			print "<input type=\"hidden\" name=\"newAmount\" id=\"hiddenAmount\">";
			print "<input type=\"hidden\" name=\"newDate\" id=\"hiddenDate\">";

			print "<input type=\"submit\" name=\"rename\" value=\"Ret filnavn\" style=\"margin-right: 2px;\">";
			print "<input type=\"submit\" name=\"insertFile\" value=\"" . findtekst('1415|Indsæt', $sprog_id) . "\" style=\"margin-right: 2px;\">";
			print "<input type=\"submit\" name=\"unlink\" value=\"" . findtekst('1099|Slet', $sprog_id) . "\" style=\"margin-right: 0px;\" onclick=\"return confirm('Er du sikker på at du vil slette?')\">";
			print "</td></tr>\n";


			print "<td style=\"text-align: center; white-space: nowrap; padding-top: 10px;\">";

			// --- Filnavn row ---
			$bg = $useAlt ? $bgcolor5 : $bgcolor;
			$useAlt = !$useAlt;
			print "<tr style=\"background-color: $bg;\"><td><b>Filnavn</b></td>";
			print "<td><input type=\"text\" style=\"width:150px; border:none; background:transparent; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;\" title=\"" . 
				htmlspecialchars($poolFile, ENT_QUOTES) . "\" name=\"newFileName\" id=\"filenameInput\" value=\"" . 
				htmlspecialchars($poolFile, ENT_QUOTES) . "\" readonly></td></tr>\n";

			// --- Empty spacing row ---
			print "<tr><td colspan=\"2\"></td></tr>\n";

			// Define fields for input
			$fields = [
				"Bilag" => "bilag",
				"Dato" => "dato",
				"Beskrivelse" => "beskrivelse",
				"Debet" => "debet",
				"Kredit" => "kredit",
				"Fakturanr" => "fakturanr",
				"Sum" => "sum",
				"Sag" => "sag",
				"Afd" => "afd",
				"Projekt" => "projekt"
			];

			foreach ($fields as $label => $varName) {
				$bg = $useAlt ? $bgcolor5 : $bgcolor;
				$useAlt = !$useAlt;

				$value = $$varName; // get variable by name
				print "<tr style=\"background-color: $bg;\"><td>$label&nbsp;</td>";
				if ($readOnly) {
					print "<td>$value</td></tr>\n";
				} else {
					$extra = $varName === "sum" ? " id=\"sumInput\"" : "";
					print "<td><input type=\"text\"$extra style=\"width:150px; border:none; background:transparent;\" name=\"$varName\" value=\"$value\"></td></tr>\n";
				}
			}

			print "</tbody></table></td></tr>\n";

			print "<input type=\"hidden\" style=\"width:150px\" name=\"unlinkFile\" value=\"$fullName\">\n";
			print "<input type=\"hidden\" style=\"width:150px\" name=\"descFile\" value=\"$descFile\">\n";

	print "</form>";
	
	if(!is_numeric($docFocus)) {
	print "<script language=\"javascript\">";
	print "document.gennemse.$docFocus.focus();";
	print "</script>";
	}else{
		print '<script>';
		print 'if (!sessionStorage.getItem("docAlertShown")) {'; 
		print '    alert("Please click on the document to proceed");';
		print '    sessionStorage.setItem("docAlertShown", "true");'; 
		print '}';
		print '</script>';

	}

	exit;


	

} # endfunc gennemse
?>
