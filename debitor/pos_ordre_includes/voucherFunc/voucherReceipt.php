<?php

$FromCharset = "UTF-8";
$ToCharset = "cp865";

//fwrite($fp,"------------------------------------------------\n");
$txt = "------------------------------------------------";
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");

$txt = "GAVEKORT";
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");

fwrite($fp,"------------------------------------------------\n\n");

$txt = $masterData['firmanavn'];
$txt=iconv($FromCharset, $ToCharset,$txt);
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");

$txt = $masterData['addr1'];
$txt=iconv($FromCharset, $ToCharset,$txt);
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");

$txt = $masterData['postnr'] . " " . $masterData['bynavn'];
$txt=iconv($FromCharset, $ToCharset,$txt);
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");

$txt = "Tlf: " . $masterData['tlf'];
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");

$txt = "CVR: " . $masterData['cvrnr'];
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");

fwrite($fp,"------------------------------------------------\n\n");

$txt = "Nr: " . $giftCards['id'];
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");

$balance = number_format($giftCards['pris'], '2', ',', '.');
$txt = "Saldo: " . $balance . " " . $giftCards['valuta'];
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");

$txt = "Købsdato: " . $giftCards['orderDate'];
$txt=iconv($FromCharset, $ToCharset,$txt);
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");

$txt = "Udløbsdato: " . $giftCards['endDate'];
$txt=iconv($FromCharset, $ToCharset,$txt);
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");

$txt = "Lovgivning: OPFØR DIG ORDENTLIGT";
$txt=iconv($FromCharset, $ToCharset,$txt);
while(strlen($txt)*2<88) $txt=" ".$txt." ";
fwrite($fp,"$txt\n");






fwrite($fp,"------------------------------------------------\n");





fclose($fp);

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
if ($printserver=='box') {
    $filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
    if ($fp=fopen($filnavn,'r')) {
        $printserver=trim(fgets($fp));
        fclose ($fp);
        if ($printserver) setcookie("saldi_printserver",$printserver,time()+60*60*24*7,'/');
    }
}
if ($printserver=='box' || !$printserver) $printserver=$_COOKIE['saldi_printserver'];
($fakturanr)?$skuffe=1:$skuffe=0;
print "<meta http-equiv=\"refresh\" content=\"0;URL=http://$printserver/saldiprint.php?printfil=&url=$url&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&skuffe=$skuffe&returside=$returside&logo=on\">\n";
exit;












?>
