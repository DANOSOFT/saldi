<?php
// -----------index/index.php-----------lap 3.0.2------2010-05-21---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------
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

print "</head>";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td  style=\"border: 1px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\" background=\"../img/grey1.gif\" width=\"45%\"> Ver $version</td>";
print "<td  style=\"border: 1px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/grey1.gif\" width=\"10%\" align = \"center\"> <a href=\"http://saldi.dk/dok/komigang.html\" target=\"_blank\">Vejledning</td>\n";
print "<td  style=\"border: 1px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\" background=\"../img/grey1.gif\" width=\"45%\" align = \"right\">&nbsp;</td>\n";
print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"center\">\n";
?>
	 
<table width=350 align=center border="5" cellspacing="5" cellpadding="5"><tbody>
<tr><td>
<table width=100% align=center border="0" cellspacing="0" cellpadding="1"><tbody>
<tr><td colspan="3" align="center"><font face="Arial,Helvetica,sans-serif"><span style="color:#000077"><big><big><big><b>SALDI </b></big></big></big></span></td></tr>
<tr><td colspan="3"><hr></td></tr>
<FORM name="login" METHOD=POST ACTION="login.php" onSubmit="return handleLogin(this);">
<tr>
	<td> <font face="Arial,Helvetica,sans-serif"><font>Regnskab</td>
	<td width=2%>
<?php
if ($login=="dropdown") {
	print "<select name=regnskab>";
	$query = db_select("select regnskab from regnskab order by regnskab asc");
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
	print"<INPUT TYPE=TEXT NAME=regnskab value=\"$regnskab\">";
} else print"<INPUT TYPE=TEXT NAME=regnskab value=\"$regnskab\">";
?>
</tr>
<tr>
	<td><font face="Arial,Helvetica,sans-serif"><font>Brugernavn</td>
	<td ><INPUT TYPE=TEXT NAME=login value="<?php echo $navn ?>"></td>
</tr>
<tr>
	<td><font face="Arial,Helvetica,sans-serif"><font>Adgangskode</td>
	<td><INPUT TYPE=password NAME=password value="<?php echo $kode ?>"></td>
</tr>
<tr><td colspan="3" align="center"><br></td></tr>
<tr>
	<td colspan=3 align=center><input type="submit" name="pwtjek" value="Login"></td>
</tr>
<INPUT TYPE=HIDDEN name="timestamp" value="<?php echo mktime("now"); ?>">
</FORM>
		</tbody></table>
		</td></tr>
		</tbody></table>
	</td></tr>
	<tr><td align="center" valign="bottom">
		<table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
			<td style="border: 1px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;" align="left" background="../img/grey1.gif" width="100%" bgcolor="<?php print $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000000">&nbsp;Copyright&nbsp;&copy;&nbsp;2003-2010&nbsp;DANOSOFT&nbsp;ApS</td>
		</tbody></table>
	</td></tr>
</tbody></table>
</head>
<?php if (!isset($_COOKIE['saldi_std'])) {
	?>
	<script language="javascript">
	document.login.regnskab.focus();
	</script>
	<?php 
} else {
	?>
	<script language="javascript">
	document.login.login.focus();
	</script>
	<?php 
} 
?>
	</body></html>
