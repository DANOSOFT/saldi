<?php
// ------------- debitor/pos_ordre.php ---------- lap 3.4.3----2014.06.13-------
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
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
//
// 2013-03-10 - Tilføjet mulighed for at give rabat på varer uden pris ved at skrive "r" efter prisen. Søg 20130310
// 2013.05.07 - Tilføjet visning af kostpris v. mus over pris.
// 2013.08.24 - Rettet i bogføringsrutine for funktion posbogfor så optælling af primosaldo kun fra start af aktivt regnskabsår,
//	alternativt henter kasseprimo fra grupper (sættes under diverse > POS valg).
// 2013.08.24	-	Rettet i funktion kassebeholdning så salg på kort nu ikke tæller fra alle kassers salg. (Tilføjet kasse_nr i transaktioner)
// 2013.08.27	-	Fejl i funktion pos_txt_print "dkdecimal($dkkmodtaget2)" rettet til dkdecimal($modtaget2)
// 2013.10.15	-	Manglende momsberegning v. mængderabat. Søg	20131015
// 2013.12.05 -	En række tilretning vedr kasseintegration og afrunding til hele 50 ører. 
// 2013.12.10	- Betalingsterminal aktiver3es kun hvis kort er afmærket som betalingskort under diverse/pos_valg. Søg 20131210
// 2014.01.29 - Indsat automatisk genkendelse af registrerede betalingskort, (Kun med integreret betalingsterminal) Søg 20140129, $kortnavn eller 'Betalingskort'
// 2014.03.18 - Omdøbt funktion opret_ordre + div. kald til opret_posordre grundet konflikt med opret_ordre fra sagssystem
// 2014.04.26 - Indsat vare id foran varenr i kald til opret_ordrelinje grundet ændring i funktionen (PHR - Danosoft) Søg 20140426 
// 2014.05.08 - Indsat diverse til bordhåndtering, bruger nr fra ordrer til bordnummer (PHR - Danosoft) Søg 20140508 eller $bordnr 
// 2014.05.26 - Indsat hentning af momssats fra ordre hvis den ikke er sat inden indsættelse af ordrelinje (PHR - Danosoft) Søg 20140526
// 2014.05.27 - Flyttet hentning af momssats til under "opret_posordre" (PHR - Danosoft) Søg 20140527
// 2014.06.03 - Tilføjet afd_navn til skærmtekst samme med kassenr (PHR - Danosoft) Søg 20140603
// 2014.06.10	-	Tilføjet bordplan (PHR - Danosoft) - søg bordplan.
// 2014.06.12	-	Lidt designændringer på bordplan (PHR - Danosoft) - søg bordplan.
// 2014.06.12	-	Bordknapper disables hvis der er indsat varenummer i inputfelt fra pos knap eller vareopslag (PHR - Danosoft) - søg disabled og bord.
// 2014.06.13 - Bogføring blev ikke afbrudt ved diff i posteringssum. 20140613
// 2014.06.13 - Ordre kunne ikke afsluttes med ørediff aktiv. 20140613
// 2014.06.13 - Div småting relateret til pos_ordrer - bl. a. momsdiff ved salg til kr. 27,12 og betaling med Dankort +100. 20140613+$retur
// 2014.06.16 - Mange ændringer i kasseoptælling og afslutings funktion. Bla. bogføring af kasse -> bank
// 2014.06.24 - Mulighed for at redigere i i pos linjer. (PHR - Danosoft) - søg $ret og _old.


@session_start();
$s_id=session_id();
ob_start();

$modulnr=5;
$title="POS_ordre";
$css="../css/pos.css";
$betaling=NULL; $betaling2=NULL; $konto_id=NULL; $next_varenr=NULL;$vis_kassenr=NULL;
$fokus="varenr_ny";
#$printserver="localhost";
$valuta='DKK';$valutakurs='100';


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

// Projekt kan knytttes til menu, f.eks dag og aften så man kan trække en rapport på hvor man har sin indtjening. 
// Projektet knyttes til varen så det både kan være dag og aften på samme bon. 
$projekt=NULL;
$tid=date("H:i");
$r=db_fetch_array(db_select("select box9 from grupper where art='POSBUT' and (box7 < box8) and (box7<'$tid' and box8>'$tid')",__FILE__ . " linje " . __LINE__));
$projekt=$r['box9'];
if (!$projekt) {
	$r=db_fetch_array(db_select("select box9 from grupper where art='POSBUT' and (box7 > box8) and ((box7>'$tid' and box8>'$tid') or (box7<'$tid' and box8<'$tid'))",__FILE__ . " linje " . __LINE__));
	$projekt=$r['box9'];
}
if (isset($_GET['flyt_til']) && isset($_GET['id'])) { #20140508
	$bordnr=$_GET['flyt_til'];
	$id=$_GET['id'];
	$delflyt=if_isset($_GET['delflyt']);
	if ($delflyt) {
		if($r=db_fetch_array(db_select("select id from ordrer where art='PO' and status < '3' and nr = '$bordnr'",__FILE__ . " linje " . __LINE__))){
			$id=$r['id'];
			} else $id=opret_posordre(NULL,$kasse);
			$tmparray=explode(",",$delflyt);
		for ($x=0;$x<count($tmparray);$x++) {
			db_modify("update ordrelinjer set ordre_id='$id' where id='$tmparray[$x]'",__FILE__ . " linje " . __LINE__);
		}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
		# exit;
	} else db_modify("update ordrer set nr='$bordnr' where id='$id'",__FILE__ . " linje " . __LINE__);
} elseif (isset($_GET['bordnr'])) {
	if ($bordnr=$_GET['bordnr']) {
		$r=db_fetch_array(db_select("select id from ordrer where art='PO' and status < '3' and nr = '$bordnr'",__FILE__ . " linje " . __LINE__));
		$id=$r['id'];
	}
}

$r=db_fetch_array(db_select("select box2 from grupper where art='OreDif'",__FILE__ . " linje " . __LINE__));
$difkto=$r['box2'];

$returside=(if_isset($_GET['returside']));
if (!$returside) {
	if ($popup) $returside="../includes/luk.php";
	else $returside="../index/menu.php";
}
print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>\n";
$luk=(if_isset($_GET['luk']));
if ($luk) {
	if ($kundedisplay) kundedisplay('****   Lukket   ****','',1);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
}

$kasse = if_isset($_GET['kasse']);
$menu_id = if_isset($_GET['menu_id']);
$kassebeholdning = if_isset($_GET['kassebeholdning']);
if ($kasse && $kassebeholdning) {
	if (isset($_POST['optael']) && ($_POST['optael']=='Godkend' || $_POST['optael']=='Beregn')) {
		$cookievalue=$_POST['ore_50'].chr(9).$_POST['kr_1'].chr(9).$_POST['kr_2'].chr(9).$_POST['kr_5'].chr(9).$_POST['kr_10'].chr(9).$_POST['kr_20'].chr(9).$_POST['kr_50'].chr(9).$_POST['kr_100'].chr(9).$_POST['kr_200'].chr(9).$_POST['kr_500'].chr(9).$_POST['kr_1000'].chr(9).usdecimal($_POST['kr_andet']);
		setcookie("saldi_kasseoptael", $cookievalue,time()+3600);
		$optalt=$_POST['ore_50']*0.5+$_POST['kr_1']+$_POST['kr_2']*2+$_POST['kr_5']*5+$_POST['kr_10']*10+$_POST['kr_20']*20+$_POST['kr_50']*50+$_POST['kr_100']*100+$_POST['kr_200']*200+$_POST['kr_500']*500+$_POST['kr_1000']*1000+usdecimal($_POST['kr_andet']); 
		($_POST['optael']=='Godkend')?$godkendt=1:$godkendt=0;
		kassebeholdning($kasse,$optalt,$godkendt,$cookievalue);
	} elseif (!isset($_POST['optael'])) {
		kassebeholdning($kasse,0,0,'');
	}
}
if (!$kasse || $kasse == "?") $kasse=find_kasse($kasse);
elseif ($kasse=="opdat") {
	$kasse=$_POST['kasse'];
	setcookie("saldi_pos",$kasse,time()+60*60*24*30);
}
ob_end_flush();
$godkendt=if_isset($_GET['godkendt']); # 20131205
if ($godkendt=='OK') { # 20131205
	$id=if_isset($_GET['id']);
	$betaling=if_isset($_GET['betaling']);
	$betaling2=if_isset($_GET['betaling2']);
	$modtaget=if_isset($_GET['modtaget']);
	$modtaget2=if_isset($_GET['modtaget2']);
	$indbetaling=if_isset($_GET['indbetaling']);
	$kortnavn=if_isset($_GET['kortnavn']); #20140129
	#cho "afslut($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling,$godkendt)<br>";
	if ($godkendt) afslut($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling,$godkendt,$kortnavn); #20140129 Tilføjet $kortnavn
}
$bon=trim(strtoupper(if_isset($_POST['bon'])));
$tilbage=if_isset($_POST['tilbage']);
if ($tilbage && $kundedisplay) 	kundedisplay('','','1');
if ($bon=='S') {
	$r=db_fetch_array(db_select("select max(id) as id from ordrer where felt_5='$kasse'",__FILE__ . " linje " . __LINE__));
	if ($id=$r['id'])	$r=db_fetch_array(db_select("select fakturanr,nr from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$bon=trim($r['fakturanr']);
	$bordnr=$r['nr']*1; #20140508
} elseif ($bon) {
	$r=db_fetch_array(db_select("select id,nr from ordrer where fakturanr = '$bon' and art = 'PO'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$bordnr=$r['nr']*1; #20140508
} else {
	$id = if_isset($_GET['id'])*1;
	$r=db_fetch_array(db_select("select nr from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$bordnr=$r['nr'];
}
$vare_id = if_isset($_GET['vare_id'])*1;
$vare_id_ny = if_isset($_GET['vare_id_ny'])*1;
if ($vare_id_ny && !$vare_id) {
	$vare_id=$vare_id_ny;
} elseif ($vare_id_ny && $vare_id) {
	if (!$id) $id=opret_posordre(NULL,$kasse);
	if (!isset($momssats)) { #20140526
		$r=db_fetch_array(db_select("select momssats from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$momssats=$r['momssats'];
	}
	$r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
#cho "A opret_ordrelinje($id,$vare_id,$r[varenr],1,'',$r[pris_ny],0,100,'PO','','','0','on','','','0')<br>\n";
	$linje_id=opret_ordrelinje($id,$vare_id,$r['varenr'],1,'',$r['pris_ny'],0,100,'PO','','','0','on','','','0'); #20140426
	$vare_id=$vare_id_ny;
	if ($kundedisplay) kundedisplay($r['beskrivelse'],$r['salgspris'],0);
}
$funktion = if_isset($_GET['funktion']);
if ($funktion) {
	$sort = if_isset($_GET['sort'])*1;
	$funktion ('PO',$sort,$fokus, $id,"","","");
}
$spec_func = if_isset($_GET['spec_func']);
if ($spec_func) {
	$kode = if_isset($_POST['kode']);
	include("../includes/spec_func.php");
	$svar=$spec_func('xx',$id,$kode);
	if (!is_numeric($svar)) {
		print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
	}	else $konto_id=$svar;
}
 #20140508 ->
if (isset($_GET['bordnr'])) $bordnr=$_GET['bordnr']*1; 
if (isset($_POST['bordnr'])) $bordnr=$_POST['bordnr']*1;
if (isset($_POST['pre_bordnr'])) $pre_bordnr=$_POST['pre_bordnr']*1;
if ($pre_bordnr && $bordnr && $pre_bordnr != $bordnr) {
	$r=db_fetch_array(db_select("select max(id) as id from ordrer where nr='$bordnr'",__FILE__ . " linje " . __LINE__));
	$id=$r['id']*1;
}
if (isset($_POST['flyt_bord'])) {
	if (file_exists("../bordplaner/bordplan_$db_id.php"))  print "<meta http-equiv=\"refresh\" content=\"0;URL=../bordplaner/bordplan_$db_id.php?id=$id&flyt=$bordnr\">\n";
	else flyt_bord($id,$bordnr,'');
}
$df=if_isset($_POST['delflyt']);
$l_id=if_isset($_POST['linje_id']);
$delflyt=NULL;
for ($x=1;$x<=count($l_id);$x++) {
	if ($df[$x]){
		($delflyt)?$delflyt.=",".$l_id[$x]:$delflyt=$l_id[$x];	
	}
}
if ($delflyt) {
	if (file_exists("../bordplaner/bordplan_$db_id.php"))  print "<meta http-equiv=\"refresh\" content=\"0;URL=../bordplaner/bordplan_$db_id.php?id=$id&flyt=$bordnr&delflyt=$delflyt\">\n";
	else flyt_bord($id,$bordnr,$delflyt);
}
#$del_bord=if_isset($_POST['del_bord']);
#cho "del_bord $del_bord<br>";
# <- 20140508
$kontonr = if_isset($_POST['kontonr'])*1;
if (!$konto_id) $konto_id = if_isset($_GET['konto_id'])*1;
if ($konto_id || $kontonr) {
	$id=opdater_konto($konto_id,$kontonr,$id);
	$r=db_fetch_array(db_select("select momssats,sum,betalt,betalingsbet from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$betalingsbet=$r['betalingsbet'];
	$momssats=$r['momssats']*1;
	if ($betalingsbet!='Kontant') $modtaget=$r['betalt']*1;
	$sum=$r['sum']*1;
	$betaling='ukendt';
#	if ($modtaget <= $sum) $id=afslut($id,'konto',$modtaget);
#	else $betaling='ukendt';
}
#cho "PS $printserver<br>\n";
if ($vare_id) {
	$r=db_fetch_array(db_select("select varenr from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
	$varenr_ny=$r['varenr'];
} elseif (sizeof($_POST)>1) {
	$afslut=if_isset($_POST['afslut']);
	$ny_bruger=if_isset($_POST['ny_bruger']);
	$kode=if_isset($_POST['kode']);
	$indbetaling=if_isset($_POST['indbetaling']);
	$sum=if_isset($_POST['sum']);
	$afrundet=if_isset($_POST['afrundet']);
#cho "sum $sum<br>";
	$betaling=if_isset($_POST['betaling']);
	$betaling2=if_isset($_POST['betaling2']);
	$kontonr=if_isset($_POST['kontonr']);
	$modtaget=if_isset($_POST['modtaget']);
	$modtaget2=if_isset($_POST['modtaget2']);
	$kundeordnr=if_isset($_POST['kundeordnr']);
	$fokus=if_isset($_POST['fokus']);
 	$pris_ny=if_isset($_POST['pris_ny']);
 	if (!$pris_ny && if_isset($_POST['pris_old'])) $pris_ny=$_POST['pris_old'];
 	$antal_ny=strtolower(trim(if_isset($_POST['antal_ny'])));
 	if (if_isset($_POST['antal'])) { #20140623
		if (!$antal_ny && $antal_ny!='0') $antal_ny=$_POST['antal'];
		elseif ($antal_ny=='p' || $antal_ny=='r' || $antal_ny=='a') $antal_ny=$_POST['antal'].$antal_ny;
		$fokus='antal_ny';
	}   
	$varenr_ny=db_escape_string(trim(if_isset($_POST['varenr_ny'])));
	$beskrivelse_ny=db_escape_string(trim(if_isset($_POST['beskrivelse_ny'])));
	$momssats=(if_isset($_POST['momssats']));
	$rabat_ny=if_isset($_POST['rabat_ny']);
	if (!$rabat_ny && if_isset($_POST['rabat_old'])) $rabat_ny=$_POST['rabat_old']; 
	if (substr($betaling,0,9) == "Kontant p") {
		$antal_ny=1;
		if ($id && $varenr_ny) {
			$r=db_fetch_array(db_select("select id,salgspris,beskrivelse from varer where varenr = '$varenr_ny'",__FILE__ . " linje " . __LINE__));
			#cho "B opret_ordrelinje($id,$r[id],$varenr_ny,1,'',$r[pris_ny],0,100,'PO','','','0','on','','','0')<br>\n";
			$linje_id=opret_ordrelinje($id,$r['id'],$varenr_ny,1,'',$r['pris_ny'],0,100,'PO','','','0','on','','','0'); #20140226
			$varenr_ny=NULL;
			if ($kundedisplay) kundedisplay($r['beskrivelse'],$r['pris_ny'],0);
		}
	}
	if (strtolower($antal_ny)=='a') {
		$antal_ny=1;
		$afslut=NULL;
	}
	$sum*=1;
	#cho "update ordrer set kundeordnr = '$kundeordnr',sum='$sum', betalt='$betalt',felt_1='$betaling',felt_2='$modtaget',felt_3='$betaling2',felt_4='$modtaget2',felt_5='$kasse' where id='$id'<br>\n";
	if ($kundeordnr && $id) db_modify("update ordrer set kundeordnr = '$kundeordnr' where id='$id'",__FILE__ . " linje " . __LINE__);

#cho "betalt=$betalt fok $fokus<br>\n";
	if (strstr($pris_ny,",")) { #Skaerer orebelob ned til 2 cifre.
		list($kr,$ore)=explode(",",$pris_ny);
		$ore=substr($ore,0,2);
		$pris_ny=$kr.",".$ore;
	}
	if(isset($_POST['ny']) && $_POST['ny'] == "Ny kunde") {
		$id=0;
		$kontonr=0;
		$menu_id=NULL;
		$bon=NULL;
	}
	if (!$id && !$varenr_ny && $kundedisplay) kundedisplay('**** Velkommen ****','','1');
	if(isset($_POST['udskriv']) && $_POST['udskriv'] == "Udskriv") {
		$momssats=$momssats*1;
#		include("../includes/formfunk.php");
		pos_txt_print($id,$betaling,$modtaget,$indbetaling);
	}
	if(isset($_POST['krediter'])) {
		$ny_id=krediter_pos($id);
	  print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$ny_id\">\n";
	}
	if ($fokus=="antal_ny" && $antal_ny!='0' && !$pris_ny) $antal_ny.="p";
	if ($fokus=="pris_ny" && $pris_ny!='f' && substr($pris_ny,-1)!='r') $fokus="antal_ny"; #20130310 tilføjet: "&& substr($pris_ny,-1)!='r'" samt 2 næste linjer
	if ($fokus=="pris_ny" && $pris_ny!='f' && substr($pris_ny,-1)=='r') { 
		$pris_ny=str_replace("r","",$pris_ny);
		$fokus='rabat_ny';
	} elseif ($fokus=="rabat_ny" && $pris_ny!='f') $fokus="antal_ny";
	if ($fokus=="antal_ny" && (substr($antal_ny,-1)=='p' || substr($antal_ny,-1)=='r')) {
		if (substr($antal_ny,-1)=='p') $fokus='pris_ny';
		else $fokus='rabat_ny';
		if (strlen($antal_ny)>1) $antal_ny=substr($antal_ny,0,strlen($antal_ny)-1);
		else $antal_ny=1;
	} elseif ($fokus=="varenr_ny" && $varenr_ny=='v') {
		vareopslag('PO',"",$fokus, $id,"","","");
	} elseif ($fokus=="pris_ny" && substr($pris_ny,-1)=='r') {
		$pris_ny=substr($pris_ny,0,strlen($pris_ny)-1);
		$fokus="rabat_ny";
	} elseif (isset($_POST['forfra']) && $id) {
		$r=db_fetch_array(db_select("select status from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$status=$r['status'];
		if ($status<3) {
			$bordnr*=1;
			$dd=date("Y-m-d");
			db_modify ("update ordrer set konto_id='0', kontonr='',firmanavn='',addr1='',addr2='',postnr='',bynavn='',land='',betalingsdage='0',betalingsbet='Kontant',
				cvrnr='',ean='',institution='',email='',kontakt='',art='PO',valuta='DKK',valutakurs='100',kundeordnr='',nr='0',ordredate='$dd' where id = '$id'",__FILE__ . " linje " . __LINE__);
			db_modify("delete from ordrelinjer where ordre_id='$id'",__FILE__ . " linje " . __LINE__);
			$varenr_ny=''; $antal_ny=''; $modtaget=''; $betaling=''; $indbetaling=''; $fokus="varenr_ny";
		}
		if ($kundedisplay) kundedisplay('','','1');
	} elseif (substr($modtaget,-1)=='t') $betaling="";
#	elseif (substr($modtaget,-1)=='d' && !$betaling) $betaling="creditcard";
	elseif (substr($modtaget,-1)=='c' && !$betaling) $betaling="kontant";
	elseif (substr($modtaget,-1)=='k' || $betaling == "konto") {
		if (substr($modtaget,0,1)=='+') $modtaget=$sum+usdecimal(substr($modtaget,1,strlen($modtaget)-1));
		elseif (!is_numeric(substr($modtaget,-1))) $modtaget=substr($modtaget,0,strlen($modtaget)-1);
		if (!$modtaget || !$kontonr) kontoopslag('PO',"",$fokus, $id,"","","");
	} elseif (isset($_POST['kontoopslag'])) {
		kontoopslag('PO',"","varenr_ny",$id,"","","","","","","");
	}
#cho "A $antal_ny P $pris_ny<br>";
	if ($indbetaling) {
			$indbetaling=str_replace("a","",$indbetaling);
			$tmp=trim(str_replace(".","",$indbetaling));
			$tmp=str_replace(",",".",$tmp);
#cho "$tmp $indbetaling $modtaget<br>\n"; 
			if (is_numeric($tmp)) {
				$indbetaling=usdecimal($indbetaling);
				$modtaget=usdecimal($modtaget);
			if ($indbetaling>$modtaget) {
				print "<BODY onLoad=\"javascript:alert('Indbetaling kan ikke v&aelig;re større end beløbet der modtages')\">\n";
				$indbetaling=$modtaget;
			}
		}
#cho "$tmp $indbetaling $modtaget<br>\n"; 
#exit;
	}	elseif ($betaling) {
		if (substr($modtaget,0,1)=='+') $modtaget=$sum+usdecimal(substr($modtaget,1,strlen($modtaget)-1));
		elseif (!is_numeric(substr($modtaget,-1))) $modtaget=usdecimal(substr($modtaget,0,strlen($modtaget)-1));
		else $modtaget=usdecimal($modtaget);
		$modtaget=$modtaget*1;
		if (!$modtaget) {
			if ($betaling=='Kontant') $modtaget=pos_afrund($sum,$difkto);
			else $modtaget=$sum;
		}
		if (substr($modtaget2,0,1)=='+') $modtaget2=$sum+usdecimal(substr($modtaget2,1,strlen($modtaget2)-1));
		elseif (!is_numeric(substr($modtaget2,-1))) $modtaget2=usdecimal(substr($modtaget2,0,strlen($modtaget2)-1));
		else $modtaget2=usdecimal($modtaget2);
		$modtaget2=$modtaget2*1;
#		if (!$modtaget2) $modtaget2=$sum;
	}
	$betalt=$modtaget+$modtaget2;
	if (($betalt && is_numeric($betalt))||(!$sum && $afslut=="Afslut")) {
		if (!$sum && $afslut=="Afslut"){
			$betaling="kontant";
		}
		$afslut="OK";
		if (!is_numeric($sum)) $afslut=NULL;

		if  ($betaling == 'Kontant' && $betalt < pos_afrund($sum,$difkto) && !$indbetaling) $afslut=NULL;
		elseif ($betaling == 'Konto' && $betalingsbet == 'Kontant' && $betalt < pos_afrund($sum,$difkto) && !$indbetaling) $afslut=NULL;
		elseif ($betaling != 'Kontant' && $betalt < $sum && !$indbetaling) $afslut=NULL; # 20130613 Indsat $betaling != 'Kontant'		
		if (!$betaling)  $afslut=NULL;
		if (substr($betaling,0,9)=="Kontant p") $afslut=NULL;
		if ($betaling=="ukendt") $afslut=NULL;
		if ($betaling2 && $betaling2=="ukendt") $afslut=NULL;
		if ($modtaget2 && (!$betaling2 || $betaling2=="ukendt")) $afslut=NULL;
		if ($afslut=="OK") {
			 $svar=afslut($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling,NULL,NULL);
			if ($svar) print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
 			else {
			  print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">\n";
			}
		}
	} else {
		$tmp=str_replace(",",".",$antal_ny);
		if ($varenr_ny == "a") {
			$betaling="ukendt";
			$varenr_ny=NULL;
		} elseif ($antal_ny == "a") {
			$betaling="ukendt";
			$antal_ny=1;
		} elseif ($antal_ny && !is_numeric($tmp) || $tmp>99999) { # Så er der skannet et varenummer ind som antal
				$next_varenr=$antal_ny;
				$antal_ny=1;
		} elseif ($fokus=="antal_ny") {
			if ($antal_ny=="0") $varenr_ny = NULL;
			elseif (!strlen($antal_ny)) $antal_ny=1;
			else $antal_ny=usdecimal($antal_ny);
		} elseif ($antal_ny=="0" && if_isset($_POST['antal'])) $varenr_ny = NULL; #20140623
 		if ($varenr_ny && $antal_ny && $fokus!="pris_ny" && $fokus!="rabat_ny") {
			if (!$id) {
				$id=opret_posordre(NULL,$kasse);
			}
			if ($id && !is_numeric($id)) {
				print "<BODY onLoad=\"javascript:alert('$id')\">\n";
			} else {
#cho "264  $id,$varenr_ny,$antal_ny,'',usdecimal($pris_ny),$rabat_ny<br>\n";
#cho "C $id,'',$varenr_ny,$antal_ny,'',usdecimal($pris_ny),$rabat_ny,100,'PO','','','0','on','','','0'<br>";
				$linje_id=opret_ordrelinje($id,'',$varenr_ny,$antal_ny,'',usdecimal($pris_ny),$rabat_ny,100,'PO','','','0','on','','','0'); #20140226
				if ($linje_id && !is_numeric($linje_id)) {
					print "<BODY onLoad=\"javascript:alert('$linje_id')\">\n";
					$fokus="pris_ny";
				} else {
					$varenr_ny=$next_varenr;
					$antal_ny=NULL;
		#			$sum=0;
				}
				if ($kundedisplay) kundedisplay($beskrivelse_ny,$pris_ny,0);
			}
		} elseif ($varenr_ny) $sum=find_pris($varenr_ny);
#		else $sum=0;
	}
}
############################
$x=0;

if (!$id) {
	$dd=date("Y-m-d");
	$vis_kassenr=1;
	if ($bordnr) $qtxt="select max(id) as id from ordrer where status < '3' and art = 'PO' and nr='$bordnr'";  #20140508
	else $qtxt="select max(id) as id from ordrer where status < '3' and art = 'PO' and ref = '$brugernavn' and ordredate = '$dd'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($id=$r['id']*1) {  #20140508
		$r=db_fetch_array(db_select("select nr from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		$bordnr=$r['nr']*1; 
	}
}
if ($ny_bruger && $ny_bruger!=$brugernavn) skift_bruger($ny_bruger,$kode);
if (!isset($momssats)) $momssats=find_momssats($id,$kasse);
print "<table width=\"100%\" height=\"100%\" border=\"1\" cellpadding=\"0\" cellspacing=\"0\"><tbody>\n";
print "<tr><td valign=\"top\" width=50%><table width=\"100%\"><tbody>\n";
print "<form name=pos_ordre action=\"pos_ordre.php?id=$id&menu_id=$menu_id&bordnr=$bordnr&del_bord=$del_bord\" method=post autocomplete=\"off\">\n";
if ($id && $betaling) $sum=betaling($id,$momssats,$betaling,$betaling2,$modtaget,$modtaget2);
elseif (!$indbetaling) {
list($varenr_ny,$pris_ny,$status)=explode(chr(9),varescan($id,$momssats,$varenr_ny,$antal_ny,$pris_ny,$rabat_ny));
} else indbetaling($id,$indbetaling,$modtaget);
if (substr($betaling,0,9) == "Kontant p") {
	$betaling='Kontant';
	$modtaget=$sum;
	$svar=afslut($id,$betaling,NULL,$modtaget,0,NULL,NULL,NULL);
	if ($svar) print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
	else print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
}
if ($varenr_ny=='fejl') fejl($id,"$status");
if ($vis_kassenr) {
	$kasse=trim($kasse);
	$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
	$kasseantal=$r['box1']*1;
	$afd=explode(chr(9),$r['box3']);
	$tmp=$kasse-1;
	$r = db_fetch_array(db_select("select * from grupper where art = 'AFD' and kodenr='$afd[$tmp]'",__FILE__ . " linje " . __LINE__));
	$afd=$r['beskrivelse'];
	if ($afd) print "<tr><td height=\"40%\" colspan=\"4\" align=\"center\" valign=\"middle\"><b><div style=\"font-size:25mm;color:$bgcolor2;\">$kasse<br>$afd</div></b></td></tr>\n";
	else print "<tr><td height=\"40%\" colspan=\"4\" align=\"center\" valign=\"middle\"><b><div style=\"font-size:95mm;color:$bgcolor2;\">$kasse</div></b></td></tr>\n";
}
print "</tbody></table></td>\n";
print "<td valign=\"top\"><table width=\"100%\" border=\"0\"><tbody><td align=\"center\">\n";
print "<tr><td colspan=\"2\" valign=\"top\" height=\"1%\"><table width=\"100%\" border=\"0\"><tbody>\n";
hoved($kasse);
# kassebeholdning($kasse);
print "</tbody></table></td></tr>\n";
#print "</FORM><form name=tastatur action=pos_ordre.php?id=$id method=post>\n";
tastatur($status);
print "</FORM>\n";
print "</tbody></table></td></tr>\n";
print "<tr><td colspan=\"2\" valign=\"top\" height=\"1%\" align=\"center\"><table width=\"100%\" border=\"0\"><tbody>\n";
if ($status<3) menubuttons($id,$menu_id,$vare_id);
print "</td></tbody></table></td></tr>\n";
print "</td></tbody></table></td></tr>\n";
#print "<tr><td colspan=2 width=\"100%\" height=\"1%\"><table width=\"100%\" height=\"100%\" border=\"0\"><tbody>\n";
#footer ($kasse);
#print "</tbody></table></td></tr>\n";
# print "</tbody></table></td>\n";


function afslut($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling,$godkendt,$kortnavn) {
print "\n<!-- Function afslut (start)-->\n";
#exit;
#cho "$id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling<br>";
	global $charset;
	global $bruger_id;
	global $kasse;
	global $regnaar;
	global $retur;
#	global $printserver;
	$tmp=array();
	$betalingskort=array();
	if ($godkendt!='OK') { #20131205
		$r = db_fetch_array(db_select("select box3,box4,box5,box6 from grupper where art = 'POS' and kodenr='2'",__FILE__ . " linje " . __LINE__)); 
		$x=$kasse-1;
		$tmp=explode(chr(9),$r['box3']);
		$printserver=trim($tmp[$x]);
		$tmp=explode(chr(9),$r['box4']);
		$terminal_ip=trim($tmp[$x]);
		$betalingskort=explode(chr(9),$r['box5']);
		$div_kort_kto=trim($r['box6']);
		if ($terminal_ip) { # 20131210  div ændringer i rutine
			$r = db_fetch_array(db_select("select box4,box5 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
			$kortantal=$r['box4']*1;
			$korttyper=explode(chr(9),$r['box5']);
			if ($div_kort_kto) {
				$betalingskort[$kortantal]='on';
				$korttyper[$kortantal]='Betalingskort';
				$kortantal++;
			}
			if (in_array($betaling,$korttyper) || in_array($betaling2,$korttyper)) {
				$amount=0;
				for($x=0;$x<$kortantal;$x++) {
					if ($betaling==$korttyper[$x] && $betalingskort[$x] && !$amount) $amount=$modtaget;
					elseif ($betaling==$korttyper[$x] && $betalingskort[$x] && $amount) return ("Der kan ikke betales med 2 betalingskort");
					if ($betaling2==$korttyper[$x] && $betalingskort[$x] && !$amount) $amount=$modtaget2;
					elseif ($betaling2==$korttyper[$x] && $betalingskort[$x] && $amount) return ("Der kan ikke betales med 2 betalingskort");
				}
			}
			if ($amount) {
				if (!$printserver) $printserver='localhost';
				$belob=dkdecimal($amount);
				$belob=str_replace(".","",$belob);
				if ($_SERVER['HTTPS']) $url='https://';
				else $url='http://';
				$url.=$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
				
				print "<meta http-equiv=\"refresh\" content=\"0;URL=http://$printserver/pointd/kvittering.php?url=$url&id=$id&kommando=kortbetaling&belob=$belob&betaling=$betaling&betaling2=$betaling2&modtaget=$modtaget&modtaget2=$modtaget2&indbetaling=$indbetaling\">\n";
				exit;
			}
		}
	} elseif ($kortnavn) { #20140129
		$r = db_fetch_array(db_select("select box3,box4,box5,box6 from grupper where art = 'POS' and kodenr='2'",__FILE__ . " linje " . __LINE__)); 
		$x=$kasse-1;
		$tmp=explode(chr(9),$r['box3']);
		$printserver=trim($tmp[$x]);
		$tmp=explode(chr(9),$r['box4']);
		$terminal_ip=trim($tmp[$x]);
		$betalingskort=explode(chr(9),$r['box5']);
		$div_kort_kto=trim($r['box6']);
		if ($terminal_ip && $div_kort_kto) { 
			$r = db_fetch_array(db_select("select box4,box5 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
			$kortantal=$r['box4']*1;
			$korttyper=explode(chr(9),$r['box5']);
			$lkt=explode(chr(9),strtolower($r['box5']));
			$lk=strtolower($kortnavn);
			if (in_array($lk,$lkt)) {
				for($x=0;$x<$kortantal;$x++) {
					if ($lk==$lkt[$x] && $betaling=='Betalingskort') $betaling=$korttyper[$x];
					if ($lk==$lkt[$x] && $betaling2=='Betalingskort') $betaling2=$korttyper[$x];
				}
			} elseif ($betaling=='Betalingskort') $betaling.="|".$kortnavn;
			elseif ($betaling2=='Betalingskort') $betaling2="|".$kortnavn;
		}
	}
#	return("$godkendt");
#	if ($bettalintaling==,$betaling2)
#exit;
	$projekt=NULL;
	$tid=date("H:i");
	$r=db_fetch_array(db_select("select box9 from grupper where art='POSBUT' and (box7 < box8) and (box7<'$tid' and box8>'$tid')",__FILE__ . " linje " . __LINE__));
	$projekt=$r['box9'];
	if (!$projekt) {
		$r=db_fetch_array(db_select("select box9 from grupper where art='POSBUT' and (box7 > box8) and ((box7>'$tid' and box8>'$tid') or (box7<'$tid' and box8<'$tid'))",__FILE__ . " linje " . __LINE__));
		$projekt=$r['box9'];
	}
	$hurtigfakt='on';
	$moms=0;
	$dd=date("Y-m-d");
	$r=db_fetch_array(db_select("select konto_id,status,fakturanr,momssats,betalingsbet from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$momssats=$r['momssats'];
	$status=$r['status'];
	$konto_id=$r['konto_id'];
	$betalingsbet=$r['betalingsbet'];
	$x=0;

	if ($status<3) {
		$r=db_fetch_array(db_select("select box8 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
		$rabatvareid=$r['box8'];
		$q=db_select("select * from ordrelinjer where ordre_id = '$id' order by rabatgruppe, id desc ",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$x++;
			$linje_id[$x]=$r['id'];
			$vare_id[$x]=$r['vare_id'];
			$varenr[$x]=$r['varenr'];
			$pris[$x]=$r['pris'];
			$antal[$x]=$r['antal'];
			$momsfri[$x]=$r['momsfri'];
			$varemomssats[$x]=$r['momssats'];
			$folgevare[$x]=$r['folgevare'];
			$rabat[$x]=$r['rabat'];
			$rabatart[$x]=$r['rabatart'];
			$rabatgruppe[$x]=$r['rabatgruppe'];
			if ($rabatgruppe[$x]) {
				if ($rabatgruppe[$x]==$rabatgruppe[$x-1]) {
					$rabatantal[$x]=$antal[$x]+$rabatantal[$x-1];
					$rabatantal[$x-1]=0;
				} else $rabatantal[$x]=$antal[$x];
			} else $rabatantal[$x]=0;
			$m_rabat[$x]=$r['m_rabat']*-1;
		}
		$linjeantal=$x;
		$pos=0;
		$sum=0;
		$moms=0;
		$incl_moms=0;
		transaktion("begin");
		for($x=1;$x<=$linjeantal;$x++) {
			$pos++;
			db_modify("update ordrelinjer set posnr='$pos',projekt='$projekt' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			if ($rabatart[$x]=='amount') {
				$tmp=afrund(($pris[$x]-$rabat[$x])*$antal[$x],2);
			} else $tmp=afrund($pris[$x]*$antal[$x]-($pris[$x]*$antal[$x]/100*$rabat[$x]),2);
			$sum+=$tmp;
			if (!$momsfri[$x]) {
				$linjemoms[$x]=$tmp*$varemomssats[$x]/100;
				$moms+=$linjemoms[$x];
			} else $linjemoms[$x]=0;
				$linjesum[$x]=afrund($tmp+$linjemoms[$x],2);
				$incl_moms+=$linjesum[$x];
#cho 	"$linjesum[$x] -> $incl_moms<br>";		
		########################################################################
			if ($folgevare[$x]) {
				$pos++;
				$r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,gruppe from varer where id = '$folgevare[$x]'",__FILE__ . " linje " . __LINE__));
				$r2 = db_fetch_array(db_select("select box4, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
				$f_bogfkto=$r2['box4'];
				$f_momsfri=$r2['box7'];
				$tmp=afrund($antal[$x]*$r['salgspris'],2);
				$sum+=$tmp;
				if (!$f_momsfri){
					$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$f_bogfkto' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
					$kodenr=substr($r['moms'],1);
					$r2 = db_fetch_array(db_select("select box2 from grupper where kodenr = '$kodenr' and art = 'SM'",__FILE__ . " linje " . __LINE__));
					$f_momssats=$r2['box2']*1;
					$incl_moms+=afrund($tmp+$tmp*$f_momssats/100,2);
				}
#cho "insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momssats,momsfri,posnr,projekt) values ('$id','$folgevare[$x]', '$r[varenr]', '$r[beskrivelse]', '$antal[$x]','0','$r[salgspris]','0','$f_momssats','$f_momsfri','$pos','0')<br>\n";
				db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momssats,momsfri,posnr,projekt) values ('$id','$folgevare[$x]', '$r[varenr]', '$r[beskrivelse]', '$antal[$x]','0','$r[salgspris]','0','$f_momssats','$f_momsfri','$pos','$projekt')",__FILE__ . " linje " . __LINE__);
#				print "<tr><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>$r[beskrivelse]</td><td align=\"right\">".dkdecimal($r['salgspris'])."</td><td align=\"right\">".dkdecimal($antal[$x]*$r['salgspris'])."</td>\n";
			}
			if ($rabatantal[$x]) {
				list($grupperabat,$rabattype)=explode(";",grupperabat($rabatantal[$x],$rabatgruppe[$x]));
					if ($grupperabat) {
						$pos++;
						if ($rabatvareid && $r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,gruppe from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__))){
							$r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
							$r_momsfri = $r2['box7'];
							$r_vare_id=$r['id'];
							$r_varenr=$r['varenr'];
							$r_beskrivelse=$r['beskrivelse'];
						} else {
							$r_momsfri = $momsfri[$x];
							$r_vare_id=$vare_id[$x];
							$r_varenr=$varenr[$x];
							$r_beskrivelse='rabat';
						}
						db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momsfri,posnr,projekt) values ('$id','$r_vare_id', '$r_varenr', '$r_beskrivelse', '$rabatantal[$x]','0','$grupperabat','0','$r_momsfri','$pos','$projekt')",__FILE__ . " linje " . __LINE__);
						$tmp=afrund($grupperabat*$rabatantal[$x],2);
						$sum+=$tmp;
						if (!$r_momsfri){
							$incl_moms+=afrund($tmp+$tmp*$varemomssats[$x]/100,2);
						}
					}
				} elseif ($m_rabat[$x] && !$rabatgruppe[$x]) {
					$pos++;
					if ($rabatvareid && $r=db_fetch_array(db_select("select id,varenr,beskrivelse,salgspris,gruppe from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__))) {
						$r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
						$r_momsfri = $r2['box7'];
						$r_vare_id=$r['id'];
						$r_varenr=$r['varenr'];
						$r_beskrivelse=$r['beskrivelse'];
					} else {
						$r_momsfri = $momsfri[$x];
						$r_vare_id=$vare_id[$x];
						$r_varenr=$varenr[$x];
						$r_beskrivelse='rabat';
					}
					db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momsfri,posnr,projekt) values ('$id','$r_vare_id', '$r_varenr', '$r_beskrivelse', '$antal[$x]','0','$m_rabat[$x]','0','$r_momsfri','$pos','$projekt')",__FILE__ . " linje " . __LINE__);
					$rabatbelob=afrund($m_rabat[$x]*$antal[$x],2);
					$sum+=$rabatbelob;
					if (!$r_momsfri) { 
						$moms+=afrund($rabatbelob*$momssats/100,2); #20131015
						$incl_moms+=afrund($rabatbelob+$rabatbelob*$varemomssats[$x]/100,2);
					}
				}
#			}
		}
		$fakturanr=1;
		$q=db_select("select fakturanr from ordrer where art = 'PO'",__FILE__ . " linje " . __LINE__); #max(fakturanr) fungerer ikke da feltet ikke er numerisk
		while ($r=db_fetch_array($q)) {
		 if ($r['fakturanr']>=$fakturanr) $fakturanr=$r['fakturanr']+1;
		}
#cho "update ordrer set fakturanr='$fakturanr' where id='$id'<br>";
		db_modify("update ordrer set fakturanr='$fakturanr' where id='$id'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q=db_select("select id from ordrer where fakturanr='$fakturanr' and art = 'PO' and id != '$id'",__FILE__ . " linje " . __LINE__))) {
			usleep($kasse*100000);
			if ($r=db_fetch_array($q=db_select("select id from ordrer where fakturanr='$fakturanr' and art = 'PO' and id != '$id'",__FILE__ . " linje " . __LINE__))) {
				$fakturanr=$fakturanr+1;
				db_modify ("update ordrer set fakturanr='$fakturanr' where id='$id'",__FILE__ . " linje " . __LINE__);
			}
		}
		$sum*=1; $moms*=1;
		$betalt=$modtaget+$modtaget2;
		$retur=afrund($betalt-($sum+$moms),2); #20140613
		if ($konto_id && ($betalingsbet!='Kontant' || $indbetaling)) {
			$saldo=0;
			$q=db_select("select * from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				$saldo=$saldo+$r['amount'];
			}
			$betaling2=$saldo;
			if ($indbetaling) {
			$modtaget2=$saldo-$indbetaling;
			$sum=$indbetaling;
			$moms='0';
		} else $modtaget2=$saldo+$sum;
	}
	$moms=afrund($moms,2);
#	$retur=($sum+$moms)-($modtaget+$modtaget2); #remmet 20140605
if ($betaling=='Kontant' && !$betaling2 && $retur) { 
		if ($difkto){
			$afrundet=pos_afrund($sum+$moms);
			$tmp=afrund($modtaget-($sum+$moms),2);
			if (!$tmp) {
				$betalt=$afrundet;
				$modtaget=$afrundet;
			} elseif ($modtaget == pos_afrund($modtaget,$difkto)) {
				$betalt=$afrundet;
			}
		}
	} elseif ($betaling=='Konto' && $betalingsbet=='Kontant' && $modtaget!=$sum+$moms && $retur) {
		if ($modtaget!=$sum+$moms && $sum+$moms!=pos_afrund($sum+$moms,$difkto)) {
#cho "$modtaget!=$sum+$moms";
#cho "&& ";
#cho "!=";
#cho pos_afrund($sum+$moms)."<br>";
			$afrundet=pos_afrund($sum+$moms,$difkto);
			$betalt=$afrundet;
		}
	}

#	if (!$retur) $retur=afrund($modtaget+$modtaget2-$betalt,2);
#cho "R $retur ($modtaget+$modtaget2-$betalt)<br>";
	$modtaget=afrund($modtaget,2);
	$modtaget2=afrund($modtaget2,2);

#cho "$sum+$moms!=$incl_moms<br>";
	if ($sum+$moms!=$incl_moms) {
// Denne rutine korrigerer for de differencer det kan opstå i totaler fordi momsberegningen på skærmen vises for den enkelte vare, mens databasen indeholder 
// summen at varer excl moms og momsen separat. Hvis der er difference på summen tillægges/frratrækkes de enkelte varer så mange tienedele ører som muligt
// uden at den afrundede værdi incl moms ændres, indtil summen ex. moms + moms svarer til summen af varer incl moms. 20131205
		$tmp=afrund($incl_moms-($sum+$moms),2);
		if (abs($tmp)<=$linjeantal/200) {#max 0,5 øre afrundingsfejl pr linje;
			$sum+=$tmp;
			for($x=1;$x<=$linjeantal;$x++) {
				$ny_pris[$x]=$pris[$x];
				$tmp2=afrund($pris[$x],2);
				$tmp3=afrund($tmp2*$antal[$x]-($tmp2*$antal[$x]/100*$rabat[$x]),2);
				#cho "A-: $tmp && ".afrund($tmp3+$linjemoms[$x],2)."==$linjesum[$x]<br>";
				while (afrund($tmp,2) && afrund($tmp3+$linjemoms[$x],2)==$linjesum[$x]) {
					#cho "A: $tmp && ".afrund($tmp3+$linjemoms[$x],2)."==$linjesum[$x]<br>";
					($tmp>0)?$tmp2+=0.001:$tmp2-=0.001;
					$tmp3=afrund($tmp2*$antal[$x]-($tmp2*$antal[$x]/100*$rabat[$x]),2);
					#cho "B: $tmp && ".afrund($tmp3+$linjemoms[$x],2)."==$linjesum[$x]<br>";
					if (afrund($tmp3+$linjemoms[$x],2)==$linjesum[$x]) {
						$ny_pris[$x]=$tmp2;
						($tmp>0)?$tmp-=0.001:$tmp+=0.001;
					}
				}
				if ($ny_pris[$x]!=$pris[$x]) {
				#cho "$ny_pris[$x]!=$pris[$x]<br>";
					$qtxt="update ordrelinjer set pris='$ny_pris[$x]' where id = '$linje_id[$x]'";
					#cho "$qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		} elseif (!$indbetaling) {
			print "<BODY onLoad=\"javascript:alert('Fejl i øreafrunding, kontakt Danosoft på telefon 46902208')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">";
			exit;
		}
	}
			#cho "INCL MOMS=$incl_moms-($sum+$moms)<br>";
	
	$tidspkt=date("Y-m-d H:i");
	$qtxt="update ordrer set levdate = '$dd',fakturadate = '$dd',sum='$sum', moms='$moms', betalt='$betalt',status='2',felt_1='$betaling',felt_2='$modtaget',felt_3='$betaling2',felt_4='$modtaget2',felt_5='$kasse',tidspkt='$tidspkt',projekt='$projekt' where id='$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if (!$indbetaling) {
			$svar=levering($id,'on','','');
			if ($svar != 'OK') return ($svar);
			$svar=bogfor($id,'');
			if ($svar != 'OK') return ($svar);
		} else {
			$svar=bogfor_indbetaling($id,'');
			if ($svar != 'OK') return ($svar);
		}
	}
	
#xit;	
	transaktion("commit");
	if (db_fetch_array(db_select("select id from grupper where art = 'POS' and kodenr = '1' and box10='on'",__FILE__ . " linje " . __LINE__))) {
	  pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling);
	 } else {
		$pfnavn="../temp/".$db."/".$bruger_id.".txt";
		$fp=fopen("$pfnavn","w");
		fclose($fp);
		$tmp="/temp/".$db."/".$bruger_id.".txt";
		$url="://".$_SERVER['SERVER_NAME'].=$_SERVER['PHP_SELF'];
		$url=str_replace("/debitor/pos_ordre.php","",$url);
		if ($_SERVER['HTTPS']) $url="s".$url;
		$url="http".$url;
		print "<BODY onLoad=\"JavaScript:window.open('http://$printserver/saldiprint.php?printfil=$tmp&url=$url&bruger_id=$bruger_id&bonantal=1' , '' , '$jsvars');\">\n";
	}
	print "\n<!-- Function afslut (slut)-->\n";
	return(NULL);
}

function betaling($id,$momssats,$betaling,$betaling2,$modtaget,$modtaget2) {
	print "\n<!-- Function betaling (start)-->\n";
	global $fokus;
	global $kontonr;
	global $difkto;
#	global $del_bord;

	$fokus="modtaget";
	if ($id) {
		$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['konto_id']*1;
		$kontonr=$r['kontonr'];
		$firmanavn=$r['firmanavn'];
		$addr1=$r['addr1'];
		$post_by=$r['postnr']." ".$r['bynavn'];
		$kundeordnr=$r['kundeordnr'];
		$status=$r['status'];
		$betalingsbet=$r['betalingsbet'];
		if ($r['lukket']) $betalingsbet='Kontant';
		$ref=$r['ref'];
	
		if ($konto_id) {
			print "<tr><td><b>$kontonr</b>\n";
			if ($kundeordnr) print "&nbsp;&nbsp;&nbsp; Rekv.nr: $kundeordnr";
			print "</td></tr>\n";
			print "<tr><td colspan=\"2\"><b>D $firmanavn</b></td></tr>\n";
			if ($betalingsbet=='Kontant')print "<tr><td colspan=\"2\"><b>Ingen kredit</b></td>\n"; 
		}
		print "<tr><td width=\"50%\"><table width=\"100%\"><tbody>\n";
		print "<tr><td>Varenummer</td><td align=\"right\">Antal</td><td>Varenavn</td><td align=\"right\">Pris</td><td align=\"right\">Sum</td></tr>\n";
		print "<tr><td colspan=\"6\"><hr></td></tr>\n";
		list($sum,$afrundet)=explode(chr(32),vis_pos_linjer($id,$momssats,$status));
		if ($kontonr && $betalingsbet!='Kontant') $modtaget=$sum;
		if ($modtaget && $afrundet) $retur=$modtaget-$afrundet;
		elseif ($modtaget) $retur=$modtaget-$sum;
	}
	print "<input type=\"hidden\" name = \"fokus\" value=\"$fokus\">\n";
	print "<input type=\"hidden\" name = \"betaling\" value=\"$betaling\">\n";
	print "<input type=\"hidden\" name = \"sum\" value=\"$sum\">\n";
	if ($modtaget) $tmp=dkdecimal($modtaget);
	else $tmp="";
#cho "$kontonr && $betalingsbet!='Kontant'<br>\n";
#exit;
	if ($kontonr && $betalingsbet!='Kontant') print "<input type=\"hidden\" name=\"modtaget\" value=\"$tmp\">\n";
	elseif(substr($betaling,0,9)!='Kontant p') {
		print "<tr><td>$betaling</td><td colspan= \"4\" align=\"right\"><input class=\"inputbox\" type=\"text\" size=\"15\" style=\"text-align:right\" name = \"modtaget\" value=\"$tmp\"></td></tr>\n";
		if ($betaling != "ukendt" && ($retur<0 || $modtaget2)) {$color="color: rgb(255, 0, 0);";
			if ($modtaget2) $tmp=dkdecimal($modtaget2);
			else $tmp="";
			if (!$betaling2) $betaling2="ukendt";
			$fokus="modtaget2";
			$retur=$retur+$modtaget2;
			print "<tr><td>$betaling2</td><td colspan= \"4\" align=\"right\"><input class=\"inputbox\" type=\"text\" size=\"15\" style=\"text-align:right\" name = \"modtaget2\" value=\"$tmp\"></td></tr>\n";
		} else $color="color: rgb(0, 0, 0);";
		#cho "retur $retur<br>";
#		$retur=pos_afrund($retur);
		print "<tr><td>Retur</td><td colspan= \"4\" align=\"right\"><span style=\"$color\">".dkdecimal($retur)."</span></td></tr>\n";
	}
	print "<td colspan=\"6\"><input STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
	print "</tbody></table>\n";
#cho "SUM $sum<br>\n";
	print "\n<!-- Function betaling (slut)-->\n";
	return($sum);
}

function skift_bruger($ny_bruger,$kode) {
	global $brugernavn;
	global $s_id;
	global $db;

	$kode=md5($kode);
	if ($r=db_fetch_array(db_select("select id from brugere where brugernavn ='$ny_bruger' and kode = '$kode'",__FILE__ . " linje " . __LINE__))) {
		include("../includes/connect.php");
		db_modify("update online set brugernavn='$ny_bruger' where session_id='$s_id' and db = '$db'",__FILE__ . " linje " . __LINE__);
		$brugernavn=$ny_bruger;
		print "<input type=\"hidden\" name=\"brugernavn\" value=\"$brugernavn\">\n";
		include("../includes/online.php");
	} else print "<BODY onLoad=\"javascript:alert('Forkert adgangskode')\">\n";
}

function varescan ($id,$momssats,$varenr_ny,$antal_ny,$pris_ny,$rabat_ny) {
	print "\n<!-- Function varescan (start)-->\n";
	global $fokus;
	global $kontonr;
	global $sum;
	global $difkto;
	global $bordnr;  #20140508
	global $kundedisplay;
	
	if ($id) {
		$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['konto_id'];
		$kontonr=$r['kontonr'];
		$firmanavn=$r['firmanavn'];
		$addr1=$r['addr1'];
		$post_by=$r['postnr']." ".$r['bynavn'];
		$status=$r['status'];
		$kundeordnr=$r['kundeordnr'];
		$betalingsbet=$r['betalingsbet'];
		if (!$r['firmanavn']) $betalingsbet='Kontant';
		if ($status >= 3) {
			$fakturanr=$r['fakturanr'];
			$kasse=$r['felt_5'];
			$fakturadato=dkdato(substr($r['fakturadate'],0,10));
			$tidspkt=substr($r['tidspkt'],-5);
			if (!$tidspkt) {
				$r2=db_fetch_array(db_select("select logtime from transaktioner where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
				$tidspkt=substr($r2['logtime'],0,5);
				$tmp=$r['fakturadate']." ".$tidspkt;
#cho "update ordrer set tidspkt = '$tmp' where id = '$id'<br>\n";
				db_modify("update ordrer set tidspkt = '$tmp' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}
		}
		if ($status >= 3) {
			$betaling=$r['felt_1'];
			$modtaget=$r['felt_2'];
			$betaling2=$r['felt_3'];
			$modtaget2=$r['felt_4'];
		} else {
			$fakturanr=NULL;
			$fakturadato=NULL;
			$kasse=NULL;
			$tidspkt=NULL;
		}
		($r['ref'])?$ref=$r['ref']:$ref=$brugernavn;

		if ($ref) {
			if ($r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$ref'",__FILE__ . " linje " . __LINE__))) {
				$ansat_id=$r['ansat_id']*1;
				if ($r=db_fetch_array(db_select("select navn from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__))) $ref=$r['navn'];
			}
		}
	}
	if ($kontonr && $betalingsbet!='Kontant') {
		$r=db_fetch_array(db_select("select kreditmax from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		$kreditmax=$r['kreditmax'];
		$r=db_fetch_array(db_select("select sum(amount) as saldo from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		$saldo=$r['saldo'];
	}

	if ($varenr_ny) {
		$varenr_ny=db_escape_string($varenr_ny);
		$varenr_low=strtolower($varenr_ny);
		$varenr_up=strtoupper($varenr_ny);
#cho "SELECT id,vare_id,variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr_up'<br>\n";
		if ($r=db_fetch_array(db_select("SELECT id,vare_id,variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr_up'",__FILE__ . " linje " . __LINE__))) {
			$vare_id=$r['vare_id'];
			$variant_type=$r['variant_type'];
			$variant_id=$r['id'];
		} else {
			$variant_id=0;
			$variant_type='';
		}
		if ($vare_id) $string="select * from varer where id='$vare_id'";
		else $string="select * from varer where lower(varenr) = '$varenr_low' or upper(varenr) = '$varenr_up' or varenr LIKE '$varenr_ny' or lower(stregkode) = '$varenr_low' or upper(stregkode) = '$varenr_up' or stregkode LIKE '$varenr_ny'";
#cho "streng ".$string."<br>\n";
		if ($r=db_fetch_array(db_select("$string",__FILE__ . " linje " . __LINE__))) {
#		  $varenr_ny=db_escape_string($r['varenr']);
			$beskrivelse[0]=$r['beskrivelse'];
		  $kostpris[0]=$r['kostpris'];
			$pris[0]=find_pris($r['varenr'])*1;
		  if ($pris[0]) $pris[0]=dkdecimal($pris[0]);
			else $pris[0]="";

			if ($fokus!="pris_ny" && $fokus!="rabat_ny") $fokus="antal_ny";
		} else return ("fejl".chr(9)."".chr(9)."Varenr: $varenr_ny eksisterer ikke");
		if ($variant_type) {
			$varianter=explode(chr(9),$variant_type);
			for ($y=0;$y<count($varianter);$y++) {
				$r1=db_fetch_array(db_select("select variant_typer.beskrivelse as vt_besk,varianter.beskrivelse as var_besk from variant_typer,varianter where variant_typer.id = '$varianter[$y]' and variant_typer.variant_id=varianter.id",__FILE__ . " linje " . __LINE__));
				$beskrivelse[0].=", ".$r1['var_besk'].":".$r1['vt_besk'];
			}
		}
	} else $fokus="varenr_ny";
	if ($kontonr) {
		print "<tr><td><b>$kontonr</b></td><td colspan=\"2\">\n";
		if ($status<3) print "Rekv.nr:&nbsp; <input type=\"text\" size=\"15\" name=\"kundeordnr\" value=\"$kundeordnr\">\n";
		elseif ($kundeordnr) print "&nbsp; Rekv.nr:&nbsp; $kundeordnr</td>\n";
		if ($status>=3) print "</td><td colspan=\"2\" align=\"right\">Ekspedient: $ref | Bon: $fakturanr</td>\n";
		print "</tr>\n<tr><td colspan=\"2\"><b>$firmanavn</b></td>\n";
		if ($status>=3) print "<td colspan=\"4\" align=\"right\">Kasse: $kasse | $fakturadato kl. $tidspkt</td></tr>\n";
		if ($betalingsbet=='Kontant')print "<tr><td colspan=\"2\"><b>Ingen kredit</b></td>\n"; 
	} else {
		print "<tr><td colspan=\"6\" align=\"right\">Ekspedient: $ref | Bon: $fakturanr</td></tr>\n";
		print "<tr><td colspan=\"6\" align=\"right\">Kasse: $kasse | $fakturadato kl. $tidspkt</td></tr>\n";
	}
	print "<tr><td width=\"10%\" height=\"25px\" valign=\"bottom\">Varenummer</td><td width=\"2%\" valign=\"bottom\">Antal</td><td valign=\"bottom\">Varenavn</td><td align=\"right\" valign=\"bottom\">Pris</td>\n";
 	if ($fokus=="rabat_ny") print "<td colspan=\"2\" align=\"right\" valign=\"bottom\">Rabat</td></tr>\n";
 	else print "<td align=\"right\" valign=\"bottom\">Sum</td></tr>\n";
	print "<tr><td colspan=\"6\"><hr></td></tr>\n";
	if ($status < 3) {
		if(isset($_GET['ret']) && is_numeric($_GET['ret']) && !$antal_ny) {
			$ret=$_GET['ret']*1;
			$r=db_fetch_array(db_select("select varenr,antal,beskrivelse,pris,kostpris,momssats,momsfri,rabat from ordrelinjer where id = '$ret'",__FILE__ . " linje " . __LINE__));
			$varenr_ny=$r['varenr'];
			$antal_old=dkdecimal($r['antal']);
			$rabat_old=dkdecimal($r['rabat']);
			$beskrivelse[0]=$r['beskrivelse'];
			$fokus="antal_ny";
			if ($r['momsfri'] && $r['momssats']==0) $pris[0]=dkdecimal($r['pris']);
			else $pris[0]=dkdecimal($r['pris']+$r['pris']*$r['momssats']/100);
			$kostpris[0]=$r['kostpris'];
			db_modify("delete from ordrelinjer where id = '$ret'",__FILE__ . " linje " . __LINE__);
		} else {
			$antal_old='1';
			$rabat_old=$rabat_ny;
			if ($pris_ny) $pris[0]=$pris_ny;
		}
		print "<input type=\"hidden\" name = \"fokus\" value=\"$fokus\">\n";
		print "<input type=\"hidden\" name = \"pre_bordnr\" value=\"$bordnr\">\n";  #20140508
		#print "<input type=\"hidden\" name = \"vare_id\" value=\"$vare_id[0]\">\n";
		print "<input type=\"hidden\" name = \"momssats\" value=\"$momssats\">\n";
		print "<input type=\"hidden\" name = \"beskrivelse_ny\" value=\"$beskrivelse[0]\">\n";
		print "<input type=\"hidden\" name = \"antal\" value=\"$antal\">\n";
		print "<tr><td width=\"30px\"><input class=\"inputbox\" type=\"text\" style=\"width:120px\" name = \"varenr_ny\" value=\"$varenr_ny\"></td>\n";
		if ($varenr_ny) {
			print "<td width=\"7px\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:40px\" name =\"antal_ny\" placeholder=\"$antal_old\" value=\"$antal_ny\"></td><td>".$beskrivelse[0]."</td>\n";
			if ($antal_ny) {
				print "<input type=hidden name=\"pris_old\" value=\"$pris[0]\">\n";
				print "<td align=\"right\" title=\"Kostpris ex. moms: ".dkdecimal($kostpris[0])."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:60px\" name = \"pris_ny\"  placeholder=\"$pris[0]\" value=\"\"></td>\n";
			} else {
				print "<input type=hidden name=\"pris_ny\" value=\"$pris[0]\">\n";
				print "<td align=\"right\" title=\"Kostpris ex. moms: ".dkdecimal($kostpris[0])."\">$pris[0]</td>\n";
			}
			if ($pris_ny && $fokus=="rabat_ny") {
				$r=db_fetch_array(db_select("select box8 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
				$rabatvareid=$r['box8']*1;
				if (db_fetch_array(db_select("select varenr from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__))) {
					print "<input type=hidden name=\"rabat_old\" value=\"$rabat_old\">\n";
					print "<td colspan=\"2\" align=\"right\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:40px\" name = \"rabat_ny\" placeholder=\"$rabat_old\"></td>\n";
				} else {
					$txt="Manglende varenr til rabat";
					print "<BODY onLoad=\"javascript:alert('$txt')\">\n";
					return($txt);
				}
			} else {
				print "<input type=hidden name=\"rabat_ny\" value=\"$rabat_old\">\n";
				if ($rabat_old && $rabat_old!='0,00') print "<td colspan=\"2\" align=\"right\">$rabat_old%</td>\n";
			}
		}
		print "</tr>\n";
	}
	list($sum,$afrundet)=explode(chr(32),vis_pos_linjer($id,$momssats,$status));
	if ($konto_id && $kreditmax && $sum > $kreditmax - $saldo) {
		$ny_saldo=$saldo+$sum;
		$txt = "Kreditmax: ".dkdecimal($kreditmax)."\\nGl. saldo :  ".dkdecimal($saldo)."\\nNy saldo :  ".dkdecimal($ny_saldo);
		print "<BODY onLoad=\"javascript:alert('$txt')\">\n";

	}
	print "<input type=\"hidden\" name = \"sum\" value = \"$sum\">\n";
	print "<input type=\"hidden\" name = \"afrundet\" value = \"$afrundet\">\n";
	if ($status >= 3 && $sum) {
		$tmp=dkdecimal($modtaget);
		print "<tr><td>$betaling</td><td colspan=\"4\" align=\"right\">$tmp</td></tr>\n";
		if ($betalt<$sum && $betaling != "Konto") {
		$tmp=dkdecimal($modtaget2);
		print "<tr><td>$betaling2</td><td colspan=\"4\" align=\"right\">$tmp</td></tr>\n";
		}
#		$tmp=dkdecimal($modtaget+$modtaget2-$sum);
		if (!$afrundet) $afrundet=$sum;
		if ($betaling=='Kontant' && !$betaling2) $tmp=dkdecimal($modtaget-$afrundet);
		elseif ($betalingsbet=='Kontant' && $modtaget+$modtaget2==pos_afrund($modtaget+$modtaget2,$difkto)) $tmp=dkdecimal($modtaget+$modtaget2-$afrundet);
		elseif ($betaling!='Kontant' && !$betaling2) $tmp=dkdecimal($modtaget-$sum);
		elseif ($betaling=='Kontant' && $betaling2 != 'Kontant') $tmp=dkdecimal($modtaget+$modtaget2-$sum);
		else $tmp=dkdecimal($modtaget-$afrundet);
		if ($betaling != "Konto" || $betalingsbet=='Kontant') {
			print "<tr><td>Retur</td><td colspan=\"4\" align=\"right\"><b>$tmp</b></td></tr>\n";
			if ($kundedisplay) {
				kundedisplay('Modtaget',$modtaget+$modtaget2,0);
				kundedisplay('Retur',$tmp,0);
			}
		}
	} elseif ($status >= 3) {
		$r=db_fetch_array($q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		print "<tr><td>Saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($r[felt_3])."</td></tr>\n";
		$indbetaling=($r['felt_4']-$r['felt_3'])*-1;
		print "<tr><td>Indbetaling</td><td colspan=\"4\" align=\"right\">".dkdecimal($indbetaling)."</td></tr>\n";
		print "<tr><td>$r[felt_1]</td><td colspan=\"4\" align=\"right\">".dkdecimal($r['felt_2'])."</td></tr>\n";
		$ny_saldo=$r['felt_4'];
		print "<tr><td>Ny saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($ny_saldo)."</td></tr>\n";
		$retur=pos_afrund($r['felt_2']+$r['felt_4']-$r['felt_3'],$difkto);
		print "<tr><td>Retur</td><td  colspan=\"4\" align=\"right\">".dkdecimal($retur)."</td></tr>\n";
	}
	print "<tr><td colspan=\"6\" align=\"right\"><input  STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
	if ($konto_id && $status<3 && $betalingsbet!='Kontant') {
		print "<tr><td>Gl. saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($saldo)."</td></tr>\n";
		$ny_saldo=$saldo+$sum;
		print "<tr><td>Ny saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($ny_saldo)."</td></tr>\n";
		if ($kreditmax) {
			print "<tr><td>Kreditmax</td><td colspan=\"4\" align=\"right\">".dkdecimal($kreditmax	)."</td></tr>\n";
		}
	}
	print "\n<!-- Function varescan (slut)-->\n";
	return ($varenr_ny.chr(9).$pris_ny.chr(9).$status);
}

function opret_posordre($konto_id,$kasse){
	global $brugernavn;
	global $bordnr;  #20140508
 	if ($r=db_fetch_array($q = db_select("select ordrenr from ordrer where art='PO' order by ordrenr desc",__FILE__ . " linje " . __LINE__))) {
		$ordrenr=$r['ordrenr']+1;
	} else $ordrenr=1;
	$ordredate=date("Y-m-d");
	$tidspkt=date("U");
	$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$kasseantal=$r['box1']*1;
	$moms=explode(chr(9),$r['box7']);
	$x=$kasse-1;
	if ($moms[$x]){
		$r=db_fetch_array(db_select("select * from grupper where art = 'SM' and kodenr = '$moms[$x]'",__FILE__ . " linje " . __LINE__));
		$momssats=$r['box2'];
	} else $momssats='0';
	$bordnr*=1;
	db_modify ("insert into ordrer
		(ordrenr,konto_id, kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,ordredate,momssats,hvem,tidspkt,ref,valuta,sprog,kontakt,pbs,status,nr)
			values
		('$ordrenr','0','$kontonr','$firmanavn','','','','','','0','Kontant','','','','','','$notes','PO','$ordredate','$momssats','$brugernavn','$tidspkt','$brugernavn','DKK','','','','0','$bordnr')",__FILE__ . " linje " . __LINE__);

	$r=db_fetch_array(db_select("select id from ordrer where hvem='$brugernavn' and tidspkt='$tidspkt' order by id desc",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	return($id);
} # endfunc opret_posordre()

function indbetaling($id,$indbetaling,$modtaget,$modtaget2,$betaling) {

	global $fokus;
	global $status;

	$fokus="indbetaling";
	$saldo=0;
	$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['konto_id'];
	$status=$r['status'];
	$kontonr=$r['kontonr'];
	$firmanavn=$r['firmanavn'];
	$addr1=$r['addr1'];
	$addr2=$r['addr2'];
	$postnr_by=$r['postnr']." ".$r['bynavn'];
	if ($status<3) {
		$q=db_select("select * from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$saldo=$saldo+$r['amount'];
		}
		list($a,$b)=explode(",",$indbetaling);
		if (!$indbetaling || !is_numeric($indbetaling)) {
			$indbetaling=$saldo;
			$modtaget='';
			$modtaget2='';
		}
		if ($modtaget+$modtaget2-$indbetaling>0) $retur=dkdecimal($modtaget+$modtaget2-$indbetaling);
		else $retur="0,00";
	} else {
		$saldo=$r['felt_3'];
		$indbetaling=$r['sum'];
		$retur=$r['felt_2']-$indbetaling;
	}
	$retur=pos_afrund($retur,$difkto);
	$ny_saldo=dkdecimal($saldo-$indbetaling);
	$saldo=dkdecimal($saldo);
	$indbetaling=dkdecimal($indbetaling);
	if ($modtaget) {
		$modtaget=dkdecimal($modtaget);
		$fokus="modtaget";
	}
	if ($modtaget2) {
		$modtaget2=dkdecimal($modtaget2);
		$fokus="modtaget";
	}
	print "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";
	print "<tr><td><b>$kontonr</b></td></tr>\n";
	print "<tr><td><b>C $firmanavn</b></td></tr>\n";
	print "<tr><td><b>$addr1</b></td></tr>\n";
	print "<tr><td><b>$addr2</b></td></tr>\n";
	print "<tr><td><b>$postnr_by</b></td></tr>\n";
	print "<tr><td colspan=2 width=400px><hr></td></tr>\n";
#	while (strlen($saldo) < 10) $saldo=" ".$saldo;
	print "<tr><td>Saldo</td><td align=\"right\">$saldo</td></tr>\n";
	print "<tr><td>Indbetaling</td>\n";
	if ($status<3) print "<td align=\"right\"><input class=\"inputbox\" type=text size=8 style=\"text-align:right\" name=\"indbetaling\" value=\"$indbetaling\"></td></tr>\n";
	else print "<td align=\"right\">$indbetaling</td></tr>\n";
	if ($status<3) print "<tr><td>Betalt</td><td align=\"right\"><input class=\"inputbox\" type=text size=8 style=\"text-align:right\" name=\"modtaget\" value=\"$modtaget\"></td></tr>\n";
	else print "<tr><td>Betalt</td><td align=\"right\">$modtaget</td></tr>\n";
	print "<tr><td>Ny saldo</td><td align=\"right\">$ny_saldo</td></tr>\n";
	print "<tr><td>Retur</td><td align=\"right\">$retur</td></tr>\n";
  print "<td colspan=\"6\"><input STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
}

function vis_pos_linjer($id,$momssats,$status) {
	print "\n<!-- Function vis_pos_linjer (start)-->\n";
	global $varelinjer;
	global $bgcolor;
	global $bgcolor5;
	global $difkto;
	global $afslut;
	global $kundedisplay;
	global $del_bord;
	$linjebg=$bgcolor;

	$del_bord=if_isset($_POST['del_bord']);
	
	$id=$id*1;
	$qtxt="select * from ordrelinjer where ordre_id = '$id' and ordre_id > 0 and posnr >= 0 order by rabatgruppe, id desc";
#cho "$qtxt<br>";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
		$x++;
		$linje_id[$x]=$r['id'];
		$vare_id[$x]=$r['vare_id'];
		$posnr[$x]=$r['posnr'];
		$varenr[$x]=$r['varenr'];
		$beskrivelse[$x]=stripslashes($r['beskrivelse']);
		$pris[$x]=$r['pris'];
		$kostpris[$x]=$r['kostpris'];
		$antal[$x]=$r['antal'];
		$folgevare[$x]=$r['folgevare'];
		$rabatgruppe[$x]=$r['rabatgruppe'];
		$rabat[$x]=$r['rabat']*1;
		$rabatart[$x]=$r['rabatart'];
		$m_rabat[$x]=$r['m_rabat']*-1;
		$momsfri[$x]=trim($r['momsfri']);
		$varemomssats[$x]=trim($r['momssats']);
		if ($rabatgruppe[$x]) {
			if ($rabatgruppe[$x]==$rabatgruppe[$x-1]) {
				$rabatantal[$x]=$antal[$x]+$rabatantal[$x-1];
				$rabatantal[$x-1]=0;
			} else $rabatantal[$x]=$antal[$x];
		} else $rabatantal[$x]=0;
		if ($varemomssats[$x] & $momsfri[$x]!='on') {
			$pris[$x]=afrund($pris[$x]+$pris[$x]/100*$varemomssats[$x],2);
#cho "$pris[$x]<br>";
			if ($m_rabat[$x]) $m_rabat[$x]=$m_rabat[$x]+$m_rabat[$x]/100*$varemomssats[$x];
		}
	}
	$varelinjer=$x;

	for ($x=1;$x<=$varelinjer;$x++) {
		($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
		if ($posnr[$x]) {
			print "<tr bgcolor=\"$linjebg\"><td>$varenr[$x]</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>$beskrivelse[$x]</td><td align=\"right\" title=\"Kostpris ex. moms: ".dkdecimal($kostpris[$x])."\">".dkdecimal($pris[$x])."</td><td align=\"right\">".dkdecimal($pris[$x]*$antal[$x])."</td><td style=\"width:50px;height:30px;\">";
			if ($del_bord) {
				print "<input style=\"width:50px;height:30px;\" name=\"delflyt[$x]\" type=\"checkbox\">"; 
				print "<input type=\"hidden\" name=\"linje_id[$x]\" value=\"$linje_id[$x]\">"; 
			} elseif ($status<'3') print "<a href=\"pos_ordre.php?id=$id&ret=$linje_id[$x]\"><input $disabled type=\"button\" style=\"width:50px;height:30px;text-align:center;font-size:15px;\" value= \"RET\"></a>";
			print "</td>\n";
			$sum+=afrund($pris[$x]*$antal[$x],2);
		}
		if ($rabat[$x]) {
			($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
			if ($rabatart[$x]=="amount") {
				if ($varemomssats[$x] & $momsfri[$x]!='on') $tmp=afrund($rabat[$x]+$rabat[$x]/100*$varemomssats[$x],2)*-1;
				else $tmp=afrund($rabat[$x],2)*-1;
				if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>Rabat</td><td align=\"right\">".dkdecimal($tmp)."</td><td align=\"right\">".dkdecimal($tmp*$antal[$x])."</td>\n";
				$sum+=afrund($tmp*$antal[$x],2);
			} else {
				$tmp=afrund($pris[$x]*$rabat[$x]/-100,2);
				if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>$rabat[$x]% rabat</td><td align=\"right\">".dkdecimal($tmp)."</td><td align=\"right\">".dkdecimal($tmp*$antal[$x])."</td>\n";
				$sum+=afrund($tmp*$antal[$x],2);
			}
		}
		if ($status < 3) {
			if ($folgevare[$x]) {
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				$r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,gruppe from varer where id = '$folgevare[$x]'",__FILE__ . " linje " . __LINE__));
				$r2 = db_fetch_array(db_select("select box4, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
				$f_bogfkto=$r2['box4'];
				$f_momsfri=$r2['box7'];
				if ($f_momsfri){
					$f_momssats=0;
					$f_pris=$r['salgspris'];
					} else {
				#cho "select moms from kontoplan where kontonr = '$f_bogfkto' and regnskabsaar = '$regnaar'<br>\n";
					$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$f_bogfkto' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
					$kodenr=substr($r2['moms'],1);
#cho "select box2 from grupper where kodenr = '$kodenr' and art = 'SM'<br>\n";
					$r2 = db_fetch_array(db_select("select box2 from grupper where kodenr = '$kodenr' and art = 'SM'",__FILE__ . " linje " . __LINE__));
					$f_momssats=$r2['box2']*1;
					$f_pris=$r['salgspris']+$r['salgspris']*$f_momssats/100;
				}
				if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>".stripslashes($r['beskrivelse'])."</td><td align=\"right\">".dkdecimal($r['salgspris'])."</td><td align=\"right\">".dkdecimal($antal[$x]*$r['salgspris'])."</td>\n";
				$sum+=afrund($antal[$x]*$r['salgspris'],2);
			}
#			}
			if ($rabatantal[$x]) {
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				list($grupperabat,$rabattype)=explode(";",grupperabat($rabatantal[$x],$rabatgruppe[$x]));
				if ($grupperabat) {
					if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($rabatantal[$x])."</td><td>Rabat</td><td align=\"right\">".dkdecimal($grupperabat)."</td><td align=\"right\">".dkdecimal($grupperabat*$rabatantal[$x])."</td>\n";
					$sum+=afrund($grupperabat*$rabatantal[$x],2);
				}
			} elseif ($m_rabat[$x] && !$rabatgruppe[$x]) {
				if ($posnr[$x]) {
					($r['beskrivelse'])?$tmp=$r['beskrivelse']:$tmp='Rabat';
					print "<tr bgcolor=\"$linjebg\"><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>".stripslashes($tmp)."</td><td align=\"right\">".dkdecimal($m_rabat[$x])."</td><td align=\"right\">".dkdecimal($antal[$x]*$m_rabat[$x])."</td>\n";
				} 
				 $sum+=afrund($m_rabat[$x]*$antal[$x],2);
			}
		}
	}
	$afrundet=pos_afrund($sum,$difkto);
	print "<tr><td colspan=\"6\"><hr></td></tr>\n";
	if ($sum) print "<tr><td>I alt</td><td align=\"right\"></td><td></td><td align=\"right\"></td><td align=\"right\">".dkdecimal($sum)."</td></tr>\n";
	if ($difkto && $afrundet != $sum) print "<tr><td>Afrundet</td><td align=\"right\"></td><td></td><td align=\"right\"></td><td align=\"right\">".dkdecimal($afrundet)."</td></tr>\n";
	if ($afslut && $status<3 && $kundedisplay) kundedisplay('I alt',$sum,1);
	print "\n<!-- Function vis_pos_linjer (slut)-->\n";
	return($sum.chr(32).$afrundet);
}

function pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling) {

#cho "$id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling<br>\n";

	global $db;
	global $db_id;
	global $brugernavn;
	global $bruger_id;
	global $momssats;
	global $db_encode;
	global $printserver;
	
#	$udskriv_bon=1;
 	include("../includes/ConvertCharset.class.php");
	if ($db_encode=="UTF8") $FromCharset = "UTF-8";
	else $FromCharset = "iso-8859-15";
	$ToCharset = "cp865";
	$convert = new ConvertCharset();

	$pfnavn="../temp/".$db."/".$bruger_id.".txt";
	$fp=fopen("$pfnavn","w");
	$r=db_fetch_array(db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$firmanavn=$r['firmanavn'];
	$addr1=$r['addr1'];
	$addr2=$r['addr2'];
	$postnr=$r['postnr'];
	$bynavn=$r['bynavn'];
	$tlf=$r['tlf'];
	$cvrnr=$r['cvrnr'];
	$belob="beløb";

	if ($firmanavn) $firmanavn = $convert ->Convert($firmanavn, $FromCharset, $ToCharset);
	if ($addr1) $addr1 = $convert ->Convert($addr1, $FromCharset, $ToCharset);
	if ($addr2) $addr2 = $convert ->Convert($addr2, $FromCharset, $ToCharset);
	if ($bynavn) $bynavn = $convert ->Convert($bynavn, $FromCharset, $ToCharset);
	if ($tlf) $tlf = $convert ->Convert($tlf, $FromCharset, $ToCharset);
	if ($cvrnr) $cvrnr = $convert ->Convert($cvrnr, $FromCharset, $ToCharset);

	if ($belob) $belob = $convert ->Convert($belob, $FromCharset, $ToCharset);

	$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['konto_id'];
	$kontonr=$r['kontonr'];
	$kundenavn=$r['firmanavn'];
	$kundeaddr1=$r['addr1'];
	$kundepostnr=$r['postnr'];
	$kundeby=$r['bynavn'];
	$kundeordnr=$r['kundeordnr'];
	$fakturadate=$r['fakturadate'];
	$fakturanr=$r['fakturanr'];
	$betalingsbet=$r['betalingsbet'];
	$fakturadato=dkdato($r['fakturadate']);
	$sum=$r['sum'];
	$moms=$r['moms'];
	$momssats=$r['momssats'];
	$betaling=$r['felt_1'];
	$modtaget=$r['felt_2']*1;
	$betaling2=$r['felt_3'];
	$modtaget2=$r['felt_4']*1;
	$betalt=$modtaget+$modtaget2;
	$ref=$r['ref'];
	$kasse=$r['felt_5'];
	$tidspkt=$r['tidspkt'];
	$dkdato=dkdato(substr($tidspkt,0,10));
	$tid=substr($tidspkt,-5);
	$bordnr=$r['nr']*1;  #20140508
	if (!$tid) $tid=date("H:i");
	if (!$betaling) $betaling="Betalt";
	if ($ref) {
		if ($r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$ref'",__FILE__ . " linje " . __LINE__))) {
			$ansat_id=$r['ansat_id']*1;
			if ($r=db_fetch_array(db_select("select navn from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__))) $ref=$r['navn'];
	  }
	}
	if (strpos($betaling,"|")) list($tmp,$betaling)=explode("|",$betaling);
	if (strpos($betaling2,"|")) list($tmp,$betaling2)=explode("|",$betaling2);
	
	if ($kundenavn) $kundenavn = $convert ->Convert($kundenavn, $FromCharset, $ToCharset);
	if ($kundeaddr1) $kundeaddr1 = $convert ->Convert($kundeaddr1, $FromCharset, $ToCharset);
	if ($kundeby) $kundeby = $convert ->Convert($kundeby, $FromCharset, $ToCharset);
	if ($ref) $ref = $convert ->Convert($ref, $FromCharset, $ToCharset);

	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$printer_ip=explode(chr(9),$r['box3']);
	$tmp=$kasse-1;
	$printserver=$printer_ip[$tmp];
	if (!$printserver)$printserver='localhost';

	#cho "printserver $printserver<br>";
	
	$x=0;
	$q=db_select("select * from ordrelinjer where ordre_id = '$id' and posnr > 0 order by posnr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$x++;
		if ($r['momsfri']!='on') $pris=$r['pris']+$r['pris']/100*$momssats;
		else $pris=$r['pris'];
		if (strtoupper($r['varenr'])=='INDBETALING') {
			$pris=$pris*-1;
			$sum=$sum*-1;
		}
		$rabat[$x]=$r['rabat']*1;
		$rabatart[$x]=$r['rabatart'];
		$beskrivelse[$x]=$r['beskrivelse'];
		if ($beskrivelse[$x]) $beskrivelse[$x]= $convert ->Convert($beskrivelse[$x], $FromCharset, $ToCharset);
		$antal[$x]=$r['antal']*1;
		$dkkpris[$x]=dkdecimal($pris*$antal[$x]);
		while(strlen($dkkpris[$x])<9){
			$dkkpris[$x]=" ".$dkkpris[$x];
		}
		while(strlen($antal[$x])<3){
			$antal[$x]=" ".$antal[$x];
		}
		if (strlen($beskrivelse[$x])>26) $beskrivelse[$x]=substr($beskrivelse[$x],0,25);
		while(strlen($beskrivelse[$x])<26){
			$beskrivelse[$x]=$beskrivelse[$x]." ";
		}
		if ($rabat[$x]) {
			$y=$x;
			$x++;
			$antal[$x]=$antal[$y];
			if ($rabatart[$y]=='amount') {
				$beskrivelse[$x]="Rabat";
				$pris=$rabat[$y]*-1;
			} else {
				$beskrivelse[$x]="Rabat ".$rabat[$y]."%";
				$pris=$r['pris']/100*$rabat[$y]*-1;
			}
			if ($r['momsfri']!='on') $pris+=$pris/100*$momssats;
			$dkkpris[$x]=dkdecimal($pris*$r['antal']);
			while(strlen($dkkpris[$x])<9){
				$dkkpris[$x]=" ".$dkkpris[$x];
			}
			while(strlen($antal[$x])<3){
				$antal[$x]=" ".$antal[$x];
			}
			if (strlen($beskrivelse[$x])>26) $beskrivelse[$x]=substr($beskrivelse[$x],0,25);
			while(strlen($beskrivelse[$x])<26){
				$beskrivelse[$x]=$beskrivelse[$x]." ";
			}
		}
		$linjeantal=$x;
	}
#cho "Sum $sum<br>";	
	$sum+=$moms;
#cho "Sum $sum<br>";
#xit;
	if ($konto_id) {
		if (!$x) $indbetaling=$sum;
		$gl_saldo=dkdecimal($betaling2);
		$ny_saldo=dkdecimal($modtaget2);
	}
	if ($indbetaling) $retur=$modtaget-$indbetaling;
	else $retur=$betalt-$sum;
	$dkksum=dkdecimal($sum);
	while(strlen($dkksum)<9){
		$dkksum=" ".$dkksum;
	}
	$dkkretur=dkdecimal($retur);
	while(strlen($dkkretur)<9){
		$dkkretur=" ".$dkkretur;
	}
	$betalt=dkdecimal($betalt);
	while(strlen($betalt)<9){
		$betalt=" ".$betalt;
	}
	while(strlen($betaling)<19){
		$betaling=$betaling." ";
	}
	while(strlen($betaling2)<19){
		$betaling2=$betaling2." ";
	}
	$dkkmodtaget=dkdecimal($modtaget);
	while(strlen($dkkmodtaget)<9){
		$dkkmodtaget=" ".$dkkmodtaget;
	}
	if ($modtaget2) {
		$dkkmodtaget2=dkdecimal($modtaget2);
		while(strlen($dkkmodtaget2)<9){
			$dkkmodtaget2=" ".$dkkmodtaget2;
		}
	}
	$dkksum=dkdecimal($sum);
	while(strlen($dkksum)<9){
		$dkksum=" ".$dkksum;
	}
	$dkkmoms=dkdecimal($moms);
	while(strlen($dkkmoms)<9){
		$dkkmoms=" ".$dkkmoms;
	}
	$filnavn="pos_print/pos_print_".$db_id.".php";
	if (file_exists("$filnavn")) include("$filnavn");
	else include("pos_print/pos_print.php");
	fclose($fp);
}

function opdater_konto($konto_id,$kontonr,$id) {
#Opdaterer kontoinformation på ordren
	global $kasse;
	global $kundeordnr;
	if (!$id) $id=opret_posordre(0,$kasse);
	$konto_id*=1;
	$kontonr*=1;
	$r=db_fetch_array(db_select("select status from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$status=$r['status'];
	if ($status < 3 && ($konto_id || $kontonr)) {
		if ($konto_id) $r=db_fetch_array(db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		else $r=db_fetch_array(db_select("select * from adresser where kontonr = '$kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__));
		if ($konto_id=$r['id']) {
			if ($r['lukket']) {
				$betalingsbet='Kontant';
				$betalingsdage='0';
			} else {
				($r['betalingsbet'])?$betalingsbet=$r['betalingsbet']:$betalingsbet='Kontant';
				$betalingsdage=$r['betalingsdage']*1;
			}
		  db_modify ("update ordrer set konto_id='$konto_id', kontonr='$r[kontonr]',firmanavn='$r[firmanavn]',addr1='$r[addr1]',addr2='$r[addr2]',
					postnr='$r[postnr]',bynavn='$r[bynavn]',land='$r[land]',betalingsdage='$betalingsdage',betalingsbet='$betalingsbet',cvrnr='$r[cvrnr]',
			ean='$r[ean]',institution='$r[institution]',email='$r[email]',kontakt='$r[kontakt]',art='PO',valuta='DKK',valutakurs='100' where id = '$id'",__FILE__ . " linje " . __LINE__);
	  }
	}
	return($id);
} # endfunc opdater_konto()


function hoved($kasse) {
	print "\n<!-- Function hoved (start)-->\n";
	global $regnskab;
	global $brugernavn;
	global $bruger_id;
	global $id;
	global $db;
	global $db_id;
	global $bon;
	global $sum;
	global $returside;
	global $bordnr;  #20140508
	global $vare_id;
	global $vare_id_ny;
	global $status;

	if ($kasse=="?") find_kasse($kasse);
	$x=0;
	$q=db_select("select brugernavn from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		$loginnavn[$x]=$r['brugernavn'];
	}
	$brugerantal=$x;
	
	$r = db_fetch_array(db_select("select box3,box4,box7 from grupper where art = 'POS' and kodenr='2'",__FILE__ . " linje " . __LINE__)); 
	$x=$kasse-1;
	$tmp=explode(chr(9),$r['box3']);
	$printserver=trim($tmp[$x]);
	$tmp=explode(chr(9),$r['box4']); #20131205
	$terminal_ip=trim($tmp[$x]);
	($r['box7'])?$bord=explode(chr(9),$r['box7']):$bord=NULL; #20140508

	if ($_SERVER['HTTPS']) $url='https://';
	else $url='http://';
	$url.=$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
	
	print "<tr>\n";
	print "<td width=\"96%\" height=\"25px\" valign=\"bottom\"><b>$regnskab</b> Kasse: <a href=pos_ordre.php?id=$id&kasse=?>$kasse</a> | ";
	print "<a href=pos_ordre.php?id=$id&kasse=$kasse&kassebeholdning=on>Kasseopt&aelig;lling</a>\n";
	if ($terminal_ip) print " | <a href=http://localhost/pointd/point.php?url=$url&id=$id&kasse=$kasse>Kortterminal</a>\n"; #20131205
	if (count($bord) && $status<'3') {
		($vare_id && $vare_id_ny)?$disabled="disabled=\"disabled\"":$disabled=NULL; 
		print "<INPUT $disabled style=\"width:70px;height:35px\" TYPE=\"submit\" NAME=\"flyt_bord\"VALUE=\"Flyt bord\">\n"; #20140508
		print "<INPUT $disabled style=\"width:70px;height:35px\" TYPE=\"submit\" NAME=\"del_bord\"VALUE=\"Del bord\">\n";
	}
	print "<br>Ekspedient<select class=\"inputbox\" NAME=\"ny_bruger\">\n";
	print "<option>$brugernavn</option>\n";
	for ($x=1;$x<=$brugerantal;$x++) {
		if ($loginnavn[$x] != $brugernavn) print "<option>$loginnavn[$x]</option>\n";
	}
	print "</option>\n";
	print "<input class=\"inputbox\" type=\"password\" size=\"10\" name=\"kode\" value=\"        \">\n";
	if ($status>=3 && !$bon && $id) {
		$r=db_fetch_array($q=db_select("select fakturanr from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$bon=$r['fakturanr'];
	}
	print "<span title=\"Skriv bon nummeret på den bon som skal genkaldes elles 'S' for sidste bon fra denne kasse\">&nbsp;&nbsp;Bon <input class=\"inputbox\" type=\"text\" name=\"bon\" size=\"6\" value=\"$bon\"></span>\n";
	if (count($bord) && $status<'3') {
		($vare_id && $vare_id_ny)?$disabled="disabled=\"disabled\"":$disabled=NULL; 
		if (file_exists("../bordplaner/bordplan_$db_id.php")) print "&nbsp;&nbsp;<a href=\"../bordplaner/bordplan_$db_id.php\"><input $disabled  type=\"button\" style=\"width:70px;height:35px;text-align:center;font-size:15px;\" value= \"Bord\"></a>\n";
		else print "&nbsp;&nbsp;Bord";
		print "<select $disabled class=\"inputbox\" NAME=\"bordnr\">\n";
		for ($x=0;$x<count($bord);$x++) {
			$tmp=$x+1;
			if ($bordnr==$x) print "<option value='$x'>$bord[$x]</option>\n";
		}
		for ($x=0;$x<count($bord);$x++) {
			$tmp=$x+1;
			if ($bordnr!=$x) print "<option value='$x'>$bord[$x]</option>\n";
		}
		print "</option>\n";
	}
	print "<br><hr></td>";

	print "<td width=\"4%\" align=\"right\" valign=\"top\"><a href='pos_ordre.php?luk=1&returside=$returside'><div class=\"luk\"></div></a></td></tr>\n";
	print "</tr>\n";
	print "\n<!-- Function hoved (slut)-->\n";
}

/*
	function hoved($kasse) {
	global $regnskab;
	global $brugernavn;
	global $id;
	global $db;
	global $db_id;

	if ($kasse=="?") find_kasse($kasse);
	print "<tr>\n";
	print "<td width=\"96%\" height=\"25px\" valign=\"bottom\"><b>$regnskab</b> Kasse: <a href=pos_ordre.php?id=$id&kasse=?>$kasse</a> | ";
	print "Ekspedient: <a href=../includes/relogin.php?regnskab=$regnskab&bruger_id=$bruger_id&db_$db&db_id=$db_id>$brugernavn</a> | ";
	print "<a href=pos_ordre.php?id=$id&kasse=$kasse&kassebeholdning=on>Kasseopt&aelig;lling</a><br><hr></td>\n";
	print "<td width=\"4%\" align=\"right\" valign=\"top\"><a href=../includes/luk.php><div class=\"luk\"></div></a></td></tr>\n";


print "</tr>\n";

}
*/
function find_kasse($kasse) {

   if ($kasse!="?" && isset($_COOKIE['saldi_pos'])) {
		return(stripslashes($_COOKIE['saldi_pos']));
	} else {
		print "<form name=pos_ordre action=\"pos_ordre.php?kasse=opdat&del_bord=$del_bord\" method=\"post\" autocomplete=\"off\">\n";
		$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
		$kasseantal=$r['box1']*1;
		$afd=explode(chr(9),$r['box3']);

		if ($kasseantal) {
			$x=0;
			$q = db_select("select * from grupper where art = 'AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$x++;
				$afd_nr[$x]=$r['kodenr'];
				$afd_navn[$x]=$r['beskrivelse'];
			}
		}
		print "V&aelig;lg kasse<SELECT NAME=kasse>\n";
		for($x=1;$x<=count($afd);$x++) {
			for($y=0;$y<count($afd_nr);$y++) {
				if ($afd[$x-1]==$afd_nr[$y]) $afdnavn=$afd_navn[$y];
			}
			print	"<option value=\"$x\">$x: $afdnavn</option>\n";
		}
		print "</SELECT></td>\n";;
		print "<INPUT TYPE=\"submit\" NAME=\"submit\"VALUE=\"OK\">\n";
		print "</form>\n";
	}
	exit;
}

function tastatur($status) {
	print "\n<!-- Function tastatur (start)-->\n";

	global $id;
	global $bon;
	global $fokus;
	global $sum;
	global $modtaget;
	global $modtaget2;
	global $kontonr;
	global $varelinjer;
	global $varenr_ny;
	global $indbetaling;
	global $betalingsbet;
	global $bordnr;
	

	$sum=afrund($sum,2);
	$modtaget=afrund($modtaget,2);
	$modtaget2=afrund($modtaget2,2);

	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$kortantal=$r['box4']*1;
	$korttyper=explode(chr(9),$r['box5']);
	$vis_kontoopslag=$r['box11'];
	$vis_hurtigknap=$r['box12'];
	$vis_indbetaling=$r['box14'];
	$timeout=$r['box13']*1;
	$r = db_fetch_array(db_select("select box6 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$div_kort_kto=$r['box6'];
	
	print "<input type=hidden name=\"sum\" value=\"$sum\">\n";
	print "<input type=hidden name=\"kontonr\" value=\"$kontonr\">\n";

	print "<TR><TD height=\"100%\" valign=\"top\"  align=\"center\"><TABLE BORDER=\"0\" CELLPADDING=\"4\" CELLSPACING=\"4\"><TBODY>\n";
	print "<TR>\n";
	if ($status < 3) {
		$stil="STYLE=\"width: 4.5em;height: 2em;font-size:150%;\"";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"one\"   VALUE=\"1\" OnClick=\"pos_ordre.$fokus.value += '1';pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"two\"   VALUE=\"2\" OnCLick=\"pos_ordre.$fokus.value += '2';pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"three\" VALUE=\"3\" OnClick=\"pos_ordre.$fokus.value += '3';pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"plus\"  VALUE=\"+\" OnClick=\"pos_ordre.$fokus.value += '+';pos_ordre.$fokus.focus();\"></TD>\n";
		print "</TR><TR>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"four\"  VALUE=\"4\" OnClick=\"pos_ordre.$fokus.value += '4';pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"five\"  VALUE=\"5\" OnCLick=\"pos_ordre.$fokus.value += '5';pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"six\"   VALUE=\"6\" OnClick=\"pos_ordre.$fokus.value += '6';pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"minus\" VALUE=\"-\" OnClick=\"pos_ordre.$fokus.value += '-';pos_ordre.$fokus.focus();\"></TD>\n";
		print "</TR><TR>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"seven\" VALUE=\"7\" OnClick=\"pos_ordre.$fokus.value += '7';pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"eight\" VALUE=\"8\" OnCLick=\"pos_ordre.$fokus.value += '8';pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"nine\"  VALUE=\"9\" OnClick=\"pos_ordre.$fokus.value += '9';pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"times\" VALUE=\"x\" OnClick=\"pos_ordre.$fokus.value += '*'\"></TD>\n";
		print "</TR><TR>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"zero\"  VALUE=\",\" OnClick=\"pos_ordre.$fokus.value += ',';pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"zero\"  VALUE=\"0\" OnClick=\"pos_ordre.$fokus.value += '0';pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"DoIt\"  VALUE=\"=\" OnClick=\"pos_ordre.$fokus.value = eval(pos_ordre.$fokus.value);pos_ordre.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"div\"   VALUE=\"/\" OnClick=\"pos_ordre.$fokus.value += '/';pos_ordre.$fokus.focus();\"></TD>\n";
		print "</TR><TR>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"clear\" VALUE=\"Ryd\" OnClick=\"pos_ordre.$fokus.value = '';pos_ordre.$fokus.focus();\"></TD>\n";
		if ($id) {
			print "<TD><INPUT TYPE=\"submit\" $stil NAME=\"afslut\"VALUE=\"Afslut\" OnClick=\"pos_ordre.$fokus.value += 'a';pos_ordre.$fokus.focus();\"></TD>\n";
			print "<TD onclick=\"return confirm('Slet alt og start forfra')\"><INPUT TYPE=\"submit\" $stil NAME=\"forfra\"VALUE=\"Forfra\" OnClick=\"pos_ordre.$fokus.value += 'f';pos_ordre.$fokus.focus();\"></TD>\n";
		} else print "<TD COLSPAN=\"2\"></TD>\n";
		if ($fokus=='modtaget') {
			print "<TD onclick=\"return confirm('Tilbage til varescanning')\"><INPUT TYPE=\"submit\" $stil NAME=\"tilbage\"VALUE=\"Tilbage\" OnClick=\"pos_ordre.$fokus.value += 't';pos_ordre.$fokus.focus();\"></TD>\n";
			print "</TR><TR>\n";
			print "<TD COLSPAN=\"3\"></TD>\n";
		}
		print "<TR><TD COLSPAN=\"4\"><HR></TD></tr>\n";
		$stil2="STYLE=\"width: 9.5em;height: 2em;font-size:150%;\"";
		print "<TR>\n";
#cho "$fokus=='modtaget' && $modtaget>=$sum && !$indbetaling<br>\n";
		if ($fokus=='varenr_ny') print "<TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"varer\"VALUE=\"Varer\" OnClick=\"pos_ordre.$fokus.value += 'v';pos_ordre.$fokus.focus();\"></TD>\n";
		elseif ($fokus=='antal_ny' || $fokus=='pris_ny') { #20130310 Tilføjet: || $fokus=='pris_ny' 
			if ($fokus=='antal_ny') print "<TD COLSPAN=\"1\"><INPUT TYPE=\"submit\" $stil NAME=\"pris\"VALUE=\"Pris\" OnClick=\"pos_ordre.$fokus.value += 'p';pos_ordre.$fokus.focus();\"></TD>\n";
			else print "<TD COLSPAN=\"1\"></TD>\n";
			print "<TD COLSPAN=\"1\"><INPUT TYPE=\"submit\" $stil NAME=\"rabat\"VALUE=\"Rabat\" OnClick=\"pos_ordre.$fokus.value += 'r';pos_ordre.$fokus.focus();\"></TD>\n";
		} elseif ($fokus=='modtaget' && $modtaget>=$sum && !$indbetaling && $betalingsbet != 'Kontant') {
			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling\" VALUE=\"Konto\" OnClick=\"pos_ordre.$fokus.value += 'k';pos_ordre.$fokus.focus();\"></TD>\n";
		} elseif ($fokus=='modtaget2' && $modtaget+$modtaget2>=$sum && !$indbetaling) {
			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling2\" VALUE=\"Konto\" OnClick=\"pos_ordre.$fokus.value += 'k';pos_ordre.$fokus.focus();\"></TD>\n";
		}	elseif ($indbetaling && $modtaget >= $indbetaling) {
			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling\" VALUE=\"Kontant\" OnClick=\"pos_ordre.$fokus.value += 'c';pos_ordre.$fokus.focus();\"></TD>\n";
		} else print "<TD colspan=2></TD>\n";
		print "<TD colspan=2><INPUT TYPE=\"submit\" $stil2 NAME=\"OK\"  VALUE=\"Enter\"></TD></tr>\n";
		if ($vis_hurtigknap && $fokus=='antal_ny') print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling\" VALUE=\"Kontant p&aring; bel&oslash;b\" OnClick=\"pos_ordre.$fokus.value += 'c';pos_ordre.$fokus.focus();\"></TD>\n";
		if ($vis_kontoopslag && !$varenr_ny && !$indbetaling) print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"kontoopslag\" VALUE=\"Kontoopslag\"></TD></tr>\n";
		if ((($fokus=='modtaget' || $fokus=='modtaget2') && (!$kontonr || $betalingsbet=='Kontant')) || ($indbetaling && $modtaget>=$indbetaling && $kontonr)) {
			if ($div_kort_kto) { #20140129
				($fokus=='modtaget2')?$tmp="betaling2":$tmp="betaling";
				print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=$tmp VALUE=\"Betalingskort\" OnClick=\"pos_ordre.$fokus.value += 'd';pos_ordre.$fokus.focus();\"></TD></tr>\n";
			} else {
				for($x=0;$x<$kortantal;$x++) {
					($fokus=='modtaget2')?$tmp="betaling2":$tmp="betaling";
					print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=$tmp VALUE=\"$korttyper[$x]\" OnClick=\"pos_ordre.$fokus.value += 'd';pos_ordre.$fokus.focus();\"></TD></tr>\n";
				}
			}
			if (!$indbetaling) {
				if ($fokus=='modtaget2') $tmp="betaling2";
				else $tmp="betaling";
				print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=$tmp VALUE=\"Kontant\" OnClick=\"pos_ordre.$fokus.value += 'c';pos_ordre.$fokus.focus();\"></TD></tr>\n";
			}
#			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling\" VALUE=\"Konto\" OnClick=\"pos_ordre.$fokus.value += 'k';pos_ordre.$fokus.focus();\"></TD></tr>\n";
		} elseif ($id && $kontonr && !$varelinjer && !$indbetaling)
		if ($vis_indbetaling) print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"indbetaling\" VALUE=\"Indbetaling\" OnClick=\"pos_ordre.$fokus.value += 'i';pos_ordre.$fokus.focus();\"></TD>\n";
	} else {
#		print "<input type=\"hidden\" name=\"bon\" value = \"\">\n";
		$stil2="STYLE=\"width: 9.5em;height: 2em;font-size:150%;\"";
		print "<TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"udskriv\"VALUE=\"Udskriv\"></TD>\n";
		print "<TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"ny\"VALUE=\"Ny kunde\"></TD></TR>\n";
		print "<TD COLSPAN=\"4\"><br></TD></TR>\n";
		print "<TD COLSPAN=\"4\"><br></TD></TR>\n";
		print "<TD COLSPAN=\"4\" align=\"center\"><INPUT TYPE=\"submit\" $stil2 NAME=\"krediter\"VALUE=\"Korrektion\"></TD>\n";
#		print "<TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"ny\"VALUE=\"Ny kunde\"></TD>\n";
		if ($timeout && !$bon) print "<meta http-equiv=\"refresh\" content=\"$timeout;URL=pos_ordre.php?id=0\">\n";
	}
	print "</tr>\n";
	print "</TBODY></TABLE></TD></tr>\n";
	print "\n<!-- Function tastatur (slut)-->\n";
}

function menubuttons($id,$menu_id,$vare_id) {
	global $bgcolor2;
	global $fokus;
	global $pris_ny;
	global $varenr_ny;
	global $bordnr;
	global $kasse;
	
	
	$kasse=trim($kasse);
	$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
	$kasseantal=$r['box1']*1;
	$afd=explode(chr(9),$r['box3']);
	$tmp=$kasse-1;
	$r = db_fetch_array(db_select("select * from grupper where art = 'AFD' and kodenr='$afd[$tmp]'",__FILE__ . " linje " . __LINE__));
	$afd=$r['beskrivelse'];

	$tid=date("H:i");
	if (!$menu_id && $afd) {
		$r=db_fetch_array(db_select("select kodenr from grupper where art='POSBUT' and box1='$afd' and (box7 < box8) and (box7<='$tid' and box8>='$tid')",__FILE__ . " linje " . __LINE__));
		$menu_id=$r['kodenr'];
	}
	if (!$menu_id) {
		$r=db_fetch_array(db_select("select kodenr from grupper where art='POSBUT' and (box7 < box8) and (box7<='$tid' and box8>='$tid')",__FILE__ . " linje " . __LINE__));
		$menu_id=$r['kodenr'];
		if (!$menu_id) { #her tages højde for at slut tidspkt kan være mindre en starttidspkt
			$r=db_fetch_array(db_select("select kodenr from grupper where art='POSBUT' and (box7 > box8) and ((box7>='$tid' and box8>='$tid') or (box7<='$tid' and box8<='$tid'))",__FILE__ . " linje " . __LINE__));
			$menu_id=$r['kodenr'];
		}
	}
	$r=db_fetch_array(db_select("select * from grupper where art='POSBUT' and kodenr='$menu_id'",__FILE__ . " linje " . __LINE__));
	$menuid=$r['kodenr'];
	$beskrivelse=$r['box1'];
	$cols=$r['box2'];
	$rows=$r['box3'];
	$height=$r['box4'];
	$width=$r['box5'];
	$fontsize=$r['box10'];
	if (!$fontsize) $fontsize=$height*$width/200;
/*
	print "<style type=\"text/css\">\n";
	print "table a {display:block;width:100%;height:100%;}";
	print "</style>\n";
*/
#print "
#";
	print "<table border=\"0\" cellspacing=\"5\" cellpadding=\"1\"><tbody>\n"; # table 1 ->
	print "<tr><td colspan=\"$cols\" align=\"center\" bgcolor=\"$bgcolor2\">$beskrivelse</td></tr>\n";

for ($x=1;$x<=$rows;$x++) {
	print "<tr>\n";
	for ($y=1;$y<=$cols;$y++) {
#		menu_id,row,col,beskrivelse,color,funktion,vare_id,colspan,rowspan
		$r=db_fetch_array(db_select("select * from pos_buttons where menu_id=$menuid and row='$x' and col='$y'",__FILE__ . " linje " . __LINE__));
		$a=$r['beskrivelse'];
		$b=$r['color'];
		$c=$r['vare_id']*1;
		$d=$r['funktion']*1;
		if ($a) {
			$knap="<input type=\"button\" style=\"width:".$width."px;height:".$height."px;text-align:center;font-size:".$fontsize."px; background-color:#$b;\" value= \"$a\">\n";
			print "<td>\n";
#			($fokus='antal')?$vnr=varenr_ny=$varenr_ny:$vnr=varenr_ny=$varenr_ny;
			if (!$d || $d==1) print "<a style=\"text-decoration:none\" href=pos_ordre.php?id=$id&menu_id=$menu_id&vare_id=$vare_id&vare_id_ny=$c&varenr_ny=$varenr_ny&pris_ny=$pris_ny&fokus=$fokus&bordnr=$bordnr>$knap</a>\n";
			elseif ($d==2) print "<a style=\"text-decoration:none\" href=pos_ordre.php?id=$id&vare_id=$vare_id&menu_id=$c&varenr_ny=$varenr_ny&pris_ny=$pris_ny&fokus=$fokus&bordnr=$bordnr>$knap</a>\n";
			elseif ($d==3) print "<a style=\"text-decoration:none\" href=pos_ordre.php?id=$id&konto_id=$c&varenr_ny=$varenr_ny&pris_ny=$pris_ny&fokus=$fokus&bordnr=$bordnr>$knap</a>\n";
			elseif ($d==4) print "<a style=\"text-decoration:none\" href=pos_ordre.php?id=$id&spec_func=spec_$c&varenr_ny=$varenr_ny&pris_ny=$pris_ny&fokus=$fokus&bordnr=$bordnr>$knap</a>\n";
			print "</td>\n";
		}
	}
	print "</tr>\n";
}
print "</tbody></table>\n"; # <- table 1
} # function menubuttons

function fejl ($id,$fejltekst) {
  print "<BODY onLoad=\"javascript:alert('$fejltekst')\">\n";
  print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">\n";

}

function posbogfor ($kasse,$regnstart) {
	global $afd;
	global $brugernavn;

#cho $_POST['udtages']."<br>";
	$udtages=if_isset($_POST['udtages']);
	if ($udtages) $udtages=usdecimal($udtages)*1;  

#cho "select ansat_id from brugere where brugernavn = '$brugernavn'<br>";
	$r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
	$ansat_id=$r['ansat_id'];

	$r=db_fetch_array(db_select("select box2,box3 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$kassekonti=explode(chr(9),$r['box2']);
	$kassekonto=$kassekonti[$kasse-1];
	$afdelinger=explode(chr(9),$r['box3']);
	$afd=$afdelinger[$kasse-1]*1;
	
	$r=db_fetch_array(db_select("select box8 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$mellemkonti=explode(chr(9),$r['box8']);
	$mellemkonto=$mellemkonti[$kasse-1];

	$x=0;
#cho "select distinct(fakturadate) as fakturadate from ordrer where felt_5='$kasse' and konto_id= '0' and art = 'PO' and status='3' and fakturadate >= '$regnstart' order by fakturadate<br>\n";
	$q=db_select("select distinct(fakturadate) as fakturadate from ordrer where felt_5='$kasse' and (konto_id='0' or betalingsbet='Kontant') and art = 'PO' and status='3' and fakturadate >= '$regnstart' order by fakturadate",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['fakturadate']) {
			$x++;
			$fakturadate[$x]=$r['fakturadate'];
#cho "$fakturadate[$x]<br>\n";
		}
	}
	$x=0;
#cho "select distinct(felt_1) as betaling from ordrer where felt_5='$kasse' and konto_id= '0' and art = 'PO' and status='3' and fakturadate >= '$regnstart' order by felt_1<br>\n";
	$q=db_select("select distinct(felt_1) as betaling from ordrer where felt_5='$kasse' and (konto_id='0' or betalingsbet='Kontant') and art = 'PO' and status='3' and fakturadate >= '$regnstart' order by felt_1",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['betaling']) {
			$x++;
			$betaling[$x]=$r['betaling'];
#cho "$betaling[$x]<br>\n";
		}
	}
	$x=0;
#cho "select distinct(felt_3) as betaling2 from ordrer where felt_5='$kasse' and konto_id= '0' and art = 'PO' and status='3' and fakturadate >= '$regnstart' order by felt_3<br>\n";
	$q=db_select("select distinct(felt_3) as betaling2 from ordrer where felt_5='$kasse' and (konto_id='0' or betalingsbet='Kontant') and art = 'PO' and status='3' and fakturadate >= '$regnstart' order by felt_3",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['betaling2']) {
			$x++;
			$betaling2[$x]=$r['betaling2'];
		}
	}
	for ($x=1;$x<=count($fakturadate);$x++) {
		for ($y=0;$y<=count($betaling);$y++) {
			for ($z=0;$z<=count($betaling2);$z++) {
				$id=NULL;
				$q=db_select("select id from ordrer where felt_5='$kasse' and fakturadate='$fakturadate[$x]' and felt_1='$betaling[$y]' and felt_3='$betaling2[$z]' and (konto_id='0' or betalingsbet='Kontant') and art = 'PO' and status='3'",__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					if($id) $id.=",".$r['id'];
					else $id=$r['id'];
				}
				$r = db_fetch_array(db_select("select box9 from grupper where art='POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
				if($id) {
					transaktion('begin');
					$svar=bogfor_nu("$id","Dagsafslutning");
					if ($svar && $svar!='OK') {
#cho "$svar<br>";
						print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverenstemmelse i posteringssummen. \\nKontakt Danosoft på telefon 4690 2208 eller 2066 9820')\">\n";
						print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
					} else transaktion('commit');
				}
			}
		}
	}
	if ($kassekonto && $mellemkonto && $udtages) {
		$dd=date("Y-m-d");
		$logtime=date("H:i");
		if ($udtages>0) {$debet=0;$kredit=$udtages;}
		else {$debet=$udtages;$kredit=0;}
		db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('0','$dd','Overført til mellemkonto fra kasse $kasse','$kassekonto','0','$debet','$kredit',0,'$afd','$dd','$logtime','','$ansat_id','0')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('0','$dd','Overført til mellemkonto fra kasse $kasse','$mellemkonto','0','$kredit','$debet',0,'$afd','$dd','$logtime','','$ansat_id','0')",__FILE__ . " linje " . __LINE__);
	}
}

function kasseoptalling ($kasse,$optalt,$ore_50,$kr_1,$kr_2,$kr_5,$kr_10,$kr_20,$kr_50,$kr_100,$kr_200,$kr_500,$kr_1000,$kr_andet) {
		#print "</FORM><form name=tastatur action=pos_ordre.php?id=$id method=post>\n";
#	list($byttepenge,$tilgang,$diff,$kortantal,$kortkonto,$kortnavn,$kortsum)=explode(chr(9),find_kassesalg($kasse,$optalt));

	$svar=find_kassesalg($kasse,$optalt);
	$byttepenge=$svar[0];
	$tilgang=$svar[1];
	$diff=$svar[2];
	$kortantal=$svar[3];
	$kontkonto=explode(chr(9),$svar[4]);
	$kortnavn=explode(chr(9),$svar[5]);
	$kortsum=explode(chr(9),$svar[6]);

	$omsatning=$tilgang;
	
	$r=db_fetch_array(db_select("select box8 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$mellemkonti=explode(chr(9),$r['box8']);
	$mellemkonto=$mellemkonti[$kasse-1];

	print "<form name=\"optael\" action=\"pos_ordre.php?id=$id&kasse=$kasse&kassebeholdning=on&bordnr=$bordnr\" method=\"post\" autocomplete=\"off\">\n"; 

	print "<input type=\"hidden\" name=\"byttepenge\" value=\"$byttepenge\">";
	print "<input type=\"hidden\" name=\"optalt\" value=\"$optalt\">";
	print "<input type=\"hidden\" name=\"tilgang\" value=\"$tilgang\">";
	for ($x=0;$x<count($kontkonto);$x++) {
		print "<input type=\"hidden\" name=\"kontkonto[$x]\" value=\"$kontkonto[$x]\">";
		print "<input type=\"hidden\" name=\"kortnavn[$x]\" value=\"$kortnavn[$x]\">";
		print "<input type=\"hidden\" name=\"kortsum[$x]\" value=\"$kortsum[$x]\">";
		$omsatning+=$kortsum[$x];
	}
	print "<input type=\"hidden\" name=\"omsatning\" value=\"$omsatning\">";

	print "<table><tbody>\n";
	print "<tr><td colspan=\"3\" align=\"center\"><b><big>Optæl kassebeholdning for kasse $kasse</big></b></td></tr>\n";
	print "<tr><td colspan=\"3\" align=\"center\">(Antal mønter/sedler af hver slags)</td></tr>\n";
	print "<tr><td align=\"right\">50</td><td>øre</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"ore_50\" value=\"$ore_50\"></td></tr>\n";
	print "<tr><td align=\"right\">1</td><td>kr</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"kr_1\" value=\"$kr_1\"></td></tr>\n";
	print "<tr><td align=\"right\">2</td><td>kr</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"kr_2\" value=\"$kr_2\"></td></tr>\n";
	print "<tr><td align=\"right\">5</td><td>kr</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"kr_5\" value=\"$kr_5\"></td></tr>\n";
	print "<tr><td align=\"right\">10</td><td>kr</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"kr_10\" value=\"$kr_10\"></td></tr>\n";
	print "<tr><td align=\"right\">20</td><td>kr</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"kr_20\" value=\"$kr_20\"></td></tr>\n";
	print "<tr><td align=\"right\">50</td><td>kr</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"kr_50\" value=\"$kr_50\"></td></tr>\n";
	print "<tr><td align=\"right\">100</td><td>kr</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"kr_100\" value=\"$kr_100\"></td></tr>\n";
	print "<tr><td align=\"right\">200</td><td>kr</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"kr_200\" value=\"$kr_200\"></td></tr>\n";
	print "<tr><td align=\"right\">500</td><td>kr</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"kr_500\" value=\"$kr_500\"></td></tr>\n";
	print "<tr><td align=\"right\">1000</td><td>kr</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"kr_1000\" value=\"$kr_1000\"></td></tr>\n";
	print "<tr><td align=\"right\">Andet</td><td>kr</td> <td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"kr_andet\" value=\"".dkdecimal($kr_andet)."\"></td></tr>\n";
	print "<tr><td align=\"center\" colspan=\"3\"><input type=\"submit\" name=\"optael\" value=\"Beregn\">&nbsp;<input type=\"submit\" name=\"optael\" value=\"Fortryd\"></td></tr>\n";
	print "<tr><td colspan=\"2\"><b>Morgenbeholdning</b></td><td align=\"right\"><b>".dkdecimal($byttepenge)."</b></td></tr>\n";
	print "<tr><td colspan=\"2\"><b>Dagens tilgang</b></td><td align=\"right\"><b>".dkdecimal($tilgang)."</b></td></tr>\n";
	print "<tr><td colspan=\"2\"><b>Forventet beholdning</b></td><td align=\"right\"><b>".dkdecimal($byttepenge+$tilgang)."</b></td></tr>\n";
	print "<tr><td colspan=\"2\"><b>Optalt beholdning</b></td><td align=\"right\"><b>".dkdecimal($optalt)."</b></td></tr>\n";
	print "<tr><td colspan=\"2\"><b>Difference</b></td><td align=\"right\"><b>".dkdecimal($optalt-($byttepenge+$tilgang))."</b></td></tr>\n";
		if ($optalt)	{
		if ($mellemkonto) print "<tr><td colspan=\"2\"><b>Udtages fra kasse</b></td><td align=\"right\"><input style=\"width:100;text-align:right;\" name=\"udtages\" value=\"".dkdecimal(pos_afrund($optalt-$byttepenge))."\"></td></tr>\n";
		else ($udtages=0);
		print "<tr><td align=\"center\" colspan=\"3\"><input type=\"submit\" name=\"optael\" value=\"Godkend\"></td></tr>\n";
	}
	for ($x=0;$x<count($kontkonto);$x++) {
		print "<tr><td colspan=\"2\"><b>$kortnavn[$x]</b></td><td align=\"right\"><b>".dkdecimal($kortsum[$x])."</b></td></tr>\n";
	}
	print "<tr><td colspan=\"2\"><b>Dagens omsætning</b></td><td align=\"right\"><b>".dkdecimal($omsatning)."</b></td></tr>\n";
	
	print "</tbody></table></form>\n";
	exit;
}

function kassebeholdning ($kasse,$optalt,$godkendt,$cookievalue) {
	global $bruger_id;
	global $brugernavn;
	global $db;
	global $db_encode;
#	global $printserver;
	global $regnaar;
	$dd=date("Y-m-d");
	$tid=date("H:m");
	
	if (!$cookievalue) $cookievalue=$_COOKIE['saldi_kasseoptael'];
	list ($ore_50,$kr_1,$kr_2,$kr_5,$kr_10,$kr_20,$kr_50,$kr_100,$kr_200,$kr_500,$kr_1000,$kr_andet) = explode(chr(9),$cookievalue);
	
	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$byttepenge=$r['box1'];
	$optalassist=$r['box2'];
	$printer_ip=explode(chr(9),$r['box3']);
	$printserver=$printer_ip[$kasse-1];
	if (!$printserver)$printserver='localhost';

	if (!$godkendt && $optalassist) kasseoptalling ($kasse,$optalt,$ore_50,$kr_1,$kr_2,$kr_5,$kr_10,$kr_20,$kr_50,$kr_100,$kr_200,$kr_500,$kr_1000,$kr_andet);
	
	$r=db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr = '$regnaar'",__FILE__ . " linje " . __LINE__));
	$startmd=$r['box1'];
	$startaar=$r['box2'];
	
#cho "startmd $startmd startaar $startaar<br>\n";
	$regnstart=$startaar."-".$startmd."-01";
	
	$r=db_fetch_array(db_select("select box9 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	if (!$r['box9']) posbogfor($kasse,$regnstart);

 	include("../includes/ConvertCharset.class.php");
	if ($db_encode=="UTF8") $FromCharset = "UTF-8";
	else $FromCharset = "iso-8859-15";
	$ToCharset = "cp865";
	$convert = new ConvertCharset();

	$pfnavn="../temp/".$db."/".$bruger_id.".txt";
	$fp=fopen("$pfnavn","w");
	$kassopgorelse="KASSEOPGØRELSE";
	$tmp = $convert ->Convert($kassopgorelse, $FromCharset, $ToCharset);

	fwrite($fp,"\n\n$tmp\n\n");
	fwrite($fp,"Den $dd kl. $tid\n");
	fwrite($fp,"Kasse nr: $kasse\n");
	$tmp = $convert ->Convert($brugernavn, $FromCharset, $ToCharset);
	fwrite($fp,"Optalt af: $tmp\n");
	
	if ($optalassist) {
		$byttepenge=if_isset($_POST['byttepenge']);
		$tilgang=if_isset($_POST['tilgang']);
		$optalt=if_isset($_POST['optalt']);
		$omsatning=if_isset($_POST['omsatning']);
		$udtages=if_isset($_POST['udtages']);
		$kontkonto=if_isset($_POST['kontkonto']);
		$kortnavn=if_isset($_POST['kortnavn']);
		$kortsum=if_isset($_POST['kortsum']);
#		fwrite($fp,"Optalt kassebeholdning: ".dkdecimal($optalt)."\n\n");
#		fwrite($fp,"Differece $prefix".dkdecimal($diff)."\n\n");
#		fwrite($fp,"Optalt tilgang i kasse: ".dkdecimal($optalt-$byttepenge)."\n\n");
		$tmp = $convert ->Convert('50 øre', $FromCharset, $ToCharset);
		fwrite($fp,"  $tmp:  $ore_50\n");
		fwrite($fp,"    1 kr:  $kr_1\n");
		fwrite($fp,"    2 kr:  $kr_2\n");
		fwrite($fp,"    5 kr:  $kr_5\n");
		fwrite($fp,"   10 kr:  $kr_10\n");
		fwrite($fp,"   20 kr:  $kr_20\n");
		fwrite($fp,"   50 kr:  $kr_50\n");
		fwrite($fp,"  100 kr:  $kr_100\n");
		fwrite($fp,"  200 kr:  $kr_200\n");
		fwrite($fp,"  500 kr:  $kr_500\n");
		fwrite($fp," 1000 kr:  $kr_1000\n");
		fwrite($fp,"Andet kr:  ".dkdecimal($kr_andet)."\n\n");
		$txt1="Morgenbeholdning:";
		$txt2=dkdecimal($byttepenge);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		$txt1="Dagens tilgang:";
		$txt2=dkdecimal($tilgang);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		$txt1="Forventet beholdning:";
		$txt2=dkdecimal($byttepenge+$tilgang);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		$txt1="Optalt beholdning:";
		$txt2=dkdecimal($optalt);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		$txt1="Difference:";
		$txt2=dkdecimal($optalt-($byttepenge+$tilgang));
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		$txt1="Udtaget fra kasse $kasse:";
		$txt2=dkdecimal($udtages);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n\n\n\n");
		fwrite($fp,"Underskrift:______________________\n\n");
	} else {
		$svar=find_kassesalg($kasse,$optalt);
		$byttepenge=$svar[0];
		$tilgang=$svar[1];
		$diff=$svar[2];
		$kortantal=$svar[3];
		$kontkonto=explode(chr(9),$svar[4]);
		$kortnavn=explode(chr(9),$svar[5]);
		$kortsum=explode(chr(9),$svar[6]);
	
		fwrite($fp,"Beholdning primo: ".dkdecimal($byttepenge)."\n\n");
		fwrite($fp,"Dagens indbetalinger: ".dkdecimal($tilgang)."\n\n");
		fwrite($fp,"Beholdning ultimo: ".dkdecimal($byttepenge+$tilgang)."\n\n");
	}
	for ($x=0;$x<count($kortnavn);$x++) {
		$txt1="$kortnavn[$x]";
		$txt2=dkdecimal($kortsum[$x]);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
#			fwrite($fp,"\nSalg paa kort:\n");
		fwrite($fp,"$txt1$txt2\n");
	}
	fwrite($fp,"\n\n\n");

	fclose($fp);
	$tmp="/temp/".$db."/".$bruger_id.".txt";
	$url="://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
	$url=str_replace("/debitor/pos_ordre.php","",$url);
	if ($_SERVER[HTTPS]) $url="s".$url;
	$url="http".$url;
	print "<BODY onLoad=\"JavaScript:window.open('http://$printserver/saldiprint.php?printfil=$tmp&url=$url&bruger_id=$bruger_id&bonantal=1' , '' , '$jsvars');\">\n";

}
function flyt_bord($id,$bordnr,$delflyt) { #20140508
	global $brugernavn;
	global $s_id;
	global $db;

	echo "Klik på et af nedenstående borde for at flytte gæsterne fra $bordnr:<br>";
	print "<a href=\"pos_ordre.php?id=$id&bordnr=$bordnr\">Fortryd</a><br>";
	
	$x=0;
	$optaget=array();
	$q=db_select("select id,nr from ordrer where art = 'PO' and status<'3'",__FILE__ . " linje " . __LINE__); 
	while($r=db_fetch_array($q)){
		if($r['nr'] && $r2=db_fetch_array(db_select("select id from ordrelinjer where ordre_id='$r[id]'",__FILE__ . " linje " . __LINE__))){ 
			$optaget[$x]=$r['nr'];
#cho "$r[id] Optaget $x -> $optaget[$x]<br>";
			$x++;
		}
	}

	$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr='2'",__FILE__ . " linje " . __LINE__)); 
	$bord=explode(chr(9),$r['box7']); #20140507
	for ($x=0;$x<count($bord);$x++){
		$tmp=$x+1;
		if (!in_array($tmp,$optaget) || $delflyt) {
			if ($delflyt) print "<a href=\"pos_ordre.php?id=$id&flyt_til=$tmp&delflyt=$delflyt\">$bord[$x]</a><br>";
			else print "<a href=\"pos_ordre.php?id=$id&flyt_til=$tmp\">$bord[$x]</a><br>";
		}
	}
	exit;
}
function kundedisplay($beskrivelse,$pris,$ryd){
#cho "Incl $incl_moms<br>";
	global $kasse;
	global $printserver;
	
	$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
if ($fp=fopen($filnavn,'r')) {
	$printserver=trim(fgets($fp));
	fclose ($fp);
}

	
#	$printserver='localhost';
	if ($kundedisplay && !$printserver) {
		$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
		$printer_ip=explode(chr(9),$r['box3']);
		$tmp=$kasse-1;
		$printserver=$printer_ip[$tmp];
		if (!$printserver)$printserver='localhost';
	}

	print "<BODY onLoad=\"JavaScript:window.open('http://localhost/kundedisplay.php?&antal=$antal&tekst=".urlencode($beskrivelse)."&pris=".dkdecimal($pris)."&ryd=$ryd','','width=200,height=100,top=1024,left=1280');\">\n";
}

function find_kassesalg($kasse,$optalt) {
	global $regnaar;
	global $straksbogfor;

	$dd=date("Y-m-d");
	
	$r=db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr = '$regnaar'",__FILE__ . " linje " . __LINE__));
	$startmd=$r['box1'];
	$startaar=$r['box2'];
	
#cho "startmd $startmd startaar $startaar<br>\n";
	$regnstart=$startaar."-".$startmd."-01";
	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$kassekonti=explode(chr(9),$r['box2']);
	$kortantal=$r['box4']*1;
	$kortnavne=$r['box5'];
	$kortnavn=explode(chr(9),$kortnavne);
	$kortkonti=$r['box6'];
	$kortkonto=explode(chr(9),$kortkonti);
	$straksbogfor=$r['box9'];

	for ($x=0;$x<count($kortnavn);$x++) $kortsum[$x]=0;
	
	
	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$byttepenge=$r['box1'];
#cho "byt $byttepenge<br>\n"; 
#$byttepenge='';
	$k=$kasse-1;
#cho "select primo from kontoplan where regnskabsaar = '$regnaar' and kontonr = '$kassekonti[$k]'<br>\n";
	if (!$byttepenge) {
		$r=db_fetch_array(db_select("select primo from kontoplan where regnskabsaar = '$regnaar' and kontonr = '$kassekonti[$k]'",__FILE__ . " linje " . __LINE__));
		$byttepenge=$r['primo'];
#cho "select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate < '$dd' and transdate >= '$regnstart' and kontonr = '$kassekonti[$k]'<br>\n";
		$r=db_fetch_array(db_select("select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate < '$dd' and transdate >= '$regnstart' and kontonr = '$kassekonti[$k]' and kasse_nr='$kasse'",__FILE__ . " linje " . __LINE__));
		$byttepenge+=$r['debet']-$r['kredit'];
#cho "byt $byttepenge<br>\n"; 
	}	
	$r = db_fetch_array(db_select("select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate = '$dd' and kontonr = '$kassekonti[$k]' and kasse_nr='$kasse'",__FILE__ . " linje " . __LINE__));
	$tilgang=$r['debet']-$r['kredit'];

	if (!$straksbogfor) {
#cho "select sum(sum+moms) as sum from ordrer where  status = '3' and art = 'PO' and fakturadate <= '$dd' and felt_5='$kasse'<br>";
		$q=db_select("select * from ordrer where  status = '3' and art = 'PO' and fakturadate <= '$dd' and felt_5='$kasse'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$retur=(($r['felt_2']+$r['felt_4'])-($r['sum']+$r['moms']));
			if ($r['felt_1']=='Kontant') $tilgang+=$r['felt_2']-$retur;
			if ($r['felt_3']=='Kontant') $tilgang+=$r['felt_4']-$retur;
			for ($x=0;$x<count($kortnavn);$x++) {
				if ($r['felt_1']==$kortnavn[$x]) $kortsum[$x]+=$r['felt_2'];
				if ($r['felt_3']==$kortnavn[$x]) $kortsum[$x]+=$r['felt_4'];
			}
		}
	}
	if ($kortantal) {
#		$kortsum[]=0;
#		fwrite($fp,"\n\nSalg paa kort\n\n");
		for ($x=0;$x<$kortantal;$x++) {
			if ($kortkonto[$x]) {
#cho "select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate = '$dd' and kontonr = '$kortkonto[$x]' and kasse_nr = '$kasse'<br>\n";		
				$r = db_fetch_array(db_select("select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate = '$dd' and kontonr = '$kortkonto[$x]' and kasse_nr = '$kasse'",__FILE__ . " linje " . __LINE__));
				$kortsum[$x]+=dkdecimal($r['debet']-$r['kredit']);
			}
		}
	}
#	$kassesum=dkdecimal($byttepenge+$tilgang);
#	$byttepenge=dkdecimal($byttepenge);
#	$tilgang=dkdecimal($tilgang);

	$diff=$optalt-($byttepenge+$tilgang);
	($diff<0)?$prefix=NULL:$prefix="+";

	$kortsummer=$kortsum[0];
	for ($x=1;$x<$kortantal;$x++) $kortsummer.=chr(9).$kortsum[$x];
	
	
	return array($byttepenge,$tilgang,$diff,$kortantal,$kortkonti,$kortnavne,$kortsummer);
} # endfunc find_kassesalg

if (!$varenr_ny && $fokus!='modtaget' && $fokus!='modtaget2' && $fokus!='indbetaling') $fokus="varenr_ny";
#cho "fokus $fokus<br>\n";
?>
</body></html>
<script language="javascript">
document.pos_ordre.<?php echo $fokus?>.focus();
</script>
