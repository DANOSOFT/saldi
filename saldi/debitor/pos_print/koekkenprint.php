<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------------debitor/pos_print/koekkenprint.php----------lap 3.7.0-----2017-06-19----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2017 saldi.dk aps
// -----------------------------------------------------------------------

function koekkenprint($linje_id,$bestil,$beskrivelse,$cat_id,$kategori,$udskrives) {
	global $besked,$bordnavn,$bruger_id,$brugernavn,$convert,$db;
	global $kp;
	global $FromCharset,$ToCharset;
	global $notes,$tilfravalg;

	$udskrives=array();
	for($y=0;$y<count($kp);$y++) {

				$udskrives[$y]=0;
				for ($x=0;$x<count($linje_id);$x++) {
// Hvis der er bestilling på varen OG (der er flere køkkener og varen har køkkenet som kategori ELLER der ikke er defineret køkken kategorier);
#cho "X $cat_id[$y] $beskrivelse[$x] Besitl $bestil[$x]<br>";
				if ($bestil[$x] && (in_array ($cat_id[$y],$kategori[$x]) || count($cat_id)<1)) $udskrives[$y]=1;
#cho "Y $cat_id[$y] $beskrivelse[$x] Udskr $udskrives[$y]<br>";
					#elseif ($bestil[$x] && !in_array ($cat_id[$y],$kategori[$x]) && count($cat_id)>=1) $udskrives[$y]=$cat_id;
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
					$txt=$convert ->Convert("******   BESTILLING   ******", $FromCharset, $ToCharset);
					while (strlen($txt)<40) $txt=" ".$txt." ";
					fwrite($fp,"$txt\n");
					if (count($kp)) {
						$txt="Køkken ";
						$txt.= $y+1;
						$txt=$convert ->Convert($txt, $FromCharset, $ToCharset);
						while (strlen($txt)<40) $txt=" ".$txt." ";
						fwrite($fp,"$txt\n");
					}
					fwrite($fp,"\nD. ".date("d.m.Y")." kl. ".(date("H:i"))."\n\n");  
					$txt=$convert ->Convert("Bord:       $bordnavn", $FromCharset, $ToCharset);
					fwrite($fp,"$txt\n\n");
					$txt=$convert ->Convert("Bestilt af: $brugernavn", $FromCharset, $ToCharset);
					fwrite($fp,"$txt\n\n");
					if ($besked) {
						fwrite($fp,"----------------------------------------\n");
						$txt=$convert ->Convert("BESKED YIL KØKKEN!", $FromCharset, $ToCharset);
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
							$txt=$convert ->Convert($linje[$l], $FromCharset, $ToCharset);
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
									$txt=$convert ->Convert("$r[beskrivelse]", $FromCharset, $ToCharset);
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