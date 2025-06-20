<?php
// -----------------------admin/restore.php------------lap 3.2.4-------2011-10-25-----------
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
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
ini_set('upload_max_filesize', '100M');

?>
<script LANGUAGE="JavaScript">
<!--
function confirmSubmit()
{
var agree=confirm("Er det sikkert, at du vil overskrive dit regnskab med denne sikkerhedskopi?");
if (agree)
        return true ;
else
        return false ;
}
// -->
</script>
<?php

$title="SALDI - genindl&aelig;s sikkerhedskopi";
$modulnr=11;
$css="../css/standard.css";
$backupdate=NULL;$backupdb=NULL;$backupver=NULL;$backupnavn=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

if (!file_exists("../temp/$db")) mkdir("../temp/$db", 0775);

print "<div align=\"center\">";
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"></div>\n";
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontent\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\"><tbody>"; # -> 1
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=\"$returside\" accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Indl&aelig;s sikkerhedskopi</td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
}

if($_POST) {
	if ($restore=if_isset($_POST['restore'])) {
		if ($restore=='OK') {
			$backup_encode=if_isset($_POST['backup_encode']);
			$filnavn=$_POST['filnavn'];
			restore($filnavn,$backup_encode,$backup_dbtype);
		} else {
			unlink($filnavn);
		}exit; 
	} 
	$fejl = $_FILES['uploadedfile']['error'];
	if ($fejl) {
		switch ($fejl) {
			case 2: print "<BODY onLoad=\"javascript:alert('Filen er for stor - er det en SALDI-sikkerhedskopi?')\">";
		}
 		upload();
		exit;
	}
	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."/restore.gz";
		$tmp=$_FILES['uploadedfile']['tmp_name'];
		system ("rm -rf ../temp/".$db."/*");
		if(move_uploaded_file($tmp, $filnavn)) {
			system ("gunzip $filnavn");
			$filnavn=str_replace(".gz","",$filnavn);
			if (file_exists($filnavn)) system ("cd ../temp/$db\n/bin/tar -xf restore");
			else system ("cd ../temp/$db\n/bin/tar -xf restore.gz");
			$infofil="../temp/".$db."/temp/backup.info";
			$fp=fopen($infofil,"r");
			if ($fp) {
				$linje=trim(fgets($fp));
				list($backupdate,$backupdb,$backupver,$backupnavn,$backup_encode,$backup_dbtype)=explode(chr(9),$linje);
				$backupfil="../temp/".$db."/temp/".$backupdb.".sql";
				$backupdato=substr($backupdate,6,2)."-".substr($backupdate,4,2)."-".substr($backupdate,0,4);
				$backuptid=substr($backupdate,-4,2).":".substr($backupdate,-2,2);
			}
			fclose($fp);
			unlink($infofil);
			if ($db_type=='mysql' && $backup_dbtype!='mysql') {
				print "<BODY onLoad=\"javascript:alert('En PostgreSQL-sikkerhedskopi kan ikke indl&aelig;ses i et MySQL-baseret system')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=backup.php\">";
				exit;
			} elseif ($db_type!='mysql' && $backup_dbtype=='mysql') {
				print "<BODY onLoad=\"javascript:alert('En MySQL-sikkerhedskopi kan ikke indl&aelig;ses i et PostgreSQL-baseret system')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=backup.php\">";
				exit;
			} 

			print "<form name=restore action=restore.php method=post>";
			print "<tr><td valign=middle align=center><table><tbody>";
			$backupnavn=trim($backupnavn);
			$regnskab=trim($regnskab);
			if ($backupnavn && $backupnavn!=$regnskab) {
				print "<tr><td colspan=2>Du er ved at overskrive dit regnskab: $regnskab<br>med en sikkerhedskopi af regnskabet: $backupnavn fra den $backupdato kl. $backuptid.</td></tr>";	
				print "<input type=\"hidden\" name=\"backup_encode\" value=\"$backup_encode\">";
				print "<input type=\"hidden\" name=\"filnavn\" value=\"$backupfil\">";
			} elseif ($backupdate) {
				print "<tr><td colspan=2>Du er ved at overskrive dit regnskab: $regnskab<br>med en sikkerhedskopi fra den $backupdato kl. $backuptid.</td></tr>";	
				print "<input type=\"hidden\" name=\"backup_encode\" value=\"$backup_encode\">";
				print "<input type=\"hidden\" name=\"filnavn\" value=\"$backupfil\">";
			} else {
				print "<tr><td colspan=2>Du er ved at overskrive dit regnskab: $regnskab.</td></tr>";	
				print "<input type=\"hidden\" name=\"filnavn\" value=\"$filnavn\">";
			}
			print "<tr><td colspan=2><hr></td></tr>";	
			print "<tr><td align=center><input type=submit value=\"OK\" name=\"restore\"></td><td align=center><input type=submit value=\"Afbryd\" name=\"restore\"></td><tr>";
			print "</tbody></table></td></tr>";
			print "</form>";
		} else {
			echo "Der er sket en fejl under hentningen - pr&oslash;v venligst igen.";
		}
	}	else upload();
} else upload();
print "</tbody></table></div>";
################################################################################################################
function upload(){

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"restore.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"99999999\">";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center>Bem&aelig;rk at alle brugere skal v&aelig;re logget ud</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center><hr width=50%></td></tr>";
	print "<tr><td width=100% align=center></td></tr>";
	print "<tr><td width=100% align=center>V&aelig;lg datafil: <input class=\"inputbox\" NAME=\"uploadedfile\" type=\"file\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"Indl&aelig;s\" onClick=\"return confirmSubmit()\"></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}

function restore($filnavn,$backup_encode,$backup_dbtype){

global $connection;
global $s_id;
global $brugernavn;
global $regnskab;
global $db;
global $sqdb;
global $squser;
global $sqpass;
global $sqhost;
global $db_encode;
global $db_type;
global $charset;

if (!$db_encode) $db_encode="LATIN9";
if (!$backup_encode) $backup_encode="UTF8";
if (!$db_type) $db_type="postgresql";
if (!$backup_dbtype) $backup_dbtype="postgresql";

$filnavn2="../temp/$db/restore.sql";
$restore="";

$fp=fopen("$filnavn","r");
$fp2=fopen("$filnavn2","w");
if ($fp) {
	while (!feof($fp)) {
		if ($linje=fgets($fp)) {
				if ($db_type=='mysql') {
				if (strpos($linje, "MySQL dump")) $dump = "OK";
			} elseif (strpos($linje, "PostgreSQL database dump")) $dump = "OK";
			if (strpos(strtolower($linje), "drop database")) {
				$restore = "NUL";
			}
			if (strpos(strtolower($linje), "drop database")) {
				$restore = "NUL";
			}
			if (strpos(strtolower($linje), "create database")) {
				$restore = "NUL";
			}
			if (strpos(strtolower($linje), "\\connect")) {
				$restore = "NUL";
			}
			if ($backup_encode!=$db_encode) {
				if ($db_encode=="UTF8" && $backup_encode=="LATIN9") {
					$linje=str_replace("SET client_encoding = 'LATIN9';","SET client_encoding = 'UTF8';",$linje);
					$ny_linje=utf8_encode($linje);
				}	elseif ($db_encode=="LATIN9" && $backup_encode=="UTF8") {
					$linje=str_replace("SET client_encoding = 'UTF8';","SET client_encoding = 'LATIN9';",$linje);
					$ny_linje=utf8_decode($linje);
				} else {
					$restore = "NUL";
				}
			} else $ny_linje=$linje;
		} else $ny_linje='';
		fwrite($fp2,"$ny_linje"); 
	}	
	if (!$restore && $dump) $restore="OK";
}
fclose($fp);
fclose($fp2);

if ($restore=='OK') {
	if ($db_type=='mysql') {
		mysql_select_db("$sqdb");
	} else {
		db_close($connection);
		$connection = db_connect ("$sqhost","$squser","$sqpass","$sqdb");
	}
	db_modify("delete from online where db='$db'",__FILE__ . " linje " . __LINE__);
	db_modify("update regnskab set version = '' where db='$db'",__FILE__ . " linje " . __LINE__);
	db_modify("DROP DATABASE $db",__FILE__ . " linje " . __LINE__);
	print "<!-- Saldi-kommentar for at skjule uddata til siden \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
	if ($db_type=='mysql') {
		db_modify("CREATE DATABASE $db",__FILE__ . " linje " . __LINE__);
		if ($db_encode=="UTF8") db_modify("SET character_set_client = 'UTF8'",__FILE__ . " linje " . __LINE__);
		else db_modify("SET character_set_client = 'LATIN1'",__FILE__ . " linje " . __LINE__);
		system("mysql -u $squser --password=$sqpass $db < $filnavn2");
	} else { 
		db_modify("CREATE DATABASE $db with encoding = '$db_encode'",__FILE__ . " linje " . __LINE__);
		system("export PGPASSWORD=$sqpass\npsql -U $squser $db < $filnavn2");
	}
	db_close($connection);
	print "<BODY ONLOAD=\"javascript:alert('Regnskabet er genskabt. Du skal logge ind igen!')\">";
	unlink($filnavn);
	unlink($filnavn_2);
	print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
	if ($popup) {
		print "<BODY ONLOAD=\"JavaScript:opener.location.reload();\"";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	} else print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";
 
} else {
	unlink($filnavn);
	unlink($filnavn_2);
	print "<BODY ONLOAD=\"javascript:alert('Det er ikke en SALDI-sikkerhedskopi, som fors&oslash;ges indl&aelig;st')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=backup.php\">";
}

print "</tbody></table>";
print "</td></tr>";
}
?>

