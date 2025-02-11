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

if (!function_exists('periodeoverskrifter')) {
	function periodeoverskrifter ($periodeantal, $periode_aar, $periode_md, $periode_dag=1, $periode_laengde="regnskabsmaaned", $regnskabsaar="") {
		# Periodelængder kan være dag, uge, maaned, regnskabsmaaned eller kvartal (eller blot det foerste bogstav)
		setlocale(LC_TIME, "da_DK","da","da_DK.utf8");
		$retur=""; # 20150622
		$trin = 1;
		$periode_laengde = strtolower(substr($periode_laengde, 0, 1));
		if ( $periode_laengde == substr("uge", 0, 1) ) {
			$trin = 7;
			$periodeantal = $trin * $periodeantal;
		}
		if ( $periode_laengde == substr("kvartal", 0, 1) ) {
			$trin = 3;
			$periodeantal = $trin * $periodeantal;
		}
		for ($z=0; $z<$periodeantal; $z=$z+$trin) {
			if ( $periode_laengde == substr("dag", 0, 1) ) {
				$periode_tidsstempel = mktime(12, 0, 0, $periode_md, $periode_dag+$z, $periode_aar);
				if ( strftime("%u", $periode_tidsstempel) > 5 ) { # Loerdag eller soendag med det danske oe i navnet
					# $periode_kort = ucfirst(substr(strftime("%a", $periode_tidsstempel),0,3))."&nbsp;".date("j/n",$periode_tidsstempel);
					$periode_kort = ucfirst(substr(strftime("%a", $periode_tidsstempel),0,1))."&oslash;&nbsp;".date("j/n",$periode_tidsstempel);
				} else {
					$periode_kort = ucfirst(substr(strftime("%a", $periode_tidsstempel),0,2))."&nbsp;".date("j/n",$periode_tidsstempel);
				}
				$periode_lang = ucfirst(strftime("%A %e. %B %Y",$periode_tidsstempel));
			} elseif ( $periode_laengde == substr("uge", 0, 1) ) {
				$periode_tidsstempel = mktime(12, 0, 0, $periode_md, $periode_dag+$z, $periode_aar);
				$periode_ugedag = strftime("%u", $periode_tidsstempel);
				$periode_start = mktime(12, 0, 0, $periode_md, $periode_dag+$z+1-$periode_ugedag, $periode_aar);
				$periode_slut = mktime(12, 0, 0, $periode_md, $periode_dag+$z+7-$periode_ugedag, $periode_aar);
				$periode_kort = strftime("u%V'%g",$periode_tidsstempel);
				$periode_lang = strftime("Uge %V i &aring;r %G",$periode_tidsstempel);
				$periode_lang .= " (".date("d/m",$periode_start)." - ".date("d/m",$periode_slut).")";
			} else {
				$periode_tidsstempel= mktime(12, 0, 0, $periode_md+$z, $periode_dag, $periode_aar);
				if ( $periode_laengde == substr("kvartal", 0, 1) ) {
					$periode_slut = mktime(12, 0, 0, $periode_md+$z+3, 0, $periode_aar);
					$periode_kvartal = floor((date("m",$periode_tidsstempel)-1)/3)+1;
					$periode_kort = $periode_kvartal.". kv'". strftime("%y",$periode_tidsstempel);
					$periode_lang = $periode_kvartal.". kvartal ". strftime("%Y",$periode_tidsstempel);
					$periode_lang .= " (".date("d/m",$periode_tidsstempel)." - ".date("d/m",$periode_slut).")";
				} elseif ( $periode_laengde == substr("maaned", 0, 1) ) {
					$periode_kort = ucfirst(strftime("%b'%y",$periode_tidsstempel));
					$periode_lang = ucfirst(strftime("%B %Y",$periode_tidsstempel));
				} elseif ( $periode_laengde == substr("regnskabsmaaned", 0, 1) ) {
					$periode_kort = ucfirst(strftime("%b'%y",$periode_tidsstempel));
					$periode_lang = ucfirst(strftime("%B %Y",$periode_tidsstempel));
					$periode_lang .= " (".($z+1).". regnskabsm&aring;ned i regnskabs&aring;ret";
					if ( $regnskabsaar ) $periode_lang .= " ".$regnskabsaar;
					$periode_lang .= ")";
					
				} else {
					$periode_kort = ($z+1).".";
					$periode_lang = ($z+1).". periode";
				}
			}
	
			print "<td title=\"$periode_lang\" align=\"right\"><b>$periode_kort</b></td>\n";
			$retur.="\"$periode_kort\";"; # 20150622
		}
		return $retur; # 20150622
	}

}
?>

