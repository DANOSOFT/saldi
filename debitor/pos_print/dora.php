<?php
// ------------- debitor/pos_print/pos_print.php ---------- lap 3.2.9----2013-05-21------
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
// http://www.saldi.dk/dok/glpdk.html
//
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------

fwrite($fp,"$firmanavn\n");
fwrite($fp,"$addr1\n");
fwrite($fp,"$postnr $bynavn\n");
fwrite($fp,"Tlf.: $tlf\n");
fwrite($fp,"CVR.: $cvrnr\n");
fwrite($fp,"\n\n");
fwrite($fp,"Stk Tekst                          $belob\n");
fwrite($fp,"----------------------------------------\n");
for($x=1;$x<=$linjeantal;$x++) {
	fwrite($fp,"$antal[$x] $beskrivelse[$x] $dkkpris[$x]\n");
}
fwrite($fp,"----------------------------------------\n");
if ($indbetaling)fwrite($fp,"Indbetaling                    $dkksum\n");
else {
	fwrite($fp,"Ialt DKK                       $dkksum\n");
	fwrite($fp,"Heraf moms                     $dkkmoms\n");
}
fwrite($fp,"----------------------------------------\n");

if (!$kontonr || $betalingsbet=='Kontant') {
	fwrite($fp,"$betaling            $dkkmodtaget\n");
	if ($modtaget2) fwrite($fp,"$betaling2            $dkkmodtaget2\n");
	fwrite($fp,"Retur                          $dkkretur\n");
	fwrite($fp,"----------------------------------------\n");
} else {
	fwrite($fp,"\n\n$kundenavn ($kontonr)\n");
	fwrite($fp,"$kundeaddr1\n");
	fwrite($fp,"$kundepostnr $kundeby\n");
	fwrite($fp,"\n\n");
#		$r=db_fetch_array(db_select("select sum(amount) as amount from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__));
#		$saldo=$r['amount'];
#		$gl_saldo=dkdecimal($sum-$saldo);
#		$saldo=dkdecimal($saldo);
	while(strlen($ny_saldo)<9) $ny_saldo=" ".$ny_saldo;
	while(strlen($gl_saldo)<9) $gl_saldo=" ".$gl_saldo;
	fwrite($fp,"Gammel saldo: $gl_saldo\nNy saldo    : $ny_saldo\n\n\n");
#		fwrite($fp,"Husk denne bon er Deres bilag\n\n\n\n");
	fwrite($fp,"---------------------------------------\n");
	fwrite($fp,"UNDERSKRIFT FOR MODTAGELSE AF VARER\n\n\n\n");
}
fwrite($fp,"De blev betjent af: $ref\n\n");
fwrite($fp,"Husk denne bon er Deres bilag\n\n");
fwrite($fp,"Kasse: $kasse             Bonnr: $fakturanr\n");
fwrite($fp,"Dato : $fakturadate    kl:    $tid\n\n");
fwrite($fp,"*****************************************\n");
$txt="Husk bon når du skal bytte en vare";
$txt= $convert ->Convert($txt, $FromCharset, $ToCharset);
fwrite($fp,"$txt\n");
fwrite($fp,"*****************************************\n");
$txt="            Tak og på gensyn";
$txt= $convert ->Convert($txt, $FromCharset, $ToCharset);
#fwrite($fp,"        $txt\n");
fwrite($fp,"$txt\n\n");
fwrite($fp,"*****************************************\n\n");

#	for ($x=1;$x<3;$x++) fwrite($fp," \n");
fclose($fp);
#if (!$udskriv_bon) $bonantal=0;
#else
if ($kontonr && $betalingsbet!='Kontant') $bonantal=2;
else $bonantal=1;
$tmp="/temp/".$db."/".$bruger_id.".txt";
$url="://".$_SERVER['SERVER_NAME'].=$_SERVER['PHP_SELF'];
$url=str_replace("/debitor/pos_ordre.php","",$url);
if ($_SERVER[HTTPS]) $url="s".$url;
$url="http".$url;

print "<BODY onLoad=\"JavaScript:window.open('http://$printserver/saldiprint.php?printfil=$tmp&url=$url&bruger_id=$bruger_id&bonantal=$bonantal' , '' , '$jsvars');\">";
#	system("lpr -P srp350plus $pfnavn");
?>