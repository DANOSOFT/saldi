<?php
// -----------index/index.php-----------lap 3.4.8------2015-01-02---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20140106	Tilføjet link til glemt kode
// 20140428 Flyttet timestamp 3 linjer op (før </form>) (Forumbruger nielsrune) Søg 20140428    

$regnskab=''; $navn=''; $kode=''; 
$css="../css/standard.css";

if (!file_exists("../includes/connect.php")) {
	print "<meta http-equiv=\"refresh\" content=\"0;url=install.php\">\n";
	print "</head><body>\n\n";
	print "<p>Du skulle automatisk bliver videresendt til installeringssiden.</p>\n\n";
	print "<p>Skulle dette ikke ske, s&aring; <a href=\"install.php\">KLIK HER</a></p>\n\n";
	print "</body></html>\n";
	exit;
}
include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/std_func.php");
include("../includes/version.php");

if (isset ($_GET['navn'])) $navn = html_entity_decode(stripslashes($_GET['navn']),ENT_COMPAT,$charset);
if (isset ($_GET['regnskab'])) $regnskab = html_entity_decode(stripslashes($_GET['regnskab']),ENT_COMPAT,$charset);
if (isset ($_GET['tlf'])) $kode = stripslashes($_GET['tlf']);
		
if (isset($brug_timestamp)) {
 	?>
	<script language="javascript" type="text/javascript" src="../javascript/md5.js"></script>

	<script language="javascript" type="text/javascript">
		function handleLogin (loginForm) {
			var inputTimestamp = loginForm.timestamp.value;
			var inputPassword = loginForm.password.value;

			loginForm.password.value = hex_md5(inputTimestamp+hex_md5(inputPassword));
			return true;
		}
	</script>
	<?php
}
if ($db_encode=="UTF8") $charset="UTF-8";
else $charset="ISO-8859-1";
if (file_exists("../doc/vejledning.pdf")) $vejledning="../doc/vejledning.pdf";
else $vejledning="http://saldi.dk/dok/komigang.html";

PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
<html>\n
<head><title>Saldi login</title>";
if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">";
print "<meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\">\n<meta http-equiv=\"content-language\" content=\"da\">\n</head>\n";
print "<body><table style=\"width:100%;height:100%;\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";# Tabel 1 ->
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #Tabel 1.1 ->
print "<tr><td  style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" width=\"45%\"> Ver $version</td>";
print "<td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;;background:url(../img/grey1.gif)\" width=\"10%\" align = \"center\"> <a href=\"$vejledning\" target=\"_blank\">Vejledning</a></td>\n";
print "<td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;;background:url(../img/grey1.gif)\" width=\"45%\" align = \"right\">&nbsp;</td></tr>\n";
print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"middle\">\n"; # <- tabel 1.1 slut
print "<table width=\"350\" align=\"center\" border=\"5\" cellspacing=\"5\" cellpadding=\"5\"><tbody>"; # tabel 1.2 ->
print "<tr><td><FORM name=\"login\" METHOD=\"POST\" ACTION=\"login.php\" onSubmit=\"return handleLogin(this);\"><table width=\"100%\" align=center border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; # tabel 1.2.1 ->
if (isset($mastername)&&$mastername) $tmp="<big><big><big><b>$mastername</b></big></big></big>";   
elseif (strpos($_SERVER['PHP_SELF'],"beta")) $tmp="<big><big><big><b>!!! BETA !!!</b></big></big></big>";
else $tmp="<big><big><big><b>SALDI</b></big></big></big>";
print "<tr><td colspan=\"2\">";
print "<table width=\"100%\" border=\"0\"><tbody><tr><td width=\"10%\">"; # tabel 1.2.1.1 ->
print "";
if (file_exists("../img/logo.png")) print "<img style=\"border:0px solid;width:50px;heigth:50px\" alt=\"\" src=\"../img/logo.png\">";
print "</td><td width=\"80%\" align=\"center\">$tmp</td><td width=\"10%\" align=\"right\">";
if (file_exists("../img/logo.png")) print "<img style=\"border:0px solid;width:50px;heigth:50px\" alt=\"\" src=\"../img/logo.png\"></td></tr>\n";
print "</tbody></table></td></tr>"; # <- tabel 1.2.1.1
print "<tr><td colspan=\"2\"><hr></td></tr>\n";
print "<tr><td>".findtekst(322,$sprog_id)."</td>";
print "<td width=\"2%\">";
if ($login=="dropdown") {
	print "<select name=regnskab>";
	$query = db_select("select regnskab from regnskab order by regnskab asc",__FILE__ . " linje " . __LINE__);
	if (db_num_rows($query)==0)	{
		print "<option>Ingen regnskaber oprettet</option>";
		} else {
			while ($row = db_fetch_array($query))
			print "<option>".$row['regnskab']."</option>";
			print "</select>";
	}
} elseif (($login=="cookie")&&(!$navn)){
	if (isset($_COOKIE['saldi_std'])) {
		$regnskab=stripslashes($_COOKIE['saldi_std']);
	}
	print "<input class=\"inputbox\" style=\"width:160px\" type=\"TEXT\" NAME=\"regnskab\" value=\"$regnskab\">";
} else print"<input class=\"inputbox\" style=\"width:160px\" type=\"TEXT\" NAME=\"regnskab\" value=\"$regnskab\">";
print "</tr><tr><td>".findtekst(323,$sprog_id)."</td><td><INPUT class=\"inputbox\" style=\"width:160px\" TYPE=\"TEXT\" NAME=\"login\" value=\"$navn\"></td></tr>\n";
print "<tr><td>".findtekst(324,$sprog_id)."</td>";
print	"<td><INPUT class=\"inputbox\" style=\"width:160px\" TYPE=\"password\" NAME=\"password\" value=\"$kode\"></td></tr>\n";
print "<tr><td colspan=\"2\" align=\"center\"><br></td></tr>\n";
print "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"pwtjek\" value=\"Login\"></td></tr>\n";
if (isset($mastername) && strtolower($mastername)=='rotary') {
	print "<tr><td colspan=\"2\" align=center>".findtekst(325,$sprog_id)."</td></tr>\n";
}
print "<input type=\"hidden\" name=\"timestamp\" value=\"".date("U")."\">"; #20140428
print "</tbody></table></FORM></td></tr>\n"; # <- tabel 1.2.1
print "<tr><td colspan=\"2\" align=\"center\">
		<a href=\"glemt_kode.php\">Glemt adgangskode</a>
		</td></tr>\n";
print	"</tbody></table></td></tr>\n"; # <- tabel 1.2
print "<tr><td align=\"center\" valign=\"bottom\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.3 ->
print "<td width=\"20%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"left\">&nbsp;Copyright&nbsp;&copy;$copyright&nbsp;DANOSOFT&nbsp;ApS</td>";
print "<td width=\"60%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"center\">Et <a href=\"http://www.saldi.dk\" target=\"blank\">SALDI</a> regnskab</td>";
print "<td width=\"20%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"left\"><br></td>";
print "</tr></tbody></table>"; # <- tabel 1.3
print "</td></tr>\n";
print "</tbody></table>"; # <- tabel 1
if (!isset($_COOKIE['saldi_std'])) {
	print "<script language=\"javascript\" type=\"text/javascript\">";
	print "document.login.regnskab.focus();";
	print "</script>";
} else {
	print "<script language=\"javascript\" type=\"text/javascript\">";
	print "document.login.login.focus();";
	print "</script>";
}
?>
</body></html>
