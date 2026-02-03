<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------index/menu.php------ ver 4.1.1 --- 2025-08-15 ---
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
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 20180807 Corrected query to check if'kasse' is activated 20180807
// 20210223 LOE replaced string Sikkerhedskopi with findtekst value
// 20210721 LOE Fixed a bug and alsoo updated some texts not translated
// 20210817 LOE Quotation mark added to some database variables where they were missing
// 20211011 PHR Removed paperflow link as it is in 'kreditor'
// 20230320 MSC Added redirect to mobile version
// 20230714 LOE Minor modification + 20230805
// 11122023 PBLM 
// 20240108 LOE Minor modification.
// 20250414 LOE $_SESSION['UserName'] added to query barcode for app
// 20250815 LOE Empty text at 110 changed to 609 for old menu [Goods]

@session_start();	# Skal angives oeverst i filen??!!
$s_id=session_id();
(isset($_COOKIE['saldi_std']))?$regnskab = $_COOKIE['saldi_std']:$regnskab = NULL;

$title="$regnskab Oversigt";
$css="../css/standard.css";
$produktion=0; # Menucolumn PRODUKTION id disabled until module is reasy for use
$ansat_id=$popup=NULL;
if (isset($_GET['online'])) $online=$_GET['online'];
else $online=0;

if(!isset($regnskab)){
	//throw error and exit, wrong call made. Could happen when trying to access menu.php before installation
	//$alerttxt="An error occured. Please contact https://saldi.dk\\n";
	header('Location: index.php'); 
	//print "<BODY onLoad=\"javascript:alert('$alerttxt')\">";
	exit;
}
$modulnr=0;
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
$menu = 'S';
$qtxt = "update grupper set box3 ='$menu' where  art = 'USET' and kodenr = '$bruger_id'"; 
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
$_SESSION['UserName'] = $brugernavn;
if ($menu == 'S') {
	$_SESSION['UserName'] = $brugernavn;
	print "<script>try {parent.location.href = '../index/main.php'} catch {window.location.href = '../index/main.php'}</script>";
	die();
} else {
	print "<script>
if(window.self !== window.top) {
//run this code if in an iframe
// alert('in frame');
parent.location.href = \"../index/menu.php\";
} 
</script>";
}
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
	alert("Din browser blokerer pop-up vinduer. Saldi bruger pop-up vinduer til en del funktioner, så for at de funktioner skal virke, bliver du nødt til at tillade dem.");
} else {
	// Proceed with the report functionality
	console.log("Pop-up allowed, proceeding with report functionality.");
}
</script>

<?php
$provision=0;
if (trim($ansat_id)) {
	$ansat_id=$ansat_id*1;
	$r = db_fetch_array(db_select("select * from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__));
	$provision = $r['provision'];
}
if (file_exists("../doc/vejledning.pdf")) $vejledning="../doc/vejledning.pdf";
else $vejledning="http://saldi.dk/dok/komigang.html";

if ($menu=='T') {
	#MOBILE SITE CODE
	#$android = strpos($_SERVER['HTTP_USER_AGENT'],"Android");
	#$bberry = strpos($_SERVER['HTTP_USER_AGENT'],"BlackBerry");
	#$iphone = strpos($_SERVER['HTTP_USER_AGENT'],"iPhone");
	#$ipod = strpos($_SERVER['HTTP_USER_AGENT'],"iPod");
	#$webos = strpos($_SERVER['HTTP_USER_AGENT'],"webOS");
	#if ($android || $bberry || $iphone || $ipod || $webos== true) 
	#{ 
	#header('Location: ../mobile/menu.php');
	#}
	if(if_isset($_SESSION,null,'UserName') == null){
		$_SESSION['UserName'] = $brugernavn;
	}
	
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "<div class=\"headerTxt\">$regnskab &nbsp;•&nbsp; $title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu=='S') {
	include("../includes/sidemenu.php");
} else {
	oldmenu();
} 
#print	"</div><!-- end of wrapper --></body></html>\n";

if ($menu=='T') {
	print "<center><input type='button' onclick=\"location.href='../debitor/pos_ordre.php'\" accesskey='L' class='blue' style='width:500px;' value='Kassesystem'></center>";
} else {
	print "";
}

function oldmenu() {
	global $ansat_id;
	global $copyright;
	global $bgcolor,$bgcolor2;
	global $jsvars;
	global $popup,$produktion,$provision;
	global $regnskab,$rettigheder;
	global $stor_knap_bg,$sprog_id, $brugernavn; #20210721
	global $textcolor;
	global $vejledning,$version;
	$_SESSION['UserName'] = $brugernavn;
	
	print "<table style='width:100%;height:100%;' border='0' cellspacing='0' cellpadding='0'><tbody>\n";
	print "<tr><td align='center' valign='top'>\n";
	print "<table style='width:100%;height:20px;' align='center' border='0' cellspacing='4' cellpadding='0'><tbody>\n";
	# print "<td  $top_bund width='10%'> Ver $version</td>\n";
	print "<tr><td width='45%'><div class='top_bund'>".date_default_timezone_get()." ".date("H:i")."&nbsp;</div></td>\n";
	print "<td width='10%' align = 'center'><div class='top_bund'><a href='$vejledning' target='_blank' ";
	print " title='".findtekst(1622, $sprog_id)."'>".findtekst(92,$sprog_id)."</a></div></td>\n";
	print "<td><div class='top_bund'>&nbsp;</div></td>\n";
	print "<td width='20' title='".findtekst(161,$sprog_id)."'><div class='luk' onclick='location.href=\"logud.php\";' style='cursor:pointer;'></div></td>\n";
	print "</tbody></table></td></tr><tr><td align='center' valign='middle'>\n";

	print "<table align='center' style='border:3px solid $bgcolor2;border-radius:5px;'><tbody>\n";
	print "<tr><td style=\"background:url(../img/blaa2hvid_bg.gif);color:$textcolor;\" colspan=\"5\" align=\"center\">";
	print "<table style=\"border:1px solid $bgcolor2;width:100%;\"><tbody><tr>";
	print "<td width=\"10%\">";
#	if (file_exists("../img/logo.png")) print "<img style=\"border:0px solid;width:50px;heigth:50px\" alt=\"\" src=\"../img/logo.png\">";
	print "</td><td width=\"80%\" align=\"center\"><img src='../img/saldiLogo.png' alt='Saldi Logo' width='25px' height='25	px'></td><td width=\"10%\" align=\"right\">";
#	if (file_exists("../img/logo.png")) print "<img style=\"border:0px solid;width:50px;heigth:50px\" alt=\"\" src=\"../img/logo.png\">";
	print "</td></tr></tbody></table>";
	print "</td></tr>\n";
	print "<tr style=\"height:35px;\"><td colspan=\"5\" align=\"center\"><big><big><b>";

	$qtxt="select id from grupper where art = 'POS' and kodenr = '1' and box1 >= '1'"; #20180807
	if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		if ($popup)	print "<a href=\"../debitor/pos_ordre.php\" target=\"_blank\">$regnskab</a></b></big></big></td></tr>\n";
		else	print "<a href=\"../debitor/pos_ordre.php\">$regnskab</a></b></big></big></td></tr>\n";
	} elseif (file_exists('../sager/sager.php')){ // Hvis 'sager.php' eksistere, skal der linkes tilbage til sagstyring
		print "<a href=\"../sager/sager.php\">$regnskab</a></b></big></big></td></tr>\n";
	} else { 
		print "$regnskab</b></big></big></td></tr>\n";
	}
	
	print "<tr style=\"height:35px;\">\n";
	if ($provision || substr($rettigheder,2,1) || substr($rettigheder,3,1) || substr($rettigheder,4,1)) {
	$tekst=findtekst(91,$sprog_id);
	print "<td style=\"height:35px;width:150px;background:url(../img/blaa2hvid_bg.gif);\" align=\"center\" title=\"$tekst\">".findtekst(95,$sprog_id)."</td>\n";
	$vis_finans=1;
	} else $vis_finans=0;
	if (substr($rettigheder,5,1) || substr($rettigheder,6,1) || substr($rettigheder,12,1)) {
		$tekst=findtekst(96,$sprog_id);
		print "<td style=\"width:150px;background:url(../img/blaa2hvid_bg.gif);\" align=\"center\" title=\"$tekst\">".findtekst(99,$sprog_id)."</td>\n";
		$vis_debitor=1;
	} else $vis_debitor=0;
	if (substr($rettigheder,7,1) || substr($rettigheder,8,1) || substr($rettigheder,13,1)) {
		$tekst=findtekst(97,$sprog_id);
		print "<td style=\"width:150px;background:url(../img/blaa2hvid_bg.gif);\" align=\"center\" title=\"$tekst\">".findtekst(100,$sprog_id)."</td>\n";
		$vis_kreditor=1;
	} else $vis_kreditor=0;
	if ($produktion) { # Udeladt indtil test gennemfoert.
    print "<td style=\"width:150px;background:url(../img/blaa2hvid_bg.gif);\" align=\"center\"> <b>PRODUKTION</b></td>\n";
	}
	if (substr($rettigheder,9,1) || substr($rettigheder,10,1) || substr($rettigheder,15,1)) {
		$tekst=findtekst(101,$sprog_id);
		print "<td style=\"width:150px;background:url(../img/blaa2hvid_bg.gif);\" align=\"center\" title=\"$tekst\">".findtekst(102,$sprog_id)."</td>\n";
		$vis_lager=1;
	} else $vis_lager=0;

	$tekst=findtekst(103,$sprog_id);
	print "<td style=\"width:150px;background:url(../img/blaa2hvid_bg.gif);\" align=\"center\" title=\"$tekst\">".findtekst(104,$sprog_id)."</td>\n";
	print "</tr>\n";
#################################################################################################################
	print "<tr style=\"height:35px;\">\n";
	if ($vis_finans) {
		$tekst=findtekst(98,$sprog_id);
		if (substr($rettigheder,2,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:kladdeliste=window.open('../finans/kladdeliste.php?returside=../includes/luk.php','kladdeliste','".$jsvars."');kladdeliste.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(105,$sprog_id)."</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../finans/kladdeliste.php?returside=../index/menu.php\">".findtekst(105,$sprog_id)."</a></td>\n";
		} else {
			print "<td $stor_knap_bg><span style=\"color:#999;\">".findtekst(105,$sprog_id)."</td>\n";
		}
	}
	if ($vis_debitor) {
		$tekst=findtekst(106,$sprog_id);
		if (substr($rettigheder,5,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:d_ordrer=window.open('../debitor/ordreliste.php?returside=../includes/luk.php','d_ordrer','".$jsvars."');d_ordrer.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(107,$sprog_id)."</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../debitor/ordreliste.php?returside=../index/menu.php\">".findtekst(107,$sprog_id)."</a></td>\n";
		} else {
			print "<td $stor_knap_bg><span style=\"color:#999;\">".findtekst(107,$sprog_id)."</td>\n";
		}
	}
	if ($vis_kreditor) {
		$tekst=findtekst(108,$sprog_id);
		if (substr($rettigheder,7,1)) {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:k_ordrer=window.open('../kreditor/ordreliste.php?returside=../includes/luk.php','k_ordrer','".$jsvars."');k_ordrer.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(107,$sprog_id)."</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../kreditor/ordreliste.php?returside=../index/menu.php\">".findtekst(107,$sprog_id)."</a></td>\n";
		} else {
			print "<td $stor_knap_bg><span style=\"color:#999;\">".findtekst(107,$sprog_id)."</td>\n";
		}
	}

	if ($produktion) {
		if (substr($rettigheder,14,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:p_ordrer=window.open('../produktion/ordreliste.php?returside=../includes/luk.php','p_ordrer','".$jsvars."');p_ordrer.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">  ".findtekst(605, $sprog_id)."</td>\n";
				else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../produktion/ordreliste.php?returside=../index/menu.php\">".findtekst(107,$sprog_id)."</a></td>\n";
			} else {
				print "<td $stor_knap_bg><span style=\"color:#999;\">".findtekst(605, $sprog_id)."</td>\n";
			}
		}
		if ($vis_lager) {
			$tekst=findtekst(109,$sprog_id);
			if (substr($rettigheder,9,1)=='1') {
				if ($popup) print "<td $stor_knap_bg onClick=\"javascript:lager=window.open('../lager/varer.php?returside=../includes/luk.php','lager','".$jsvars."');lager.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(609,$sprog_id)."</td>\n";
				else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../lager/varer.php?returside=../index/menu.php\">".findtekst(609,$sprog_id)."</a></td>\n";
			} else {
				$row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
				#if ($row[ansat_id]) {
					if ($row['ansat_id']) {#20210817
					$row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]",__FILE__ . " linje " . __LINE__));
					#if ($row[navn]) {
					if ($row['navn']) { #20210817 quotation mark added
						$ref=$row['navn'];
						if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'",__FILE__ . " linje " . __LINE__))) {
							if ($row= db_fetch_array(db_select("select beskrivelse, kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {
								$lager=$row['kodenr']*1;
								if ($popup) print "<td $stor_knap_bg onClick=\"javascript:lager=window.open('../lager/beholdningsliste.php?returside=../index/menu.php?returside=../includes/luk.php','lager','".$jsvars."');lager.focus();\"	onMouseOver=\"this.style.cursor = 'pointer'\" >".findtekst(111,$sprog_id)."</td>\n";
								print "<td $stor_knap_bg><a href=\"../lager/beholdningsliste.php?returside=../index/menu.php\">".findtekst(111,$sprog_id)."</td>\n";
						}
					}
				}
			}
			#if (!$lager) {
			if (!$lager) {
					print "<td $stor_knap_bg><span style=\"color:#999;\">".findtekst(609,$sprog_id)."</td>\n";
			}
		}
	}
	$tekst=findtekst(112,$sprog_id);
	if (substr($rettigheder,0,1)=='1') {
		if ($popup) print "<td $stor_knap_bg onClick=\"javascript:kontoplan=window.open('../systemdata/kontoplan.php?returside=../includes/luk.php','kontoplan','".$jsvars."');kontoplan.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(113,$sprog_id)."</td>\n";
		else  print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../systemdata/kontoplan.php?returside=../index/menu.php\">".findtekst(113,$sprog_id)."</a></td>\n";
#		print "<td $stor_knap_bg title=\"Paperflow\"><a href=\"../paperpdf/papflowselct.php\">"."Paperflow"."</a></td>\n";
	} else {
		print "<td $stor_knap_bg><span style=\"color:#999;\">".findtekst(113,$sprog_id)."</td>\n";
	}
	print "</tr>\n";
	##########################################################################################################
	print "<tr style=\"height:35px;\">\n";
	if ($vis_finans) {
		$tekst=findtekst(114,$sprog_id);
		if (substr($rettigheder,3,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:regnskab=window.open('../finans/regnskab.php?returside=../includes/luk.php','regnskab','".$jsvars."');regnskab.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(115,$sprog_id)."</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../finans/regnskab.php?returside=../index/menu.php\">".findtekst(115,$sprog_id)."</a></td>\n";
		} else {
			print "<td $stor_knap_bg><span style=\"color:#999;\">".findtekst(115,$sprog_id)."</td>\n";
		}
	}
	if ($vis_debitor) {
		$tekst=findtekst(116,$sprog_id);
		if (substr($rettigheder,6,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:d_konti=window.open('../debitor/debitor.php?returside=../includes/luk.php','d_konti','".$jsvars."');d_konti.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(117,$sprog_id)."</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../debitor/debitor.php?returside=../index/menu.php\">".findtekst(117,$sprog_id)."</a></td>\n";
		} else {
			print "<td $stor_knap_bg><span style=\"color:#999;\">".findtekst(117,$sprog_id)."</td>\n";
		}
	}
	if ($vis_kreditor) {
		$tekst=findtekst(118,$sprog_id);
		if (substr($rettigheder,8,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:k_konti=window.open('../kreditor/kreditor.php?returside=../includes/luk.php','k_konti','".$jsvars."');k_konti.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(117,$sprog_id)."</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../kreditor/kreditor.php?returside=../index/menu.php\">".findtekst(117,$sprog_id)."</a></td>\n";
		} else {
			print "<td $stor_knap_bg><span style=\"color:#999;\">".findtekst(117,$sprog_id)."</td>\n";
		}
	}

	if ($produktion) {
		if (substr($rettigheder,15,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:k_rapport=window.open('../tidsreg/index.php?returside=../includes/luk.php','k_rapport','".$jsvars."');k_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" >  Tidsreg</td>\n";
    		else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../tidsreg/index.php?returside=../index/menu.php>Tidsreg</a></td>\n";
			} else {
				print "<td $stor_knap_bg><span style=\"color:#999;\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">Tidsreg</td>\n";
			}
		}
		if ($vis_lager) {
		$tekst=findtekst(181,$sprog_id);
			if (substr($rettigheder,10,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:modtageliste=window.open('../lager/modtageliste.php?returside=../includes/luk.php','modtageliste','".$jsvars."');modtageliste.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(182,$sprog_id)."</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../lager/modtageliste.php?returside=../index/menu.php\">".findtekst(182,$sprog_id)."</a></td>\n";
			} else {
			print "<td $stor_knap_bg><span style=\"color:#999;\">".findtekst(182,$sprog_id)."</td>\n";
		}
	}
	$tekst=findtekst(121,$sprog_id);
	if (substr($rettigheder,1,1)=='1') {
		if ($popup) print "<td  $stor_knap_bg onClick=\"javascript:syssetup=window.open('../systemdata/syssetup.php?returside=../includes/luk.php','syssetup','".$jsvars."');syssetup.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(122,$sprog_id)."</td>\n";
		else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../systemdata/syssetup.php?returside=../index/menu.php\">".findtekst(122,$sprog_id)."</a></td>\n";
	} else {
		if ($popup) print "<td $stor_knap_bg onClick=\"javascript:brugerdata=window.open('../systemdata/brugerdata.php?returside=../includes/luk.php','brugerdata','".$jsvars."');brugerdata.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(122,$sprog_id)."</td>\n";
		else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../systemdata/brugerdata.php?returside=../index/menu.php\">".findtekst(122,$sprog_id)."</a></td>\n";
	}
	print "</tr>\n";
############################################################################################
	print "<tr style=\"height:35px;\">\n";
	if ($vis_finans) {
		$tekst=findtekst(123,$sprog_id);
		if (substr($rettigheder,4,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:f_rapport=window.open('../finans/rapport.php?returside=../includes/luk.php','f_rapport','".$jsvars."');f_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(124,$sprog_id)."</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../finans/rapport.php?returside=../index/menu.php\">".findtekst(124,$sprog_id)."</a></td>\n";
		} elseif ($provision) {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:provisionsrapport=window.open('../finans/provisionsrapport.php?returside=../includes/luk.php','provisionsrapport','".$jsvars."');provisionsrapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">  Provision</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../finans/provisionsrapport.php?returside=../index/menu.php\">".findtekst(124,$sprog_id)."</a></td>\n";
		} else {
			print "<td $stor_knap_bg><span style=\"color:#999;\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\" title=\"$tekst\">".findtekst(124,$sprog_id)."</td>\n";
		}
	}

	if ($vis_debitor) {
		$tekst=findtekst(125,$sprog_id);
		if (substr($rettigheder,12,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:d_rapport=window.open('../debitor/rapport.php?returside=../includes/luk.php','d_rapport','".$jsvars."');d_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(124,$sprog_id)."</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../debitor/rapport.php?returside=../index/menu.php\">".findtekst(124,$sprog_id)."</a></td>\n";
		} else {
			print "<td $stor_knap_bg><span style=\"color:#999;\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">".findtekst(124,$sprog_id)."</td>\n";
		}
	}

	if ($vis_kreditor) {
		$tekst=findtekst(126,$sprog_id);
		if (substr($rettigheder,13,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:k_rapport=window.open('../kreditor/rapport.php?returside=../includes/luk.php','k_rapport','".$jsvars."');k_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(124,$sprog_id)."</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../kreditor/rapport.php?returside=../index/menu.php\">".findtekst(124,$sprog_id)."</a></td>\n";
		} else {
			print "<td $stor_knap_bg><span style=\"color:#999;\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">".findtekst(124,$sprog_id)."</td>\n";
		}
	}

	if ($produktion) {
		print "<td>&nbsp;</td>\n";
	}
	if ($vis_lager) {
		$tekst=findtekst(127,$sprog_id);
		if (substr($rettigheder,15,1)=='1') {
			if ($popup) print "<td $stor_knap_bg onClick=\"javascript:k_rapport=window.open('../lager/rapport.php?returside=../includes/luk.php','k_rapport','".$jsvars."');k_rapport.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(124,$sprog_id)."</td>\n";
			else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../lager/rapport.php?returside=../index/menu.php\">".findtekst(124,$sprog_id)."</a></td>\n";
		} else {
			print "<td $stor_knap_bg><span style=\"color:#999;\"><a onfocus=\"this.style.color='#888888'\" onblur=\"this.style.color='#888888'\">".findtekst(124,$sprog_id)."</td>\n";
		}
	}
	$tekst=findtekst(128,$sprog_id);
	if (substr($rettigheder,11,1)=='1') {
		if ($popup) print "<td  $stor_knap_bg onClick=\"javascript:backup=window.open('../admin/backup.php?returside=../includes/luk.php','backup','".$jsvars."');backup.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(521,$sprog_id)."</td>\n"; #20210223
		else print "<td $stor_knap_bg title=\"$tekst\"><a href=\"../admin/backup.php?returside=../index/menu.php\">".findtekst(521,$sprog_id)."</a></td>\n"; #20210223
	} else {
		print "<td $stor_knap_bg><span style=\"color:#999;\">Backup</td>\n";
	}

	print "</tr>\n";

	print	"</tbody></table>\n";
	print	"</td></tr>\n";
	print	"<tr><td align=\"center\" valign=\"bottom\">\n";
	print "<a href = 'menu.php?useMain=on'>Nyt design</a><br>";
	print "<div class=top_bund><small>SALDI&nbsp;version&nbsp;$version&nbsp;-&nbsp;Copyright&nbsp;&copy;&nbsp;$copyright</small></div></td></tr>\n";
	print	"</tbody></table>\n";
	print	"</center></body></html>\n";
	$query = db_select("select * from grupper where art = 'RA'",__FILE__ . " linje " . __LINE__);
	if (!$row = db_fetch_array($query)) {
		if ($popup) print "<body onLoad=\"JavaScript:regnaar=window.open('../systemdata/regnskabsaar.php?returside=../includes/luk.php','regnaar',',.$jsvars.,alwaysraised=yes');regnaar.focus();regnaar.focus();\">\n";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=../systemdata/regnskabskort.php?returside=../index/menu.php\">\n";
	}
}

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>
<script>
	// prompt user for bank account number
/* 	function promptBankAccount() {
		var bankAccount = prompt("I overensstemmelse med bogføringsloven skal der være en konto i kontoplanen, der repræsenterer banken. Venligst angiv dit valgte kontonummer.", "")
		if (bankAccount != null) {
			alert("Din konto nummer er opdateret")
		}
	}
	promptBankAccount() */
</script>
