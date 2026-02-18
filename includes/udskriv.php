<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/udskriv.php --- lap 4.1.1 --- 2026.02.18 ---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 2013.03.20 Tilføjet mulighed for fravalg af logo på udskrift. Søg "PDF-tekst"
// 2013.12.02	Efter udskrivning af kreditorordre, åbnes ordre som debitorordre. Tilføjer $art. Søg $art.
// 2013.12.10	Efter udskrivning, åbnes ordren 2. gang v. popup. Tilføjet "|| $popup", søg 20131210
// 2014.06.13 Har sat gammel og ny kode sammen, så det virker til både saldi og stillads. Søg efter 'stillads' for indsat kode
// 2016.11.25 PHR Indført html som formulargenerator som alternativ til postscript. Søg htmfp, .htm & weasyprint
// 2017.03.24	PHR Blev smidt af efter udskriv som PDF grundet at det ikke mere kører i popup. Søg art='R'
// 2018.04.18	PHR Tilføjet udskriv til='fil'
// 2019.01.03 PHR	Tilføjet '.ps' 20190103
// 2019.04.16 PHR - Added localPrint for printing through local webserver (raspberry) 
// 2019.10.23 PHR - $exec_path now read from admin settings #20191023
// 2019.11.05 PHR - Varius cleanup
// 2020.01.13 PHR - Print from 'genfakturer' returned to includes/ordreliste.php which does not exist. 20200113
// 20230522 PHR php8
// 20260102 LOE Added alert to install pdftk if not already done.
// 20260217 LOE Updated the $href for 'DO' type.


@session_start();
$s_id=session_id();
header('Expires: Mon, 01 Jan 2017 05:00:00 GMT'); 
header('Cache-Control: no-store, no-cache, must-revalidate');  
header('Cache-Control: post-check=0, pre-check=0', FALSE); 
header('Pragma: no-cache');

$css="../css/standard.css";		
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (!isset($exec_path)) $exec_path="/usr/bin";
$localPrint=if_isset($_COOKIE, NULL, 'localPrint');
$udfil=$zx=NULL;

$ps_fil        = if_isset($_GET, NULL, 'ps_fil');
$valg          = if_isset($_GET, NULL, 'valg');
$logoart       = if_isset($_GET, NULL, 'logoart');
$id            = if_isset($_GET, NULL, 'id');
$udskriv_til   = if_isset($_GET, NULL, 'udskriv_til');
$udskrift      = if_isset($_GET, NULL, 'udskrift');
$bgr           = if_isset($_GET, NULL, 'bgr');          # stillads
$art           = if_isset($_GET, NULL, 'art');
$ordreliste    = if_isset($_GET, NULL, 'ordreliste');
$ordre_antal   = if_isset($_GET, NULL, 'ordre_antal');
$returside    = if_isset($_GET, NULL, 'returside');


if ($udskriv_til == 'PDF') { // refer ../includes/udskriv.php
	if (substr($art,0,1) == 'K' && !$returside) $returside = '../kreditor/ordreliste.php';
	elseif (!$returside) $returside = '../debitor/ordreliste.php';
    $pdftk_check = shell_exec("which pdftk");
		$pdftk_check = trim($pdftk_check);

    // If pdftk is not installed, alert the user and redirect
    if (!$pdftk_check) {
        error_log("ERROR: pdftk is not installed. Please install pdftk first.");
        
        // Use JavaScript to alert and then redirect
        echo "<script>
                alert('ERROR: pdftk is not installed. Please install pdftk first.');
                setTimeout(function() {
                    window.location.href = '$returside';
                }, 1000); // 1 second delay
              </script>";
        exit();
    }
}


if ($returside=='ordreliste.php') { #20200113
	if ($art=='KO' || $art=='KK') $returside="../kreditor/ordreliste.php";
	else $returside="../debitor/ordreliste.php";
}

if ($udskriv_til=='historik') {
	historik($id,$ps_fil);
	$valg="tilbage";
}
if ($ordreliste) {
	$ordre_id = explode(",",$ordreliste);
}
if ($id && !$art) {
	$r=db_fetch_array(db_select("select art from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$art=$r['art'];
} 
if (!$art) $art='DO';

if ($valg=="tilbage" && !$bgr) {
	if ((!$id && $art!='R') || $popup ) { # 20131210
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
  	exit;
	} else {
		if ($art=='R') print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/rykker.php?rykker_id=$id\">";
		elseif (substr($art,0,1)=='K') print "<meta http-equiv=\"refresh\" content=\"0;URL=../kreditor/ordre.php?id=$id\">";
		elseif ($art=='PO') print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/pos_ordre.php?id=$id\">";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordre.php?id=$id\">";
  	exit;
	}
} elseif ($valg=="tilbage" && $bgr) {# stillads
  if ($popup || (!$id && $art!='R')) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
  	exit;
	} else {
		if ($art=='R') print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/rykker.php?rykker_id=$id\">";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordre.php?id=$id\">";
  	exit;
	}
}
if (!$valg) {
	$valg="pdf";
	$qtxt="select id,box1 from grupper where art='PV'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		if ($r['box1']=='on') {
			$ip=$_SERVER['REMOTE_ADDR'];
			print "<!--!";
			system("lpq -P $ip", $tjek);
			print "-->";
			if ($tjek) {
				$ip=NULL;
			} else $valg='ip';
		}
	}
}
if ($valg) {
	include("../includes/connect.php"); #20191023
	$r=db_fetch_array(db_select("select var_value from settings where var_name='ps2pdf'",__FILE__ . " linje " . __LINE__));
	if ($r['var_value']) $ps2pdf=$r['var_value'];
	else $ps2pdf="$exec_path/ps2pdf";
	include("../includes/online.php");
	$log=fopen("../temp/$db/udskriv.log","a");
	fwrite($log,__line__." Valg: $valg\n");
	$qtxt="select box1,box2,box3 from grupper where art='PV'";
	fwrite($log,__line__." $qtxt\n");
  $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($valg=="pdf" || $valg=="ip")  {
#		print "<!--";
	if (isset($r['box2']) && $r['box2']) { 
	fwrite($log,__line__." system (\"$r[box2] ../temp/$ps_fil.ps ../temp/$ps_fil.pdf\"\n");
			system ("$r[box2] ../temp/$ps_fil.ps ../temp/$ps_fil.pdf");
		} elseif (isset($r['box3']) && $r['box3'] && $udskrift!='kontokort') { # Brug html
		fwrite($log,__line__." unlink(\"../temp/".$ps_fil."_*.pdf\"\n");
		if (file_exists("../temp/".$ps_fil."_*.pdf")) unlink("../temp/".$ps_fil."_*.pdf");
		list($a,$b,$c)=explode("/",$ps_fil);
		$htmfil=glob("../temp/$a/$b/*.htm");
		$indfil='';
		for ($i=0;$i<count($htmfil);$i++) {
			if (filesize($htmfil[$i])) {
				$pdf[$i]=str_replace("htm","pdf",$htmfil[$i]);
				fwrite($log,__line__." $pdf[$i]=str_replace(\"htm\",\"pdf\",$htmfil[$i])\n"); #20190103
				fwrite($log,__line__." system (\"weasyprint -e UTF-8 $htmfil[$i] $pdf[$i]\")\n"); #20190103
				system ("weasyprint -e UTF-8 $htmfil[$i] $pdf[$i]");
				($indfil)?$indfil.=" ".$pdf[$i]:$indfil=$pdf[$i];
				fwrite($log,__line__." indfil $indfil\n");
			} 
			if (count($htmfil)>1) {
				$udfil="../temp/$a/$b/udskrift.pdf";
				fwrite($log,__line__." $udfil=\"../temp/$a/$b/udskrift.pdf\"\n");
				$ps_fil="/$a/$b/udskrift";
				fwrite($log,__line__." $ps_fil=\"/$a/$b/udskrift\"\n"); 
			} else $udfil=NULL;
		} 
		if ($udfil) {
			system ("pdftk $indfil output $udfil");
			fwrite($log,__line__." system (\"pdftk $indfil output $udfil\")\n");
			for ($i=0;$i<count($htmfil);$i++) {
				unlink ($htmfil[$i]);
				fwrite($log,__line__." unlink ($htmfil[$i])\n");
				if (isset($pdffil[$i]) && file_exists($pdffil[$i])) {
					unlink ($pdffil[$i]);
					fwrite($log,__line__." unlink ($pdffil[$i])\n");
				}
			}
		}
	} else { # Brug PostScript 
		$ps_fil=str_replace("../temp/","",$ps_fil);
		$ps_fil=str_replace("$db/$db","$db",$ps_fil);
		if (file_exists("../temp/".$ps_fil."_*.pdf")) {
			unlink("../temp/".$ps_fil."_*.pdf");
			fwrite($log,__line__." unlink(\"../temp/".$ps_fil."_*.pdf\"\n");
		}
		list($a,$b,$c)=explode("/",$ps_fil);
		$psfil=glob("../temp/$a/$b/*.ps");
#		fwrite($log,__line__." $psfil=glob(\"../temp/$a/$b/*.ps\")\n");
		$indfil='';
		for ($i=0;$i<count($psfil);$i++) {
#				fwrite($log,__line__." PSFIL $psfil[$i]\n");
			if (filesize($psfil[$i])) {
				$pdf[$i]=str_replace("ps","pdf",$psfil[$i]);
				fwrite($log,__line__." $pdf[$i]=str_replace(\"ps\",\"pdf\",$psfil[$i])\n");
				fwrite($log,__line__." system (\"$ps2pdf  $psfil[$i] $pdf[$i]\")\n");
				system ("$ps2pdf $psfil[$i] $pdf[$i]");
				($indfil)?$indfil.=" ".$pdf[$i]:$indfil=$pdf[$i];
				fwrite($log,__line__." indfil $indfil\n");
			} 
			if (count($psfil)>1) {
				$udfil="../temp/$a/$b/udskrift.pdf";
				fwrite($log,__line__." $udfil=\"../temp/$a/$b/udskrift.pdf\"\n");
				$ps_fil="/$a/$b/udskrift";
				fwrite($log,__line__." $ps_fil=\"/$a/$b/udskrift\"\n");
			} else $udfil=NULL;
		}
		if ($udfil) {
		system ("pdftk $indfil output $udfil");
		fwrite($log,__line__." system (\"pdftk $indfil output $udfil\")\n");
		for ($i=0;$i<count($psfil);$i++) {
			unlink ($psfil[$i]);
			fwrite($log,__line__." unlink ($psfil[$i])\n");
				if (isset($pdffil[$i]) && file_exists($pdffil[$i])) {
				unlink ($pdffil[$i]);
				fwrite($log,__line__." unlink ($pdffil[$i])\n");
			}
			}
		}
	}
	
	if ($zx) { # Brug PostScript 
		$tmp = system ("ls");
			fwrite($log,__line__." system (\"$ps2pdf ../temp/$ps_fil.ps ../temp/$ps_fil.pdf\")\n");
			
			
			#system ("$ps2pdf ../temp/$ps_fil.ps ../temp/$ps_fil.pdf");
			if (file_exists("../temp/$ps_fil.ps") && filesize("../temp/$ps_fil.ps") > 0) {
				system ("$ps2pdf ../temp/$ps_fil.ps ../temp/$ps_fil.pdf");
			} else {
				fwrite($log, "Error: PS file not found or empty: ../temp/$ps_fil.ps\n");
			}
		}

		fwrite($log,__line__." if (file_exists(\"../temp/$ps_fil.pdf\")\n");
		$ps_fil=str_replace("../temp/$db","",$ps_fil);
		// if (file_exists("../temp/$ps_fil.pdf")) {
		// 	if (strpos($ps_fil,'tilbud') && file_exists("../logolib/$db_id/tilbud_bg.pdf")) $bg_fil="../logolib/$db_id/tilbud_bg.pdf";
		// 	elseif (strpos($ps_fil,'ordre') && file_exists("../logolib/$db_id/ordrer_bg.pdf")) $bg_fil="../logolib/$db_id/ordrer_bg.pdf";
		// 	elseif (strpos($ps_fil,'fakt') && file_exists("../logolib/$db_id/faktura_bg.pdf")) $bg_fil="../logolib/$db_id/faktura_bg.pdf";
		// 	elseif (file_exists("../logolib/$db_id/bg.pdf")) $bg_fil="../logolib/$db_id/bg.pdf";
if (file_exists("../temp/$ps_fil.pdf")) {
    // Use the background file from GET if provided and exists
    // if (isset($_GET['bgr']) && $_GET['bgr'] && file_exists($_GET['bgr'])) {
    //     $bg_fil = $_GET['bgr'];
    // }

	if (isset($_GET['bgr']) && $_GET['bgr']) {
    $bgr_param = urldecode($_GET['bgr']);
	
    if (file_exists($bgr_param)) {
        $bg_fil = $bgr_param;
    }
}
	 elseif (strpos($ps_fil,'tilbud') && file_exists("../logolib/$db_id/tilbud_bg.pdf")) $bg_fil="../logolib/$db_id/tilbud_bg.pdf";
    elseif (strpos($ps_fil,'ordre') && file_exists("../logolib/$db_id/ordrer_bg.pdf")) $bg_fil="../logolib/$db_id/ordrer_bg.pdf";
    elseif (strpos($ps_fil,'fakt') && file_exists("../logolib/$db_id/faktura_bg.pdf")) $bg_fil="../logolib/$db_id/faktura_bg.pdf";
    elseif (file_exists("../logolib/$db_id/bg.pdf")) $bg_fil="../logolib/$db_id/bg.pdf";
			print "<!-- kommentar for at skjule uddata til siden \n";
			if (system("which pdftk") && file_exists($bg_fil) && $udskriv_til != 'PDF-tekst' && $udskriv_til != 'fil') {
				$out="../temp/".$ps_fil."x.pdf";
				fwrite($log,__line__." $out=\"../temp/".$ps_fil."x.pdf\"\n");
				system ("$exec_path/pdftk ../temp/$ps_fil.pdf background $bg_fil output $out");
				fwrite($log,__line__." system (\"$exec_path/pdftk ../temp/$ps_fil.pdf background $bg_fil output $out\")\n");
				unlink ("../temp/$ps_fil.pdf");
				fwrite($log,__line__." unlink (\"../temp/$ps_fil.pdf\")\n");
				system  ("mv $out ../temp/$ps_fil.pdf");
				fwrite($log,__line__." system  (\"mv $out ../temp/$ps_fil.pdf\")\n");
			}
			print "--> \n";
			
			if ($localPrint == 'on') {
				$qtxt="select id,box1 from grupper where art='PV'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($r['box1']=='on') { 
					$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
					if ($fp=fopen($filnavn,'r')) {
						$ip=trim(fgets($fp));
						fclose ($fp);
					} else $ip=NULL;
				}
			}
			if ($localPrint == 'on' && $ip) {
				$url="://".$_SERVER['SERVER_NAME'].=$_SERVER['PHP_SELF'];
				$url=str_replace("/includes/udskriv.php","",$url);
				if ($_SERVER['HTTPS']) $url="s".$url;
				$url="http".$url;
				if ($art=='PO') $returside=$url."/debitor/pos_ordre.php";
				else $returside=$url."/debitor/ordre.php";
				$url.="/temp/$ps_fil";
				$printfil=end(explode('/', $ps_fil));
				$url=str_replace($printfil,'',$url);
				$qtxt="select firmanavn,fakturanr from ordrer where id=$id";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$firmanavn=htmlentities($r['firmanavn']);
				$fakturanr=htmlentities($r['fakturanr']);
				print "<meta http-equiv=\"refresh\" content=\"0;URL=http://$ip/localprint.php?printfil=$printfil.pdf&url=$url&id=$id&returside=$returside&bruger_id=$bruger_id&firmanavn=$firmanavn&fakturanr=$fakturanr\">\n";
				exit;
			} elseif ($valg=='ip') {
				print "<!--!";
				system("lpr -P $ip ../temp/$ps_fil.pdf &");
				print "--> \n";
				if ($art=='PO') print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/pos_ordre.php?id=$id\">";
				else print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordre.php?id=$id\">";
				exit;
			} elseif ($udskriv_til=='fil') {
				$r=db_fetch_array(db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordreliste.php?gem_id=$id&gem=../temp/$ps_fil.pdf&download=$r[kundeordnr]_$r[firmanavn].pdf\">";
				exit;
			}

			global $menu;

			include("../includes/topline_settings.php");

			if ($menu == 'S') {
				print "<table width=100% height=100%><tbody>";

				if ($returside) {
					if (substr($art,0,1)=='K'){ 
					   $href="\"../kreditor/ordre.php?tjek=$id&id=$id&returside=$returside\" accesskey=\"L\"";
				    }elseif ($art == 'DO' && (strpos($returside, "ordreliste.php") !== false)) {
						$href = "../debitor/ordreliste.php";
					} else {
						$href = "../debitor/ordre.php?tjek=$id&id=$id&returside=$returside\" accesskey=\"L\"";
					}
				} else {
					$href = "udskriv.php?valg=tilbage&id=$id&art=$art\" accesskey=\"L\"";
				}
				print "<td width='10%'><a href=$href>
					   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">$ordre_antal ".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

				print "<td width='80%' align='center' title='".findtekst('2179|Klik her for at åbne filen i nyt vindue, højreklik her for at gemme', $sprog_id)."'>
					   <a href=../temp/$ps_fil.pdf target=blank>
					   <button style='$topStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('2180|Vis PDF udskrift', $sprog_id)."</button></a></td>";

				print "<td width='10%' align='right' style='$topStyle'></td>";
				print "<tr><td width=100% height=99% align='center' valign='middle' colspan='3'><iframe frameborder='0' width='100%' height='100%' scrolling='auto' src='../temp/$ps_fil.pdf'></iframe></td></tr>";
				print "</tbody></table>";

			} else {
				print "<table width=100% height=100%><tbody>";
				if ($returside) $href="\"$returside\" accesskey=\"L\"";
				else $href="\"udskriv.php?valg=tilbage&id=$id&art=$art\" accesskey=\"L\"";
				print "<td width=\"10%\" height=\"1%\" $top_bund><a href=$href>$ordre_antal ".findtekst('2172|Luk', $sprog_id)."</a></td>";
				print "<td width=\"80%\" $top_bund align=\"center\" title=\"".findtekst('2179|Klik her for at åbne filen i nyt vindue, højreklik her for at gemme', $sprog_id).">";
				print "<a href=../temp/$ps_fil.pdf target=blank>".findtekst('2180|Vis PDF udskrift', $sprog_id)."</a>";
				print "</td>";
				print "<td width=\"10%\" $top_bund align = \"right\"></td>";
				print "<tr><td width=100% height=99% align=\"center\" valign=\"middle\" colspan=\"3\"><iframe frameborder=\"0\" width=\"100%\" height=\"100%\" scrolling=\"auto\" src=\"../temp/$ps_fil.pdf\"></iframe></td></tr>";
				print "</tbody></table>";
			}
			print exit;

		} else print "<BODY onLoad=\"javascript:alert('PDF-fil ikke fundet - er PS2PDF installeret?')\">";
	}
  if ($valg=="printer") {
    system ("$r[box1] ../temp/$ps_fil");
    print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
    exit;
  }
	if ($valg==-1)	$ps_fil="formularprint.php?id=$id&formular=$formular";
	else {
		$ip=$_SERVER['REMOTE_ADDR'];
		print "<!--!";
		system("lpq -P $ip", $tjek);
		print "-->";
		if ($tjek) $ip=NULL;
		$ps_fil="formularprint.php?id=$id&formular=3&udskriv_til=printer&ip=$ip";
	}

  fclose ($log);
  #xit;
}

global $menu;

include ("../includes/topline_settings.php");

if ($menu == 'S') {
	print "<table width=\"100%\" height=\"75%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height=\"1%\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\"><tbody>";

	print "<td width=\"10%\"><a href=\"udskriv.php?valg=tilbage\" accesskey=\"L\">
		   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">Luk</button></a></td>";

	print "<td width=\"80%\" align=\"center\" style='$topStyle'>Udskriftsvalg</td>";

	print "<td width=\"10%\" align = \"right\" style='$topStyle'><br></td>";

	print "</tbody></table>";
	print "</td></tr>";
	print "<tr><td height=\"99%\" align = center valign = middle>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

	print "<tr><td align=center><a href='udskriv.php?valg=pdf&ps_fil=$ps_fil'>
		   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">PDF</button></a></td></tr>";

	print "<tr><td align=center><a href='udskriv.php?valg=printer&ps_fil=$ps_fil'>
		   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">Printer</button></a></td></tr>";

	print "</tbody></table></td>";
	print "</tbody></table>";

} else {
	print "<table width=\"100%\" height=\"75%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height=\"1%\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=\"udskriv.php?valg=tilbage\" accesskey=\"L\">Luk</a></td>";
	print "<td width=\"80%\" $top_bund align=\"center\">Udskriftsvalg</td>";
	print "<td width=\"10%\" $top_bund align = \"right\"><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<tr><td height=\"99%\" align = center valign = middle>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	print "<tr><td align=center> <a href='udskriv.php?valg=pdf&ps_fil=$ps_fil'>PDF</a></td></tr>";
	print "<tr><td align=center> <a href='udskriv.php?valg=printer&ps_fil=$ps_fil'>Printer</a></td></tr>";
	print "</tbody></table></td>";
	print "</tbody></table>";
}

exit;

function historik($id,$filnavn) {
global $db;
global $bruger_id;
global $sprog_id;
global $exec_path;

	$r=db_fetch_array(db_select("select var_value from settings where var_name='ps2pdf'",__FILE__ . " linje " . __LINE__));
	if ($r['var_value']) $ps2pdf=$r['var_value'];
	else $ps2pdf="$exec_path/ps2pdf";

	if (!file_exists("$filnavn")) {
	print "<BODY onLoad=\"javascript:alert('indl&aelig;sning af $filnavn fejlet')\">";
		return ('indl&aelig;sning af $filnavn fejlet');
	}
	$dd=date("Y-m-d");
	$r=db_fetch_array(db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$kundeordnr=$r['kundeordnr'];
	$konto_id=$r['konto_id'];
	$kontakt=$r['kontakt'];
	$ref=$r['ref'];
	$status=$r['status'];
	$art=$r['art'];

	if (!$status) $notat = findtekst(488,$sprog_id);
	elseif ($status==1 || $status==2) $notat = findtekst(489,$sprog_id);
	elseif ($art=='DO') $notat = findtekst(498,$sprog_id);
	else $notat = findtekst(499,$sprog_id);
	$tidspkt=date("H:i");
	$notat=str_replace('$time',$tidspkt,$notat);

	$r=db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	$egen_id=$r['id']*1;

	$r=db_fetch_array(db_select("select * from ansatte where konto_id='$egen_id' and navn = '$ref'",__FILE__ . " linje " . __LINE__));
	$ansat_id=$r['id']*1;
	
	$r=db_fetch_array(db_select("select * from ansatte where konto_id='$konto_id' and navn = '$kontakt'",__FILE__ . " linje " . __LINE__));
	$kontakt_id=$r['id']*1;

	db_modify("insert into historik(konto_id,kontakt_id,ansat_id,notat,notedate,kontaktet) values ('$konto_id','$kontakt_id','$ansat_id','$notat','$dd','$dd')",__FILE__ . " linje " . __LINE__);
	$r=db_fetch_array(db_select("select id from historik where konto_id='$konto_id' and kontakt_id='$kontakt_id' and ansat_id='$ansat_id' and notat='$notat' and notedate='$dd'",__FILE__ . " linje " . __LINE__));
	$bilag_id=$r['id'];

	$r=db_fetch_array(db_select("select * from grupper where art='FTP'",__FILE__ . " linje " . __LINE__));
	$box1=$r['box1'];
	$box2=$r['box2'];
	$box3=$r['box3'];
	$mappe=$r['box5'];
	$undermappe="debitor_$konto_id";
	$ftpfilnavn="doc_".$bilag_id;

	$fp=fopen("../temp/$db/ftpscript.$bruger_id","w");
	if ($fp) {
		fwrite ($fp,"mkdir $mappe\ncd $mappe\nmkdir $undermappe\ncd $undermappe\nput $ftpfilnavn\nbye\n");
	}
	fclose($fp);
	$pdfnavn=$ftpfilnavn.".pdf";
	$kommando="cd \"../temp/$db\"\nrm \"$ftpfilnavn\"\nmv \"../$filnavn\" \"$ftpfilnavn\"\n$ps2pdf \"$ftpfilnavn\"\n rm \"$ftpfilnavn\"\nmv \"$pdfnavn\" \"$ftpfilnavn\"\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id > ftplog\nrm $ftpfilnavn\n";#rm ftpscript.$bruger_id";
	system ($kommando);
		$fp=fopen("../temp/$db/ftpscript.$bruger_id","w");
		if ($fp) {
			fwrite ($fp, "cd $mappe\ncd $undermappe\nget $ftpfilnavn\nbye\n");
		}
		fclose($fp);
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id >> ftplog\n";
		system ($kommando);
		$langt_filnavn="../temp/$db/".$ftpfilnavn;
		if (file_exists($langt_filnavn)) {
			$tmp=explode("/",$filnavn);
			$filnavn=($tmp[count($tmp)-1]);
			$filnavn.=".pdf";
			$filnavn=db_escape_string($filnavn);
			db_modify("update historik set dokument='$filnavn' where id='$bilag_id'",__FILE__ . " linje " . __LINE__);
			$alerttekst=findtekst(490,$sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
		} else {
			$alerttekst=findtekst(506,$sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
		}
}
?>
