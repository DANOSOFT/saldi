<?php

/**
 * Take in a product list and compare it against a database of products
 * to align product varenr and kostpris.
 */

@session_start();
$s_id=session_id();

include "../includes/connect.php";
include "../includes/online.php";

/* 
 * take in csv file with these headers
 * "varenr";"stregkode";"varem�rke";"beskrivelse";"kostpris";"salgspris";"vejl_pris";"notes";"enhed";"udg�et";"gruppe";"min_lager";"max_lager";"lokation"
 * all we care about is to check weather varenr exists in the database and if so, update kostpris and add old varenr as varenr_alias
 * kostpris should be Kostpris = (kostpris * antal + csv_kostpris * csv_antal) / (antal + csv_antal)
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['product_file'])) {
	$file = $_FILES['product_file']['tmp_name'];
	
	if (($handle = fopen($file, 'r')) !== FALSE) {
		$header = fgetcsv($handle, 1000, ';');
		$varenr_index = array_search('varenr', $header);
		$kostpris_index = array_search('kostpris', $header);
		$beskrivelse_index = array_search('beskrivelse', $header);
		$enhed_index = array_search('enhed', $header);
		$gruppe_index = array_search('gruppe', $header);
		$min_lager_index = array_search('min_lager', $header);
		$max_lager_index = array_search('max_lager', $header);
		$lokation_index = array_search('lokation', $header);
		$udgået_index = array_search('udgået', $header);
		$varenr_alias_index = array_search('varenr_alias', $header);
		$notes_index = array_search('notes', $header);
		$stregkode_index = array_search('stregkode', $header);
		$varemærke_index = array_search('varemærke', $header);
		$salgspris_index = array_search('salgspris', $header);
		$lager_antal_index = array_search('lager_antal', $header);
		$beskrivelse_alias_index = array_search('beskrivelse_alias', $header);

		if ($varenr_index === false || $kostpris_index === false) {
			die("CSV file must contain 'varenr' and 'kostpris' columns.");
		}
		
		while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
			$varenr = trim($data[$varenr_index]);
			$varenr_alias = isset($data[$varenr_alias_index]) ? db_escape_string(trim($data[$varenr_alias_index])) : '';
			// Convert Danish price format to SQL numeric (e.g. "3.267,00" -> "3267.00")
			$kostpris_raw = trim($data[$kostpris_index]); // Fix: use kostpris_raw first
			$kostpris = str_replace('.', '', $kostpris_raw); // Remove thousand separators
			$kostpris = str_replace(',', '.', $kostpris);    // Convert decimal comma to dot
			$lager_antal = trim($data[$lager_antal_index]);
			$old_lager_antal = 0;
			
			// Check if varenr exists in the database
			$sql = "SELECT id, kostpris FROM varer WHERE varenr = '$varenr_alias'";
			$result = db_select($sql, __FILE__ . " line " . __LINE__);
			
			if (db_num_rows($result) > 0) {
				$row = db_fetch_array($result);
				$vare_id = $row['id']; // Fix: get the vare_id from the result
				$old_kostpris = $row['kostpris'];
				
				// Fix: use $vare_id instead of $r[id]
				$query = db_select("SELECT COALESCE(SUM(beholdning), 0) AS lager_antal FROM lagerstatus WHERE vare_id = '$vare_id'", __FILE__ . " line " . __LINE__);
				if ($lager_row = db_fetch_array($query)) {
					$old_lager_antal = $lager_row['lager_antal'];
				}
				
				// Update kostpris
				if($lager_antal > 0 && $old_lager_antal > 0) {
					$new_kostpris = ($old_kostpris * $old_lager_antal + $kostpris * $lager_antal) / ($old_lager_antal + $lager_antal);
				} elseif($lager_antal > 0) {
					$new_kostpris = $kostpris;
				} else {
					$new_kostpris = $old_kostpris; // No change if no new stock
				}
				echo "Updating kostpris for varenr: $varenr from $old_kostpris - $kostpris new $new_kostpris<br>";
				$update_sql = "UPDATE varer SET kostpris = $new_kostpris WHERE id = $vare_id";
				db_modify($update_sql, __FILE__ . " line " . __LINE__);
				
				// Add old varenr as alias
				$alias_sql = "UPDATE varer SET varenr_alias = '$varenr' beskrivelse_alias = $beskrivelse WHERE id = $vare_id";
				db_modify($alias_sql, __FILE__ . " line " . __LINE__);
				
				echo "Updated kostpris for varenr: $varenr<br>";
			} else {
				$kostpris_raw = trim($data[$kostpris_index]);
				$kostpris = str_replace('.', '', $kostpris_raw);
				$kostpris = str_replace(',', '.', $kostpris);
				$varenr = intval($data[$varenr_index]);
				$beskrivelse = isset($data[$beskrivelse_index]) ? db_escape_string(trim($data[$beskrivelse_index])) : '';
				$enhed = isset($data[$enhed_index]) ? db_escape_string(trim($data[$enhed_index])) : '';
				$gruppe = isset($data[$gruppe_index]) ? intval(trim($data[$gruppe_index])) : 0;
				$min_lager = isset($data[$min_lager_index]) ? intval(trim($data[$min_lager_index])) : 0;
				$max_lager = isset($data[$max_lager_index]) ? intval(trim($data[$max_lager_index])) : 0;
				$lokation = isset($data[$lokation_index]) ? db_escape_string(trim($data[$lokation_index])) : '';
				$udgået = isset($data[$udgået_index]) ? intval(trim($data[$udgået_index])) : 0;
				$notes = isset($data[$notes_index]) ? db_escape_string(trim($data[$notes_index])) : '';
				$stregkode = isset($data[$stregkode_index]) ? db_escape_string(trim($data[$stregkode_index])) : '';
				$varenr_alias = isset($data[$varenr_alias_index]) ? db_escape_string(trim($data[$varenr_alias_index])) : '';
				$salgspris_raw = isset($data[$salgspris_index]) ? trim($data[$salgspris_index]) : '';
				$salgspris = str_replace('.', '', $salgspris_raw);
				$salgspris = str_replace(',', '.', $salgspris);
				$trademark = isset($data[array_search('varemærke', $header)]) ? db_escape_string(trim($data[array_search('varemærke', $header)])) : '';
				$insert_sql = "INSERT INTO varer (varenr, beskrivelse, kostpris, enhed, gruppe, min_lager, max_lager, location, lukket, notes, stregkode, varenr_alias, trademark, salgspris) 
							   VALUES ('$varenr', '$beskrivelse', $kostpris, '$enhed', '$gruppe', $min_lager, $max_lager, '$lokation', $udgået, '$notes', '$stregkode', '$varenr_alias', '$trademark', $salgspris)";
				db_modify($insert_sql, __FILE__ . " line " . __LINE__);
				echo "Inserted new product with varenr: $varenr<br>";
			}
		}
		
		fclose($handle);
	} else {
		die("Could not open the file.");
	}
}else{
	// file uplaod of csv file
	echo '<form method="POST" enctype="multipart/form-data">
		<label for="product_file">Upload CSV file:</label>
		<input type="file" name="product_file" id="product_file" accept=".csv">
		<input type="submit" value="Upload">
	</form>';
}