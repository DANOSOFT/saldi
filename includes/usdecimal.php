<?php
// ----------------------------------------------------------------------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------
if (!function_exists('usdecimal'))
{
  function usdecimal($tal)
  {
    if (!$tal){$tal="0,00";}
    $tal = str_replace(".","",$tal);
    $tal = str_replace(",",".",$tal);
    $tal=$tal*1;
    $tal=round($tal,2);
    if (!$tal){$tal="0.00";}
    return $tal;
  }
}
?>
