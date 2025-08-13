<?php

/* 
* take in debitor list and compare it against a database of customers
* to align customer data.
*/

@session_start();
$s_id = session_id();

include "../includes/connect.php";
include "../includes/online.php";

/* 
* take in csv file with these headers
* "kontonr";"firmanavn";"addr1";"addr2";"postnr";"bynavn";"land";"kontakt";"tlf";"fax";"email";"web";"notes";"kreditmax";"betalingsbet";"betalingsdage";"cvrnr";"ean";"institution";"bank_reg";"bank_konto";"gruppe";"kontoansvarlig";"oprettet";"kontakt_navn";"kontakt_addr1";"kontakt_addr2";"kontakt_postnr";"kontakt_bynavn";"kontakt_tlf";"kontakt_fax";"kontakt_email";"kontakt_notes"
* insert all new customers with a new kontornr based on the highest kontonr in the database
* and if email, tlf or firmanavn already exists do nothing
* table "adresser" columns are the same as the csv headers
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['customer_file'])) {
	$file = $_FILES['customer_file']['tmp_name'];
	
	if (($handle = fopen($file, 'r')) !== FALSE) {
		$header = fgetcsv($handle, 1000, ';');
		$firmanavn_index = array_search('firmanavn', $header);
		$email_index = array_search('email', $header);
		$tlf_index = array_search('tlf', $header);
		$addr1_index = array_search('addr1', $header);
		$addr2_index = array_search('addr2', $header);
		$postnr_index = array_search('postnr', $header);
		$bynavn_index = array_search('bynavn', $header);
		$land_index = array_search('land', $header);
		$kontakt_index = array_search('kontakt', $header);
		$fax_index = array_search('fax', $header);
		$web_index = array_search('web', $header);
		$notes_index = array_search('notes', $header);
		$kreditmax_index = array_search('kreditmax', $header);
		$betalingsbet_index = array_search('betalingsbet', $header);
		$betalingsdage_index = array_search('betalingsdage', $header);
		$cvrnr_index = array_search('cvrnr', $header);
		$ean_index = array_search('ean', $header);
		$institution_index = array_search('institution', $header);
		$bank_reg_index = array_search('bank_reg', $header);
		$bank_konto_index = array_search('bank_konto', $header);
		$gruppe_index = array_search('gruppe', $header);
		$kontoansvarlig_index = array_search('kontoansvarlig', $header);
		$oprettet_index = array_search('oprettet', $header);
		$kontakt_navn_index = array_search('kontakt_navn', $header);
		$kontakt_addr1_index = array_search('kontakt_addr1', $header);
		$kontakt_addr2_index = array_search('kontakt_addr2', $header);
		$kontakt_postnr_index = array_search('kontakt_postnr', $header);
		$kontakt_bynavn_index = array_search('kontakt_bynavn', $header);
		$kontakt_tlf_index = array_search('kontakt_tlf', $header);
		$kontakt_fax_index = array_search('kontakt_fax', $header);
		$kontakt_email_index = array_search('kontakt_email', $header);
		$kontakt_notes_index = array_search('kontakt_notes', $header);

		if ($firmanavn_index === false) {
			die("CSV file must contain 'firmanavn' columns.");
		}
		
		while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
			$firmanavn = db_escape_string(trim($data[$firmanavn_index]));
			$email = isset($data[$email_index]) ? db_escape_string(trim($data[$email_index])) : '';
			$tlf = isset($data[$tlf_index]) ? db_escape_string(trim($data[$tlf_index])) : '';
			
			// Check if customer already exists
			$sql = "SELECT id FROM adresser WHERE email = '$email' OR tlf = '$tlf' OR firmanavn = '$firmanavn'";
			$result = db_select($sql, __FILE__ . " line " . __LINE__);
			
			if (db_num_rows($result) == 0) {
				// Insert new customer with all fields except kontonr which should be the next available number
				$kontonr = db_select("SELECT MAX(kontonr) + 1 AS next_kontonr FROM adresser", __FILE__ . " line " . __LINE__);
				$row = db_fetch_array($kontonr);
				$kontonr = $row['next_kontonr'];
				$addr1 = isset($data[$addr1_index]) ? db_escape_string(trim($data[$addr1_index])) : '';
				$addr2 = isset($data[$addr2_index]) ? db_escape_string(trim($data[$addr2_index])) : '';
				$postnr = isset($data[$postnr_index]) ? db_escape_string(trim($data[$postnr_index])) : '';
				$bynavn = isset($data[$bynavn_index]) ? db_escape_string(trim($data[$bynavn_index])) : '';
				$land = isset($data[$land_index]) ? db_escape_string(trim($data[$land_index])) : '';
				$kontakt = isset($data[$kontakt_index]) ? db_escape_string(trim($data[$kontakt_index])) : '';
				$fax = isset($data[$fax_index]) ? db_escape_string(trim($data[$fax_index])) : '';
				$web = isset($data[$web_index]) ? db_escape_string(trim($data[$web_index])) : '';
				$notes = isset($data[$notes_index]) ? db_escape_string(trim($data[$notes_index])) : '';
				$kreditmax = isset($data[$kreditmax_index]) ? floatval(trim($data[$kreditmax_index])) : 0;
				$betalingsbet = isset($data[$betalingsbet_index]) ? db_escape_string(trim($data[$betalingsbet_index])) : '';
				$betalingsdage = isset($data[$betalingsdage_index]) ? intval(trim($data[$betalingsdage_index])) : 0;
				$cvrnr = isset($data[$cvrnr_index]) ? db_escape_string(trim($data[$cvrnr_index])) : '';
				$ean = isset($data[$ean_index]) ? db_escape_string(trim($data[$ean_index])) : '';
				$institution = isset($data[$institution_index]) ? db_escape_string(trim($data[$institution_index])) : '';
				$bank_reg = isset($data[$bank_reg_index]) ? db_escape_string(trim($data[$bank_reg_index])) : '';
				$bank_konto = isset($data[$bank_konto_index]) ? db_escape_string(trim($data[$bank_konto_index])) : '';
				$gruppe = isset($data[$gruppe_index]) ? intval(trim($data[$gruppe_index])) : 0;
				$kontoansvarlig = isset($data[$kontoansvarlig_index]) ? db_escape_string(trim($data[$kontoansvarlig_index])) : '';
				$oprettet = isset($data[$oprettet_index]) ? db_escape_string(trim($data[$oprettet_index])) : date('Y-m-d H:i:s');
				$insert_sql = "INSERT INTO adresser (kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf, fax, email, web, notes, kreditmax, betalingsbet, betalingsdage, cvrnr, ean, institution, bank_reg, bank_konto, gruppe) VALUES ('$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$tlf', '$fax', '$email', '$web', '$notes', $kreditmax, '$betalingsbet', $betalingsdage, '$cvrnr', '$ean', '$institution', '$bank_reg', '$bank_konto', $gruppe)";
				db_query($insert_sql, __FILE__ . " line " . __LINE__);
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

