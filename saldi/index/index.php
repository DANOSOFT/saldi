<?php
// --------------------------------------------------------index/index.php-----------patch1.0.9RC3----------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
	<title>SALDI - det frie danske finansprogram</title>
<?php
	# PHP 5.x er ikke understøttet.
/*	
if ( substr(phpversion(), 0, 1) == "5" ) {
		print "</head>\n<body>\n<h2>Saldi kan ikke køre med PHP 5.x</h2>\n\n";
		print "<p>\nNedgradér til PHP version 4.x.\n</p>\n\n";
		print "<p>\nEn af de kommende 1.0.x-versioner vil understøttet PHP version 5.x. \n";
		print "Se hvornår PHP 5.x kan forventes at være understøttet i Saldi på siden \n";
		print "<a href=\"http://www.saldi.dk/udvikling/plan/\">Udviklingsplan for Saldi</a>.\n\n";
		print "</body></html>\n";
		exit;
	}
*/

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
if (!file_exists("../includes/connect.php")) {
	print "<meta http-equiv=\"refresh\" content=\"0;url=install.php\">";
	exit;
}
print "</head>";
# include("../includes/connect.php");
include("../includes/connect.php");
include("../includes/db_query.php");

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"20%\" bgcolor=\"$bgcolor2\">$font <small>Ver $version</small></td>";
print "<td width=\"60%\" bgcolor=\"$bgcolor2\" align = \"center\">$font <a href=komigang.html><small>Vejledning</small></td>\n";
print "<td width=\"20%\" bgcolor=\"$bgcolor2\" align = \"right\">$font <a href=logud.php accesskey=L><small></small></td>\n";
print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"center\">\n";
?>
	 
<table width=30% align=center border="5" cellspacing="5" cellpadding="5"><tbody>
<tr><td>
<table width=100% align=center border="0" cellspacing="0" cellpadding="1"><tbody>
	<tr>
	<td colspan="3" align="center"><font face="Arial,Helvetica,sans-serif"><big><big><big><b> = SALDI = </b></big></big></big></td><tr>
	<tr><td colspan="3"><br></td></tr>
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
}
elseif ($login=="cookie"){
	if (!isset($_COOKIE['saldi_std'])) {print "<INPUT TYPE=TEXT NAME=regnskab>";} // Ingen cookie
	else	{
	$regnskab=stripslashes($_COOKIE['saldi_std']);
	print"<INPUT TYPE=TEXT NAME=regnskab value=\"$regnskab\">";
	}
}
else {print "<INPUT TYPE=TEXT NAME=regnskab>";}

?>
</tr>
<tr>
	<td><font face="Arial,Helvetica,sans-serif"><font>Brugernavn</td>
	<td ><INPUT TYPE=TEXT NAME=login></td>
</tr>
<tr>
	<td><font face="Arial,Helvetica,sans-serif"><font>Password</td>
	<td><INPUT TYPE=password NAME=password></td>
</tr>
<tr><td colspan="3" align="center"><br></td></tr>
<tr>
	<td colspan=3 align=center><INPUT TYPE=submit name=pwtjek VALUE=login></td>
</tr>
<INPUT TYPE=HIDDEN name="timestamp" value="<?php echo mktime("now"); ?>">
</FORM>
		</tbody></table>
		</td></tr>
		</tbody></table>
	</td></tr>
	<tr><td align="center" valign="bottom">
		<table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
			<td width="25%" bgcolor=#bbbbbb><font face="Helvetica, Arial, sans-serif" color="#000000"><small><small>Copyright&nbsp;&copy;&nbsp;2004-2006&nbsp;DANOSOFT&nbsp;ApS</small></small></td>
			<td width="50%" bgcolor=#bbbbbb align = "center"><font face="Helvetica, Arial, sans-serif"></td>
			<td width="25%" bgcolor=#bbbbbb align = "right"><font face="Helvetica, Arial, sans-serif"></td>
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
