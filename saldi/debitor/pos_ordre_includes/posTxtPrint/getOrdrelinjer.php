<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/posTxtPrint/getOrdrelinjer.php ---------- lap 3.7.4----2019.05.08-------
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
// 06.05.2019 LN Get data from ordrelinjer
    
    $saetpris = 0;
    $x = 0;

	$r=db_fetch_array(db_select("select varenr from ordrelinjer where varenr='R' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
	$rvnr=$r['varenr'];
	$q=db_select("select * from ordrelinjer where ordre_id = '$id' and posnr > 0 and varenr!='$rvnr' order by saet,rabatgruppe,samlevare,posnr,id desc",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if (substr($r['tilfravalg'],0,2)!='L:' || $status>=3){ #20170622-2 tilføjet || $status>=3
			$x++;
			$saet[$x]=$r['saet']*1;
			$momsfri=$r['momsfri'];
			$vatArray[$x]=$r['momssats']; # LN 20190206
			$vatPrice=$r['vat_price'];
			$pris=$vatPrice;
			if (strtoupper($r['varenr'])=='INDBETALING') {
				$pris=$pris*-1;
				$sum=$sum*-1;
			}
			$vare_id[$x]=$r['vare_id']; #20180126
			$varepris[$x]=$r['pris'];
			$m_rabat[$x]=$r['m_rabat']*1;
			$rabat[$x]=$r['rabat']*1;
			$rabatart[$x]=$r['rabatart'];
			$beskrivelse[$x]=$r['beskrivelse'];
			$folgevare[$x]=$r['folgevare'];
			$tilfravalg[$x]=$r['tilfravalg'];
			if ($beskrivelse[$x]) $beskrivelse[$x] = iconv($FromCharset, $ToCharset,$beskrivelse[$x]);
			$antal[$x]=$r['antal']*1;
			$dkkpris[$x]=dkdecimal($pris*$antal[$x],2);
			while(strlen($dkkpris[$x])<9) $dkkpris[$x]=" ".$dkkpris[$x];
			while(strlen($antal[$x])<3) $antal[$x]=" ".$antal[$x];
			if (!strpos($beskrivelse[$x],"<br>") && strlen($beskrivelse[$x])>26) $beskrivelse[$x]=substr($beskrivelse[$x],0,25);
			while(strlen($beskrivelse[$x])<26) $beskrivelse[$x]=$beskrivelse[$x]." ";
			if ($saet[$x]) {
				if ($r['samlevare']) {
					list($pris,$tmp)=explode("|",$r['lev_varenr'],2);
					$dkkpris[$x]=dkdecimal($pris,2);
					while(strlen($dkkpris[$x])<9) $dkkpris[$x]=" ".$dkkpris[$x];
					$saetpris=0;
				} elseif ($x && $saet[$x-1] && $saet[$x-1]!=$saet[$x] && $saetpris) {
					$x++;
					$antal[$x]=1;
					while(strlen($antal[$x])<3) $antal[$x]=" ".$antal[$x];
					$beskrivelse[$x]=$r['beskrivelse'];
					if ($beskrivelse[$x]) $beskrivelse[$x] = iconv($FromCharset, $ToCharset,$beskrivelse[$x]);
					while(strlen($beskrivelse[$x])<26) $beskrivelse[$x]=$beskrivelse[$x]." ";
					$dkkpris[$x]="";
					while(strlen($dkkpris[$x])<9) $dkkpris[$x]=" ".$dkkpris[$x];
					$saetpris=0;
				} else {
					$saetpris+=$pris*$antal[$x]-($pris*$antal[$x]*$rabat[$x]/100);
					$dkkpris[$x]='';
				}
			} elseif (!$rvnr && $rabat[$x]) {
				$y=$x;
				$x++;
				$antal[$x]=$antal[$y];
				if ($rabatart[$y]=='amount') {
					$beskrivelse[$x]="Rabat";
					$pris=$rabat[$y]*-1;
				} else {
					$beskrivelse[$x]="Rabat ".$rabat[$y]."%";
					$pris=$r['pris']/100*$rabat[$y]*-1;
				}
				if ($r['momsfri']!='on') $pris+=$pris/100*$momssats;
				$dkkpris[$x]=dkdecimal($pris*$r['antal'],2);
				while(strlen($dkkpris[$x])<9){
					$dkkpris[$x]=" ".$dkkpris[$x];
				}
				while(strlen($antal[$x])<3){
					$antal[$x]=" ".$antal[$x];
				}
				if (strlen($beskrivelse[$x])>26) $beskrivelse[$x]=substr($beskrivelse[$x],0,25);
				while(strlen($beskrivelse[$x])<26){
					$beskrivelse[$x]=$beskrivelse[$x]." ";
				}
			}
			if ($status<3 && $m_rabat[$x]) { #20141025
				$x++;
				$antal[$x]=$antal[$x-1];
				if ($r['momsfri']!='on') $pris=$m_rabat[$x-1]+$m_rabat[$x-1]/100*$momssats;
				else $pris=$m_rabat[$x-1];
				$pris*=-1;
				$dkkpris[$x]=dkdecimal($pris*$antal[$x],2);
				$beskrivelse[$x]="Rabat";
				while(strlen($dkkpris[$x])<9){
					$dkkpris[$x]=" ".$dkkpris[$x];
				}
				while(strlen($antal[$x])<3){
					$antal[$x]=" ".$antal[$x];
				}
				if (strlen($beskrivelse[$x])>26) $beskrivelse[$x]=substr($beskrivelse[$x],0,25);
				while(strlen($beskrivelse[$x])<26){
					$beskrivelse[$x]=$beskrivelse[$x]." ";
				}
			}
			if ($folgevare[$x] > 0 || $tilfravalg[$x]) {
				if ($tilfravalg[$x]) $tfvare=explode(chr(9),$tilfravalg[$x]);
				else $tfvare[0]=$folgevare[$x];
				if ($status<3) { 	#20170622-2 rettet <5 til <3
					for($fv=0;$fv<count($tfvare);$fv++) {
                        getProductInfo($tfvare, $fv);
					}
				} 
			}
		}
	}
	



?>

