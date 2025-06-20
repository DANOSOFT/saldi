<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
transaktion("begin");
$fp=fopen("../temp/summaktoplan.txt","r");
if ($fp) {
  $x=0;
  while (!feof($fp)) {
    $x++;
#    $linje=fgets($fp);
    $linje=str_replace('"','',fgets($fp));
    list($kontonr, $beskrivelse, $type, $moms, $afd, $fra_kto, $til_kto) = split(chr(9), $linje);
    $kontonr=trim($kontonr); $beskrivelse=trim($beskrivelse); $type=trim($type); $moms=trim($moms); 
    if (($x>1)&&($type)){
      if ($type=="Tekst") {$type= "H";}
      elseif ($type=="Drift") {$type= "D";}
      elseif  ($type=="Sum") {$type= "Z";}  
      elseif  ($type=="Status") {$type= "S";}
      if ($moms=="S25"){$moms="S1";}
      elseif ($moms=="K25"){$moms="K1";}
      if (strlen($kontonr)<5) {
        for ($y=strlen($kontonr); $y<5; $y++) {$kontonr="0".$kontonr;}
      } 
      db_modify("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, fra_kto, til_kto, moms, regnskabsaar) values ('$kontonr', '$beskrivelse', '$type', '$fra_kto', '$til_kto', '$moms', '1')");
    }
  } 
  fclose($fp);
}
transaktion("commit");
?>