<?php 

if ($option == 1) {
				print "<div id='hiddenContent1' style='display: block;'>"; // show content in div if 'limit' is  set
			} else {
				print "<div id='hiddenContent1' style='display: none;'>"; 
			}
			#############################################
			$id = $filtyper = $filtypebeskrivelse = $lev_id=$aktiv = $prislister = array();
			$i=0;
			$q=db_select("select * from grupper where art = 'PL' order by beskrivelse",__FILE__ . " linje " . __LINE__);
			
			while ($r = db_fetch_array($q)) {
				$id[$i]=$r['id'];
				$beskrivelse[$i]=$r['beskrivelse']; //holds names of the records
				$lev_id[$i]=$r['box1'];
				$prisfil[$i]=$r['box2']; //file location ?
				$opdateret[$i]=$r['box3'];
				$inserted[$i]=$r['box4']; //on
        $aktiv[$i]=$r['box12']; //Yes
				$rabat[$i]=$r['box6'];
				$gruppe[$i]=$r['box8'];
				$filtype[$i]=$r['box9'];
				$delimiter[$i] = $r['box10'];
				$encoding[$i]=$r['box11'];
				$i++;
			}
		
			$vgrp = array();
			$i = 0;
			$qtxt = "select * from grupper where art = 'VG' and fiscal_year = '$regnaar' order by kodenr";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$vgrp[$i]   = $r['kodenr'];
				$vgbesk[$i] = $r['beskrivelse'];
				$i++;
			}
		
			$filtyper[0]="csv";
			$filtypebeskrivelse[0]="Kommasepareret";
			$filtyper[1]="tab";
			$filtypebeskrivelse[1]="Tabulator";
			$filtyper[2]="sql";
			$filtypebeskrivelse[2]="Databasefil (SQL-dump)";
			$filtyper[3]="html";
			$filtypebeskrivelse[3]="HTML-celler (td)";
			
			$i = 0;
			$qtxt = "select id, kontonr, firmanavn from adresser where art = 'K' order by firmanavn";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if (!isset($r['lukket'])) {
					$supplId[$i]      = $r['id'];
					$supplAccout[$i]  = $r['kontonr'];
					$supplCompany[$i] = $r['firmanavn'];
					$i++;
				}
			}
			
				print '<table style="width: 100%; border-collapse: collapse;">';
				// Default delimiter (comma)
			   
					$csvData = '';
					$idCount = count($id);

					for ($x = 0; $x < $idCount; $x++) {
						if (trim($aktiv[$x]) === 'Yes' && !empty($prisfil[$x])) {
         				# $checkDelimiter = bin2hex($delimiter[$x])
							$delimiter = if_isset($delimiter[$x], ';');

							$url = (str_starts_with($prisfil[$x], 'http://') || str_starts_with($prisfil[$x], 'https://'))
								? $prisfil[$x]
								: 'https://' . $prisfil[$x];

							$csv_url = filter_var($url, FILTER_SANITIZE_URL);

							if (!filter_var($csv_url, FILTER_VALIDATE_URL)) {
								echo "<script>alert('Invalid URL format: $url');</script>";
								continue;
							}

							$csvData = @file_get_contents($csv_url);

							if ($csvData === false) {
								echo "<script>alert('Unable to fetch the CSV file: $csv_url');</script>";
								$csvData = '';
								continue;
							}

							// ✅ CSV found — stop loop
							break;
						}
					}

					if (empty($csvData)) {
						error_log('No valid active CSV file found matching the criteria.');
						echo "<script>alert('No valid active CSV file found matching the criteria.');</script>";
						return;
					}
					$lines = explode(PHP_EOL, trim($csvData));
					$rows = [];
					foreach ($lines as $line) {
						$rows[] = str_getcsv($line, $delimiter);
					}
					(isset($rows[0])) ? $header = $rows[0] : $header = [];
					unset($rows[0]);

					
					$rowCount = count($rows);
					$varenrList = [];

					for ($i = 1; $i <= $rowCount; $i++) {
						$covtR = mb_convert_encoding((isset($rows[$i][0])) ? $rows[$i][0] : '', 'UTF-8', 'ISO-8859-1');

						if ($covtR == '/' || $covtR == "") continue;

						$varenrList[] = $covtR;
					}

					$Nrows = [];

					if (count($varenrList) > 0) {
						
            $filteredVarenrList = array_filter($varenrList, function($value) {
                return is_string($value) && $value !== ''; 
            });
            $varenrListStr = implode("','", array_map('addslashes', $filteredVarenrList));
            $qr = db_select("SELECT varenr FROM varer WHERE varenr IN ('$varenrListStr')", __FILE__ . " linje " . __LINE__);


						$existingVarnr = [];
						if(!$qr) {
							echo "<script>alert('Error fetching existing varenr from database.');</script>";
							error_log('Error fetching existing varenr from database.');
							exit;
							#return;
						}
						while ($row = db_fetch_array($qr)) {
							$existingVarnr[] = $row['varenr'];
						}

						for ($i = 1; $i <= $rowCount; $i++) {
							$covtR = mb_convert_encoding($rows[$i][0] ?? '', 'UTF-8', 'ISO-8859-1');

							if ($covtR == '/' || $covtR == "" || in_array($covtR, $existingVarnr)) continue;

							$Nrows[$i] = $rows[$i];
						}
					}

				if (count($Nrows) > 0) {	
				    print '<table border="1" style="width: 100%; name: items_A; border-collapse: collapse; margin-bottom: 20px; margin-top: 5px;">';

                // Table Header
                
                ######################
                print '<form method="post" action="_varerInsert.php">'; // Form to submit selected rows
                print '<table border="1" cellpadding="5" cellspacing="0">';

                // Header row with "Select All" checkbox
                print '<tr style="background-color: #F6F6F6;">';
                print '<th><input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes(this)"></th>';
                foreach ($header as $columnName) {
                  $encodedHeader = htmlspecialchars(mb_convert_encoding($columnName, 'UTF-8', 'ISO-8859-1'));
                  print '<th>' . $encodedHeader . '</th>';
                }
                print '</tr>';
                $idd = $_GET['id'] ?? null;
                $cnrRows = count($Nrows);

                // Data rows
                for ($i = 1; $i <= $cnrRows; $i++) {
                  if (empty($Nrows[$i])) continue;
                  print '<tr style="background-color:rgb(241, 239, 202);">';
                  // Prepare row data
                  $rowData = [];
                  foreach ($header as $index => $columnName) {
                    $columnValue = $Nrows[$i][$index] ?? '';
                    $columnValue = mb_convert_encoding($columnValue, 'UTF-8', 'ISO-8859-1');
                    $rowData[mb_convert_encoding($columnName, 'UTF-8', 'ISO-8859-1')] = $columnValue;
                  }
                  $rowData['id'] = $idd;
                  $rowData['bordnr'] = $bordnr;
                  $rowData['db'] = $db;
                  $rowData['fokus'] = 'varenr';

                  $serialized = htmlspecialchars(json_encode($rowData), ENT_QUOTES, 'UTF-8');
                  print "<td><input type='checkbox' class='rowCheckbox' name='selectedRows[]' value='{$serialized}'></td>";
                  // Data columns
                  foreach ($Nrows[$i] as $column) {
                    $column = mb_convert_encoding($column, 'UTF-8', 'ISO-8859-1');
                    print '<td>' . htmlspecialchars($column) . '</td>';
                  }
                  print '</tr>';
                }
                print '</table>'; // End of data table
                print '<br>';
                print '<input type="submit" value="Insert Data" style="padding: 10px; background-color:rgb(47, 156, 102); color: white; border: none; border-radius: 4px;">';
                print '</form>';
                // JavaScript block in print
                print '
                <script>
                function toggleAllCheckboxes(source) {
                  var checkboxes = document.querySelectorAll(".rowCheckbox");
                  checkboxes.forEach(function(cb) {
                    cb.checked = source.checked;
                  });
                }
                </script>
                ';                     
          }
		        print '</table>'; //

