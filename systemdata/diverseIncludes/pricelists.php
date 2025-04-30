<?php
#session_start();
function pricelists(){


    function editForm($index) {
        
        print '
            <script>
                var index = ' . $index . ';  // Correctly inject the PHP variable into JavaScript
                function toggleEditForm(index) {
                    var editForm = document.getElementById("edit-form-" + index);
                    document.getElementById("addPricelistBtn").style.display = "none";
                    if (editForm.style.display === "none") {
                        editForm.style.display = "table-row";
                    } else {
                        editForm.style.display = "none";
                    }
                }
            </script>
        ';
        //print '<button type="submit" name="edit_submit" style="margin-left: 10px;">Save</button>';
    }
	global $bgcolor, $bgcolor5;
	global $regnaar;
	global $sprog_id;

	$id = $filtyper = $filtypebeskrivelse = $lev_id = $prislister = array();
	$i=0;
	$q=db_select("select * from grupper where art = 'PL' order by beskrivelse",__FILE__ . " linje " . __LINE__);
   
	while ($r = db_fetch_array($q)) {
		$id[$i]=$r['id'];
		$beskrivelse[$i]=$r['beskrivelse']; //holds names of the records
		$lev_id[$i]=$r['box1'];
		$prisfil[$i]=$r['box2']; //file location ?
		$opdateret[$i]=$r['box3'];
		$aktiv[$i]=$r['box4'];
		$rabat[$i]=$r['box6'];
		// $gruppe[$i]=$r['box8'];
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
        $gruppe[$i]=$r['box8'];
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
#	}

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

    $k=0;
    $qtxt = "select * from grupper where art = 'VG' and fiscal_year = '$regnaar' order by kodenr";
	$q1 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
    while ($row = db_fetch_array($q1)){
       
        $beskrivelse1[$k]=$row['beskrivelse'];
        $k++;
    }
 //e.g  https://saldi.dk/Buchberg/varer.csv
 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handling submitted data for editing existing entries
    if (isset($_POST['edit_submit'])) { // Check for edit form submission
        if (isset($_POST['edit_beskrivelse']) && isset($_POST['edit_prisfil'])) {
            foreach ($_POST['edit_beskrivelse'] as $index => $newBeskrivelse) {
                $newPrisfil = $_POST['edit_prisfil'][$index];
                $delimiter = isset($_POST['edit_delimiter'][$index]) ? $_POST['edit_delimiter'][$index] : '';
                $newGruppe = isset($_POST['edit_gruppe'][$index]) ? $_POST['edit_gruppe'][$index] : '';
                $newEncoding = isset($_POST['edit_encoding'][$index]) ? $_POST['edit_encoding'][$index] : '';
                $id = isset($_POST['id'][$index]) ? $_POST['id'][$index] : '';
                
                $newBeskrivelse = htmlspecialchars($newBeskrivelse);
                $newPrisfil = htmlspecialchars($newPrisfil);
                $delimiter = htmlspecialchars($delimiter);
                $newGruppe = htmlspecialchars($newGruppe);
                $newEncoding = htmlspecialchars($newEncoding);
                $id = htmlspecialchars($id);
            
              
                    $qtxt = "update grupper set box10='$delimiter', box8='$newGruppe',beskrivelse='$newBeskrivelse',";  
                    $qtxt.="box11='$newEncoding',box2='$newPrisfil' where id='$id'";

                    db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                    print "<script>alert('Updated.');</script>";
                    print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
                    exit;
            
            }
            return; // Stop further processing after handling edit form
        }
    }elseif (isset($_POST['add_submit'])) { // Check for add form submission
        if (isset($_POST['new_beskrivelse']) && isset($_POST['csv_url'])) {
            $newDescription = htmlspecialchars($_POST['new_beskrivelse']);
            $newUrl = htmlspecialchars($_POST['csv_url']);
            $delimiter = htmlspecialchars($_POST['new_delimiter']);
            $newEncoding = htmlspecialchars($_POST['new_encoding']);
            $newProductGroup = htmlspecialchars($_POST['new_product_group']);
          
            
            #######################################
            $qtxt = "insert into grupper(box2,box10,box11,beskrivelse,box8,art) values ";
            $qtxt.= "('$newUrl','$delimiter','$newEncoding','$newDescription',2,'PL')";
            $saved = db_modify($qtxt,__FILE__ . " linje " . __LINE__);


            #######################################

        #****************************
        // Check if a URL was provided
            if (!empty($_POST['csv_url'])) {
            
            $csv_url = filter_var($_POST['csv_url'], FILTER_SANITIZE_URL);
            if (!filter_var($csv_url, FILTER_VALIDATE_URL)) {
                
                print "<script>alert('Invalid URL format.');</script>";
            print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
                exit;
            }
            
            $csvData = @file_get_contents($csv_url);
            if ($csvData === FALSE) {
            
                print "<script>alert('Unable to fetch the CSV file1. Please check the URL.');</script>";
            
                print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";

                exit;
            }
            
        } else {
            print "<script>alert('Please provide a CSV file URL or upload a CSV file.');</script>";
             print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
            exit;
        }

         // Parse CSV data to get the header row
         $lines = explode(PHP_EOL, trim($csvData));
         $header = str_getcsv(trim($lines[0] ?? ''), $delimiter);
 
         if (!$header || count($header) < 2) {
            if($delimiter != ';'){
                $delimiter = ';';
            }else{
                print "<script>alert('The provided file is not a valid CSV format.Check the delimiter');</script>";
                print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
                exit;
            }
            
         }
 

        #**********************
            ################################
              // Second step: displaying selected columns
        
       // $csvData = $_SESSION['csv_data'];
       $delimiter = $delimiter ?? ';';
        $lines = explode(PHP_EOL, $csvData);
        $rows = array_map(function ($line) use ($delimiter) {
            return str_getcsv($line, $delimiter);
        }, $lines);

        $header = $rows[0];
        unset($rows[0]); // Remove header from rows

        print "<h2>Selected Data</h2>";
        print "<form name='diverse' action='diverse.php?sektion=pricelists' method='post'>\n";
        print "<input type='hidden' name='step' value='save_data'>";
        
        // Create a table to display the data
        print "<table border='1'>";
        
        // Add a header row with a checkbox for selecting/deselecting all
        print "<tr>";
       
       
       // Generating table headers with select options
        foreach ($header as $column) {
            $column = mb_convert_encoding($column, 'UTF-8', 'ISO-8859-1');
            print "<th>";
            print '<select name="columnSelect[]">'; // Using array syntax to allow multiple selections if needed

            // Setting the default option to the current column
            print '<option value="' . htmlspecialchars($column) . '" selected>' . htmlspecialchars($column) . '</option>'; 

            // Adding the rest of the options from the header array
            foreach ($header as $option) {
                $option = mb_convert_encoding($option, 'UTF-8', 'ISO-8859-1');
                // Avoid adding the current column again to prevent duplication in the dropdown
                if ($option !== $column) {
                    print '<option value="' . htmlspecialchars($option) . '">' . htmlspecialchars($option) . '</option>'; 
                }
            }

            print '</select>';
            print "</th>";
        }
        print "</tr>";
        
        // Add data rows with checkboxes
       

        $rowCount = 0; // Initialize a counter for the number of rows shown

        foreach ($rows as $row) {
            if (!empty($row)) {
                $row = mb_convert_encoding($row, 'UTF-8', 'ISO-8859-1');

                // Check if we have already printed 10 rows
                if ($rowCount >= 10) {
                    break; // Exit the loop if 10 rows have been printed
                }

                print "<tr>"; // Start a new table row

                // Render the data cells for the selected columns
                foreach ($header as $column) {
                    $colIndex = array_search($column, $header); // Get the index of the current column
                    print "<td>" . htmlspecialchars($row[$colIndex] ?? '') . "</td>"; // Print the cell value
                }

                print "</tr>"; // End the table row
                $rowCount++; // Increment the row counter after printing the row
            }
        }
                
        print "</table>";
        print "<button type='submit'>Save Data</button>";
        echo "</form>";
        
        
            #################################






            return; // Stop further processing after handling new pricelist form
        }
    }elseif(isset($_POST['step']) && $_POST['step'] === 'save_data'){
        print "<script>alert('To be saved.');</script>";
        print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        ##############Sample varer################
        // Build the full URL including protocol (http or https), host, and request URI
        $fullUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $fullUrl .= "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        // Output the full URL
        $pos = strpos($fullUrl, 'systemdata/');

        if ($pos !== false) {
            // Keep everything up to and including 'systemdata/'
            $basePath = substr($fullUrl, 0, $pos + strlen('systemdata/'));

            $sampleVarer = $basePath.'sample_varer.csv';
        }
        ############################



 //**************** begin  add list form */
                // Add Pricelist button
               

                print '<table style="width: 100%; border-collapse: collapse;">';
                print '<tr>
                            <td colspan="2">
                                <button id="addPricelistBtn" type="button" onclick="toggleAddForm()" style="color: blue; border: 2; padding: 10px 15px; cursor: pointer;">Add Pricelist</button>
                            </td>
                        </tr>';
                print '<tr id="add-form" style="display:none; margin-top: 20px;">
                            <td colspan="2">
                                <h3>Add New Pricelist</h3>
                                <form name="addForm" action="diverse.php?sektion=pricelists" method="post" enctype="multipart/form-data">
                                    <table style="width: 100%; border-collapse: collapse;">';
                print '<tr>
                            <td><label>Description:</label></td>
                            <td><input type="text" name="new_beskrivelse"></td>
                        </tr>';
                print '<tr>
                            <td><label>URL:</label></td>
                            <td><input type="text" name="csv_url"></td>
                        </tr>';
                print '<tr>
                            <td><label>Delimiter:</label></td>
                            <td>
                                <select name="new_delimiter">
                                    <option value=";">Semicolon</option>
                                    <option value=",">Comma</option>
                                    <option value="\t">Tab</option>
                                </select>
                            </td>
                        </tr>';
                print '<tr>
                            <td><label>Encoding:</label></td>
                            <td>
                                <select name="new_encoding">
                                    <option value="utf-8">UTF-8</option>
                                    <option value="iso-8859">ISO-8859</option>
                                </select>
                            </td>
                        </tr>';
                        print '<tr>
                        <td><label>Product Group:</label></td>
                        <td>
                            <select name="new_product_group">';
                            
                            foreach ($beskrivelse1 as $option) {
                                print '<option value="' . htmlspecialchars($option) . '">' . htmlspecialchars($option) . '</option>';
                            }
                        print '    </select>
                                    </td>
                                </tr>';
               
                print '<tr style="display: none;">
                            <td colspan="2">
                                <input type="hidden" name="new_pricelist" value="1">
                            </td>
                        </tr>';
                print '<tr>
                            <td colspan="2">
                                <button type="submit" name="add_submit" style="margin-top: 10px; margin-bottom: 20px; color:blue;">Save New Pricelist</button>
                            </td>
                            <td colspan="2">
                                <button type="button" onclick="window.location.href=\'diverse.php?sektion=pricelists\';" style="margin-top: 10px; margin-bottom: 20px; color: blue;">Cancel</button>
                            </td>
                        </tr>';
                print '    </table>
                                </form>
                            </td>
                        </tr>';
                print '</table>';
    
            //**************** end of add list */
    
    
    // Displaying the forms
    print '
        <form id="formEd" name="editForm" action="diverse.php?sektion=pricelists" method="post" enctype="multipart/form-data">
    ';

    print '<table style="width: 100%; margin-top:20px; name: outer-table; border-collapse: collapse;">';
    print '<hr>';
    print '</hr>';
    print '<tr>';
    print "<th style='text-align: left;'>" . findtekst(914, $sprog_id) . "</th>";
    print "<th style='text-align: left;'></th>";
    print '</tr>';

    for ($x = 0; $x < count($id); $x++) {
        if(empty($prisfil[$x])){
            $prisfil[$x]=$sampleVarer;
        }
        print '<tr>';
        print "<input type='hidden' name='id[$x]' value='" . htmlspecialchars($id[$x]) . "'>\n";
        print "<input type='hidden' name='beskrivelse[$x]' value='" . htmlspecialchars($beskrivelse[$x]) . "'>\n";
        print '<td>' . htmlspecialchars($beskrivelse[$x]) . '</td>';

        // Edit button with a data-index attribute for row identification
        print '<td><button type="button" onclick="toggleEditForm(' . $x . ')" style="margin-left: 10px; background-color: blue; color: white; border: none; padding: 5px 10px; cursor: pointer;">Edit</button></td>';

        print '</tr>';

        // Hidden edit form for this row
        print '<tr id="edit-form-' . $x . '" style="display:none;">';
        print '<td colspan="3">';

        // Create a table for the edit form
        print '<table style="width: 100%; border-collapse: collapse;">';

        // Row for 'beskrivelse'
        print '<tr>';
        print '<td><strong>Beskrivelse:</strong></td>';
        print '<td><input type="text" name="edit_beskrivelse[' . $x . ']" value="' . htmlspecialchars($beskrivelse[$x]) . '"></td>';
        print '</tr>';

        // Row for 'prisfil'
        print '<tr>';
        print '<td><strong>Prisfil:</strong></td>';
       
        print '<td><input type="text" name="edit_prisfil[' . $x . ']" value="' . htmlspecialchars($prisfil[$x]) . '"></td>';
        
        print '</tr>';
        
        // Row for 'delimiter'
        $delimiterE = ['Semicolon'=>';', 'Comma'=>',', 'Tab'=>'\t'];
        $selectedDelimiter = htmlspecialchars($delimiter[$x]); 
        print '<tr>
                <td><label>Delimiter:</label></td>
                <td>
                    <select name="edit_delimiter">';

            // Check if the selected delimiter exists in $delimiterE
            if (in_array($selectedDelimiter, $delimiterE)) {
                // If the selected delimiter exists, show it as the first option
                $selectedKey = array_search($selectedDelimiter, $delimiterE); // Find the key for the selected value
                print "<option value=\"$selectedDelimiter\" selected>$selectedKey</option>";  
            } else {
               
                print '<option value=""></option>';
            }

            
            foreach ($delimiterE as $key => $value) {
                // Skip the selected delimiter if it's already shown as the first option
                if ($value !== $selectedDelimiter) {
                    $selected = ($value === $selectedDelimiter) ? 'selected' : ''; 
                    print "<option value=\"$value\" $selected>$key</option>";
                }
            }

            print '</select>
                </td>
            </tr>';
        //end delimiter row
    
        print '<tr>';
        print '<td><strong>Gruppe:</strong></td>';
        print '<td><select name="edit_gruppe[' . $x . ']">';
        $selectedGruppe = htmlspecialchars($gruppe[$x]);
       
        if (!empty($selectedGruppe)) {
            
            print "<option value=\"$selectedGruppe\" selected>$selectedGruppe</option>";
        } else {
           
            print '<option value="">...</option>';
        }
        
        foreach ($beskrivelse1 as $group) {
            // Skip the selected group if it already exists in the options
            if ($group !== $selectedGruppe) {
                print "<option value=\"$group\">$group</option>";
            }
        }
        
        print '</select></td>';
        print '</tr>';

        // Row for 'encoding'
        $encodingE = ['UTF'=>'UTF-8', 'ISO'=>'ISO-8859-1'];
     
        $selectedEncoding = htmlspecialchars($encoding[$x]); // Get the value (e.g., 'UTF-8', 'ISO-8859-1')

        // Start the form row for encoding
        print '<tr>';
        print '<td><strong>Encoding:</strong></td>';
        print '<td><select name="edit_encoding[' . $x . ']">';
        
        // Check if the selected encoding exists in the $encodingE array
        if (in_array($selectedEncoding, $encodingE)) {
            
            print "<option value=\"$selectedEncoding\" selected>$selectedEncoding</option>";
        } elseif (isset($selectedEncoding) && $selectedEncoding !== "") {
            
            print "<option value=\"$selectedEncoding\" selected>$selectedEncoding</option>";
        } else {
           
            print '<option value="">...</option>';
        }
        
        // Loop through the $encodingE array and display the remaining options
        foreach ($encodingE as $key => $value) {
            if ($value !== $selectedEncoding) {
                print "<option value=\"$value\">$value</option>";
            }
        }
        
        print '</select></td>';
        print '</tr>';
        


        #///////////////////////////////////
        if (!empty($prisfil[$x])) {
            // $url = 'https://'.$prisfil[$x];
           
            $url = (str_starts_with($prisfil[$x], 'http')) ? $prisfil[$x] : 'https://' . $prisfil[$x];

            $delimiter = $delimiter[$x] ?? ';';
            
            $csv_url = filter_var($url, FILTER_SANITIZE_URL);

            if (!filter_var($url, FILTER_VALIDATE_URL)) {  
                print "<script>alert('Invalid URL format.');</script>";
                
            print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
                exit;
            }
            
            $csvData = @file_get_contents($csv_url);
        #  echo $csv_url;
            if ($csvData === FALSE) {
            
                print "<script>alert('Unable to fetch the CSV file. Please check the URL.edit');</script>";
            
                // print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";

                // exit;
                editForm($x);


            }
            
        } else {
            print "<script>alert('Please provide a CSV file URL or upload a CSV file.');</script>";
            
            editForm($x);
        
        }

         // Parse CSV data to get the header row
         $lines = explode(PHP_EOL, trim($csvData));
         $header = str_getcsv(trim($lines[0] ?? ''), $delimiter);
 
         if (!$header || count($header) < 2) {
                if($delimiter != ';'){
                    $delimiter = ';';
                }else{
                    print "<script>alert('The provided file is not a valid CSV format.Check the delimiter');</script>";
                   editForm($x);
                
                }
                
         }
 
        #**********************

            ################################
             
        $rows = array_map(function ($line) use ($delimiter) {
            return str_getcsv($line, $delimiter);
        }, $lines);

        $header = $rows[0];
        unset($rows[0]); // Remove header from rows

        print "<input type='hidden' name='step' value='save_data'>";
        
        // Create a table to display the data
        print "<table border='1'>";
        
        // Add a header row with a checkbox for selecting/deselecting all
        print "<tr>";
       
       // Generating table headers with select options
        foreach ($header as $column) {
            $column = mb_convert_encoding($column, 'UTF-8', 'ISO-8859-1');
            print "<th>";
            print '<select name="columnSelect[]">'; // Using array syntax to allow multiple selections if needed

            // Setting the default option to the current column
            print '<option value="' . htmlspecialchars($column) . '" selected>' . htmlspecialchars($column) . '</option>'; 

            // Adding the rest of the options from the header array
            foreach ($header as $option) {
                $option = mb_convert_encoding($option, 'UTF-8', 'ISO-8859-1');
                // Avoid adding the current column again to prevent duplication in the dropdown
                if ($option !== $column) {
                    print '<option value="' . htmlspecialchars($option) . '">' . htmlspecialchars($option) . '</option>'; 
                }
            }

            print '</select>';
            print "</th>";
        }
        print "</tr>";
        
        // Add data rows with checkboxes
       

        $rowCount = 0; // Initialize a counter for the number of rows shown

        foreach ($rows as $row) {
            if (!empty($row)) {
                $row = mb_convert_encoding($row, 'UTF-8', 'ISO-8859-1');

                // Check if we have already printed 10 rows
                if ($rowCount >= 10) {
                    break; // Exit the loop if 10 rows have been printed
                }

                print "<tr>"; // Start a new table row

                // Render the data cells for the selected columns
                foreach ($header as $column) {
                    $colIndex = array_search($column, $header); // Get the index of the current column
                    print "<td>" . htmlspecialchars($row[$colIndex] ?? '') . "</td>"; // Print the cell value
                }

                print "</tr>"; // End the table row
                $rowCount++; // Increment the row counter after printing the row
            }
        }
                
        print "</table>"; //End of form handling csv data
        print '<button type="submit" name="edit_submit" style="margin-left: 10px;">Save</button>'; 
        #///////////////////////////////////

        print '</table>'; // Close the edit form table

       // Add name to the button
        print '</td>';
        print '</tr>';
    }
    print '</table>';
    // JavaScript to toggle the visibility of the edit form and add form
    print '
    <script>
        function toggleEditForm(index) {
            var editForm = document.getElementById("edit-form-" + index);
              document.getElementById("addPricelistBtn").style.display = "none";
            if (editForm.style.display === "none") {
                editForm.style.display = "table-row";
            } else {
                editForm.style.display = "none";
            }
        }

        function toggleAddForm() {
            var addForm = document.getElementById("add-form");
             document.getElementById("addPricelistBtn").style.display = "none";
             document.getElementById("formEd").style.display = "none";
            if (addForm.style.display === "none") {
                addForm.style.display = "block";
            } else {
                addForm.style.display = "none";
            }
        }
    </script>
    ';
    return;
}
}
	# endfunc pricelists
?>







