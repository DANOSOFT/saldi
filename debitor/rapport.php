<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------debitor/rapport.php------patch 4.1.1 ----2025-09-24--------------
//                           LICENSE
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------

// 20121105 - Fejl ved "masseudligning (Klik på 0,00 i åbenpostoversigt) når kun 1 dato sat. Søg 20121105 
// 20170303 - Inlføjet inkasso (ikke aktiv)
// 20180207 - PHR Udlign kan nu bestå af flere kontonumre. Søg udlign
// 20181218 - MSC Rettet isset fejl
// 20190410 - PHR $konto_fra=$konto_fra=$konto rettet til $konto_fra=$konto_til=$konto;
// 20190815 - PHR
// 20210805 - LOE Translated some texts 
// 20250923 - LOE Sets rapportart to $rapportart = 'kontokort'; only if not accountChart 


@session_start();
$s_id = session_id();
$css = "../css/std.css";
$title = "Debitorrapport";
$modulnr = 12;

$tmp = NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");
include("../includes/autoudlign.php");
include("../includes/rapportfunc.php");
include("../includes/row-hover-style-with-links.js.php");


?>
<script>
function checkPopupBlocked() {
    var popup = window.open('', 'test', 'width=1,height=1');
    
    if (!popup || popup.closed || typeof popup.closed == 'undefined') {
        // Popup blocked
        return true;
    } else {
        // Popup allowed - close test popup
        popup.close();
        return false;
    }
}

const res = checkPopupBlocked();
if (res) {
	// Alert the user about the popup blocker (Dansk translation)
	alert("Din browser blokerer pop-up vinduer. For at kunne bruge rapportfunktionen, skal du tillade pop-up vinduer for denne side.");
} else {
	// Proceed with the report functionality
	console.log("Pop-up allowed, proceeding with report functionality.");
}
</script>
<?php

print '
<style>
.hover-highlight:hover {
  outline: 2px solid #000;
  background-color: #f9f9f9;
  cursor: pointer;
}
</style>
<script>
document.addEventListener("DOMContentLoaded", function () {
  // Only apply to tables with a specific class, e.g. "data-table"
  document.querySelectorAll("table.data-table").forEach(table => {
    table.querySelectorAll("tr").forEach(row => {
      const tds = row.querySelectorAll("td");
      if (tds.length <= 1) return;
      if (row.offsetParent === null) return;

      let hasLabel = false;
      let skip = false;

      for (let td of tds) {
        if (td.querySelector("label")) {
          hasLabel = true;
        }
        const interactive = td.querySelector("button, select, input:not([type=\'hidden\']), b, i, img");
        if (interactive) {
          skip = true;
          break;
        }
      }

      if (!skip || hasLabel) {
        row.classList.add("hover-highlight");
      }
    });
  });
});
</script>
';

#print "<script LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\" SRC=\"../javascript/overlib.js\"></script>";
global $sprog_id; //2021

$backUrl = isset($_GET['returside'])
	? $_GET['returside']
	: '../index/menu.php';
if ($popup)
	$returside = "../includes/luk.php";
else
	$returside = $backUrl;

if(isset($_GET["rapportart"]) && $_GET["rapportart"] == "openpost") {
	$openpost = true;
}

if (!isset($_GET["rapportart"]))
	$rapportart = NULL;
if (!isset($_GET['submit']))
	$_GET['submit'] = NULL;
/* if (!isset($_GET['returside']))
	$_GET['returside'] = NULL; */
if (!isset($_GET['udlign']))
	$_GET['udlign'] = NULL;

if (isset($_GET['ny_rykker'])) {
	$dato_fra = $_GET['dato_fra'];
	$dato_til = $_GET['dato_til'];
	$konto_fra = $_GET['konto_fra'];
	$konto_til = $_GET['konto_til'];
	#	$regnaar=$_GET['regnaar'];
	openpost($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, 'D');
	exit;
} elseif (isset($_GET['rapportart']) && $_GET['rapportart'] != 'openpost') {
	$dato_fra = if_isset($_GET['dato_fra']);
	$dato_til = if_isset($_GET['dato_til']);
	$konto_fra = if_isset($_GET['konto_fra']);
	$konto_til = if_isset($_GET['konto_til']);
	$rapportart = $_GET['rapportart']; 
	if (isset($_GET['udlign'])) {
		$udlign = explode(",", $_GET['udlign']);
		#		$autoudlign=array($udlign);
		for ($x = 0; $x < count($udlign); $x++) {
			autoudlign($udlign[$x]);
		}
	}
	if ($rapportart == 'accountChart')
	include("../includes/row-hover-style-with-link-no-input.js.php");
    else $rapportart = 'kontokort'; 
	
	if ($rapportart == 'accountChart')
		include_once("../includes/reportFunc/accountChart.php");
	$rapportart($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, 'D');
	
	exit;
}

$rapportart = NULL;
if (isset($_POST['openpost']) || $openpost)
	$rapportart = 'openpost';
if (isset($_POST['kontosaldo']))
	$rapportart = 'kontosaldo';
if (isset($_POST['kontokort']))
	$rapportart = 'kontokort';
if (isset($_POST['accountChart']))
	$rapportart = 'accountChart';
if ($rapportart == 'accountChart')
	include_once("../includes/reportFunc/accountChart.php");
if (isset($_POST['dato'])) {
	$dato = $_POST['dato'];
	if (strpos($dato, ':'))
		list($dato_fra, $dato_til) = explode(":", $dato);
	elseif ($dato) {
		$dato_til = $dato_fra = $dato;
		$dato_fra = '01-01-2000';
	} else
		$dato_til = $dato_fra = NULL;
	if ($dato_fra) {
		$fromdate = usdate($dato_fra);
		$dato_fra = str_replace("-20", "", dkdato($fromdate));
		$dato_fra = trim(str_replace("-", "", $dato_fra));
	}
	if ($dato_til) {
		$todate = usdate($dato_til);
		$dato_til = str_replace("-20", "", dkdato($todate));
		$dato_til = trim(str_replace("-", "", $dato_til));
	}
}
if (isset($_POST['konto'])) {
	$konto = $_POST['konto'];
	if (strpos($konto, ':'))
		list($konto_fra, $konto_til) = explode(":", $konto);
	else
		$konto_fra = $konto_til = $konto;
	if (!is_numeric($konto_fra))
		$konto_til = NULL;
}
$husk = if_isset($_POST['husk']);
if (isset($_POST['salgsstat']) && $_POST['salgsstat']) {
	if ($husk)
		db_modify("update grupper set box1='$husk',box2='$dato_fra',box3='$dato_til',box4='$konto_fra',box5='$konto_til',box6='$rapportart' where art='DRV' and kodenr='$bruger_id'", __FILE__ . " linje " . __LINE__);
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../includes/salgsstat.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&art=D\">";
	exit;
}
if (isset($_POST['saft'])) {
	header("Location: saftCashRegister.php");
	exit();
}

if (isset($_POST['submit']) || $rapportart) {
	#	$husk=$_POST['husk'];
	if (!$rapportart) {
		$submit = strtolower(trim($_POST['submit']));
		$rapportart = strtolower(trim($_POST['rapportart']));
		$dato_fra = $_POST['dato_fra'];
		$dato_til = $_POST['dato_til'];
	} else {
		db_modify("update grupper set box1='$husinputk',box2='$dato_fra',box3='$dato_til',box4='$konto_fra',box5='$konto_til',box6='$rapportart' where art='DRV' and kodenr='$bruger_id'", __FILE__ . " linje " . __LINE__);
		$submit = 'ok';
	}
	#	$md=$_POST['md'];
	#	if (isset($_POST['konto_fra']) && strpos($_POST['konto_fra'],":")) {
	#		list ($konto_fra, $firmanavn) = explode(":", $_POST['konto_fra']);
	$konto_fra = trim(if_isset($konto_fra));
	#	}
	#	if (isset($_POST['konto_til']) && strpos($_POST['konto_til'],":")) {
	#		list ($konto_til, $firmanavn) = explode(":", $_POST['konto_til']);
	$konto_til = trim(if_isset($konto_til));
	#	}
	#	if (isset($_POST['regnaar']) && strpos($_POST['regnaar'],"-")) {
	#		list ($regnaar, $firmanavn)= explode("-", $_POST['regnaar']);
	$firmanavn = trim(if_isset($firmanavn));
	#	}
	if (!isset($_POST['konto_id']))
		$_POST['konto_id'] = NULL;
	if (!isset($_POST['kontoudtog']))
		$_POST['kontoudtog'] = NULL;
	if (!isset($_POST['rykkerbelob']))
		$_POST['rykkerbelob'] = NULL;
	if (($submit == "mail kontoudtog") || ($submit == "opret rykker") || ($submit == "ryk alle")) {
		$kontoantal = $_POST['kontoantal'];
		$konto_id = $_POST['konto_id'];
		$kontoudtog = $_POST['kontoudtog'];
		$rykkerbelob = $_POST['rykkerbelob'];
		$y = 0;
		$tmp = NULL;
		for ($x = 1; $x <= count($konto_id); $x++) {
			if (isset($kontoudtog[$x])) {
				if ($kontoudtog[$x] == 'on' && ($submit == "mail kontoudtog") || ($rykkerbelob[$x] > 0)) {
					$tmp .= $konto_id[$x] . ";";
					$y++;
				}
			}
		}
		$kontoantal = $y;
		if (!isset($tmp))
			$tmp = NULL;
		if ($tmp) {
			if ($submit == "mail kontoudtog") {
				print "<BODY onLoad=\"window.open('mail_kontoudtog.php?kontoliste=$tmp&dato_fra=$dato_fra&dato_til=$dato_til&kontoantal=$kontoantal','','$jsvars')\">";
			} else {
				print "<BODY onLoad=\"window.open('ny_rykker.php?kontoliste=$tmp&kontoantal=$kontoantal','','$jsvars')\">";
				$ny_rykker = 1;
			}
		} elseif ($submit == "ryk alle") {
			print "<BODY onLoad=\"window.open('ny_rykker.php?kontoliste=alle&kontoantal=max','','$jsvars')\">";
			$ny_rykker = 1;
		} else {
			$alert = findtekst(1791, $sprog_id); #20210805
			$alert1 = findtekst(1792, $sprog_id);
			if ($submit == "mail kontoudtog") {
				print "<BODY onLoad=\"javascript:alert('$alert')\">";
			} else {
				print "<BODY onLoad=\"javascript:alert('$alert1')\">";
			}
		}
		/*
								  if (!strstr($dato_fra," ")) { 
									  if ($md[$dato_fra]) $dato_fra=$regnaar." ".$md[$dato_fra];
									  else $dato_fra=$regnaar." ".$dato_fra;
									  if ($md[$dato_til]) $dato_til=$regnaar." ".$md[$dato_til];
									  else $dato_til=$regnaar." ".$dato_til;
								  }
						  */
		$submit = 'ok';
	} elseif ($submit == "slet" || $submit == "udskriv" || strstr($submit, "bogf") || $submit == "ny rykker" || $submit == "afslut" || $submit == "inkasso") {
		$rykkerantal = if_isset($_POST['rykkerantal']);
		$rykker_id = if_isset($_POST['rykker_id']);
		$rykkerbox = if_isset($_POST['rykkerbox']);
		if ($submit == "slet") {
			for ($x = 1; $x <= $rykkerantal; $x++) {
				if (isset($rykkerbox[$x]) && $rykkerbox[$x] == 'on') {
					db_modify("delete from ordrelinjer where ordre_id=$rykker_id[$x]", __FILE__ . " linje " . __LINE__);
					db_modify("delete from ordrer where id=$rykker_id[$x]", __FILE__ . " linje " . __LINE__);
				}
			}
		} elseif ($submit == "udskriv" || $submit == "ny rykker" || $submit == "afslut" || $submit == "inkasso") {
			$tmp = '';
			$tmp2 = 0;
			for ($x = 1; $x <= $rykkerantal; $x++) {
				if ($rykkerbox[$x] == 'on') {
					if ($tmp)
						$tmp = $tmp . ";";
					$tmp = $tmp . $rykker_id[$x];
					$tmp2++;
				}
			}
			if ($submit == "udskriv" && $tmp2 > 0)
				print "<BODY onLoad=\"window.open('rykkerprint.php?rykker_id=$tmp&kontoantal=$tmp2','','$jsvars')\">";
			elseif ($submit == "ny rykker" && $tmp2 > 0) {
				print "<BODY onLoad=\"window.open('ny_rykker.php?rykker_id=$tmp&kontoantal=$tmp2','','$jsvars')\">";
				$ny_rykker = 1;
			} elseif ($submit == "afslut" && $tmp2 > 0) {
				print "<BODY onLoad=\"window.open('afslut_rykker.php?rykker_id=$tmp&kontoantal=$tmp2','','$jsvars')\">";
				$ny_rykker = 1;
			} elseif ($submit == "inkasso" && $tmp2 > 0) {
				echo "SASASA";
				print "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0; url=inkasso.php?rykker_id=$tmp&kontoantal=$tmp2\">";
				#				print "<BODY \"onLoad=location.href='inkasso.php?rykker_id=$tmp&kontoantal=$tmp2'\">";
				#				$ny_rykker=1;
				exit;
			}
		} elseif (strstr($submit, "bogf")) {
			for ($x = 1; $x <= $rykkerantal; $x++) {
				if ($rykkerbox[$x] == 'on')
					bogfor_rykker($rykker_id[$x]);
			}
		}
		/*
								  if (!strstr($dato_fra," ")) { 
									  if ($md[$dato_fra]) $dato_fra=$regnaar." ".$md[$dato_fra];
									  else $dato_fra=$regnaar." ".$dato_fra;
									  if ($md[$dato_til]) $dato_til=$regnaar." ".$md[$dato_til];
									  else $dato_til=$regnaar." ".$dato_til;
								  }
						  */
		$submit = 'ok';
	}
	# echo "KF $konto_fra<br>";
} elseif (isset($_GET['konto_fra'])) {
	$rapportart = $_GET['rapportart'];
	$dato_fra = $_GET['dato_fra'];
	$dato_til = $_GET['dato_til'];
	$konto_fra = $_GET['konto_fra'];
	$konto_til = $_GET['konto_til'];
	#	$regnaar=$_GET['regnaar'];
	$submit = $_GET['submit'];
	$returside = $_GET['returside'];
	if ($udlign = $_GET['udlign'])
		autoudlign($udlign);
} elseif (isset($_GET['kontonr'])) {
	$konto_fra = $_GET['kontonr'];
	$konto_til = $_GET['kontonr'];
	$submit = "ok";
	$rapportart = $_GET['rapportart'];
	/*
				 $row = db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
					 $start_md[$x]=$row['box1']*1;
					 $start_aar[$x]=$row['box2']*1;
					 $slut_md[$x]=$row['box3']*1;
					 $slut_aar[$x]=$row['box4']*1;
					 $dato_fra="$row[box2] $row[box1]";
					 $dato_til="$row[box4] $row[box3]";
			 */
}
#if ($dato_fra) $dato_fra=find_maaned_nr($dato_fra); 
#if ($dato_til) $dato_til=find_maaned_nr($dato_til); 

if ($udlign = if_isset($_GET['udlign']))
	echo "dada"; #autoudlign($udlign);
if (strstr($rapportart, "ben post"))
	$rapportart = "openpost";
if (!isset($submit))
	$submit = NULL;
if ($submit != 'ok')
	$submit = 'forside';
elseif ($rapportart)
	$submit = $rapportart;

if (!isset($dato_fra))
	$dato_fra = NULL;
if (!isset($dato_til))
	$dato_til = NULL;
if (!isset($konto_fra))
	$konto_fra = NULL;
if (!isset($konto_til))
	$konto_til = NULL;

$submit($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, 'D', $returside);

?>

</html>