<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------admin/restore.php--------lap 4.1.0------2024-11-08-----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2020 saldi.dk ApS
// ----------------------------------------------------------------------
// 20160609 PHR if ($POST) fungerer ikke mere, hvis ikke det angives hvad der postes.  
// 20200308 PHR Varius changes related til Centos 8 / mariadb /postgresql 9x
// 20241108 PHR PHP8

@session_start();
$s_id=session_id();
ini_set('display_errors',0);

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
$title="SALDI - genindlæs sikkerhedskopi";
$modulnr=11;
$css="../css/standard.css";
$backupdate=$backupdb=$backupver=$backupnavn=$filnavn=$menu=$regnskab=$timezone=$popup=$uploadedfile=NULL;

include("../includes/connect.php");
if (isset($_GET['db']) && $_GET['db']) {
	$db=$_GET['db'];
	if (!db_exists($db)) {
		db_create($db);		
	}
	if (!$regnskab) {
		include("../includes/connect.php");
		$qtxt = "select regnskab from regnskab where db='$db'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$regnskab=$r['regnskab'];
	}
	db_connect($sqhost, $squser, $sqpass, $db, "");
	print "<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset;\">\n";
	print "<meta http-equiv=\"content-language\" content=\"da\">\n";
	print "<meta name=\"google\" content=\"notranslate\">\n";
} else include("../includes/online.php");	
include("../includes/std_func.php");

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

if (!file_exists("../temp/$db")) mkdir("../temp/$db", 0775);

include("../includes/topline_settings.php");

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
} elseif ($menu=='S') {
	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>";
	print "<tr><td height = '25' align='center' valign='top'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";

	print "<td width='10%'><a href='$returside' accesskey=L>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
		   .findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

	print "<td width='80%' style='$topStyle' align='center'>".findtekst('1247|Indlæs sikkerhedskopi', $sprog_id)."</td>";
	print "<td width='10%' style='$topStyle' align='center'><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=\"$returside\" accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Indlæs sikkerhedskopi</td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
}
if(isset($_FILES['uploadedfile']['name']) || isset($_POST['filnavn'])) { # 20160609
	if ($restore=if_isset($_POST['restore'])) {
		if ($restore=='OK') {
			print "<script>javascript:document.body.style.cursor = 'wait';</script>";
			$backup_encode=if_isset($_POST['backup_encode']);
			$backup_dbtype=if_isset($_GET['backup_dbtype']);
			$filnavn=$_POST['filnavn'];
			restore($filnavn,$backup_encode,$backup_dbtype);
		} else {
			unlink($filnavn);
			print "<script>javascript:document.body.style.cursor = 'default';</script>";
		} 
	}
	$fejl = $_FILES['uploadedfile']['error'];
	if ($fejl) {
		print "<script>javascript:document.body.style.cursor = 'default';</script>";
		switch ($fejl) {
			case 1: print "<BODY onLoad=\"javascript:alert('Filen er for stor - Kontroller upload_max_filesize i php.ini')\">";
			case 2: print "<BODY onLoad=\"javascript:alert('Filen er for stor - er det en SALDI-sikkerhedskopi?')\">";
		}
	}
#cho __line__."<br>";
	if (basename($_FILES['uploadedfile']['name'])) {
#cho __line__."<br>";
		$filnavn="../temp/".$db."/restore.gz";
		$tmp=$_FILES['uploadedfile']['tmp_name'];
		system ("rm -rf ../temp/".$db."/*");
		if(move_uploaded_file($tmp, $filnavn)) {
#cho __line__."<br>";
			system ("gunzip $filnavn");
			$filnavn=str_replace(".gz","",$filnavn);
			if (file_exists($filnavn)) system ("cd ../temp/$db\n/bin/tar -xf restore");
			else system ("cd ../temp/$db\n/bin/tar -xf restore.gz");
			$infofil="../temp/".$db."/temp/backup.info";
			$fp=fopen($infofil,"r");
			if ($fp) {
#cho __line__."<br>";
				$linje=trim(fgets($fp));
				list($backupdate,$backupdb,$backupver,$backupnavn,$backup_encode,$backup_dbtype)=explode(chr(9),$linje);
				$backupfil="../temp/".$db."/temp/".$backupdb.".sql";
				$backupdato=substr($backupdate,6,2)."-".substr($backupdate,4,2)."-".substr($backupdate,0,4);
				$backuptid=substr($backupdate,-4,2).":".substr($backupdate,-2,2);
			}
#cho __line__."<br>";
			if ($fp) fclose($fp);
			unlink($infofil);
#cho __line__."<br>";
			print "<script>javascript:document.body.style.cursor = 'default';</script>";
#cho __line__."<br>";
			if (($db_type=='mysql' or $db_type=='mysqli') && ($backup_dbtype!='mysql' and $backup_dbtype!='mysqli')) { #RG_mysqli
				print "<BODY onLoad=\"javascript:alert('En PostgreSQL-sikkerhedskopi kan ikke indl&aelig;ses i et MySQL-baseret system')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=backup.php\">";
				exit;
			} elseif ( ($db_type!='mysql' && $db_type!='mysqli') && ($backup_dbtype=='mysql' or $backup_dbtype=='mysqli') ) { #RG_mysqli
				print "<BODY onLoad=\"javascript:alert('En MySQL-sikkerhedskopi kan ikke indl&aelig;ses i et PostgreSQL-baseret system')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=backup.php\">";
				exit;
			} 
			print "<script>javascript:document.body.style.cursor = 'default';</script>";
			print "<form name='restore' action='restore.php?db=$db&backup_dbtype=$backup_dbtype' method='post'";
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
			print "<tr><td align=center><input type='submit' value='OK' name='restore' 
				onClick = \"document.body.style.cursor = 'wait';\"></td>";
			print "<td align=center><input type='submit' value='Afbryd' name='restore' 
			onClick = \"document.body.style.cursor = 'default';\"></td>	";
			print "</tbody></table></td></tr>";
			print "</form>";
		} else {
			echo "Der er sket en fejl under hentningen - pr&oslash;v venligst igen.";
		}
	}	else upload($db);
} else upload($db);
print "</tbody></table></div>";
################################################################################################################
function upload($db){

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"restore.php?db=$db\" method=\"POST\" 
	onsubmit=	\"document.body.style.cursor = 'wait';\">";
#	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"99999999\">";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center>Bem&aelig;rk at alle brugere skal v&aelig;re logget ud</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center><hr width=50%></td></tr>";
	print "<tr><td width=100% align=center></td></tr>";
	print "<tr><td width=100% align=center>V&aelig;lg datafil: <input class=\"inputbox\" NAME=\"uploadedfile\" type=\"file\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"Indl&aelig;s\" onClick=\"return confirmSubmit();\"></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}

function restore($filnavn,$backup_encode,$backup_dbtype){
#cho __line__."<br>";

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
#cho __line__."<br>";

if (!$db_encode) $db_encode="LATIN9";
if (!$backup_encode) $backup_encode="UTF8";
if (!$db_type) $db_type="postgresql";
if (!$backup_dbtype) $backup_dbtype="postgresql";
#cho __line__."<br>";

$filnavn2="../temp/$db/restore.sql";
$restore="";
$fp=fopen("$filnavn","r");
$fp2=fopen("$filnavn2","w");
#cho __line__."<br>";

if ($fp) {
	while (!feof($fp)) {
		if ($linje=fgets($fp)) {
				if ($db_type=='mysql' or $db_type=='mysqli') {
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
} else echo "$filnavn ikke fundet";
fclose($fp);
fclose($fp2);
#cho __line__." $restore	<br>";

if ($restore=='OK') {
#cho __line__." $db_type<br>";
	if ($db_type=='mysql') {
		mysql_select_db("$sqdb");
	} else if ($db_type=='mysqli') { #RG_mysqli
		$connection = db_connect ("$sqhost", "$squser", "$sqpass", "$sqdb");
		mysqli_select_db($connection, $sqdb);
	} else {
#cho __line__." $db_type<br>";
		if ($connection) db_close($connection);
#cho __line__." $db_type<br>";
		$connection = db_connect ("$sqhost","$squser","$sqpass","$sqdb");
#cho __line__." $db_type<br>";
	}
#cho __line__." $db_type<br>";
	db_modify("delete from online where db='$db'",__FILE__ . " linje " . __LINE__);
#cho __line__." $db_type<br>";
	db_modify("update regnskab set version = '' where db='$db'",__FILE__ . " linje " . __LINE__);
#cho __line__." $db_type<br>";
	db_modify("DROP DATABASE $db",__FILE__ . " linje " . __LINE__);
#cho __line__." $db_type<br>";
		db_create($db);
#cho __line__."<br>";
  print "<!-- Saldi-kommentar for at skjule uddata til siden \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
	if (substr($db_type,0,5)=='mysql') system("mysql -u $squser --password=$sqpass $db < $filnavn2");
	else {
		system("export PGPASSWORD=$sqpass\n/usr/bin/psql -U $squser $db < $filnavn2");
	}
	db_close($connection);
  print "-->";
#cho __line__."<br>";
	alert('Regnskabet er genskabt. Du skal logge ind igen!');
	unlink($filnavn);
	unlink($filnavn2);
	print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
#	if (isset($popup) ) {
#		print "<BODY ONLOAD=\"JavaScript:opener.location.reload();\"";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
#	} else 
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";
} else {
	unlink($filnavn);
	unlink($filnavn2);
	print "<BODY ONLOAD=\"javascript:alert('Det er ikke en SALDI-sikkerhedskopi, som fors&oslash;ges indl&aelig;st')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=backup.php\">";
}

print "</tbody></table>";
print "</td></tr>";
}
?>

