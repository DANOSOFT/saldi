<?php
// ---------------------includes/online.php----lap 3.4.9---2015-01-04---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2012.09.05 $ansat_navn bliver nu sat her. Søg 20120905
// 2013.01.20 $sag_rettigheder bliver nu sat her. Søg 20130120
// 2014.01.12 ændres headertekst til så google ikke tror at siderne er på norsk 
// 2014.01.17 Remmet 2 linjer og fjernet id  + $g_id fre 3. linje (Gav efterfølgende duplicate key value violates unique constraint "grupper_pkey") . Søg 20140117
// 2014.05.02	Indsat javascript i header til ordrelinje udfoldning - PHR Danosoft.Søg 20140502  
// 2015.01.04 Indsat kontrol for om database er blevet opdateret. Søg 20150104
// 2015.01.04 Ændret alert til tekstboks. Søg tekstboks
 
ini_set("display_errors", "0");
$db_skriv_id=NULL; #bruges til at forhindre at skrivninger til masterbasen logges i de enkelte regnskaber.
if (!isset($modulnr))$modulnr=NULL;
if (!isset($db_type))$db_type="postgres";
$ip=$_SERVER['REMOTE_ADDR'];
$ip=substr($ip,0,10);
$sag_rettigheder=NULL;
if ($title!="kreditorexport" && $ip!="128.30.52."){
	$query = db_select("select * from online where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$dbuser = trim($row['dbuser']);
		$db	= trim($row['db']);
		$regnaar = trim($row['regnskabsaar']);
		$brugernavn = db_escape_string($row['brugernavn']);
		$unixtime=date("U");
		$rettigheder=$row['rettigheder'];
		$revisor=$row['revisor'];
		if ($row['logtime']) db_modify("update online set logtime = '$unixtime' where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
	} elseif ($title!='login' && $title!='opdat' && $title!='logud' && $title!='Aaben regnskab') {
		if ($webservice) return ('Session expired');
		else {
			if (!isset($nextver)) $nextver=NULL;
			if (!$nextver) {# 20150125
				include("../includes/std_func.php");
				$txt='&nbsp;Din session er udl&oslash;bet - du skal logge ind igen';
				print tekstboks($txt);
				print "<meta http-equiv=\"refresh\" content=\"4;URL=../index/logud.php\">";
				exit;
			}
		}
	}
}

$labelprint=0;
if($sqdb=='udvikling') $labelprint=1;
elseif($sqdb=='severinus') $labelprint=1;
elseif($db=='bizsys_22') $labelprint=1;
elseif($db=='bizsys_25') $labelprint=1;

if($db=='severinus_22') $kundedisplay=1;

if ($modulnr && $modulnr<100 && $db==$sqdb) { #Lukker vinduet hvis revisorbruger er logget af
	include("../includes/std_func.php");
	$txt='Du har logget ud - vinduet lukkes';
	print tekstboks($txt);
	print "<meta http-equiv=\"refresh\" content=\"4;URL=../includes/luk.php\">";
	exit;
}
#echo "select * from regnskab where db = '$db'<br>";
if ($row = db_fetch_array(db_select("select * from regnskab where db = '$db'",__FILE__ . " linje " . __LINE__))){
	$db_id = $row['id'];
	$db_skriv_id = $db_id;
	$db_ver = $row['version'];  # 20150104
	$regnskab = $row['regnskab'];
	$max_posteringer = $row['posteringer'];
}
if ($db && $sqdb && $db!=$sqdb) {
	if (!isset($nextver)) { # 20150104
			if ($version>$db_ver) { 
			if ($db_type=='mysql') {
				if (!mysql_select_db("$db")) die( "Unable to connect to MySQL");
			} else {
				$connection = db_connect ("$sqhost", "$squser", "$sqpass", "$db", __FILE__ . " linje " . __LINE__);
				if (!$connection) die( "Unable to connect to PostgreSQL");
			}
			$r = db_fetch_array(db_select("select box1 from grupper where art='VE'",__FILE__ . " linje " . __LINE__));
			include("../includes/connect.php");
			if ($r['box1']>$db_ver) {
				$db_ver=$r['box1'];
				db_modify("update regnskab set version = '$db_ver' where id = '$db_id'", __FILE__ . " linje " . __LINE__);
			}
			if ($version>$db_ver && $title!='Aaben regnskab') {
				include("../includes/std_func.php");
				$txt="Saldi er blevet opdateret, log af og p&aring; igen";
				print tekstboks($txt);
			}
		}
	}
	if ($db_type=='mysql') {
		if (!mysql_select_db("$db")) die( "Unable to connect to MySQL");
	} else {
	$connection = db_connect ("$sqhost", "$squser", "$sqpass", "$db", __FILE__ . " linje " . __LINE__);
		if (!$connection) die( "Unable to connect to PostgreSQL");
	}	
	if (!$revisor) {
		$r=db_fetch_array(db_select("select * from brugere where brugernavn= '$brugernavn'",__FILE__ . " linje " . __LINE__));
		$bruger_id=$r['id'];
		$rettigheder = trim($r['rettigheder']);
		$ansat_id=$r['ansat_id']*1;
		$sprog_id=$r['sprog_id']*1;
		if ($ansat_id) { #20120905 
		$r = db_fetch_array(db_select("select * from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__));
			$ansat_navn=$r['navn'];
			if (isset($r['gruppe'])) {
				$ansat_gruppe=$r['gruppe']*1; #20120905
				$r = db_fetch_array(db_select("select * from grupper where id = '$ansat_gruppe'",__FILE__ . " linje " . __LINE__));
				$sag_rettigheder=$r['box2'];
			}
		} else {
			$ansat_navn=$brugernavn;
			$sag_rettigheder=NULL;	
		}
	}	else $bruger_id=-1;
	if (!$sprog_id)$sprog_id=1;
	$jsvars="statusbar=0,menubar=0,titlebar=0,toolbar=0,scrollbars=1,resizable=1,dependent=1";

	if (!$r = db_fetch_array(db_select("select box1,box2,box3,box4,box5 from grupper where art = 'USET' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__))) {
#		$r = db_fetch_array(db_select("select max(id) as id from grupper",__FILE__ . " linje " . __LINE__)); 20140117 
#		$g_id=$r['id']+1;
		db_modify("insert into grupper(beskrivelse,art,kodenr,box1,box2,box3,box4,box5) values ('Usersettings','USET','$bruger_id','$jsvars','','on','#eeeef0','')",__FILE__ . " linje " . __LINE__);
	} else {
		$jsvars=$r['box1'];
		$popup=$r['box2'];
		$menu=$r['box3'];
		$bgcolor=$r['box4'];
		$bgnuance1=$r['box5'];
		if (strpos($jsvars,"reziseable")) { #tilfoejet 20090730 grundet stavefejl i reziseable
			$jsvars=str_replace("reziseable","resizable",$jsvars);
			db_modify("update grupper set box1='$jsvars' where  art = 'USET' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__);
		}
	}
	$textcolor="#000077";
	$textcolor2="#009900";
	$textcolor3="#6666aa"; # Svagere tekst til det som er mindre vigtigt
	if (!isset($bgcolor)) $bgcolor="#eeeef0"; #alm baggrund
	if (!isset($bgcolor2)) $bgcolor2="#BEBCCE"; #top & bundlinjer
	if (!isset($bgcolor3)) $bgcolor3="#cccccc";
	if (!isset($bgcolor4)) $bgcolor4="#d0d0f0";
	if (!isset($bgcolor5)) {
		for ($x=1;$x<=5;$x=$x+2) {
			$a=hexdec(substr($bgcolor,$x,2));
			if ($a<="224")$a+=32;
			else $a-=32;
			$bgcolor5.=dechex($a);
		}
		# $bgcolor5="#e0e0f0";
	}
if (!isset($bgnuance1)) $bgnuance1="+01+01-55"; # Aendring af nuancen til gult ved skiftende linjer
	
	if ($menu=='T') {
		$header='nix';
		$bg='nix';
		$css=NULL;
	}
#	echo "$rettigheder -> $modulnr -> ".substr($rettigheder,$modulnr,1)."<br>";
	if (($rettigheder)&&($modulnr)&&(substr($rettigheder,$modulnr,1)!='1')) {
		include("../includes/std_func.php");
		$txt="Du har ikke nogen rettigheder her - din aktivitet er blevet logget";
		print tekstboks($txt);
		exit;
	}
}

if ($header!='nix') {
	if ($db_encode=="UTF8") $charset="UTF-8";
	else $charset="ISO-8859-1";
	PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
	<html>\n
	<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset;\">\n
	<meta http-equiv=\"content-language\" content=\"da\">\n
	<meta name=\"google\" content=\"notranslate\">\n";
	if($meta_returside) print "$meta_returside"; #20140502
	if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">\n";
	else print "<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/saldimenu.css\"/>\n";
	print "<script type=\"text/javascript\" src=\"../javascript/jquery-1.8.0.min.js\"></script>\n"; #20140502
	print "<script type=\"text/javascript\" src=\"../javascript/jquery.autosize.js\"></script>\n"; #20140502
	print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/overlib.js\"></script>\n"; 
	print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>\n"; #20140502
	#print "<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/main.css\"/>\n";
	print "
	<script type=\"text/javascript\">
	
	var linje_id=0;
	var vare_id=0;
	var antal=0;
	function serienummer(linje_id,antal){
		window.open(\"serienummer.php?linje_id=\"+ linje_id,\"\",\"left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no\")
	}
	function batch(linje_id,antal){
		window.open(\"batch.php?linje_id=\"+ linje_id,\"\",\"left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no\")
	}
	function stykliste(vare_id){
		window.open(\"../lager/fuld_stykliste.php?id=\"+ vare_id,\"\",\"left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no\")
	}
	
	</script>";
	#20140502 -->
	?> 
	
		<script type="text/javascript">
// jQuery funktion til autosize på textarea 
	$(document).ready(function(){
		$('.autosize').autosize();
	});
		
	// jQuery funktion til ordrelinjer i ordre.php. Ved tryk på enter submitter formen og ved shift+enter laver den ny linje i textarea
	$(function() {
		$('textarea.comment').keyup(function(e) {
				if (e.which == 13 && ! e.shiftKey) {
						$("#submit").click();
				}
		});
	});
	</script>
	<?php
	# <-- 20140502
	PRINT "</head>\n";
}
if ($bg!='nix') {
	if (!$bgcolor) $bgcolor="#eeeef0";
	PRINT "<body bgcolor=\"$bgcolor\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\">\n";
}
?>
