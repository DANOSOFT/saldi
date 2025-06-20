<?php
ob_start(); //Starter output buffering
// --------------index/login.php----------lap 2.0.7----- 2009-05-20------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();
$css="../css/standard.css";

$fortsaet=NULL;

include("../includes/connect.php");
include("../includes/db_query.php");

$unixtime=date("U");

if ((isset($_POST))||($_GET['login']=='test')) {
	if (isset($_POST)){
		$regnskab = addslashes(trim($_POST['regnskab']));
		$brugernavn = addslashes(trim($_POST['login']));
		$password = addslashes(trim($_POST['password'])); // password i formatet uppercase( md5( timestamp + uppercase( md5(original_password) ) ) )
		$timestamp = trim($_POST['timestamp']);
		if (isset($_POST['fortsaet'])) $fortsaet = $_POST['fortsaet'];
		if (isset($_POST['afbryd'])) $afbryd = $_POST['afbryd'];
	}
	else{
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
		if (!$db) {
			$db=$sqdb;
			db_modify("update regnskab set db='$sqdb' where id='$db_id'",__FILE__ . " linje " . __LINE__);
		}
#		if (isset($fortsaet)) {
#			 db_modify("delete from online where db='$db' and brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__);
#		}
		if (isset($afbryd)) {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";		}
		$tmp=date("U");
		if ($masterversion > "1.1.3") db_modify("update regnskab set sidst='$tmp' where id = '$db_id'",__FILE__ . " linje " . __LINE__);
	}
	else	{
		print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab findes ikke')\">";
print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";		exit;
	}
}
if ((!(($regnskab=='test')&&($brugernavn=='test')&&($password=='test')))&&(!(($regnskab=='demo')&&($brugernavn=='admin')))) {
	$query = db_select("select * from online where brugernavn = '$brugernavn' and db = '$db' and session_id != '$s_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)){
		$last_time=$row['logtime'];
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
	if ($masterversion > "1.1.3") db_modify("insert into online (session_id, brugernavn, db, dbuser, regnskabsaar, logtime) values ('$s_id', '$brugernavn', '$db', '$dbuser', '$regnskabsaar', '$unixtime')",__FILE__ . " linje " . __LINE__);
	else db_modify("insert into online (session_id, brugernavn, db, dbuser, regnskabsaar) values ('$s_id', '$brugernavn', '$db', '$dbuser', '$regnskabsaar')",__FILE__ . " linje " . __LINE__);
}
elseif($db) {
	if ($masterversion > "1.1.3") db_modify("insert into online (session_id, brugernavn, db, dbuser, logtime) values ('$s_id', '$brugernavn', '$db', '$dbuser', '$unixtime')",__FILE__ . " linje " . __LINE__);
	else db_modify("insert into online (session_id, brugernavn, db, dbuser) values ('$s_id', '$brugernavn', '$db', '$dbuser')",__FILE__ . " linje " . __LINE__);
}
else db_modify("delete from online where db=''",__FILE__ . " linje " . __LINE__);
## Versions kontrol / opdatering af database.
if (($regnskab)&&($regnskab!=$sqdb)) {
	if (!file_exists("../temp/$db")) {
		mkdir("../temp/$db");
	}
#	if (!$dbver) {
		include("../includes/online.php");
		$query = db_select("select box1 from grupper where art = 'VE'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$dbver=$row['box1'];
			include("../includes/connect.php");
			 db_modify("update regnskab set version = '$dbver' where id='$db_id'",__FILE__ . " linje " . __LINE__);
#		}	else {
#			$dbver=0;
#			db_modify("insert into grupper (beskrivelse, art, box1) values ('Version', 'VE', '0')",__FILE__ . " linje " . __LINE__);
#			include("../includes/connect.php");
#		}
	}
	if ($dbver<$version) {
#		include ("../includes/opdater.php");
		$tmp = str_replace(".",";",$dbver);		
		list($a, $b, $c)=split(";", trim($tmp)); 
		if ($a==0) {
			include("../includes/opdat_0.php");
			opdat_0('1.0', $dbver);
			$a=1;$b=0;
		}
		if ($a==1) {
			if ($b==0) {
				include("../includes/opdat_1.0.php");
				opdat_1_0($b, $c);
				$b=1;
			}
			if ($b==1) {
				include("../includes/opdat_1.1.php");
				opdat_1_1($b, $c);
				$b=9; $c=0;
			}
			if ($b==9) {
				include("../includes/opdat_1.9.php");
				opdat_1_9($b, $c);
				$a=2;$b=0;$c=0;
			}
		} if ($a==2) {
				include("../includes/opdat_2.0.php");
				opdat_2_0($b, $c);
		}
#		if ($a==0) {opdat_0('1.0', $dbver);}
#		if ($a==1) {opdat_1($b, $c);}
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=login.php\">";
#		exit;
	}
}
include("../includes/online.php");
if (isset ($brug_timestamp)) $query = db_select("select * from brugere where brugernavn='$brugernavn' and (upper(md5('$timestamp' || upper(kode)))=upper('$password'))",__FILE__ . " linje " . __LINE__);
else {
	$password=md5($password);
	$query = db_select("select * from brugere where brugernavn='$brugernavn' and kode= '$password'",__FILE__ . " linje " . __LINE__);
}
if ($row = db_fetch_array($query)) {
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
	if ($post_max) {
		$r=db_fetch_array(db_select("select box6 from grupper where art = 'RA' and kodenr = '$regnskabsaar'",__FILE__ . " linje " . __LINE__));
		$post_antal=$r['box6']*1;
		if ($post_max < $post_antal) {
			$diff=$post_antal-$post_max;
			print "<BODY onLoad=\"javascript:alert('Det maksimale antal posteringer ($post_max) for det aktive regnskabs&aring;r er overskredet med $diff posteringer - kontakt venligst DANOSOFT på telefon 4690 2208, og h&oslash;r om dine muligheder for flere posteringer.')\">";
		}
	}
} else $afbryd=1;
ob_end_flush();	//Sender det "bufferede" output afsted...

if(!isset($afbryd)){
	$db_skriv_id=NULL;
	$fp=fopen("../temp/online.log","a");
	fwrite($fp,date("Y-m-d")." ".date("H:i:s")." ".getenv("remote_addr")." ".$s_id." ".$brugernavn."\n");
	fclose($fp);
	if ($regnskab==$sqdb) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=admin_menu.php\">";
		exit;
	} else {
		if ($fortsaet) {
			include("../includes/connect.php");
			db_modify("delete from online where brugernavn = '$brugernavn' and db = '$db' and session_id != '$s_id'",__FILE__ . " linje " . __LINE__);
			include("../includes/online.php");
		}
		if (substr($rettigheder,5,1)=='1') include("../debitor/rykkertjek.php");
		print "<meta http-equiv=\"refresh\" content=\"0;URL=menu.php\">";
	}
} else {
	include("../includes/connect.php");
	db_modify("delete from online where session_id='$s_id'",__FILE__ . " linje " . __LINE__);
	print "<BODY onLoad=\"javascript:alert('Fejl i brugernavn eller adgangskode')\">";
print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";	exit;
}
	
function online($regnskab, $brugernavn, $password, $timestamp, $s_id)
{
	global $charset;
	
	print "<FORM METHOD=POST NAME=\"online\" ACTION=\"login.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";
	print "<table width=50% align=center border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td colspan=\"2\" align=\"center\" valign=\"center\"> <big><b>$brugernavn er allerede logget ind</b></big></td></tr>";
	print "<tr><td colspan=\"2\" align=\"center\"> <big><b>vil du forts&aelig;tte ?</b></big></td></tr>";

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
