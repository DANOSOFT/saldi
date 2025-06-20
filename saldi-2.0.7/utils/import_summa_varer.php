<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/usdecimal.php");

transaktion("begin");
$fp=fopen("../temp/summavarer.txt","r");
if ($fp) {
  $x=0;
  while (!feof($fp)) {
    $x++;
#    $linje=fgets($fp);
    $linje=str_replace('"','',fgets($fp));
    list($varenr, $beskrivelse, $enhed, $salgspris, $gruppe, $kostpris) = split(chr(9), $linje);
    $varenr=trim($varenr); $beskrivelse=trim($beskrivelse); $enhed=trim($enhed); $gruppe=trim($gruppe); $salgspris=usdecimal($salgspris); $kostpris=usdecimal($kostpris); 
    if ($gruppe=="1000") {$gruppe= "1";}
    elseif ($gruppe=="6900") {$gruppe= "2";}
    elseif ($gruppe=="1300") {$gruppe= "3";}
    elseif ($gruppe=="1200") {$gruppe= "4";}
    db_modify("INSERT INTO varer (varenr, beskrivelse, enhed, salgspris, gruppe, lukket) values ('$varenr', '$beskrivelse', '$enhed', '$salgspris', '$gruppe', '0')");
    $row= db_fetch_array(db_select("select id from varer where varenr = '$varenr'"));
    db_modify("INSERT INTO vare_lev (posnr, lev_id, vare_id, kostpris, lev_varenr) values ('1', '1', $row[id], $kostpris, '$varenr')");
  }
} 
fclose($fp);
transaktion("commit");
?>