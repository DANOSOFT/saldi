<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|

// --- includes/alignOpenpostIncludes/doAlign.php --- ver 4.0.8 --- 2016-14-04--------
// LICENS>
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
// Copyright (c) 2003-2016 DANOSOFT ApS
// ----------------------------------------------------------------------

echo "<!- includes/alignOpenpostIncludes/doAlign.php -->";
echo __file__."<br>"; 
	$alignDate=usdate($diffdato);
	transaktion('begin');
	$query = db_select("select MAX(udlign_id) as udlign_id from openpost",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) $udlign_id=$row['udlign_id']+1;
	
	if (abs($dkkdiff) > 0.005 && $diffkto) {
echo round($dkkdiff,3)."<br>";
// 20121106 ->
		$q = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
		if ($r = db_fetch_array($q)){
			$year=trim($r['box2']);
			$aarstart=str_replace(" ","",$year.$r['box1']);
			$year=trim($r['box4']);
			$aarslut=str_replace(" ","",$year.$r['box3']);
		}
		list ($year, $month, $day) = explode ('-', $alignDate);
		$year=trim($year);
		$ym=$year.$month;
		
		if (($ym<$aarstart || $ym>$aarslut))	{ #20140505
			
			print "<BODY onLoad=\"javascript:alert('Udligningsdato ($ym) udenfor regnskabs&aring;r ($aarstart - $aarslut)')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;../includes/udlign_openpost.php?post_id=$post_id[0]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=$retur\">";
echo __line__." $diff | $dkkdiff<br>";
exit;
			exit;
			$alignDate=date("Y-m-d");
	}
	// <- 20121106
		if ($basisvaluta!='DKK') {
			$r=db_fetch_array(db_select("select box3 from grupper where art='VK' and box1='$basisvaluta'",__FILE__ . " linje " . __LINE__));
			$diffkto=$r['box3']; 
		}

		if (!$dkkdiff)$dkkdiff=$diff;	
		$logdate=date("Y-m-d");
		$logtime=date("H:i");
		$dkkdiff=afrund($dkkdiff,2);
		$diff=afrund($diff,2);
		$r=db_fetch_array(db_select("select art, kontonr, gruppe, art from adresser where id = '$konto_id[0]'",__FILE__ . " linje " . __LINE__));
		$kontoart=$r['art']; #20160426-1
		$kontonr[0]=$r['kontonr'];
		$gruppe=trim($r['gruppe']);
		$art=trim($r['art']);
		if (substr($art,0,1)=='D') $art='DG';
		else $art='KG';
		$r=db_fetch_array(db_select("select box2 from grupper where art='$art' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
		$samlekonto=$r['box2'];
		$r=db_fetch_array(db_select("select max(regnskabsaar) as tmp from kontoplan",__FILE__ . " linje " . __LINE__));
		if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$samlekonto' and regnskabsaar='$r[tmp]'",__FILE__ . " linje " . __LINE__))) {
			$tekst=findtekst(177,$sprog_id);
			print "<BODY onLoad=\"javascript:alert('$tekst')\">";
		}
		($kontoart=='D')?$bogf_besk="Debitor: $kontonr[0]":$bogf_besk="Kreditor: $kontonr[0]";
		if ($dkkdiff!=$diff) {
			$bogf_besk.=" Udligning af valutadiff, ($valuta[$x] ".dkdecimal($diff).", DKK ".dkdecimal($dkkdiff).")";
		}
		if (abs($dkkdiff)>$maxdiff && $valuta[$x]=='DKK') { #20131129 -> 20160412
			$message=$db." | udlign_openpost | ".$brugernavn." ".date("Y-m-d H:i:s")." | Diff: $diff DKKdiff: $dkkdiff Maxdiff $maxdiff";
			$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'SALDI Opdat fejl', $message, $headers);
			print "<BODY onLoad=\"javascript:alert('Differencen overstiger det maksimalt tilladte')\">"; #20131129
			exit;
		}
		if (abs($diff)<=$maxdiff) { #20150311 + 20161028
			if ($dkkdiff >= 0.01) {
				$qtxt="insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)";
				$qtxt.="values('$diffkto', '0', '$alignDate', '$logdate', '$logtime', '$bogf_besk', '$dkkdiff', '0', '0', '0', '0')";
echo "$qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt="insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)";
				$qtxt.="values('$samlekonto', '0', '$alignDate', '$logdate', '$logtime', '$bogf_besk', '$dkkdiff', '0', '0', '0', '0')";
echo "$qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if ($diff) {
					$vkurs=abs($dkkdiff/$diff*100);
					$tmp=$dkkdiff/$vkurs*100;
					if ($diff>0) $tmp*=-1;
					else $vkurs*=-1;
					$qtxt="insert into openpost";
					$qtxt.="(konto_id,konto_nr,amount,beskrivelse,udlignet,transdate,kladde_id,refnr,valuta,valutakurs,udlign_id,udlign_date)";
					$qtxt.=" values ";
					$qtxt.="('$konto_id[0]','$kontonr[0]','$tmp','$bogf_besk','1','$alignDate','0','0','$basisvaluta','$vkurs','$udlign_id','$alignDate')";
echo "$qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}	else {
					$vkurs=$dkkdiff/0.001*100;
					$tmp=$dkkdiff/$vkurs*100;
					if ($diff<=0)$tmp*=-1;
					$qtxt="insert into openpost";
					$qtxt.="(konto_id,konto_nr,amount,beskrivelse,udlignet,transdate,kladde_id,refnr,valuta,valutakurs,udlign_id,udlign_date)";
					$qtxt.=" values "; 
					$qtxt.="('$konto_id[0]','$kontonr[0]','$tmp','$bogf_besk','1','$alignDate','0','0','$basisvaluta','$vkurs','$udlign_id','$alignDate')";
echo "$qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			} elseif ($dkkdiff <= -0.01) {
echo "DKDIF $dkkdiff<br>";
				$dkkdiff=$dkkdiff*-1;
				$qtxt="insert into transaktioner ";
				$qtxt.="(kontonr, bilag,transdate,logdate,logtime,beskrivelse,kredit,kladde_id,afd,ansat,projekt)";
				$qtxt.=" values ";
				$qtxt.="($diffkto,'0','$alignDate','$logdate','$logtime','$bogf_besk','$dkkdiff','0','0','0','0')";
#cho "$qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt="insert into transaktioner ";
				$qtxt.="(kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)";
				$qtxt.=" values ";
				$qtxt.="('$samlekonto', '0', '$alignDate', '$logdate', '$logtime', '$bogf_besk', '$dkkdiff', '0', '0', '0', '0')";
#cho "$qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if ($diff) {
					$tmp=$diff*-1;
					$qtxt="insert into openpost ";
					$qtxt.="(konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date)";
					$qtxt.=" values ";
					$qtxt.="('$konto_id[0]','$kontonr[0]','$tmp','$bogf_besk','1','$alignDate','0','0',";  #20160426-3
					$qtxt.="'$basisvaluta','$basiskurs','$udlign_id','$alignDate')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				} else {
					$vkurs=$dkkdiff/0.001*100;
					$tmp=$dkkdiff/$vkurs*100; #20160414
					$qtxt="insert into openpost ";
					$qtxt.="(konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date)";
					$qtxt.=" values "; 
					$qtxt.="('$konto_id[0]','$kontonr[0]','$tmp','$bogf_besk','1','$alignDate','0','0','$basisvaluta','$vkurs','$udlign_id','$alignDate')";
#cho "$qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		} else { #20150311
			$message=$db." | udlign_openpost | ".$brugernavn." ".date("Y-m-d H:i:s")." | Diff: $diff DKKdiff: $dkkdiff Maxdiff $maxdiff";
			$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'SALDI Opdat fejl', $message, $headers);
			print "<BODY onLoad=\"javascript:alert('Der er konstateret en posteringsdifference, udligning afbrudt')\">";
			exit;
		}
	}
	for ($x=0; $x<=$postantal; $x++) {
		if ($udlign[$x]=='on') {
			db_modify("UPDATE openpost set udlignet='1', udlign_id='$udlign_id', udlign_date='$alignDate' where id = $post_id[$x]",__FILE__ . " linje " . __LINE__);
		}
	}
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapportart=accountChart&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok\">";

?>

 
