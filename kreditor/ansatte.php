<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------- kreditor/ansatte.php --------lap 4.1.1--- 2022.03.13 -------
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
// Copyright (c) 2003-2022 saldi.dk aps
// ----------------------------------------------------------------------
// 20220313 PHR Added ",__FILE__ . " linje " . __LINE__" to queries
// 20260708 Sawaneh Grid Framework header + fixed "Uforudset hændelse" on save (posnr set on insert, redirect back to account)

@session_start();
$s_id = session_id();

$modulnr = 8;
$title   = "Leverandørkort";
$css     = "../css/standard.css";

global $menu;

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

if ($_GET) {
	$id       = isset($_GET['id']) ? $_GET['id'] : 0;
	$returside = isset($_GET['returside']) ? $_GET['returside'] : '';
	$ordre_id = isset($_GET['ordre_id']) ? $_GET['ordre_id'] : '';
	$fokus    = isset($_GET['fokus']) ? $_GET['fokus'] : '';
	$konto_id = isset($_GET['konto_id']) ? $_GET['konto_id'] : '';
}

if ($_POST) {
	$id       = $_POST['id'];
	$submit   = trim($_POST['submit']);
	$delete   = if_isset($_POST['delete'], NULL);
	$konto_id = $_POST['konto_id'];
	$navn     = addslashes(trim($_POST['navn']));
	$addr1    = addslashes(trim($_POST['addr1']));
	$addr2    = addslashes(trim($_POST['addr2']));
	$postnr   = addslashes(trim($_POST['postnr']));
	$bynavn   = addslashes(trim($_POST['bynavn']));
	$tlf      = addslashes(trim($_POST['tlf']));
	$mobile   = addslashes(trim($_POST['mobile']));
	$mobil    = addslashes(trim($_POST['mobil']));
	$email    = addslashes(trim($_POST['email']));
	$cprnr    = addslashes(trim($_POST['cprnr']));
	$notes    = addslashes(trim($_POST['notes']));
	$ordre_id = $_POST['ordre_id'];
	$returside = $_POST['returside'];
	$fokus    = $_POST['fokus'];

	if ($submit == "Slet" || $delete) {
		if ($id) db_modify("delete from ansatte where id = '$id'", __FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=kreditorkort.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus\">";
		exit;
	} else {
		if ($postnr && !$bynavn) $bynavn = bynavn($postnr);
		// The "mobile" column was added to ansatte in a later schema version. Older
		// databases only have "mobil", so guard against writing a column that is missing
		// (otherwise the insert/update fails with "Uforudset hændelse").
		$has_mobile = (bool) db_fetch_array(db_select("select 1 as ok from information_schema.columns where table_name = 'ansatte' and column_name = 'mobile'", __FILE__ . " linje " . __LINE__));
		if (($id == 0) && ($navn)) {
			// assign the next position number so the contact list keeps a stable order
			$row  = db_fetch_array(db_select("select coalesce(max(posnr),0)+1 as np from ansatte where konto_id = '$konto_id'", __FILE__ . " linje " . __LINE__));
			$posnr = $row['np'];
			$mobile_col = $has_mobile ? "mobile, " : "";
			$mobile_val = $has_mobile ? "'$mobile', " : "";
			db_modify("insert into ansatte (navn, konto_id, addr1, addr2, postnr, bynavn, tlf, {$mobile_col}mobil, email, cprnr, notes, lukket, posnr) values ('$navn', '$konto_id', '$addr1', '$addr2', '$postnr', '$bynavn', '$tlf', {$mobile_val}'$mobil', '$email', '$cprnr', '$notes', '', '$posnr')", __FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			$mobile_set = $has_mobile ? "mobile = '$mobile', " : "";
			db_modify("update ansatte set navn = '$navn', konto_id = '$konto_id', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', email = '$email', tlf = '$tlf', {$mobile_set}mobil = '$mobil', cprnr = '$cprnr', notes = '$notes', lukket = '' where id = '$id'", __FILE__ . " linje " . __LINE__);
		}
		// return to the account so the new contact person is visible in the list
		print "<meta http-equiv=\"refresh\" content=\"0;URL=kreditorkort.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus\">";
		exit;
	}
}

$query = db_select("select firmanavn from adresser where id = '$konto_id'", __FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$firmanavn = $row['firmanavn'];

######################### Top menu #########################

if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft headLink\"><a href=\"kreditorkort.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus\" accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst('30|Tilbage', $sprog_id) . "</a></div>";
	print "<div class=\"headerTxt\">$firmanavn - " . findtekst('1262|Ansatte', $sprog_id) . "</div>";
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
	print "</div>";
	print "<div class='content-noside'>";
	print "<table cellpadding=\"0\" cellspacing=\"10\" border=\"0\" class='dataTableForm' style='width:100%'><tbody>\n";
} elseif ($menu == 'S') {
	// Side-menu mode: modern header consistent with the redesigned creditor card (kreditorkort.php)
	$tilbage_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
	$add_icon     = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M440-280h80v-160h160v-80H520v-160h-80v160H280v80h160v160Zm40 200q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';
	print "<style>.center-btn{display:flex;align-items:center;justify-content:center;text-decoration:none;gap:5px;} a:link{text-decoration:none;}</style>";
	print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
	print "<tr bgcolor=$bg><td colspan=\"3\" align=\"center\" valign=\"top\">\n";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
	print "<td width='5%'><a href=\"kreditorkort.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus\" accesskey=L><button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">" . $tilbage_icon . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>\n";
	print "<td width='90%' style='$topStyle' align='center'>$firmanavn - " . findtekst('1262|Ansatte', $sprog_id) . "</td>\n";
	print "<td width='5%'><a href=\"ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$konto_id\" accesskey='N'><button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">" . $add_icon . findtekst('39|Ny', $sprog_id) . "</button></a></td>\n";
	print "</tbody></table>\n";
	print "</td></tr>\n";
	print "<tr><td align='center' valign='top'>\n";
	print "<table cellpadding=\"0\" cellspacing=\"10\" border=\"0\" class='dataTableForm' style='width:100%'><tbody>\n";
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=kreditorkort.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus accesskey=L>" . findtekst('30|Tilbage', $sprog_id) . "</a></td>";
	print "<td width=\"80%\" $top_bund>$firmanavn - " . findtekst('1262|Ansatte', $sprog_id) . "</td>";
	print "<td width=\"10%\" $top_bund><a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$konto_id accesskey=N>" . findtekst('39|Ny', $sprog_id) . "</a><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<td align = center valign = center>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
}

###########################################################

if ($id > 0) {
	$query = db_select("select * from ansatte where id = '$id'", __FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id = htmlentities($row['konto_id'], ENT_COMPAT, $charset);
	$navn     = htmlentities($row['navn'], ENT_COMPAT, $charset);
	$addr1    = htmlentities($row['addr1'], ENT_COMPAT, $charset);
	$addr2    = htmlentities($row['addr2'], ENT_COMPAT, $charset);
	$postnr   = htmlentities($row['postnr'], ENT_COMPAT, $charset);
	$bynavn   = htmlentities($row['bynavn'], ENT_COMPAT, $charset);
	$email    = htmlentities($row['email'], ENT_COMPAT, $charset);
	$tlf      = htmlentities($row['tlf'], ENT_COMPAT, $charset);
	$mobile   = htmlentities(isset($row['mobile']) ? $row['mobile'] : '', ENT_COMPAT, $charset);
	$mobil    = htmlentities($row['mobil'], ENT_COMPAT, $charset);
	$cprnr    = htmlentities($row['cprnr'], ENT_COMPAT, $charset);
	$notes    = htmlentities($row['notes'], ENT_COMPAT, $charset);
} else {
	$id = 0;
	$navn   = isset($navn) ? $navn : '';
	$addr1  = isset($addr1) ? $addr1 : '';
	$addr2  = isset($addr2) ? $addr2 : '';
	$postnr = isset($postnr) ? $postnr : '';
	$bynavn = isset($bynavn) ? $bynavn : '';
	$email  = isset($email) ? $email : '';
	$tlf    = isset($tlf) ? $tlf : '';
	$mobile = isset($mobile) ? $mobile : '';
	$mobil  = isset($mobil) ? $mobil : '';
	$notes  = isset($notes) ? $notes : '';
}
print "<form name=ansatte action=ansatte.php method=post>";
print "<input type=hidden name=id value='$id'>";
print "<input type=hidden name=konto_id value='$konto_id'>";
print "<input type=hidden name=ordre_id value='$ordre_id'>";
print "<input type=hidden name=returside value='$returside'>";
print "<input type=hidden name=fokus value='$fokus'>";


print "<td>" . findtekst('138|Navn', $sprog_id) . "</td><td><br></td><td><input class=\"inputbox\" type=\"text\" size=25 name=navn value=\"$navn\"></td></tr>";
print "<tr><td>" . findtekst('140|Adresse', $sprog_id) . "</td><td><br></td><td><input class=\"inputbox\" type=\"text\" size=25 name=addr1 value=\"$addr1\"></td>";
print "<td><br></td>";
print "<td>" . findtekst('142|Adresse 2', $sprog_id) . "</td><td><br></td><td><input class=\"inputbox\" type=\"text\" size=25 name=addr2 value=\"$addr2\"></td></tr>";
print "<tr><td>" . findtekst('36|Postnr.', $sprog_id) . "</td><td><br></td><td><input class=\"inputbox\" type=\"text\" size=6 name=postnr value=\"$postnr\"></td>";
print "<td><br></td>";
print "<td>" . findtekst('46|By', $sprog_id) . "</td><td><br></td><td><input class=\"inputbox\" type=\"text\" size=25 name=bynavn value=\"$bynavn\"></td></tr>";
print "<tr><td>" . findtekst('52|E-mail', $sprog_id) . "</td><td><br></td><td><input class=\"inputbox\" type=\"text\" size=25 name=email value=\"$email\"></td>";
print "<td><br></td>";
print "<td>" . findtekst('401|Mobil', $sprog_id) . "</td><td><br></td><td><input class=\"inputbox\" type=\"text\" size=10 name=mobil value=\"$mobil\"></td></tr>";
print "<tr><td>" . findtekst('654|Lokalnr.', $sprog_id) . "</td><td><br></td><td><input class=\"inputbox\" type=\"text\" size=10 name=tlf value=\"$tlf\"></td>";
print "<td><br></td>";
print "<td>" . findtekst('655|Lokal mobil', $sprog_id) . "</td><td><br></td><td><input class=\"inputbox\" type=\"text\" size=10 name=mobile value=\"$mobile\"></td></tr>";
print "<td><br></td>";
print "<tr><td valign=top>" . findtekst('659|Bemærkning', $sprog_id) . "</td><td colspan=7><textarea class=\"inputbox\" name=\"notes\" rows=\"3\" cols=\"85\">$notes</textarea></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";
print "<td><br></td><td><br></td><td><br></td>";
print "<td align = center><input type=submit accesskey=\"g\" value=\"" . findtekst('3|Gem', $sprog_id) . "\" name=\"submit\"></td>";
print "<td><br></td>";
$return_confirm = findtekst('2696|Er du sikker på, at du vil slette denne ansatte?', $sprog_id);
print "<td align = center><input type=submit accesskey=\"s\" value=\"" . findtekst('1099|Slet', $sprog_id) . "\" name=\"delete\" onclick='return confirm(\"$return_confirm\");'></td>";
print "</form>";

######################### Footer #########################

print "</tbody></table>";

if ($menu == 'T') {
	print "</div>"; // .content-noside
	include_once '../includes/topmenu/footer.php';
} elseif ($menu == 'S') {
	print "</td></tr>";      // close the row holding the form table
	print "</tbody></table>"; // close the outer wrapper table
	include_once '../includes/oldDesign/footer.php';
} else {
	print "</td></tr>";
	print "<tr><td align = \"center\" valign = \"bottom\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<td width=\"100%\"><div class=top_bund><br></div></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "</tbody></table>";
	print "</body></html>";
}
?>
