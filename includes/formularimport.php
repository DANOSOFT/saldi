<?php
#	@session_start();
#	$s_id=session_id();

// --- systemdata/formularimport.php --- lap 3.3.2 --- 2013.08.19 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2022 Saldi.dk ApS
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
		$x=0;
		while (!feof($fp)) {
			$linje=trim(fgets($fp));
			if ($linje) {
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
					(is_numeric($xa)   )?$xa*=    1: $xa    = 0; 
					(is_numeric($xb)   )?$xb*=    1: $xb    = 0; 
					(is_numeric($ya)   )?$ya*=    1: $ya    = 0; 
					(is_numeric($yb)   )?$yb*=    1: $yb    = 0; 
					(is_numeric($yb)   )?$yb*=    1: $yb    = 0; 
					(is_numeric($str)  )?$str*=   1: $str   = 0; 
					(is_numeric($color))?$color*= 1: $color = 0; 
					if (($formularnr && $formular==$formularnr) || !$formularnr) {
						db_modify("insert into formularer (formular,art,beskrivelse,xa,ya,xb,yb,justering,str,color,font,fed,kursiv,side,sprog)values('$formular','$art','$beskrivelse','$xa','$ya','$xb','$yb','$justering','$str','$color','$font','$fed','$kursiv','$side','$sprog')",__FILE__ . " linje " . __LINE__); 
					}
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
