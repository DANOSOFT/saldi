<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
transaktion("begin");
$fp=fopen("../temp/c4.txt","r");
if ($fp) {
  $x=0;
  while (!feof($fp)) {
    $x++;
#    $linje=fgets($fp);
    $linje=str_replace('"','',fgets($fp));
    list($kontonr, $beskrivelse, $type, $moms, $fra_kto) = split(chr(9), $linje);
    $kontonr=trim($kontonr); $beskrivelse=trim($beskrivelse); $type=trim($type); $moms=trim($moms); 
    if (($x>1)&&($type)){
      if ($type=="2") {$type= "H";}
      elseif ($type=="0") {$type= "D";}
      elseif  ($type=="5") {$type= "Z";}  
      elseif  ($type=="1") {$type= "S";}
      if ($moms=="2"){$moms="S1";}
      elseif ($moms=="1"){$moms="K1";}
      db_modify("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, fra_kto, til_kto, moms, regnskabsaar) values ('$kontonr', '$beskrivelse', '$type', '$fra_kto', '$kontonr', '$moms', '1')");
    }
  } 
  fclose($fp);
}
transaktion("commit");
?>