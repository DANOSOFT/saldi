<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/posTxtPrint/getOrdrelinjer.php -- lap 4.0.4 -- 2021.11.24 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20190506 LN Get data from ordrelinjer
// 20200721 PHR Added conversion of é & É 
// 20200905 PHR Added ItemNo & ItemName
// 20211124 PHR sets saet,salmevare and rabatgruppe to 0 if NULL to make sort function correct.
    
    $saetpris = 0;
    $x = 0;

	$qtxt = "update ordrelinjer set saet = '0' where saet is NULL and ordre_id = '$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update ordrelinjer set rabatgruppe = '0' where rabatgruppe is NULL and ordre_id = '$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update ordrelinjer set samlevare = '0' where (samlevare = '' or samlevare is NULL) and ordre_id = '$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "select varenr from ordrelinjer where varenr='R' and ordre_id='$id'";
	($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$rvnr=$r['varenr']:$rvnr='';
	$qtxt = "select * from ordrelinjer where ordre_id = '$id' and posnr > 0 and varenr!='$rvnr' ";
	$qtxt.= "order by saet,rabatgruppe,samlevare,posnr,id desc";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
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
			$variant_id[$x]=$r['variant_id']; 
			$varepris[$x]=$r['pris'];
			$itemNo[$x]=$r['varenr'];
			$m_rabat[$x]=$r['m_rabat']*1;
			$rabat[$x]=$r['rabat']*1;
			$rabatart[$x]=$r['rabatart'];
			$beskrivelse[$x]=$r['beskrivelse'];
			$itemName[$x]=$r['beskrivelse'];
			$folgevare[$x]=$r['folgevare'];
			$tilfravalg[$x]=$r['tilfravalg'];
			$beskrivelse[$x]=str_replace('é','e+acute',$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('È','E+acute',$beskrivelse[$x]);
			if ($FromCharset=='UTF-8') $beskrivelse[$x]=utf8_decode($beskrivelse[$x]);
			if (iconv('iso-8859-1','cp865//TRANSLIT',$beskrivelse[$x])) {
				$beskrivelse[$x] = iconv('iso-8859-1','cp865//TRANSLIT',$beskrivelse[$x]);
			} elseif (iconv('iso-8859-1','cp865//IGNORE',$beskrivelse[$x])) {
				$beskrivelse[$x] = iconv('iso-8859-1','cp865//IGNORE',$beskrivelse[$x]);
			}  
			$beskrivelse[$x]=str_replace('e+acute',chr(130),$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('E+acute',chr(144),$beskrivelse[$x]);
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
				} elseif ($x && isset($saet[$x-1]) && $saet[$x-1]!=$saet[$x] && $saetpris) {
					$x++;
					$antal[$x]=1;
					while(strlen($antal[$x])<3) $antal[$x]=" ".$antal[$x];
					$beskrivelse[$x]=$r['beskrivelse'];
					$item[$x]=$r['beskrivelse'];
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
					$itemName[$x]=$beskrivelse[$x]="Rabat";
					$item[$x]=$r['Rabat'];
					$pris=$rabat[$y]*-1;
				} else {
					$itemName[$x]=$beskrivelse[$x]="Rabat ".$rabat[$y]."%";
					$item[$x]="Rabat ".$rabat[$y]."%";
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
				$item[$x]="Rabat";
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

