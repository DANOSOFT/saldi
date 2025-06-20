<?php
require_once('nusoap.php');

$regnskab="webservice";
$brugernavn="webservice";
$password="webservice";

$temp=$regnskab."::".$brugernavn."::".$password;

$c = new soapclient('https://ssl2.saldi.dk/demo/webservice/webservice.php');
$s_id = $c->call('Login', array('Login' => $temp));

echo "Session id er $s_id<br>";
$kladdenote="Denne kassekladde er oprettet gennem webservice";

$temp=$s_id."::".$kladdenote;

$svar = $c->call('OpretKladde', array('s_id' => $temp));

list($kladde_id,$next_bilag)=split('::',$svar);

echo "Kladde[id] id er: $kladde_id, næste bilag er: $next_bilag<br>";

$dato=150608; 
$beskrivelse="Bilag $next_bilag indsat den $dato";
$d_type="F";
$debet="11010";
$k_type="F";
$kredit="26200";
$belob="1.100,25";
$moms="on";
$ansat=0;
$afd=0;
$projekt=0;
$fakturanr='';
$valuta='USD';

$temp=$s_id."::".$kladde_id."::".$next_bilag."::".$dato."::".$beskrivelse."::".$d_type."::".$debet."::".$k_type."::".$kredit."::".$fakturanr."::".$belob."::".$moms."::".$ansat."::".$afd."::".$projekt."::".$valuta;

$next_bilag = $c->call('IndsaetKladdeLinje', array('s_id' => $temp));

echo "Næste bilag = $next_bilag<br>";

$svar = $c->call('Logoff', array('s_id' => $s_id));

echo "$svar<br>";
?>
