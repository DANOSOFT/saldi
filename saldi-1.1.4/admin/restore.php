<?php
// -----------------------admin/restore.php------------lap 1.1.3-------30.01.2008-----------
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

@session_start();
$s_id=session_id();

?>
<script LANGUAGE="JavaScript">
<!--
function confirmSubmit()
{
var agree=confirm("Er det sikkert at du vil overskrive dit regnskab med denne sikkerhedskopi?");
if (agree)
        return true ;
else
        return false ;
}
// -->
</script>
<?php

$title="SALDI - restore";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

print "<div align=\"center\">";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=\"../includes/luk.php\" accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Indl&aelig;s sikkerhedskopi</small></td>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
print "</tbody></table>";
print "</td></tr>";


if($_POST) {
	$fejl = $_FILES['uploadedfile']['error'];
	if ($fejl) {
		switch ($fejl) {
			case 2: print "<BODY onLoad=\"javascript:alert(filen er for stor - er det en SALDI backup? ')\">";
		}
 		upload();
		exit;
	}
	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$s_id.".gz";
		$tmp=$_FILES['uploadedfile']['tmp_name'];
		if(move_uploaded_file($tmp, $filnavn)) {
		restore($filnavn);
		}
		else{
			echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
		}
	}
	else upload();

/*	
if ($kladde_id)
	{
		hentdata($kladde_id);
	}
*/	
} else upload($kladde_id, $bilag);
print "</tbody></table>";
################################################################################################################
function upload(){
	global $font;

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"restore.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"9999999\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center>$font Bemærk at alle brugere skal være logget ud</td></tr>";
	print "<tr><td width=100% align=center>$font <br></td></tr>";
	print "<tr><td width=100% align=center>$font<hr width=50%></td></tr>";
	print "<tr><td width=100% align=center>$font</td></tr>";
	print "<tr><td width=100% align=center>$font V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"Indl&aelig;s\" onClick=\"return confirmSubmit()\"></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}

function restore($filnavn){
global $connection;
global $font;
global $s_id;
global $brugernavn;
global $db;
global $sqdb;
global $squser;
global $sqpass;
global $sqhost;

$restore="";

system ("gunzip $filnavn");
# system ("rm $filnavn");
$filnavn=str_replace(".gz","",$filnavn);
$fp=fopen("$filnavn","r");
if ($fp) {
	$x=0;
	while ((!feof($fp))&&($x<=10)) {
		if ($linje=trim(fgets($fp))) {
			$x++;
			if (strpos($linje, "PostgreSQL database dump")) {
				$restore = "OK";
				break;
			}
		}
	}	
}
fclose($fp);
if ($restore=='OK') {
	db_close($connection);
	$connection = db_connect ("host=$sqhost dbname=$sqdb user=$squser password=$sqpass");
	db_modify("delete from online where db='$db'");
	db_modify("DROP DATABASE $db");
	db_modify("CREATE DATABASE $db with encoding = 'LATIN9'");
	print "<!-- Saldi-kommentar for at skjule uddata til siden \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
#			system ("export PGPASSWORD=$sqpass");
		echo "export PGPASSWORD=$sqpass\npsql -U $squser $db < $filnavn<br>";	
		system("export PGPASSWORD=$sqpass\npsql -U $squser $db < $filnavn");
			print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
					db_close($connection);
					print "<BODY ONLOAD=\"javascript:alert('Regnskabet er genskabt, du skal logge ind igen !')\">";
					system ("rm $filnavn");
					print "<BODY ONLOAD=\"JavaScript:opener.location.reload();\"";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
} else {
	print "<BODY ONLOAD=\"javascript:alert('Det er ikke en SALDI backup der fors&oslash;ges indl&aelig;st')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
}


print "</tbody></table>";
print "</td></tr>";
}

