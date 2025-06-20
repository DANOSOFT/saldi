<?php
// --------------------------index/install.php-------1.1.2----04.03.2008----------
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
	<title>SALDI - det frie danske finansprogram</title>
<?php

if (file_exists("../includes/connect.php")) print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php\">";
exit;

# include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/settings.php");
include("../includes/version.php");


print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"100%\" align = \"center\" $top_bund>$font<a href=komigang.html><small>Vejledning</small></td>\n";
print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"center\">\n";


if (isset($_POST['opret'])){
	
	$db_navn=trim($_POST['db_navn']);
	$db_bruger=trim($_POST['db_bruger']);
	$db_password=trim($_POST['db_password']);
	$adm_navn=trim($_POST['adm_navn']);
	$adm_password=md5(trim($_POST['adm_password']));

	$host="localhost";
	$tempdb="template1";
	
	$connection = db_connect ("host=$host dbname=$tempdb user=$db_bruger password=$db_password");
	if (!$connection)	{
		die( "Kan ikke oprette forbindelse til PostgreSQL\n");
	}
	db_modify("CREATE DATABASE $db_navn with encoding = 'LATIN9'");
	db_close($connection);
	$connection = db_connect ("host=$host dbname=$db_navn user=$db_bruger password=$db_password");
	
transaktion("begin");

	db_modify("CREATE TABLE brugere(id serial NOT NULL, brugernavn varchar, kode varchar, status varchar, regnskabsaar integer, rettigheder varchar, PRIMARY KEY (id))");
	db_modify("INSERT INTO brugere (brugernavn, kode, rettigheder) values ('$adm_navn' ,'$adm_password', '11111111111111111111')");
	db_modify("CREATE TABLE regnskab (id serial NOT NULL,	regnskab varchar, dbhost varchar, dbuser varchar, db varchar, version varchar, sidst varchar, PRIMARY KEY (id))");
	db_modify("INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version) values ('$db_navn' ,'$host', '$db_bruger', '$db_navn', '$version')");
	db_modify("CREATE TABLE online (session_id varchar, brugernavn varchar, db varchar, dbuser varchar, rettigheder varchar, regnskabsaar integer, logtime varchar)");
	db_modify("CREATE TABLE brugerdata (id serial NOT NULL, firmanavn varchar, addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, kontakt varchar, email varchar, cvrnr varchar, regnskab varchar, brugernavn varchar, kodeord varchar, kontrol_id varchar, aktiv int, logtime varchar, PRIMARY KEY (id))");
	db_modify("CREATE TABLE tabelinfo (id serial NOT NULL, tabelnavn varchar, feltnavn varchar, bekrivelse varchar)");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'brugernavn')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'kode')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'status')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'regnskabsaar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugere' ,'rettigheder')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'regnskab')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'dbhost')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'dbuser')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('regnskab' ,'db')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'session_id')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'brugernavn')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'db')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'dbuser')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'rettigheder')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'regnskabsaar')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('online' ,'logtime')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'id')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'firmanavn')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'addr1')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'addr2')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'postnr')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'bynavn')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'kontakt')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'email')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'cvrnr')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'regnskab')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'brugernavn')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'kodeord')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'kontrol_id')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'aktiv')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'logdate')");
	db_modify("INSERT INTO tabelinfo (tabelnavn, feltnavn) values ('brugerdata' ,'logtime')");
	
	transaktion("commit");
#	rename("../includes/connect", "../includes/connect.php");
	
	
	if ($fp=fopen("../includes/connect.php","w") ) {
		skriv_connect($fp, $host, $db_bruger, $db_password, $db_navn); 	
		fclose($fp);
		print "<a href=../index/index.php>Forts&aelig;t";
	} else {
		print "<p>Der er ikke skriveadgang til kataloget \"includes\", hvor \"connect.php\" skal oprettes.</p>\n\n";
		print "<p>S&oslash;rg for at der er skriveadgang for den bruger, som den besgende k&oslash;rer som. til katalogerne \n";
		print "\"includes\", \"temp\" og \"logolib\". Se hvordan i installeringsvejledningen INSTALLATION.txt.</p>\n\n";
		print "</td></tr></table></body></html>";
		exit;
	}		
} else {

	?>
	<table width=40% align=center border="0" cellspacing="0" cellpadding="0"><tbody>
		<tr><td colspan="5" align="center"><font face="Helvetica, Arial, sans-serif"><big><b>Velkommen til SALDI</b></big></td></tr>
		<tr><td colspan="5" align="center"> <font face="Helvetica, Arial, sans-serif">Hvis du har installeret Apache, PHP og PostgreSQL kan du nu installere SALDI</td></tr>
	<FORM name="opret" METHOD=POST ACTION="install.php">	
	<tr><td colspan=2><br></td></tr>

	<tr><td><font face="Arial,Helvetica"><font>Database navn</td><td><INPUT TYPE=TEXT NAME=db_navn VALUE = "saldi"> <td><td width=5%></td></tr>
	<tr><td><br></td></tr>
	<tr><td><font face="Arial,Helvetica"><font>Postgres brugernavn</td> <td ><INPUT TYPE=TEXT NAME=db_bruger VALUE = "postgres"></td><td></td></tr>
	<tr><td><br></td></tr>
	<tr><td><font face="Arial,Helvetica"><font>db_password</td><td><INPUT TYPE=password NAME=db_password></td><td></td></tr>
	<tr><td><br></td></tr>
	<tr><td><font face="Arial,Helvetica"><font>Administrator navn</td><td><INPUT TYPE=TEXT NAME=adm_navn	VALUE = "admin"></td><td></td></tr>
	<tr><td><br></td></tr>
	<tr><td><font face="Arial,Helvetica"><font>Administrator password</td><td><INPUT TYPE=password NAME=adm_password></td><td></td></tr>
	<tr><td><br></td></tr>
	<tr><td colspan=2 align=center><INPUT TYPE=submit name=opret VALUE=installer></td></tr>

	<tr><td><br></td></tr>
	<tr>
	</tr>
	</FORM>
	<?php

	print "</tr>";

	print	"</tbody></table>";
	print	"</td></tr>";
	print	"<tr><td align=\"center\" valign=\"bottom\">";
	print	"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print	"<td style=\"border: 1px solid rgb(180,180,255); padding: 0pt 0pt 1px;\" align=\"left\" background=\"../img/grey1.gif\" width=\"100%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000000\"><small><small>&nbsp;Copyright&nbsp;&copy;&nbsp;2003-2008&nbsp;DANOSOFT&nbsp;ApS</small></small></td>";
	print	"</tbody></table>";
	print	"</td></tr>";
	print	"</tbody></table>";

}
function skriv_connect($fp, $host, $db_bruger, $db_password, $db_navn) {
	fwrite($fp," \n");
		fwrite($fp,"<?php\n");
		fwrite($fp,"// ----/includes/connect.php---------------lap 1.1.4-----04.03.2008-----\n");
		fwrite($fp,"// LICENS\n");
		fwrite($fp,"//\n");
		fwrite($fp,"// Dette program er fri software. Du kan gendistribuere det og / eller\n");
		fwrite($fp,"// modificere det under betingelserne i GNU General Public License (GPL)\n");
		fwrite($fp,"// som er udgivet af The Free Software Foundation; enten i version 2\n");
		fwrite($fp,"// af denne licens eller en senere version efter eget valg\n");
		fwrite($fp,"//\n");
		fwrite($fp,"// Dette program er udgivet med haab om at det vil vaere til gavn,\n");
		fwrite($fp,"// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se\n");
		fwrite($fp,"// GNU General Public Licensen for flere detaljer.\n");
		fwrite($fp,"//\n");
		fwrite($fp,"// En dansk oversaettelse af licensen kan laeses her:\n");
		fwrite($fp,"// http://www.fundanemt.com/gpl_da.html\n");
		fwrite($fp,"//\n");
		fwrite($fp,"// Copyright (c) 2004-2008 DANOSOFT ApS\n");
		fwrite($fp,"// ----------------------------------------------------------------------\n");
		fwrite($fp,"\n");
		fwrite($fp,	"if (!isset(\$bg)) \$bg='';\n");
		fwrite($fp,	"if (!isset(\$title)) \$title='';\n");
		fwrite($fp,"\n");
		fwrite($fp,"if (file_exists(\"../includes/db_query.php\")) {\n");
		fwrite($fp,"	include(\"../includes/db_query.php\");\n");
		fwrite($fp,"	include(\"../includes/version.php\");\n");
		fwrite($fp,"	include(\"../includes/settings.php\");\n");
		fwrite($fp,"}\n");
		fwrite($fp,"elseif (file_exists(\"../../includes/db_query.php\")){\n");
		fwrite($fp,"	include(\"../../includes/db_query.php\");\n");
		fwrite($fp,"	include(\"../../includes/version.php\");\n");
		fwrite($fp,"	include(\"../../includes/settings.php\");\n");
		fwrite($fp,"}\n");
		fwrite($fp,"\n");
		fwrite($fp,"\$sqhost = \"$host\";\n");
		fwrite($fp,"\$squser	= \"$db_bruger\";\n");
		fwrite($fp,"\$sqpass = \"$db_password\";\n");
		fwrite($fp,"\$sqdb = \"$db_navn\";\n");
		fwrite($fp,"\n");
		fwrite($fp,"#\$login = \"\";\n");
		fwrite($fp,"#\$login = \"dropdown\";\n");
		fwrite($fp,"\$login = \"cookie\";\n");
		fwrite($fp,"\n");
		fwrite($fp,"# \$brug_timestamp=\"y\";\n");
		fwrite($fp,"\n");
		fwrite($fp,"\$font = \"<font face='Arial, Helvetica, sans-serif'>\";\n");
		fwrite($fp,"\n");
		fwrite($fp,"if (\$sqpass) \$connection = db_connect (\"host=\$sqhost dbname=\$sqdb user=\$squser password=\$sqpass\");\n");
		fwrite($fp,"else \$connection = db_connect (\"host=\$sqhost dbname=\$sqdb user=\$squser password=\$sqpass\");\n");
		fwrite($fp,"if (!isset(\$connection))\n");
		fwrite($fp,"{\n");
		fwrite($fp,"	die( \"Unable to connect\");\n");
		fwrite($fp,"}\n");
		fwrite($fp,"\n");
		fwrite($fp,"?>\n");
}

?>
</head>
</body></html>
