<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/payListFunc.php --- Patch 4.0.5 --- 2022.01.31 ---
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
// Copyright (c) 2003-2022 saldi.dk aps
// -----------------------------------------------------------------------------------
//
// 20220131 Moved functions from debitor/betalinger.php
// 20231213 MSC - Implementing new design

function betalingskontrol($erh,$fra_kto,$egen_ref,$til_kto,$kort_ref,$modt_navn,$belob,$valuta,$betalingsdato) {
	global $myCtry;
		if (!$betalingsdato) $betalingsdato = date('dmY');
		$x=0;
		$k1[$x]=$k2[$x]=$k3[$x]=$k4[$x]=$k5[$x]=$k6[$x]=$k7[$x]=$k8[$x]=$k9[$x]=NULL;

	
	if (!$fra_kto || !is_numeric($fra_kto)) {
		if ($myCtry=='NO' && strlen($fra_kto)!=11) $k1[$x] = "Egen konto ikke gyldig";
		elseif (strlen($fra_kto)!=11) $k1[$x] = "Egen konto ikke gyldig"; 
	}
	if ($erh=='ERH351'||$erh=='ERH352'||$erh=='ERH358'||$erh=='SDCK020') {
		if (!$til_kto || !is_numeric($til_kto)||strlen($til_kto)!=8) $k3[$x] = "Modtager konto ikke gyldig - skal være på 8 cifre";
		if (!$kort_ref || !is_numeric($kort_ref)) {
			$k4[$x] = "Ugyldig betalingsidentifikation (modt. ref - må kun bestå af cifre)";
		} else {
			if ($erh=='ERH351'||$erh='SDCK020') {
				$len=15; #strlen af ERH351 og SDCK020 skal vaere 15
			} else { 
				$len=16;
			}
			for($x=strlen($kort_ref);$x<$len;$x++) $kort_ref='0'.$kort_ref;
			for ($x=$len-1;$x>=0;$x--) { #Beregning af kontrolciffer.
				$y=substr($kort_ref,$x,1)*2;
				$x--;
				$y=substr($kort_ref,$x,1)*1;
			}
			while ($y>9) { #Reduktion af kontrolciffer
				$y=substr($y,0,1)+$y=substr($y,1,1);	
			}
			if (substr($kort_ref,-1) != $y) $kommentar = "Ugyldig betalingsidentifikation (modt. ref - kontrolciffer passer ikke)";
		}
	} elseif ($erh=='ERH355'||$erh=='ERH356'|| $erh='SDC3') {
		if ($myCtry=='NO' && strlen($til_kto)!=11) {
			$k3[$x] = "Modtager konto ikke gyldig - skal være på 11 cifre";
		}	elseif (!$til_kto || !is_numeric($til_kto) || ($myCtry!='NO' && strlen($til_kto)!=14)) {
			$k3[$x] = "Modtager konto ikke gyldig - skal være på 14 cifre (regnr. på 4 og kontonr på 10)";
		}
		if(!$kort_ref) $k4[$x] = "Modt ref skal udfyldes";
	}
	if (usdecimal($belob,2)<0.01) $k4[$x]="Bel&oslash;b skal være st&oslash;rre end 0";
	if ($valuta!='DKK') $k5[$x]="Ugyldig valuta, kun DKK kan anvendes";	
	if (strlen($betalingsdato)!=8) $k6[$x]="ugyldig dato - skal v&aelig;re i formatet ddmmyyyy";
	$dag=substr($betalingsdato,0,2);
	$md=substr($betalingsdato,2,2);
	$aar=substr($betalingsdato,4);
	$bd=$aar.$md.$dag;
	$dd=date("Ymd");
	if ($dd>$bd) $k8[$x]="Betalingsdato er overskredet";
	if (!checkdate($md, $dag, $aar)) $k8[$x]="ugyldig dato - skal v&aelig;re i formatet ddmmyyyy";
	#	echo "$kort_ref,$kommentar -- ";
	return(array($kort_ref,$k1[$x],$k2[$x],$k3[$x],$k4[$x],$k5[$x],$k6[$x],$k7[$x],$k8[$x],$k9[$x]));
}

function fejl_i_betalingslinje($linjenr_fejl, $erh_fejl,$fra_kto_fejl,$egen_ref_fejl,$til_kto_fejl,$kort_ref_fejl,$modt_navn_fejl,$belob_fejl,$valuta_fejl,$betalingsdato_fejl)
{
	$retur="Fejl i linje ".$linjenr_fejl.":";
	if($erh_fejl) $retur.=" ".$erh_fejl.".";
	if($fra_kto_fejl) $retur.=" ".$fra_kto_fejl.".";
	if($egen_ref_fejl) $retur.=" ".$egen_ref_fejl.".";
	if($til_kto_fejl) $retur.=" ".$til_kto_fejl.".";
	if($kort_ref_fejl) $retur.=" ".$kort_ref_fejl.".";
	if($modt_navn_fejl) $retur.=" ".$modt_navn_fejl.".";
	if($belob_fejl) $retur.=" ".$belob_fejl.".";
	if($valuta_fejl) $retur.=" ".$valuta_fejl.".";
	if($betalingsdato_fejl) $retur.=" ".$betalingsdato_fejl.".";
	$retur.="<br />\n";
	
	return $retur;
}
function udskriv_nordea($db_id,$bruger_id,$liste_id) {
	$dd=date("Ymd");
	$filnavn="../temp/$db_id"."$bruger_id".".UTF";
	$fp=fopen("$filnavn","w");
	$qtxt="select firmanavn from adresser where art='S'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$myName=$r['firmanavn'];
	
	$qtxt="select * from betalinger where liste_id=$liste_id order by betalingsdato";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		#$kort_ref = $r['kort_ref'];
#		if (substr($r['bet_type'], 0, 3)=="ERH") {
			$x++;
#			if ($betalingsdato<$dd)$betalingsdato=$dd;
#cho __LINE__."<br>";
			$linje='"0","45","","","","","","","","","","'; #felt 1-11
			$linje.=$r['modt_navn']; #felt 12 Modtager
			$linje.='","","","","","'; # felt 13-16 
			$linje.=substr($r['til_kto'],0,4); # felt 17 Til-regnr
			$tmp=substr($r['til_kto'],4,10); # 10 Til-konto-nr
			while (strlen($tmp)<10)$tmp="0".$tmp;
			$linje.=$tmp; # felt 17 Til-konto-nr
			$linje.='","","","","","","';  # felt 18-22
			$linje.=$r['kort_ref']; # felt 23 meddelelse til kunde  
			$linje.='","';
			$linje.=$r['kort_ref']; # felt 24   
			$linje.='","0","';# felt 25
			$linje.=$r['kort_ref']; # felt 26   
			$linje.='","","","","","DKK","","'; # felt 27-32
			$linje.=usdecimal($r['belob'],2); # felt 33;
			$linje.='","'; #Felt 33
			$linje.=str_replace("-","",usdate($r['betalingsdato'])); #Felt 34
			$linje.='","","","'; #Felt 35-36
			$linje.=substr($r['fra_kto'],0,4); # Felt 37 Til-regnr
			$tmp=substr($r['fra_kto'],4,10); # Til-konto-nr
			while (strlen($tmp)<10)$tmp="0".$tmp;
			$linje.=$tmp; # Felt 37 Til-konto-nr
			$linje.='","';
			$linje.=substr($r['egen_ref'],0,20); #Felt 38 (max 20 tegn)
			$linje.='","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","","';
#			$linje.='","","","","","","","","","","","","","","","'; #Felt 39-52
#			$linje.=$r['kort_ref']; # felt 53 Kort advis
#			$linje.='","","","","","","","","","","","","","","","","","","","","","","","","","","","0","'; #Felt 54-91
#			$linje.=$r['kort_ref']; # felt 92 Modtagers identifikation af afsender  
			$linje.='"';
			$linje.="\r\n";
		fwrite($fp,$linje);
	}
	fclose($fp);
	print "<tr><td colspan=3 height=200 widht=100%><br></td></tr>\n";
#	if ( $fejl_i_liste ) {
#		print "<tr><td width='20%'><br /></td><td><b>Advarsel: Fejl i betalinger:</b><br>\n";
#		print $fejl_i_liste;
#		print "</td><td width='20%'><br /></td></tr>\n";
#	}
	print "<tr><td width=40%><br></td><td $top_bund title=\"Klik på knappen for at &aring;bne betalingsfilen eller h&oslash;jreklik for at gemme\"> <a href='$filnavn'>Se / gem betalingsfil</a></td><td width=40%><br></td></tr>\n";

}
function udskriv_danskebank($db_id,$bruger_id,$liste_id) {
	$dd=date("Ymd");
	$filnavn="../temp/$db_id"."$bruger_id".".UTF";
	$fp=fopen("$filnavn","w");
	$qtxt="select firmanavn from adresser where art='S'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$myName=$r['firmanavn'];
	
	$qtxt="select * from betalinger where liste_id=$liste_id order by betalingsdato";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		#$kort_ref = $r['kort_ref'];
#		if (substr($r['bet_type'], 0, 3)=="ERH") {
			$x++;
#			if ($betalingsdato<$dd)$betalingsdato=$dd;
#cho __LINE__."<br>";
			$linje='"CMBO",'; #felt 1
			$linje.='"'.$r['fra_kto'].'",'; # Felt 2 Fra-reg+ktonr
			$linje.='"'.$r['til_kto'].'",'; # Felt 3 Til-reg+ktonr
			$linje.='"'.str_replace('.','',$r['belob']).'",'; # felt 4; Beløb
			if (usdate($r['betalingsdato'])>date('Y-m-d')) $linje.='"'.$r['betalingsdato'].'",'; #Felt 5 Betalingsdato DDMMYYYY
			else $linje.='"",'; #Felt 5 Betaling snarest
			$linje.='"DKK",'; # felt 6 Valuta
			$linje.='"",'; # felt 7 Blank = Standard transfer
			$linje.='"","","","","","","","","","","","",'; # felt 8-19 Ubrugt
			$linje.='"'.$r['egen_ref'].'",'; # felt 20 meddelelse til egen kto  
			$linje.='"",'; # felt 21 Ubrugt
			$linje.='"'.$r['kort_ref'].'"'; # felt 22 meddelelse til kunde  
			$linje.="\r\n";
		fwrite($fp,$linje);
	}
	fclose($fp);
	print "<tr><td colspan=3 height=200 widht=100%><br></td></tr>\n";
#	if ( $fejl_i_liste ) {
#		print "<tr><td width='20%'><br /></td><td><b>Advarsel: Fejl i betalinger:</b><br>\n";
#		print $fejl_i_liste;
#		print "</td><td width='20%'><br /></td></tr>\n";
#	}
	print "<tr><td width=40%><br></td><td $top_bund title=\"Klik på knappen for at &aring;bne betalingsfilen eller h&oslash;jreklik for at gemme\"> <a href='$filnavn'>Se / gem betalingsfil</a></td><td width=40%><br></td></tr>\n";

}
function udskriv_bankdata($db_id,$bruger_id,$liste_id){
	$filnavn="../temp/$db_id"."$bruger_id".".txt";
	$fp=fopen("$filnavn","w");
#		if (substr($r['bet_type'], 0, 3)=="ERH") {
		$linje='"';
		$linje.="IB000000000000";
		$linje.='","';
		$linje.=date("Ymd");
		$linje.='","';
		$tmp='';
		while (strlen($tmp)<90)$tmp.=' '; #felt3
		$linje.=$tmp;		
		$linje.='","';
		$tmp='';
		for ($i=0;$i<3;$i++){
			while (strlen($tmp)<255)$tmp.=' '; #felt4-6
			$linje.=$tmp;		
			if ($i<2) $linje.='","';
		}
		$linje.='"';
		$linje.="\r\n";
		fwrite($fp,$linje);
#	}
	$x=0;
	$sum=0;
	$qtxt="select * from betalinger where liste_id=$liste_id order by betalingsdato";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$kort_ref = $r['kort_ref'];
		if (substr($r['bet_type'], 0, 3)=="ERH") {
			$x++;
#cho __LINE__."<br>";		
			$linje='"';
			$linje.="IB030202000005"; #1 Trans-type 
			$linje.='","';
			$linje.="0001"; #2 Index
			$linje.='","';
			$linje.=substr($r['betalingsdato'],-4).substr($r['betalingsdato'],2,2).substr($r['betalingsdato'],0,2); #3 Eksp-dato
			$linje.='","';
			$tmp=usdecimal($r['belob'],2)*100; #4 Transbeløb
			$sum+=$tmp;
			while (strlen($tmp)<13)$tmp="0".$tmp;
			$linje.=$tmp; 
			$linje.="+"; #Beløb skal slutte med et + 
			$linje.='","';
			$tmp=$r['valuta']; #5 Mønt
			while (strlen($tmp)<3)$tmp.=" ";
			$linje.=$tmp;
			$linje.='","';
			$linje.='2'; #6 Fra-type
			$linje.='","';
			$tmp=$r['fra_kto']; #7 Fra-konto
			while (strlen($tmp)<15)$tmp="0".$tmp;
			$linje.=$tmp; 
			$linje.='","';
			$linje.='2'; #8 Overførselstype
			$linje.='","';
			$tmp=substr($r['til_kto'],0,4); # 9Til-regnr
			$linje.=$tmp;
			$linje.='","';
			$tmp=substr($r['til_kto'],4,10); # 10 Til-konto-nr
			while (strlen($tmp)<10)$tmp="0".$tmp;
			$linje.=$tmp;
			$linje.='","';
			$linje.='0'; #11 Adviseringstype
			$linje.='","';
			$tmp=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $r['kort_ref']),0,20)); #12cPosteringstekst
			while (strlen($tmp)<35)$tmp.=" ";
			$linje.=$tmp;
			$linje.='","';
			$tmp=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $r['modt_navn']),0,32)); #Navn
			while (strlen($tmp)<32)$tmp.=" ";
			$linje.=$tmp;
			$linje.='","';
			$tmp='';
			while (strlen($tmp)<32)$tmp.=" "; #felt 14 Adresse-1
			$linje.=$tmp;
			$linje.='","';
			$tmp='';
			while (strlen($tmp)<32)$tmp.=" "; #felt 15 Adresse-2
			$linje.=$tmp;
			$linje.='","';
			$tmp='';
			while (strlen($tmp)<4)$tmp.=" "; #felt 16 Postnr
			$linje.=$tmp;
			$linje.='","';
			$tmp='';
			while (strlen($tmp)<32)$tmp.=" "; #felt 17 Bynavn
			$linje.=$tmp;
			$linje.='","';
			$tmp='';
			$tmp.=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $r['egen_ref']),0,20)); #18 Eget-bilagnr
			while (strlen($tmp)<35)$tmp.=" ";
			$linje.=$tmp;
			$linje.='","';
			$tmp='';
			for ($i=0;$i<9;$i++) {
				$tmp='';
				while (strlen($tmp)<35)$tmp.=" "; #felt 19-27
				$linje.=$tmp;
				$linje.='","';
			}
			$linje.=" ";# felt 28 Blanke
			$linje.='","';
			$tmp='';
			while (strlen($tmp)<215)$tmp.=" "; #felt 29 Reserveret
			$linje.=$tmp;
			$linje.='"';
			$linje.="\r\n";
			#			if ($r['bet_type']=="ERH351") $kort_ref="71".$kort_ref;
#			$linje="\"$r[bet_type]\",\"$r[fra_kto]\",\"$r[egen_ref]\",\"$r[til_kto]\",\"$r[modt_navn]\",\"00\",\"0\",,\"".str_replace(".", "", $r['belob'])."\",\"".substr($r['betalingsdato'],0,4).substr($r['betalingsdato'],-2)."\",\"$kort_ref\",,,,,,,,,\"N\"\r\n";
		} elseif ($r['bet_type']=="SDCK020") { # SDC betalingstype K020 - FI-kort 71 
#cho __LINE__."<br>";		
			$bet_type=substr($r['bet_type'], 3);
			$linje=$bet_type.$r['fra_kto'].$r['betalingsdato'];
			$linje.=sprintf("%015.2f", str_replace(",", ".", str_replace(".", "", $r['belob'])))."N";
			$linje.=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $r['egen_ref']),0,20));
			$linje.=substr($r['til_kto'],0,8)."71";
			$linje.=sprintf("%015s", substr($kort_ref,0,15)); 
			$linlaengde=strlen($linje); # Linjelængden skal være på 87 tegn ifølge specifikationen - kan testes for senere
			echo "\n<!-- SDCK020 linjelængde er ".strlen($linje)." (skal være 87) -->\n"; # Linjelængden skal være på 87 tegn ifølge specifikationen - kan testes for senere
			$linje.="\r\n";
		} elseif ($r['bet_type']=="SDC3") { # SDC betalingstype 3 - bankovf. med kort advisering
#cho __LINE__."<br>";		
			$linje="3".$r['fra_kto'].substr($r['betalingsdato'],0,4).substr($r['betalingsdato'],-2);
			$linje.=sprintf("%015.2f", str_replace(",", ".", str_replace(".", "", $r['belob'])))."N";
			$linje.=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $r['egen_ref']),0,20));
			$linje.=substr($r['til_kto'], 0, 4).sprintf("%010s", substr($r['til_kto'], 4))."    ";
			$linje.=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $kort_ref),0,20));
			$linlaengde=strlen($linje); # Linjelængden skal være på 95 tegn ifølge specifikationen - kan testes for senere
			$linje.="\r\n";
		}
		fwrite($fp,$linje);
	}	
#	if (substr($r['bet_type'], 0, 3)=="ERH") {
		$linje='"';
		$linje.="IB999999999999";
		$linje.='","';
		$linje.=date("Ymd");
		$linje.='","';
		$tmp=$x;
		while (strlen($tmp)<6)$tmp='0'.$tmp; #felt3
		$linje.=$tmp;		
		$linje.='","';
		$tmp=$sum;
		while (strlen($tmp)<13)$tmp='0'.$tmp; #felt4
		$linje.=$tmp;
		$linje.="+";
		$linje.='","';
		$tmp='';
		while (strlen($tmp)<64)$tmp.=' '; #felt4
		$linje.=$tmp;		
		$linje.='","';
		$tmp='';
		for ($i=0;$i<3;$i++){
			while (strlen($tmp)<255)$tmp.=' '; #felt4
			$linje.=$tmp;		
			if ($i<2) $linje.='","';
		}
		$linje.='"';
		$linje.="\r\n";
		fwrite($fp,$linje);
#	}
	fclose($fp);
	print "<tr><td colspan=3 height=200 widht=100%><br></td></tr>\n";
#	if ( $fejl_i_liste ) {
#		print "<tr><td width='20%'><br /></td><td><b>Advarsel: Fejl i betalinger:</b><br>\n";
#		print $fejl_i_liste;
#		print "</td><td width='20%'><br /></td></tr>\n";
#	}
	print "<tr><td width=40%><br></td><td $top_bund title=\"Klik på knappen for at &aring;bne betalingsfilen eller h&oslash;jreklik for at gemme\"> <a href='$filnavn'>Se / gem betalingsfil</a></td><td width=40%><br></td></tr>\n";
}
function udskriv_bec($db_id,$bruger_id,$liste_id){
	$filnavn="../temp/$db_id"."$bruger_id".".txt";
	$fp=fopen("$filnavn","w");
	$q=db_select("select * from betalinger where liste_id=$liste_id order by betalingsdato",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$kort_ref = $r['kort_ref'];
		if (substr($r['bet_type'], 0, 3)=="ERH") {
			if ($r['bet_type']=="ERH351") $kort_ref="71".$kort_ref;
			$linje="\"$r[bet_type]\",\"$r[fra_kto]\",\"$r[egen_ref]\",\"$r[til_kto]\",\"$r[modt_navn]\",\"00\",\"0\",,\"".str_replace(".", "", $r['belob'])."\",\"".substr($r['betalingsdato'],0,4).substr($r['betalingsdato'],-2)."\",\"$kort_ref\",,,,,,,,,\"N\"\r\n";
		} elseif ($r['bet_type']=="SDCK020") { # SDC betalingstype K020 - FI-kort 71 
			$bet_type=substr($r['bet_type'], 3);
			$linje=$bet_type.$r['fra_kto'].$r['betalingsdato'];
			$linje.=sprintf("%015.2f", str_replace(",", ".", str_replace(".", "", $r['belob'])))."N";
			$linje.=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $r['egen_ref']),0,20));
			$linje.=substr($r['til_kto'],0,8)."71";
			$linje.=sprintf("%015s", substr($kort_ref,0,15)); 
			$linlaengde=strlen($linje); # Linjelængden skal være på 87 tegn ifølge specifikationen - kan testes for senere
			echo "\n<!-- SDCK020 linjelængde er ".strlen($linje)." (skal være 87) -->\n"; # Linjelængden skal være på 87 tegn ifølge specifikationen - kan testes for senere
			$linje.="\r\n";
		} elseif ($r['bet_type']=="SDC3") { # SDC betalingstype 3 - bankovf. med kort advisering
			$linje="3".$r['fra_kto'].substr($r['betalingsdato'],0,4).substr($r['betalingsdato'],-2);
			$linje.=sprintf("%015.2f", str_replace(",", ".", str_replace(".", "", $r['belob'])))."N";
			$linje.=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $r['egen_ref']),0,20));
			$linje.=substr($r['til_kto'], 0, 4).sprintf("%010s", substr($r['til_kto'], 4))."    ";
			$linje.=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $kort_ref),0,20));
			$linlaengde=strlen($linje); # Linjelængden skal være på 95 tegn ifølge specifikationen - kan testes for senere
			$linje.="\r\n";
		}
		fwrite($fp,$linje);
	}	
	fclose($fp);
	print "<tr><td colspan=3 height=200 widht=100%><br></td></tr>\n";
#	if ( $fejl_i_liste ) {
#		print "<tr><td width='20%'><br /></td><td><b>Advarsel: Fejl i betalinger:</b><br>\n";
#		print $fejl_i_liste;
#		print "</td><td width='20%'><br /></td></tr>\n";
#	}
	print "<tr><td width=40%><br></td><td $top_bund title=\"Klik på knappen for at &aring;bne betalingsfilen eller h&oslash;jreklik for at gemme\"> <a href='$filnavn'>Se / gem betalingsfil</a></td><td width=40%><br></td></tr>\n";
}
function udskriv_sdc($db_id,$bruger_id,$liste_id){
	$filnavn="../temp/$db_id"."$bruger_id".".txt";
	$fp=fopen("$filnavn","w");
	$qtxt="select * from betalinger where liste_id=$liste_id order by betalingsdato";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$kort_ref = $r['kort_ref'];
		$linje="3".$r['fra_kto'].substr($r['betalingsdato'],0,4).substr($r['betalingsdato'],-2);
		$linje.=sprintf("%015.2f", str_replace(",", ".", str_replace(".", "", $r['belob'])))."N";
		$linje.=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $r['egen_ref']),0,20));
		$linje.=substr($r['til_kto'], 0, 4).sprintf("%010s", substr($r['til_kto'], 4))."    ";
		$linje.=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $kort_ref),0,20));
#		$linlaengde=strlen($linje);
		$linje.="\r\n";
		fwrite($fp,$linje);
	}
	fclose($fp);
	print "<tr><td colspan=3 height=200 widht=100%><br></td></tr>\n";
#	if ( $fejl_i_liste ) {
#		print "<tr><td width='20%'><br /></td><td><b>Advarsel: Fejl i betalinger:</b><br>\n";
#		print $fejl_i_liste;
#		print "</td><td width='20%'><br /></td></tr>\n";
#	}
	print "<tr><td width=40%><br></td><td $top_bund title=\"Klik på knappen for at &aring;bne betalingsfilen eller h&oslash;jreklik for at gemme\"> <a href='$filnavn'>Se / gem betalingsfil</a></td><td width=40%><br></td></tr>\n";
}
function udskriv_xml($db_id,$bruger_id,$liste_id){

	global $format,$top_bund;
	$r=db_fetch_array(db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$tmp=$r['bank_konto'];
	$myName=$r['firmanavn'];
	$myCvr=trim(str_replace(' ','',$r['cvrnr']));
	$myEan=$r['ean'];
	$myCtry=$r['land'];
	$myBIG=$r['swift'];

	if (strtolower(substr($myCtry,0,1))=='n') {
		$myCtry='NO';
		$myCcy='NOK';
		$x=0;
		$fp=fopen('../importfiler/BIC_NO.txt','r');
		while($line=fgets($fp)) {
			list($regLst[$x],$bicLst[$x])=explode(chr(9),trim($line));
			$x++;
		}
		fclose($fp);
	} else {
		$myCtry='DK';
		$myCcy='DKK';
	}
	
	$r=db_fetch_array(db_select("select tidspkt from betalingsliste where id='$liste_id'",__FILE__ . " linje " . __LINE__));
	$tidspkt = substr($r['tidspkt'],-10);

	$date    = date("Y-m-d",$tidspkt);
	$time    = date("H:i:s",$tidspkt);

	$qtxt="select * from betalinger where liste_id=$liste_id order by id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($r=db_fetch_array($q)) {
		$EndToEndId[$x]  = $r['id'];
		$ordreId[$x]     = $r['ordre_id'];
		$bilagId[$x]     = $r['bilag_id'];
		$PmtInfId[$x]    = $tidspkt ."-". $x;
		$Id[$x]          = $r['til_kto'];
		$Nm[$x]          = urlencode(str_replace('&','og',$r['modt_navn'])); 
		$Prtry[$x]       = urlencode(str_replace('&','og',$r['kort_ref']));
		$DbtrAcctId[$x]  = $r['fra_kto'];
		$CdtrAcctId[$x]  = $r['til_kto'];
		$ReqdExctnDt[$x] = usdate($r['betalingsdato']);
		$InstdAmt[$x]    = usdecimal($r['belob']);
		$Ctry[$x]        = $myCtry;
		$Ccy[$x]         = $myCcy;
		$BIC[$x]         = NULL;
		$DbNo[$x]        = NULL;
		$x++;
	}
	for ($x=0;$x<count($Id);$x++){

		if ($ordreId[$x]) {
			$qtxt = "select konto_id from ordrer where id='$ordreId[$x]'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$DbNo[$x]=$r['konto_id'];
		}
		if (!$DbNo[$x] && $bilagId[$x]) {
			$qtxt = "select d_type,debet,k_type,kredit from kassekladde where id='$bilagId[$x]'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$DbNo[$x]=NULL;
			if ($r['d_type']=='D' && $r['debet']) $DbNo[$x]=$r['debet'];
			elseif ($r['k_type']=='D' && $r['kredit']) $DbNo[$x]=$r['kredit'];
		} 
		if ($DbNo[$x]) {
			$qtxt = "select land,swift from adresser where art='D' and kontonr='$DbNo[$x]'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$BIC[$x]=$r['swift'];
		}
		if ($myCtry=='NO') {
			$DbtrAcctId[$x]=substr($DbtrAcctId[$x],0,4).substr($DbtrAcctId[$x],-7);
			$CdtrAcctId[$x]=substr($CdtrAcctId[$x],-11);
			if (!$myBIC) {
				for ($b=0;$b<count($regLst);$b++) {
					if (substr($DbtrAcctId[$x],0,4)==$regLst[$b]) $myBIC=$bicLst[$b];
				}
			}
			if (!$BIC[$x]) {
				for ($b=0;$b<count($regLst);$b++) {
					if (substr($CdtrAcctId[$x],0,4)==$regLst[$b]) $BIC[$x]=$bicLst[$b];
				}
			}
		}
	}
	$filnavn="../temp/$db_id"."$bruger_id".".xml";
	$fp=fopen("$filnavn","w");
	$xmltxt = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n";
	$xmltxt.= "<Document xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\r\n";
	$xmltxt.= "xmlns=\"urn:iso:std:iso:20022:tech:xsd:pain.001.001.03\"\r\n";
	$xmltxt.= "xsi:schemaLocation=\"urn:iso:std:iso:20022:tech:xsd:pain.001.001.03 pain.001.001.03.xsd\">\r\n";
	$xmltxt.= "  <CstmrCdtTrfInitn>\r\n";
	$xmltxt.= "    <GrpHdr>\r\n";
	$xmltxt.= "      <MsgId>". $db_id ."00000". $liste_id. "</MsgId>\r\n";
	$xmltxt.= "      <CreDtTm>". date("Y-m-d") ."T". date("H:i:s") ."</CreDtTm>\r\n";
	$xmltxt.= "      <NbOfTxs>". count($PmtInfId) ."</NbOfTxs>\r\n";
	$xmltxt.= "      <InitgPty>\r\n";
	$xmltxt.= "        <Nm>". urlencode(str_replace('&','og',$myName)) ."</Nm>\r\n";
	$xmltxt.= "        <Id>\r\n";
	$xmltxt.= "          <OrgId>\r\n";
	$xmltxt.= "            <Othr>\r\n";
	$xmltxt.= "              <Id>". str_replace(' ','',$myCvr) ."</Id>\r\n";
	$xmltxt.= "              <SchmeNm>\r\n";
	$xmltxt.= "                <Cd>CUST</Cd>\r\n";
	$xmltxt.= "              </SchmeNm>\r\n";
	$xmltxt.= "            </Othr>\r\n";
 	$xmltxt.= "            <Othr>\r\n";
 	$xmltxt.= "              <Id>PROVISJON</Id>\r\n";
	$xmltxt.= "              <SchmeNm>\r\n";
	$xmltxt.= "                <Cd>BANK</Cd>\r\n";
	$xmltxt.= "              </SchmeNm>\r\n";
	$xmltxt.= "            </Othr>\r\n";
	$xmltxt.= "          </OrgId>\r\n";
	$xmltxt.= "        </Id>\r\n";
	$xmltxt.= "      </InitgPty>\r\n";
	$xmltxt.= "    </GrpHdr>\r\n";
	fwrite($fp,$xmltxt);
	for ($x=0;$x<count($PmtInfId);$x++) {
		$xmltxt = "    <PmtInf>\r\n";
		$xmltxt.= "      <PmtInfId>$PmtInfId[$x]</PmtInfId>\r\n";
		$xmltxt.= "      <PmtMtd>TRF</PmtMtd>\r\n";
		$xmltxt.= "      <PmtTpInf>\r\n";
		$xmltxt.= "        <InstrPrty>NORM</InstrPrty>\r\n";
		$xmltxt.= "        <SvcLvl>\r\n";
		$xmltxt.= "          <Cd>NURG</Cd>\r\n";
		$xmltxt.= "        </SvcLvl>\r\n";
		$xmltxt.= "        <CtgyPurp>\r\n";
		$xmltxt.= "          <Cd>SUPP</Cd>\r\n";
		$xmltxt.= "        </CtgyPurp>\r\n";
		$xmltxt.= "        </PmtTpInf>\r\n";
		$xmltxt.= "        <ReqdExctnDt>$ReqdExctnDt[$x]</ReqdExctnDt>\r\n";
		$xmltxt.= "        <Dbtr>\r\n";
		$xmltxt.= "          <Nm>". urlencode(str_replace('&','og',$myName)) ."</Nm>\r\n";
		$xmltxt.= "          <PstlAdr>\r\n";
		$xmltxt.= "            <Ctry>$Ctry[$x]</Ctry>\r\n";
		$xmltxt.= "          </PstlAdr>\r\n";
		if ($format != 'norskeBank') {
			$xmltxt.= "          <Id>\r\n";
			$xmltxt.= "            <OrgId>\r\n";
			$xmltxt.= "              <Othr>\r\n";
			$xmltxt.= "               <Id>". str_replace(' ','',$myCvr) ."</Id>\r\n";
			$xmltxt.= "              <SchmeNm>\r\n";
			$xmltxt.= "                <Cd>BANK</Cd>\r\n";
			$xmltxt.= "              </SchmeNm>\r\n";
			$xmltxt.= "               </Othr>\r\n";
			$xmltxt.= "            </OrgId>\r\n";
			$xmltxt.= "          </Id>\r\n";
		}
		$xmltxt.= "        </Dbtr>\r\n";
		$xmltxt.= "        <DbtrAcct>\r\n";
		$xmltxt.= "          <Id>\r\n";
		$xmltxt.= "            <Othr><Id>$DbtrAcctId[$x]</Id>\r\n";
		$xmltxt.= "            <SchmeNm>\r\n";
		$xmltxt.= "              <Cd>BBAN</Cd>\r\n";
		$xmltxt.= "            </SchmeNm>\r\n";
		$xmltxt.= "            </Othr>\r\n";
		$xmltxt.= "          </Id>\r\n";
		$xmltxt.= "          <Ccy>$myCcy</Ccy>\r\n";
		$xmltxt.= "        </DbtrAcct>\r\n";
		if ($myBIC) {
			$xmltxt.= "       <DbtrAgt>\r\n";
			$xmltxt.= "         <FinInstnId>\r\n";
			$xmltxt.= "           <BIC>$myBIC</BIC>\r\n";
			$xmltxt.= "           <PstlAdr>\r\n";
			$xmltxt.= "             <Ctry>$myCtry</Ctry>\r\n";
			$xmltxt.= "           </PstlAdr>\r\n";
			$xmltxt.= "         </FinInstnId>\r\n";
			$xmltxt.= "       </DbtrAgt>\r\n";
		}
		$xmltxt.= "       	<CdtTrfTxInf>\r\n";
		$xmltxt.= "         <PmtId>\r\n";
		$InstrId = $x+date('ymdHi');
		$xmltxt.= "           <InstrId>$InstrId</InstrId>\r\n";
		$xmltxt.= "           <EndToEndId>$EndToEndId[$x]</EndToEndId>\r\n";
		$xmltxt.= "         </PmtId>\r\n";
		$xmltxt.= "         <Amt>\r\n";
		$xmltxt.= "           <InstdAmt Ccy='$Ccy[$x]'>$InstdAmt[$x]</InstdAmt>\r\n";
		$xmltxt.= "         </Amt>\r\n";
		if ($BIC[$x]) {
			$xmltxt.= "         <CdtrAgt>\r\n";
			$xmltxt.= "           <FinInstnId>\r\n";
			$xmltxt.= "             <BIC>$BIC[$x]</BIC>\r\n";
			$xmltxt.= "             <PstlAdr>\r\n";
			$xmltxt.= "               <Ctry>$Ctry[$x]</Ctry>\r\n";
			$xmltxt.= "             </PstlAdr>\r\n";
			$xmltxt.= "           </FinInstnId>\r\n";
			$xmltxt.= "         </CdtrAgt>\r\n";
		}
		$xmltxt.= "         <Cdtr>\r\n";
		$xmltxt.= "           <Nm>$Nm[$x]</Nm>\r\n";
		$xmltxt.= "           <PstlAdr>\r\n";
		$xmltxt.= "             <Ctry>$Ctry[$x]</Ctry>\r\n";
		$xmltxt.= "           </PstlAdr>\r\n";
		$xmltxt.= "         </Cdtr>\r\n";
		$xmltxt.= "         <CdtrAcct>\r\n";
		$xmltxt.= "           <Id>\r\n";
		$xmltxt.= "             <Othr>\r\n";
		$xmltxt.= "               <Id>$CdtrAcctId[$x]</Id>\r\n";
		$xmltxt.= "               <SchmeNm>\r\n";
		$xmltxt.= "                 <Cd>BBAN</Cd>\r\n";
		$xmltxt.= "               </SchmeNm>\r\n";
		$xmltxt.= "             </Othr>\r\n";
		$xmltxt.= "           </Id>\r\n";
		$xmltxt.= "         </CdtrAcct>\r\n";
		$xmltxt.= "         <Purp>\r\n";
		$xmltxt.= "           <Prtry>$Prtry[$x]</Prtry>\r\n";
		$xmltxt.= "         </Purp>\r\n";
		$xmltxt.= "       </CdtTrfTxInf>\r\n";
		$xmltxt.= "    </PmtInf>\r\n";
	fwrite($fp,$xmltxt);
	}
	$xmltxt = "</CstmrCdtTrfInitn>\r\n";
	$xmltxt.= "</Document>\r\n";
	fwrite($fp,$xmltxt);
	fclose($fp);
	
/*
  
	$qtxt="select * from betalinger where liste_id=$liste_id order by betalingsdato";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$kort_ref = $r['kort_ref'];
		$linje="3".$r['fra_kto'].substr($r['betalingsdato'],0,4).substr($r['betalingsdato'],-2);
		$linje.=sprintf("%015.2f", str_replace(",", ".", str_replace(".", "", $r['belob'])))."N";
		$linje.=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $r['egen_ref']),0,20));
		$linje.=substr($r['til_kto'], 0, 4).sprintf("%010s", substr($r['til_kto'], 4))."    ";
		$linje.=sprintf("%-20s", substr(iconv("UTF-8", "Windows-1252//IGNORE", $kort_ref),0,20));
#		$linlaengde=strlen($linje);
		$linje.="\r\n";
		fwrite($fp,$linje);
	}
	fclose($fp);
*/	
	print "<tr><td colspan=3 height=200 widht=100%><br></td></tr>\n";
#	if ( $fejl_i_liste ) {
#		print "<tr><td width='20%'><br /></td><td><b>Advarsel: Fejl i betalinger:</b><br>\n";
#		print $fejl_i_liste;
#		print "</td><td width='20%'><br /></td></tr>\n";
#	}
	print "<tr><td width=40%><br></td><td $top_bund title=\"Klik på knappen for at &aring;bne betalingsfilen eller h&oslash;jreklik for at gemme\"> <a href='$filnavn'>Se / gem betalingsfil</a></td><td width=40%><br></td></tr>\n";
}

?>
