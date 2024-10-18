<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/boxCountMethods/printBoxCount.php --- patch 4.1.1 --- 2024-10-07 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// LN 20190312 Make functions to the box count
// 20190314	PHR	Varius changes in function 'setPrintTxt' according to 'changeCardValue'
// 20230623 PHR Added (float) to $omsatning, $byttepenge & $tilgang
// 20240729 PHR Various translations
// 20241007 PHR more translations

function setSpecifiedPrintText() {
	global $baseCurrency;

  if ($baseCurrency == "EUR") {
    return ["oneTenth" => "10 cent", "twoTenth" => "20 cent", "fiveTenth" => "50 cent", "one" => "1 eur:", "two" => "2 eur:", "five" => "5 eur:", "ten" => "10 eur:", "twenty" => "20 eur:", "fifty" => "50 eur:", "hundred" => "100 eur:", "twoHundred" => "200 eur:", "fiveHundred" => "500 eur:", "thousand" => "1000 eur:", "other" => "Other eur:"];
  } else {
    return ["fiveTenth" => "50 øre", "one" => "1 kr:", "two" => "2 kr:", "five" => "5 kr:", "ten" => "10 kr:", "twenty" => "20 kr:", "fifty" => "50 kr:", "hundred" => "100 kr:", "twoHundred" => "200 kr:", "fiveHundred" => "500 kr:", "thousand" => "1000 kr:", "other" => "Andet kr:"];
  }
}


function setSpecifiedCashPrintText() {
    $country = getCountry();
    if ($country == "Switzerland") {
        return ["portfolio" => "Morgen portfolio", "newPortfolio" => "Neues morgen portfolio", "dayApproach" => "Heutiger Ansatz: ", 
                "expInv" => "Erwartetes inventar SFR: ", "countInv" => "Gezähltes inventar SFR: ", "diff" => "Unterschied SFR: ", "fromBox" => "Aus der Box genommen ", "currency" => "SFR: ",
                "turnover" => "Heutiger Umsatz: "];
    } else {
        return ["portfolio" => "Morgenbeholdning", "newPortfolio" => "Ny Morgenbeholdning",
                "dayApproach" => "Dagens tilgang: ", 
                "expInv" => "Forventet beholdning DKK: ", "countInv" => "Optalt beholdning DKK: ", 
                "diff" => "Difference DKK: ", "fromBox" => "Udtaget fra kasse ", "currency" => "DKK: ", 
                "turnover" => "Dagens omsætning: "];
    }
}

function acceptPrint() {
	global $sprog_id;
	
    $country = getCountry();
    if ($country == "Switzerland") {
        return "Zählen genehmigen?";
    } else {
        return findtekst('2136|Godkend optælling', $sprog_id);
    }
}

function setPrintTxt($fp, $log, $FromCharset, $ToCharset, $ore_10, $ore_20, $ore_50, $kr_1, $kr_2, $kr_5, $kr_10, $kr_20, $kr_50, $kr_100, $kr_200, $kr_500, $kr_1000, $kr_andet, $valuta, $optval,$changeCardValue,$reportNumber) {

	global  $baseCurrency, $sprog_id;
	
	$dd=date("Y-m-d");
	$specifiedCashTxt = setSpecifiedPrintText();
	$cashCountTxt = setSpecifiedCashPrintText();
	$country = getCountry();
	
	$byttepenge=if_isset($_POST['byttepenge']);
	$tilgang=if_isset($_POST['tilgang']);
	$optalt=if_isset($_POST['optalt']);
	$omsatning=if_isset($_POST['omsatning']);
	$udtages=usdecimal(if_isset($_POST['udtages']));
	$kontkonto=if_isset($_POST['kontkonto']);
	$kortnavn=if_isset($_POST['kortnavn']);
	$kortsum=if_isset($_POST['kortsum']);
	$ny_kortsum=if_isset($_POST['ny_kortsum']);
	$kontosum=if_isset($_POST['kontosum']);
	$ValutaByttePenge=if_isset($_POST['ValutaByttePenge']);
	$ValutaTilgang=if_isset($_POST['ValutaTilgang']);
	$ValutaKasseDiff=if_isset($_POST['ValutaKasseDiff']);
	$ValutaUdtages=if_isset($_POST['ValutaUdtages']);

	$txt2133 = findtekst('2133|Dagens tilgang',$sprog_id);	
	$txt2134 = findtekst('2134|Udtaget fra kasse',$sprog_id);
	$txt2135 = findtekst('2135|Salg på konto',$sprog_id);
	$txt3075 = findtekst('3075|Dagens omsætning',$sprog_id);
	$txt3088 = findtekst('3088|Morgenbeholdning',$sprog_id);
	$txt3093 = findtekst('3093|Forventet beholdning',$sprog_id);
	$txt3094 = findtekst('3094|Optalt beholdning',$sprog_id);
	$txt3095 = findtekst('3095|Difference',$sprog_id);

	$kortdiff=0;
	if ($changeCardValue) {
		for ($x=0;$x<count($kortsum);$x++) {
			$kortdiff+=$kortsum[$x]-usdecimal($ny_kortsum[$x],2);
		}
		$kortdiff=afrund($kortdiff,2);
	}
	if ($reportNumber) {
		$qtxt  = "insert into report (date,type,description,count,total,report_number) values ";
		if ($baseCurrency == 'EUR') {
			$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[oneTenth]','0','". (int)$ore_10 ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[twoTenth]','0','". (int)$ore_20 ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		}
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[fiveTenth]','0','". (int)$ore_50 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[one]','0','". (int)$kr_1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[two]','0','". (int)$kr_2 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[five]','0','". (int)$kr_5 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[ten]','0','". (int)$kr_10 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[twenty]','0','". (int)$kr_20 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[fifty]','0','". (int)$kr_50 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[hundred]','0','". (int)$kr_100 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[twoHundred]','0','". (int)$kr_200 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[fiveHundred]','0','". (int)$kr_500 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		if ($baseCurrency != 'EUR') {
			$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[thousand]','0','". (int)$kr_1000 ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__);
		}
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[other]','0','". (float)$kr_andet ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		if (count($valuta)) {
			for ($x=0;$x<count($valuta);$x++) {
				$qtxt2 = "('$dd','cashCount','$valuta[$x]','0','". usdecimal($optval[$x],2)	 ."','$reportNumber')";
				db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			}
		}
		
		$byttepenge = (float)$byttepenge;
		$omsatning  = (float)$omsatning;
		$optalt     = (float)$optalt; 
		$tilgang    = (float)$tilgang;
		
		$qtxt2 = "('$dd','cashCount','".db_escape_string($txt3075)." $baseCurrency','0','$omsatning','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','".db_escape_string($txt3088)." $baseCurrency','0','$byttepenge','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','".db_escape_string($txt2133)." $baseCurrency','0','$tilgang','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__);
		$tmp=$byttepenge+$tilgang;
		$qtxt2 = "('$dd','cashCount','".db_escape_string($txt3093)." $baseCurrency','0','$tmp','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','".db_escape_string($txt3094)." $baseCurrency','0','$optalt','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__);
		$tmp=$optalt-($byttepenge+$tilgang+$kortdiff);
		$qtxt2 = "('$dd','cashCount','".db_escape_string($txt3094)." $baseCurrency','0','$tmp','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','".db_escape_string($txt2134)." $kasse $baseCurrency','0','$udtages','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		for ($x=0;$x<count($valuta);$x++) {
			$qtxt2 = "('$dd','cashCount','".db_escape_string($txt3088)." $valuta[$x]:','0','". $ValutaByttePenge[$x] ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			$qtxt2 = "('$dd','cashCount','".db_escape_string($txt2133)." $valuta[$x]:','0','". usdecimal($ValutaTilgang[$x],2) ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			$tmp=usdecimal($ValutaByttePenge[$x],2)+usdecimal($ValutaTilgang[$x],2);
			$qtxt2 = "('$dd','cashCount','".db_escape_string($txt3093)." $valuta[$x]:','0','$tmp','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			$qtxt2 = "('$dd','cashCount','".db_escape_string($txt3094)." $valuta[$x]:','0','". usdecimal($optval[$x],2) ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			$qtxt2 = "('$dd','cashCount','".db_escape_string($txt3094)." $valuta[$x]:','0','". usdecimal($ValutaKasseDiff[$x],2) ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			$qtxt2 = "('$dd','cashCount','".db_escape_string($txt2134)." $kasse $valuta[$x]:','0','". usdecimal($ValutaUdtages[$x],2) ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		}
		if ($kontosum) {
			$qtxt2 = "('$dd','cashCount','".db_escape_string($txt2135)."','0','". usdecimal($kontosum,2) .#','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		}
		for ($x=0;$x<count($kortnavn);$x++) {
				$txt1="$kortnavn[$x]";
			if ($changeCardValue) {
				$txt1.="(". dkdecimal($kortsum[$x],2) .")";
				$txt2=usdecimal($ny_kortsum[$x],2);
			} else $txt2=usdecimal($kortsum[$x],2);
			$qtxt2 = "('$dd','cashCount','$txt1','0','$txt2','$reportNumber')";
			if ($txt1) db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__);
		}
	}
	if ($baseCurrency == 'EUR') {
		$tmp = iconv($FromCharset, $ToCharset,$specifiedCashTxt['oneTenth']);
		fwrite($fp,"  $tmp:  $ore_10\n");
		fwrite($log,"  $tmp:  $ore_10\n");
		$tmp = iconv($FromCharset, $ToCharset,$specifiedCashTxt['twoTenth']);
		fwrite($fp,"  $tmp:  $ore_20\n");
		fwrite($log,"  $tmp:  $ore_20\n");
	}
	$tmp = iconv($FromCharset, $ToCharset,$specifiedCashTxt['fiveTenth']);
	fwrite($fp,"  $tmp:  $ore_50\n");
	fwrite($log,"  $tmp:  $ore_50\n");
	fwrite($fp,"   $specifiedCashTxt[one]  $kr_1\n");
	fwrite($log,"  $specifiedCashTxt[one]  $kr_1\n");
	fwrite($fp,"   $specifiedCashTxt[two]  $kr_2\n");
	fwrite($log,"  $specifiedCashTxt[two]  $kr_2\n");
	fwrite($fp,"   $specifiedCashTxt[five]  $kr_5\n");
	fwrite($log,"  $specifiedCashTxt[five]  $kr_5\n");
	fwrite($fp,"   $specifiedCashTxt[ten]  $kr_10\n");
	fwrite($log,"  $specifiedCashTxt[ten]  $kr_10\n");
	fwrite($fp,"   $specifiedCashTxt[twenty]  $kr_20\n");
	fwrite($log,"  $specifiedCashTxt[twenty]  $kr_20\n");
	fwrite($fp,"   $specifiedCashTxt[fifty]  $kr_50\n");
	fwrite($log,"  $specifiedCashTxt[fifty]  $kr_50\n");
	fwrite($fp,"   $specifiedCashTxt[hundred]  $kr_100\n");
	fwrite($log,"  $specifiedCashTxt[hundred]  $kr_100\n");
	fwrite($fp,"   $specifiedCashTxt[twoHundred]  $kr_200\n");
	fwrite($log,"  $specifiedCashTxt[twoHundred]  $kr_200\n");
	fwrite($fp,"   $specifiedCashTxt[fiveHundred]  $kr_500\n");
	fwrite($log,"  $specifiedCashTxt[fiveHundred]  $kr_500\n");
	if ($baseCurrency != 'EUR') {
		fwrite($fp,"   $specifiedCashTxt[thousand]  $kr_1000\n");
		fwrite($log,"  $specifiedCashTxt[thousand]  $kr_1000\n");
	}
	fwrite($fp,"$specifiedCashTxt[other]  ".dkdecimal($kr_andet,2)."\n\n");
	fwrite($log,"$specifiedCashTxt[other]  ".dkdecimal($kr_andet,2)."\n\n");
	if (count($valuta)) {
		for ($x=0;$x<count($valuta);$x++) {
			$txt1=$valuta[$x];
			while(strlen($txt1)<9) $txt1.=' ';
			fwrite($fp,"$valuta[$x]  ".dkdecimal($optval[$x],2)."\n\n");
			fwrite($log,"$valuta[$x]  ".dkdecimal($optval[$x],2)."\n\n");
		}
	}
	$txt1 = $txt3075;
	$txt2=dkdecimal($omsatning,2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n");
	fwrite($log,"$txt1$txt2\n");

	// Morgenbeholdning
	$txt=dkdecimal($byttepenge,2);
	while (strlen($txt3088)+strlen($txt)<40) $txt=" ".$txt;
	fwrite($fp,"$txt3088$txt\n");
	fwrite($log,"$txt3088$txt\n");
	// Dagens tilgang
	$txt1 = $txt2133;
	$txt2=dkdecimal($tilgang,2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n");
	fwrite($log,"$txt1$txt2\n");
	//Forventet beholdning
	$txt1=$txt3093;
	$txt2=dkdecimal($byttepenge+$tilgang,2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n");
	fwrite($log,"$txt1$txt2\n");
	// Optalt beholdning
	$txt1 =$txt3094 ;
	$txt2=dkdecimal($optalt,2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n");
	fwrite($log,"$txt1$txt2\n");
	// Difference
	$txt1=$txt3095;
	$txt2=dkdecimal($optalt-($byttepenge+$tilgang+$kortdiff),2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n");
	fwrite($log,"$txt1$txt2\n");
	$txt1=$txt2134;
	$txt2=dkdecimal($udtages,2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n\n\n\n");
	fwrite($log,"$txt1$txt2\n\n\n\n");
	for ($x=0;$x<count($valuta);$x++) {
		$txt1="$txt3088 $valuta[$x]:";
		$txt2=dkdecimal($ValutaByttePenge[$x],2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		fwrite($log,"$txt1$txt2\n");
		$txt1="Dagens tilgang $valuta[$x]:";
		$txt2=dkdecimal($ValutaTilgang[$x],2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		fwrite($log,"$txt1$txt2\n");
		$txt1="Forventet beholdning $valuta[$x]:";
		$txt2=dkdecimal($ValutaByttePenge[$x]+$ValutaTilgang[$x],2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		fwrite($log,"$txt1$txt2\n");
		$txt1="Optalt beholdning $valuta[$x]:";
		$txt2=dkdecimal($optval[$x],2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		fwrite($log,"$txt1$txt2\n");
		$txt1=$txt3095;
		$txt2=dkdecimal($ValutaKasseDiff[$x],2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		fwrite($log,"$txt1$txt2\n");
		$txt1=$txt2134;
		$txt2=dkdecimal($ValutaUdtages[$x],2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n\n");
		fwrite($log,"$txt1$txt2\n\n");
	}
	if ($kontosum) {
		$txt1="Salg på konto";
		$txt1 = iconv($FromCharset, $ToCharset,$txt1);
		$txt2=dkdecimal($kontosum,2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		fwrite($log,"$txt1$txt2\n");
	}
	for ($x=0;$x<count($kortnavn);$x++) {
		$txt1="$kortnavn[$x]";
		if ($changeCardValue) {
			$txt1.="(". dkdecimal($kortsum[$x],2) .")";
			$txt2=dkdecimal($ny_kortsum[$x],2);
		} else $txt2=dkdecimal($kortsum[$x],2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		if ($kortnavn[$x]) {
			fwrite($fp,"$txt1$txt2\n");
			fwrite($log,"$txt1$txt2\n");
		}
	}
}
?>

