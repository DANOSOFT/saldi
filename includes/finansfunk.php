<?php
// ----------- includes/finansfunk.php ------------- lap 3.5.6 -- 2015-06-22 --
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg

// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------------
//
// 20150622 CA  funktionen periodeoverskrifter returnere overskrifterne som CSV
// 20250207 migrate from strftime to IntlDateFormatter. + strip function down to what is actually used.

if (!function_exists('periodeoverskrifter')) {
	function periodeoverskrifter ($periodeantal, $periode_aar, $periode_md, $periode_dag=1, $regnskabsaar="") {
		$date_fmt = new IntlDateFormatter('da_DK');
		$retur="";
		$trin = 1;
		for ($z=0; $z<$periodeantal; $z=$z+$trin) {
			$periode_tidsstempel= mktime(12, 0, 0, $periode_md+$z, $periode_dag, $periode_aar);

			$date_fmt->setPattern("MMM''yy");
			$periode_kort = ucfirst($date_fmt->format($periode_tidsstempel));

			$date_fmt->setPattern("MMMM yyyy");
			$periode_lang = ucfirst($date_fmt->format($periode_tidsstempel));
			$periode_lang .= " (".($z+1).". regnskabsm&aring;ned i regnskabs&aring;ret";
			if ( $regnskabsaar ) $periode_lang .= " ".$regnskabsaar;
			$periode_lang .= ")";
	
			print "<td title=\"$periode_lang\" align=\"right\"><b>$periode_kort</b></td>\n";
			$retur.="\"$periode_kort\";";
		}
		return $retur;
	}
}
?>
