<?php
#session_start();
function pricelists(){


   
	global $bgcolor, $bgcolor5;
	global $regnaar;
	global $sprog_id;

	$id = $filtyper = $filtypebeskrivelse = $lev_id = $prislister = array();
	$i=0;
	$q=db_select("select * from grupper where art = 'PL' order by beskrivelse",__FILE__ . " linje " . __LINE__);
   
	while ($r = db_fetch_array($q)) {
		$id[$i]=$r['id'];
		$beskrivelse[$i]=$r['beskrivelse']; //holds names of the records
		$prisfil[$i]=$r['box2']; //file location ? //the url
		$aktiv[$i]=$r['box4'];
		$gruppe1[$i]=$r['box8'];  //selected group
		$supplier[$i]=$r['box9'];
        $delimiter[$i] = $r['box10'];
        $encoding[$i]=$r['box11'];
		$i++;
	}
	$vgrp = array();
	$i = 0;
	// $qtxt = "select * from grupper where art = 'VG' and fiscal_year = '$regnaar' order by kodenr";
    $qtxt = "select * from grupper where art = 'VG' and fiscal_year = '$regnaar' order by beskrivelse";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$vgrp[$i]   = $r['kodenr'];
		$vgbesk[$i] = $r['beskrivelse']; //list of groups
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
	$qtxt = "select id,kontonr,firmanavn from adresser where art = 'K' and lukket != 'on' order by firmanavn";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {	
			$supplId[$i]      = $r['id'];
			$supplAccout[$i]  = $r['kontonr'];
			$supplCompany[$i] = $r['firmanavn'];
			$i++;
		
	}

    $k=0;
    $qtxt = "select * from grupper where art = 'VG' and fiscal_year = '$regnaar' order by kodenr";
	$q1 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
    while ($row = db_fetch_array($q1)){
       
        $beskrivelse1[$k]=$row['beskrivelse']; //gruppe
        $k++;
    }



 //e.g  https://saldi.dk/Buchberg/varer.csv
 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedUrl = $_POST['use_url'] ?? null;
    $deleteUrl = $_POST['delete_url'] ?? null; 
    // Handling submitted data for editing existing entries
        if($selectedUrl || $deleteUrl){
            if ($deleteUrl) {
                echo "<script>alert('Select to delete URL: $deleteUrl');</script>";
                
                $qtxt = "delete from grupper where box2='$deleteUrl'";
                db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                print "<script>alert('Deleted.');</script>";
                print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
                return;
            }else{
                // Fetch all groups again (same as before)
            $q = db_select("SELECT * FROM grupper WHERE art = 'PL' ORDER BY beskrivelse", __FILE__ . " linje " . __LINE__);

            while ($r = db_fetch_array($q)) {
                $id = $r['id'];
                $url = $r['box2']; // same as $prisfil
                $isActive = $r['box4']; // same as $aktiv
               
                if ($url === $selectedUrl) {
                    // Set the selected URL as active
                    db_modify("UPDATE grupper SET box4 = 'Yes' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
                } else if ($isActive === 'Yes') {
                    // Deactivate all other records that were previously active
                    db_modify("UPDATE grupper SET box4 = '' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
                }
            }

        }
         echo "<script>alert('Operation completed');</script>";
         print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
        return;
        
    }
   
    if (isset($_POST['edit_submit'])) { // Check for edit form submission
       
        if (isset($_POST['edit_beskrivelse']) && isset($_POST['edit_prisfil'])) {
            foreach ($_POST['id'] as $i => $id) {
                $beskrivelse = $_POST['beskrivelse'][$i];
                $prisfil = $_POST['prisfil'][$i];
                $opdateret = $_POST['opdateret'][$i];
                $aktiv = $_POST['aktiv'][$i];
                $gruppe = $_POST['gruppe'][$i];
                $filtype = $_POST['filtype'][$i];
                $delimiter = $_POST['delimiter'][$i];
                $encoding = $_POST['encoding'][$i];
                $supplier = $_POST['suppliergroup'][$i];
               
                
                
                $beskrivelse = htmlspecialchars($beskrivelse);
                $prisfil = htmlspecialchars($prisfil);
                $delimiter = htmlspecialchars($delimiter);
                $gruppe = htmlspecialchars($gruppe);
                $encoding= htmlspecialchars($encoding);
                $id = htmlspecialchars($id);
                $aktiv = htmlspecialchars($aktiv);
                $path_info = pathinfo($prisfil);
                $supplier = htmlspecialchars($supplier);

               
                switch ($delimiter) {
                    case 'tab':
                        $delimiter = '\\t';
                        break;
                    case ',':
                    case ';':
                        $delimiter = $delimiter;
                        break;
                    default:
                        $delimiter = null;
                }



                 $checkDelimiter = [',', ';', '\\t'];
                 
                    $valid = false;
                    
                  if (in_array($delimiter, $checkDelimiter, true)){
                        $valid = true;
                        if($delimiter == '\\t'){
                            $delimiter = "\t";
                        }
                        
                    }else{
                        $valid = false;
                        error_log("Invalid delimiter: $delimiter");
                    }
                if (isset($path_info['extension']) && strtolower($path_info['extension']) === 'csv' && $valid) {
                    $mode=true;
                }else{
                    $mode=false;
                }
                
              if($mode && !empty($beskrivelse)){
             
                    $qtxt = "update grupper set box10='$delimiter',box9='$supplier', box8='$gruppe',beskrivelse='$beskrivelse',";  
                    $qtxt.="box11='$encoding',box2='$prisfil' where id='$id'";
                    

                    db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                    
                   
              }else{
                    
                    print "<script>alert('Ensure the url/description/delimiter are valid.'); 
                    window.location.href = 'diverse.php?sektion=pricelists';</script>";
                    exit;
              }
            
            }
            print "<script>alert('Updated.');</script>";
            print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
            return; // Stop further processing after handling edit form
        }else{

          $showsave = true;
          
            print("<form name='editformn' action='diverse.php?sektion=pricelists' method='post'>\n");
            print('<table border="1" cellpadding="5" cellspacing="0">');
            print('<tr>
                <th>Beskrivelse</th>
                <th>Prisfil</th>
                <th>Opdateret</th>
                <th>Aktiv</th>
                <th>Gruppe</th>
                <th>Supplier Group</th>
                <th>Delimiter</th>
                <th>Encoding</th>
            </tr>');

            // Check if data exists in the arrays
            if (!empty($id) && count($id) > 0) {
                // Loop through each record and output as editable row in the form
                for ($i = 0; $i < count($id); $i++) {
                    print('<tr>');
                    print('<input type="hidden" name="id[]" value="' . $id[$i] . '">');
                    // Editable inputs for each of the values
                    print('<td><input type="text" name="beskrivelse[]" value="' . (isset($beskrivelse[$i]) ? htmlspecialchars($beskrivelse[$i]) : '') . '"></td>');
                    print('<td><input type="text" name="prisfil[]" value="' . (isset($prisfil[$i]) ? htmlspecialchars($prisfil[$i]) : '') . '"></td>');
                    print('<td><input type="text" name="opdateret[]" value="' . (isset($opdateret[$i]) ? htmlspecialchars($opdateret[$i]) : '') . '"></td>');
                    print('<td><input type="text" name="aktiv[]" value="' . (isset($aktiv[$i]) ? htmlspecialchars($aktiv[$i]) : '') . '"></td>');
                    print '<td><select name="gruppe[]">';
                        if (empty($gruppe1[$i])) {
                            print '<option value="">-- Select Group --</option>';
                        }
                        foreach ($vgbesk as $value) {
                            $selected = (isset($gruppe1[$i]) && $gruppe1[$i] === $value) ? 'selected' : '';
                            print '<option value="' . htmlspecialchars($value) . '" ' . $selected . '>' . htmlspecialchars($value) . '</option>';
                        }
                    print '</select></td>';
                    // Supplier Group
                    print '<td><select name="suppliergroup[]">';
                    if (empty($supplier[$i])) {
                        print '<option value="">-- Select Supplier --</option>';
                    }
                    foreach ($supplCompany as $company) {
                        $selected = (isset($supplier[$i]) && $supplier[$i] === $company) ? 'selected' : '';
                        print '<option value="' . htmlspecialchars($company) . '" ' . $selected . '>' . htmlspecialchars($company) . '</option>';
                    }
                    print '</select></td>';

                    ##Delimiter Start
                  // Get the selected delimiter from the database (or empty string if not set)
                  
                    $selected = isset($delimiter[$i]) ? $delimiter[$i] : ','; // Default to comma if not set

                    print('<td><select name="delimiter[]">');

                    $options = [
                        ','     => 'Comma',
                        ';'     => 'Semicolon',
                        'tab'   => 'Tab' // Use symbolic value for tab
                    ];

                    // Normalize the selected value for comparison (handle tab case)
                    $selected = ($selected === "\t") ? 'tab' : $selected;

                    foreach ($options as $value => $label) {
                        $isSelected = ($value === $selected) ? ' selected' : '';
                        print("<option value=\"$value\"$isSelected>$label</option>");
                    }

                    print('</select></td>');
                    ###Delimiter end select

                    ##Encoding Start
                    print '<td><select name="encoding[]">';
                    if (empty($encoding[$i])) {
                        print '<option value="">-- Select Encoding --</option>';
                    }
                    $encodings = ['utf-8' => 'UTF-8', 'iso-8859' => 'ISO-8859'];
                    foreach ($encodings as $value => $label) {
                        $selected = (isset($encoding[$i]) && $encoding[$i] === $value) ? 'selected' : '';
                        print '<option value="' . htmlspecialchars($value) . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
                    }
                    print '</select></td>';
                    ##Encoding end select
           
                    print ("<input type='hidden' name='edit_beskrivelse' value='edit_beskrivelse'>");
                    print ("<input type='hidden' name='edit_prisfil' value='edit_prisfil'>");
                    print('</tr>');
                }
            } else {
                $showsave=false;
                print('<tr><td colspan="10">No records found in the database. Please add new records.</td></tr>');
                print "<meta http-equiv=\"refresh\" content=\"2;url=diverse.php?sektion=pricelists\">";
                exit;
            }

            if ($showsave == true) {
                 print('</table><br>');
                print '<input type="submit" name="edit_submit" value="Save Changes" style="padding: 6px 12px; background-color:#ccc; color: black; text-decoration: none; border-radius: 0px; cursor: pointer; border: none;">';
                print '<a href="diverse.php?sektion=pricelists" style="margin-left: 10px; padding: 6px 12px; background-color:#ccc; color: black; text-decoration: none; border-radius: 0px; display: inline-block; cursor: pointer; border: none;">Cancel</a>';
            } else {
                print '<a href="diverse.php?sektion=pricelists">Cancel</a>';
            }

            print('</form>');
        }
    }elseif (isset($_POST['add_submit'])) { // Check for add form submission
        if (isset($_POST['new_beskrivelse']) && isset($_POST['csv_url'])) {
            $newDescription = htmlspecialchars($_POST['new_beskrivelse']);
            $newUrl = htmlspecialchars($_POST['csv_url']);
            $delimiter = htmlspecialchars($_POST['new_delimiter']);
            $newEncoding = htmlspecialchars($_POST['new_encoding']);
            $newProductGroup = htmlspecialchars($_POST['new_product_group']);
            $newSupplierGroup = htmlspecialchars($_POST['new_supplier_group']);
            $mode = htmlspecialchars($_POST['mode']);
          
            
           

        #****************************
        // Check if a URL was provided
            if (!empty($_POST['csv_url'])) {
            
            $csv_url = filter_var($_POST['csv_url'], FILTER_SANITIZE_URL);
            if (!filter_var($csv_url, FILTER_VALIDATE_URL)) {
                $mode=FALSE;
                print "<script>alert('Invalid URL format.');</script>";
                echo "<script>alert('Invalid URL format.'); window.location.href = 'diverse.php?sektion=pricelists';</script>";
                exit;

            }
            $path_info = pathinfo($csv_url);
            if (isset($path_info['extension']) && strtolower($path_info['extension']) === 'csv') {
                $mode=true;
            }
                    
            $csvData = @file_get_contents($csv_url);
            if ($csvData === FALSE || $mode==FALSE) {
            
                print "<script>alert('Unable to fetch the CSV file1. Please check the URL.');</script>";
            
                echo "<script>alert('Invalid URL format.'); window.location.href = 'diverse.php?sektion=pricelists';</script>";
                exit;

            }else{
                 #######################################
                 $selection= db_select("select * from grupper where box2='$newUrl' and art='PL'",__FILE__ . " linje " . __LINE__);
                 if(db_fetch_array($selection)){
                    echo "<script>alert('The URL already exists.'); window.location.href = 'diverse.php?sektion=pricelists';</script>";
                    exit;
                 }else{
                    $qtxt = "insert into grupper(box2,box10,box11,beskrivelse,box8,art,box9) values ";
                    $qtxt.= "('$newUrl','$delimiter','$newEncoding','$newDescription','$newProductGroup','PL','$newSupplierGroup')";
                    $saved = db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                }

                #######################################
            }
            
        } else {
            $mode=false;
            print "<script>alert('Please provide a CSV file URL or upload a CSV file.');</script>";
             print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
            exit;
        }

         
        
           
            print "<script>alert('Saved');</script>";
            print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
            return; 
        }
    }elseif(isset($_POST['step']) && $_POST['step'] === 'save_data'){
     
        error_log('use for edit');
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


            #+++++++++++++++
            // Function for the Add Pricelist form
            function renderAddPricelistForm($group,$supplier) {
                print '<table style="width: 100%; border-collapse: collapse;">';
		     print '<tr>
                            <td colspan="2">
                                <button id="addPricelistBtn" type="button" onclick="toggleAddForm()" "></button>
                            </td>
                        </tr>';
                print '<tr id="add-form" style="display:none; margin-top: 20px;">
                        <td colspan="2">
                            <h2>Add New Pricelist URL</h2>
                            <h3>Sample pricelist: <a href="https://saldi.dk/Buchberg/varer.csv" target="_blank">https://saldi.dk/Buchberg/varer.csv</a></h3>
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
                
                foreach ($group as $option) {
                    print '<option value="' . htmlspecialchars($option) . '">' . htmlspecialchars($option) . '</option>';
                }
                print '        </select>
                            </td>
                        </tr>';
                        
                print '<tr style="display: none;">
                            <td colspan="2">
                                <input type="hidden" name="new_pricelist" value="1">
                            </td>
                        </tr>';
                        print '<tr>
                            <td><label>Supplier Group:</label></td>
                            <td>
                                <select name="new_supplier_group">';
                
                foreach ($supplier as $supp) {
                    print '<option value="' . htmlspecialchars($supp) . '">' . htmlspecialchars($supp) . '</option>';
                }

                print '        </select>
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

                print '</table>
                        </form>
                    </td>
                </tr>';

                print '</table>';
            }
            #++++++++++end of add pricelist func

            // Begin main page output
           print '<tr><td colspan="2"><h2>📦 Price List Files</h2></td></tr>';

            print '<tr><td colspan="2" style="background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd;">';
                print '<form method="POST" action="">';

            if (!empty($prisfil) && is_array($prisfil) && is_array($beskrivelse)) {
                print '<tr><td colspan="2" style="background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd;">';
                print '<form method="POST" action="">';

                $count = min(count($prisfil), count($beskrivelse)); // Safeguard if arrays differ in length

                for ($i = 0; $i < $count; $i++) {
                    $url = htmlspecialchars($prisfil[$i]);
                    $desc = htmlspecialchars($beskrivelse[$i]);
                    $use = htmlspecialchars($aktiv[$i]);

                    print '<div class="pricelist" style="margin-bottom: 10px; display: flex; align-items: center; background-color: #fff; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">';
                    
                    print "<span style=\"flex-grow: 1; font-family: sans-serif; color: #333;\">$url</span>";
                    print "<span style=\"flex-grow: 1; font-family: sans-serif; color: #666;\">$desc</span>";
                    
                    // Use button
                   if($use == 'Yes'){
                        print "<button type=\"submit\" name=\"use_url\" value=\"$url\" style=\"padding: 6px 12px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;\">Use</button>";  
                    }else{
                        print "<button type=\"submit\" name=\"use_url\" value=\"$url\" style=\"padding: 6px 12px; background-color: #ccc; color: black; border: none; border-radius: 4px; cursor: pointer;\">Use</button>";
                    }

                    // Delete button
                    print "<button type=\"submit\" name=\"delete_url\" value=\"$url\" style=\"padding: 6px 12px; background-color: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer;\">Delete</button>";
                    
                    print '</div>';
                }

                print '</form>';
                print '</td></tr>';
            }



                print '</form>';
                print '</td></tr>';


           

            print '<tr><td colspan="2">';
            print '<div style="display: flex; gap: 10px;">';

            
            print "<form name='diverse' action='diverse.php?sektion=pricelists' method='post'>\n";
            print "<input type='hidden' name='edit_submit' value='edit_submit'>";
            print '<button type="submit">Edit Pricelists</button>';
            print '</form>';

            print '<form method="get" action="">'; // reload page or trigger JS
            print '<button type="button" onclick="toggleAddForm()">Add New Pricelist</button>';
            print '</form>';

            print '</div>';
            print '</td></tr>';
            // Show add form below the buttons
            renderAddPricelistForm($beskrivelse1,$supplCompany);

            print '<script>
                function toggleAddForm() {
                    var formRow = document.getElementById("add-form");
                    if (formRow.style.display === "none") {
                        formRow.style.display = "table-row";
                    } else {
                        formRow.style.display = "none";
                    }
                }
            </script>';

            #+++++++++++++++

    // Displaying the forms
    print '
        <form id="formEd" name="editForm" ">
    ';
    #print '<h3>Sample pricelist: <a href="https://saldi.dk/Buchberg/varer.csv" target="_blank">https://saldi.dk/Buchberg/varer.csv</a></h3>';
    print '<table style="width: 10%; margin-top:20px; name: outer-table; border-collapse: collapse;">';
   
    print '<tr>';
    #print "<th style='text-align: left;'>" . findtekst(914, $sprog_id) . "</th>";
    print "<th style='text-align: left;'></th>";
    print '</tr>';

    for ($x = 0; $x < count($id); $x++) {
        if(empty($prisfil[$x])){
            $prisfil[$x]=$sampleVarer;
        }
        print '<tr>'; 
 
        #**********************
        
        print '</select></td>';
        print '</tr>';


        #///////////////////////////////////
        if (!empty($prisfil[$x])) {
            // $url = 'https://'.$prisfil[$x];
           
            $url = (str_starts_with($prisfil[$x], 'http')) ? $prisfil[$x] : 'https://' . $prisfil[$x];

            $delimiter2 = htmlspecialchars($delimiter[$x]);
            $delimiter2 = $delimiter[$x] ?? ';';
            $csv_url = filter_var($url, FILTER_SANITIZE_URL);

            if (!filter_var($url, FILTER_VALIDATE_URL)) {  
               print "<script>alert('Invalid URL format.');</script>";
                    db_modify("delete from grupper where id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
                
                    print "<meta http-equiv=\"refresh\" content=\"0;url=diverse.php?sektion=pricelists\">";
                exit;
            }
            
            $csvData = @file_get_contents($csv_url);
        #  echo $csv_url;
            if ($csvData === FALSE) {
            
                print "<script>alert('Unable to fetch the CSV file. Please check the URL.edit');</script>";
               # editForm($x);
                exit;
            }
            
        } else {
            print "<script>alert('Please provide a CSV file URL or upload a CSV file.');</script>";  
           # editForm($x);
        }

         // Parse CSV data to get the header row
          $lines = explode(PHP_EOL, trim($csvData));
            $firstLine = trim($lines[0] ?? '');

            $checkDelimiter = [',', ';', "\t"];
            $valid = false;
           if(!in_array($delimiter2[$x], $checkDelimiter)){
                $valid = false;
            }
            
        if (!$valid) {
            error_log('The provided file is not a valid CSV format. Check the delimiter.');
            
          #  exit;
        }
      
        #**********************
        // Create a table to display the data
       
        ################################
         print "<table border='1' >";
              // display Chosen pricelist before use.
        if($aktiv[$x] == 'Yes'){ 
             $delimiter = $delimiter[$x] ?? ';';
        $lines = explode(PHP_EOL, $csvData);
        $lines = explode(PHP_EOL, trim($csvData));
		$rows = array_map(fn($line) => str_getcsv($line, $delimiter), $lines);
            $header = $rows[0];
            unset($rows[0]); // Remove header from rows
                    print "<h2>Selected Pricelist Structure</h2>";
                    print "<input type='hidden' name='step' value='save_data'>";
                    print "<input type='hidden' name='mode' value=$mode>";
                    // Create a table to display the data
                   print "<table border='1'>";
                    // Header row
                    print "<tr>";
                    foreach ($header as $column) {
                        $column = mb_convert_encoding($column, 'UTF-8', 'ISO-8859-1');
                        print "<th>" . htmlspecialchars($column) . "</th>";
                    }
                    print "</tr>";
                    // Data rows
                    $rowCount = 0;
                    foreach ($rows as $row) {
                        if (!empty($row)) {
                            $row = mb_convert_encoding($row, 'UTF-8', 'ISO-8859-1');

                            if ($rowCount >= 50) {
                                break;
                            }
                            print "<tr>";
                            foreach ($header as $column) {
                                $colIndex = array_search($column, $header);
                                print "<td>" . htmlspecialchars($row[$colIndex] ?? '') . "</td>";
                            }
                            print "</tr>";
                            $rowCount++;
                        }
                    }
                    print "</table>";
                   
            #################################  
        }  

        print '</table>'; // Close the edit form table
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







