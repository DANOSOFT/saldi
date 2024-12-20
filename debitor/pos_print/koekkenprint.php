<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_print/koekkenprint.php--- lap 4.1.0 --- 2024-05-21----
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
// Copyright (c) 2008-2021 saldi.dk aps
// -----------------------------------------------------------------------
// 20210915 - PHR Replaced convert with iconv.
// 20240521 - PHR PHP8

function koekkenprint($linje_id,$bestil,$beskrivelse,$cat_id,$kategori) {
	global $besked,$bordnavn,$bruger_id,$brugernavn,$db;
	global $kp;
	global $FromCharset,$ToCharset;
	global $notes,$tilfravalg;

#	for ($i=0;$i<count($kategori);$i++) {
#		for ($x=0;$x<count($kategori);$x++) echo "$i $x ".$kategori[$i][$x]."<br>";
#	}
	$udskrives=array();
	for($y=0;$y<count($kp);$y++) {
				$udskrives[$y]=0;
				for ($x=0;$x<count($linje_id);$x++) {
// Hvis der er bestilling på varen OG (der er flere køkkener og varen har køkkenet som kategori ELLER der ikke er defineret køkken kategorier);
#cho "$cat_id[$y]<br>";
					if ($bestil[$x] && (in_array ($cat_id[$y],$kategori[$x]) || count($cat_id)<1)) $udskrives[$y]=1;
				}
				$kp[$y]=strtolower($kp[$y]);
#cho "$kp[$y]<br>";				
				if (trim($kp[$y])=='box') {
					$z=$y+1;
					$filnavn="http://saldi.dk/kasse/K".$z."_".$_SERVER['REMOTE_ADDR'].".ip";
#cho __line__." $filnavn<br>";
					if ($fp=fopen($filnavn,'r')) {
						$kp[$y]=trim(fgets($fp));
						fclose ($fp);
					}
#cho __line__." $kp[$y]<br>";						
					if ($kp[$y]=='box') {
						Print tekstboks('Køkkenprinter '.$z.' kan ikke findes');
						exit;					
					}
				}
				$pfnavn="../temp/".$db."/".abs($bruger_id).".$y";
				$fp=fopen("$pfnavn","w");
				if ($y) fwrite($fp,"$kp[$y]\n");
				if ($udskrives[$y]) {
#					$txt = chr(27).'@'; # Initialize
					$txt = chr(27).'R4'; # Denmark I
#					$txt = chr(27).'R10'; # Denmark II
						fwrite($fp,"$txt");
					$txt = chr(27).'G1'; # s emphasized
					fwrite($fp,"$txt");
#					$txt = chr(29).chr(33).chr(12); # font size
#					fwrite($fp,"$txt");
					$txt=iconv($FromCharset, $ToCharset, "******   BESTILLING   ******");
					while (strlen($txt)<40) $txt=" ".$txt." ";
					fwrite($fp,"$txt\n");
					if (count($kp)) {
						$txt="Køkken ";
						$txt.= $y+1;
						$txt=iconv($FromCharset, $ToCharset, $txt);
						while (strlen($txt)<40) $txt=" ".$txt." ";
						fwrite($fp,"$txt\n");
					}
					fwrite($fp,"\nD. ".date("d.m.Y")." kl. ".(date("H:i"))."\n\n");  
					$txt=iconv($FromCharset, $ToCharset, "Bord:       $bordnavn");
					fwrite($fp,"$txt\n\n");
					$txt=iconv($FromCharset, $ToCharset, "Bestilt af: $brugernavn");
					fwrite($fp,"$txt\n\n");
					if ($besked) {
						fwrite($fp,"----------------------------------------\n");
						$txt=iconv($FromCharset, $ToCharset, "BESKED TIL KØKKEN!");
						while (strlen($txt)<40) $txt=" ".$txt." ";
						fwrite($fp,"$txt\n\n");
						$ord=explode(' ',$besked);
						$linje=array();
						$l=0;
						$linje[$l]=$ord[0];
						for ($o=1;$o<count($ord);$o++) {
							if (strlen($linje[$l]." ".$ord[$o]) <= 40 && $o<count($ord)) $linje[$l].=" ".$ord[$o];
							else {
								$l++;
								$linje[$l]=$ord[$o]; 
							}
						}
						for ($l=0;$l<count($linje);$l++) {
							$txt=iconv($FromCharset, $ToCharset, $linje[$l]);
#							while (strlen($txt)<40) $txt=" ".$txt." ";
							fwrite($fp,"$txt\n");
						}
						fwrite($fp,"----------------------------------------\n\n");
					}
					fwrite($fp,"Antal  Beskrivelse\n");
					fwrite($fp,"----------------------------------------\n");
					for ($x=0;$x<count($linje_id);$x++) {
						if ($bestil[$x] && (in_array ($cat_id[$y],$kategori[$x]) || count($cat_id)<1)) {
							fwrite($fp,"$bestil[$x]  $beskrivelse[$x]\n");
							if ($tilfravalg[$x]){
								$tfv=explode(chr(9),$tilfravalg[$x]);
								for ($t=0;$t<count($tfv);$t++){
									$r=db_fetch_array(db_select("select beskrivelse from varer where id = '$tfv[$t]'",__FILE__ . " linje " . __LINE__));
									$txt=iconv($FromCharset, $ToCharset, $r['beskrivelse']);
									fwrite($fp,"     $txt\n");
								}
							}
							fwrite($fp,"$notes[$x]\n\n\n\n");
							fwrite($fp,"----------------------------------------\n");
						}
					}
			fwrite($fp,"\n\n\n");
			#cho "$bestil[$x]=$tmp<br>";
#		else $bestil[$x]=$antal[$x]; 
				}
				fclose($fp);
				$bon='';
			}
}
			
			
?>
