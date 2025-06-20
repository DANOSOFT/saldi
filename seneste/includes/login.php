<?php
ob_start(); //Starter output buffering
// --------------index/login.php----------lap 3.2.9------2012-06-15------
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
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();
$css="../css/standard.css";

$fortsaet=NULL;

include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/tjek4opdat.php");

if ($db_encode=="UTF8") $charset="UTF-8";
else $charset="ISO-8859-1";
PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
<html>\n
<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\">\n";
if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\" />";
print "</head>";


$unixtime=date("U");

if ((isset($_POST['regnskab']))||($_GET['login']=='test')) {
	if (isset($_POST)){
		$regnskab = addslashes(trim($_POST['regnskab']));
		$brugernavn = addslashes(trim($_POST['login']));
		$password = addslashes(trim($_POST['password'])); // password i formatet uppercase( md5( timestamp + uppercase( md5(original_password) ) ) )
		$timestamp = trim($_POST['timestamp']);
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
			if (!$mastername) $mastername='DANOSOFT'
			print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab er lukket\\nKontakt $mastername for gen&aring;bning')\">";
			login (htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset));
#			print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";
			exit;
		}
#		if (isset($fortsaet)) {
#			 db_modify("delete from online where db='$db' and brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__);
#		}
		if (isset($afbryd)) {
			login (htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset));
#			print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";
		}
		$tmp=date("U");
		if ($masterversion > "1.1.3") db_modify("update regnskab set sidst='$tmp' where id = '$db_id'",__FILE__ . " linje " . __LINE__);
	}	else {
		if ($regnskab) print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab findes ikke')\">";
		login (htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset));
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";		exit;
	}
} else {
	login (htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset));
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
	if ($y > $bruger_max) {
		$headers = 'From: saldi@saldi.dk'."\r\n".'Reply-To: saldi@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
		mail("saldi@saldi.dk", "Brugerantal ($x) overskredet for $regnskab / $db", "$brugernavn logget ind som bruger nr $y.", "$headers");
#		print "<BODY onLoad=\"javascript:alert('Max antal samtidige brugere ($x) er overskredet.')\">";
	}
	$q = db_select("select * from online where brugernavn = '$brugernavn' and db = '$db' and session_id != '$s_id'",__FILE__ . " linje " . __LINE__);
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
	if ($post_max && $db!=$sqdb) {
		$r=db_fetch_array(db_select("select box6 from grupper where art = 'RA' and kodenr = '$regnskabsaar'",__FILE__ . " linje " . __LINE__));
		$post_antal=$r['box6']*1;
#		if (($sqdb=="saldi" || $sqdb=="gratis" || $sqdb=="udvikling") && $post_max<=9000 && $post_max < $post_antal ) {
			$diff=$post_antal-$post_max;
			if ($sqdb=="gratis" && $post_antal>$post_max) {
				$txt="Dit maksikale posteringsantal ($post_max) er overskredet.\\nDer er i alt foretaget $post_antal posteringer inden for de sidste 12 m&aring;neder.\\nDu kan bestille et professionelt regnskab p&aring; http://saldi.dk med hotline og automatisk \\nsikkerhedskopiering p&aring; hurtigere systemer, og let flytte hele dit regnskab dertil.\\nEller du kan kontakte DANOSOFT p&aring; tlf 4690 2208 og h&oslash;re om mulighederne for ekstra gratis posteringer.\\n";
				print "<BODY onLoad=\"javascript:alert('$txt')\">";
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
		print "<meta http-equiv=\"refresh\" content=\"0;URL=admin_menu.php\">";
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
		print "<meta http-equiv=\"refresh\" content=\"0;URL=menu.php\">";
} else {
	include("../includes/connect.php");
	db_modify("delete from online where session_id='$s_id'",__FILE__ . " linje " . __LINE__);
	print "<BODY onLoad=\"javascript:alert('Fejl i brugernavn eller adgangskode')\">";
	login (htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset));
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

function login ($regnskab,$brugernavn) {
	global $charset;
	global $version;

	include("../includes/std_func.php");

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
#	if ($db_encode=="UTF8") $charset="UTF-8";
#	else $charset="ISO-8859-1";
	if (file_exists("../doc/vejledning.pdf")) $vejledning="../doc/vejledning.pdf";
	else $vejledning="http://saldi.dk/dok/komigang.html";

	PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
	<html>\n
	<head><title>$title</title>";
	if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">";
	print "<meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\"></head>\n";
	print "<body><table style=\"width:100%;height:100%;\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";# Tabel 1 ->
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #Tabel 1.1 ->
	print "<tr><td  style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" width=\"45%\"> Ver $version</td>";
	print "<td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;;background:url(../img/grey1.gif)\" width=\"10%\" align = \"center\"> <a href=\"$vejledning\" target=\"_blank\">Vejledning</a></td>\n";
	print "<td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;;background:url(../img/grey1.gif)\" width=\"45%\" align = \"right\">&nbsp;</td></tr>\n";
	print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"middle\">\n"; # <- tabel 1.1 slut
	print "<table width=\"350\" align=\"center\" border=\"5\" cellspacing=\"5\" cellpadding=\"5\"><tbody>"; # tabel 1.2 ->
	print "<tr><td><FORM name=\"login\" METHOD=\"POST\" ACTION=\"login.php\" onSubmit=\"return handleLogin(this);\"><table width=\"100%\" align=center border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tbody>"; # tabel 1.2.1 ->
	if (isset($mastername)&&$mastername) $tmp="<big><big><big><b>$mastername</b></big></big></big>";   
	elseif (strpos($_SERVER['PHP_SELF'],"beta")) $tmp="<big><big><big><b>!!! BETA !!!</b></big></big></big>";
	else $tmp="<big><big><big><b>SALDI</b></big></big></big>";
	print "<tr><td colspan=\"2\">";
	print "<table width=\"100%\"><tbody><tr><td width=\"10%\">"; # tabel 1.2.1.1 ->
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
	print "</tbody></table><INPUT TYPE=\"HIDDEN\" name=\"timestamp\" value=\"".date("U")."\"></FORM></td></tr>\n"; # <- tabel 1.2.1
	print	"</tbody></table></td></tr>\n"; # <- tabel 1.2
	print "<tr><td align=\"center\" valign=\"bottom\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.3 ->
	print "<td width=\"20%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"left\">&nbsp;Copyright&nbsp;&copy;&nbsp;2003-2012&nbsp;DANOSOFT&nbsp;ApS</td>";
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
}

?>
