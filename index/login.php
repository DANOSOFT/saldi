<?php
ob_start(); //Starter output buffering
// --------------index/login.php----------lap 3.3.8------2014-01-06------
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
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2013.09.19 Tjekkede ikke om der var opdateringer ved login i "hovedregnskab" Søg 20130919
// 2014.01.06	Tilføjet opslag i tmp_kode. Søg tmp_kode

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$fortsaet=NULL;

include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/tjek4opdat.php");
require("../includes/pbkdf2.php");

if ($db_encode=="UTF8") $charset="UTF-8";
else $charset="ISO-8859-1";
/* print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
<html>\n
<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\">\n";
if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\" />";
print "</head>";
*/

$unixtime=date("U");

if ((isset($_POST['regnskab']))||($_GET['login']=='test')) {
	if (isset($_POST)){
		$regnskab = db_escape_string(trim($_POST['regnskab']));
		$brugernavn = db_escape_string(trim($_POST['login']));
		$password = db_escape_string(trim($_POST['password'])); // Hvis ikke skiftet til PBKDF2: password i formatet uppercase( md5( timestamp + uppercase( md5(original_password) ) ) )
		(isset($_POST['timestamp']))?$timestamp = trim($_POST['timestamp']):$timestamp=NULL;
		if (isset($_POST['fortsaet'])) $fortsaet = $_POST['fortsaet'];
		if (isset($_POST['afbryd'])) $afbryd = $_POST['afbryd'];
	}	else {
		 $regnskab = "test";
		 $brugernavn = "test";
		 $password = "test";
	}
	$r=db_fetch_array(db_select("select * from regnskab where regnskab = '$sqdb'",__FILE__ . " linje " . __LINE__));
	$masterversion=$r["version"];
	$query = db_select("select * from regnskab where regnskab = '$regnskab'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$dbuser = trim($row['dbuser']);
		$dbver = trim($row['version']);
		if (isset($row['dbpass'])) $dbpass = trim($row['dbpass']);
		$db = trim($row['db']);
		$db_id= trim($row['id']);
		$post_max = $row['posteringer']*1;
		$bruger_max = $row['brugerantal']*1;	
		$lukket = $row['lukket'];
		if (!$db) {
			$db=$sqdb;
			db_modify("update regnskab set db='$sqdb' where id='$db_id'",__FILE__ . " linje " . __LINE__);
		}
		if ($lukket) {
			if (!$mastername) $mastername='DANOSOFT';
			//print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab er lukket!\\nKontakt $mastername for gen&aring;bning')\">";
			header("Location: index.php?e=".base64_encode("Regnskab $regnskab er lukket! Kontakt $mastername for genåbning"));
			exit;
			// login (htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset));
		}
#		if (isset($fortsaet)) {
#			 db_modify("delete from online where db='$db' and brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__);
#		}
		if (isset($afbryd)) {
			header("Location: index.php");
			//login (htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset));
		}
		$tmp=date("U");
		if ($masterversion > "1.1.3") db_modify("update regnskab set sidst='$tmp' where id = '$db_id'",__FILE__ . " linje " . __LINE__);
	}	else {
		if ($regnskab) {
			header("Location: index.php?e=".base64_encode("Regnskab $regnskab findes ikke"));
			exit;
		} // login (htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset));
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";		exit;
	}
} else {
	// login (htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset));
	header("Location: index.php");
	exit;
}

// Hvis PHP extension mcrypt og/eller hash ikke er indlæst, kan PBKDF2-algoritmen ikke køres.
if (!extension_loaded('mcrypt') || !extension_loaded('hash')) {
        header("Location: index.php?e=".base64_encode("PHP extension mcrypt og/eller hash er ikke indlæst. Prøv at installere pakken php5-mcrypt."));
        // login (htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset));
        exit;
}


if ((!(($regnskab=='test')&&($brugernavn=='test')&&($password=='test')))&&(!(($regnskab=='demo')&&($brugernavn=='admin')))) {
	$udlob=date("U")-36000;
	$x=0;
	$q=db_select("select distinct(brugernavn) from online where brugernavn != '$brugernavn' and db = '$db' and session_id != '$s_id'  and logtime > '$udlob'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		$aktiv[$x]=$r['brugernavn'];
	}
	$y=$x+1;
#	if ($y > $bruger_max) {
#		$headers = 'From: saldi@saldi.dk'."\r\n".'Reply-To: saldi@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
#		mail("saldi@saldi.dk", "Brugerantal ($x) overskredet for $regnskab / $db", "$brugernavn logget ind som bruger nr $y.", "$headers");
#		print "<BODY onLoad=\"javascript:alert('Max antal samtidige brugere ($x) er overskredet.')\">";
#	}
	$q = db_select("select * from online where brugernavn = '$brugernavn' and db = '$db' and session_id != '$s_id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)){
		$last_time=$r['logtime'];
		if (!$fortsaet && $unixtime - $last_time < 3600) {
			online($regnskab, $brugernavn, $password, $timestamp, $s_id);
			exit;
		} elseif (!$fortsaet) {
			$tmp=date("d-m-y", $last_time)." kl. ".date("H:i", $last_time);
			print "<BODY onLoad=\"javascript:alert('Velkommen $brugernavn. Du har ikke logget korrekt af da du sidst var online d. $tmp')\">";
			db_modify("delete from online where brugernavn = '$brugernavn' and db = '$db' and session_id != '$s_id'",__FILE__ . " linje " . __LINE__);
		}
	}
}
db_modify("delete from online where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
if ($db && !file_exists("../temp/.ht_$db.log")) {
	$fp=fopen("../temp/.ht_$db.log","a");
	fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor."\n");
	fwrite($fp,"\\connect $db;\n");
	fclose ($fp);
}

if ((isset($regnskabsaar))&&($db)){
	db_modify("insert into online (session_id, brugernavn, db, dbuser, regnskabsaar, logtime) values ('$s_id', '$brugernavn', '$db', '$dbuser', '$regnskabsaar', '$unixtime')",__FILE__ . " linje " . __LINE__);
}
elseif($db) {
	db_modify("insert into online (session_id, brugernavn, db, dbuser, logtime) values ('$s_id', '$brugernavn', '$db', '$dbuser', '$unixtime')",__FILE__ . " linje " . __LINE__);
}
else db_modify("delete from online where db=''",__FILE__ . " linje " . __LINE__);
## Versions kontrol / opdatering af database.
if (($regnskab)&&($regnskab!=$sqdb)) {
	if (!file_exists("../temp/$db")) {
		mkdir("../temp/$db");
	}
#	if (!$dbver) {
		include("../includes/online.php");
		db_modify("update grupper set box3 = 'on' where art='USET'",__FILE__ . " linje " . __LINE__); #fjernes når topmenu fungerer.
		$query = db_select("select box1 from grupper where art = 'VE'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			if (!$dbver || $dbver>$row['box1']) $dbver=$row['box1'];
			include("../includes/connect.php");
			 db_modify("update regnskab set version = '$dbver' where id='$db_id'",__FILE__ . " linje " . __LINE__);
#		}	else {
#			$dbver=0;
#			db_modify("insert into grupper (beskrivelse, art, box1) values ('Version', 'VE', '0')",__FILE__ . " linje " . __LINE__);
#			include("../includes/connect.php");
#		}
	}
	if ($dbver<$version) tjek4opdat($dbver,$version);
}

// Timestamp systemet bør anses for deprecated og fremadrettet fjernes fra koden.
// Systemet blev ikke brugt konsekvent ved alle login-punkter i koden,
// og systemet beskyttede reelt ikke adgangskoderne fra af blive opsnappen ved
// et MITM-angreb eller en manipulation af koden på klientsiden.
//
// Sikkerhedsforanstaltninger bør i stedet udgøres ved:
// 1. Koder opbevares kun salt og hash, se implementeringen af PBKDF2
// 2. Koder overføres mellem klient og server i klartekst og der bør derfor benyttes HTTPS
//
// For nuværende udkommenteres systemet i fornøden grad.
//
$header="nix"; $bg="nix";
include("../includes/online.php");
$bruger_id=NULL;
/* if (isset ($brug_timestamp)) { // pbkdf2 er ikke implementeret for brug_timestamp pt. og bliver det nok heller ikke --nrb
	$row=db_fetch_array(db_select("select * from brugere where brugernavn='$brugernavn' and (upper(md5('$timestamp' || upper(kode)))=upper('$password'))",__FILE__ . " linje " . __LINE__));
	$bruger_id=$row['id'];
} else { */
	// $tmp=md5($password);
	$row = db_fetch_array(db_select("select * from brugere where brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__));

	// check om password stadig er gemt som md5-hash.
	if((strlen($row['kode']) == 32 && ctype_xdigit($row['kode'])) && ($row['kode'] == md5($password))){
		// Viderestil til skift_kode.php
		header("Location: skift_kode.php");
		exit;
	}
	// slut

	// Hvis hash af salt og indtastet kode er lig gemt hash i databasen, har brugeren indtastet korrekt password og skal logges ind.
	if(\PBKDF2\validate_password($password, $row['kode'])) $saldibruger_valideret = true;
	$bruger_id=$row['id'];
	if (!$bruger_id) {
		$row=db_fetch_array(db_select("select * from brugere where brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__));
		if ($row['tmp_kode']) {
			list($tidspkt,$tmp_kode)=explode("|",$row['tmp_kode']);
			if (date("U")<=$tidspkt) {
				if ($tmp_kode==$password) {
					$bruger_id=$row['id'];
				} 
			} elseif ($tmp_kode==$password) {
				header("Location: index.php?e=".base64_encode("Midlertidig adgangskode udløbet")); //print "<BODY onLoad=\"javascript:alert('Midlertidig adgangskode udløbet')\">";
        			exit;
			}
		}
	}
// }
#cho "BID $bruger_id";
//if ($bruger_id) {
if($saldibruger_valideret){
	$db_skriv_id=NULL;
	if ($db_type=='mysql') {
		if (!mysql_select_db("$sqdb")) die( "Unable to connect to MySQL");
	} else {
		$connection = db_connect ("'$sqhost'", "'$dbuser'", "'$sqpass'", "'$sqdb'", __FILE__ . " linje " . __LINE__);
		if (!$connection) die( "Unable to connect to PostgreSQL");
	}
	$rettigheder=trim($row['rettigheder']);
	$regnskabsaar=$row['regnskabsaar'];
	if (($regnskabsaar)&&($db)) {db_modify("update online set rettigheder='$rettigheder', regnskabsaar='$regnskabsaar' where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);}
	else {db_modify("update online set rettigheder='$rettigheder' where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);}
	$connection = db_connect ("'$sqhost'", "'$dbuser'", "'$sqpass'", "'$db'");
	if (!isset($connection)) die( "Unable to connect to SQL");
	if ($login=="cookie") {setcookie("saldi_std",$regnskab,time()+60*60*24*30);}
	if ($post_max && $db!=$sqdb) {
		$r=db_fetch_array(db_select("select box6 from grupper where art = 'RA' and kodenr = '$regnskabsaar'",__FILE__ . " linje " . __LINE__));
		$post_antal=$r['box6']*1;
#		if (($sqdb=="saldi" || $sqdb=="gratis" || $sqdb=="udvikling") && $post_max<=9000 && $post_max < $post_antal ) {
			$diff=$post_antal-$post_max;
			if ($sqdb=="gratis" && $post_antal>$post_max) {
				$txt="Dit maksikale posteringsantal ($post_max) er overskredet.\\nDer er i alt foretaget $post_antal posteringer inden for de sidste 12 m&aring;neder.\\nDu kan bestille et professionelt regnskab p&aring; http://saldi.dk med hotline og automatisk \\nsikkerhedskopiering p&aring; hurtigere systemer, og let flytte hele dit regnskab dertil.\\nEller du kan kontakte DANOSOFT p&aring; tlf 4690 2208 og h&oslash;re om mulighederne for ekstra gratis posteringer.\\n";
				echo "<script>alert('$txt')</script>";
			}
#		}
	}
} else $afbryd=1;
ob_end_flush();	//Sender det "bufferede" output afsted...

if(!isset($afbryd)){
	$db_skriv_id=NULL;
	$fp=fopen("../temp/online.log","a");
	fwrite($fp,date("Y-m-d")." ".date("H:i:s")." ".getenv("remote_addr")." ".$s_id." ".$brugernavn."\n");
	fclose($fp);
	if ($regnskab==$sqdb) {
		if ($dbver<$version) tjek4opdat($dbver,$version); #20130919
		header("Location: admin_menu.php");
		// print "<meta http-equiv=\"refresh\" content=\"0;URL=admin_menu.php\">";
		exit;
	} else {
		if ($fortsaet) {
			include("../includes/connect.php");
			db_modify("delete from online where brugernavn = '$brugernavn' and db = '$db' and session_id != '$s_id'",__FILE__ . " linje " . __LINE__);
			include("../includes/online.php");
		}
		if (substr($rettigheder,5,1)=='1') include("../debitor/rykkertjek.php");
#		transtjek();
		}
		header("Location: menu.php");
		//print "<meta http-equiv=\"refresh\" content=\"0;URL=menu.php\">";
} else {
	include("../includes/connect.php");
	db_modify("delete from online where session_id='$s_id'",__FILE__ . " linje " . __LINE__);
	//print "<BODY onLoad=\"javascript:alert('Fejl i brugernavn eller adgangskode')\">";
	header("Location: index.php?e=".base64_encode("Fejl i brugernavn eller adgangskode"));
	exit;
	// login (htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset));
#	print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";
	exit;
}

function online($regnskab, $brugernavn, $password, $timestamp, $s_id) {
	global $charset;

	print "<FORM METHOD=POST NAME=\"online\" ACTION=\"login.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";
	print "<table width=50% align=center border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td colspan=\"2\" align=\"center\" valign=\"center\"> <big><b>Brugeren <i>$brugernavn</i> er allerede logget ind.</b></big></td></tr>";
	print "<tr><td colspan=\"2\" align=\"center\"> <big><b>Vil du forts&aelig;tte?</b></big></td></tr>";

	print "<tr>";
	print "<INPUT TYPE=hidden NAME=regnskab VALUE='$regnskab'>";
	print "<INPUT TYPE=hidden NAME=login VALUE='$brugernavn'>";
	print "<INPUT TYPE=hidden NAME=password VALUE='$password'>";
	print "<INPUT TYPE=hidden NAME=timestamp VALUE='$timestamp'>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td align=center><INPUT TYPE=submit name=afbryd VALUE=Afbryd></td>";
	print "<td align=center><INPUT TYPE=submit name=fortsaet VALUE=Forts&aelig;t></td>";
	print "</tr>";
	print "</FORM>";
}
?>
