<?php
	@session_start();
	$s_id=session_id();

// ---- /systemdata/importer_posmenu.php ------- vers. 4.0.4 -- 2021-11-19 --
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2015-2021 Saldi.dk ApS
// --------------------------------------------------------------------------
// 20211119 CA  Import PoS menus

$saldifileformat="saldi_posmenus";
$title="Importer POS menuer";
$css="../css/standard.css";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/posmenu_import.php");
include("../includes/std_func.php"); #20210713 

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href='posmenuer.php' accesskey='L'>".findtekst(30, $sprog_id)."</a></td>"; # Close
print "<td width=\"80%\" $top_bund>".findtekst(1934, $sprog_id)."</td>"; # Import POS menus
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";

if ($_POST) {
	$submit=$_POST['submit'];
	$filnavn=$_POST['filnavn'];

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$saldifileformat."_".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			posmenu_import($filnavn);
		} else {
			echo findtekst(1370, $sprog_id); # An error occurred while retrieving, please try again
		}
		if ($popup) {
			print "<div style=\"text-align: center;\">".findtekst(1935, $sprog_id)."<br></div>"; # Import of POS menus successful
			print "<meta http-equiv=\"refresh\" content=\"3;URL=../includes/luk.php\">";
			exit;
		} else  print "<BODY onLoad=\"JavaScript:alert('".findtekst(1935, $sprog_id)."')\">"; # Import of POS menus successful
	} else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		exit;
	}
} else {
	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"importer_posmenu.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"200000\">";
	print "<tr><td width=100% align=center> ".findtekst(1936, $sprog_id).": <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>"; # Select POS menus file
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"".findtekst(1078, $sprog_id)."\" /></td></tr>"; # Fetch
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}
?>
</tbody></table>
</body></html>
