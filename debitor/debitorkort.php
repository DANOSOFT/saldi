<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/debitorkort.php --- lap 5.0.0 --- 2026-02-16 --- 
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
// Copyright (c) 2003-2026 saldi.dk aps 
// ----------------------------------------------------------------------

// 20240528 PHR Added $_SESSION['debitorId']
// 20240906 phr Moved $debitorId to settings as 20240528 didnt work with open orders ??
// 20250911 LOE Create a contact employee if none exists for erhverv accounts 
// 20250917 LOE Position methods for contacts updated for ansatte table and related queries
// 20251122 LOE Modified icons to SVG format and buttons to fit the new design
// 20260204 LOE Added grid for displaying orders; related to the debitor SD-245
// 20260205 LOE Fixed a bug where newly created accounts loads new form when save is clicked SD-321
// 20260213 LOE  - Reordered the columns of datagrid, added Total field and clickable rows.
@session_start();
$s_id = session_id();

$fokus = $katString = NULL;
$konto_id = $lukket = $ordre_id = $productlimit = $status = $status_antal = 0;
$cat_id = $kategori = array();
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/overlib.js\"></script>\n";

$modulnr = 6;
$title = "Debitorkort";
$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/grid.php");
# >> Date picker scripts 
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/jquery-3.6.4.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/moment.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/daterangepicker.min.js\" defer></script>";
print '<link rel="stylesheet" type="text/css" href="../css/daterangepicker.css" />';

$qtxt = "select id from settings where var_name = 'debitorId' and var_grp = 'debitor' and user_id = '$bruger_id'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "update settings set var_value = '' where id = '$r[id]'";
} else {
	$qtxt = "insert into settings (var_name, var_grp, user_id, var_description) ";
	$qtxt .= "values ";
	$qtxt .= "('debitorId','debitor', '$bruger_id','Used to track debitor Id when orderlist is called from debitor card')";
}
db_modify($qtxt, __FILE__ . " linje " . __LINE__);

#if (isset($_SESSION['debitorId'])) $_SESSION['debitorId'] = NULL;
print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>\n";
$_private = if_isset($_GET, NULL, 'privat');
$_business = if_isset($_GET, NULL, 'erhverv');

$id = if_isset($_GET, NULL, 'id');
if (!$id) $id = if_isset($_GET, NULL, 'konto_id');
if (!isset($_GET['fokus'])) $_GET['fokus'] = NULL;
if (!isset($_GET['ordre_id'])) $_GET['ordre_id'] = NULL;
if (!isset($_GET['returside'])) $_GET['returside'] = NULL;
$backUrl = isset($_GET['returside'])
	? $_GET['returside']
	: 'javascript:window.history.go(-2);';
if ($_GET['returside']) {
	$returside = $_GET['returside'];
	$ordre_id = $_GET['ordre_id'];
	$fokus = $_GET['fokus'];
	$returside .= '?ordre_id=' . $ordre_id;
} else {
	if ($popup) $returside = "../includes/luk.php";
	else $returside = "debitor.php";
}
if (isset($_GET['delete_category'])) {
	$delete_category = $_GET['delete_category'];
	$r = db_fetch_array(db_select("select * from grupper where art='DebInfo'", __FILE__ . " linje " . __LINE__));
	$cat_id = explode(chr(9), $r['box1']);
	$cat_beskrivelse = explode(chr(9), $r['box2']);
	for ($x = 0; $x < count($cat_id); $x++) {
		if ($cat_id[$x] != $delete_category) {
			($box1) ? $box1 .= chr(9) . $cat_id[$x] : $box1 = $cat_id[$x];
			($box2) ? $box2 .= chr(9) . db_escape_string($cat_beskrivelse[$x]) : $box2 = db_escape_string($cat_beskrivelse[$x]);
		}
	}
	$delete_category = NULL;
	db_modify("update grupper set box1='$box1',box2='$box2' where art = 'DebInfo'", __FILE__ . " linje " . __LINE__);
}
#$rename_category = isset($_GET['rename_category']) ? $_GET['rename_category'] : NULL;

$rename_category = if_isset($_GET, NULL, 'rename_category');

############# 
if ($id) {
	$query = db_select("
									SELECT navn 
									FROM ansatte 
									WHERE konto_id = '$id' 
									AND posnr = 1
								", __FILE__ . " linje " . __LINE__);

	$row = db_fetch_array($query);
	if ($row && isset($row['navn']) && $row['navn']) {
		$navnA = $row['navn'];

		// Update adresser where id = konto_id and set kontakt
		db_modify("UPDATE adresser SET kontakt = '$navnA' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
	}
}

############
$is_grid_submission = (
    isset($_GET['page']) || 
    isset($_GET['sort']) || 
    (isset($_GET['search']) && is_array($_GET['search']))
);

if (!$is_grid_submission && (isset($_POST['id']) || isset($_POST['firmanavn']))) {
	$submit = if_isset($_POST['submit'], NULL);
	$DelEt = findtekst('1099|Slet', $sprog_id);
	$id     = $_POST['id'];
	if (isset($_POST['anonymize']) && $id) {
		include('anonymize.php');
	} elseif ($submit != $DelEt) {
		$notes = $_POST['notes'];
		$firmanavn = db_escape_string(trim($_POST['firmanavn']));
		$addr1 = db_escape_string(trim($_POST['addr1']));
		$addr2 = db_escape_string(trim($_POST['addr2']));
		$postnr = db_escape_string(trim($_POST['postnr']));
		$bynavn = db_escape_string(trim($_POST['bynavn']));
		$land = db_escape_string(trim($_POST['land']));
		$kontakt = db_escape_string(trim($_POST['kontakt']));
		$tlf = db_escape_string(trim($_POST['tlf']));
		$email = db_escape_string(trim($_POST['email']));
		$mailfakt = db_escape_string(trim(if_isset($_POST['mailfakt'])));
		$cvrnr = db_escape_string(trim($_POST['cvrnr']));
		$kontonr = db_escape_string(trim($_POST['kontonr']));
		$felt_1 = db_escape_string(trim($_POST['felt_1']));
		$notes = db_escape_string(trim($_POST['notes']));
		$ny_kontonr = db_escape_string(trim($_POST['ny_kontonr']));
		$gl_kontotype = db_escape_string(trim($_POST['gl_kontotype']));
		$kontotype = db_escape_string(trim($_POST['kontotype']));
		(isset($_POST['fornavn'])) ? $fornavn = db_escape_string(trim($_POST['fornavn'])) : $fornavn = '';
		(isset($_POST['efternavn'])) ? $efternavn = db_escape_string(trim($_POST['efternavn'])) : $efternavn = '';
		$fax = db_escape_string(trim($_POST['fax']));
		$web = db_escape_string(trim($_POST['web']));
		$betalingsbet = db_escape_string(trim($_POST['betalingsbet']));
		$ean = db_escape_string(trim($_POST['ean']));
		$institution = db_escape_string(trim($_POST['institution']));
		$betalingsdage = $_POST['betalingsdage'] * 1;
		$kreditmax = usdecimal($_POST['kreditmax'], 2);
		$felt_2 = db_escape_string(trim($_POST['felt_2']));
		$felt_3 = db_escape_string(trim($_POST['felt_3']));
		$felt_4 = db_escape_string(trim($_POST['felt_4']));
		$felt_5 = db_escape_string(trim($_POST['felt_5']));
		$lev_firmanavn = db_escape_string(trim($_POST['lev_firmanavn']));
		(isset($_POST['lev_fornavn'])) ? $lev_fornavn   = db_escape_string(trim($_POST['lev_fornavn']))   : $lev_fornavn   = '';
		(isset($_POST['lev_efternavn'])) ? $lev_efternavn = db_escape_string(trim($_POST['lev_efternavn'])) : $lev_efternavn = '';
		$lev_addr1 = db_escape_string(trim($_POST['lev_addr1']));
		$lev_addr2 = db_escape_string(trim($_POST['lev_addr2']));
		$lev_postnr = db_escape_string(trim($_POST['lev_postnr']));
		$lev_bynavn = db_escape_string(trim($_POST['lev_bynavn']));
		$lev_land = db_escape_string(trim($_POST['lev_land']));
		$lev_kontakt = db_escape_string(trim($_POST['lev_kontakt']));
		$lev_tlf = db_escape_string(trim($_POST['lev_tlf']));
		(isset($_POST['lev_email'])) ? $lev_email = db_escape_string(trim($_POST['lev_email'])) : $lev_email = '';

		$vis_lev_addr = db_escape_string(if_isset($_POST['vis_lev_addr'], NULL));
		update_settings_value("vis_lev_addr", "ordrer", $vis_lev_addr, "If the adress field should be showen as standard value", $bruger_id);

		$lukket = db_escape_string(if_isset($_POST['lukket'], NULL));
		(isset($_POST['password'])) ? $password = db_escape_string(trim($_POST['password'])) : $password = '';
		$productlimit = db_escape_string(trim($_POST['productlimit']));
		list($gruppe) = explode(':', $_POST['gruppe']);
		(isset($_POST['rabatgruppe'])) ? $rabatgruppe = db_escape_string(trim($_POST['rabatgruppe'])) : $rabatgruppe = 0;
		if (!$rabatgruppe) $rabatgruppe = 0;
		$kontoansvarlig = $_POST['kontoansvarlig'];
		$bank_reg = $_POST['bank_reg'];
		$bank_konto = $_POST['bank_konto'];
		$swift = $_POST['swift'];
		(isset($_POST['pbs_nr'])) ? $pbs_nr = db_escape_string(trim($_POST['pbs_nr'])) : $pbs_nr = '';
		(isset($_POST['pbs'])) ? $pbs    = db_escape_string(trim($_POST['pbs'])) : $pbs    = '';
		$ordre_id = $_POST['ordre_id'];
		$returside = $_POST['returside'];
		$fokus = $_POST['fokus'];
		$posnr = if_isset($_POST['posnr'], array());
		(isset($_POST['ans_id'])) ? $ans_id = $_POST['ans_id'] : $ans_id = 0;
		$ans_ant = $_POST['ans_ant'];

		$cat_id          = if_isset($_POST['cat_id'], array());
		$cat_valg        = if_isset($_POST['cat_valg'], array());
		$cat_beskrivelse = if_isset($_POST['cat_beskrivelse'], array());
		$newCatName      = if_isset($_POST['newCatName'], NULL);
		$rename_category = isset($_POST['rename_category']) ? $_POST['rename_category'] : NULL;

		$status = db_escape_string(trim($_POST['status']));
		(isset($_POST['ny_status'])) ? $ny_status = db_escape_string(trim($_POST['ny_status'])) : $ny_status = '';
		$status_id = $_POST['status_id'];
		$status_beskrivelse = $_POST['status_beskrivelse'];
		$status_antal = count($status_id);
		$rename_status = if_isset($_POST['rename_status']);

		if ($gl_kontotype == 'privat') {
			$firmanavn = trim($fornavn . " " . $efternavn);
			$lev_firmanavn = trim($lev_fornavn . " " . $lev_efternavn);
		}
		#		if (!$pbs) $pbs_nr=NULL;
		($status == 'new_status') ? $new_status = 1 : $new_status = 0;
		if (!$status) $status = 0;

		if (substr($ny_kontonr, 0, 1) == "=") {
			$ny_kontonr = str_replace("=", "", $ny_kontonr);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=kontofusion.php?returside=$returside&ordre_id=$ordre_id&id=$id&fokus=$fokus&kontonr=$ny_kontonr\">\n";
			exit;
		}
		if (!$id && !$firmanavn && !$ny_kontonr) {
			if (findtekst('255|Ekstrafelt 1', $sprog_id) == 'Regnskab' && $felt_1 > 1 && is_numeric($felt_1)) {
				include("../includes/connect.php");
				if ($r = db_fetch_array($q = db_select("select * from regnskab where id='$felt_1'", __FILE__ . " linje " . __LINE__))) {
					$regnskab = db_escape_string($r['regnskab']);
					if ($r = db_fetch_array($q = db_select("select * from kundedata where regnskab='$regnskab' or regnskab_id='$felt_1'", __FILE__ . " linje " . __LINE__))) {
						$ny_kontonr = db_escape_string($r['tlf']);
						$firmanavn = db_escape_string($r['firmanavn']);
						$felt = db_escape_string($r['regnskab']);
						$addr1 = db_escape_string($r['addr1']);
						$addr2 = db_escape_string($r['addr2']);
						$postnr = db_escape_string($r['postnr']);
						$land = db_escape_string($r['land']);
						$land = db_escape_string($r['land']);
						$kontakt = db_escape_string($r['kontakt']);
						$tlf = db_escape_string($r['tlf']);
						$email = db_escape_string($r['email']);
						$cvrnr = db_escape_string($r['cvrnr']);
						$kontonr = db_escape_string($r['kontonr']);
						$notes = db_escape_string($r['notes']);
						$mailfakt = 'on';
						$gruppe = 4;
					}
					$felt_1 .= " : $regnskab";
				}
				include("../includes/online.php");
			}
		}
		######### Kategorier (Categories Section)

		// Ensure the required variables are initialized if they are not already set
		if (!isset($kategori)) $kategori = array();
		if (!isset($status_valg)) $status_valg = array();
		if (!isset($box3)) $box3 = NULL;
		if (!isset($box4)) $box4 = NULL;

		// Loop through all category IDs
		for ($x = 0; $x < count($cat_id); $x++) {
			// Ensure the corresponding category selection is initialized
			if (!isset($cat_valg[$x])) $cat_valg[$x] = '';

			// If the category is selected (indicated by "on")
			if ($cat_valg[$x] == "on") {
				// Add the category ID to the katString, separated by a tab character (chr(9))
				// If katString is empty, initialize it with the first category ID
				($katString || $katString == '0') ? $katString .= chr(9) . $cat_id[$x] : $katString = $cat_id[$x];
			}
		}

		// Retrieve the placeholder text for a new category name based on the language ID
		$tmp = findtekst('343|Skriv evt. ny kategori her', $sprog_id);

		// Check if a new category name was provided and it does not match the placeholder text
		if ($newCatName && $newCatName != $tmp) {
			// Check for duplicate category names if not renaming
			if (!is_numeric($rename_category) && in_array($newCatName, $cat_beskrivelse)) {
				// Set an alert message if the new name already exists
				$alerttekst = findtekst('344|Kategorien $ny_kategori eksisterer allerede', $sprog_id);
			} else {
				$isRenamed = false; // Track if renaming occurred

				// Loop through all category IDs and descriptions
				for ($x = 0; $x < count($cat_id); $x++) {
					// Ensure both the ID and description are valid
					if (($cat_id[$x] || $cat_id[$x] == '0') && $cat_beskrivelse[$x]) {
						// Update the category description if renaming
						if ($cat_id[$x] == $rename_category) {
							$cat_beskrivelse[$x] = $newCatName;
							$isRenamed = true; // Mark as renamed
						}
					}
				}

				// If renaming was not successful, add a new category
				if (!$isRenamed) {
					$x = '0'; // Start with 0 for the new category ID
					$y = count($cat_id);
					// Find the lowest unused category ID
					while (in_array($x, $cat_id)) $x++;
					// Assign the new ID and description to the arrays
					$cat_id[$y] = $x;
					$cat_beskrivelse[$y] = $newCatName;
				}

				// Initialize temporary storage variables for updated category data
				$box1 = $box2 = NULL;
				for ($x = 0; $x < count($cat_id); $x++) {
					if (($cat_id[$x] || $cat_id[$x] == '0') && $cat_beskrivelse[$x]) {
						// Build the box1 string with category IDs (tab-separated)
						($box1 || $box1 == '0') ? $box1 .= chr(9) . $cat_id[$x] : $box1 = $cat_id[$x];
						// Build the box2 string with escaped category descriptions (tab-separated)
						($box2) ? $box2 .= chr(9) . db_escape_string($cat_beskrivelse[$x]) : $box2 = db_escape_string($cat_beskrivelse[$x]);
					}
				}

				// Reset the rename_category variable
				$rename_category = NULL;

				// Prepare the SQL query to update the category group data
				$qtxt = "update grupper set box1='$box1',box2='$box2' where art = 'DebInfo'";

				// Execute the query to update the database
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}

		######### Status

		

		// Helper function to append values with tabs
		function appendWithTab(&$target, $value)
		{
			$target = isset($target) ? $target . chr(9) . $value : $value;
		}

		// Add selected statuses
		foreach ($status_valg as $index => $status_value) {
			if ($status_value || $status_value === '0') {
				appendWithTab($status, $status_id[$index]);
			}
		}

		// Handle new status
		if ($ny_status) {
			if (!$rename_status && in_array($ny_status, $status_beskrivelse)) {
				$alerttekst = findtekst('344|Kategorien $ny_kategori eksisterer allerede', $sprog_id);
			} else {
				if (!$rename_status) {
					$x = 1;
					while (in_array($x, $status_id)) $x++; // Find the lowest available value
					$status = $x;
					$status_id[$status_antal] = $x;
					$status_beskrivelse[$status_antal] = $ny_status;
					$status_antal++;
				}
				$box3 = null;
				$box4 = null;
			}
		}

		// Always keep existing statuses in $box3 and $box4, whether they are used or not
		for ($x = 0; $x < $status_antal; $x++) {
			if ($status_id[$x] == $rename_status) {
				$status_beskrivelse[$x] = $ny_status;
			}

			// Add all statuses to $box3 and $box4 without removing them
			appendWithTab($box3, $status_id[$x]);
			appendWithTab($box4, $status_beskrivelse[$x]);
		}

		// Reset rename status and update database
		$rename_status = 0;
		db_modify("UPDATE grupper SET box3 = '$box3', box4 = '$box4' WHERE art = 'DebInfo'", __FILE__ . " linje " . __LINE__);


		######### Tjekker om kontonr er integer

		$temp = str_replace(" ", "", $ny_kontonr);
		$tmp2 = '';
		for ($x = 0; $x < strlen($temp); $x++) {
			$y = substr($temp, $x, 1);
			if ((ord($y) < 48) || (ord($y) > 57)) {
				$y = 0;
			}
			$tmp2 = $tmp2 . $y;
		}
		$tmp2 = (float)$tmp2;
		if ($tmp2 != $ny_kontonr) {
			$alerttekst = findtekst('345|Kontonummer må kun bestå af heltal uden mellemrum', $sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 345-->";
		}
		$ny_kontonr = $tmp2;
		/* 	
		if ($pbs) {
			if (!is_numeric($bank_reg)||strlen($bank_reg)!=4) {
				$pbs="";	
				print "<BODY onLoad=\"javascript:alert('Bank reg skal best&aring; af et tal p&aring; 4 cifre for at PBS kan aktiveres')\">\n";
			} elseif (!is_numeric($bank_konto)||strlen($bank_konto)!=10) {
				$pbs="";	
				print "<BODY onLoad=\"javascript:alert('Bank konto skal best&aring; af et tal p&aring; 10 cifre for at PBS kan aktiveres')\">\n";
			} elseif (!is_numeric($cvrnr)||strlen($cvrnr)!=8) {
				$pbs="";	
				print "<BODY onLoad=\"javascript:alert('CVR nr skal best&aring; af et tal p&aring; 8 cifre for at PBS kan aktiveres')\">\n";
			}
		}
*/
		if (!$id && $ny_kontonr) {
			$qtxt = "select id from adresser where kontonr = '$ny_kontonr' and art = 'D'";
			if ($ny_kontonr && db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$alerttekst = findtekst('350|Der findes allerede en debitor med Kontonr', $sprog_id);
				$alerttekst = $alerttekst.': '.$ny_kontonr;
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">"; #<!--tekst 350-->\n";
				$ny_kontonr = '!';
			}
		}
		if (!$firmanavn) {
			$alerttekst = findtekst('346|Navn skal angives', $sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\">"; #<!--tekst 346-->\n";
			$kontonr = $ny_kontonr;
		}
		if ($postnr && !$bynavn) $bynavn = bynavn($postnr);
		if ($lev_postnr && !$lev_bynavn) $lev_bynavn = bynavn($lev_postnr);
		if ($kontoansvarlig) {
			if ($r = db_fetch_array(db_select("select id from adresser where art = 'S'", __FILE__ . " linje " . __LINE__))) {
				if ($r = db_fetch_array(db_select("select id from ansatte where initialer = '$kontoansvarlig' and konto_id='$r[id]'", __FILE__ . " linje " . __LINE__))) $kontoansvarlig = $r['id'];
			}
		} elseif ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box2 = 'on'", __FILE__ . " linje " . __LINE__))) {
			$alerttekst = findtekst('347|Kundeansvarlig ikke valgt!', $sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 347-->\n";
		}
		if (!$kontoansvarlig) $kontoansvarlig = '0';
		if (!$gruppe) {
			$alerttekst = findtekst('348|Debitorgruppe ikke valgt!', $sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 348-->\n";
			$gruppe = '0';
		}
		## Tildeler aut kontonr hvis det ikke er angivet
		$ktoliste = array();
		if ($firmanavn && $ny_kontonr !== '!' && ($ny_kontonr < 1 || !$ny_kontonr)) {
			if (!$id) $id = "0";
			$x = 0;
			$qtxt = "select kontonr from adresser where art = 'D' and id != $id order by kontonr";
			$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$x++;
				$ktoliste[$x] = $r['kontonr'];
			}
			$ny_kontonr = 1000;
			while (in_array($ny_kontonr, $ktoliste)) $ny_kontonr++;
			$alerttekst = findtekst('349|Kontonummer $ny_kontonr tildelt automatisk!', $sprog_id);
			$alerttekst = str_replace('$ny_kontonr', $ny_kontonr, $alerttekst);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 349-->\n";
		}


		############################
		if (!$betalingsdage) {
			$betalingsdage = 0;
		}
		if (!$kreditmax) {
			$kreditmax = 0;
		}
		if ($id == 0 && $ny_kontonr && $ny_kontonr != '!') {
			$oprettet = date("Y-m-d");
			$qtxt = "insert into adresser ";
			$qtxt .= "(kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,tlf,fax,email,";
			$qtxt .= "mailfakt,web,betalingsdage,kreditmax,betalingsbet,cvrnr,ean,institution,notes,";
			$qtxt .= "art,gruppe,kontoansvarlig,oprettet,bank_reg,bank_konto,swift,pbs_nr,pbs,kontotype,";
			$qtxt .= "fornavn,efternavn,lev_firmanavn,lev_fornavn,lev_efternavn,lev_addr1,lev_addr2,lev_postnr,";
			$qtxt .= "lev_bynavn,lev_land,lev_kontakt,lev_tlf,lev_email,felt_1,felt_2,felt_3,felt_4,felt_5,";
			$qtxt .= "vis_lev_addr,lukket,kategori,rabatgruppe,status,productlimit)";
			$qtxt .= " values ";
			$qtxt .= "('$ny_kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$tlf','$fax','$email',";
			$qtxt .= "'$mailfakt','$web','$betalingsdage','$kreditmax','$betalingsbet','$cvrnr','$ean','$institution','$notes','D',";
			$qtxt .= "'$gruppe','$kontoansvarlig','$oprettet','$bank_reg','$bank_konto','$swift','$pbs_nr','$pbs','$kontotype',";
			$qtxt .= "'$fornavn','$efternavn','$lev_firmanavn','$lev_fornavn','$lev_efternavn','$lev_addr1','$lev_addr2','$lev_postnr',";
			$qtxt .= "'$lev_bynavn','$lev_land','$lev_kontakt','$lev_tlf','$lev_email','$felt_1','$felt_2','$felt_3','$felt_4','$felt_5',";
			$qtxt .= "'$vis_lev_addr','$lukket','$katString','$rabatgruppe','$status','" . usdecimal($productlimit) . "')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$q = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'D'", __FILE__ . " linje " . __LINE__);
			$r = db_fetch_array($q);
			$id = $r['id'];
			if ($kontakt) db_modify("insert into ansatte(konto_id, navn) values ('$id', '$kontakt')", __FILE__ . " linje " . __LINE__);
			
			print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?tjek_id=$id&id=$id&returside=$returside\">\n";
			exit;
		} elseif ($id > 0) {
			#######	
			$q1 = db_select("select id from ansatte where konto_id = '$id'", __FILE__ . " linje " . __LINE__);
			$ar = db_fetch_array($q1);
			$a_id = $ar['id'];
			#######


			if ($ny_kontonr != $kontonr) {
				$q = db_select("select kontonr from adresser where art = 'D' order by kontonr", __FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$x++;
					$ktoliste[$x] = $r['kontonr'];
				}
				if (in_array($ny_kontonr, $ktoliste)) {
					$alerttekst = findtekst('351|Kontonummer findes allerede', $sprog_id);
					print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 351-->\n";
				} else {
					$kontonr = $ny_kontonr;
				}
			}

			####
			$q2 = db_select("select kontotype from adresser where kontonr = '$kontonr' and art = 'D'", __FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$vkontotype = $r2['kontotype'];
			####

			if (($kontotype == $vkontotype) || (!isset($a_id))) {
				$qtxt = "update adresser set kontonr = '$kontonr', firmanavn = '$firmanavn', addr1 = '$addr1', addr2 = '$addr2', ";
				$qtxt .= "postnr = '$postnr', bynavn = '$bynavn', land = '$land', kontakt = '$kontakt', tlf = '$tlf', fax = '$fax', ";
				$qtxt .= "email = '$email', mailfakt = '$mailfakt', web = '$web', betalingsdage= '$betalingsdage', ";
				$qtxt .= "kreditmax = '$kreditmax',betalingsbet = '$betalingsbet', cvrnr = '$cvrnr', ean = '$ean', ";
				$qtxt .= "institution = '$institution', notes = '$notes',gruppe='$gruppe', ";
				$qtxt .= "kontoansvarlig='$kontoansvarlig',bank_reg='$bank_reg',bank_konto='$bank_konto',swift='$swift',";
				$qtxt .= "pbs_nr = '$pbs_nr', pbs = '$pbs',kontotype='$kontotype',fornavn='$fornavn',efternavn='$efternavn',";
				$qtxt .= "lev_firmanavn='$lev_firmanavn',lev_fornavn='$lev_fornavn',lev_efternavn='$lev_efternavn',";
				$qtxt .= "lev_addr1='$lev_addr1',lev_addr2='$lev_addr2',lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',";
				$qtxt .= "lev_land='$lev_land',lev_kontakt='$lev_kontakt',lev_tlf='$lev_tlf',lev_email='$lev_email',";
				$qtxt .= "felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',";
				$qtxt .= "vis_lev_addr='$vis_lev_addr',lukket='$lukket',kategori='$katString',";
				$qtxt .= "rabatgruppe='$rabatgruppe',status='$status', productlimit = '" . usdecimal($productlimit) . "' ";
				#if ($password != '**********') $qtxt.=",password = '". saldikrypt('$id','$password') ."' "; 20210706
				$qtxt .= "where id = '$id'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				// for ($x = 1; $x <= $ans_ant; $x++) {
				// 	$y = trim($posnr[$x]);
				// 	if ($y && is_numeric($y) && $ans_id[$x]) db_modify("update ansatte set posnr = '$y' where id = '$ans_id[$x]'", __FILE__ . " linje " . __LINE__);
				// 	elseif (($y == "-") && ($ans_id[$x])) {
				// 		db_modify("delete from ansatte 	where id = '$ans_id[$x]'", __FILE__ . " linje " . __LINE__);
				// 	} else {
				// 		$alerttekst = findtekst('352|Hint! Du skal sætte et - (minus) som pos nr for at slette en kontaktperson', $sprog_id);
				// 		print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 352-->\n";
				// 	}
				// }
				//			if (!$pbs) db_modify("delete from pbs_kunder where konto_id = $id",__FILE__ . " linje " . __LINE__); # 2012103
				###########################

				$seen_posnr = [];
				$used_ids = [];
				$errors = [];


				// ----------  Validate inputs & check for duplicates ----------
				for ($x = 1; $x <= $ans_ant; $x++) {
					$y = trim($posnr[$x]);
					$current_id = (int)$ans_id[$x];



					if (!$current_id) {

						continue;
					}

					// Handle deletion
					if ($y === "-") {

						db_modify("DELETE FROM ansatte WHERE id = $current_id", __FILE__ . " linje " . __LINE__);



						#######
						$query = db_select("
										SELECT navn 
										FROM ansatte 
										WHERE konto_id = '$id' 
										AND posnr = 1
									", __FILE__ . " linje " . __LINE__);

						$row = db_fetch_array($query);
						$navnA = $row['navn'];
						if (!$navnA) $navnA = NULL;
						//update adresser where id = id and set kontakt to kontakt
						db_modify("update adresser set kontakt = '$navnA' where id = '$id'", __FILE__ . " linje " . __LINE__);
						######
						continue;
					}

					// Validate: must be numeric and within allowed range 
					if (!is_numeric($y)) {
						error_log("Invalid posnr input (not numeric) at index $x: '$y'");
						$errors[] = findtekst('352|Hint! Du skal sætte et - (minus) som pos nr for at slette en kontaktperson', $sprog_id);

						continue;
					}

					// Now validate numeric range
					if ($y < 1 || $y > $ans_ant) {
						$errors[] = "Invalid position number: $y. It must be between 1 and $ans_ant.";
						error_log("Invalid position number at index $x: $y");
						continue;
					}

					// Check for duplicates in the input
					if (isset($seen_posnr[$y])) {
						$errors[] = "Duplicate position number detected: $y";
						error_log("Duplicate position detected at index $x: $y");
						continue;
					}

					// Store for 2nd pass
					$seen_posnr[$y] = true;
					$used_ids[$current_id] = (int)$y;

					error_log("Accepted posnr $y for ansatte id $current_id at index $x");
				}

				foreach ($used_ids as $id => $pos) {
					error_log("  id = $id => posnr = $pos");
				}

				// ---------- Error Handling ----------
				if (!empty($errors)) {
					foreach ($errors as $msg) {
						error_log($msg);
					}
					$alerttekst = implode("\\n", $errors);
					$alerttekst_js = addslashes($alerttekst); // escape for JS string

					print <<<HTML
						<script>
							alert('$alerttekst_js');
							if (document.referrer) {
								window.location.href = document.referrer;
							} else {
								window.location.href = '/';
							}
						</script>
						HTML;
					exit; // stop execution
				}

				error_log("Clearing posnr for used IDs");
				foreach ($used_ids as $id => $target_posnr) {
					$id = (int)$id;

					db_modify("UPDATE ansatte SET posnr = NULL WHERE id = $id", __FILE__ . " linje " . __LINE__);
				}


				foreach ($used_ids as $id => $target_posnr) {
					$id = (int)$id;
					$target_posnr = (int)$target_posnr;



					// If position 1, update kontakt in adresser
					if ($target_posnr == 1) {
						$q_navn = db_select("SELECT navn FROM ansatte WHERE id = $id", __FILE__ . " linje " . __LINE__);
						$r_navn = db_fetch_array($q_navn);
						$navnT = $r_navn['navn'];

						error_log("Position 1 detected for id $id; updating kontakt to '$navnT'");
						db_modify("UPDATE adresser SET kontakt = '$navnT' WHERE id = $id", __FILE__ . " linje " . __LINE__);
					}

					db_modify("UPDATE ansatte SET posnr = $target_posnr WHERE id = $id", __FILE__ . " linje " . __LINE__);
				}

				print <<<HTML
												<script>
													if (document.referrer) {
														window.location.href = document.referrer;
													} else {
														// fallback if no referrer available
														window.location.href = '/'; 
													}
												</script>
												HTML;

				exit;


				###########################

			} else {
				$alerttekst = "Please delete all contacts to proceed";
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--....-->\n";

				if ($vkontotype == 'erhverv') {
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ansatte.php?id=$a_id&konto_id=$id&privat=privat\">";
				} elseif ($vkontotype == 'privat') {
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ansatte.php?id=$a_id&konto_id=$id&erhverv=erhverv\">";
				}
				exit;
			}
		}
	} else {
		
		db_modify("delete from adresser where id = $id", __FILE__ . " linje " . __LINE__);
		db_modify("delete from shop_adresser where saldi_id = $id", __FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=debitor.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus\">\n";
	   exit;
	}
}

if ($id > 0) {
	$q = db_select("select * from adresser where id = '$id'", __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$kontonr = trim($r['kontonr']);
	$kontotype = trim($r['kontotype']);
	$firmanavn = htmlentities(trim($r['firmanavn']), ENT_COMPAT, $charset);
	$fornavn = htmlentities(trim($r['fornavn']), ENT_COMPAT, $charset);
	$efternavn = htmlentities(trim($r['efternavn']), ENT_COMPAT, $charset);
	$addr1 = htmlentities(trim($r['addr1']), ENT_COMPAT, $charset);
	$addr2 = htmlentities(trim($r['addr2']), ENT_COMPAT, $charset);
	$postnr = trim($r['postnr']);
	$bynavn = htmlentities(trim($r['bynavn']), ENT_COMPAT, $charset);
	$land = htmlentities(trim($r['land']), ENT_COMPAT, $charset);
	$lev_firmanavn = htmlentities(trim($r['lev_firmanavn']), ENT_COMPAT, $charset);
	$lev_fornavn = htmlentities(trim($r['lev_fornavn']), ENT_COMPAT, $charset);
	$lev_efternavn = htmlentities(trim($r['lev_efternavn']), ENT_COMPAT, $charset);
	$lev_addr1 = htmlentities(trim($r['lev_addr1']), ENT_COMPAT, $charset);
	$lev_addr2 = htmlentities(trim($r['lev_addr2']), ENT_COMPAT, $charset);
	$lev_postnr = trim($r['lev_postnr']);
	$lev_bynavn = htmlentities(trim($r['lev_bynavn']), ENT_COMPAT, $charset);
	$lev_land = htmlentities(trim($r['lev_land']), ENT_COMPAT, $charset);
	$lev_tlf = trim($r['lev_tlf']);
	$lev_email = trim($r['lev_email']);
	$lev_kontakt = htmlentities(trim($r['lev_kontakt']), ENT_COMPAT, $charset); #20131004
	$tlf = trim($r['tlf']);
	$fax = trim($r['fax']);
	$email = trim($r['email']);
	$mailfakt = trim($r['mailfakt']);
	$web = trim($r['web']);
	$kreditmax = $r['kreditmax'];
	$betalingsdage = $r['betalingsdage'];
	$betalingsbet = trim($r['betalingsbet']);
	$cvrnr = trim($r['cvrnr']);
	$ean = trim($r['ean']);
	$institution = htmlentities(trim($r['institution']), ENT_COMPAT, $charset);
	$notes = htmlentities(trim($r['notes']), ENT_COMPAT, $charset);
	$gruppe = trim($r['gruppe']);
	$rabatgruppe = $r['rabatgruppe'];
	$bank_konto = trim($r['bank_konto']);
	$bank_reg = trim($r['bank_reg']);
	$swift = trim($r['swift']);
	$kontakt = htmlentities(trim($r['kontakt']), ENT_COMPAT, $charset);
	if ($r['pbs'] == 'on') $pbs = "checked";
	$pbs_nr = trim($r['pbs_nr']);
	$pbs_date = trim($r['pbs_date']);
	$kontoansvarlig = trim($r['kontoansvarlig']);
	$status = trim($r['status']);
	$oprettet = $r['oprettet'];
	$productlimit = $r['productlimit'];
	if (!$kontoansvarlig) $kontoansvarlig = '0';
	($r['vis_lev_addr']) ? $vis_lev_addr = 'checked' : $vis_lev_addr = NULL;
	$felt_1 = htmlentities(trim($r['felt_1']), ENT_COMPAT, $charset);
	$felt_2 = htmlentities(trim($r['felt_2']), ENT_COMPAT, $charset);
	$felt_3 = htmlentities(trim($r['felt_3']), ENT_COMPAT, $charset);
	$felt_4 = htmlentities(trim($r['felt_4']), ENT_COMPAT, $charset);
	$felt_5 = htmlentities(trim($r['felt_5']), ENT_COMPAT, $charset);
	($r['lukket']) ? $lukket = 'checked' : $lukket = '';

	$kategori = array();
	if ($r['kategori'] || $r['kategori'] == 0) $kategori = explode(chr(9), $r['kategori']);
	if (!$oprettet) {
		$oprettet = date("Y-m-d");
		$qtxt = "select max(oprettet) as oprettet from adresser where id < '$id'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$oprettet = $r['oprettet'];
		$qtxt = "select min(ordredate) as oprettet from ordrer where konto_id='$id'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$oprettet = $r['oprettet'];
		$qtxt = "select min(transdate) as oprettet from openpost where konto_id='$id'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r['oprettet']) $oprettet = $r['oprettet'];
	}
} else {
	$kontonr = NULL;
	$r = db_fetch_array(db_select("select count(kontotype) as privat from adresser where kontotype = 'privat'", __FILE__ . " linje " . __LINE__));
	$privat = $r['privat'];
	$r = db_fetch_array(db_select("select count(kontotype) as erhverv from adresser where kontotype = 'erhverv'", __FILE__ . " linje " . __LINE__));
	$erhverv = $r['erhverv'];
	($privat > $erhverv) ? $kontotype = "privat" : $kontotype = "erhverv";
	$x = 0;
	$bb = array();
	$q = db_select("select distinct(betalingsbet) as betalingsbet from adresser", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$bb[$x] = $r['betalingsbet'];
		$x++;
	}
	$maxbb = 'Netto';
	for ($x = 0; $x < count($bb); $x++) {
		$qtxt = "select count(betalingsbet) as betalingsbet from adresser where  art='D' and betalingsbet = '$bb[$x]'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$betbet[$x] = $r['betalingsbet'];
		if ($x == 0) $maxbb = $bb[$x];
		elseif ($betbet[$x] > $betbet[$x - 1]) $maxbb = $bb[$x];
	}
	$bb = NULL;
	$x = 0;
	$bd = array();
	$q = db_select("select distinct(betalingsdage) as betalingsdage from adresser WHERE betalingsdage != 0", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$bd[$x] = $r['betalingsdage'];
		$x++;
	}
	if ($maxbb != 'Kontant' && $maxbb != 'Forud') {
		$maxbd = 8;
		for ($x = 0; $x < count($bd); $x++) {
			$r = db_fetch_array(db_select("select count(betalingsdage) as betalingsdage from adresser where art='D' and betalingsdage = '$bd[$x]'", __FILE__ . " linje " . __LINE__));
			$betdag[$x] = $r['betalingsdage'];
			if ($x && $betdag[$x] > $betdag[$x - 1]) $maxbd = $bd[$x];
		}
	} else $maxbd = 0;
	$bd = NULL;
	$x = NULL;
	$id = 0;
	$betalingsbet = $maxbb;
	$betalingsdage = $maxbd;
	$kontoansvarlig = '0';
	if (isset($_GET['kontonr'])) $kontonr = $_GET['kontonr'];
	if (isset($_GET['firmanavn'])) $firmanavn = $_GET['firmanavn'];
	if (isset($_GET['addr1'])) $addr1 = $_GET['addr1'];
	if (isset($_GET['addr2'])) $addr2 = $_GET['addr2'];
	if (isset($_GET['postnr'])) $postnr = $_GET['postnr'];
	if (isset($_GET['bynavn'])) $bynavn = $_GET['bynavn'];
	if (isset($_GET['land'])) $land = $_GET['land'];
	if (isset($_GET['kontakt'])) $kontakt = $_GET['kontakt'];
	if (isset($_GET['tlf'])) $tlf = $_GET['tlf'];
	if (!isset($vis_lev_addr)) $vis_lev_addr = 'checked';
	print "<BODY onLoad=\"javascript:docChange = true;\">\n";
}

if (!isset($kreditmax)) $kreditmax = NULL;
$kreditmax = dkdecimal($kreditmax);

if ($r = db_fetch_array(db_select("select * from grupper where art='DebInfo'", __FILE__ . " linje " . __LINE__))) {
	$cat_id = explode(chr(9), $r['box1']);
	$cat_beskrivelse = explode(chr(9), $r['box2']);
	$status_id = explode(chr(9), $r['box3']);
	$status_beskrivelse = explode(chr(9), $r['box4']);
	$status_antal = count($status_id);
} else db_modify("insert into grupper(beskrivelse,art) values ('Div DebitorInfo','DebInfo')", __FILE__ . " linje " . __LINE__);

if (!isset($fornavn)) $fornavn = null;
if (!isset($efternavn)) $efternavn = null;
if (!isset($firmanavn)) $firmanavn = null;
if (!isset($lev_fornavn)) $lev_fornavn = null;
if (!isset($lev_efternavn)) $lev_efternavn = null;

if ($kontotype == "privat") {
	if (!$fornavn && !$efternavn && $firmanavn) {
		list($fornavn, $efternavn) = explode(",", split_navn($firmanavn));
		list($lev_fornavn, $lev_efternavn) = explode(",", split_navn($lev_firmanavn));
		db_modify("update adresser set fornavn='" . db_escape_string($fornavn) . "',efternavn='" . db_escape_string($efternavn) . "' where id = '$id'", __FILE__ . " linje " . __LINE__); #20140507
	}
}
######################## OUTPUT ######################

if (!isset($felt_1)) $felt_1 = NULL;
if (!isset($felt_2)) $felt_2 = NULL;
if (!isset($felt_3)) $felt_3 = NULL;
if (!isset($felt_4)) $felt_4 = NULL;
if (!isset($felt_5)) $felt_5 = NULL;
if (!isset($kontonr)) $kontonr = NULL;

$tekst = findtekst('154|Dine ændringer er ikke blevet gemt! Tryk OK for at forlade siden uden at gemme.', $sprog_id);
if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	## add onClick=\"JavaScript:opener.location.reload();\" but still get style from headlink MALENE
	print "<div class=\"headerbtnLft headLink\"><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst('30|Tilbage', $sprog_id) . "</a>";
	if ($jobkort) {
		print "&nbsp;&nbsp;";
	} else {
		print "";
	}
	print "</div>";
	print "<div class=\"headerTxt\">$title</div>";
	print "<div class=\"headerbtnRght headLink\"><a href='historikkort.php?id=$id&returside=debitorkort.php' title='" . findtekst('131|Historik', $sprog_id) . "'><i class='fa fa-history fa-lg'></i></a>&nbsp;&nbsp;<a href='rapport.php?rapportart=kontokort&konto_fra=$kontonr&konto_til=$kontonr&returside=../debitor/debitorkort.php?id=$id' title='" . findtekst('133|Kontokort', $sprog_id) . "'><i class='fa fa-vcard fa-lg'></i></a>";
	if (substr($rettigheder, 5, 1) == '1') {
		print "&nbsp;&nbsp;<a href='ordreliste.php?konto_id=$id&valg=faktura&returside=../debitor/debitorkort.php?id=$id' title='" . findtekst('134|Fakturaliste', $sprog_id) . "'><i class='fa fa-dollar fa-lg'></i></a>";
	} else {
		print "";
	}
	if ($jobkort) {
		print "&nbsp;&nbsp;<a href='jobliste.php?konto_id=$id&returside=debitorkort.php' title='" . findtekst('38|Stillingsliste', $sprog_id) . "'><i class='fa fa-list-ul fa-lg'></i></a>";
	} else {
		print "";
	}
	print "</div></div>";
	print "<div class='content-noside'>";
	print  "<table border='0' cellspacing='1' class='dataTableForm' width='100%'>";
} elseif ($menu == 'S') {
	############################ 
	$icon_back = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="24" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
	$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';
	$add_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M440-280h80v-160h160v-80H520v-160h-80v160H280v80h160v160Zm40 200q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';

	##########################
	print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n"; # TABEL 1 ->
	print "<tr><td align=\"center\" valign=\"top\">\n";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; # TABEL 1.1 ->

	print "<td width='10%'>
		<a href=\"$returside\" accesskey=L>
		<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. $icon_back . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>\n";

	print "<td width='75%'  style='$topStyle' align='center'>" . findtekst('356|Debitorkort', $sprog_id) . "</td>\n";

	print "<td id='tutorial-help' width=5% style=$buttonStyle>";
	print "<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
	print $help_icon . findtekst('2564|Hjælp', $sprog_id) . "</button></td>";

	print "<td width='5%'>
		<a href=\"javascript:confirmClose('debitorkort.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=0','$tekst')\" accesskey=N>
		<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. $add_icon . findtekst('39|Ny', $sprog_id) . "</button></a></td>\n";

	print "</tbody></table>"; # <- TABEL 1.1
	print "</td></tr>\n"; # <- Close the table row and cell
	print "</tbody></table>\n"; # <- TABEL 1
?>
	<style>
		.headerbtn,
		.center-btn {
			display: flex;
			align-items: center;
			text-decoration: none;
			gap: 5px;
		}
	</style>
<?php

} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n"; # TABEL 1 ->
	print "<tr><td align=\"center\" valign=\"top\">\n";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; # TABEL 1.1 ->
	if ($popup) print "<td onClick=\"JavaScript:opener.location.reload();\" width=\"10%\" $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>" . findtekst('30|Tilbage', $sprog_id) . "<!--tekst 30--></a></td>\n";
	else print "<td $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L><!--tekst 154-->" . findtekst('30|Tilbage', $sprog_id) . "<!--tekst 30--></a></td>\n";
	print "<td width=\"80%\"$top_bund>" . findtekst('356|Debitorkort', $sprog_id) . "<!--tekst 356--></td>\n";
	print "<td width=\"10%\"$top_bund><a href=\"javascript:confirmClose('debitorkort.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=0','$tekst')\" accesskey=N><!--tekst 154-->" . findtekst('39|Ny', $sprog_id) . "<!--tekst 39--></a></td>\n";
	print "</tbody></table>"; # <- TABEL 1.1
	print "</td></tr>\n";
	print "<tr><td align = center valign = center>\n";
	print "<table cellpadding=\"0\" cellspacing=\"10\" border=\"0\"><tbody>\n"; # TABEL 1.2 ->
	print "</td></tr>\n";
	print "</tbody></table>\n"; # <- Close TABEL 1
}

print "<div class='outer-datatable-wrapper'>";
print "<div class='form-wrapper'>"; // CHANGED: specific class for form area
if ($menu != 'T') {
	// START A NEW TABLE with the same properties:
	print "<table cellpadding=\"0\" cellspacing=\"10\" border=\"0\" width=\"100%\"><tbody>\n"; # NEW TABEL 1.2 ->
}

print "<form name=debitorkort action=debitorkort.php method=post>\n";
$vis_addr = get_settings_value("vis_lev_addr", "ordrer", "off", $bruger_id);
if ($vis_addr == "on") {
	print "<input type=hidden name=\"felt_1\" value='$felt_1'>\n";
	print "<input type=hidden name=\"felt_2\" value='$felt_2'>\n";
	print "<input type=hidden name=\"felt_3\" value='$felt_3'>\n";
	print "<input type=hidden name=\"felt_4\" value='$felt_4'>\n";
	print "<input type=hidden name=\"felt_5\" value='$felt_5'>\n";
} else {
	print "<input type=hidden name=\"lev_firmanavn\" value='$lev_firmanavn'>\n";
	print "<input type=hidden name=\"lev_fornavn\" value='$lev_fornavn'>\n";
	print "<input type=hidden name=\"lev_efternavn\" value='$lev_efternavn'>\n";
	print "<input type=hidden name=\"lev_addr1\" value='$lev_addr1'>\n";
	print "<input type=hidden name=\"lev_addr2\" value='$lev_addr2'>\n";
	print "<input type=hidden name=\"lev_postnr\" value='$lev_postnr'>\n";
	print "<input type=hidden name=\"lev_bynavn\" value='$lev_bynavn'>\n";
	print "<input type=hidden name=\"lev_land\" value='$lev_land'>\n";
	print "<input type=hidden name=\"lev_tlf\" value='$lev_tlf'>\n";
	print "<input type=hidden name=\"lev_email\" value='$lev_email'>\n";
	print "<input type=hidden name=\"lev_kontakt\" value='$lev_kontakt'>\n";
}

if (!isset($kontakt)) $kontakt = NULL;
if (!isset($pbs_date)) $pbs_date = NULL;

print "<input type=hidden name=id value='$id'>\n";
print "<input type=hidden name=kontonr value='$kontonr'>\n";
print "<input type=hidden name=ordre_id value='$ordre_id'>\n";
print "<input type=hidden name=returside value='$returside'>\n";
print "<input type=hidden name=fokus value='$fokus'>\n";
print "<input type=hidden name=kontakt value='$kontakt'>\n";
print "<input type=hidden name=pbs_date value='$pbs_date'>\n";
# print "<input type=hidden name=pbs_nr value='$pbs_nr'>\n";
# print "<input type=hidden name=gl_pbs_nr value='$pbs_nr'>\n";
#print "<input type=hidden name=pbs value='$pbs'>\n";

$bg = $bgcolor5;
print "<input type=hidden name=gl_kontotype value='$kontotype'>\n";
print "<tr bgcolor='$bg'><td colspan=2 align=center>" . findtekst('1149|Kundetype', $sprog_id) . " <select class='inputbox' NAME=kontotype onchange=\"javascript:docChange = true;\">\n";
if ($kontotype == 'privat') {

	print "<option value=privat>" . findtekst('353|Privat', $sprog_id) . "<!--tekst 353--></option>\n";
	print "<option value=erhverv>" . findtekst('354|Erhverv', $sprog_id) . "<!--tekst 354--></option>\n";
} else {
	print "<option value=erhverv>" . findtekst('354|Erhverv', $sprog_id) . "<!--tekst 354--></option>\n";
	print "<option value=privat>" . findtekst('353|Privat', $sprog_id) . "<!--tekst 353--></option>\n";
}
print "</select></td>\n";
print "<td align=right>" . findtekst('355|Vis leveringsadresse', $sprog_id) . "<!--tekst 355--><input class='inputbox' type=\"checkbox\" name=\"vis_lev_addr\" $vis_lev_addr> <a href=\"labelprint.php?id=$id\" target=\"blank\"><img src=\"../ikoner/print.png\" style=\"border: 0px solid;\"></a></td></tr>\n";
print "<tr><td valign=top height=250px><table border=0 width=100%><tbody>"; # TABEL 1.2.1 ->
$bg = $bgcolor5;
print "<tr bgcolor=$bg><td>" . findtekst('357|Kundenr.', $sprog_id) . "<!--tekst 357--></td><td><input class='inputbox' type='text' size='25' name=ny_kontonr value=\"$kontonr\" onchange=\"javascript:docChange = true;\" title=\"Tast CVR-nr. omsluttet af *, +, eller / for at importere data fra Erhvervsstyrelsen (Data leveres af CVR API)\" style=\"background-image: url('../img/search-white.png'); background-repeat: no-repeat; background-position: right;\"></td></tr>\n";

if (!isset($firmanavn)) $firmanavn = NULL;
if (!isset($addr1)) $addr1 = NULL;
if (!isset($addr2)) $addr2 = NULL;
if (!isset($postnr)) $postnr = NULL;
if (!isset($land)) $land = NULL;
if (!isset($email)) $email = NULL;
if (!isset($web)) $web = NULL;
if (!isset($gruppe)) $gruppe = NULL;
if (!isset($bynavn)) $bynavn = NULL;
if (!isset($mailfakt)) $mailfakt = NULL;
if (!isset($cvrnr)) $cvrnr = NULL;
if (!isset($tlf)) $tlf = NULL;
if (!isset($fax)) $fax = NULL;
if (!isset($ean)) $ean = NULL;
if (!isset($institution)) $institution = NULL;
if (!isset($bank_reg)) $bank_reg = NULL;
if (!isset($bank_konto)) $bank_konto = NULL;
if (!isset($swift)) $swift = NULL;
if (!isset($lukket)) $lukket = NULL;
if (!isset($lev_firmanavn)) $lev_firmanavn = NULL;
if (!isset($lev_addr1)) $lev_addr1 = NULL;
if (!isset($lev_addr2)) $lev_addr2 = NULL;
if (!isset($lev_postnr)) $lev_postnr = NULL;
if (!isset($lev_land)) $lev_land = NULL;
if (!isset($lev_kontakt)) $lev_kontakt = NULL;
if (!isset($lev_bynavn)) $lev_bynavn = NULL;
if (!isset($lev_tlf)) $lev_tlf = NULL;
if (!isset($notes)) $notes = NULL;

if ($kontotype == 'privat') {
	print "<input type=\"hidden\" name=\"firmanavn\" value=\"$firmanavn\">\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>" . findtekst('358|Fornavn', $sprog_id) . "<!--tekst 358--></td><td><input class='inputbox' type='text' size='25' name=fornavn value=\"$fornavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>" . findtekst('359|Efternavn', $sprog_id) . "<!--tekst 359--></td><td><input class='inputbox' type='text' size='25' name=efternavn value=\"$efternavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
} else {
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>" . findtekst('360|Firmanavn', $sprog_id) . "<!--tekst 360--></td><td><input class='inputbox' type='text' size='25' name=firmanavn value=\"$firmanavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
}

($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('361|Adresse', $sprog_id) . "<!--tekst 361--></td><td><input class='inputbox' type='text' size='25' ";
print "name='addr1' value=\"$addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('362|Adresse 2', $sprog_id) . "<!--tekst 362--></td><td><input class='inputbox' type='text' size='25' ";
print "name='addr2' value=\"$addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('363|Postnr./By', $sprog_id) . "<!--tekst 363--></td><td><input class='inputbox' type='text' size='3' ";
print "name='postnr' value=\"$postnr\" onchange=\"javascript:docChange = true;\">\n";
print "<input class='inputbox' type='text' size=16 name=bynavn value=\"$bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr gcolor=$bg><td>" . findtekst('364|Land', $sprog_id) . "<!--tekst 364--></td><td><input class='inputbox' type='text' size='25' ";
print "name='land' value=\"$land\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('365|E-mail / brug mail', $sprog_id) . "<!--tekst 365--></td><td><input class='inputbox' type='text' size='22' ";
print "name='email' value=\"$email\" onchange=\"javascript:docChange = true;\">\n";
if ($email && $mailfakt) $mailfakt = "checked";
print "<span title=\"" . findtekst('366|Afmærk her hvis modtageren skal modtage tilbud', $sprog_id) . "\"><!--tekst 366--><input class='inputbox' type=checkbox name='mailfakt' $mailfakt>";
print "</span></td></tr>\n";
if ($kontotype == 'erhverv') {
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>" . findtekst('367|Hjemmeside', $sprog_id) . "<!--tekst 367--></td>";
	print "<td><input class='inputbox' type='text' size='25' name='web' value=\"$web\" ";
	print "onchange=\"javascript:docChange = true;\"></td></tr>\n";
} else print "<input type = 'hidden' name = 'web' value = \"$web\">";
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('368|Betalingsbetingelse', $sprog_id) . "<!--tekst 368--></td>\n";
print "<td><select class='inputbox' NAME=betalingsbet onchange=\"javascript:docChange = true;\" >\n";
print "<option>$betalingsbet</option>\n";
if ($betalingsbet != 'Forud') print "<option value=\"Forud\">" . findtekst('369|Forud', $sprog_id) . "<!--tekst 369--></option>\n";
if ($betalingsbet != 'Kontant') print "<option value=\"Kontant\">" . findtekst('370|Kontant', $sprog_id) . "<!--tekst 370--></option>\n";
if ($betalingsbet != 'Efterkrav') print "<option value=\"Efterkrav\">" . findtekst('371|Efterkrav', $sprog_id) . "<!--tekst 371--></option>\n";
if ($betalingsbet != 'Netto') print "<option value=\"Netto\">" . findtekst('372|Netto', $sprog_id) . "<!--tekst 372--></option>\n";
if ($betalingsbet != 'Lb. md.')  print "<option value=\"Lb. md.\">" . findtekst('373|Lb. md.', $sprog_id) . "<!--tekst 373--></option>\n";
if (($betalingsbet == 'Kontant') || ($betalingsbet == 'Efterkrav') || ($betalingsbet == 'Forud')) $betalingsdage = '';

elseif (!$betalingsdage) {
	$betalingsdage = 'Nul';
}
if ($betalingsdage) {
	if ($betalingsdage == 'Nul') {
		$betalingsdage = 0;
	}
	print "</SELECT>&nbsp;+<input class='inputbox' type='text' size='2' style='text-align:right' name='betalingsdage' value=\"$betalingsdage\" onchange=\"javascript:docChange = true;\"></td>\n";
} else print "</SELECT></td></tr>\n";
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('374|Debitorgruppe', $sprog_id) . "<!--tekst 374--></td>\n";
if (!$gruppe) {
	if (db_fetch_array(db_select("select id from grupper where art='DIV' and kodenr='2' and box1='on'", __FILE__ . " linje " . __LINE__))) $gruppe = '0';
	else $gruppe = 1;
}
print "<td><select class='inputbox' NAME=gruppe onchange=\"javascript:docChange = true;\">\n";
if ($gruppe) {
	$r = db_fetch_array(db_select("select beskrivelse from grupper where art='DG' and kodenr='$gruppe' and fiscal_year='$regnaar'", __FILE__ . " linje " . __LINE__));
	print "<option>$gruppe:$r[beskrivelse]</option>\n";
}
$q = db_select("select * from grupper where art='DG' and kodenr!='$gruppe' AND fiscal_year='$regnaar' order by kodenr", __FILE__ . " linje " . __LINE__);

while ($r = db_fetch_array($q)) {
	print "<option>$r[kodenr]:$r[beskrivelse]</option>\n";
}
print "</SELECT></td></tr>\n";
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg>";
$x = 0;
$q = db_select("select * from grupper where art='DRG' order by box1", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$x++;
	$drg_nr[$x] = $r['kodenr'];
	$drg_navn[$x] = $r['box1'];
}
if ($drg = $x) {
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<td>" . findtekst('375|Rabatgruppe', $sprog_id) . "<!--tekst 375--></td>\n";
	print "<td><select class='inputbox' NAME=rabatgruppe onchange=\"javascript:docChange = true;\">\n";
	for ($x = 1; $x <= $drg; $x++) {
		if ($rabatgruppe == $drg_nr[$x]) print "<option value=\"$rabatgruppe\">$drg_navn[$x]</option>\n";
	}
	print "<option value=\"0\"></option>\n";
	for ($x = 1; $x <= $drg; $x++) {
		if ($rabatgruppe != $drg_nr[$x]) print "<option value=\"$drg_nr[$x]\">$drg_navn[$x]</option>\n";
	}
	print "</SELECT></td></tr>\n";
} else print "<td colspan=\"2\"><br></td></tr>";
#print "<td><br></td>\n";
print "</tbody></table></td>"; # <- TABEL 1.2.1
print "<td valign=top><table border=0 width=100%><tbody>"; # TABEL 1.2.2 ->
$bg = $bgcolor5;
print "<tr bgcolor=$bg><td>" . findtekst('376|CVR-nr.', $sprog_id) . "<!--tekst 376--></td><td><input class=\"inputbox\" type='text' style='width:100px' name=cvrnr value=\"$cvrnr\" onchange=\"javascript:docChange = true;\" title=\"Tast CVR-nr. omsluttet af *, +, eller / for at importere data fra Erhvervsstyrelsen (Data leveres af CVR API)\" style=\"background-image: url('../img/search-white.png'); background-repeat: no-repeat; background-position: right;\"></td></tr>\n";
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('377|Telefon', $sprog_id) . "<!--tekst 377-->";
print "</td><td><input class=\"inputbox\" type='text' style='width:100px' name=tlf value=\"$tlf\" onchange=\"javascript:docChange = true;\" title=\"Tast telefonnr. omsluttet af *, +, eller / for at importere data fra Erhvervsstyrelsen (Data leveres af CVR API)\" style=\"background-image: url('../img/search-white.png'); background-repeat: no-repeat; background-position: right;\"></td></tr>\n";
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('378|Telefax', $sprog_id) . "<!--tekst 378--></td><td><input class=\"inputbox\" type='text' style='width:100px' name=fax value=\"$fax\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
if ($kontotype == 'erhverv') {
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>" . findtekst('379|EAN-nr.', $sprog_id) . "<!--tekst 379--></td>";
	print "<td><input class=\"inputbox\" type='text' style='width:100px' name='ean' value=\"$ean\" ";
	print "onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>" . findtekst('380|Institutionsnr.', $sprog_id) . "<!--tekst 380--></td>";
	print "<td><input class=\"inputbox\" type='text' style='width:100px' name='institution' value=\"$institution\" ";
	print "onchange=\"javascript:docChange = true;\"></td></tr>\n";
} else {
	print "<input type = 'hidden' name = 'ean' value = \"$ean\">";
	print "<input type = 'hidden' name = 'institution' value=\"$institution\">";
}
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('381|Kreditmax', $sprog_id) . "<!--tekst 381--></td><td><input class='inputbox' type='text' style='width:100px' ";
print "name='kreditmax' value=\"$kreditmax\"></td></tr>\n";
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('382|Bank reg.', $sprog_id) . "<!--tekst 382--></td><td><input class='inputbox' type='text' style='width:100px' ";
print "name=bank_reg value=\"$bank_reg\"></td></tr>\n";
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('383|Bank konto', $sprog_id) . "<!--tekst 383--></td><td><input class='inputbox' type='text' style='width:100px' name=bank_konto value=\"$bank_konto\"></td></tr>\n";
print "<tr bgcolor=$bg><td>" . findtekst('769|Bank BIC', $sprog_id) . "<!--tekst 769--></td><td><input class='inputbox' type='text' style='width:100px' name='swift' value=\"$swift\"></td></tr>\n";
##################### PBS ##################### 
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
if (!isset($pbs)) $pbs = NULL;
if ($pbs) {
	print "<tr bgcolor=$bg><td height=25px>" . findtekst('384|BS-nr.', $sprog_id) . "<!--tekst 384--></td><td><input class='inputbox' type=checkbox name=pbs $pbs><input class='inputbox' size=\"8\" type=\"text\" name=\"pbs_nr\" value=\"$pbs_nr\"></td></tr>\n";
} else {
	print "<tr bgcolor=$bg><td height=25px>" . findtekst('385|BS', $sprog_id) . "<!--tekst 385--></td><td><input class='inputbox' type=checkbox name=pbs $pbs></td></tr>\n";
}
##################### KONTOANSVARLIG ##################### 
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('386|Kundeansvarlig', $sprog_id) . "<!--tekst 386--></td>\n";
print "<td><select class='inputbox' NAME=kontoansvarlig value=\"$kontoansvarlig\"  onchange=\"javascript:docChange = true;\">\n";
if ($r = db_fetch_array(db_select("select initialer from ansatte where id='$kontoansvarlig'", __FILE__ . " linje " . __LINE__))) {
	$r = db_fetch_array(db_select("select initialer from ansatte where id='$kontoansvarlig'", __FILE__ . " linje " . __LINE__));
	print "<option>$r[initialer]</option>\n";
}
print "<option></option>\n";
if ($r = db_fetch_array(db_select("select id from adresser where art='S'", __FILE__ . " linje " . __LINE__))) $q = db_select("select id, initialer from ansatte where konto_id='$r[id]'", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	print "<option>$r[initialer]</option>\n";
}
print "</SELECT></td></tr>\n";
##################### STATUS ##################### 
for ($x = 0; $x < $status_antal; $x++) {
	print "<input type=\"hidden\" name=\"status_id[$x]\" value=\"$status_id[$x]\">";
	print "<input type=\"hidden\" name=\"status_beskrivelse[$x]\" value=\"$status_beskrivelse[$x]\">";
}
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
if (!isset($new_status)) $new_status = NULL;
if ($new_status) {
	print "<tr bgcolor=$bg title=\"" . findtekst('497|Navn på ny status.', $sprog_id) . "\"><!--tekst 497--><td height=\"25px\">" . findtekst('494|Status', $sprog_id) . "<!--tekst 494--></td><td><input class='inputbox' type='text' style='width:100px' name=ny_status></td></tr>\n";
} else {
	print "<tr bgcolor=$bg><td title='" . findtekst('496|Vælg \'Ny Status\' for at tilføje en ny status', $sprog_id) . "'  height=\"25px\"><!--tekst 496-->" . findtekst('494|Status', $sprog_id) . "<!--tekst 494--></td>\n";
	print "<td><select class='inputbox' NAME=status onchange=\"javascript:docChange = true;\">\n";
	if (!$status) print "<option></option>\n";
	for ($x = 0; $x < $status_antal; $x++) {
		if ($status == $status_id[$x]) print "<option value=\"$status_id[$x]\">$status_beskrivelse[$x]</option>\n";
	}
	for ($x = 0; $x < $status_antal; $x++) {
		if ($status != $status_id[$x]) print "<option value=\"$status_id[$x]\">$status_beskrivelse[$x]</option>\n";
	}
	if ($status) print "<option></option>\n";
	print "<option value=\"new_status\">" . findtekst('495|Ny status', $sprog_id) . "<!--tekst 495--></option>\n";
	print "</SELECT></td></tr>\n";
}
##################### LUKKET ##################### 
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>" . findtekst('387|Lukket', $sprog_id) . "<!--tekst 387--></td><td><input class='inputbox' type=checkbox name=lukket $lukket></td></tr>\n";
print "</tbody></table></td>"; # <- TABEL 1.2.2
print "<td valign=top><table border='0' width='100%'><tbody>"; # TABEL 1.2.3 ->
$bg = $bgcolor5;
$vis_addr = get_settings_value("vis_lev_addr", "ordrer", "off", $bruger_id);
if ($vis_addr == "on") {
	print "<tr bgcolor=$bg><td colspan=2 align=center height=25px><b>" . findtekst('1148|Levering', $sprog_id) . "</b></td></tr>\n"; #20210702
	if ($kontotype == 'privat') {
		print "<input type=\"hidden\" name=\"lev_firmanavn\" value=\"$lev_firmanavn\">\n";
		($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
		print "<tr bgcolor=$bg><td>" . findtekst('358|Fornavn', $sprog_id) . "<!--tekst 358--></td><td><input class='inputbox' type='text' size='25' name=lev_fornavn value=\"$lev_fornavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
		print "<tr bgcolor=$bg><td>" . findtekst('359|Efternavn', $sprog_id) . "<!--tekst 359--></td><td><input class='inputbox' type='text' size='25' name=lev_efternavn value=\"$lev_efternavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	} else {
		($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
		print "<tr bgcolor=$bg><td>" . findtekst('360|Firmanavn', $sprog_id) . "<!--tekst 360--></td><td><input class='inputbox' type='text' size='25' name=lev_firmanavn value=\"$lev_firmanavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	}
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>" . findtekst('361|Adresse', $sprog_id) . "<!--tekst 361--></td><td><input class='inputbox' type='text' size='25' name=lev_addr1 value=\"$lev_addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>" . findtekst('362|Adresse 2', $sprog_id) . "<!--tekst 362--></td><td><input class='inputbox' type='text' size='25' name=lev_addr2 value=\"$lev_addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>" . findtekst('363|Postnr./By', $sprog_id) . "<!--tekst 363--></td><td><input class='inputbox' type='text' size=3 name=lev_postnr value=\"$lev_postnr\" onchange=\"javascript:docChange = true;\">\n";
	print "<input class='inputbox' type='text' size=19 name=lev_bynavn value=\"$lev_bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>" . findtekst('364|Land', $sprog_id) . "<!--tekst 364--></td><td><input class='inputbox' type='text' size='25' name=lev_land value=\"$lev_land\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td  height=\"25px\">" . findtekst('502|Kontakt', $sprog_id) . "<!--tekst 502--></td><td height=\"25px\"><input class='inputbox' type='text' size=\"25px\" name=lev_kontakt value=\"$lev_kontakt\" onchange=\"javascript:docChange = true;\">\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>" . findtekst('377|Telefon', $sprog_id) . "<!--tekst 377--></td><td><input class='inputbox' type='text' size='25' name=lev_tlf value=\"$lev_tlf\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
} else {
	print "<tr bgcolor=$bg><td colspan=2 height=25px align=center><b>" . findtekst('254|Ekstrafelter', $sprog_id) . "<!--tekst 254--></b></tr>\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('" . findtekst('260|Denne tekst kan rettes under <i>Indstillinger</i> -> <i>Diverse</i> -> <i>Sprog</i><br>Find Id 255 & 260.', $sprog_id) . "', WIDTH=600);\" onmouseout=\"return nd();\"><!--tekst 260-->" . findtekst('255|Ekstrafelt 1', $sprog_id) . "<!--tekst 255--></td><td><input class='inputbox' type='text' name=\"felt_1\" size=\"25\" value=\"$felt_1\"></span></td></tr>\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('" . findtekst('261|Denne tekst kan rettes under <i>Indstillinger</i> -> <i>Diverse</i> -> <i>Sprog</i><br>Find Id 256 & 261.', $sprog_id) . "', WIDTH=600);\" onmouseout=\"return nd();\"><!--tekst 261-->" . findtekst('256|Ekstrafelt 2', $sprog_id) . "<!--tekst 256--></td><td><input class='inputbox' type='text' name=\"felt_2\" size=\"25\" value=\"$felt_2\"></td></tr>\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('" . findtekst('262|Denne tekst kan rettes under <i>Indstillinger</i> -> <i>Diverse</i> -> <i>Sprog</i><br>Find Id 257 & 262.', $sprog_id) . "', WIDTH=600);\" onmouseout=\"return nd();\"><!--tekst 262-->" . findtekst('257|Ekstrafelt 3', $sprog_id) . "<!--tekst 257--></td><td><input type='text' class='inputbox' name=\"felt_3\" size=\"25\" value=\"$felt_3\"></td></tr>\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('" . findtekst('263|Denne tekst kan rettes under <i>Indstillinger</i> -> <i>Diverse</i> -> <i>Sprog</i><br>Find Id 258 & 263.', $sprog_id) . "', WIDTH=600);\" onmouseout=\"return nd();\"><!--tekst 263-->" . findtekst('258|Ekstrafelt 4', $sprog_id) . "<!--tekst 258--></td><td><input class='inputbox' type='text' name=\"felt_4\" size=\"25\" value=\"$felt_4\"></td></tr>\n";
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('" . findtekst('264|Denne tekst kan rettes under <i>Indstillinger</i> -> <i>Diverse</i> -> <i>Sprog</i><br>Find Id 259 & 264.', $sprog_id) . "', WIDTH=600);\" onmouseout=\"return nd();\"><!--tekst 264-->" . findtekst('259|Ekstrafelt 5', $sprog_id) . "<!--tekst 259--></td><td><input type='text' class='inputbox' name=\"felt_5\" size=\"25\" value=\"$felt_5\"></td></tr>\n";
}

$qtxt = "select var_value from settings where var_grp='debitor' and var_name='mySale'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
	print "<tr bgcolor=$bg><td>";
	$oLibTxt = "Adgangskode til Mit Salg<br>Stjerner vises også når der ikke er nogen kode!";
	print "<span onmouseover=\"return overlib('$oLibTxt', WIDTH=600);\" onmouseout=\"return nd();\">Mit Salg kode</span>";
	print "</td><td><input type='text' class='inputbox' name=\"Password\" size=\"25\" value=\"**********\"></td></tr>\n";
}
($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
print "<tr bgcolor=$bg><td>";
$oLibTxt = findtekst('2217|Sæt grænse for, hvor mange varer en kunde kan oprette', $sprog_id);
print "<span onmouseover=\"return overlib('$oLibTxt', WIDTH=600);\" onmouseout=\"return nd();\">" . findtekst('2215|Varegrænse', $sprog_id) . "</span>";
print "</td><td><input type='text' class='inputbox' name=\"productlimit\" size=\"25\" value=\"" . dkdecimal($productlimit, 0) . "\"></td></tr>\n";


print "</tbody></table></td></tr>"; # <- TABEL 1.2.3

print "<tr><td colspan=3><table border=\"0\" width=\"100%\"><tbody>"; # TABEL 1.2.4 ->
print "<tr><td valign=\"top\"><table cellpadding=\"0\" cellspacing=\"1\" border=\"0\" width=\"100%\"><tbody>"; # TABEL 1.2.4.1 ->


$bg = $bgcolor5;
print "<tr bgcolor=$bg><td colspan=\"4\" valign=\"top\">" . findtekst('388|Kategorier', $sprog_id) . "<!--tekst 388--></td></tr>\n";
$x = 0;
if (!is_numeric($rename_category)) {
	for ($x = 0; $x < count($cat_id); $x++) {
		if ($cat_id[$x] || $cat_id[$x] == '0') {
			$checked = "";
			for ($y = 0; $y < count($kategori); $y++) {
				if ($cat_id[$x] == $kategori[$y]) $checked = "checked";
			}
			print "<tr><td>$cat_beskrivelse[$x]</td>\n";
			$tekst = findtekst('395|Afmærk her for at knytte $firmanavn til denne kategori', $sprog_id);
			$tekst = str_replace('$firmanavn', $firmanavn, $tekst);
			print "<td title=\"$tekst\" align=\"center\"><!--tekst 395--><input type=\"checkbox\" name=\"cat_valg[$x]\" $checked></td>\n";
			print "<td title=\"" . findtekst('396|Klik her for at omdøbe denne kategori', $sprog_id) . "\"><!--tekst 396--><a href=\"debitorkort.php?id=$id&rename_category=$cat_id[$x]\" id=\"rename_category-$x\" onclick=\"return confirm('Vil du omd&oslash;be denne kategori?')\"><img src=../ikoner/rename.png border=0></a></td>\n";
			print "<td title=\"" . findtekst('397|Klik her for at slette denne kategori', $sprog_id) . "\"><!--tekst 396--><a href=\"debitorkort.php?id=$id&delete_category=$cat_id[$x]\" id=\"delete_category-$x\" onclick=\"return confirm('Vil du slette denne kategori?')\"><img src=../ikoner/delete.png border=0></a></td>\n";
			print "</tr>\n";
			print "<input type=\"hidden\" name=\"cat_id[$x]\" value=\"$cat_id[$x]\">\n";
			print "<input type=\"hidden\" name=\"cat_beskrivelse[$x]\" value=\"$cat_beskrivelse[$x]\">\n";
		}
	}
}
// Check if we are renaming a category by verifying if $rename_category is numeric
if (is_numeric($rename_category)) {
	// Loop through all category IDs
	for ($x = 0; $x < count($cat_id); $x++) {
		// Output hidden inputs to retain category IDs and descriptions in the form
		print "<input type=\"hidden\" name=\"cat_id[$x]\" value=\"$cat_id[$x]\">\n";
		print "<input type=\"hidden\" name=\"cat_beskrivelse[$x]\" value=\"$cat_beskrivelse[$x]\">\n";

		// If the current category ID matches the one being renamed, save it for later
		if ($rename_category == $cat_id[$x]) {
			$newCatName = $cat_beskrivelse[$x];
		} else {
			print "<tr><td>$cat_beskrivelse[$x]</td></tr>\n";
		}
	}
	// Add a hidden input to retain the rename_category value in the form
	print "<input type=\"hidden\" name=\"rename_category\" value=\"$rename_category\">\n";

	// Provide an input field for entering the new category name
	print "<tr><td colspan=\"4\" title=\"Skriv det nye navn p&aring; kategorien her\"><input type=\"text\" size=\"25\" name=\"newCatName\" value=\"$newCatName\"></td></tr>\n";
} else {
	// If not renaming a category, display a text input field for creating a new category
	// Use placeholders and titles for better user guidance
	print "<tr><td colspan=\"4\" title=\"" . findtekst('390|For at oprette en ny kategori skrives navnet på kategorien her. For at oprette en underkategori skrives id på den overstående kategori foran navnet med | som adskillelse', $sprog_id) . "\"><!--tekst 390--><input class='inputbox' type=\"text\" size=\"25\" name=\"newCatName\" placeholder=\"" . findtekst('343|Skriv evt. ny kategori her', $sprog_id) . "\"></td></tr>\n";
}


print "</tbody></table></td>"; # <- TABEL 1.2.4.1
print "<td><table border=0 width='100%'><tbody>"; # TABEL 1.2.4.2 ->

$bg = $bgcolor5;
print "<tr bgcolor=$bg><td colspan=\"5\" valign=\"top\"><b>" . findtekst('391|Bemærkning', $sprog_id) . ":</b><br><!--tekst 391--> <div class='textwrapper'><textarea name=\"notes\" rows=\"6\" cols=\"85\" style='width:100%;'>$notes</textarea></div></td></tr>\n";
#print "<tr><td> <a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Kontaktperson</a></td><td><br></td>\n";
print "</tbody></table></td></tr>"; # <- TABEL 1.2.4.2
print "<tr><td colspan=2><table border=\"0\" width=\"100%\"><tbody>"; # TABEL 1.2.4.3 ->

print "<tr><td colspan=6></td></tr>\n";

#$_business == 'erhverv'
#$_private=='privat'

##############

$z2 = db_select("select id from ansatte where konto_id = '$id'", __FILE__ . " linje " . __LINE__);
$y2 = db_fetch_array($z2);
$an_id = $y2['id'];

###########


if ((!$kontotype && !$_private && !isset($an_id))) { //insert erhverv as default if not set in adresser table

	db_modify("UPDATE adresser SET kontotype = 'erhverv' WHERE id = '$id' ", __FILE__ . " linje " . __LINE__);
	$kontotype = 'erhverv';
	print "<meta http-equiv='refresh' content='0;url=debitorkort.php?id=$id'>";
} elseif (($kontotype && $_private) && !isset($an_id)) {

	db_modify("UPDATE adresser SET kontotype = 'privat' WHERE id = '$id' ", __FILE__ . " linje " . __LINE__);
	$kontotype = 'privat';
	print "<meta http-equiv='refresh' content='0;url=debitorkort.php?id=$id'>";
} elseif (($kontotype && $_business) && !isset($an_id)) {

	db_modify("UPDATE adresser SET kontotype = 'erhverv' WHERE id = '$id' ", __FILE__ . " linje " . __LINE__);
	$kontotype = 'erhverv';
	print "<meta http-equiv='refresh' content='0;url=debitorkort.php?id=$id'>";
} elseif (($kontotype && $_private) && isset($an_id)) {
	db_modify("UPDATE adresser SET kontotype = 'erhverv' WHERE id = '$id' ", __FILE__ . " linje " . __LINE__);
	$kontotype = 'erhverv';
	print "<meta http-equiv='refresh' content='0;url=debitorkort.php?id=$id'>";
}



$x = 0;
if ($kontotype == 'erhverv') {
	print "<tr bgcolor=$bg><td colspan=6><b>" . findtekst('392|Kontaktpersoner', $sprog_id) . "<!--tekst 392--></b></td></tr>\n";
	if ($id) {

		($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
		print "<tr bgcolor=$bg><td title=\"" . findtekst('393|Positionsnummer. Primær kontakt har nummer 1', $sprog_id) . "\"><!--tekst 393-->" . findtekst('394|Pos.', $sprog_id) . "<!--tekst 394--></td><td>" . findtekst('398|Kontakt', $sprog_id) . "<!--tekst 398--></td><td title=\"" . findtekst('399|Direkte telefonnummer eller lokalnummer', $sprog_id) . "\"><!--tekst 399-->" . findtekst('400|Direkte/lokal', $sprog_id) . "<!--tekst 400--></td><td>" . findtekst('401|Mobil', $sprog_id) . "<!--tekst 401--></td><td>" . findtekst('402|E-mail', $sprog_id) . "<!--tekst 402--></td><td><a href='ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id'><button type='button' class='button green small' style='$buttonStyle; padding: 2px 10px 2px 10px' onMouseOver=\"this.style.cursor='pointer'\">" . findtekst('39|Ny', $sprog_id) . "<!--tekst 39--></button></a></td>\n"; 
		$x = 0;
		$q = db_select("select * from ansatte where konto_id = '$id' order by posnr", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);

		while ($r) {
			$x++;
			($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
			print "<tr bgcolor=$bg>\n";
			print "<td width=10><input class='inputbox' type='text' size=2 name=posnr[$x] value=\"$x\"></td><td title=\"" . htmlentities($r['notes'], ENT_COMPAT, $charset) . "\"><a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id&id=$r[id]>" . htmlentities($r['navn'], ENT_COMPAT, $charset) . "</a></td>\n";
			print "<td>$r[tlf]</td><td>$r[mobil]</td><td> $r[email]</td></td><td></td></tr>\n";
			print "<input class='inpPasswordutbox' type=hidden name=ans_id[$x] value=$r[id]>\n";
			if ($x == 1) {
				print "<input class='inputbox' type=hidden name=kontakt value='$r[navn]'>";
			}
			//fetch next
			$r = db_fetch_array($q);
		}
		print "<tr><td colspan='2' width='20%'><br></td></tr>\n";
	}
}

print "<input type='hidden' name='ans_ant' value='$x'>\n";

#print "<tr><td><br></td></tr>\n";
$q = db_select("select id from openpost where konto_id = '$id'", __FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet = "NO";
$q = db_select("select id from ordrer where konto_id = '$id'", __FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet = "NO";
$q = db_select("select id from ansatte where konto_id = '$id'", __FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet = "NO";
if (!isset($slet)) $slet = NULL;
print "<tr>";
if ($slet == "NO") {
	print "<td colspan='2' width='20%'><br></td>";
	print "<td colspan='2' align='center' width='30%'>";
	print "<input type='submit' class='button green medium' style='border-radius:4px;' accesskey='g' ";
	print "value=" . findtekst('471|Gem/opdatér', $sprog_id) . " name='submit' onclick='javascript:docChange = false;'>";
	print "&nbsp;<input type='submit' style='border-radius:4px;' ";
	print "name='anonymize' value='" . findtekst('1929|Anonymisér', $sprog_id) . "' ";
	$txt = str_replace('$kontonr', $kontonr, findtekst('1930|Anonymiser konto $kontonr? \r\nNavn, adresse og telefonnummer fjernes på dette kort og på alle ordrer, rykkere mm. \r\nEventuelle kontakter slettes.', $sprog_id));
	print "onclick=\"return confirm('$txt')\">";
	print "</td>";
	print "<td colspan='2' width='20%'><br></td>";
} else {
	print "<td colspan='2' width='20%'><br></td>";
	print "<td align='center' width='30%'>";
	print "<input class='button green medium' style='border-radius:4px;' type=submit accesskey=\"g\" ";
	print "value=\"" . findtekst('471|Gem/opdatér', $sprog_id) . "\" name=\"submit\" onclick=\"javascript:docChange = false;\">";
	print "</td>";
	print "<td align='center' width='30%'>";
	print "<input class='button rosy medium' style='border-radius:4px;' type='submit' accesskey='s'";
	print "value='" . findtekst('1099|Slet', $sprog_id) . "' 
	name='submit' 
	onclick=\"return confirm('" . findtekst('1099|Slet', $sprog_id) . " $firmanavn?');\"";

	print "</td>";
	print "<td colspan='2' width='20%'><br></td>"; 
}
print "</tr>";
print "</form>\n";

#print "<tr><td colspan=5><hr></td></tr>\n";
print "</tbody></table></td></tr>"; # <- TABEL 1.2.4.3
print "</tbody></table></td></tr>"; # <- TABEL 1.2.4

print "</tbody></table></td></tr>"; # <- TABEL 1.2

print "</div>"; // Close form-wrapper

print "<tr><td align = 'center' valign = 'bottom'>\n";
if ($menu == 'T') {
} elseif ($menu == 'S') {

##############

 
// Store button HTML in a JavaScript variable
$tekst_historik = findtekst('130|Vis historik.', $sprog_id);
$tekst_kontokort = findtekst('132|Vis Kontokort.', $sprog_id);
$tekst_faktura = findtekst('129|Vis fakturaliste.', $sprog_id);
$tekst_jobliste = findtekst('312|Klik her for at åbne listen med arbejdskort.', $sprog_id);

$jobkort = db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__))['box7'];

// Build buttons HTML
$buttons_html = "<div class='sticky-custom-buttons' style='display: flex; justify-content: center; align-items: center; gap: 10px; padding: 10px 0; width: 100%; background: #f4f4f4; border-top: 2px solid #ddd;'>";

// Historik button
if ($popup) {
    $buttons_html .= "<button type='button' onclick=\"window.open('historikkort.php?id=$id&amp;returside=../includes/luk.php', 'historik')\" style='$buttonStyle; padding: 8px 16px; cursor: pointer;' title='$tekst_historik'>" . findtekst('131|Historik', $sprog_id) . "</button>";
} elseif ($returside != "historikkort.php") {
    $buttons_html .= "<button type='button' onclick=\"window.location.href='historikkort.php?id=$id&amp;returside=debitorkort.php'\" style='$buttonStyle; padding: 8px 16px; cursor: pointer;' title='$tekst_historik'>" . findtekst('131|Historik', $sprog_id) . "</button>";
} else {
    $buttons_html .= "<button type='button' onclick=\"window.location.href='historikkort.php?id=$id'\" style='$buttonStyle; padding: 8px 16px; cursor: pointer;' title='$tekst_historik'>" . findtekst('131|Historik', $sprog_id) . "</button>";
}

// Kontokort button
$buttons_html .= "<button type='button' onclick=\"window.location.href='rapport.php?rapportart=kontokort&amp;konto_fra=$kontonr&amp;konto_til=$kontonr&amp;returside=../debitor/debitorkort.php?id=$id'\" style='$buttonStyle; padding: 8px 16px; cursor: pointer;' title='$tekst_kontokort'>" . findtekst('133|Kontokort', $sprog_id) . "</button>";

// Fakturaliste button
if (substr($rettigheder, 5, 1) == '1') {
    $buttons_html .= "<button type='button' onclick=\"window.location.href='ordreliste.php?konto_id=$id&amp;valg=faktura&amp;returside=../debitor/debitorkort.php?id=$id'\" style='$buttonStyle; padding: 8px 16px; cursor: pointer;' title='$tekst_faktura'>" . findtekst('134|Fakturaliste', $sprog_id) . "</button>";
} else {
    $buttons_html .= "<button style='$buttonStyle; padding: 8px 16px; opacity: 0.5; cursor: not-allowed;' disabled>" . findtekst('134|Fakturaliste', $sprog_id) . "</button>";
}

// Stillingsliste button
if ($jobkort) {
    $buttons_html .= "<button type='button' onclick=\"window.location.href='jobliste.php?konto_id=$id&amp;returside=../debitor/debitorkort.php?id=$id'\" style='$buttonStyle; padding: 8px 16px; cursor: pointer;' title='$tekst_jobliste'>" . findtekst('38|Stillingsliste', $sprog_id) . "</button>";
} else {
    $buttons_html .= "<button style='$buttonStyle; padding: 8px 16px; opacity: 0.5; cursor: not-allowed;' disabled>" . findtekst('38|Stillingsliste', $sprog_id) . "</button>";
}

// Print button
$print_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 0 24 24" width="14px" fill="#FFFFFF" style="vertical-align: middle; margin-right: 5px;"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>';
$buttons_html .= "<button type='button' onclick='printPurchaseHistory()' style='$buttonStyle; padding: 8px 16px; cursor: pointer; display: flex; align-items: center;' title='Print købshistorik'>" . $print_icon . "Print</button>";

$buttons_html .= "</div>";

// Escape for JavaScript
$buttons_html_escaped = str_replace("'", "\\'", $buttons_html);
$buttons_html_escaped = str_replace("\n", "", $buttons_html_escaped);



} else {
	print "<table width='100%' align='center' border='0' cellspacing='1' cellpadding='0'><tbody>"; # TABEL 1.3 ->
	print "<td width='25%' $top_bund>&nbsp;</td>\n";
	$tekst = findtekst('130|Vis historik.', $sprog_id);
	if ($popup) {
		print "<td width='10%' $top_bund ";
		print "onClick=\"javascript:historik=window.open('historikkort.php?id=$id&returside=../includes/luk.php',";
		print "'historik','" . $jsvars . "');historik.focus();' onMouseOver=\"this.style.cursor = 'pointer'\" ";
		print "title='$tekst'>" . findtekst('131|Historik', $sprog_id) . "<!--tekst 131--></td>\n";
	} elseif ($returside != "historikkort.php") {
		print "<td width='10%' $top_bund title='$tekst'><!--tekst 130-->";
		print "<a href=historikkort.php?id=$id&returside=debitorkort.php>" . findtekst('131|Historik', $sprog_id) . "<!--tekst 131--></td>\n";
	} else {
		print "<td width='10%' $top_bund title='$tekst'><!--tekst 130-->";
		print "<a href=historikkort.php?id=$id>" . findtekst('131|Historik', $sprog_id) . "<!--tekst 131--></td>\n";
	}
	$tekst = findtekst('132|Vis Kontokort.', $sprog_id);
	if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:kontokort=window.open('rapport.php?rapportart=kontokort&konto_fra=$kontonr&konto_til=$kontonr&returside=../includes/luk.php','kontokort','" . $jsvars . "');kontokort.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">" . findtekst('133|Kontokort', $sprog_id) . "<!--tekst 133--></td>\n";
	else print "<td width=\"10%\" $top_bund  title=\"$tekst\"><!--tekst 132--><a href=rapport.php?rapportart=kontokort&konto_fra=$kontonr&konto_til=$kontonr&returside=../debitor/debitorkort.php?id=$id>" . findtekst('133|Kontokort', $sprog_id) . "<!--tekst 133--></td>\n";
	$tekst = findtekst('129|Vis fakturaliste.', $sprog_id);
	if (substr($rettigheder, 5, 1) == '1') {
		if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:d_ordrer=window.open('ordreliste.php?konto_id=$id&valg=faktura&returside=../includes/luk.php','d_ordrer','" . $jsvars . "');d_ordrer.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">" . findtekst('134|Fakturaliste', $sprog_id) . "<!--tekst 134--></td>\n";
		else print "<td width=\"10%\" $top_bund  title=\"$tekst\"><!--tekst 129--><a href=ordreliste.php?konto_id=$id&valg=faktura&returside=../debitor/debitorkort.php?id=$id>" . findtekst('134|Fakturaliste', $sprog_id) . "<!--tekst 134--></td>\n";
	} else print "<td width=\"10%\" $top_bund><span style=\"color:#999;\">" . findtekst('134|Fakturaliste', $sprog_id) . "<!--tekst 134--></span></td>\n";
	$r = db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__));
	$jobkort = $r['box7'];
	if ($jobkort) {
		$tekst = findtekst('312|Klik her for at åbne listen med arbejdskort.', $sprog_id); #"Klik her for at &aring;bne listen med arbejdskort"
		print "<td width=\"10%\" $top_bund title=\"$tekst\"><!--tekst 312--><a href=jobliste.php?konto_id=$id&returside=debitorkort.php>" . findtekst('38|Stillingsliste', $sprog_id) . "<!--tekst 38--></td>\n";
	} else print "<td width=\"10%\"  $top_bund><span style=\"color:#999;\">" . findtekst('38|Stillingsliste', $sprog_id) . "<!--tekst 38--></span></td>\n";
	print "<td width=\"25%\" $top_bund>&nbsp;</td>\n";
	print "</td></tbody></table></td></tr>"; # <- TABEL 1.3 
	print "</tbody></table>"; # <- TABEL 1
}

function split_navn($firmanavn)
{
	$y = 0;
	$tmp = array();
	$tmp = explode(" ", $firmanavn);
	$x = count($tmp) - 1;
	$efternavn = $tmp[$x];
	while ($y < $x - 1) {
		$fornavn .= $tmp[$y] . " ";
		$y++;
	}
	$fornavn .= $tmp[$y];
	return ($fornavn . "," . $efternavn);
}

if (!$id) {
	print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/cvrapiopslag.js\"></script>\n";
}

##################


################## PURCHASE HISTORY GRID ##################
if ($id > 0) {
    // Start purchase history wrapper - separate from form
    echo "<div class='purchase-history-wrapper'>";
    
$purchase_columns = [
    [
        'field' => 'dato',
        'headerName' => 'Date',
        'type' => 'text',
        'width' => '1',
        'sortable' => true,
        'searchable' => true,
        'align' => 'left',
        'sqlOverride' => "dato",
        'render' => function($value, $row, $column) {
            $vare_id = isset($row['vare_id']) ? $row['vare_id'] : '';
            return "<td align='{$column['align']}' data-vare-id='{$vare_id}'>{$value}</td>";
        }
    ],
    [
        'field' => 'varenr',
        'headerName' => 'Item No.',
        'type' => 'text',
        'width' => '1',
        'sortable' => true,
        'searchable' => true,
        'align' => 'left',
        'sqlOverride' => 'varenr',
        'render' => function($value, $row, $column) {
            $vare_id = isset($row['vare_id']) ? $row['vare_id'] : '';
            return "<td align='{$column['align']}' data-vare-id='{$vare_id}'>{$value}</td>";
        }
    ],
    [
        'field' => 'varenavn',
        'headerName' => 'Item Name',
        'type' => 'text',
        'width' => '3',
        'sortable' => true,
        'searchable' => true,
        'align' => 'left',
        'sqlOverride' => 'varenavn',
        'render' => function($value, $row, $column) {
            $vare_id = isset($row['vare_id']) ? $row['vare_id'] : '';
            return "<td align='{$column['align']}' data-vare-id='{$vare_id}'>{$value}</td>";
        }
    ],
    [
        'field' => 'antal',
        'headerName' => 'Quantity',
        'type' => 'number',
        'width' => '1',
        'sortable' => true,
        'searchable' => true,
        'align' => 'left',
        'decimalPrecision' => 2,
        'sqlOverride' => 'antal',
        'render' => function($value, $row, $column) {
            $vare_id = isset($row['vare_id']) ? $row['vare_id'] : '';
            $formatted_value = DEFAULT_VALUE_GETTER($value, $row, $column);
            return "<td align='{$column['align']}' data-vare-id='{$vare_id}'>{$formatted_value}</td>";
        },
        'generateSearch' => function ($column, $term) {
            $term = db_escape_string($term);
            
            if (strstr($term, ':')) {
                list($num1, $num2) = explode(":", $term, 2);
                return "round(antal::numeric, 2) >= '" . usdecimal($num1) . "' 
                        AND 
                        round(antal::numeric, 2) <= '" . usdecimal($num2) . "'";
            } else {
                $term = usdecimal($term);
                return "round(antal::numeric, 2) >= $term 
                        AND 
                        round(antal::numeric, 2) <= $term";
            }
        }
    ],
    [
        'field' => 'salgspris',
        'headerName' => 'Sales Price',
        'type' => 'number',
        'width' => '1',
        'sortable' => true,
        'searchable' => true,
        'align' => 'left',
        'decimalPrecision' => 2,
        'sqlOverride' => 'salgspris',
        'render' => function($value, $row, $column) {
            $vare_id = isset($row['vare_id']) ? $row['vare_id'] : '';
            $formatted_value = DEFAULT_VALUE_GETTER($value, $row, $column);
            return "<td align='{$column['align']}' data-vare-id='{$vare_id}'>{$formatted_value}</td>";
        },
        'generateSearch' => function ($column, $term) {
            $term = db_escape_string($term);
            
            if (strstr($term, ':')) {
                list($num1, $num2) = explode(":", $term, 2);
                return "round(salgspris::numeric, 2) >= '" . usdecimal($num1) . "' 
                        AND 
                        round(salgspris::numeric, 2) <= '" . usdecimal($num2) . "'";
            } else {
                $term = usdecimal($term);
                return "round(salgspris::numeric, 2) >= $term 
                        AND 
                        round(salgspris::numeric, 2) <= $term";
            }
        }
    ],
    [
        'field' => 'total',
        'headerName' => 'Total',
        'type' => 'number',
        'width' => '1',
        'sortable' => true,
        'searchable' => true,
        'align' => 'left',
        'decimalPrecision' => 2,
        'sqlOverride' => 'total',
        'render' => function($value, $row, $column) {
            $vare_id = isset($row['vare_id']) ? $row['vare_id'] : '';
            $formatted_value = DEFAULT_VALUE_GETTER($value, $row, $column);
            return "<td align='{$column['align']}' data-vare-id='{$vare_id}'>{$formatted_value}</td>";
        },
        'generateSearch' => function ($column, $term) {
            $term = db_escape_string($term);
            
            if (strstr($term, ':')) {
                list($num1, $num2) = explode(":", $term, 2);
                return "round(total::numeric, 2) >= '" . usdecimal($num1) . "' 
                        AND 
                        round(total::numeric, 2) <= '" . usdecimal($num2) . "'";
            } else {
                $term = usdecimal($term);
                return "round(total::numeric, 2) >= $term 
                        AND 
                        round(total::numeric, 2) <= $term";
            }
        }
    ]
];

// Get the month filter from URL or default to 'all'
$month_filter = if_isset($_GET, 'all', 'months');

// Build the date filter condition
$date_condition = "1=1";
if ($month_filter != 'all' && is_numeric($month_filter)) {
    $months_ago = date('Y-m-d', strtotime("-$month_filter months"));
    $date_condition = "ordrer.ordredate >= '$months_ago'";
}

// Handle date range search
$date_where = "";
if (isset($_GET['search']['purchase_history']['dato'])) {
    $date_search = $_GET['search']['purchase_history']['dato'];
    
    if (strpos($date_search, ' : ') !== false) {
        list($start, $end) = explode(' : ', $date_search);
        $start_obj = DateTime::createFromFormat('d-m-Y', trim($start));
        $end_obj = DateTime::createFromFormat('d-m-Y', trim($end));
        
        if ($start_obj && $end_obj) {
            $date_where = "ordrer.ordredate BETWEEN '" . $start_obj->format('Y-m-d') . "' 
                          AND '" . $end_obj->format('Y-m-d') . "'";
        }
    } else {
        $date_obj = DateTime::createFromFormat('d-m-Y', trim($date_search));
        if ($date_obj) {
            $date_where = "ordrer.ordredate = '" . $date_obj->format('Y-m-d') . "'";
        }
    }
    
    unset($_GET['search']['purchase_history']['dato']);
}

if ($date_where) {
    $date_condition .= " AND " . $date_where;
}

// Define the grid data
$purchase_grid = [
    'query' => "
        SELECT 
            dato,
            varenr,
            varenavn,
            antal,
            salgspris,
            total,
            vare_id
        FROM (
            SELECT 
                TO_CHAR(ordrer.ordredate, 'DD-MM-YYYY') AS dato,
                varer.varenr AS varenr,
                varer.id AS vare_id,
                varer.beskrivelse AS varenavn,
                SUM(ordrelinjer.antal) AS antal,
                ordrelinjer.pris AS salgspris,
                (SUM(ordrelinjer.antal) * ordrelinjer.pris) AS total
            FROM ordrelinjer
            INNER JOIN ordrer ON ordrelinjer.ordre_id = ordrer.id
            INNER JOIN varer ON ordrelinjer.vare_id = varer.id
            WHERE ordrer.konto_id = '$id' 
            AND $date_condition
            GROUP BY varer.varenr, varer.id, varer.beskrivelse, ordrelinjer.pris, ordrer.ordredate
        ) AS purchase_history
        WHERE {{WHERE}}
        ORDER BY {{SORT}}
    ",
    'columns' => $purchase_columns,
    'filters' => []
];

    // Render the purchase history grid
    create_datagrid('purchase_history', $purchase_grid);
    
    echo "</div>"; // Close purchase-history-wrapper  
}else{
    error_log("Invalid customer ID for purchase history grid: " . htmlspecialchars($id));
}

echo "</div>"; // Close outer-datatable-wrapper

################## END PURCHASE HISTORY GRID ##################

// Updated JavaScript for clickable rows - click anywhere in the row
echo <<<SCRIPT
<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        var gridTable = document.querySelector('#datatable-purchase_history tbody');
        
        if (gridTable) {
            gridTable.addEventListener('click', function(e) {
                var cell = e.target.closest('td');
                
                if (cell && cell.hasAttribute('data-vare-id')) {
                    var vareId = cell.getAttribute('data-vare-id');
                    
                    if (vareId) {
                        window.location.href = '../lager/varekort.php?id=' 
                            + encodeURIComponent(vareId) 
                            + '&returside=../debitor/debitorkort.php?id=$id';
                    }
                }
            });
            
            // Add hover effect to all rows with data
            var rows = gridTable.querySelectorAll('tr:not(.filler-row)');
            rows.forEach(function(row) {
                var cells = row.querySelectorAll('td[data-vare-id]');
                if (cells.length > 0) {
                    row.style.cursor = 'pointer';
                }
            });
        }
    }, 600);
});
</script>
SCRIPT;



##################

if ($menu == 'T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

$steps = array();
$steps[] = array(
	"selector" => 'select[name="kontotype"]',
	"content" => findtekst('2627|Her vælger du kundetype (privat eller erhverv)', $sprog_id) . "."
);
$steps[] = array(
	"selector" => 'input[type="text"][name="ny_kontonr"]',
	"content" => findtekst('2628|Indsæt kontonummer, eller lad systemet gøre det for dig ved at hoppe videre til næste felt', $sprog_id) . "."
);
$steps[] = array(
	"selector" => 'input[type="text"][name="fornavn"], input[type="text"][name="efternavn"]',
	"content" => findtekst('2629|Angiv kundens navn', $sprog_id) . "."
);
$steps[] = array(
	"selector" => 'input[type="text"][name="firmanavn"]',
	"content" => findtekst('2630|Angiv kundens firmanavn', $sprog_id) . "."
);
$steps[] = array(
	"selector" => 'input[type="text"][name="email"], input[type="checkbox"][name="mailfakt"]',
	"content" => findtekst('2631|Kundens e-mail indtastes her. Hvis du vil have, at systemet som standard sender e-mails, når du fakturerer en ordre, kan du sætte hak i \'Brug mail\'', $sprog_id) . "."
);
$steps[] = array(
	"selector" => 'select[name="betalingsbet"], input[type="text"][name="betalingsdage"]',
	"content" => findtekst('2632|Du kan opstille kundens betalingsbetingelser her', $sprog_id) . "."
);
$steps[] = array(
	"selector" => 'input[type="text"][name="cvrnr"]',
	"content" => findtekst('2633|Når du indtaster en ny kunde, kan du lave et CVR-opslag ved at sætte \'/\' før og efter CVR-nummeret.<br><br>Prøv f.eks. med <b>/20756438/</b>', $sprog_id) . "."
);
$steps[] = array(
	"selector" => 'input[type="text"][name="felt_1"], input[type="text"][name="felt_5"]',
	"content" => findtekst('2634|Her kan du oprette op til 5 selvdefinerede felter. Du kan kontakte Saldi-teamet, hvis du ønsker at tilpasse felternes tekst, så den passer til indholdet.', $sprog_id)
);

include(__DIR__ . "/../includes/tutorial.php");
create_tutorial("debkort", $steps);
?>

<?php
// Get background color for styling
if (preg_match('/background-color:([a-fA-F0-9#]+)/', $topStyle, $matches)) {
    $backgroundColor = $matches[1];
} else {
    $backgroundColor = '#114691';
}
?>

<style>
.daterangepicker .ranges li.active {
    background-color: <?= htmlspecialchars($backgroundColor) ?> !important;
}
.daterangepicker td.active{
     background-color: <?= htmlspecialchars($backgroundColor) ?> !important;
}
</style>

<style>
.daterangepicker {
    font-size: 12px !important;
    width: auto !important;
}

.daterangepicker .calendar-table {
    font-size: 11px !important;
}

.daterangepicker td, 
.daterangepicker th {
    min-width: 28px !important;
    height: 28px !important;
    line-height: 28px !important;
    padding: 2px !important;
}

.daterangepicker .calendar-table .next span, 
.daterangepicker .calendar-table .prev span {
    border-width: 0 2px 2px 0 !important;
    padding: 3px !important;
}

.daterangepicker select.monthselect, 
.daterangepicker select.yearselect {
    font-size: 11px !important;
    padding: 2px !important;
    height: 26px !important;
}

.daterangepicker .ranges {
    width: 140px !important;
    font-size: 11px !important;
}

.daterangepicker .ranges li {
    padding: 6px 10px !important;
    font-size: 11px !important;
}

.daterangepicker .drp-buttons {
    padding: 6px !important;
}

.daterangepicker .drp-buttons .btn {
    font-size: 11px !important;
    padding: 4px 12px !important;
}

.daterangepicker .drp-calendar {
    max-width: 250px !important;
    padding: 6px !important;
}

.daterangepicker.show-calendar .drp-calendar.left {
    padding: 6px !important;
}

.daterangepicker.show-calendar .drp-calendar.right {
    padding: 6px !important;
}

/* Reduce month/year header size */
.daterangepicker .calendar-table thead tr:first-child th {
    padding: 4px 0 !important;
}

/* Adjust overall container */
.daterangepicker.drop-up {
    margin-bottom: 5px !important;
}

.daterangepicker .ranges li.active {
    background-color: <?= htmlspecialchars($backgroundColor) ?> !important;
}

.daterangepicker td.active{
     background-color: <?= htmlspecialchars($backgroundColor) ?> !important;
}
</style>





<script>
document.addEventListener('DOMContentLoaded', function() {
    var bruger_id = <?php echo json_encode($bruger_id); ?>;
    
    // Target the date input in the purchase history grid
    const dateInput = document.querySelector("input[name='search[purchase_history][dato]']");
    
    if (!dateInput) {
        console.log('Date input not found');
        return;
    }
    
    // Add autocomplete="off" to prevent browser history dropdown
    dateInput.setAttribute('autocomplete', 'off');
    dateInput.setAttribute('autocapitalize', 'off');
    dateInput.setAttribute('autocorrect', 'off');
    dateInput.setAttribute('spellcheck', 'false');
    
    var gridId = 'purchase_history';
    var field = 'dato';
    
    // Initialize variables
    var savedPreference = null;
    var startDate = moment();
    var endDate = moment();
    var chosenLabel = null;
    
    // Function to load saved preference from database
    function loadSavedPreference(callback) {
        $.ajax({
            url: 'save_date_settings.php',
            type: 'POST',
            data: {
                action: 'get_date_preference',
                grid_id: gridId,
                field: field,
                bruger_id: bruger_id
            },
            success: function(response) {
                try {
                    if (response && typeof response === 'string') {
                        response = JSON.parse(response);
                    }
                    
                    if (response && response.date_value !== undefined && response.date_value !== null && response.date_value !== '') {
                        if (callback) callback(response);
                    } else {
                        if (callback) callback(null);
                    }
                } catch(e) {
                    console.log('Error parsing response:', e);
                    if (callback) callback(null);
                }
            },
            error: function(xhr, status, error) {
                console.log('Error loading date preference:', error);
                if (callback) callback(null);
            }
        });
    }
    
    // Load saved preference BEFORE initializing picker
    loadSavedPreference(function(preference) {
        if (preference) {
            savedPreference = preference;
            chosenLabel = preference.range_type;
            
            // Parse the saved date value
            var dateValue = preference.date_value;
            if (dateValue.includes(' : ') || dateValue.includes(' - ')) {
                var separator = dateValue.includes(' : ') ? ' : ' : ' - ';
                var dates = dateValue.split(separator);
                
                if (dates.length >= 2) {
                    var parsedStart = moment(dates[0].trim(), 'DD-MM-YYYY', true);
                    var parsedEnd = moment(dates[1].trim(), 'DD-MM-YYYY', true);
                    
                    if (parsedStart.isValid() && parsedEnd.isValid()) {
                        startDate = parsedStart;
                        endDate = parsedEnd;
                    }
                }
            } else {
                var parsed = moment(dateValue, 'DD-MM-YYYY', true);
                if (parsed.isValid()) {
                    startDate = parsed;
                    endDate = parsed;
                }
            }
            
            // Set input value from saved preference
            var urlParams = new URLSearchParams(window.location.search);
            var searchKey = 'search[' + gridId + '][' + field + ']';
            var urlSearchValue = urlParams.get(searchKey);
            
            if (urlSearchValue && urlSearchValue.trim() !== '') {
                dateInput.value = urlSearchValue;
            } else {
                dateInput.value = '';
            }
        }
        
        initializePicker();
    });
    
    function initializePicker() {
        // Initialize daterangepicker
        $(dateInput).daterangepicker({
		    drops: 'up',
            singleDatePicker: false,
            showDropdowns: true,
            autoUpdateInput: false,
            autoApply: false,
            linkedCalendars: false,
            startDate: startDate,
            endDate: endDate,
            minYear: 1900,
            maxYear: parseInt(moment().format('YYYY'), 10) + 10,
            alwaysShowCalendars: true,
            showCustomRangeLabel: true,
            ranges: {
                'Clear': [],
                'I dag': [moment(), moment()],
                'I går': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Sidste 7 dage': [moment().subtract(6, 'days'), moment()],
                'Sidste 30 dage': [moment().subtract(29, 'days'), moment()],
                'Denne måned': [moment().startOf('month'), moment().endOf('month')],
                'Sidste måned': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'Sidste 3 måneder': [moment().subtract(3, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'Sidste 6 måneder': [moment().subtract(6, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'Sidste 12 måneder': [moment().subtract(12, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'Dette år': [moment().startOf('year'), moment().endOf('year')],
                'Sidste år': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
            },
            locale: {
                format: 'DD-MM-YYYY',
                separator: ' : ',
                applyLabel: 'Søg',
                cancelLabel: 'Ryd',
                fromLabel: 'Fra',
                toLabel: 'Til',
                customRangeLabel: 'Brugerdefineret',
                daysOfWeek: ['Sø', 'Ma', 'Ti', 'On', 'To', 'Fr', 'Lø'],
                monthNames: ['Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni',
                    'Juli', 'August', 'September', 'Oktober', 'November', 'December'
                ],
                firstDay: 1
            }
        });
        
        var picker = $(dateInput).data('daterangepicker');
        
        // Set the chosenLabel AFTER initialization
        if (savedPreference && chosenLabel !== null && chosenLabel !== undefined && chosenLabel !== 'Clear') {
            setTimeout(function() {
                if (picker) {
                    picker.chosenLabel = chosenLabel;
                    
                    if (chosenLabel in picker.ranges) {
                        picker.setStartDate(picker.ranges[chosenLabel][0]);
                        picker.setEndDate(picker.ranges[chosenLabel][1]);
                    }
                    
                    picker.updateCalendars();
                    picker.updateView();
                }
            }, 100);
        }
        
        // When user clicks "Søg" (Apply) button
        $(dateInput).on('apply.daterangepicker', function(ev, picker) {
            if (picker.chosenLabel === 'Clear') {
                $(this).val('');
                var form = $(this).closest('form');
                if (form.length > 0) {
                    form.submit();
                }
                picker.hide();
                return;
            }
            
            var selectedStartDate = picker.startDate.format('DD-MM-YYYY');
            var selectedEndDate = picker.endDate.format('DD-MM-YYYY');
            
            var displayValue;
            if (selectedStartDate === selectedEndDate) {
                displayValue = selectedStartDate;
            } else {
                displayValue = selectedStartDate + ' : ' + selectedEndDate;
            }
            
            $(this).val(displayValue);
            
            var rangeTypeToSave = picker.chosenLabel;
            if (!rangeTypeToSave || rangeTypeToSave === 'Custom Range' || rangeTypeToSave === 'Brugerdefineret') {
                rangeTypeToSave = 'Custom';
            }
            
            // Save preference
            $.ajax({
                url: 'save_date_settings.php',
                type: 'POST',
                data: {
                    action: 'save_date_preference',
                    grid_id: gridId,
                    field: field,
                    range_type: rangeTypeToSave,
                    date_value: displayValue,
                    bruger_id: bruger_id
                },
                success: function(response) {
                    var form = $(dateInput).closest('form');
                    if (form.length > 0) {
                        form.submit();
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error saving date preference:', error);
                }
            });
        });
        
        // When user clicks "Ryd" (Cancel) button
        $(dateInput).on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            var form = $(this).closest('form');
            if (form.length > 0) {
                form.submit();
            }
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait for grid to be fully rendered
    setTimeout(function() {
        var gridForm = document.querySelector('#datatable-wrapper-purchase_history form');
        
        if (gridForm) {
            // Get current URL parameters we want to preserve
            var currentId = <?php echo json_encode($id); ?>;
            var returside = <?php echo json_encode($returside); ?>;
            
            // Add hidden inputs to preserve these parameters
            var hiddenInputs = [
                { name: 'tjek', value: currentId },
                { name: 'id', value: currentId },
                { name: 'returside', value: returside }
            ];
            
            hiddenInputs.forEach(function(input) {
                // Check if input already exists
                var existingInput = gridForm.querySelector('input[name="' + input.name + '"]');
                if (!existingInput) {
                    var hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = input.name;
                    hiddenInput.value = input.value;
                    gridForm.appendChild(hiddenInput);
                }
            });
            
            console.log('Grid form parameters added:', hiddenInputs);
        } else {
            console.log('Grid form not found');
        }
    }, 200);
});
</script>

<style>
	body {
		padding: 0;
		height: 100vh;
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}

	.outer-datatable-wrapper {
		width: 100%;
		flex: 1;
		overflow: hidden;
		display: flex;
		flex-direction: column;
	}

	.form-wrapper {
		flex-shrink: 0;
		overflow-y: auto;
		overflow-x: hidden;
		max-height: 50vh;
		border-bottom: 2px solid #ddd;
		padding-bottom: 10px;
	}

	.purchase-history-wrapper {
		flex: 1;
		overflow: hidden;
		display: flex;
		flex-direction: column;
		min-height: 200px;
		padding-top: 10px;
	}

	#datatable-wrapper-purchase_history {
		height: 100%;
		display: flex;
		flex-direction: column;
	}

	/* Make the search wrapper fill available space */
	#datatable-wrapper-purchase_history .datatable-search-wrapper {
		flex: 1;
		overflow: auto;
		position: relative;
		display: flex;
		flex-direction: column;
	}

	/* Make the form fill its container */
	#datatable-wrapper-purchase_history form {
		display: flex;
		flex-direction: column;
		min-height: 100%;
	}

	/* Make the table fill and stretch */
	#datatable-wrapper-purchase_history table.datatable {
		width: 100%;
		border-collapse: collapse;
		flex: 1;
	}

	/* The filler row should have height: 100% to expand */
	#datatable-wrapper-purchase_history tbody tr.filler-row td {
		height: 100%;
		background: transparent;
	}

	/* Custom buttons container */
	.sticky-custom-buttons {
		position: sticky;
		bottom: 20px;
		flex-shrink: 0;
		background-color: #f4f4f4;
		border-top: 2px solid #ddd;
		padding: 10px 0;
		z-index: 500;
	}

	.sticky-custom-buttons button {
		transition: all 0.2s ease;
		min-width: 120px;
	}

	.sticky-custom-buttons button:hover:not(:disabled) {
		opacity: 0.8;
		transform: translateY(-1px);
	}

	a:link {
		text-decoration: none;
	}
	
	.dropdown{
		display:none !important;
	}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait a moment for the grid to render
    setTimeout(function() {
        var tfoot = document.querySelector('#datatable-wrapper-purchase_history tfoot');
        if (tfoot) {
            // Create a new row
            var row = document.createElement('tr');
            var cell = document.createElement('td');
            cell.colSpan = 100;
            cell.style.padding = '0';
            cell.style.margin = '0';
            
            // Insert the translated buttons (directly from PHP)
            cell.innerHTML = '<?php echo $buttons_html_escaped; ?>';
            
            // Fix any styling on the buttons container
            var buttonsDiv = cell.querySelector('.sticky-custom-buttons');
            if (buttonsDiv) {
                buttonsDiv.style.position = 'static';
                buttonsDiv.style.margin = '0';
                buttonsDiv.style.padding = '10px 0';
            }
            
            row.appendChild(cell);
            tfoot.appendChild(row);
            
            // Add inline style to remove gaps
            var style = document.createElement('style');
            style.textContent = '#datatable-wrapper-purchase_history tfoot tr:last-child { border-spacing: 0 !important; margin: 0 !important; }';
            document.head.appendChild(style);
        }
    }, 500);
});
</script>

<script>
function printPurchaseHistory() {
    // Get the datatable wrapper
    var datatableWrapper = document.querySelector('#datatable-wrapper-purchase_history');
    
    if (!datatableWrapper) {
        alert('Could not find purchase history table');
        return;
    }
    
    // Clone the wrapper to manipulate it
    var clonedWrapper = datatableWrapper.cloneNode(true);
    
    // Remove footer-box by ID
    var footerBox = clonedWrapper.querySelector('#footer-box');
    if (footerBox) {
        footerBox.remove();
    }
    
    // Remove all elements with class "dropdown"
    var dropdowns = clonedWrapper.querySelectorAll('.dropdown');
    dropdowns.forEach(function(dropdown) {
        dropdown.remove();
    });

    var customButtons = clonedWrapper.querySelectorAll('.sticky-custom-buttons');
    customButtons.forEach(function(buttons) {
        buttons.remove();
    });

    // Remove the second tr from thead
    var thead = clonedWrapper.querySelector('thead');
    if (thead) {
        var rows = thead.querySelectorAll('tr');
        if (rows.length > 1) {
            rows[1].remove();
        }
    }
    
    // Get the table
    var table = clonedWrapper.querySelector('table.datatable#datatable-purchase_history');
    
    if (!table) {
        alert('Could not find datatable');
        return;
    }
    
    // Get customer info
    var firmanavn = <?php echo json_encode($firmanavn ?? ''); ?>;
    var kontonr = <?php echo json_encode($kontonr ?? ''); ?>;
    
    // Get the background color from PHP
    var backgroundColor = <?php echo json_encode($backgroundColor); ?>;
    
    // Create print window
    var printWindow = window.open('', 'PrintPurchaseHistory', 'width=900,height=700');
    
    // Build the print content
    var printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Purchase History - ${firmanavn}</title>
            <style>
                @media print {
                    @page {
                        size: A4 landscape;
                        margin: 1cm;
                    }
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    margin: 20px;
                }
                h1 {
                    font-size: 18px;
                    margin-bottom: 5px;
                }
                h2 {
                    font-size: 14px;
                    margin-top: 0;
                    color: #666;
                }
                table.datatable {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }
                table.datatable th {
                    background-color: ${backgroundColor} !important;
                    color: white !important;
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                    font-weight: bold;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                table.datatable td {
                    border: 1px solid #ddd;
                    padding: 6px;
                }
                table.datatable tr:nth-child(even) {
                    background-color: #f9f9f9;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .print-date {
                    text-align: right;
                    font-size: 10px;
                    color: #666;
                    margin-bottom: 20px;
                }
                /* Hide any remaining unwanted elements */
                .dropdown {
                    display: none !important;
                }
                #footer-box {
                    display: none !important;
                }
                /* Ensure background colors print */
                * {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
            </style>
        </head>
        <body>
            <h1>Purchase History</h1>
            <h2>${firmanavn} (Customer No.: ${kontonr})</h2>
            <div class="print-date">
                Printed: ${new Date().toLocaleDateString('en-GB')} ${new Date().toLocaleTimeString('en-GB')}
            </div>
            ${table.outerHTML}
        </body>
        </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    // Wait for content to load, then print
    printWindow.onload = function() {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };
}
</script>
