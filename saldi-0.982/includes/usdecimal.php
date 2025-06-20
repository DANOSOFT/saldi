<?
// ----------------------------------------------------------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere it under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg dog med med
// flgende tilfjelse:
//
// SQL finans m�kun efter skriftelig aftale med ITz ApS anvendes som
// v�t for andre virksomheders regnskaber.
//
// Dette program er udgivet med h� om at det vil v�e til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk overs�telse af licensen kan l�es her:
// http://www.fundanemt.com/gpl_da.html
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
