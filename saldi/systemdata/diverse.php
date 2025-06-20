<?php
// -------------------- systemdata/diverse.php ------ patch 3.6.2 -- 2016-01-16 --
//
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
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
// Copyright (c) 2003-2016 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2012.09.20 Tilføjet integration med ebconnect
// 2013.01.19 funktioner lagt i selvstændig fil (../includes/sys_div_func.php)
// 2013.05.23 varelaterede rettet til varerelaterede valg.
// 2013.12.10	Tilføjet valg om kort er betalingskort som aktiver betalingsterminal. Søg 21031210
// 2013.12.13	Tilføjet "intern" bilagsopbevaring (box6 under ftp)
// 2014.01.29	Tilføjet valg til automatisk genkendelse af betalingskort (kun ved integreret betalingsterminal) Søg 20140129
// 2014.04.29	Ændret teksten så siden er mere overskuelig. Claus Agerskov ca@saldi.dk
// 2014.05.08	Tilføjet valg til bordhåndtering under pos_valg Søg 20140508
// 2014.06.16 Tilføjet mellemkonto til pos kasser. Søg mellemkonto.
// 2014.07.01	FTP ændret til bilag og intern bilagsopbevaring flyttet til owncloud
// 2015.04.11	Tilføjet labelprint.Søg label
// 20150424 CA  Ændret link til funktionsfilen sys_div_func.php    Søg 20150424a
// 20150424 CA  Ændret link til funktionsfilen konv_lager.php      Søg 20150424b
// 20150424 CA  Benytter funktionen skriv_formtabel til formularer Søg 20150424c
// 20150612 CA  Databasehåndtering af prislister (ej afsluttet)    Søg 20150612
// 20150907 PHR Sætpriser tilføjet under ordre_valg, Søg 20150907 & $saetvareid
// 20151006 PHR Labelprint ændret fra php til html og kontrol for php indsat.
// 20160116 PHR Indsat kontrol for ftp adgang v ebconnect integration

@session_start();
$s_id=session_id();
$title="Diverse Indstillinger";
$modulnr=1;
$css="../css/standard.css";

$diffkto=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("sys_div_func.php"); # 20150424a
include("skriv_formtabel.inc.php"); # 20150424c

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"></div>\n";
#	print "<span class=\"headerTxt\">Systemsetup</span>\n";     
#	print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=../debitor/debitorkort.php;title=debitor\" class=\"button green small right\">Ny debitor</a>--></div>";       
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_div_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontent maincontentborder\">\n";
} else include("top.php");

if (!isset($exec_path)) $exec_path="/usr/bin";

$sektion=if_isset($_GET['sektion']);
$skiftnavn=if_isset($_GET['skiftnavn']);

if ($_POST) {
	if ($sektion=='provision') {
		$id=$_POST['id'];
		$beskrivelse=$_POST['beskrivelse'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3'];
		$box4=$_POST['box4'];
		if (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='1'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
		db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2, box3, box4) values ('Provisionsrapport', '1', 'DIV', '$box1', '$box2', '$box3', '$box4')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) db_modify("update grupper set  box1 = '$box1', box2 = '$box2', box3 = '$box3' , box4 = '$box4' where id = '$id'",__FILE__ . " linje " . __LINE__);
	#######################################################################################
	} elseif ($sektion=='personlige_valg') {
		$refresh_opener=NULL;
		$id=$_POST['id'];
		$jsvars=$_POST['jsvars'];
		if ($popup && $_POST['popup']=='') $refresh_opener="on";
		$popup=$_POST['popup'];
		$menu=$_POST['menu'];
		if ($menu=="sidemenu") $menu='S';
		elseif ($menu=="topmenu") $menu='T';
		else $menu='';
		$bgcolor="#".$_POST['bgcolor'];
		$nuance=$_POST['nuance'];
		if  (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'USET' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5) values ('Personlige valg','$bruger_id','USET','$jsvars','$popup','$menu','$bgcolor','$nuance')",__FILE__ . " linje " . __LINE__);
		} elseif ($id>0) db_modify("update grupper set box1='$jsvars',box2='$popup',box3='$menu',box4='$bgcolor',box5='$nuance' where id = '$id'",__FILE__ . " linje " . __LINE__);
		if ($refresh_opener) {
			print "<BODY onLoad=\"javascript:opener.location.reload();\">";
		}
	#######################################################################################
	} elseif ($sektion=='div_valg') {
		$id=$_POST['id']*1;
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3']; #ledig
		$box4=$_POST['box4']; #ledig
		$box5=$_POST['box5']; #ledig
		$box6=$_POST['box6'];
		$box7=$_POST['box7'];
		$box8=$_POST['box8']; #ebconnect
		$box9=$_POST['box9']; #ledig
		$box10=$_POST['box10'];

		if ($box8) {
			ftptest($_POST['oiourl'],$_POST['oiobruger'],$_POST['oiokode']);
			$box8=$_POST['oiourl'].chr(9).$_POST['oiobruger'].chr(9).$_POST['oiokode'];
		}
		if (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='2'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		if ($id==0){
		db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('Div_valg','2','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion=='ordre_valg') {
		$box1=$_POST['box1'];#incl_moms
		$box2=$_POST['box2'];#Rabatvarenr
		$box3=$_POST['box3'];#folge_s_tekst
		$box4=$_POST['box4'];#hurtigfakt
		$box5=$_POST['box5'];#straks_bogf
		$box6=$_POST['box6'];#fifo
		$box7=$_POST['box7'];#
		$box8=$_POST['box8'];#vis_nul_lev
		$box9=$_POST['box9'];#negativt_lager
		$box10=$_POST['box10'];#
		$box11=$_POST['box11'];#advar_lav_beh
		$box12=$_POST['box12'];#$procentfakt
		$box13=$_POST['procenttillag'].chr(9).$_POST['procentvare'];
		$box14=$_POST['box14'];
		$rabatvarenr=$_POST['rabatvarenr'];
		$kostmetode=$_POST['kostmetode'];
		$saetvarenr=$_POST['saetvarenr']; #20150907

		if ($box2 && $r=db_fetch_array(db_select("select id from varer where varenr = '$box2'",__FILE__ . " linje " . __LINE__))) {
			$box2=$r['id'];
		} elseif ($box2) {
				$txt = str_replace('XXXXX',$box2,findtekst(289,$sprog_id));
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		if ($box14 && !$box2) {
			$txt="Samlet pris forudsætter at der er et varenr til rabat";
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			$box14='';
		}
		#20150907 ->
		if ($saetvarenr && $r=db_fetch_array(db_select("select id from varer where varenr = '$saetvarenr'",__FILE__ . " linje " . __LINE__))) {
			$saetvareid=$r['id'];
		}
		if ($saetvarenr && !$saetvareid) { 
			$txt="Varenummer for sæt eksisterer ikke";
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		# <- 20150907
		if  ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__))) {
			$id=$r['id'];
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12',box13='$box13',box14='$box14' where id = '$id'",__FILE__ . " linje " . __LINE__);
		} else {
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14) values ('Div_valg (Ordrer)','3','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10','$box11','$box12','$box13','$box14')",__FILE__ . " linje " . __LINE__);
		}		
		if ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='5'",__FILE__ . " linje " . __LINE__))) {
			$id=$r['id'];
			db_modify("update grupper set box6='$kostmetode',box8='$saetvareid' where id = '$id'",__FILE__ . " linje " . __LINE__);
		} else {
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13) values ('Div_valg','5','DIV','','','','','','','$kostmetode','','','','','','')",__FILE__ . " linje " . __LINE__);
		} 
	#######################################################################################
	} elseif ($sektion=='vare_valg') {
		$id=$_POST['id'];
		$box1=if_isset($_POST['box1']);#incl_moms
		$box2=if_isset($_POST['box2']);#Shop url
		$box3=if_isset($_POST['box3']);#shop valg
		$box4=if_isset($_POST['box4']);#merchant id
		$box5=if_isset($_POST['box5']);#md5 secret
		$box6=if_isset($_POST['box6']);#Bruges ved vare_valg
		$box7=if_isset($_POST['box7']);#Tegnsæt for webshop
		$box8=if_isset($_POST['box8']);#Bruges ved ordre_valg
		$box9=if_isset($_POST['box9']);#ledig
		$box10=if_isset($_POST['box10']);#ledig
		
		if ($box3=='1') $box2='!';
		if ($box6) {
			if ($r=db_fetch_array(db_select("select id from grupper where art = 'VG' and box1 != box2",__FILE__ . " linje " . __LINE__))) {
				$txt = findtekst(733,$sprog_id);
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../systemdata/konv_lager.php\">\n"; # 20140424b
				exit;
			}
		}
		
		if  (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='5'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('Div_valg (Varer)','5','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box7='$box7',box9='$box9',box10='$box10' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion=='varianter') {
		$id=if_isset($_POST['id']);
		$variant_beskrivelse=if_isset($_POST['variant_beskrivelse']);
		$variant_id=if_isset($_POST['variant_id']);
		$var_type_beskrivelse=if_isset($_POST['var_type_beskrivelse']);
		$variant_antal=if_isset($_POST['variant_antal']);
		$rename_varianter=if_isset($_POST['rename_varianter']);
		$rename_var_type=if_isset($_POST['rename_var_type']);
		if ($rename_var_type) {
			db_modify("update variant_typer set  beskrivelse='$var_type_beskrivelse' where id = '$rename_var_type'",__FILE__ . " linje " . __LINE__);
		} elseif ($rename_varianter) {
			db_modify("update varianter set  beskrivelse='$variant_beskrivelse' where id = '$rename_varianter'",__FILE__ . " linje " . __LINE__);
		}	elseif ($variant_beskrivelse) db_modify("insert into varianter (beskrivelse) values ('$variant_beskrivelse')",__FILE__ . " linje " . __LINE__);
		for ($x=1;$x<=$variant_antal;$x++) {
			if ($var_type_beskrivelse[$x] && $variant_id[$x]) db_modify("insert into variant_typer (beskrivelse,variant_id) values ('$var_type_beskrivelse[$x]','$variant_id[$x]')",__FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion=='label') {
		$label=if_isset($_POST['label']);
		$fy_ord=array('<?php','<?','?>');
		for ($x=0;$x<count($fy_ord);$x++) {
			if (strstr($label,$fy_ord[$x])) {
				$label=str_replace($fy_ord[$x],'',$label);
				print "<BODY onLoad=\"JavaScript:alert('Illegal værdi i labeltekst')\">";
			}
		}
		$r = db_fetch_array(db_select("select id from grupper where art = 'LABEL'",__FILE__ . " linje " . __LINE__));
		$id=$r['id'];
		if ($id) {
			db_modify("update grupper set  box1='".db_escape_string($label)."' where id = '$id'",__FILE__ . " linje " . __LINE__);
		} else {
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1) values ('Label layout','1','LABEL','".db_escape_string($label)."')",__FILE__ . " linje " . __LINE__);
		} 
	#######################################################################################
	} elseif ($sektion=='prislister') {
		$id=$_POST['id'];
		$beskrivelse=$_POST['beskrivelse'];
		$box1=$_POST['lev_id'];
		$box2=$_POST['prisfil'];
		$box3=$_POST['opdateret'];
		$box4=$_POST['aktiv'];
		$box5=$_POST['rabatter'];
		$box6=$_POST['rabat'];
		$box7=$_POST['grupper'];
		$box8=$_POST['gruppe'];
		$box9=$_POST['filtype'];
		$slet=$_POST['slet'];
		$antal=$_POST['antal'];

		for($x=1;$x<=$antal;$x++) {
			if (!$box4[$x]) $box1[$x]='';

			$id[$x]*=1;
echo "\n<p> id[$x] = $id[$x] box2[$x] = $box2[$x]   box4[$x] = $box4[$x] slet[$x] = $slet[$x] </p>";
			if ($id[$x]==0 && $box4[$x] && $r = db_fetch_array(db_select("select id from grupper where art='PL' and beskrivelse='$beskrivelse[$x]'",__FILE__ . " linje " . __LINE__))) {
				$id[$x]=$r['id'];
			} elseif ( $id[$x]==0 && $box4[$x] && $beskrivelse[$x] ) {
				$box4[$x]=0; # 20150612
				db_modify("insert into grupper (beskrivelse,kodenr,art,box2,box4,box6,box8,box9) values ('$beskrivelse[$x]','0','PL','$box2[$x]','$box4[$x]','$box6[$x]','$box8[$x]','$box9[$x]')",__FILE__ . " linje " . __LINE__);
			} elseif ( $id[$x] && $slet[$x]=="Slet" ) {
				db_modify("delete from grupper where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
				$slet[$x]=$slet[$x];
			} elseif ($id[$x] > 0) {
				db_modify("update grupper set beskrivelse='$beskrivelse[$x]',box1='$box1[$x]',box2='$box2[$x]',box4='$box4[$x]',box6='$box6[$x]',box8='$box8[$x]',box9='$box9[$x]' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
	#######################################################################################
	} elseif ($sektion=='rykker_valg') {
		$id=if_isset($_POST['id']);
		$box1=if_isset($_POST['box1']);
		$box2=if_isset($_POST['box2']);
		$box3=if_isset($_POST['box3']);
		$box4=if_isset($_POST['box4']);
		$box5=if_isset($_POST['box5']);
		$box6=if_isset($_POST['box6']);
		$box7=if_isset($_POST['box7']);
		# $box8 er reserveret til dato for sidst afsendte mail.


		if ($box1) {
			$r = db_fetch_array(db_select("select id from brugere where brugernavn = '$box1'",__FILE__ . " linje " . __LINE__));
			$box1=$r['id'];
		}
		if  (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='4'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif ($id==0){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('Div_valg (Rykker)','4','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','','','')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion=='pos_valg') {
		$id1=if_isset($_POST['id1'])*1;
		$box1=if_isset($_POST['kasseantal'])*1;
		$afd_nr=if_isset($_POST['afd_nr']);
		$kassekonti=if_isset($_POST['kassekonti']);
		$box4=if_isset($_POST['kortantal'])*1;
		$korttyper=if_isset($_POST['korttyper']);
		$kortkonti=if_isset($_POST['kortkonti']);
		$moms_nr=if_isset($_POST['moms_nr']);
		$rabatvarenr=if_isset($_POST['rabatvarenr']);
		$box9=if_isset($_POST['straksbogfor']);
		$box10=if_isset($_POST['udskriv_bon']);
		$box11=if_isset($_POST['vis_kontoopslag']);
		$box12=if_isset($_POST['vis_hurtigknap']);
		$box13=if_isset($_POST['timeout']);
		$box14=if_isset($_POST['vis_indbetaling']);

		$id2=if_isset($_POST['id2'])*1;
		$kasseprimo=if_isset($_POST['kasseprimo']);
		$kasseprimo=usdecimal($kasseprimo)*1;
		$optalassist=if_isset($_POST['optalassist']);
		$printer_ip=if_isset($_POST['printer_ip']);
		$terminal_ip=if_isset($_POST['terminal_ip']);
		$koekkenprinter=if_isset($_POST['koekkenprinter']);
		$betalingskort=if_isset($_POST['betalingskort']); #20131210
		$div_kort_kto=if_isset($_POST['div_kort_kto']); #20140129
		$bordantal=if_isset($_POST['bordantal']); #20140508
		$bord=if_isset($_POST['bord']); #20140508
		$mellemkonti=if_isset($_POST['mellemkonti']);
		$diffkonti=if_isset($_POST['diffkonti']);
		$varenr=if_isset($_POST['varenr']);
		$vis_saet=if_isset($_POST['vis_saet']);
		
		$box2=NULL;
		$box3=NULL;
		$box7=NULL;
		$box8=NULL;
		$box3_2=NULL;
		$box4_2=NULL;
		$box11_2=NULL;
		for ($x=0;$x<$box1;$x++) {
			if (!($kassekonti[$x] && is_numeric($kassekonti[$x]) && db_fetch_array(db_select("select id from kontoplan where kontonr = '$kassekonti[$x]'",__FILE__ . " linje " . __LINE__)))) {
				if ($kassekonti[$x]) $txt=str_replace("<variable>",$kassekonti[$x],findtekst(277,$sprog_id));
				else $txt = findtekst(278,$sprog_id);
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			}
			$txt='';
			if (!($mellemkonti[$x] && is_numeric($mellemkonti[$x]) && db_fetch_array(db_select("select id from kontoplan where kontonr = '$mellemkonti[$x]'",__FILE__ . " linje " . __LINE__)))) {
				if ($mellemkonti[$x]) $txt=str_replace("<variable>",$mellemkonti[$x],findtekst(717,$sprog_id));
				if ($mellemkonti[$x]) $txt=str_replace("<variable>",$diffkonti[$x],findtekst(723,$sprog_id));
				# else $txt = findtekst(718,$sprog_id);
				if ($txt) print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			}
			if ($box2) {
				$box2.=chr(9).$kassekonti[$x];
				$box3.=chr(9).$afd_nr[$x];
				$box7.=chr(9).$moms_nr[$x];
				$box3_2.=chr(9).$printer_ip[$x];	
				$box4_2.=chr(9).$terminal_ip[$x];	
				$box8_2.=chr(9).$mellemkonti[$x];	
				$box9_2.=chr(9).$diffkonti[$x];	
				$box10_2.=chr(9).$koekkenprinter[$x];	
			} else {
				$box2=$kassekonti[$x];
				$box3=$afd_nr[$x];
				$box7=$moms_nr[$x];
				$box3_2.=$printer_ip[$x];	
				$box4_2.=$terminal_ip[$x];	
				$box8_2.=$mellemkonti[$x];	
				$box9_2.=$diffkonti[$x];	
				$box10_2.=$koekkenprinter[$x];	
			}
		}
		$box5=NULL;
		$box6=NULL;
		for ($x=0;$x<$box4;$x++) {
			if ($korttyper[$x]) {
				$kortkonti[$x]*=1;
				if (!db_fetch_array(db_select("select id from kontoplan where kontonr = '$kortkonti[$x]'",__FILE__ . " linje " . __LINE__))) {
					if ($kortkonti[$x]) $txt=str_replace("<variable>",$kortkonti[$x],findtekst(277,$sprog_id));
					else $txt = findtekst(278,$sprog_id);
					print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
				}
				if ($box5) {
					$box5.=chr(9).$korttyper[$x];
					$box6.=chr(9).$kortkonti[$x];
					$box5_2.=chr(9).$betalingskort[$x];	 #20121210
				} else {
					$box5=$korttyper[$x];
					$box6=$kortkonti[$x];
					$box5_2.=$betalingskort[$x];	#20121210
				}
			}
		}
		$box7_2=NULL;
		for ($x=0;$x<$bordantal;$x++) { #20140508
			$tmp=$x+1;
			if (!$bord[$x])$bord[$x]="Bord ".$tmp;
			($box7_2)?$box7_2.=chr(9).$bord[$x]:$box7_2=$bord[$x];
		}
		if ($varenr && $r=db_fetch_array(db_select("select id from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__))) {
			$box11_2=$r['id'];
		} elseif ($varenr) {
				$txt = str_replace('XXXXX',$varenr,findtekst(289,$sprog_id));
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		if ($rabatvarenr && $r=db_fetch_array(db_select("select id from varer where varenr = '$rabatvarenr'",__FILE__ . " linje " . __LINE__))) {
			$box8=$r['id'];
		} elseif ($rabatvarenr) {
				$txt = str_replace('XXXXX',$rabatvarenr,findtekst(289,$sprog_id));
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		if  (($id1==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'POS' and kodenr='1'",__FILE__ . " linje " . __LINE__)))) $id1=$r['id'];
		elseif ($id1==0){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14) values ('POS_valg','1','POS','$box1','$box2','$box3','$box4','$box5','$box6','$box7','','$box9','$box10','$box11','$box12','$box13','$box14')",__FILE__ . " linje " . __LINE__);
		} elseif ($id1 > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12',box13='$box13',box14='$box14' where id = '$id1'",__FILE__ . " linje " . __LINE__);
		}
		if ($id2) db_modify("update grupper set box1='$kasseprimo',box2='$optalassist',box3='$box3_2',box4='$box4_2',box5='$box5_2',box6='$div_kort_kto',box7='$box7_2',box8='$box8_2',box9='$box9_2',box10='$box10_2',box11='$box11_2',box12='$vis_saet' where id = '$id2'",__FILE__ . " linje " . __LINE__);

		#######################################################################################
	} elseif ($sektion=='docubizz') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3'];
		$box4=$_POST['box4'];
		$box5=$_POST['box5'];

		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DocBiz'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5) values ('DocuBizz','1','DocBiz','$box1','$box2','$box3','$box4','$box5')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	} elseif ($sektion=='upload_dbz') {
		include("docubizzexport.php");
		$r = db_fetch_array(db_select("select * from grupper where art = 'DocBiz'",__FILE__ . " linje " . __LINE__));
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$r['box2'].":".$r['box3']."@".$r['box1']."/".$r['box5']." < ftpscript > NULL ";
		system ($kommando);
		print "<BODY onLoad=\"JavaScript:alert('Data sendt til DocuBizz')\">";
#######################################################################################
	} elseif ($sektion=='bilag') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3'];
		$box4=$_POST['box4'];
		$box5=$_POST['box5'];
		($_POST['box6']=='intern_ftp')?$box6='on':$box6=NULL;
		$box7=$_POST['box7'];
#		if ($box6) {
#			include("../includes/connect.php");
#			$r=db_fetch_array(db_select("select * from diverse where beskrivelse='FTP' and nr='1'"));
#			$box1=$r['box1'];
#			$box2=$r['box2'];
#			$box3=$r['box3'];
#			$box4=$r['box4'];
#			$box5=$r['box5'];
#			include("../includes/online.php");
#		}
		if ($box1 && substr($box1,-1)!="/") $box1.="/";
		if ($box6 && $box1 && !strpos($_SERVER['SERVER_NAME'],$box1)) $box1.=$_SERVER['SERVER_NAME']."/";
		if ($box6 && $box1 && !strpos($db,$box1)) $box1.=$db."/";
		if ($box3=='********') {
			$r=db_fetch_array(db_select("select box3 from grupper where art = 'bilag'",__FILE__ . " linje " . __LINE__));
			$box3=$r['box3'];
		}
		if (!$box6 && $box1 && $box2 && $box4 && $box5) testftp($box1,$box2,$box3,$box4,$box5,$box6);
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper where art = 'bilag'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7) values ('Bilag og dokumenter','1','bilag','$box1','$box2','$box3','$box4','$box5','$box6','$box7')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			if ($box6) {
				db_modify("update grupper set box6='$box6',box7='$box7' where id = '$id'",__FILE__ . " linje " . __LINE__);
			} else {
				db_modify("update grupper set box1='$box1',box2='$box2',box4='$box4',box5='$box5',box6='$box6' where id = '$id'",__FILE__ . " linje " . __LINE__);
				include("../includes/connect.php");
				db_modify("update regnskab set bilag='0' where id = '$db_id'",__FILE__ . " linje " . __LINE__);
				include("../includes/online.php");
				if ($box3!='********') db_modify("update grupper set  box3='$box3' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}
		}
		if ($box6) {
			include("../includes/connect.php");
			db_modify("update regnskab set bilag='1' where id = '$db_id'",__FILE__ . " linje " . __LINE__);
			include("../includes/online.php");
		}
#######################################################################################
	} elseif ($sektion=='email') {
		$id=$_POST['id'];
		$box1=db_escape_string($_POST['box1']);
		$box2=db_escape_string($_POST['box2']);
		$box3=db_escape_string($_POST['box3']);
		$box4=db_escape_string($_POST['box4']);
		$box5=db_escape_string($_POST['box5']);
		$box6=db_escape_string($_POST['box6']);
		$box7=db_escape_string($_POST['box7']);
		$box8=db_escape_string($_POST['box8']);
		$box9=db_escape_string($_POST['box9']);
		$box10=db_escape_string($_POST['box10']);

		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper where art = 'MAIL' and kodenr = '1'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('e-mail tekster','1','MAIL','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
#######################################################################################
	} elseif ($sektion=='orediff') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2']*1;
		if ($box1) $box1=usdecimal($box1);
		if ($box2 && !db_fetch_array(db_select("select id from kontoplan where kontonr = '$box2' and kontotype = 'D' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))){
			$tekst=findtekst(175,$sprog_id);
			print "<BODY onLoad=\"JavaScript:alert('$tekst')\">";
			$diffkto=$box2;
			$box2='';
		}
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2) values ('Oredifferencer','1','OreDif','$box1','$box2')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
######################################################################################
	} elseif ($sektion=='massefakt') {
		$id=$_POST['id'];
		$brug_mfakt=$_POST['brug_mfakt'];
		if ($brug_mfakt) {
			$brug_dellev=$_POST['brug_dellev'];
			$levfrist=$_POST['levfrist'];
		} else {
			$brug_dellev=NULL;
			$levfrist=0;
		}
		if  ((!$id) && ($r = db_fetch_array(db_select("select id from grupper where art = 'MFAKT'",__FILE__ . " linje " . __LINE__)))) $id=$r['id'];
		elseif (!$id){
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3) values ('Massefakturering','1','MFAKT','$brug_mfakt','$brug_dellev','$levfrist')",__FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$brug_mfakt',box2='$brug_dellev',box3='$levfrist' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
######################################################################################
	} elseif ($sektion=='kontoplan_io') {
		if (strstr($_POST['submit'])=="Eksport") {
			list($tmp)=explode(":",$_POST['regnskabsaar']);
			print "<BODY onLoad=\"javascript:exporter_kontoplan=window.open('exporter_kontoplan.php?aar=$tmp','lager','scrollbars=yes,resizable=yes,dependent=yes');exporter_kontoplan.focus();\">";
		}
		elseif (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:importer_kontoplan=window.open('importer_kontoplan.php','kontoplan','scrollbars=yes,resizable=yes,dependent=yes');importer_kontoplan.focus();\">";
		}
	} elseif ($sektion=='adresser_io') {
		if (strstr($_POST['submit'])=="Eksport") {
			print "<BODY onLoad=\"javascript:exporter_debitor=window.open('exporter_debitor.php?aar=$tmp','debitor','scrollbars=yes,resizable=yes,dependent=yes');exporter_debitor.focus();\">";
		}
		elseif (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:importer_debitor=window.open('importer_debitor.php','debitor','scrollbars=yes,resizable=yes,dependent=yes');importer_debitor.focus();\">";
		}
	} elseif ($sektion=='varer_io') {
		if (strstr($_POST['submit'])=="Eksport") {
			print "<BODY onLoad=\"javascript:exporter_debitor=window.open('exporter_debitor.php?aar=$tmp','debitor','scrollbars=yes,resizable=yes,dependent=yes');exporter_debitor.focus();\">";
		}
		elseif (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:importer_varer=window.open('importer_varer.php','importer_varer','scrollbars=yes,resizable=yes,dependent=yes');importer_varer.focus();\">";
		}
	} elseif ($sektion=='solar_io') {
		if (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:solarvvs=window.open('solarvvs.php','solarvvs','scrollbars=yes,resizable=yes,dependent=yes');solarvvs.focus();\">";
		}
	} elseif ($sektion=='formular_io') {
		if (strstr($_POST['submit'])=="Eksport") {
			print "<BODY onLoad=\"javascript:exporter_formular=window.open('exporter_formular.php','exporter_formular','scrollbars=yes,resizable=yes,dependent=yes');exporter_formular.focus();\">";
		}
		elseif (strstr($_POST['submit'])=="Import") {
			print "<BODY onLoad=\"javascript:importer_formular=window.open('importer_formular.php','importer_formular','scrollbars=yes,resizable=yes,dependent=yes');importer_formular.focus();\">";
		}
	}elseif ($sektion=='sqlquery_io') {
		$sqlstreng=if_isset($_POST['sqlstreng']);
	} elseif ($sektion=='kontoindstillinger') {
		if (strstr($_POST[submit],'Skift')) {
			$nyt_navn=trim(db_escape_string($_POST['nyt_navn']));
			include("../includes/connect.php");
			if (db_fetch_array(db_select("select id from regnskab where regnskab = '$nyt_navn'",__FILE__ . " linje " . __LINE__))) {
				print "<BODY onLoad=\"JavaScript:alert('Der findes allerede et regnskab med navnet $nyt_navn! Navn ikke &aelig;ndret')\">";
			} else {
				$r=db_fetch_array(db_select("select id from kundedata where regnskab_id = '$db_id'"));
				if (!$r['id']){
					$tmp=db_escape_string($regnskab);
					db_modify("update kundedata set regnskab_id = '$db_id' where regnskab='$tmp'",__FILE__ . " linje " . __LINE__);
				}
				db_modify("update regnskab set regnskab = '$nyt_navn' where db='$db'",__FILE__ . " linje " . __LINE__);

			}
			include("../includes/online.php");
		}
	} elseif ($sektion=='smtp') {
		$smtp=trim(db_escape_string($_POST['smtp']));
		db_modify("update adresser set felt_1 = '$smtp' where art='S'",__FILE__ . " linje " . __LINE__);
		$sektion='kontoindstillinger';
	
	} elseif ($sektion=='tjekliste') {
		$id=if_isset($_POST['id']);
		$tjekantal=if_isset($_POST['tjekantal']);
		$fase=if_isset($_POST['fase']);
		$ny_fase=if_isset($_POST['ny_fase']);
		$ny_tjekgruppe=if_isset($_POST['ny_tjekgruppe']);
		$tjekpunkt=if_isset($_POST['tjekpunkt']);
		$nyt_tjekpunkt=if_isset($_POST['nyt_tjekpunkt']);
		$liste_id=if_isset($_POST['liste_id']);
		$gruppe_id=if_isset($_POST['gruppe_id']);
		$ret=if_isset($_POST['ret']);

		if ($ny_tjekliste=$_POST['ny_tjekliste']) {
			$r = db_fetch_array($q = db_select("select max(fase) as fase from tjekliste where assign_to = 'sager'",__FILE__ . " linje " . __LINE__));
			$nf=$r['fase']+1;
#cho "A insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekliste','0','sager','$ny_fase')<br>";
			db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekliste','0','sager','$nf')",__FILE__ . " linje " . __LINE__);
		}
		for ($x=1;$x<=$tjekantal;$x++) {	
			if ($ny_fase[$x]) $nf=$ny_fase[$x]+.1;
			if ($fase[$x]!=$nf) db_modify("update tjekliste set fase='$nf' where id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
			if ($ret && $ret==$id[$x] && $tjekpunkt[$x]) db_modify("update tjekliste set tjekpunkt='$tjekpunkt[$x]' where id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
			if (isset($ny_tjekgruppe[$x]) && $ny_tjekgruppe[$x]) {
#cho "B insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekgruppe[$x]','$liste_id[$x]','sager','$fase[$x]')";
				db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekgruppe[$x]','$liste_id[$x]','sager','$fase[$x]')",__FILE__ . " linje " . __LINE__);
			}
			if (isset($nyt_tjekpunkt[$x]) && $nyt_tjekpunkt[$x]) {
#cho "B insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$nyt_tjekpunkt[$x]','$gruppe_id[$x]','sager','$fase[$x]')";
				db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$nyt_tjekpunkt[$x]','$gruppe_id[$x]','sager','$fase[$x]')",__FILE__ . " linje " . __LINE__);
			}
		}

		if ($ny_tjekgruppe=$_POST['ny_tjekgruppe']) {
			
			$r = db_fetch_array($q = db_select("select max(fase) from tjekliste where assign_to = 'sager'",__FILE__ . " linje " . __LINE__));
			$ny_fase=$r['fase'];
			if ($ny_fase || $ny_fase=='0') $ny_fase++; 
			else $ny_fase=0;
#cho "B insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekgruppe','0','sager','$ny_fase')";
#			db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekgruppe','0','sager','$ny_fase')",__FILE__ . " linje " . __LINE__);
		}
	}
} 



if(db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box6='on'",__FILE__ . " linje " . __LINE__))) $docubizz='on';

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" height=\"100%\"><tbody>";

if ($menu != 'T') {
	print "<td width=\"170px\" valign=\"top\">";
	print "<table cellpadding=\"2\" cellspacing=\"2\" border=\"0\" width=\"100%\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\"><br></td></tr>";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=kontoindstillinger>Kontoindstillinger</a></td></tr>\n";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=provision>Provisionsberegning</a>&nbsp;</td></tr>\n";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=personlige_valg>Personlige valg</a></td></tr>\n";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=ordre_valg>Ordrerelaterede valg</a></td></tr>\n";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=vare_valg>Varerelaterede valg</a></td></tr>\n";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=prislister>".findtekst(427,$sprog_id)."</a><!--tekst 427--></td></tr>\n";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=rykker_valg>Rykkerrelaterede valg</a></td></tr>\n";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=div_valg>Diverse valg</a></td></tr>\n";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=tjekliste>Tjeklister</a></td></tr>\n";
	if ($docubizz) print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=docubizz>DocuBizz</a></td></tr>\n";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=bilag>Bilagshåndtering</a></td></tr>\n";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=orediff>".findtekst(170,$sprog_id)."</a><!--tekst 170--></td></tr>\n";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=massefakt>".findtekst(200,$sprog_id)."</a><!--tekst 200--></td></tr>\n";
	if (file_exists("../debitor/pos_ordre.php")) print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=pos_valg>".findtekst(271,$sprog_id)."</a><!--tekst 271--></td></tr>\n";
	# print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=email>Mail indstillinger</a></td></tr>";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=sprog>Sprog</a></td></tr>\n";
	# print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=kontoplan_io>Indl&aelig;s  / udl&aelig;s kontoplan</a></td></tr>";
	print "<tr><td align=left $top_bund>&nbsp;<a href=diverse.php?sektion=div_io>Import &amp; eksport</a></td></tr>\n";
	print "</tbody></table></td><td valign=\"top\" align=\"left\"><table align=\"left\" valign=\"top\" border=\"0\" width=\"90%\"><tbody>\n";
}
if (!$sektion) print "<td><br></td>";
if ($sektion=="kontoindstillinger") kontoindstillinger($regnskab,$skiftnavn);
if ($sektion=="provision") provision();
if ($sektion=="personlige_valg") personlige_valg();
if ($sektion=="ordre_valg") ordre_valg();
if ($sektion=="vare_valg" || $sektion=="varianter" || $sektion=="label") vare_valg();
if ($sektion=="prislister") prislister();
if ($sektion=="rykker_valg") rykker_valg();
if ($sektion=="div_valg") div_valg();
if ($sektion=="docubizz") docubizz();
if ($sektion=="bilag") bilag();
if ($sektion=="orediff") orediff($diffkto);
if ($sektion=="massefakt") massefakt();
if ($sektion=="pos_valg") pos_valg();
if ($sektion=="sprog") sprog();
if ($sektion=="tjekliste") tjekliste();
if (strpos($sektion,"_io")) {
	kontoplan_io();
	formular_io();
	adresser_io();
	varer_io();
	variantvarer_io();
	sqlquery_io($sqlstreng);
}

print "</tbody></table></td></tr>";
if ($menu=='T') print "</div>";
#print "</form>";
#print "</tbody></table></td></tr>";

?>
</tbody></table>
</body></html>
