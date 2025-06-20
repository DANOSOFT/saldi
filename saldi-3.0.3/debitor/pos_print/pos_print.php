<?php	
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
else fwrite($fp,"Ialt                           $dkksum\n");
fwrite($fp,"----------------------------------------\n");
	
if (!$kontonr) {
	fwrite($fp,"$betaling            $modtaget\n");
	if ($modtaget2) fwrite($fp,"$betaling2            $modtaget2\n");
	fwrite($fp,"Retur                          $retur\n");
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
fwrite($fp,"        $txt\n");
fwrite($fp,"$txt\n\n");
fwrite($fp,"*****************************************\n\n");

#	for ($x=1;$x<3;$x++) fwrite($fp," \n");
fclose($fp);
if ($kontonr) $bonantal=2;
else $bonantal=1;
$tmp="/temp/".$db."/".$bruger_id.".txt";
print "<BODY onLoad=\"JavaScript:window.open('http://localhost/saldiprint.php?printfil=$tmp&bruger_id=$bruger_id&bonantal=$bonantal' , '' , '$jsvars');\">";
#	system("lpr -P srp350plus $pfnavn");
?>