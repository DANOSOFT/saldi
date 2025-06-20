<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/deposit.php ---------- lap 3.7.7----2019.05.13-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190513 Move the handling of indbetaling here
	if ($indbetaling) {
			$indbetaling=str_replace("a","",$indbetaling);
			if ($fokus=='indbetaling') { #20160220-2
				if (!is_numeric(str_replace(",","",$indbetaling))) {
					$b=substr($indbetaling,-1);
					if ($b=='t') { #20160418-2
						print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
						exit;
					}
					$i=str_replace($b,'',$indbetaling);
					$usi=(str_replace(".","",$i));
					$usi=(str_replace(",",".",$usi));
					if (is_numeric($usi)) {
						$m=usdecimal($modtaget,2)*1;
						if ($usi>$m && $modtaget!='') {
							print "<BODY onLoad=\"javascript:alert('Indbetaling kan ikke v&aelig;re større end beløbet der modtages')\">\n";
							$indbetaling='Indbetaling konto';
							$modtaget=0;
						} elseif (!$modtaget) {
							$indbetaling=$i;
							$modtaget=$i;
						} elseif ($usi==$m) {
							$fokus='modtaget';
							$tmp=$modtaget;
							$modtaget=$indbetaling;
							$indbetaling=$tmp;
						} else {
							$indbetaling=$i;
						}
					}
				}
			} elseif ($fokus=='modtaget') { #20160418
				if ($indbetaling && $m=='') $modtaget=$indbetaling; #20161205
				if (!is_numeric(str_replace(",","",$modtaget))) {
					$b=substr($modtaget,-1);
					if ($b=='t') { #20160418-2
						print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
						exit;
					}
					$m=str_replace($b,'',$modtaget);
					$usm=(str_replace(".","",$m));
					$usm=(str_replace(",",".",$usm)); # 20151205 rettet usi til usm
					if (is_numeric($usm)) {
						$i=usdecimal($indbetaling,2)*1;
						if ($i>$usm && $m!='') {
							print "<BODY onLoad=\"javascript:alert('Indbetaling kan ikke v&aelig;re større end beløbet der modtages')\">\n";
							$indbetaling='Indbetaling konto';
							$modtaget=0;
						} elseif (!$m) {
							$indbetaling=$i;
							$modtaget=$i;
						} elseif ($usm==$i) {
							$fokus='modtaget';
							$modtaget=$indbetaling;
							$indbetaling=$m;
						}
					}
				}
			}
			$tmp=trim(str_replace(".","",$indbetaling));
			$tmp=str_replace(",",".",$tmp);
			if (is_numeric($tmp)) {
				$indbetaling=usdecimal($indbetaling,2)*1;
				$modtaget=usdecimal($modtaget,2)*1;
			if ($indbetaling<0 && $modtaget != $indbetaling) { #20160902
				print "<BODY onLoad=\"javascript:alert('Ved udbetaling skal `Indbetaling` og `Betalt` være samme beløb')\">\n";
				$indbetaling='Indbetaling konto'; #20160220-2
				$modtaget=0; #20160220-2
			}
			if ($indbetaling>$modtaget && $modtaget!=0) {
				print "<BODY onLoad=\"javascript:alert('Indbetaling kan ikke v&aelig;re større end beløbet der modtages')\">\n";
#				$indbetaling=$modtaget;
				$indbetaling='Indbetaling konto'; #20160220-2
				$modtaget=0; #20160220-2
			}
		}
	} elseif ($indbetal) {
        $indbetaling=$indbetal;
		#exit;
	}	elseif ($betaling && ($betaling!='ukendt' || substr($modtaget,0,1)=='/')) { #20160817
#cho __LINE__." $modtaget<br>";
		if (substr($modtaget,0,1)=='/') { #Delbetaling
			$modtaget=substr($modtaget,1);
			if (!is_numeric(substr($modtaget,-1))) {
				$delbetaling=usdecimal(substr($modtaget,0,strlen($modtaget)-1));
				$sluttegn=substr($modtaget,-1);
			} else {
				$delbetaling=$modtaget;
				$sluttegn='';
			}
			$modtaget=dkdecimal($sum/$delbetaling,2);
			$modtaget.=$sluttegn;
		}# else $delbetaling=if_isset($_POST['delbetaling']);
		if (substr($modtaget,0,1)=='+') $modtaget=$sum+usdecimal(substr($modtaget,1,strlen($modtaget)-1),2);
		elseif (strlen($modtaget)==1 && !is_numeric($modtaget)) $modtaget='';
		elseif (!is_numeric(substr($modtaget,-1))) $modtaget=usdecimal(substr($modtaget,0,strlen($modtaget)-1),2);
		else $modtaget=usdecimal($modtaget,2);
			
		if (!$modtaget) {
			$modtaget=$sum;
			$r=db_fetch_array(db_select("select sum(amount) as amount from pos_betalinger where ordre_id='$id'",__FILE__ . " linje " . __LINE__));
			$modtaget-=$r['amount'];
			$rest=$modtaget;
			if ($betaling=='Kontant') $modtaget=pos_afrund($modtaget,$difkto,'');
			if ($betvalkurs) {
				$modtaget*=100/$betvalkurs;
				$rest=$modtaget;
			}
		}

		if (substr($modtaget2,0,1)=='+') $modtaget2=$sum+usdecimal(substr($modtaget2,1,strlen($modtaget2)-1),2);
		elseif (!is_numeric(substr($modtaget2,-1))) $modtaget2=usdecimal(substr($modtaget2,0,strlen($modtaget2)-1),2);
		else $modtaget2=usdecimal($modtaget2,2);
		$modtaget2=$modtaget2*1;
	} else $modtaget=usdecimal($modtaget,2);

?>
