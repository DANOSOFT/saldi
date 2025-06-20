<?php
#	@session_start();
#	$s_id=session_id();

// ----------/systemdata/formularimport.php-----lap 3.3.2------2013.08.19---------------------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20130510, Tilføjet $formularnr.
// 20130819, Tilføjet sporingsdata. Søg 20130819.

function formularimport($filnavn,$formularnr) {
	global $db_encode;

$fp=fopen($filnavn,"r");
	if ($fp) {
		if ($formularnr) $qtxt="delete from formularer where formular='$formularnr'";
		else $qtxt="delete from formularer";
		db_modify("$qtxt",__FILE__ . " linje " . __LINE__); # 20130819
		while (!feof($fp)) {
			$linje=fgets($fp);
			$linje=str_replace("\n","",$linje);
			if ($db_encode=="UTF8") $linje=utf8_encode($linje);
			list($formular, $art, $beskrivelse, $justering, $xa, $ya, $xb, $yb, $str, $color, $font, $fed, $kursiv, $side, $sprog) = explode(chr(9),$linje);
			if ((substr($formular,0,1))=="'"&&(substr($formular,-1))=="'") $formular=(substr($formular,1,strlen($formular)-2));
			if ((substr($art,0,1))=="'"&&(substr($art,-1))=="'") $art=(substr($art,1,strlen($art)-2));
			if ((substr($beskrivelse,0,1))=="'"&&(substr($beskrivelse,-1))=="'") $beskrivelse=(substr($beskrivelse,1,strlen($beskrivelse)-2));
			if ((substr($justering,0,1))=="'"&&(substr($justering,-1))=="'") $justering=(substr($justering,1,strlen($justering)-2));
			if ((substr($xa,0,1))=="'"&&(substr($xa,-1))=="'") $xa=(substr($xa,1,strlen($xa)-2));
			if ((substr($ya,0,1))=="'"&&(substr($ya,-1))=="'") $ya=(substr($ya,1,strlen($ya)-2));
			if ((substr($xb,0,1))=="'"&&(substr($xb,-1))=="'") $xb=(substr($xb,1,strlen($xb)-2));
			if ((substr($yb,0,1))=="'"&&(substr($yb,-1))=="'") $yb=(substr($yb,1,strlen($yb)-2));
			if ((substr($str,0,1))=="'"&&(substr($str,-1))=="'") $str=(substr($str,1,strlen($str)-2));
			if ((substr($color,0,1))=="'"&&(substr($color,-1))=="'") $color=(substr($color,1,strlen($color)-2));
			if ((substr($font,0,1))=="'"&&(substr($font,-1))=="'") $font=(substr($font,1,strlen($font)-2));
			if ((substr($fed,0,1))=="'"&&(substr($fed,-1))=="'") $fed=(substr($fed,1,strlen($fed)-2));
			if ((substr($kursiv,0,1))=="'"&&(substr($kursiv,-1))=="'") $kursiv=(substr($kursiv,1,strlen($kursiv)-2));
			if ((substr($side,0,1))=="'"&&(substr($side,-1))=="'") $side=(substr($side,1,strlen($side)-2));
			if ((substr($sprog,0,1))=="'"&&(substr($sprog,-1))=="'") $sprog=(substr($sprog,1,strlen($sprog)-2));
			$beskrivelse=addslashes($beskrivelse);
			if ($xa>0) {
				$justering=trim($justering); $form=trim($font); $fed=trim($fed); $kursiv=trim($kursiv); $side=trim($side); $sprog=trim($sprog);
				$xa= $xa*1; $ya= $ya*1; $xb= $xb*1; $yb=$yb*1; $str=$str*1; $color=$color*1;
				if (($formularnr && $formular==$formularnr) || !$formularnr) {
					db_modify("insert into formularer (formular,art,beskrivelse,xa,ya,xb,yb,justering,str,color,font,fed,kursiv,side,sprog)values('$formular','$art','$beskrivelse','$xa','$ya','$xb','$yb','$justering','$str','$color','$font','$fed','$kursiv','$side','$sprog')",__FILE__ . " linje " . __LINE__); 
				}
			}
		}
		fclose($fp);
#		print "<div style=\"text-align: center;\">$font<small>Import succesfuld - vindue lukkes</small></font><br></div>";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
#		exit;
	}
}
?>
</tbody></table>
</body></html>
