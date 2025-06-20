<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ debitor/pos_print/pos_print.php -- lap 3.7.4 -- 2019-01-16 --
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
// Copyright (c) 2003-2019 saldi.dk aps
// --------------------------------------------------------------------------
// 2016.04.26 PHR Tilføjet valuta   
// 2018.12.10 CA  Udskrivning af gavekort 20181210
// 2019.01.16 PHR Erstattet Convert med iconv
// 2019.10.23 PHR added '&kasse=$kasse' to saldiprint url.
// 2019.10.24 PHR added if (strtolower(substr($printserver,0,1))=='n');

#$printserver='localhost';
$txt="$firmanavn";
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");
$txt="$addr1";
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");
$txt="$postnr $bynavn";
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");
$txt="Tlf.: $tlf";
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");
$txt="CVR.: $cvrnr";
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");
fwrite($fp,"\n\n");
fwrite($fp,"Stk Tekst                                 $belob\n");
fwrite($fp,"------------------------------------------------\n");
$tmp=0;
for($x=1;$x<=$linjeantal;$x++) {
	fwrite($fp,"$antal[$x] $beskrivelse[$x]         $dkkpris[$x]\n");
	$tmp+=usdecimal($dkkpris[$x]);
}
fwrite($fp,"------------------------------------------------\n");
if ($indbetaling)fwrite($fp,"Indbetaling                           $dkksum\n");
elseif($fakturanr) {
	fwrite($fp,"Ialt DKK                               $dkksum\n");
	fwrite($fp,"Heraf moms                             $dkkmoms\n");
} else {
	$tmp=dkdecimal($tmp);
	while (strlen($tmp)<9) $tmp=" ".$tmp;
	fwrite($fp,"Ialt DKK                               $tmp\n");
}
fwrite($fp,"------------------------------------------------\n");

if ($fakturanr) {
	if (!$kontonr || $betalingsbet=='Kontant') {
		for ($x=0;$x<count($dkkamount);$x++) {
			$y=0;
			$tmp=" ";	
			$txt=trim($betalingstype[$x].$tmp.$dkkamount[$x]);
#cho strlen($txt)."<br>";
#cho $txt."<br>";
			while(strlen($txt)<48){
				$y++;
				$tmp.=" ";	
				$txt=trim($betalingstype[$x].$tmp.$dkkamount[$x]);
#cho strlen($txt)."<br>";
#cho ">$txt<<br>";
			}
			fwrite($fp,"$txt\n");
#			else fwrite($fp,"$betalingstype[$x] ($valuta[$x]: $valutaamount[$x])  $dkkamount[$x]\n");
		}
#xit;
#		fwrite($fp,"$betaling                    $dkkmodtaget\n");
#		if ($modtaget2) fwrite($fp,"$betaling2                    $dkkmodtaget2\n");
		fwrite($fp,"Retur                                  $dkkretur\n");
		fwrite($fp,"------------------------------------------------\n");
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
		fwrite($fp,"-----------------------------------------------\n");
		fwrite($fp,"UNDERSKRIFT FOR MODTAGELSE AF VARER\n\n\n\n");
	}
}
#fwrite($fp,"Du blev betjent af: $ref\n\n");
fwrite($fp,"\n");
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
fwrite($fp,"***********************************************\n\n");
$txt="TAK FOR BESØGET";
while(strlen($txt)*2<88) $txt=" ".$txt." ";
$txt = iconv($FromCharset, $ToCharset,$txt);
//$txt= $convert ->Convert($txt, $FromCharset, $ToCharset);
fwrite($fp,"$txt\n\n");
fwrite($fp,"***********************************************\n\n");
#	for ($x=1;$x<3;$x++) fwrite($fp," \n");
#fwrite($fp,gavekortbon(800, 2)."\n");	# 20181210
#$gavekortbon=1;
#if ( $gavekortbon ) {
#	$gavekortbon=gavekortbon(800,2);
#	$gavekortbon=gavekortbon(123,234);
	$gavekortbon=udskrivgavekort($fakturanr);
	$gavekortbon = iconv($FromCharset, $ToCharset,$gavekortbon);
//	$gavekortbon = $convert ->Convert($gavekortbon, $FromCharset, $ToCharset); 
	fwrite($fp,$gavekortbon);
#}					# 20181210
fclose($fp);
#if (!$udskriv_bon) $bonantal=0;
#else
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
$bon=urlencode($bon);
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
($fakturanr)?$skuffe=1:$skuffe=0;
print "<meta http-equiv=\"refresh\" content=\"0;URL=http://$printserver/saldiprint.php?printfil=&url=$url&kasse=$kasse&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&skuffe=$skuffe&returside=$returside&logo=on\">\n";
exit;
#print "<BODY onLoad=\"JavaScript:window.open('http://$printserver/saldiprint.php?printfil=$tmp&url=$url&bruger_id=$bruger_id&bonantal=$bonantal' , '' , '$jsvars');\">";
#	system("lpr -P srp350plus $pfnavn");
?>
