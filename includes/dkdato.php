<?php
// ----------------------------------------------------------------------050502----------
  // LICENS
  //
  // Dette program er fri software. Du kan gendistribuere det og / eller
  // modificere det under betingelserne i GNU General Public License (GPL)
  // som er udgivet af The Free Software Foundation; enten i version 2
  // af denne licens eller en senere version efter eget valg
  //
  // Dette program er udgivet med haab om at det vil vaere til gavn,
  // men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
  // GNU General Public Licensen for flere detaljer.
  //
  // En dansk oversaettelse af licensen kan laeses her:
  // http://www.fundanemt.com/gpl_da.html
  //
  // Copyright (c) 2004-2005 DANOSOFT ApS
// ----------------------------------------------------------------------
if (!function_exists('dkdato'))
{
  function dkdato($dato)
  {
    list ($year, $month, $day) = split ('-', $dato);
#  $year=substr($year,-2,2);
    $month=$month*1;
    $day=$day*1;
    if ($month<10){$month='0'.$month;}
    if ($day<10){$day='0'.$day;}
      $dato = $day . "-" . $month . "-" . $year;
    return $dato;
  }
}
?>
