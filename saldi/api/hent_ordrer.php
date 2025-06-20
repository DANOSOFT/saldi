<?php
// ------------- api/hent_ordrer.php ---------- lap 3.5.6----2015.06.08-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// dog under iagttagelse af følgende:
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

@session_start();
$s_id=session_id();

print "<html>";
print "<head><title>Hent shop_ordrer</title><meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8;\">";
print "<meta http-equiv=\"content-language\" content=\"da\">";
print "<meta name=\"google\" content=\"notranslate\">";
print "</head><body>";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

(isset($_GET['shop_db']))?$shop_db=$_GET['shop_db']:$shop_db=NULL;
(isset($_SERVER['HTTP_REFERER']))?list($shopurl,$null)=explode("/hent_ordrer.php",$_SERVER['HTTP_REFERER']):$shopurl=NULL;

$r=db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
$encoding=$r['box7'];

$prefix=$_GET['prefix'];
$nye_ordrer=$_GET['nye_ordrer'];
$fp=fopen("$db.lock",'w');
fwrite($fp,date("U")."\n");
fclose($fp);
$fp=fopen("$db.csv",'a');
fwrite($fp,$nye_ordrer."\n");
fclose($fp);
unlink("$db.lock");

$gruppe=1;
$ordreliste=array();

$lockfil="../api/$db.lock";
$x=0;
if (file_exists($lockfil)) {
	if ($fp=fopen($lockfil,"r")) { 
		$a=fgets($fp);
		fclose($fp);
		if (date("U")-$a>3) unlink($lockfil);
	}
}
while (file_exists($lockfil)) {
	sleep(2);
	$x++;
	if ($x>10) {
		echo "Importfejl prøv igen senere<bt>";
		exit;
	}
}
$fp=fopen($lockfil,"w");
fwrite($fp,date("U"));
fclose($fp);

$filnavn="../api/$db.csv";
if (file_exists($filnavn)) {
	$fp=fopen($filnavn,'r');
	$ordreliste=explode(",",fread($fp,filesize($filnavn)));
	fclose($fp);
	for ($x=0;$x<count($ordreliste);$x++) {
		$ordreliste[$x]*=1;
		if ($ordreliste[$x]) {
			overfoer_data($shopurl,$ordreliste[$x]);
		} else echo $shopurl."/".$ordreliste[$x]." ikke fundet<br>";
	}
	fclose($fp);
}
unlink($lockfil);
unlink($filnavn);
$alerttxt=count($ordreliste)." ordrer importeret fra shop";
$r=db_fetch_array(db_select("select box2 from grupper where art='DIV' and kodenr='5'",__FILE__ . " linje " . __LINE__));
if ($url=$r['box2']) {
	if (substr($url,0,4)=='http') {
		$url=str_replace("/?","/opdat_ordrer.php?",$url);
#		$url=$url."&saldi_db=$db";
		$saldiurl="://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		if ($_SERVER['HTTPS']) $saldiurl="s".$saldiurl;
		$saldiurl="http".$saldiurl;
		$url.="&saldiurl=$saldiurl";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$url&prefix=$prefix&ordreliste=$nye_ordrer&status=2\">";
	}
}

exit;
#print "<body onload=\"javascript:alert('$alerttxt');window.close();\">";

function overfoer_data($shopurl,$shop_ordre_id){
	global $charset;
	global $gruppe;
	global $encoding;
	global $prefix;

	$filnavn=trim($shopurl)."/".$prefix."_".trim($shop_ordre_id);
	$betalingsbet='Netto';
	$betalingsdage=8;
	$fp=fopen($filnavn,'r');
	$x=0;
	$y=0;
	while($linje=fgets($fp)) {
		if ($encoding!='UTF-8') $linje=utf8_encode($linje);
		$linje=db_escape_string($linje);
		if ($x==0) {
			list($date,$ordre_fornavn,$ordre_efternavn,$ordre_email,$ordresum,$forsendelse,$vaegt,$valuta,$betaling)=explode(chr(9),$linje);
		} elseif ($x==1) {
			list($shop_konto_id,$firmanavn,$fornavn,$efternavn,$adresse,$postnr,$bynavn,$land,$tlf,$cvrnr,$email)=explode(chr(9),$linje);
			} elseif ($x==2) {
			list($lev_konto_id,$lev_firmanavn,$lev_fornavn,$lev_efternavn,$lev_adresse,$lev_postnr,$lev_bynavn,$lev_land,$lev_tlf,$lev_cvrnr,$lev_email)=explode(chr(9),$linje);
		} elseif (trim($linje)) {
			list($shop_vare_id[$y],$varenr[$y],$antal[$y],$beskrivelse[$y],$pris[$y])=explode(chr(9),$linje);
			$y++;
		}
		$x++;
	}
	fclose($fp);
	$shop_konto_id*=1;
	$firmanavn=trim($firmanavn);
	$fornavn=trim($fornavn);
	$efternavn=trim($efternavn);
	$adresse=trim($adresse);
	$postnr=trim($postnr);
	$bynavn=trim($bynavn);
	$land=trim($land);
	$tlf=trim($tlf);
	$cvrnr=trim($cvrnr);
	$email=trim($email);
	if (!$fornavn) $fornavn=$ordre_fornavn;
	if (!$efternavn) $efternavn=$ordre_efternavn;
	if (!$email) $email=$ordre_email;
	if (!$firmanavn) $firmanavn=$fornavn." ".$efternavn;
	$tlf=str_replace(" ","",$tlf);
	
#xit;
	$r=db_fetch_array (db_select("select saldi_id from shop_adresser where shop_id='$shop_konto_id'",__FILE__ . " linje " . __LINE__));
	$saldi_id=$r['saldi_id'];
	$qtxt="select id from shop_ordrer where shop_id='$shop_ordre_id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['id']) {
		return;
		exit;
	}
	transaktion("begin");
	
		if (!$saldi_id) {
		$qtxt="select id from adresser where art = 'D' and lower(firmanavn)='".db_escape_string(strtolower($firmanavn))."' and tlf='$tlf'<br>";
		$r=db_fetch_array (db_select("select id from adresser where art = 'D' and lower(firmanavn)='".db_escape_string(strtolower($firmanavn))."' and tlf='$tlf'",__FILE__ . " linje " . __LINE__));
		$saldi_id=$r['id'];
		if ($saldi_id) {
			db_modify("insert into shop_adresser(saldi_id,shop_id)values('$saldi_id','$shop_konto_id')",__FILE__ . " linje " . __LINE__);  
		} else {
			$x=0;
			$q=db_select("select kontonr from adresser where art = 'D' order by kontonr",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				$ktonr[$x]=$r['kontonr'];
				$x++;
			}
			$kontonr=1000;
			while(in_array($kontonr,$ktonr)) $kontonr++;
			db_modify("insert into adresser(kontonr,firmanavn,addr1,postnr,bynavn,land,cvrnr,email,tlf,gruppe,art,betalingsbet,betalingsdage) values ('$kontonr','".db_escape_string($firmanavn)."','".db_escape_string($adresse)."','".db_escape_string($postnr)."','".db_escape_string($bynavn)."','".db_escape_string($land)."','".db_escape_string($cvrnr)."','".db_escape_string($email)."','".db_escape_string($telefon)."','$gruppe','D','$betalingsbet','$betalingsdage')",__FILE__ . " linje " . __LINE__);
		}
		$r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__));
		$saldi_id=$r['id'];
		db_modify("insert into shop_adresser(saldi_id,shop_id)values('$saldi_id','$shop_konto_id')",__FILE__ . " linje " . __LINE__);  
	} else {
		$r=db_fetch_array(db_select("select kontonr from adresser where id = '$saldi_id'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['kontonr'];
	}
	$qtxt="select max(ordrenr) as ordrenr from ordrer where art='DO'";
	$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
	$ordrenr=$r['ordrenr']+1;
	$projektnr=0;
	$qtxt="select box1 from grupper where art='DG' and kodenr = '$gruppe'";
	$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
	$momsgruppe=str_replace('S','',$r['box1']);
	$qtxt="select box2 from grupper where art='SM' and kodenr = '$momsgruppe'";
	$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
	$momssats=$r['box2']*1;
	if (!$valuta)$valuta='DKK';
	if ($valuta=='DKK') {
		$valutakurs=100;
	} else {
		$qtxt="select box2 from grupper where art='VK' and box1 = '$valuta'";
		if ($r=db_fetch_array(db_modify($qtxt,__FILE__ . " linje " . __LINE__))) $valutakurs=$r['box2']*1;
		else $valutakurs=100;
	}
	$qtxt="insert into ordrer(ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,email,art,projekt,momssats,betalingsbet,betalingsdage,status,ordredate,valuta,valutakurs) values ('$ordrenr','$saldi_id','$kontonr','".db_escape_string($firmanavn)."','".db_escape_string($adresse)."','".db_escape_string($addr2)."','".db_escape_string($postnr)."','".db_escape_string($bynavn)."','".db_escape_string($email)."','DO','$projektnr','$momssats','$betalingsbet','$betalingsdage','0','".date("Y-m-d")."','$valuta','$valutakurs')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$r=db_fetch_array(db_select("select max(id) as id from ordrer where kontonr='$kontonr'",__FILE__ . " linje " . __LINE__));
	$ordre_id=$r['id'];
	db_modify("insert into shop_ordrer(saldi_id,shop_id)values('$ordre_id','$shop_ordre_id')",__FILE__ . " linje " . __LINE__);  
	$posnr=0;
	for ($x=0;$x<count($shop_vare_id);$x++) {
		$r=db_fetch_array (db_select("select saldi_id from shop_varer where shop_id='$shop_vare_id[$x]'",__FILE__ . " linje " . __LINE__));
		$vare_id[$x]=$r['saldi_id'];
		if ($vare_id[$x]) {
			$r=db_fetch_array (db_select("select varenr from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
		}
		else {
	echo "select id from varer where varenr='$varenr[$x]' or stregkode='$varenr[$x]'<br>";
			$r=db_fetch_array(db_select("select id from varer where varenr='$varenr[$x]' or stregkode='$varenr[$x]'",__FILE__ . " linje " . __LINE__));
			$vare_id[$x]=$r['id'];
			if (!$vare_id[$x]) {
				$r=db_fetch_array(db_select("select id from varer where varenr='$shop_vare_id[$x]'",__FILE__ . " linje " . __LINE__));
				$vare_id[$x]=$r['id'];
			}	
			if (!$vare_id[$x]) {
				$r=db_fetch_array(db_select("select id from varer where beskrivelse='$beskrivelse[$x]'",__FILE__ . " linje " . __LINE__));
				$vare_id[$x]=$r['id'];
			}	
			if (!$vare_id[$x] && '$varenr[$x]' && '$beskrivelse') {
				db_modify("insert into varer(varenr,beskrivelse,salgspris,gruppe)values('$varenr[$x]','$beskrivelse[$x]','$pris[$x]','1')",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from varer where varenr='$varenr[$x]'",__FILE__ . " linje " . __LINE__));
				$vare_id[$x]=$r['id'];
			}
			db_modify("insert into shop_varer(saldi_id,shop_id)values('$vare_id[$x]','$shop_vare_id[$x]')",__FILE__ . " linje " . __LINE__);  
		}
		db_modify("update varer set publiceret='on' where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
		opret_ordrelinje($ordre_id,$vare_id[$x],$varenr[$x],$antal[$x],$beskrivelse[$x],$pris[$x],0,100,'DO','',$posnr,'0','on','','','0');
	}
	transaktion("commit");
}
print "</body></html>"
?>