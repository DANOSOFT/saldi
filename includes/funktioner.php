<?php
// ----------------------------------------------------------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere it under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg dog med med
// følgende tilføjelse:
//
// Dette program er udgivet med håb om at det vil være til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversættelse af licensen kan læses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------


function dkdato($dato)
{
  list ($year, $month, $day) = split ('-', $dato);
  $year=substr($year,-2,2);
  $dato = $day . "-" . $month . "-" . $year;
  return $dato;
}
######################################################################################################################################
function usdate($date)
{
  if (strlen($date) == 6)
  {  global $id;

    $g1=substr($date,0,2);
    $g2=substr($date,2,2);
    $g3=substr($date,4,2);
    $date=$g1."-".$g2."-".$g3;
  }
  list ($day, $month, $year) = split ('-', $date);
if ($year < 80) {$year = "20".$year;}
elseif ($year < 100) {$year = "19".$year;}
  $date = $year . "-" . $month . "-" . $day;
  return $date;
}
######################################################################################################################################
function dkdecimal($tal)
{
  $tal = round($tal,2);
  $tal = str_replace(".",",",$tal);
  if (!strstr($tal, ",")) {$tal = $tal . ",00";}
  if (substr($tal,-2,1) == ",") {$tal = $tal . "0";}
  return $tal;
}

######################################################################################################################################

function usdecimal($tal)
{
  if (!$tal){$tal="0,00";}
  $tal = str_replace(".","",$tal);
  $tal = str_replace(",",".",$tal);
  return $tal;
}
# endfunc usdecimal
######################################################################################################################################
?>
