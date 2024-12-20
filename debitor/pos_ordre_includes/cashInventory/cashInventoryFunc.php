<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/cashInventory/cashInventoryFunc.php ---------- lap 3.7.9----2019.05.09-------
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
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190309 LN Set the function kassebeholdning here

function kassebeholdning ($kasse,$optalt,$godkendt,$cookievalue) {
	global $bruger_id,$brugernavn;
	global $db,$db_encode;
	global $regnaar;
	global $vis_saet;
	
	$dd=date("Y-m-d");
	$tid=date("H:i");
	
	$r = db_fetch_array(db_select("select box6,box12 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$div_kort_kto=$r['box6'];
	$vis_saet=trim($r['box12']);
	
	if (!$cookievalue) $cookievalue=$_COOKIE['saldi_kasseoptael'];
	$tmparray=explode(chr(9),$cookievalue);
	$ore_50=$tmparray[0];
	$kr_1=$tmparray[1];
	$kr_2=$tmparray[2];
	$kr_5=$tmparray[3];
	$kr_10=$tmparray[4];
	$kr_20=$tmparray[5];
	$kr_50=$tmparray[6];
	$kr_100=$tmparray[7];
	$kr_200=$tmparray[8];
	$kr_500=$tmparray[9];
	$kr_1000=$tmparray[10];
	$kr_andet=$tmparray[11];
	$fiveRappen = $tmparray[12];
	$tenRappen = $tmparray[13];
	$twentyRappen = $tmparray[14];
	for ($x=15;$x<count($tmparray);$x++) {
		$optval[$x-15]=$tmparray[$x];
	}
	$r=db_fetch_array(db_select("select var_value from settings where var_name = 'change_cardvalue' limit 1",__FILE__ . " linje " . __LINE__));
	$change_cardvalue=$r['var_value'];
	
	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$byttepenge=$r['box1'];
	$optalassist=$r['box2'];
	$printer_ip=explode(chr(9),$r['box3']);
	$printserver=strtolower($printer_ip[$kasse-1]);
	if (!$printserver)$printserver='localhost';
	if ($printserver=='box' || $printserver=='saldibox') {
		$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
		if ($fp=fopen($filnavn,'r')) {
			$printserver=trim(fgets($fp));
			fclose ($fp);
			$printpopup=0;
		}
	} elseif (strtolower($printserver)=='popupbox') {
		$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
		if ($fp=fopen($filnavn,'r')) {
			$printserver=trim(fgets($fp));
			fclose ($fp);
			$printpopup=1;
		}
	} else $printpopup=1;
	if (!$godkendt && $optalassist) kasseoptalling ($kasse,$optalt,$ore_50,$kr_1,$kr_2,$kr_5,$kr_10,$kr_20,$kr_50,$kr_100,$kr_200,$kr_500,$kr_1000,$kr_andet,$optval, $fiveRappen, $tenRappen, $twentyRappen);
	$r=db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr = '$regnaar'",__FILE__ . " linje " . __LINE__));
	$startmd=$r['box1'];
	$startaar=$r['box2'];
	
	($startaar && $startmd)?$regnstart=$startaar."-".$startmd."-01":$regnstart='2000-01-01';
	
	if ($godkendt) posbogfor($kasse,$regnstart);

	$optval=if_isset($_POST['optval']);
	$valuta=if_isset($_POST['valuta']);
	$ValutaKasseDiff=if_isset($_POST['ValutaKasseDiff']);
	$ValutaByttePenge=if_isset($_POST['ValutaByttePenge']);
	$ValutaTilgang=if_isset($_POST['ValutaTilgang']);
	$ValutaUdtages=if_isset($_POST['ValutaUdtages']);
	
	if ($db_encode=="UTF8") $FromCharset = "UTF-8";
	else $FromCharset = "iso-8859-15";
	$ToCharset = "cp865";
	$pfnavn="../temp/".$db."/kasseopg".str_replace("-","",$kasse).".txt";
	$fp=fopen("$pfnavn","w");
	$logfil="../temp/".$db."/kasseopg".str_replace("-","",$kasse).".log";
	$log=fopen("$logfil","a");
    setPrintHeaderTxt($FromCharset, $ToCharset, $fp, $dd, $tid, $kasse, $brugernavn);
	if ($optalassist) {
        setPrintTxt($fp, $log, $FromCharset, $ToCharset, $ore_50, $kr_1, $kr_2, $kr_5, $kr_10, $kr_20, $kr_50, $kr_100, $kr_200, $kr_500, $kr_1000, $kr_andet, $valuta, $optval, $change_cardvalue);
	} else {
		$svar=find_kassesalg($kasse,$optalt,'DKK');
		$byttepenge=$svar[0];
		$tilgang=$svar[1];
		$diff=$svar[2];
		$kortantal=$svar[3];
		$kontkonto=explode(chr(9),$svar[4]);
		$kortnavn=explode(chr(9),$svar[5]);
		$kortsum=explode(chr(9),$svar[6]);
		$kontosum=$svar[7];
#		$kontosalg=$svar[8];
		$omsatning=$tilgang+$kontosum;
		for ($x=0;$x<count($kortnavn);$x++) {
			$omsatning+=$kortsum[$x];
		}
		$txt1 = iconv($FromCharset, $ToCharset,'Dagens omsætning');
		$txt2=dkdecimal($omsatning,2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		fwrite($log,"$txt1$txt2\n");
		$txt1="Beholdning primo:";
		$txt2=dkdecimal($byttepenge,2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		fwrite($log,"$txt1$txt2\n");
		$txt1="Dagens indbetalinger:";
		$txt2=dkdecimal($tilgang,2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		fwrite($log,"$txt1$txt2\n");
		$txt1="Beholdning ultimo:";
		$txt2=dkdecimal($byttepenge+$tilgang,2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n\n");
		fwrite($log,"$txt1$txt2\n\n");
	}
    setSignatureTxt($fp, $log);
	if ($kontosum) {
		$txt1="Salg på konto";
		$txt1 = iconv($FromCharset, $ToCharset,$txt1);
		$txt2=dkdecimal($kontosum,2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		fwrite($log,"$txt1$txt2\n");
	}
	for ($x=0;$x<count($kortnavn);$x++) {
		$txt1="$kortnavn[$x]";
		$txt2=dkdecimal($kortsum[$x],2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		if ($kortnavn[$x]) {
			fwrite($fp,"$txt1$txt2\n");
			fwrite($log,"$txt1$txt2\n");
		}
	}
	fwrite($fp,"\n\n\n");
	fwrite($log,"\n\n\n");

	fclose($fp);
	fclose($log);
	$bon='';
	$fp=fopen("$pfnavn","r");
	while($linje=fgets($fp)) {
		$bon.=$linje;
	}
	$bon=urlencode($bon);
	if ($udskriv) $tmp="../temp/".$db."/kasseopg".str_replace("-","",$kasse).".txt";
	else $tmp="/temp/".$db."/".str_replace("-","",$kasse).".txt";
	$url="://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
	$url=str_replace("/debitor/pos_ordre.php","",$url);
	if ($_SERVER['HTTPS']) $url="s".$url;
	$url="http".$url;
	if ($printpopup) print "<BODY onLoad=\"JavaScript:window.open('http://$printserver/saldiprint.php?printfil=$tmp&url=$url&bruger_id=$bruger_id&bonantal=1&bon=$bon&skuffe=1&gem=1' , '' , '$jsvars');\">\n";
	else print "<meta http-equiv=\"refresh\" content=\"0;URL=http://$printserver/saldiprint.php?printfil=$tmp&url=$url&bruger_id=$bruger_id&bonantal=$bonantal&id=$id&returside=$url/debitor/pos_ordre.php&bon=$bon&skuffe=1&gem=1\">\n";
	$accept = setCashCountText()['accept'];
    if (isset($_POST['optael']) &&  $_POST['optael'] == $accept && getCountry() == "Norway") {
        $_SESSION['boxZreport'] = true;
        print "<meta http-equiv=\"refresh\" content=\"0\"; url=https://udvikling.saldi.dk/lars/debitor/pos_ordre.php?id='$id'&kasse='$kasse'&kassebeholdning=on&bordnr=$bordnr>";
	}
} # endfunc kassebeholdning



?>

