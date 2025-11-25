<?php
// --- debitor/historikkort.php --- patch 4.1.1 --- 2025-11-21 ---
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20190213 MSC - Rettet isset fejl og db fejl + rettet topmenu design til
// 20190215 MSC - Rettet topmenu design
// 20210728 LOE - Updated some texts with translated ones 
// 20220719 MSC - Implementing new design
// 20241009 MMK - Added datepicker functionalaty
// 20250808 PHR - Added $id to returside 
// 20251121 LOE - Modified icons to SVG format and buttons to fit the new design

@session_start();
$s_id = session_id();

?>
<script LANGUAGE="JavaScript">
	<!--
	function Slet() {
		var agree = confirm("Slet handling?");
		if (agree)
			return true;
		else
			return false;
	}
	// 
	-->
</script>
<?php

$modulnr = 6;
$title = "Historik";
$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
# >> Date picker scripts <<
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/jquery-3.6.4.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/moment.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/daterangepicker.min.js\" defer></script>";
print '<link rel="stylesheet" type="text/css" href="../css/daterangepicker.css" />';

$editIcon       = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>';
$deleteIcon     = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>';
$attach_icon    = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M720-330q0 104-73 177T470-80q-104 0-177-73t-73-177v-370q0-75 52.5-127.5T400-880q75 0 127.5 52.5T580-700v350q0 46-32 78t-78 32q-46 0-78-32t-32-78v-370h80v370q0 13 8.5 21.5T470-320q13 0 21.5-8.5T500-350v-350q-1-42-29.5-71T400-800q-42 0-71 29t-29 71v370q-1 71 49 120.5T470-160q70 0 119-49.5T640-330v-390h80v390Z"/></svg>';
$attached_icon  = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h360l200 200v520q0 33-23.5 56.5T720-80H240Zm0-80h480v-480H560v-160H240v640Zm240-40q67 0 113.5-47T640-360v-160h-80v160q0 33-23 56.5T480-280q-33 0-56.5-23.5T400-360v-220q0-9 6-14.5t14-5.5q9 0 14.5 5.5T440-580v220h80v-220q0-42-29-71t-71-29q-42 0-71 29t-29 71v220q0 66 47 113t113 47ZM240-800v160-160 640-640Z"/></svg>';
$company_icon   = '<svg xmlns="http://www.w3.org/2000/svg" height="27px" viewBox="0 -960 960 960" width="27px" fill="#2b66ba"><path d="M680-600h80v-80h-80v80Zm0 160h80v-80h-80v80Zm0 160h80v-80h-80v80Zm0 160v-80h160v-560H480v56l-80-58v-78h520v720H680Zm-640 0v-400l280-200 280 200v400H360v-200h-80v200H40Zm80-80h80v-200h240v200h80v-280L320-622 120-480v280Zm560-360ZM440-200v-200H200v200-200h240v200Z"/></svg>';
$location_icon  = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="rgb(75 85 99)"><path d="M480-480q33 0 56.5-23.5T560-560q0-33-23.5-56.5T480-640q-33 0-56.5 23.5T400-560q0 33 23.5 56.5T480-480Zm0 294q122-112 181-203.5T720-552q0-109-69.5-178.5T480-800q-101 0-170.5 69.5T240-552q0 71 59 162.5T480-186Zm0 106Q319-217 239.5-334.5T160-552q0-150 96.5-239T480-880q127 0 223.5 89T800-552q0 100-79.5 217.5T480-80Zm0-480Z"/></svg>';
$phone_icon     = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="rgb(75 85 99)"><path d="M798-120q-125 0-247-54.5T329-329Q229-429 174.5-551T120-798q0-18 12-30t30-12h162q14 0 25 9.5t13 22.5l26 140q2 16-1 27t-11 19l-97 98q20 37 47.5 71.5T387-386q31 31 65 57.5t72 48.5l94-94q9-9 23.5-13.5T670-390l138 28q14 4 23 14.5t9 23.5v162q0 18-12 30t-30 12ZM241-600l66-66-17-94h-89q5 41 14 81t26 79Zm358 358q39 17 79.5 27t81.5 13v-88l-94-19-67 67ZM241-600Zm358 358Z"/></svg>';
$mail_icon      = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="rgb(75 85 99)"><path d="M160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h640q33 0 56.5 23.5T880-720v480q0 33-23.5 56.5T800-160H160Zm320-280L160-640v400h640v-400L480-440Zm0-80 320-200H160l320 200ZM160-640v-80 480-400Z"/></svg>';
$time_icon      = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="rgb(75 85 99)"><path d="m612-292 56-56-148-148v-184h-80v216l172 172ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-400Zm0 320q133 0 226.5-93.5T800-480q0-133-93.5-226.5T480-800q-133 0-226.5 93.5T160-480q0 133 93.5 226.5T480-160Z"/></svg>';
$date_icon      = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="rgb(75 85 99)"><path d="M200-80q-33 0-56.5-23.5T120-160v-560q0-33 23.5-56.5T200-800h40v-80h80v80h320v-80h80v80h40q33 0 56.5 23.5T840-720v560q0 33-23.5 56.5T760-80H200Zm0-80h560v-400H200v400Zm0-480h560v-80H200v80Zm0 0v-80 80Z"/></svg>';
$fax_icon       = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="rgb(75 85 99)"><path d="M200-120q-50 0-85-35t-35-85v-280q0-50 35-85t85-35q27 0 49.5 11t39.5 29h31v-200h400v200h40q50 0 85 35t35 85v320H289q-17 18-39.5 29T200-120Zm0-80q17 0 28.5-11.5T240-240v-280q0-17-11.5-28.5T200-560q-17 0-28.5 11.5T160-520v280q0 17 11.5 28.5T200-200Zm200-400h240v-120H400v120Zm-80 360h480v-240q0-17-11.5-28.5T760-520H320v280Zm280-160q17 0 28.5-11.5T640-440q0-17-11.5-28.5T600-480q-17 0-28.5 11.5T560-440q0 17 11.5 28.5T600-400Zm120 0q17 0 28.5-11.5T760-440q0-17-11.5-28.5T720-480q-17 0-28.5 11.5T680-440q0 17 11.5 28.5T720-400ZM600-280q17 0 28.5-11.5T640-320q0-17-11.5-28.5T600-360q-17 0-28.5 11.5T560-320q0 17 11.5 28.5T600-280Zm120 0q17 0 28.5-11.5T760-320q0-17-11.5-28.5T720-360q-17 0-28.5 11.5T680-320q0 17 11.5 28.5T720-280Zm-360 0h160v-200H360v200Zm-40 40v-280 280Z"/></svg>';
$link_icon      = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="rgb(75 85 99)"><path d="M440-280H280q-83 0-141.5-58.5T80-480q0-83 58.5-141.5T280-680h160v80H280q-50 0-85 35t-35 85q0 50 35 85t85 35h160v80ZM320-440v-80h320v80H320Zm200 160v-80h160q50 0 85-35t35-85q0-50-35-85t-85-35H520v-80h160q83 0 141.5 58.5T880-480q0 83-58.5 141.5T680-280H520Z"/></svg>';
$job_icon       = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M480-400ZM80-160v-400q0-33 23.5-56.5T160-640h120v-80q0-33 23.5-56.5T360-800h240q33 0 56.5 23.5T680-720v80h120q33 0 56.5 23.5T880-560v400H80Zm240-200v40h-80v-40h-80v120h640v-120h-80v40h-80v-40H320ZM160-560v120h80v-40h80v40h320v-40h80v40h80v-120H160Zm200-80h240v-80H360v80Z"/></svg>';
$debitor_icon   = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Zm80-80h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>';


if (!isset($_GET['konto_id'])) $_GET['konto_id'] = NULL;
if (!isset($historik_id)) $historik_id = NULL;
if (!isset($_GET['handling'])) $_GET['handling'] = NULL;
if (!isset($_GET['ordre_id'])) $_GET['ordre_id'] = NULL;
if (!isset($_GET['fokus'])) $_GET['fokus'] = NULL;
if (!isset($_POST['submit'])) $_POST['submit'] = NULL;
if (!isset($kontaktet)) $kontaktet = NULL;
if (!isset($oprettet)) $oprettet = NULL;
if (!isset($kontaktes)) $kontaktes = 0;
if (!isset($ansat_id)) $ansat_id = 0;
if (!isset($ansat)) $ansat = 0;
if (!isset($kontakt)) $kontakt = 0;
if (!isset($_GET['id'])) $_GET['id'] = 0;
if (!isset($_POST['historik_id'])) $_POST['historik_id'] = NULL;
if (!isset($_POST['oprettet'])) $_POST['oprettet'] = NULL;
if (!isset($r1['navn'])) $r1['navn'] = NULL;
if (!isset($vis_bilag)) $vis_bilag = NULL;

print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

$id = (int)$_GET['id'];
if ($_GET['konto_id']) $id = $_GET['konto_id'];
if (isset($_GET['historik_id'])) $historik_id = $_GET['historik_id'];
$handling = $_GET['handling'];

if ($handling == 'slet') {
	db_modify("delete from historik where id = $historik_id", __FILE__ . " linje " . __LINE__);
	$historik_id = '';
}
if (isset($_GET['returside'])) {
	$returside = $_GET['returside'];
} else {
	if ($popup) $returside = "../includes/luk.php";
	else $returside = "historik.php";
}

################################# Save post request ###################################
function processContactForm($post) {
    // Early return if form not submitted
    if (!isset($post['submit'])) {
        return false;
    }

    // Sanitize and validate inputs
    $sanitizedInputs = sanitizeFormInputs($post);

	updateOprettet($post["id"], $post["oprettet"]);

    // Only proceed if there's a note or future contact
    if (!$sanitizedInputs['hasContactOrNote']) {
        return false;
    }

    // Fetch relevant IDs
    $employeeIds = fetchEmployeeIds(
        $sanitizedInputs['egen_id'], 
        $sanitizedInputs['id'], 
        $sanitizedInputs['ansat'], 
        $sanitizedInputs['kontakt']
    );

    // Update or insert historik record
    $historikId = processHistorikRecord(
        $sanitizedInputs, 
        $employeeIds
    );

    // Update address record
    updateAddressRecord(
        $sanitizedInputs, 
        $historikId
    );

    return true;
}

function sanitizeFormInputs($post) {
    return [
        'id' => $post['id'],
        'egen_id' => $post['egen_id'],
        'historik_id' => $post['historik_id'] ?? null,
        'ansat' => $post['ansat'],
        'kontakt' => $post['kontakt'],
        'kontaktet' => !empty($post['kontaktet']) 
            ? usdate($post['kontaktet']) 
            : date("Y-m-d"),
        'kontaktes' => !empty($post['kontaktes']) 
            ? usdate($post['kontaktes']) 
            : null,
        'note' => db_escape_string(trim($post['note'] ?? '')),
        'hasContactOrNote' => !empty($post['kontaktes']) || !empty(trim($post['note'] ?? ''))
    ];
}

function fetchEmployeeIds($egen_id, $id, $ansat, $kontakt) {
    $ansat_query = db_select("SELECT id FROM ansatte WHERE konto_id = '$egen_id' AND navn = '$ansat'", __FILE__ . " linje " . __LINE__);
    $ansat_result = db_fetch_array($ansat_query);
    $ansat_id = (int)$ansat_result['id'];

    $kontakt_query = db_select("SELECT id FROM ansatte WHERE konto_id = '$id' AND navn = '$kontakt'", __FILE__ . " linje " . __LINE__);
    $kontakt_result = db_fetch_array($kontakt_query);
    $kontakt_id = (int)$kontakt_result['id'];

    return [
        'ansat_id' => $ansat_id,
        'kontakt_id' => $kontakt_id
    ];
}

function processHistorikRecord($inputs, $employeeIds) {
    $notedate = date("Y-m-d");
    
    // Update existing record
    if (!empty($inputs['historik_id'])) {
        db_modify("UPDATE historik 
            SET kontakt_id = {$employeeIds['kontakt_id']}, 
                ansat_id = {$employeeIds['ansat_id']}, 
                notat = '{$inputs['note']}', 
                kontaktet = '{$inputs['kontaktet']}' 
            WHERE id = {$inputs['historik_id']}", 
            __FILE__ . " linje " . __LINE__
        );
        return $inputs['historik_id'];
    }
    
    // Insert new record
    $query = $inputs['kontaktes'] 
        ? "INSERT INTO historik 
            (konto_id, kontakt_id, ansat_id, notat, notedate, kontaktet, kontaktes) 
            VALUES (
                {$inputs['id']}, 
                {$employeeIds['kontakt_id']}, 
                {$employeeIds['ansat_id']}, 
                '{$inputs['note']}', 
                '$notedate', 
                '{$inputs['kontaktet']}', 
                '{$inputs['kontaktes']}'
            )"
        : "INSERT INTO historik 
            (konto_id, kontakt_id, ansat_id, notat, notedate, kontaktet) 
            VALUES (
                {$inputs['id']}, 
                {$employeeIds['kontakt_id']}, 
                {$employeeIds['ansat_id']}, 
                '{$inputs['note']}', 
                '$notedate', 
                '{$inputs['kontaktet']}'
            )";
    
    db_modify($query, __FILE__ . " linje " . __LINE__);
}

function updateOprettet($id, $oprettet) {
	if (isset($oprettet)) {
      if (!$oprettet) $oprettet = date('Y-m-d');
      db_modify("UPDATE adresser
            SET oprettet = '{$oprettet}' 
            WHERE id = {$id}", 
            __FILE__ . " linje " . __LINE__
        );
	}
}

function updateAddressRecord($inputs, $historikId) {
    if ($inputs['kontaktes']) {
        // Update with future contact date
        db_modify("UPDATE adresser 
            SET kontaktet = '{$inputs['kontaktet']}', 
                kontaktes = '{$inputs['kontaktes']}' 
            WHERE id = {$inputs['id']}", 
            __FILE__ . " linje " . __LINE__
        );
        
        // Update historik record if exists
        if ($historikId) {
            db_modify("UPDATE historik 
                SET kontaktes = '{$inputs['kontaktes']}' 
                WHERE id = $historikId", 
                __FILE__ . " linje " . __LINE__
            );
        }
    } else {
        // Update with current contact date only
        db_modify("UPDATE adresser 
            SET kontaktet = '{$inputs['kontaktet']}' 
            WHERE id = {$inputs['id']}", 
            __FILE__ . " linje " . __LINE__
        );
    }
}

// Usage
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = processContactForm($_POST);
}
############################ DISPLAY TOP HEADER #################################
if (!$id) print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/dashboard.php\">";
if (strstr($returside, 'historikkort.php')) $returside = "historik.php";
if ($returside == 'debitorkort.php') $returside.= "?id=$id";

if ($menu == 'T') {
	$center = "align=center";
	$width = "width=20%";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft headLink\"><a href=\"javascript:confirmClose('historikkort.php?luk=luk.php')\" accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst('30|Tilbage', $sprog_id) . "</a></div>";
	print "<div class=\"headerTxt\">$title</div>";
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu == 'S') {
    ############################
     $icon_back = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';

    ##########################
	$center = "";
	$width = "width=10%";
	print "<table width='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n"; #tabel1 start
	print "<tr><td align='center' valign='top' height='1%'>\n";
	print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody>\n"; #tabel2a start

	$tekst = findtekst('154|Dine ændringer er ikke blevet gemt! Tryk OK for at forlade siden uden at gemme.', $sprog_id);
	print "<td width='10%' align=center><a href=\"javascript:confirmClose('$returside')\" accesskey=L>
		  <button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".$icon_back  . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>\n";

	print "<td width='80%' align=center style='$topStyle'>" . findtekst('1668|Historik for debitor', $sprog_id) . "</td>\n";

	print "<td width='10%' align=center style='$buttonStyle;'>
		   <br></td>\n";

	print "</tbody></table>\n"; #tabel2a slut
	print "</td></tr>\n";
	print "<tr><td width=\"100%\" valign=\"top\">";
    ####
    print "</td></tr>\n";

    print "</tbody></table>\n";  # tabel1 slut
    #####

    ?>
    <style>
    .headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;
	}
    </style>
    <?php

} else {
	$center = "";
	$width = "width=10%";
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n"; #tabel1 start
	print "<tr><td align=\"center\" valign=\"top\" height=\"1%\">\n";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>\n"; #tabel2a start
	$tekst = findtekst('154|Dine ændringer er ikke blevet gemt! Tryk OK for at forlade siden uden at gemme.', $sprog_id);
	#if ($returside=="debitorkort.php") print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=\"javascript:confirmClose('$returside?id=$id&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>Luk</a></div></td>\n";
	#print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>Luk</a></div></td>\n";
	print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=\"javascript:confirmClose('historikkort.php?luk=luk.php')\" accesskey=L>" . findtekst('30|Tilbage', $sprog_id) . "</a></div></td>\n";
	print "<td width=\"80%\" align=center><div class=\"top_bund\">" . findtekst('1668|Historik for debitor', $sprog_id) . "</div></td>\n";
	print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=\"javascript:confirmClose('debitorkort.php?returside=historikkort.php&id=$id&ordre_id=$ordre_id&fokus=$fokus','$tekst')\" accesskey=N>" . findtekst('39|Ny', $sprog_id) . "</a><br></div></td>\n";
	print "</tbody></table>\n"; #tabel2a slut
	print "</td></tr>\n";
	print "<tr><td width=\"100%\" valign=\"top\">";
}

print "<div class='outer-datatable-wrapper'>";
print "<div class='datatable-wrapper'>";
if($menu == 'S'){
 print "<table width='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n"; #tabel1 start
}
print "<table class='dataTableForm' width=\"100%\" cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>"; #tabel2b start

if ($id > 0) {
	$q          = db_select("select * from adresser where id = '$id'", __FILE__ . " linje " . __LINE__);
	$r          = db_fetch_array($q);
	$kontonr    = trim($r['kontonr']);
	$firmanavn  = htmlentities(trim($r['firmanavn']), ENT_COMPAT, $charset);
	$addr1      = htmlentities(trim($r['addr1']), ENT_COMPAT, $charset);
	$addr2      = htmlentities(trim($r['addr2']), ENT_COMPAT, $charset);
	$postnr     = trim($r['postnr']);
	$bynavn     = htmlentities(trim($r['bynavn']), ENT_COMPAT, $charset);
	$land       = htmlentities(trim($r['land']), ENT_COMPAT, $charset);
	$tlf        = trim($r['tlf']);
	$fax        = trim($r['fax']);
	$email      = trim($r['email']);
	$web        = trim($r['web']);
	$notes      = htmlentities(trim($r['notes']), ENT_COMPAT, $charset);
	if ($r['oprettet']) $oprettet = dkdato($r['oprettet']);
	if ($r['kontaktet']) $kontaktet = dkdato($r['kontaktet']);
	if ($r['kontaktes']) $kontaktes = dkdato($r['kontaktes']);
}
######################### COMPANY HEADER #####################################

if (db_fetch_array(db_select("select * from grupper where ART = 'FTP' and box1 !='' and box2 !='' and box3 !=''", __FILE__ . " linje " . __LINE__))) $vis_bilag = 1;
$vis_bilag = 1;

// Sanitize data for security
$email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$tlf = htmlspecialchars($tlf, ENT_QUOTES, 'UTF-8');
$web = htmlspecialchars($web, ENT_QUOTES, 'UTF-8');

// Format phone number - remove spaces and special characters for tel: link
$clean_phone = preg_replace('/[^0-9+]/', '', $tlf);

print "<form name='historikkort' action='?id=$id&returside=".urlencode($returside)."' method='post'>";
print "<input type='hidden' name=\"id\" value='$id'>";
print "<section id='contact-header'>";
print "<div class='column'>";
print " <h1>$company_icon $firmanavn</h1>";
print " <div id='contact-row'>";
# Contact information, adress, phone, fax, email, web etc
print " <div>";
$address = "$addr1 $addr2 $postnr $bynavn $land";
echo "<a href='https://maps.google.com/?q=$address' target='_blank' class='link'>";
echo "    <p>$location_icon $addr1 $addr2 $postnr $bynavn $land</p>";
echo "</a>";
print " <p>$phone_icon <a class='link' href='tel:$clean_phone' title='Call $firmanavn'>$tlf</a></p>";
if ($fax) print " <p>$fax_icon $fax</p>";
if ($email) print " <p>$mail_icon <a class='link' href='mailto:$email' title='Send email to $firmanavn'>$email</a></p>";
if ($web) {
    // Add http:// if not present
    $web_url = (strpos($web, 'http') === false) ? 'http://' . $web : $web;
    print " <p>$link_icon <a class='link' href='$web_url' target='_blank' rel='noopener noreferrer' title='Visit website'>$web</a></p>";
}
print " </div>";
# Dates
print " <div>";
print " <p>$time_icon Oprettet: " . ($oprettet ? $oprettet : " <input class='date-picker' type=text name=oprettet size=11 onchange=\"javascript:docChange = true;\">") . "</p>";
print " <p>$date_icon Seneste kontakt: $kontaktet</p>";
print " <p>$date_icon Næste kontakt: $kontaktes</p>";
print " </div>";
print " </div>";
print "</div>";
print "<div class='column' id='button-column'>";
print " <a href=debitorkort.php?id=$id&returside=".urlencode($_SERVER['REQUEST_URI'])."><button type=button>$debitor_icon Deibitorkort</button></a>";
if (db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '2' and box7='on'", __FILE__ . " linje " . __LINE__))) {
    $url = "jobliste.php?kontonr=$kontonr&konto_id=$id&returside=".urlencode($_SERVER['REQUEST_URI']);
    print "<a href='$url'><button type=button>$job_icon Jobkort</button></a>";
}
print "</div>";
print "</section>";


$hrtd = "align='center'";
print "</tbody></table></td></tr>"; #tabel3a slut;

######################### GET DEFAULT VALUES FOR EDIT #####################################
print "<tr><td $hrtd colspan=6><hr class='hrtd'></td></tr>";
print "<tr><td $width><table border=0 width=100%><tbody>"; #tabel3b start;
if ($historik_id) {
	$r = db_fetch_array(db_select("select * from historik where id = '$historik_id'", __FILE__ . " linje " . __LINE__));
	$notat = ($r['notat']);
	$kontaktet = dkdato($r['kontaktet']);
	if ($r['kontaktes']) $kontaktes = dkdato($r['kontaktes']);
	else $kontaktes = NULL;
	$ansat_id = $r['ansat_id'] * 1;
	$kontakt_id = $r['kontakt_id'] * 1;
	$r = db_fetch_array(db_select("select id, navn from ansatte where id = $ansat_id", __FILE__ . " linje " . __LINE__));
	$ansat = $r['navn'];
	$r = db_fetch_array(db_select("select id, navn from ansatte where id = $kontakt_id", __FILE__ . " linje " . __LINE__));
	$kontakt = $r['navn'];
} else {
	$notat = '';
	$kontaktet = '';
	$kontaktes = '';
	$kontakt_id = '';

	$employee = if_isset($_GET["employee"], NULL);
	if ($employee) {
		$r = db_fetch_array(db_select("select id, navn from ansatte where id = $employee", __FILE__ . " linje " . __LINE__));
		$kontakt = $r['navn'];
		$kontakt_id = $r['id'];
	} else {
		$kontakt = '';
	}
}

$ansat_id = (int) $ansat_id;
$ansat_navn = '';

if ($ansat_id) {
    $ansat_query = "SELECT navn FROM ansatte WHERE id = $ansat_id AND lukket != 'on'";
    $ansat_data = db_fetch_array(db_select($ansat_query, __FILE__ . " linje " . __LINE__));
    $ansat_navn = $ansat_data['navn'] ?? '';
}

$egen_query = "SELECT id FROM adresser WHERE art = 'S'";
$egen_data = db_fetch_array(db_select($egen_query, __FILE__ . " linje " . __LINE__));
$egen_id = (int) $egen_data['id'];

// Hidden input for egen_id
echo "<input type='hidden' name='egen_id' value='$egen_id'>";

#################################### EDIT CARD ######################################

$html_ansat_select = "<select class='inputbox' name='ansat' value='$ansat'>";

$ansatte_query = "SELECT id, navn FROM ansatte WHERE konto_id = $egen_id AND lukket != 'on'";
$ansatte_result = db_select($ansatte_query, __FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($ansatte_result)) {
    $html_ansat_select .= "<option ".($ansat_navn == $row["navn"] ? "selected=selected" : "").">{$row['navn']}</option>";
}
$html_ansat_select .= "</select>\n";

$html_kontakt_select = "<select class='inputbox' name='kontakt' value='$kontakt'>";

$kontakt_query = "SELECT id, navn, tlf, mobil, email, notes FROM ansatte WHERE konto_id = $id";
$kontakt_result = db_select($kontakt_query, __FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($kontakt_result)) {
    $selected = ($row['id'] == $kontakt_id) ? "selected='selected'" : "";
    $title = "D: {$row['tlf']} M: {$row['mobil']} E: {$row['email']} B: {$row['notes']}";
    $html_kontakt_select .= "<option $selected title='$title'>{$row['navn']}</option>\n";
}
$html_kontakt_select .= "<option></option></select>\n";

$kontaktet = $kontaktet ?: date("d-m-Y");
$kontaktes = $kontaktes ?: dkdato(if_isset($_GET['kontaktigen'], NULL));

$html_kontaktet = "<input class='inputbox date-picker' type='text' name='kontaktet' value='$kontaktet' onchange='javascript:docChange = true;'>\n";
$html_kontaktes = "<input class='inputbox date-picker' type='text' name='kontaktes' value='$kontaktes' onchange='javascript:docChange = true;'>\n";

$submit_text = $historik_id ? findtekst('471|Gem/opdatér', $sprog_id) : findtekst('3|Gem', $sprog_id);
$submit_input = "<input class='submit-button' type='submit' accesskey='g' value='$submit_text' name='submit' onclick='javascript:docChange = false;'>";

echo $historik_id ? "<input type='hidden' name='historik_id' value='$historik_id'>" : "";

print "<div class='card'>
	<div>
		<p class='person-line infobox'><span>$html_ansat_select</span><span>&rarr;</span><span>$html_kontakt_select</span></p>
		<p class='date-line   infobox'><span>$html_kontaktet</span><span>&rarr;</span><span>$html_kontaktes</span></p>
		$submit_input
	</div>
	<textarea name='note' style='flex: 1;height: 145px' onchange='javascript:docChange = true;'>$notat</textarea>
</form>";


###################### Display cards ###############################
$q = db_select(
    "SELECT * FROM historik WHERE konto_id = $id ORDER BY kontaktet DESC, id DESC",
    __FILE__ . " linje " . __LINE__
);

print "<tr><td $hrtd colspan=6><hr class='hrtd'></td></tr>";

while ($r = db_fetch_array($q)) {
    $ansat_id = (int) $r['ansat_id'];
    $kontakt_id = (int) $r['kontakt_id'];
    $notedato = dkdato($r['notedate']);
    $kontaktet = dkdato($r['kontaktet']);
    $kontaktes = $r['kontaktes'] ? dkdato($r['kontaktes']) : '';

    // Fetch employee data
    $r1 = db_fetch_array(
        db_select("SELECT navn FROM ansatte WHERE id = $ansat_id", __FILE__ . " linje " . __LINE__)
    );
    $ansat = str_replace(" ", "&nbsp;", $r1['navn']);

    $r1 = db_fetch_array(
        db_select("SELECT navn, tlf, mobil, email, notes FROM ansatte WHERE id = $kontakt_id", __FILE__ . " linje " . __LINE__)
    );
    $kontakt = str_replace(" ", "&nbsp;", $r1['navn']);

    // Format note
    $notat = htmlentities($r['notat'], ENT_COMPAT, $charset);
    $notat = str_replace("  ", "&nbsp;&nbsp;", $notat);
    $notat = nl2br($notat);
    $dokument = $r['dokument'];

    print "<tr><td colspan=1000>
	<div class='card'>
		<div>
			<p class='person-line infobox'><span>$ansat</span><span>&rarr;</span><span title='D: $r1[tlf] M: $r1[mobil] E: $r1[email] B: $r1[notes]'>$kontakt</span></p>
			<p class='date-line   infobox'><span>$kontaktet</span><span>&rarr;</span><span>$kontaktes</span></p>
		
			<div class='action-buttons button-line'>
				<a class='action-button' href='historikkort.php?id=$id&historik_id=$r[id]&handling=ret&returside=".urlencode($returside)."'>
					$editIcon
				</a>
				<a class='action-button' href='historikkort.php?id=$id&historik_id=$r[id]&handling=slet&returside=".urlencode($returside)."' onclick='return Slet()'>
					$deleteIcon
				</a>
	";

    if ($vis_bilag) {
        if ($dokument) {
            #print "<td align='right' title='" . findtekst('1454|Klik her for at åbne bilaget', $sprog_id) . ": $dokument'>";
            print "<a class='action-button' href='../includes/bilag.php?kilde=historik&filnavn=$dokument&kilde_id=$id&bilag_id=$r[id]'>";
            #print "<img style='border: 0px solid' alt='clip med papir' src='../ikoner/paper.png'></a>";
			print "$attached_icon</a>";
        } else {
            #print "<td align='right' title='" . findtekst('1455|Klik her for at vedhæfte et bilag', $sprog_id) . "'>";
            print "<a class='action-button' href='../includes/bilag.php?kilde=historik&ny=ja&kilde_id=$id&bilag_id=$r[id]'>";
            #print "<img style='border: 0px solid' alt='papirclip' src='../ikoner/clip.png'></a>";
			print "$attach_icon</a>";
        }
    }
	print "</div>
		</div>";

    print "<div class='note'>$notat</div>";
	print "</div>";
    print "</div></td></tr>";
}

print "</tbody>
</table>
</td></tr>
</tbody></table>";
print "</div>"; //datatable-wrapper
print "</div>"; //outer-datatable-wrapper
if ($menu == 'T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>


<!-- Date range picker scripts-->
<script defer>
// Add custom CSS for highlighting current day
$('<style>')
  .text(`
    .current-day {
      position: relative;
      background-color: #e8f4ff !important;
    }
    .current-day:after {
      content: '';
      position: absolute;
      bottom: 2px;
      left: 50%;
      transform: translateX(-50%);
      width: 4px;
      height: 4px;
      background-color: #2196F3;
      border-radius: 50%;
    }
    .current-day.active {
      background-color: #357ABD !important;
    }
    .current-day.active:after {
      background-color: white;
    }
  `)
  .appendTo('head');

$(function() {
  $('.date-picker').daterangepicker({
    singleDatePicker: true,
    locale: {
      format: 'DD-MM-YYYY',
      separator: ':'
    },
    autoUpdateInput: false,
    opens: 'right',
    startDate: moment(),
    isCustomDate: function(date) {
      // Check if the date is today
      if (date.format('YYYY-MM-DD') === moment().format('YYYY-MM-DD')) {
        return 'current-day';
      }
      return false;
    }
  },
  function(start, end, label) {
    console.log(start.format('DD-MM-YYYY'));
    $(this.element).val(start.format('DD-MM-YYYY'));
  });

  // Add event listener for when the picker is shown
  $('.date-picker').on('show.daterangepicker', function(ev, picker) {
    if (!$(this).val()) {
      picker.setStartDate(moment());
    }
  });

  // Add event listener for when today's date is selected
  $('.date-picker').on('apply.daterangepicker', function(ev, picker) {
    $(this).val(picker.startDate.format('DD-MM-YYYY'));
  });
});

</script>

<style>
    
	#contact-header p, #contact-header h1{margin: 0; padding: 0;}
	#contact-header {
		display: flex;
		justify-content: space-between;
		flex-wrap: wrap;
	}
	#contact-header #button-column {
		margin-top: 10px;
		display: flex;
		flex-direction: column;
		gap: 5px;
		align-items: end;
	}
	#contact-header #button-column a {
		text-decoration: none;
	}
	#contact-header #button-column button {
		background-color: #2b66ba;
		border: none;
		padding: 8px 16px;
		border-radius: 5px;
		cursor: pointer;
		color: #f6f9fe;

		display: flex;
		align-items: center;
		gap: 5px;
	}

	#contact-header h1 {
		display: flex;
		align-items: center;
		gap: 10px;
		margin-bottom: 10px;
		margin-top: 10px;
		font-size: 1.5rem;
	}
	#contact-header #contact-row {
		display: flex;
		gap: 2em;
	}
	#contact-header #contact-row p {
		display: flex;
		align-items: center;
		gap: 10px;
		margin-bottom: 5px;
		color: rgb(75 85 99);
	}


	.card {
		flex: 1;
		flex-wrap: wrap;
		background-color: #fff;
		border-radius: 5px;
		box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
		padding: 1.4em 2em;
		width: 100%;
		box-sizing: border-box;
		align-items: top;
		display: flex;
		gap: 10px;
	}

	.card .infobox {
		display: flex;
		justify-content: space-between;
		width: 200px;
		border-radius: 10px;
		margin: 0;
		padding: 12px;
	}
	.card .infobox span:nth-child(2n+1) {
		width: 90px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
	}
	.card .infobox span:last-child {
		text-align: right;
	}

	.card .infobox select, .card .infobox input {
		width: 90px;
	}

	.card .person-line {
		margin-bottom: 5px;
		background-color: #eff6ff;
		color: rgb(37 99 235);
	}
	.card .date-line {
		background-color: #f0fdf4;
		color: rgb(22 163 74);
	}
	.card .button-line {
		margin: 0;
		padding-top: 5px;
	}
	.card .note {
		margin: 0;
		padding: 12px;
		border-radius: 10px;
		background-color: #F5F5F5;
		flex: 1;
		min-width: 200px;
	}

    .action-buttons {
        display: flex;
        gap: 1px;
        margin-left: auto;
    }

    .action-button {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4px;
        border-radius: 4px;
        transition: background-color 0.2s;
        text-decoration: none;
    }

    .action-button:hover {
        background-color: rgba(0, 0, 0, 0.1);
    }

    .action-button svg {
        display: block;
    }

	.submit-button {
		width: 100%;
		margin-top: 15px;
		border-radius: 10px;
		border: none;
		background-color: #2b66ba;
		padding: 8px 16px;
		border-radius: 5px;
		cursor: pointer;
		color: #f6f9fe;
	}

	.link {
		color: inherit;
		text-decoration: none;
		display: flex;
		align-items: center;
		transition: background-color 0.2s;
	}

	.link:hover {
		background-color: #f5f5f5;
	}
</style>
 <script>
document.addEventListener('DOMContentLoaded', function() {
   
    let themeColor = document.querySelector('.center-btn');

    if (themeColor) {
       
        let themeColorBackgroundColor = window.getComputedStyle(themeColor).backgroundColor;
        // Get all buttons
        let buttons = document.querySelectorAll('#contact-header #button-column button, .submit-button');
        // Loop through each button and set its background color
        buttons.forEach(button => {
            button.style.cssText = `background-color: ${themeColorBackgroundColor} !important`;
        });
    }
});

</script>   

<style>

    .outer-datatable-wrapper {
        width: 100%;
        height: calc(100vh - 34px - 34px);
    }
    .datatable-wrapper {
        margin-bottom: 5px;
        overflow-x: auto;
        position: relative;
        height: 100%;
        width: 100%;
    }
	.tbody{
		min-height: calc(100vh - 200px);
	}
    a:link{
		text-decoration: none;
	}
</style>