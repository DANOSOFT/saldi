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
// SQL finans mï¿½kun efter skriftelig aftale med ITz ApS anvendes som
// vï¿½t for andre virksomheders regnskaber.
//
// Dette program er udgivet med hï¿½ om at det vil vï¿½e til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversï¿½telse af licensen kan lï¿½es her:
// http://www.fundanemt.com/gpl_da.html
// ----------------------------------------------------------------------

function dkdecimal($tal)
{
	#  $tal = round($tal,2);
	#  $tal = str_replace(".",",",$tal);
	#  if (!strstr($tal, ",")) {$tal = $tal . ",00";}
	#  if (substr($tal,-2,1) == ",") {$tal = $tal . "0";}
	# Der kan dog forekomme fejlafrundinger, men differencen er 
	# højst 1 øre - altså hvor den runder op, hvor den skulle runde 
	# ned.
	$tal=number_format($tal,2,",",".");
	return $tal;
}
?>
