<?php
// -----------------index/skift_kode.php -----------2014.09.xx----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public Licenser (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg, dog med med
// foelgende tilfoejelse:
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaetelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
// copyright (c) 2003-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Skift adgangskode";

include("../includes/connect.php");
require("../includes/pbkdf2.php");

if ($_POST) {
	include("../includes/online.php");
	$glkode=trim($_POST['glkode']);
	$nykode1=trim($_POST['nykode1']);
	$nykode2=trim($_POST['nykode2']);

	if ($glkode!=$nykode1) { // glkode og nykode må ikke være ens
		if ($nykode1==$nykode2 && $glkode && $nykode1) { // alle felter udfyldt og nykode1 er lig nykode2
			$r=db_fetch_array(db_select("select kode from brugere where brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__));
			if($r['kode'] == md5($glkode)) { // glkode skal være korrekt
				$nykode1 = \PBKDF2\create_hash($nykode1); // Genererer ny unik salt og hash
				db_modify("update brugere set kode='$nykode1' where brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__);
				print "<tr><td align=center> Adgangskode &aelig;ndret! Viderestiller...</td></tr>";
				print "<meta http-equiv=\"refresh\" content=\"2;url=index.php\">";
				exit;
			} elseif ($r['kode']) {
				print "<tr><td align=center> Der er tastet forkert v&aelig;rdi i \"Gl. adgangskode\"</td></tr>";
			}
		} else print "<tr><td align=center> Der er tastet forskellige v&aelig;rdier i \"Ny adgangskode\" & \"Bekr&aelig;ft ny kode\"</td></tr>";
	} else {
		print "<tr><td align=center> Adgangskoden skal skiftes til en ny</td></tr>";
	}
}

print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
<html>\n
<head><title>Glemt kode</title>";
if ($css) print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">";
print "<meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\"></head>\n";
print "<body><table style=\"width:100%;height:100%;\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";# Tabel 1 ->
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #Tabel 1.1 ->
print "<tr><td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" width=\"10%\"> Ver $version</td>";
print "<td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;;background:url(../img/grey1.gif)\" width=\"80%\" align = \"center\"> Skift adgangskode for bruger $brugernavn</td>\n";
print "<td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;;background:url(../img/grey1.gif)\" width=\"10%\" align = \"right\">&nbsp;</td></tr>\n";
print "</tbody></table></td></tr>";

print "<tr><td align=\"center\" valign=\"middle\">Nye sikkerhedsforanstaltninger i Saldi n&oslash;dvendigg&oslash;r, at du skifter din adgangskode til en ny.

Dette vil i h&oslash;j grad sikre din adgangskode i komme til uvedkommendes kendskab, herunder i tilf&aelig;lde af dataindbrud, eller hvis uvedkommende f&aring;r adgang til en sikkerhedskopi.

Bem&aelig;rk, at dine gemte sikkerhedskopier indeholder din hidtidige kode, hvorfor du b&oslash;r gemme nye sikkerhedskopier til erstatning for dine gamle.
	</td></tr>";

print "<tr><td align=\"center\" valign=\"middle\">\n"; # <- tabel 1.1 slut
print "<form name=\"brugerdata\" action=\"skift_kode.php\" method=\"post\">";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
print "<tr><td align=\"center\" colspan=\"2\"><b>Skift adgangskode</b></td></tr>";
print "<tr><td> Gl. adgangskode</td><td><input type=\"password\" size=\"20\" name=\"glkode\"></td></tr>";
print "<tr><td> Ny adgangskode</td><td><input type=\"password\" size=\"20\" name=\"nykode1\"></td></tr>";
print "<tr><td>Bekr&aelig;ft ny kode</td><td><input type=\"password\" size=\"20\" name=\"nykode2\"></td></tr>";
print "<td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Ok\" name=\"submit\"></td>";
print "</form";
print "</tr></tbody></table></td></tr>";
print "</td></tr>";
?>
</tbody></table>
</body></html>
