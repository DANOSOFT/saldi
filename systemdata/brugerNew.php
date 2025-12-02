<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------------systemdata/brugere.php-----patch 4.0.8 ----2023-07-23-----
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20150327 CA  - Topmenudesign tilføjet                             søg 20150327
// 20161104	PHR	- Ændret kryptering af adgangskode
// 20181220 MSC - Rettet isset fejl
// 20190221 MSC - Rettet topmenu design
// 20190225 MSC - Rettet topmenu design
// 20190321 PHR - Added 'read only' attribut at 'varekort'
// 20190415 PHR - Corrected an error in module order printet on screen, resulting in wrong rights to certain modules
// 20200709 PHR - Various changes in variable names and user deletion.
// 20210711 LOE - Translated some texts to Norsk and English from Dansk
// 20210828 LOE - Added a functionality to enable users select language from user's page
// 20210831 LOE - Added more funtionalities
// 20210901 LOE - This block of code added to authenticate user IP
// 20210908 LOE - Added input box for IP addresses
// 20210909 LOE - Modified some codes relating to Ip
// 20211015 LOE - Modified some codes to adjust to IP moved to settings table
// 20220514 MSC - Implementing new design
// 20230316 PHR Replaced *1 by (int)

@session_start();
$s_id=session_id();

$modulnr=1;
$title="Brugere";
$css="../css/standard.css";

$employeeId=$rights=$roRights=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

if (!isset ($colbg)) $colbg = NULL;
$da = str_replace(" ", "",(findtekst('1141|Debitorapport', $sprog_id)));
$ka = str_replace(" ", "",(findtekst('1140|Kreditorapport', $sprog_id)));

$kontoplan   	  =  lcfirst(findtekst('113|Kontoplan', $sprog_id));		 $indstillinger	  = lcfirst(findtekst('122|Indstillinger', $sprog_id)); #20210711
$kassekladde 	  =  lcfirst(findtekst('601|Kassekladde', $sprog_id));		 $regnskab		  = lcfirst(findtekst('322|Regnskab', $sprog_id));
$finansrapport    =  lcfirst(findtekst('895|Finansrapport', $sprog_id));	 $debitorordre	  = lcfirst(findtekst('1255|Debitorordre', $sprog_id));
$debitorkonti     =  lcfirst(findtekst('1256|Debitorkonti', $sprog_id)); 	 $kreditorordre   = lcfirst(findtekst('1257|Kreditorordre', $sprog_id));
$kreditorkonti    =  lcfirst(findtekst('1258|Kreditorkonti', $sprog_id));	 $varer 		  = lcfirst(findtekst('609|Varer', $sprog_id));
$enheder		  =  lcfirst(findtekst('1259|Enheder', $sprog_id));			 $backup		  = lcfirst(findtekst('521|Sikkerhedskopi', $sprog_id));
$debitorrapport   =  lcfirst($da);											 $kreditorrapport = lcfirst($ka);
$produktionsordre =  lcfirst(findtekst('1260|Produktionsordre', $sprog_id)); $varerapport	  = lcfirst(findtekst('965|Varerapport', $sprog_id));



$modules=array($kontoplan,$indstillinger,$kassekladde,$regnskab,$finansrapport,$debitorordre,$debitorkonti,
$kreditorordre,$kreditorkonti,$varer,$enheder,$backup,
$debitorrapport,$kreditorrapport,$produktionsordre,$varerapport);
#$modules=array('kontoplan','indstillinger','kassekladde','regnskab','finansrapport','debitorordre','debitorkonti','kreditorordre','kreditorkonti','varer','enheder','backup','debitorrapport','kreditorrapport','produktionsordre','varerapport');

// Get button colors from database (set in online.php, but ensure they're available)
if (!isset($buttonColor)) {
	$qtxt = "select var_value from settings where var_name = 'buttonColor' and var_grp = 'colors' and user_id = '$bruger_id'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$buttonColor = $r['var_value'];
	} else {
		$buttonColor = '#114691'; // Default button color
	}
}
if (!isset($buttonTxtColor)) {
	$qtxt = "select var_value from settings where var_name = 'buttonTxtColor' and var_grp = 'colors' and user_id = '$bruger_id'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$buttonTxtColor = $r['var_value'];
	} else {
		$buttonTxtColor = '#ffffff'; // Default button text color
	}
}
// Calculate hover colors
$buttonColorHover = darkenColor($buttonColor, 0.2);
$buttonColorBorder = darkenColor($buttonColor, 0.15);
$buttonColorBorderHover = darkenColor($buttonColor, 0.25);
// Convert hex to rgba for box-shadow (add 66 for 40% opacity)
$buttonColorRgba = $buttonColor . '66';

// Modern CSS for user management page
print "<style>
	.user-management-container {
		padding: 20px;
		max-width: 1400px;
		margin: 0 auto;
	}
	.user-list-card {
		background: #fff;
		border-radius: 8px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		padding: 20px;
		margin-bottom: 30px;
	}
	.user-form-card {
		background: #fff;
		border-radius: 8px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		padding: 30px;
		margin-bottom: 20px;
	}
	.modern-table {
		width: 100%;
		border-collapse: collapse;
		margin: 10px 0;
		font-size: 13px;
	}
	.modern-table thead {
		background: $buttonColor;
		color: $buttonTxtColor;
	}
	.modern-table th {
		padding: 8px 8px;
		text-align: left;
		font-weight: 600;
		font-size: 11px;
		text-transform: uppercase;
		letter-spacing: 0.3px;
	}
	.modern-table tbody tr {
		border-bottom: 1px solid #e5e7eb;
		transition: background-color 0.2s;
	}
	.modern-table tbody tr:hover {
		background-color: #f9fafb;
	}
	.modern-table td {
		padding: 8px 8px;
		vertical-align: middle;
		font-size: 13px;
	}
	.modern-table tbody tr:last-child {
		border-bottom: none;
	}
	.user-link {
		color: $buttonColor;
		text-decoration: none;
		font-weight: 500;
		transition: color 0.2s;
	}
	.user-link:hover {
		color: $buttonColorHover;
		text-decoration: underline;
	}
	.permission-badge {
		display: inline-block;
		width: 18px;
		height: 18px;
		border-radius: 50%;
		text-align: center;
		line-height: 18px;
		font-size: 10px;
		font-weight: bold;
		margin: 0 1px;
	}
	.permission-full {
		background: #10b981;
		color: white;
	}
	.permission-readonly {
		background: #f59e0b;
		color: white;
	}
	.permission-none {
		background: #ef4444;
		color: white;
	}
	.permission-icon {
		font-size: 14px;
	}
	.modern-form-group {
		margin-bottom: 20px;
	}
	.form-row {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 20px;
		margin-bottom: 20px;
	}
	.form-row .modern-form-group {
		margin-bottom: 0;
	}
	.modern-form-label {
		display: block;
		margin-bottom: 8px;
		font-weight: 600;
		color: #374151;
		font-size: 14px;
	}
	.modern-input {
		width: 100%;
		padding: 12px 16px;
		border: 2px solid #e5e7eb;
		border-radius: 6px;
		font-size: 14px;
		transition: all 0.2s;
		box-sizing: border-box;
	}
	.modern-input:focus {
		outline: none;
		border-color: $buttonColor;
		box-shadow: 0 0 0 3px $buttonColorRgba;
	}
	.modern-select {
		width: 100%;
		padding: 12px 16px;
		border: 2px solid #e5e7eb;
		border-radius: 6px;
		font-size: 14px;
		background: white;
		cursor: pointer;
		transition: all 0.2s;
		box-sizing: border-box;
	}
	.modern-select:focus {
		outline: none;
		border-color: $buttonColor;
		box-shadow: 0 0 0 3px $buttonColorRgba;
	}
	.modern-checkbox {
		width: 20px;
		height: 20px;
		cursor: pointer;
		accent-color: $buttonColor;
	}
	.modern-checkbox:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}
	.permissions-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
		gap: 8px;
		margin: 10px 0;
		padding: 12px;
		background: #f9fafb;
		border-radius: 8px;
	}
	.permissions-grid-single {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(150px, max-content));
		gap: 8px;
		margin: 10px 0;
		padding: 12px;
		background: #f9fafb;
		border-radius: 8px;
	}
	.permission-item {
		display: flex;
		align-items: center;
		gap: 6px;
		padding: 4px 8px;
		background: white;
		border-radius: 4px;
		border: 1px solid #e5e7eb;
		white-space: nowrap;
	}
	.permission-item label {
		font-weight: 500;
		color: #374151;
		cursor: pointer;
		margin: 0;
		font-size: 13px;
	}
	.button-group {
		display: flex;
		gap: 12px;
		margin-top: 30px;
		flex-wrap: wrap;
	}
	.btn-modern {
		padding: 12px 24px;
		border: none;
		border-radius: 6px;
		font-size: 14px;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.2s;
		text-decoration: none;
		display: inline-block;
	}
	.btn-primary {
		background: $buttonColor;
		border: solid 1px $buttonColorBorder;
		color: $buttonTxtColor;
	}
	.btn-primary:hover {
		background: $buttonColorHover;
		border: solid 1px $buttonColorBorderHover;
		transform: translateY(-2px);
		box-shadow: 0 4px 12px $buttonColorRgba;
	}
	.btn-success {
		background: #10b981;
		color: white;
	}
	.btn-success:hover {
		background: #059669;
		transform: translateY(-2px);
		box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
	}
	.btn-danger {
		background: #ef4444;
		color: white;
	}
	.btn-danger:hover {
		background: #dc2626;
		transform: translateY(-2px);
		box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
	}
	.section-title {
		font-size: 24px;
		font-weight: 700;
		color: #1f2937;
		margin-bottom: 20px;
		padding-bottom: 10px;
	}
	.revisor-checkbox-wrapper {
		display: flex;
		align-items: center;
		gap: 8px;
	}
	.revisor-checkbox-wrapper input[type='checkbox'] {
		width: 20px;
		height: 20px;
		cursor: pointer;
		accent-color: #667eea;
	}
	.module-header {
		background: $buttonColor;
		color: $buttonTxtColor;
		padding: 15px 20px;
		border-radius: 8px 8px 0 0;
		margin: 0;
		font-weight: 600;
		font-size: 16px;
	}
	@media (max-width: 768px) {
		.permissions-grid {
			grid-template-columns: 1fr;
		}
		.form-row {
			grid-template-columns: 1fr;
		}
	}
</style>";

if ($menu=='T') {  # 20150327 start
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
    print "<div id=\"leftmenuholder\">";
    include_once 'left_menu.php';
    print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<div class='divSys'>";
	print "<div class='user-management-container'>";
} else {
	include("top.php");
	print "<div class='user-management-container'>";
}  # 20150327 stop

$ip_address = if_isset($_SERVER['REMOTE_ADDR']);
$proxy_ip = if_isset($_SERVER['HTTP_X_FORWARDED_FOR']);
$client_ip = if_isset($_SERVER['HTTP_CLIENT_IP']); #20210828

// Check for button submissions - buttons can send empty strings, so check if key exists
$addUser = isset($_POST['addUser']);
$deleteUser = isset($_POST['deleteUser']);
$id = if_isset($_POST['id']);
$updateUser = isset($_POST['updateUser']); // Check if key exists, not just value
$ret_id = if_isset($_GET['ret_id']);
$slet_id = if_isset($_GET['slet_id']);
$yd = get_ip(); #20211015

#var_dump($yd, $db);

// DEBUG: Write debugging output to file
$debug_file = __DIR__ . "/../temp/brugere_debug.log";

// Helper function to write debug output
function write_debug($content, $debug_file) {
	if (empty($content)) return;
	
	$debug_dir = dirname($debug_file);
	if (!is_dir($debug_dir)) {
		@mkdir($debug_dir, 0755, true);
	}
	
	$write_result = @file_put_contents($debug_file, $content, FILE_APPEND);
	if ($write_result === false) {
		// Try fallback location
		$fallback_file = "/tmp/brugere_debug.log";
		@file_put_contents($fallback_file, "[" . date('Y-m-d H:i:s') . "] Could not write to: $debug_file\n", FILE_APPEND);
		@file_put_contents($fallback_file, $content, FILE_APPEND);
	}
	return $write_result !== false;
}

$debug_output = "";

// Always write something if it's a POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$debug_output = "========================================\n";
	$debug_output .= "DEBUG INFORMATION - " . date('Y-m-d H:i:s') . "\n";
	$debug_output .= "========================================\n\n";

	// Check request method
	$debug_output .= "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
	$debug_output .= "addUser: " . ($addUser ? 'SET' : 'NOT SET') . "\n";
	$debug_output .= "updateUser: " . ($updateUser ? 'SET' : 'NOT SET') . "\n";
	$debug_output .= "deleteUser: " . ($deleteUser ? 'SET' : 'NOT SET') . "\n";
	$debug_output .= "POST['updateUser'] exists: " . (isset($_POST['updateUser']) ? 'YES' : 'NO') . "\n";
	$debug_output .= "POST['updateUser'] value: '" . (isset($_POST['updateUser']) ? $_POST['updateUser'] : 'NOT SET') . "'\n";
	$debug_output .= "id: " . (isset($id) ? $id : 'NOT SET') . "\n";
	$debug_output .= "ret_id: " . (isset($ret_id) ? $ret_id : 'NOT SET') . "\n";
	$debug_output .= "IP Address: " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'UNKNOWN') . "\n";
	$debug_output .= "User Agent: " . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'UNKNOWN') . "\n\n";

	// Show all POST data
	$debug_output .= "--- All POST Data ---\n";
	$debug_output .= print_r($_POST, true);
	$debug_output .= "\n";
	
	// Write initial debug info immediately
	write_debug($debug_output, $debug_file);
}

if ($addUser || $updateUser) {
	$debug_output .= "\n--- Processing form submission ---\n";
	
	$tmp=if_isset($_POST['random']);
	$debug_output .= "Random field name: " . ($tmp ? $tmp : 'NOT SET') . "\n";
	
	if ($tmp && isset($_POST[$tmp])) {
		$brugernavn=trim($_POST[$tmp]);
		$debug_output .= "Username found in POST[$tmp]: " . $brugernavn . "\n";
	} else {
		$brugernavn = '';
		$debug_output .= "Username: NOT FOUND (checked POST['$tmp'])\n";
	}
	$kode=trim(if_isset($_POST['kode']));
	$kode2=trim(if_isset($_POST['kode2']));
	$tlf=trim(if_isset($_POST['tlf']));
	$email=trim(if_isset($_POST['email']));
	$medarbejder=trim(if_isset($_POST['medarbejder']));
	$employeeId=if_isset($_POST['employeeId']);
	$twofactor=if_isset($_POST['twofactor']);
	
	$debug_output .= "Password: " . ($kode ? 'SET (' . strlen($kode) . ' chars)' : 'NOT SET') . "\n";
	$debug_output .= "Password2: " . ($kode2 ? 'SET (' . strlen($kode2) . ' chars)' : 'NOT SET') . "\n";
	$debug_output .= "EmployeeId: " . print_r($employeeId, true) . "\n";
	$debug_output .= "TwoFactor: " . ($twofactor ? 'SET' : 'NOT SET') . "\n";
	if($twofactor){
		$twofactor = 't';  // PostgreSQL boolean true
	} else {
		$twofactor = 'f';  // PostgreSQL boolean false
	}
	// $restore_user = if_isset($_POST['ruser_ip']); #20210831
	$insert_ip = if_isset($_POST['insert_ip']) ? trim($_POST['insert_ip']) : ''; #20210908
	// $user_ip = if_isset($_POST['user_ip']); #20210831
	 $re_id=if_isset($_POST['re_id']); #20210909
	if($insert_ip){
	$user_ip=$insert_ip;
	// input_ip($user_ip, $id);
	} #20210908
	$afd = if_isset($_POST['afdeling']) ? $_POST['afdeling'] : '';
	$rights = if_isset($_POST['rights']) ? $_POST['rights'] : array();
	$roRights = if_isset($_POST['roRights']) ? $_POST['roRights'] : array();
	
	$debug_output .= "Rights array: " . print_r($rights, true) . "\n";
	$debug_output .= "RoRights array: " . print_r($roRights, true) . "\n";
	$debug_output .= "Afdeling: " . ($afd ? $afd : 'NOT SET') . "\n";
	
	$rettigheder=NULL;
	for ($x=0;$x<16;$x++) {
		// Handle rights checkbox - if not set or empty, it's unchecked
		$rightChecked = (isset($rights[$x]) && $rights[$x]=='on');
		// Handle readonly rights checkbox - if not set or empty, it's unchecked
		$roRightChecked = (isset($roRights[$x]) && $roRights[$x]=='on' && !empty($roRights[$x]));
		
		if ($roRightChecked) {
			$rettigheder.='2';
		} elseif ($rightChecked) {
			$rettigheder.='1';
		} else {
			$rettigheder.='0';
		}
	}
	$debug_output .= "Rettigheder string: " . $rettigheder . "\n";
	$brugernavn=trim($brugernavn);
	$debug_output .= "\n--- Validation Checks ---\n";
	$debug_output .= "addUser condition: " . ($addUser ? 'TRUE' : 'FALSE') . "\n";
	$debug_output .= "updateUser condition: " . ($updateUser ? 'TRUE' : 'FALSE') . "\n";
	$debug_output .= "brugernavn: " . ($brugernavn ? $brugernavn : 'EMPTY') . "\n";
	$debug_output .= "kode: " . ($kode ? 'SET' : 'NOT SET') . "\n";
	$debug_output .= "id: " . (isset($id) && $id ? $id : 'NOT SET OR EMPTY') . "\n";
	
	if ($kode && $kode != $kode2) {
		$alerttext="Adgangskoder er ikke ens";
		$debug_output .= "ERROR: Passwords don't match!\n";
		print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
		$kode=NULL;
		// Only set ret_id if we're updating (id exists), not for new users
		if ($updateUser && $id) {
			$ret_id=$id;
		}
	}
	$tmp=substr($medarbejder,0,1);
	if (isset($employeeId[0]) && $employeeId[0]) {
		$employeeId[0]=(int)$employeeId[0];
	} else {
		$employeeId[0] = 0;
	}
	
	$debug_output .= "\n--- Add User Check ---\n";
	$debug_output .= "addUser && brugernavn && kode: " . (($addUser && $brugernavn && $kode) ? 'TRUE - Will execute add' : 'FALSE - Will NOT execute add') . "\n";
	
	if ($addUser && $brugernavn && $kode) {
		$debug_output .= ">>> EXECUTING ADD USER...\n";
		$brugernavn = db_escape_string($brugernavn);
		$insert_ip = db_escape_string($insert_ip);
		$tlf = db_escape_string($tlf);
		$email = db_escape_string($email);
		
		$query = db_select("select id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$debug_output .= "ERROR: User already exists!\n";
			$alerttext="Der findes allerede en bruger med brugenavn: $brugernavn!";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
#			print "<tr><td align=center>Der findes allerede en bruger med brugenavn: $brugernavn!</td></tr>";
		}	else {
			$debug_output .= "User doesn't exist, proceeding with insert...\n";
			if (!$regnaar) $regnaar=1;
			// Encrypt password - will need to re-encrypt after we get the user ID
			$kode_encrypted = saldikrypt(0, $kode);
			$kode_encrypted = db_escape_string($kode_encrypted);
			$qtxt = "insert into brugere (brugernavn,kode,rettigheder,regnskabsaar,ansat_id,ip_address,tlf,twofactor,email) ";
			$qtxt.= "values ('$brugernavn','$kode_encrypted','$rettigheder','$regnaar',$employeeId[0],'$insert_ip','$tlf','$twofactor','$email')";
			$debug_output .= "SQL INSERT: " . $qtxt . "\n";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$debug_output .= "INSERT executed!\n";
			$qtxt="select id from brugere where brugernavn = '$brugernavn'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r && isset($r['id']) && $r['id']) {
				$id=$r['id'];
				$debug_output .= "New user ID: $id\n";
				// Re-encrypt password with correct user ID
				$kode_encrypted = saldikrypt($id, $kode);
				$kode_encrypted = db_escape_string($kode_encrypted);
				db_modify("update brugere set kode='$kode_encrypted' where id=$id",__FILE__ . " linje " . __LINE__);
				$debug_output .= "Password re-encrypted with user ID!\n";
			} else {
				$debug_output .= "WARNING: Could not retrieve new user ID!\n";
			}
			// Write debug to file before redirect
			$debug_output .= "Redirecting...\n";
			$debug_output .= "\n========================================\n\n";
			write_debug($debug_output, $debug_file);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=brugere.php\">";
			exit;
		}
	} else {
		$debug_output .= "Add user condition NOT met - skipping add\n";
	}
	
	$debug_output .= "\n--- Update User Check ---\n";
	$debug_output .= "updateUser && id: " . (($updateUser && isset($id) && $id) ? 'TRUE - Will execute update' : 'FALSE - Will NOT execute update') . "\n";
	
	if ($updateUser && $id) {
		$debug_output .= ">>> EXECUTING UPDATE USER...\n";
		// Debug: Check if we're in update mode
		// Get brugernavn from database if not provided (due to random field name)
		if (!$brugernavn) {
			$debug_output .= "Username not in POST, fetching from database...\n";
			$query = db_select("select brugernavn from brugere where id = $id",__FILE__ . " linje " . __LINE__);
			if ($r = db_fetch_array($query)) {
				$brugernavn = $r['brugernavn'];
				$debug_output .= "Username from DB: " . $brugernavn . "\n";
			} else {
				// User not found - can't update
				$brugernavn = NULL;
				$debug_output .= "ERROR: User not found in database!\n";
			}
		}
		if ($brugernavn) {
			$debug_output .= "Username validated, proceeding with update...\n";
			// Escape values for SQL
			$brugernavn = db_escape_string($brugernavn);
			$insert_ip = db_escape_string($insert_ip);
			$tlf = db_escape_string($tlf);
			$email = db_escape_string($email);
			
			if ($kode && !strstr($kode,'**********') && strlen($kode) > 0) {
				$debug_output .= "Password provided, updating with password...\n";
				// Password was changed - encrypt and update
				$kode=saldikrypt($id,$kode);
				$kode = db_escape_string($kode);
				$qtxt = "update brugere set brugernavn='$brugernavn', kode='$kode', rettigheder='$rettigheder', ansat_id=$employeeId[0], ip_address = '$insert_ip', tlf = '$tlf', twofactor = '$twofactor', email = '$email' where id=$id";
				$debug_output .= "SQL UPDATE (with password): " . $qtxt . "\n";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$debug_output .= "UPDATE executed!\n";
				update_settings_value('afd', 'brugerAfd', $afd, '', $id);
				$debug_output .= "Settings updated for afdeling\n";
			} else {
				$debug_output .= "No password provided, updating without password...\n";
				// Password not changed - update without password
				$qtxt = "update brugere set brugernavn='$brugernavn', rettigheder='$rettigheder', ansat_id=$employeeId[0], ip_address = '$insert_ip', tlf = '$tlf', twofactor = '$twofactor', email = '$email' where id=$id";
				$debug_output .= "SQL UPDATE (no password): " . $qtxt . "\n";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$debug_output .= "UPDATE executed!\n";
				update_settings_value('afd', 'brugerAfd', $afd, '', $id);
				$debug_output .= "Settings updated for afdeling\n";
			}
			// Write debug to file before redirect
			$debug_output .= "Redirecting...\n";
			$debug_output .= "\n========================================\n\n";
			write_debug($debug_output, $debug_file);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=brugere.php\">";
			exit;
		} else {
			$debug_output .= "ERROR: Username is empty, cannot update!\n";
		}
	} else {
		$debug_output .= "Update user condition NOT met - skipping update\n";
	}
	
	// Write debug to file if we haven't exited yet
	if ($debug_output) {
		$debug_output .= "\n========================================\n\n";
		write_debug($debug_output, $debug_file);
	}
	
	// if($restore_user){
	// 	restore_user_ip($restore_user, $re_id); #20210831 + 20210909
	// }
	
} elseif (($deleteUser)) {
	$qtxt="select ansat_id from brugere where id ='$id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['ansat_id']) { 
		$qtxt="update ansatte set lukket='on', slutdate='".date("Y-m-d")."' where id = '$r[ansat_id]'";
		db_modify($qtxt,__FiLE__ . " linje " . __LINE__);
	}
	db_modify("delete from brugere where id = $id",__FILE__ . " linje " . __LINE__);
}

print "<div class='user-list-card'>";
print "<h2 class='section-title'>".findtekst('777|Brugere', $sprog_id)."</h2>";
print "<form name='bruger' action='brugere.php' method='post'>";
print "<table class='modern-table'><thead><tr>";
print "<th style='width: 40px;'>".findtekst('2562|Revisor', $sprog_id)."</th>";
print "<th>".findtekst('225|Brugernavn', $sprog_id)."</th>";
$da = str_replace(" ", "",(findtekst('1141|Debitorapport', $sprog_id))); #20210711
$ka = str_replace(" ", "",(findtekst('1140|Kreditorapport', $sprog_id)));

$Sikkerhedskopi = findtekst('521|Sikkerhedskopi', $sprog_id);   $Debitorrapport	   = findtekst('449|Debitorrapporter', $sprog_id);
$Varemodtagelse = findtekst('182|Varemodtagelse', $sprog_id);   $Kreditorrapport   = $ka;
$Varelager      = findtekst('1261|Varelager', $sprog_id);		$Produktionsordrer = findtekst('1260|Produktionsordre', $sprog_id);
$Kreditorkonti  = findtekst('1258|Kreditorkonti', $sprog_id);	$Varerapport	   = findtekst('965|Varerapport', $sprog_id);
$Kreditorordrer = findtekst('1257|Kreditorordre', $sprog_id);	$Debitorkonti	   = findtekst('1256|Debitorkonti', $sprog_id);
$Debitorordrer  = findtekst('1255|Debitorordre', $sprog_id);	$Finansrapport	   = findtekst('895|Finansrapport', $sprog_id);
$Regnskab		= findtekst('322|Regnskab', $sprog_id);			$Kassekladde	   = findtekst('601|Kassekladde', $sprog_id);
$Indstillinger  = findtekst('122|Indstillinger', $sprog_id);	$Kontoplan		   = findtekst('113|Kontoplan', $sprog_id);

#var_dump($Produksjonsordrer);

$modules=array($Sikkerhedskopi,$Debitorrapport,$Varemodtagelse,$Kreditorrapport,$Varelager,$Produktionsordrer,$Kreditorkonti,$Varerapport,
$Kreditorordrer,$Debitorkonti,$Debitorordrer,$Finansrapport,$Regnskab,$Kassekladde,$Indstillinger,$Kontoplan);
#$modules=array('Sikkerhedskopi','Debitorrapport','Varemodtagelse','Kreditorrapport','Varelager','Produktionsordrer','Kreditorkonti','Varerapport','Kreditorordrer','Debitorkonti','Debitorordrer','Finansrapport','Regnskab','Kassekladde','Indstillinger','Kontoplan');

// Print module headers
for ($x=0;$x<count($modules);$x++) {
	print "<th style='width: 32px; text-align: center; padding: 8px 4px;' title='$modules[$x]'>".substr($modules[$x], 0, 8)."</th>";
}
print "</tr></thead><tbody>"; 
$query = db_select("SELECT * FROM settings WHERE var_name = 'revisor' AND var_grp = 'system'", __FILE__ . " linje " . __LINE__);
if(db_num_rows($query) > 0){
	$r = db_fetch_array($query);
	$userId = $r['user_id'];
	if ($userId) {
		$disabled = "disabled";
	} else {
		$disabled = "";
	}
} else {
	$userId = 0;
	$disabled = "";
}

$query = db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	if ($row['id']!=$ret_id) {
		if ($row['ansat_id']) {
			$r2 = db_fetch_array(db_select("select initialer from ansatte where id = $row[ansat_id]",__FILE__ . " linje " . __LINE__));
		}	else {$r2['initialer']='';}
		print "<tr>";
		print "<td><div class='revisor-checkbox-wrapper'><input type='checkbox' name='revisor' id='$row[id]' class='modern-checkbox' $disabled " . (($userId == $row['id']) ? 'checked' : '') . "></div></td>";
		print "<td><a href='brugere.php?ret_id=$row[id]' class='user-link'>";
		($row['brugernavn'])?print htmlspecialchars($row['brugernavn']):print '?';
		print "</a></td>";
		for ($y=0; $y<=15; $y++) {
			$permission = substr($row['rettigheder'],$y,1);
			if ($permission == 2) {
				print "<td align='center' style='padding: 8px 4px;'><span class='permission-badge permission-readonly' title='$modules[$y]: ".findtekst('2475|Kun visning', $sprog_id)."'>R</span></td>";
			} elseif ($permission == 1) {
				print "<td align='center' style='padding: 8px 4px;'><span class='permission-badge permission-full' title='$modules[$y]: Full access'>✓</span></td>";
			} else {
				print "<td align='center' style='padding: 8px 4px;'><span class='permission-badge permission-none' title='$modules[$y]: No access'>✗</span></td>";
			}
		}
		print "</tr>";
	}
}
print "</tbody></table>";
print "</form>";
print "</div>"; // Close user-list-card
?>
	<script>
		const checkbox = document.querySelectorAll("[name=revisor]")
		const db = "<?php echo $db; ?>";
		const confirmMessage = <?php echo json_encode(findtekst('2563|Vil du gøre denne bruger til revisor? Kun én bruger kan have revisoradgang, og du kan ikke ændre hvilken bruger der er revisor uden at kontakte Saldi support.', $sprog_id)); ?>;
		// event listener for checkboxes
		checkbox.forEach((el) => {
		el.addEventListener("change", () => {
			// Check if checkbox is selected
			if (el.checked) {
				if (confirm(confirmMessage)) {
					const res = fetch("brugereRevisor.php",
					{
						method: "POST",
						headers: {
							"Content-Type": "application/x-www-form-urlencoded"
						},
						body: "id=" + el.id + "&db=" + db
					}
				);
					window.location.reload();
				} else {
					// Remove selection if user cancels
					el.checked = false;
				}
			} else {
				// Do nothing if checkbox is de-selected (no confirm dialog)
				// No popup if checkbox is de-selected
			}
		});
	});
	</script>
<?php
if ($ret_id) {
	$query = db_select("select * from brugere where id = $ret_id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$userName=$row['brugernavn'];
	// get afd from settings using settings function from std_func.php
	$afd = get_settings_value('afd', 'brugerAfd', 0, $row['id']);
	
	print "<div class='user-form-card' id='edit-user-form'>";
	print "<h2 class='section-title'>Rediger bruger: ".htmlspecialchars($userName)."</h2>";
	print "<form name='bruger' action='brugere.php' method='post'>";
	print "<input type=hidden name=id value=$row[id]>";
	$tmp="navn".rand(100,999);				#For at undgaa at browseren "husker" et forkert brugernavn.
	print "<input type=hidden name=random value=$tmp>";	#For at undgaa at browseren "husker" et forkert brugernavn.
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('225|Brugernavn', $sprog_id)."</label>";
	print "<input class='modern-input' type='text' name='$tmp' value=\"".htmlspecialchars($row['brugernavn'])."\">";
	print "</div>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('329|Adgang til', $sprog_id)."</label>";
	print "<div class='permissions-grid'>";
	// Select All checkbox
	print "<div class='permission-item' style='background: #e5e7eb; font-weight: 600;'>";
	print "<input class='modern-checkbox' type='checkbox' id='selectAllRights'>";
	print "<label for='selectAllRights' style='cursor: pointer;'>".findtekst('89|Vælg alle', $sprog_id)."</label>";
	print "</div>";
	for ($x=0;$x<16;$x++) {
		(substr($row['rettigheder'],$x,1)>=1)?$checked='checked':$checked=NULL;
		print "<div class='permission-item'>";
		print "<input class='modern-checkbox rights-checkbox' type='checkbox' name=\"rights[$x]\" id=\"rights_$x\" $checked>";
		print "<label for=\"rights_$x\">$modules[$x]</label>";
		print "</div>";
	}
	print "</div>";
	print "</div>";
	print "<script>
		(function() {
			const selectAll = document.getElementById('selectAllRights');
			const checkboxes = document.querySelectorAll('.rights-checkbox');
			
			if (selectAll && checkboxes.length > 0) {
				// Update select all state based on individual checkboxes
				function updateSelectAll() {
					const allChecked = Array.from(checkboxes).every(cb => cb.checked);
					selectAll.checked = allChecked;
					selectAll.indeterminate = !allChecked && Array.from(checkboxes).some(cb => cb.checked);
				}
				
				// Select all functionality
				selectAll.addEventListener('change', function() {
					checkboxes.forEach(cb => {
						cb.checked = selectAll.checked;
					});
				});
				
				// Update select all when individual checkboxes change
				checkboxes.forEach(cb => {
					cb.addEventListener('change', updateSelectAll);
				});
				
				// Initial state
				updateSelectAll();
			}
		})();
	</script>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('2475|Kun visning', $sprog_id)."</label>";
	print "<div class='permissions-grid-single'>";
	for ($x=0;$x<16;$x++) {
		// Only show the enabled checkbox (x==9 for varer module)
		if ($x==9) {
			(substr($row['rettigheder'],$x,1)==2)?$checked='checked':$checked=NULL;
			print "<div class='permission-item'>";
			print "<input class='modern-checkbox' type='checkbox' name=\"roRights[$x]\" id=\"roRights_$x\" $checked>";
			print "<label for=\"roRights_$x\">$modules[$x]</label>";
			print "</div>";
		}
		// Add hidden input for disabled checkboxes to maintain form structure
		else {
			print "<input type='hidden' name='roRights[$x]' value=''>";
		}
	}
	print "</div>";
	print "</div>";
	
	print "<div class='form-row'>";
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('327|Adgangskode.', $sprog_id)."</label>";
	print "<input class='modern-input' type=password name=kode placeholder='".findtekst('327|Adgangskode.', $sprog_id)."'>";
	print "</div>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('328|Gentag adgangskode', $sprog_id)."</label>";
	print "<input class='modern-input' type=password name=kode2 placeholder='".findtekst('328|Gentag adgangskode', $sprog_id)."'>";
	print "</div>";
	print "</div>";
	$x=0;
	if ($r2 = db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))) {
		$employeeId=array();
		$q2 = db_select("select * from ansatte where konto_id = $r2[id]  and lukket!='on' order by initialer",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$employeeId[$x]=$r2['id'];
			$employeeInitials[$x]=$r2['initialer'];
			if ($employeeId[$x]==$row['ansat_id']) {
				$employeeId[0]=$employeeId[$x];
				$employeeInitials[0]=$employeeInitials[$x];
			}		 
#			print "<input type = hidden name=employeeId[$x] value=$employeeId[$x]>";
		}
	}
	$ansat_antal=$x;
	print "<div class='form-row'>";
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('589|Ansat', $sprog_id)."</label>";
	print "<select class='modern-select' name='employeeId[0]'>";
	print "<option value=\"$employeeId[0]\">$employeeInitials[0]</option>";
	for ($x=1; $x<=$ansat_antal; $x++) { 
		print "<option value=\"$employeeId[$x]\">$employeeInitials[$x]</option>";
	} 
	if ($medarbejder) print "<option></option>";
	print "</select>";
	print "</div>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>Afdeling</label>";
	print "<select class='modern-select' name='afdeling'>";
	$q = db_select("select * from grupper where art = 'AFD'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if($r['kodenr']==$afd) {
			print "<option value=\"$r[kodenr]\" selected>$r[beskrivelse]</option>";
		} else {
			print "<option value=\"$r[kodenr]\">$r[beskrivelse]</option>";
		}
	}
	print "</select>";
	print "</div>";
	print "</div>";

	print "<input type=hidden name=re_id value=$ret_id>"; #20210909+20211015
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('1904|Angiv brugerens tilladte IP adresser', $sprog_id)."</label>";
	print "<input class='modern-input' type='text' name='insert_ip' maxlength=49 value='".htmlspecialchars($row['ip_address'])."'>";
	print "</div>";
	
	print "<div class='form-row'>";
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label' title='Hvis telefon og email er udfyldt, vil 2fa sendes til tlf og ikke email'>Tlf (til 2fa):</label>";
	print "<input class='modern-input' type='text' name='tlf' value='".htmlspecialchars($row['tlf'])."'>";
	print "</div>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label' title='Hvis telefon og email er udfyldt, vil 2fa sendes til tlf og ikke email'>Email (til 2fa):</label>";
	print "<input class='modern-input' type='text' name='email' value='".htmlspecialchars($row['email'])."'>";
	print "</div>";
	print "</div>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>";
	if($row['twofactor'] == 't') {
		$twofactor = "checked";
	} else {
		$twofactor = "";
	}
	print "<input class='modern-checkbox' type='checkbox' name='twofactor' $twofactor style='width: 20px; height: 20px; margin-right: 8px;'>";
	print "Two factor authentication</label>";
	print "</div>";
	
	print "<div class='button-group'>";
	print "<button type='submit' name='updateUser' class='btn-modern btn-success'>".findtekst('1091|Opdater', $sprog_id)."</button>";
	print "<button type='submit' name='deleteUser' class='btn-modern btn-danger' onclick=\"return confirm('Slet ".htmlspecialchars($userName)."?');\">".findtekst('1099|Slet', $sprog_id)."</button>";
	print "</div>";
	print "</form>";
	print "</div>";
} else {
	print "<div class='user-form-card'>";
	print "<h2 class='section-title'>".findtekst('333|Ny bruger', $sprog_id)."</h2>";
	print "<form name='bruger' action='brugere.php' method='post'>";
	$tmp="navn".rand(100,999);				#For at undgaa at browseren "husker" et forkert brugernavn.
	print "<input type=hidden name=random value = $tmp>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('225|Brugernavn', $sprog_id)."</label>";
	print "<input class='modern-input' type='text' name='$tmp' placeholder='".findtekst('225|Brugernavn', $sprog_id)."'>";
	print "</div>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('329|Adgang til', $sprog_id)."</label>";
	print "<div class='permissions-grid'>";
	// Select All checkbox
	print "<div class='permission-item' style='background: #e5e7eb; font-weight: 600;'>";
	print "<input class='modern-checkbox' type='checkbox' id='selectAllRightsNew'>";
	print "<label for='selectAllRightsNew' style='cursor: pointer;'>".findtekst('2564|Vælg alle', $sprog_id)."</label>";
	print "</div>";
	for ($x=0;$x<16;$x++) {
		print "<div class='permission-item'>";
		print "<input class='modern-checkbox rights-checkbox-new' type='checkbox' name=\"rights[$x]\" id=\"rights_new_$x\">";
		print "<label for=\"rights_new_$x\">$modules[$x]</label>";
		print "</div>";
	}
	print "</div>";
	print "</div>";
	print "<script>
		(function() {
			const selectAll = document.getElementById('selectAllRightsNew');
			const checkboxes = document.querySelectorAll('.rights-checkbox-new');
			
			if (selectAll && checkboxes.length > 0) {
				// Update select all state based on individual checkboxes
				function updateSelectAll() {
					const allChecked = Array.from(checkboxes).every(cb => cb.checked);
					selectAll.checked = allChecked;
					selectAll.indeterminate = !allChecked && Array.from(checkboxes).some(cb => cb.checked);
				}
				
				// Select all functionality
				selectAll.addEventListener('change', function() {
					checkboxes.forEach(cb => {
						cb.checked = selectAll.checked;
					});
				});
				
				// Update select all when individual checkboxes change
				checkboxes.forEach(cb => {
					cb.addEventListener('change', updateSelectAll);
				});
				
				// Initial state
				updateSelectAll();
			}
		})();
	</script>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('2475|Kun visning', $sprog_id)."</label>";
	print "<div class='permissions-grid-single'>";
	for ($x=0;$x<16;$x++) {
		// Only show the enabled checkbox (x==9 for varer module)
		if ($x==9) {
			print "<div class='permission-item'>";
			print "<input class='modern-checkbox' type='checkbox' name='roRights[$x]' id=\"roRights_new_$x\">";
			print "<label for=\"roRights_new_$x\">$modules[$x]</label>";
			print "</div>";
		}
		// Add hidden input for disabled checkboxes to maintain form structure
		else {
			print "<input type='hidden' name='roRights[$x]' value=''>";
		}
	}
	print "</div>";
	print "</div>";
	
	print "<div class='form-row'>";
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('324|Adgangskode', $sprog_id)."</label>";
	print "<input class='modern-input' type='password' name='kode' placeholder='".findtekst('324|Adgangskode', $sprog_id)."'>";
	print "</div>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>".findtekst('328|Gentag adgangskode', $sprog_id)."</label>";
	print "<input class='modern-input' type='password' name='kode2' placeholder='".findtekst('328|Gentag adgangskode', $sprog_id)."'>";
	print "</div>";
	print "</div>";
	
	print "<div class='form-row'>";
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label' title='Hvis telefon og email er udfyldt, vil 2fa sendes til tlf og ikke email'>Tlf (til 2fa):</label>";
	print "<input class='modern-input' type='text' name='tlf' placeholder='Telefonnummer'>";
	print "</div>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label' title='Hvis telefon og email er udfyldt, vil 2fa sendes til tlf og ikke email'>Email (til 2fa):</label>";
	print "<input class='modern-input' type='text' name='email' placeholder='Email'>";
	print "</div>";
	print "</div>";
	
	print "<div class='modern-form-group'>";
	print "<label class='modern-form-label'>";
	print "<input class='modern-checkbox' type='checkbox' name='twofactor' style='width: 20px; height: 20px; margin-right: 8px;'>";
	print "Two factor authentication</label>";
	print "</div>";
	
	print "<div class='button-group'>";
	print "<button type='submit' name='addUser' class='btn-modern btn-primary'>".findtekst('1175|Tilføj', $sprog_id)."</button>";
	print "</div>";
	print "</form>";
	print "</div>";
}
print "</div>"; // Close user-management-container

// Auto-scroll to edit form when a user is selected
if ($ret_id) {
	print "<script>
		(function() {
			function scrollToEditForm() {
				const editForm = document.getElementById('edit-user-form');
				if (editForm) {
					// Calculate position with offset
					const elementPosition = editForm.getBoundingClientRect().top;
					const offsetPosition = elementPosition + window.pageYOffset - 80; // 80px offset from top
					
					window.scrollTo({
						top: offsetPosition,
						behavior: 'smooth'
					});
				}
			}
			
			// Try immediately if DOM is ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', scrollToEditForm);
			} else {
				// DOM is already ready, but wait a bit for layout
				setTimeout(scrollToEditForm, 100);
			}
		})();
	</script>";
}

if ($menu=='T') {
	print "</div></div>"; // Close maincontentLargeHolder and divSys
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>