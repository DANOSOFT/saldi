<?php
@session_start();
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ debitor/pos_print/pos_print.php -- lap 3.9.4 -- 2020-09-06 --
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2008-2021 saldi.dk aps
// --------------------------------------------------------------------------
// 2016.04.26 PHR Tilføjet valuta   
// 2018.12.10 CA  Udskrivning af gavekort 20181210
// 2019.01.16 PHR Erstattet Convert med iconv
// 2019.10.23 PHR added '&kasse=$kasse' to saldiprint url.
// 2019.10.24 PHR added if (strtolower(substr($printserver,0,1))=='n');
// 2020.09.05	PHR various changes (ItemNo, wordWrap etc.)

#$printserver='localhost';
$dd=date("Y-m-d");
include ("pos_ordre_includes/posTxtPrint/wrapText.php");
include ("../includes/stdFunc/dkDecimal.php");
(isset($labelWidth))?$width=$labelWidth:$width=48;
/*
$txt="www.shop.dk";
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
fwrite($fp,"$txt\n\n");
*/
$txt="$firmanavn";
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
fwrite($fp,"$txt\n");
$txt="$addr1";
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
fwrite($fp,"$txt\n");
$txt="$postnr $bynavn";
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
fwrite($fp,"$txt\n");
$txt="Tlf.: $tlf ";
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
fwrite($fp,"$txt\n");
$txt="CVR.: $cvrnr";
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
fwrite($fp,"$txt\n");
fwrite($fp,"\n");

list($txt,$txt2) = wrapText('Stk Tekst',$width,' ',$belob,' ');
fwrite($fp,"$txt $belob\n");
list($txt,$txt2) = wrapText('-',$width,'','','-');
fwrite($fp,"$txt\n");
$tmp=0;
for($x=1;$x<=$linjeantal;$x++) {
/*
	$txt=iconv($FromCharset, $ToCharset,trim($itemNo[$x]));
	list($txt,$txt2)=wrapText($txt,$width,'    ','',' ');
	if (trim($txt)) fwrite($fp,"    $txt\n");
*/
	$txt = str_replace("•","*",$itemName[$x]);
	$txt=iconv($FromCharset, $ToCharset,$txt);
	$dkkpris[$x]="  ".trim($dkkpris[$x]);
	list($txt,$txt2)=wrapText($txt,$width,"$antal[$x] ",$dkkpris[$x],' ');
	fwrite($fp,"$antal[$x] $txt$dkkpris[$x]\n");
	while ($txt2) {
	list($txt,$txt2)=wrapText($txt2,$width,'       ','',' ');
		if (trim($txt)) fwrite($fp,"   $txt\n");
	}
	if (isset($beskrivelse2[$x])) {
		$txt=iconv($FromCharset, $ToCharset,trim($beskrivelse2[$x]));
	list($txt,$txt2)=wrapText($txt,$width,'    ','',' ');
		write($fp,"    ". $txt ."\n");
		while (trim($txt2)) {
		list($txt,$txt2)=wrapText($txt2,$width,'       ','',' ');
			if (trim($txt)) fwrite($fp,"   $txt\n");
		}
	}
	$tmp+=usdecimal($dkkpris[$x]);
}
$txt='-';
while(strlen($txt) < $width) $txt.='-';
fwrite($fp,"$txt\n");
if ($indbetaling){
	$txt='Indbetaling';
	while(strlen($txt.$dkksum) < $width) $txt.=" ";
	$txt.=$dkksum;
	fwrite($fp,"$txt\n");
#	fwrite($fp,"Indbetaling                           $dkksum\n");
} else {
	list($txt,$txt2) = wrapText('Ialt DKK',$width,'',$dkksum,' ');
	fwrite($fp,"$txt$dkksum\n");
}
if($fakturanr) {
list($txt,$txt2) = wrapText('Heraf moms',$width,'',$dkkmoms,' ');
fwrite($fp,"$txt$dkkmoms\n");
#} else {
#	$tmp=dkdecimal($tmp);
#	list($txt,$txt2) = wrapText("$txt",$width,' ',"$tmp",' ');
#	fwrite($fp,"$txt\n");
}
$txt='-';
while(strlen($txt) < $width) $txt.='-';
fwrite($fp,"$txt\n");
$skuffe=0;
if ($fakturanr) {
	if ($retur > 0) $skuffe=1;
	if (!$kontonr || $betalingsbet=='Kontant') {
		for ($x=0;$x<count($dkkamount);$x++) {
			if(trim($betalingstype[$x])=='Kontant') $skuffe=1;
			if (!trim($betalingstype[$x])) $betalingstype[$x] = "Unknown";
			$y=0;
			$tmp=" ";	
			$txt=trim($betalingstype[$x].$tmp.$dkkamount[$x]);
			while(strlen($txt)<$width){
				$y++;
				$tmp.=" ";	
				$txt=trim($betalingstype[$x].$tmp.$dkkamount[$x]);
			}
			fwrite($fp,"$txt\n");
			if ($retur*1) {
				$skuffe=1;
				$txt=trim('Retur'.$tmp.$dkkretur);
				while(strlen($txt)<$width){
					$y++;
					$tmp.=" ";	
					$txt=trim('Retur'.$tmp.$dkkretur);
				}
				fwrite($fp,"$txt\n");
			}
			list($txt,$txt2) = wrapText('-',$width,'','','-');
			fwrite($fp,"$txt\n");
		}
		if ($dd < $fakturadate) $skuffe=0;
	} else {
		fwrite($fp,"\n\n$kundenavn ($kontonr)\n");
		fwrite($fp,"$kundeaddr1\n");
		fwrite($fp,"$kundepostnr $kundeby\n");
		fwrite($fp,"\n\n");
#		$r=db_fetch_array(db_select("select sum(amount) as amount from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__));
#		$saldo=$r['amount'];
#		$gl_saldo=dkdecimal($sum-$saldo);
#		$saldo=dkdecimal($saldo);
		while(strlen($ny_saldo) < 12) $ny_saldo=" ".$ny_saldo;
		while(strlen($gl_saldo) < 12) $gl_saldo=" ".$gl_saldo;
		fwrite($fp,"Gammel saldo : $gl_saldo\nNy saldo     : $ny_saldo\n\n\n");
#		fwrite($fp,"Husk denne bon er Deres bilag\n\n\n\n");
		list($txt,$txt2) = wrapText('-',$width,'','','-');
		fwrite($fp,"$txt\n");
		fwrite($fp,"UNDERSKRIFT FOR MODTAGELSE AF VARER\n\n\n\n");
	}
}
# fwrite($fp,"\nDu blev betjent af: $ref\n");
if ($fakturanr) {
	fwrite($fp,"Husk denne bon er dit bilag\n\n");
	fwrite($fp,"Kasse: $kasse             Bonnr: $fakturanr\n");
} else {
	$fakturadate=date("d-m-Y");
	$tid=date("H:m");
}
fwrite($fp,"Dato : $fakturadate    kl:    $tid\n");
if ($bordnavn) fwrite($fp,"Bord : $bordnavn");
elseif ($bordnr) fwrite($fp,"Bord : $bordnr");
fwrite($fp,"\n\n");
$txt='*';
while(strlen($txt) < $width) $txt.='*';
fwrite($fp,"$txt\n");
if($fakturanr) {
/*
	$txt="Vi bytter med et smil inden for 14 dage";
	while(strlen($txt)*2<88) $txt=" ".$txt." ";
	$txt = iconv($FromCharset, $ToCharset,$txt);
	fwrite($fp,"$txt\n");
	$txt="til anden vare eller tilgodebevis";
	while(strlen($txt)*2<88) $txt=" ".$txt." ";
	$txt = iconv($FromCharset, $ToCharset,$txt);
	fwrite($fp,"$txt\n\n");
*/
	$txt="TAK FOR BESØGET";
	while(strlen($txt) < $width) $txt=" ".$txt." ";
	$txt = iconv($FromCharset, $ToCharset,$txt);
	fwrite($fp,"$txt\n\n");
	$txt='*';
	while(strlen($txt) < $width) $txt.='*';
	fwrite($fp,"$txt\n");
}
#	$gavekortbon=udskrivgavekort($fakturanr);
#	$gavekortbon = iconv($FromCharset, $ToCharset,$gavekortbon);
#	fwrite($fp,$gavekortbon);
fclose($fp);
if ($kontonr && $betalingsbet!='Kontant') $bonantal=2;
else $bonantal=1;
$tmp="/temp/".$db."/".$bruger_id.".txt";
$url="://".$_SERVER['SERVER_NAME'].=$_SERVER['PHP_SELF'];
$url=str_replace("/debitor/pos_ordre.php","",$url);
if ($_SERVER['HTTPS']) $url="s".$url;
$url="http".$url;
$returside=$url."/debitor/pos_ordre.php";
$bon='';
$fp=fopen("$pfnavn","r");
while($linje=fgets($fp))$bon.=$linje;

include ("pos_ordre_includes/voucherFunc/printVoucher.php");
$bon.= printVoucher($id,NULL);

// Check for card receipt and add it to the print job (only when Udskriv button is manually clicked)
$card_receipt_file = "../temp/$db/terminal_$id.txt";
echo "<script>console.log('Card receipt file: " . $id . "');</script>";
// Check if this is a manual print by looking for the Udskriv button in POST data
$is_manual_print = isset($_POST['udskriv']) && ($_POST['udskriv'] == "Udskriv" || $_POST['udskriv'] == "Print");

// Debug: Log what we're checking
error_log("Card receipt check: file_exists=" . (file_exists($card_receipt_file) ? 'true' : 'false') . 
          ", POST_udskriv=" . (isset($_POST['udskriv']) ? $_POST['udskriv'] : 'not_set') . 
          ", is_manual_print=" . ($is_manual_print ? 'true' : 'false'));

if (file_exists($card_receipt_file) && $is_manual_print) {
    $card_receipt_content = file_get_contents($card_receipt_file);
    $card_data = json_decode($card_receipt_content, true);
    
    if ($card_data) {
        // Add card receipt header
        $bon .= "\n\n";
        $bon .= "========================================\n";
        $bon .= "           KORTKVITTERING\n";
        $bon .= "========================================\n";
        
        $bon .= $card_data;
        
        $bon .= "========================================\n";
    }
}

$bon = urlencode($bon);
if (strtolower(substr($printserver,0,1))=='n') {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
	exit;
} elseif (strtolower($printserver)=='box') {
	$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
	if ($fp=fopen($filnavn,'r')) {
		$printserver=trim(fgets($fp));
		fclose ($fp);
		if ($printserver) setcookie("saldi_printserver",$printserver,time()+60*60*24*7,'/');
	} 
}
if ($printserver=='box' || !$printserver) $printserver=$_COOKIE['saldi_printserver'];
if (!$printserver) {
	alert ("Printserver ikke fundet");
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
	exit;
}
# OBS This file is not called if #Udskriv bon automatisk# is disabled. Check exitFunc/exit.php
print "<meta http-equiv=\"refresh\" content=\"0;URL=" . ($printserver == 'android' ? "saldiprint://" : "http://$printserver") . "/saldiprint.php?printfil=&url=$url&kasse=$kasse&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&skuffe=$skuffe&returside=$returside&logo=on\">\n";
exit;
#print "<BODY onLoad=\"JavaScript:window.open('" . ($printserver == 'android' ? "saldiprint://" : "http://$printserver") . "/saldiprint.php?printfil=$tmp&url=$url&bruger_id=$bruger_id&bonantal=$bonantal' , '' , '$jsvars');\">";
#	system("lpr -P srp350plus $pfnavn");
?>
