<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/online.php --- patch 4.1.0 --- 2024-04-23---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20120905 $ansat_navn bliver nu sat her. Søg 20120905
// 20130120 $sag_rettigheder bliver nu sat her. Søg 20130120
// 20140112 ændres headertekst til så google ikke tror at siderne er på norsk 
// 20140117 Remmet 2 linjer og fjernet id  + $g_id fre 3. linje (Gav efterfølgende duplicate key value violates unique constraint "grupper_pkey") . Søg 20140117
// 20140502	Indsat javascript i header til ordrelinje udfoldning - PHR Danosoft.Søg 20140502  
// 20150104 Indsat kontrol for om database er blevet opdateret. Søg 20150104
// 20150104 Ændret alert til tekstboks. Søg tekstboks
// 20170213 Initialiserer $meta_returside.
// 20171009 Table / body styles flyttet fra css/pos.css så font size kan sættes som variabel. søg 21071009
// 20190110 timezone og andre systemvariabler hentes nu fra tabellen settings. 
// 20190412 customAlertText hentes nu fra tabellen settings. 
// 20190412 PHR - "1)!='1')" changed to "1)<'1')" to support read only function. 20190529
// 20190605 PHR Check for version from 'regnskab' before trying to fetch from 'settings' 20190605.
// 20190704 RG (Rune Grysbæk) Mysqli implementation 
// 20190821 PHR Check if account is closed ($lukket). 
// 20200225 PHR Some corrections regarding MySQLi;
// 20200928 PHR Added '$db_id &&' to avoid error if no db_id when calling labelprint.php from mylabel.php 20200928 
// 20200930 PHR Added  'order by logtime desc limit 1' to 'select * from online'
// 20201218 LOE Added this to replace the previous function as it displays autosize not a function error 20201218
// 20210916 LOE Added lingua as language set for admins* 
// 20211001 PHR	Simplyfying language
// 20211018 LOE fixed some bugs..checking if the select and fetch query is true.
// 20211220 PHR removed if ($r['box5']) from '20210928'. Why was it set?? 
// 20220105 PHR Sets $sprog_id=$languageID
// 20220212 PHR	MySale users removed from 'grupper' 
// 20230725 LOE Initilized $webservice and some modifications.
// 20230801 LOE Minor modifications; javascript and css path 
// 20231107 PK Added css- and javascript-link for flatpickr
// 20240422 PHR Added 'S' in box3 when insertion uset in grupper
// 26042024 PBLM changed the path of the javascript and css files to be relative to the file location for flatpickr
// 23-05-2024 PBLM Setup notification from easyUBL

#include("../includes/connect.php"); #20211001
if (isset($_COOKIE['timezone'])) { #20190110
	$timezone = $_COOKIE['timezone'];
	date_default_timezone_set($timezone);
} else {
	date_default_timezone_set('Europe/Copenhagen');
	#$r=db_fetch_array(db_select("select lukket,version from regnskab where id='1'",__FILE__ . " linje " . __LINE__)); # 20190605
	$r = db_fetch_array(db_select("select lukket,version from regnskab where id='1' and db = '$sqdb'", __FILE__ . " linje " . __LINE__)); # 20210930
	if (isset($dbver) && $dbver >= '3.7.2') {
		$r = db_fetch_array(db_select("select id, var_value from settings where var_name='timezone'", __FILE__ . " linje " . __LINE__));
		if ($r['var_value']) {
			$timezone = $r['var_value'];
		} else {
			$timezone = 'Europe/Copenhagen';
			if ($r['id']) $qtxt = "update settings set var_value='$timezone' where id='$r[id]'";
			else {
				$qtxt = "insert into settings (var_name,var_value,var_description)";
				$qtxt .= " values ";
				$qtxt .= "('timezone','$timezone','Generel tidszone. Anvendes hvis der ikke er sat tidszone i det enkelte regnskab')";
			}
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		$qtxt = "select var_value from settings where var_name='alertText'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$r ? $customAlertText = $r['var_value'] : $customAlertText = NULL; #20211018
		$r = db_fetch_array(db_select("select var_value from settings where var_name='ps2pdf'", __FILE__ . " linje " . __LINE__));
		$r ? $ps2pdf = $r['var_value'] : $ps2pdf = NULL;
		$r = db_fetch_array(db_select("select var_value from settings where var_name='pdftk'", __FILE__ . " linje " . __LINE__));
		$r ? $pdftk = $r['var_value'] : $pdftk = NULL;
		$r = db_fetch_array(db_select("select var_value from settings where var_name='ftp'", __FILE__ . " linje " . __LINE__));
		$r ? $ftp = $r['var_value'] : $ftp = NULL;
		$r = db_fetch_array(db_select("select var_value from settings where var_name='dbdump'", __FILE__ . " linje " . __LINE__));
		$r ? $dbdump = $r['var_value'] : $dbdump = NULL;
		$r = db_fetch_array(db_select("select var_value from settings where var_name='tar'", __FILE__ . " linje " . __LINE__));
		$r ? $tar = $r['var_value'] : $tar = NULL;
		$r = db_fetch_array(db_select("select var_value from settings where var_name='zip'", __FILE__ . " linje " . __LINE__));
		$r ? $zip = $r['var_value'] : $zip = NULL;
		$r = db_fetch_array(db_select("select var_value from settings where var_name='systemLanguage'", __FILE__ . " linje " . __LINE__));
		$r ? $systemLanguage = $r['var_value'] : $systemLanguage = 'Dansk';
	}
}

if (!isset($meta_returside)) $meta_returside = NULL;
$db_skriv_id = NULL; #bruges til at forhindre at skrivninger til masterbasen logges i de enkelte regnskaber.
if (!isset($modulnr))    $modulnr = NULL;
if (!isset($db_type))    $db_type = "postgres";
if (!isset($webservice)) $webservice = NULL;
$ip = $_SERVER['REMOTE_ADDR'];
$ip              = substr($ip, 0, 10);
$sag_rettigheder = NULL;
$unixtime        = date("U");

$qtxt = "select * from online where session_id = '$s_id' order by logtime desc limit 1";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
if ($r = db_fetch_array($q)) {
	$dbuser = trim(if_isset($r['dbuser'], ''));
	$db = trim(if_isset($r['db'], ''));
	$regnaar = trim(if_isset($r['regnskabsaar'], ''));
	$brugernavn = db_escape_string($r['brugernavn']);
	$rettigheder = $r['rettigheder'];
	$superUserPermission = $rettigheder;
	$revisor = $r['revisor'];
	$logtime = $r['logtime'];
	($r['language_id']) ? $languageID = $r['language_id'] : $languageID = 0;
	$sprog_id = $languageID;
	if ($logtime) {
		$qtxt = "update online set logtime = '$unixtime' where session_id = '$s_id'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
} elseif ($title != 'login' && $title != 'opdat' && $title != 'logud' && $title != 'Aaben regnskab') {
	if ($webservice) return ('Session expired');
	else {
		if (!isset($nextver))
			$nextver = NULL;
		if (!$nextver) { # 20150125
			include("../includes/std_func.php");
			$txt = '&nbsp;Din session er udl&oslash;bet - du skal logge ind igen';
			print tekstboks($txt);
			print "<meta http-equiv=\"refresh\" content=\"4;URL=../index/logud.php\">";
			exit;
		}
	}
}
#}

$labelprint = 0;
if ($sqdb == 'udvikling') $labelprint = 1;
$kundedisplay = 0;

if ($modulnr && $modulnr < 100 && $db == $sqdb) { #Lukker vinduet hvis revisorbruger er logget af
	include("../includes/std_func.php");
	$txt = 'Du har logget ud - vinduet lukkes';
	print tekstboks($txt);
	print "<meta http-equiv=\"refresh\" content=\"4;URL=../includes/luk.php\">";
	exit;
}
if ($row = db_fetch_array(db_select("select * from regnskab where db = '$db'", __FILE__ . " linje " . __LINE__))) {
	$db_id = $row['id'];
	$db_skriv_id = $db_id;
	$db_ver = $row['version']; # 20150104
	$regnskab = $row['regnskab'];
	$max_posteringer = $row['posteringer'];
	$lukket = $row['lukket'];

}

if (isset($db_id) && isset($db) && isset($sqdb) && $db != $sqdb) { #20200928
	$menu=NULL;
	if (!isset($nextver)) { # 20150104
		if ($version > $db_ver) {
			if ($db_type == 'mysql') {
				if (!mysql_select_db($db))
					die("Unable to connect to MySQL");
			} elseif ($db_type == 'mysqli') { #RG_mysqli
				$connection = db_connect($sqhost, $squser, $sqpass, $db);
				if (!mysqli_select_db($connection, $db))
					die("Unable to connect to MySQLi");
			} else {
				$connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " linje " . __LINE__);
				if (!$connection)
					die("Unable to connect to PostgreSQL");
			}
			if (tbl_exists('grupper')) {
				$r = db_fetch_array(db_select("select box1 from grupper where art='VE'", __FILE__ . " linje " . __LINE__));
				include("../includes/connect.php");
				if ($r['box1'] > $db_ver) {
					$db_ver = $r['box1'];
					db_modify("update regnskab set version = '$db_ver' where id = '$db_id'", __FILE__ . " linje " . __LINE__);
				}
				if ($version > $db_ver && $title != 'Aaben regnskab') {
					include("../includes/tjek4opdat.php");
					tjek4opdat($db_ver, $version);
				}
			} else {
				echo "stopper her";
				exit;
			}
		}
	}
	if ($db_type == 'mysql') {
		if (!mysql_select_db($db))
			die("Unable to connect to MySQL");
	} elseif ($db_type == 'mysqli') {
		if (!mysqli_select_db($connection, $db))
			die("Unable to connect to MySQL");
	} else {
		$connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " linje " . __LINE__);
		if (!$connection)
			die("Unable to connect to PostgreSQL");
	}

	$qtxt = "select var_value from settings where var_name = 'baseCurrency'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$baseCurrency = $r['var_value'];
	} else $baseCurrency = 'DKK';

	if (!$revisor) {
		if ($lukket) {
			echo "regnskabet er lukket";
			print "<meta http-equiv=\"refresh\" content=\"4;URL=../index/index.php\">";
			exit;
		}
		$r = db_fetch_array(db_select("select * from brugere where brugernavn= '$brugernavn'", __FILE__ . " linje " . __LINE__));
		$bruger_id = $r['id'];
		$rettigheder = trim(if_isset($r['rettigheder'], ''));
		$ansat_id = $r['ansat_id'] * 1;
		#		if ($r['language_id']) $languageID=$sprog_id=$r['language_id'];
		if ($ansat_id) { #20120905 
			$r = db_fetch_array(db_select("select * from ansatte where id = '$ansat_id'", __FILE__ . " linje " . __LINE__));
			$ansat_navn = $r['navn'];
			if (isset($r['gruppe'])) {
				$ansat_gruppe = (int) $r['gruppe']; #20120905
				$r = db_fetch_array(db_select("select * from grupper where id = '$ansat_gruppe'", __FILE__ . " linje " . __LINE__));
				$sag_rettigheder = $r['box2'];
			}
		} else {
			$ansat_navn = $brugernavn;
			$sag_rettigheder = NULL;
		}
	} else
		$bruger_id = -1;
	if (!$sprog_id)
		$sprog_id = 1;
	if (!strpos($css, 'mysale') && $bruger_id) {
		$jsvars = "statusbar=0,menubar=0,titlebar=0,toolbar=0,scrollbars=1,resizable=1,dependent=1";
		$qtxt = "select box1,box2,box3,box4,box5 from grupper where art = 'USET' and kodenr = '$bruger_id'";
		if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			#			$r = db_fetch_array(db_select("select max(id) as id from grupper",__FILE__ . " linje " . __LINE__)); 20140117 
#			$g_id=$r['id']+1;
			db_modify("insert into grupper(beskrivelse,art,kodenr,box1,box2,box3,box4,box5) values ('Usersettings','USET','$bruger_id','$jsvars','','S','#eeeef0','')", __FILE__ . " linje " . __LINE__);
		} else {
			$jsvars = $r['box1'];
			$popup = $r['box2'];
			$menu = $r['box3'];
			$bgcolor = $r['box4'];
			$bgnuance1 = $r['box5']; #20210928
			if (strpos($jsvars, "reziseable")) { #tilfoejet 20090730 grundet stavefejl i reziseable
				$jsvars = str_replace("reziseable", "resizable", $jsvars);
				db_modify("update grupper set box1='$jsvars' where  art = 'USET' and kodenr = '$bruger_id'", __FILE__ . " linje " . __LINE__);
			}
		}
		$qtxt = "select var_value from settings where var_name = 'buttonColor' and var_grp = 'colors' and user_id = '$bruger_id'";
if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$buttonColor = $r['var_value'];
} else {
	$buttonColor = '#114691'; // Default button color
}
$qtxt = "select var_value from settings where var_name = 'buttonTxtColor' and var_grp = 'colors' and user_id = '$bruger_id'";
if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$buttonTxtColor = $r['var_value'];
} else {
	$buttonTxtColor = '#ffffff'; // Default button text color
}
		$textcolor = "#000077";
		$textcolor2 = "#009900";
		$textcolor3 = "#6666aa"; # Svagere tekst til det som er mindre vigtigt
		if (!isset($bgcolor))
			$bgcolor = "#eeeef0"; #alm baggrund
		if (!isset($bgcolor2))
			$bgcolor2 = "#BEBCCE"; #top & bundlinjer
		if (!isset($bgcolor3))
			$bgcolor3 = "#cccccc";
		if (!isset($bgcolor4))
			$bgcolor4 = "#d0d0f0";
		if (!isset($bgcolor5)) {
			$bgcolor5 = NULL;
			for ($x = 1; $x <= 5; $x = $x + 2) {
				$a = hexdec(substr($bgcolor, $x, 2));
				if ($a <= "224")
					$a += 32;
				else
					$a -= 32;
				$bgcolor5 .= dechex($a);
			}
			# $bgcolor5="#e0e0f0";
		}
		if (!isset($bgnuance1))
			$bgnuance1 = "+01+01-55"; # Aendring af nuancen til gult ved skiftende linjer
	}
	if ($menu == 'T') {
		$header = 'nix';
		$bg = 'nix';
		$css = NULL;
	}
	if (($rettigheder) && ($modulnr) && (substr($rettigheder, $modulnr, 1) < '1')) { #20190529
		include("../includes/std_func.php");
		$txt = "Du har ikke nogen rettigheder her - din aktivitet er blevet logget";
		print tekstboks($txt);
		exit;
	}
}

$javaPath = "../javascript";
if (!file_exists($javaPath)) {
	if (file_exists("../../javascript")) $javaPath = "../../javascript";
	elseif (file_exists("../../../javascript")) $javaPath = "../../../javascript";
}
$cssPath = "../css";
if (!file_exists($cssPath)) {
	if (file_exists("../../css")) $cssPath = "../../css";
	elseif (file_exists("../../../css")) $cssPath = "../../../css";
}
////<!-- PRINT "<!DOCTYPE html>\n -->
if ($header != 'nix') {
	if ($db_encode == "UTF8") $charset = "UTF-8";
	else $charset = "ISO-8859-1";
	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
	
	<html>\n
	<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset;\">\n
	<meta http-equiv=\"content-language\" content=\"da\">\n
	<meta name=\"google\" content=\"notranslate\">\n";
	// print '<!DOCTYPE html>';
	// print '<html>';
	// print '<head>';
	// print '<title>$title</title>';
	// print '<meta charset="$charset">';
	// print '<meta http-equiv="content-language" content="da">';
	// print '<meta name="google" content="notranslate">';
	if ($meta_returside)
		print "$meta_returside"; #20140502
	if ($css) print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">\n";
	else      print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$cssPath/saldimenu.css\"/>\n";
	print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$cssPath/saft.css\"/>\n";
	print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$cssPath/prism.css\"/>\n";
	print "<link rel=\"stylesheet\" href=\"$cssPath/flatpickr.min.css\">\n"; #20231107
	if (substr($title, 0, 3) == 'POS') { # 21071009
		($title == 'POS_ordre' && isset($_COOKIE['saldi_pfs'])) ? $pfs = $_COOKIE['saldi_pfs'] : $pfs = 10;
		print "<style> body {font-family: Arial, Helvetica, sans-serif;font-size: " . $pfs . "pt;} </style>";
		print "<style> table {font-family: Arial, Helvetica, sans-serif;font-size: " . $pfs . "pt;} </style>";
	}
	print "<script type=\"text/javascript\" src=\"$javaPath/jquery-1.8.0.min.js\"></script>\n"; #20140502
	print "<script type=\"text/javascript\" src=\"$javaPath/jquery.autosize.js\"></script>\n"; #20140502
	print "<script LANGUAGE=\"JavaScript\" src=\"$javaPath/overlib.js\"></script>\n";
	print "<script language=\"javascript\" type=\"text/javascript\" src=\"$javaPath/confirmclose.js\"></script>\n"; #20140502
	print "<script src=\"$javaPath/flatpickr.js\"></script>\n"; #20231107
	/* print "<script src=\"https://npmcdn.com/flatpickr/dist/flatpickr.min.js\"></script>\n"; #20231107
	print "<script src=\"https://npmcdn.com/flatpickr/dist/l10n/da.js\"></script>\n"; #20231107 */
	#	print "<script src=\"../javascript/sweetalert.min.js\"></script>";
#	print "<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/sweetalert.css\">";

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
			$('.autosize').autosize()
		});
		// jQuery funktion til ordrelinjer i ordre.php. Ved tryk på enter submitter formen og ved shift+enter laver den ny linje i textarea
		$(function() {
			$('textarea.comment').keyup(function(e) {
				if (e.which == 13 && ! e.shiftKey) {
					$("#submit").click()
				}
			})
		})

	</script>
	<?php
	# <-- 20140502
	print "</head>\n";
}
/*
// get backtrace file to ignore popup notification on pos
	$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='notifications'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$qtxt = "CREATE TABLE notifications (id SERIAL PRIMARY KEY, msg varchar(255), read_status int)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$backtrace = debug_backtrace();
	$lastCall = end($backtrace);

	if($lastCall["file"] != "pos_ordre.php"){
		// get notifications for the user
		$query = db_select("SELECT * FROM notifications WHERE read_status = 0", __FILE__ . " linje " . __LINE__);
		while($notification = db_fetch_array($query)) {
			// Send the notification to the user
			?>
			<div class="relative z-10" aria-labelledby="modal-title" role="dialog" aria-modal="true">
				<!--
					Background backdrop, show/hide based on modal state.

					Entering: "ease-out duration-300"
					From: "opacity-0"
					To: "opacity-100"
					Leaving: "ease-in duration-200"
					From: "opacity-100"
					To: "opacity-0"
				-->
				<div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

				<div class="fixed inset-0 z-10 w-screen overflow-y-auto">
					<div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
					<!--
						Modal panel, show/hide based on modal state.

						Entering: "ease-out duration-300"
						From: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
						To: "opacity-100 translate-y-0 sm:scale-100"
						Leaving: "ease-in duration-200"
						From: "opacity-100 translate-y-0 sm:scale-100"
						To: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
					-->
					<div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
						<div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
						<div class="sm:flex sm:items-start">
							<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
							<svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
							</svg>
							</div>
							<div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
							<h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Notifikation</h3>
							<div class="mt-2">
								<p class="text-sm text-gray-500"><?php echo $notification["msg"]; ?></p>
							</div>
							</div>
						</div>
						</div>
						<div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
						<button type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">OK</button>
						</div>
					</div>
					</div>
				</div>
			</div>
			<?php
			// Mark the notification as read
			db_modify("UPDATE notifications SET read_status = 1 WHERE id = $notification[id]", __FILE__ . " linje " . __LINE__);
		}
	}
*/
if ($bg != 'nix') {
	if (!$bgcolor) $bgcolor = "#000000";
	print "<body bgcolor=\"$bgcolor\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\">\n";
}

?>

