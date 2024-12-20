<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/boxCountMethods/printBoxCount.php -----patch 4.0.8 ----2023-07-22--
//                           LICENSE
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// LN 20190312 Make functions to print the box count
// 20190314	PHR	Varius changes in function 'setPrintTxt' according to 'changeCardValue'
// 20230623 PHR Added (float) to $omsatning, $byttepenge & $tilgang

function setSpecifiedPrintText() 
{ 
    $country = getCountry();
    if ($country == "Switzerland") {
        return ["fiveRappen" => "5 rappen", "tenRappen" => "10 rappen", "twentyRappen" => "20 rappen", "half" => "½ franc", "one" => "1 franc", "two" => "2 franc", "five" => "5 franc", "ten" => "10 franc", "twenty" => "20 franc", "fifty" => "50 franc", "hundred" => "100 franc", "twoHundred" => "200 franc", "fiveHundred" => "500 franc", "thousand" => "1000 franc", "other" => "Anderes franc"];
    } else {
        return ["half" => "50 øre", "one" => "1 kr:",
                "two" => "2 kr:", "five" => "5 kr:", "ten" => "10 kr:", "twenty" => "20 kr:", "fifty" => "50 kr:", "hundred" => "100 kr:",
                "twoHundred" => "200 kr:", "fiveHundred" => "500 kr:", "thousand" => "1000 kr:",
                "other" => "Andet kr:"];
    }
}


function setSpecifiedCashPrintText() 
{
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

    $country = getCountry();
    if ($country == "Switzerland") {
        return "Zählen genehmigen?";
    } else {
        return "Godkend optælling?";
    }
}

function setPrintTxt($fp, $log, $FromCharset, $ToCharset, $ore_50, $kr_1, $kr_2, $kr_5, $kr_10, $kr_20, $kr_50, $kr_100, $kr_200, $kr_500, $kr_1000, $kr_andet, $valuta, $optval,$changeCardValue,$reportNumber) {

	echo __line__ ."$reportNumber<br>";


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
	
	$kortdiff=0;
	if ($changeCardValue) {
		for ($x=0;$x<count($kortsum);$x++) {
			$kortdiff+=$kortsum[$x]-usdecimal($ny_kortsum[$x],2);
		}
		$kortdiff=afrund($kortdiff,2);
	}
	if ($reportNumber) {
		$qtxt  = "insert into report (date,type,description,count,total,report_number) values ";
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[half]','0','". $ore_50*1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[one]','0','". $kr_1*1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[two]','0','". $kr_2*1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[five]','0','". $kr_5*1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[ten]','0','". $kr_10*1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[twenty]','0','". $kr_20*1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[fifty]','0','". $kr_50*1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[hundred]','0','". $kr_100*1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[twoHundred]','0','". $kr_200*1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[fiveHundred]','0','". $kr_500*1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[thousand]','0','". $kr_1000*1 ."','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__);
		$qtxt2 = "('$dd','cashCount','$specifiedCashTxt[other]','0','". $kr_andet*1 ."','$reportNumber')";
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
		$qtxt2 = "('$dd','cashCount','$cashCountTxt[turnover]','0','$omsatning','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$cashCountTxt[portfolio]','0','$byttepenge','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$cashCountTxt[dayApproach]','0','$tilgang','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__);
		$tmp=$byttepenge+$tilgang;
		$qtxt2 = "('$dd','cashCount','$cashCountTxt[expInv]','0','$tmp','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$cashCountTxt[countInv]','0','$optalt','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__);
		$tmp=$optalt-($byttepenge+$tilgang+$kortdiff);
		$qtxt2 = "('$dd','cashCount','$cashCountTxt[diff]','0','$tmp','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		$qtxt2 = "('$dd','cashCount','$cashCountTxt[fromBox] $kasse $cashCountTxt[currency]','0','$udtages','$reportNumber')";
		db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		for ($x=0;$x<count($valuta);$x++) {
			$qtxt2 = "('$dd','cashCount','Morgenbeholdning $valuta[$x]:','0','". $ValutaByttePenge[$x] ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			$qtxt2 = "('$dd','cashCount','Dagens tilgang $valuta[$x]:','0','". usdecimal($ValutaTilgang[$x],2) ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			$tmp=usdecimal($ValutaByttePenge[$x],2)+usdecimal($ValutaTilgang[$x],2);
			$qtxt2 = "('$dd','cashCount','Forventet beholdning $valuta[$x]:','0','$tmp','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			$qtxt2 = "('$dd','cashCount','Optalt beholdning $valuta[$x]:','0','". usdecimal($optval[$x],2) ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			$qtxt2 = "('$dd','cashCount','Difference $valuta[$x]:','0','". usdecimal($ValutaKasseDiff[$x],2) ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
			$qtxt2 = "('$dd','cashCount','Udtaget fra kasse $kasse  $valuta[$x]:','0','". usdecimal($ValutaUdtages[$x],2) ."','$reportNumber')";
			db_modify($qtxt.$qtxt2,__FILE__ . " linje " . __LINE__); 
		}
		if ($kontosum) {
			$qtxt2 = "('$dd','cashCount','Salg på konto','0','". usdecimal($kontosum,2) .#','$reportNumber')";
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
	$tmp = iconv($FromCharset, $ToCharset,$specifiedCashTxt['half']);
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
	fwrite($fp,"   $specifiedCashTxt[thousand]  $kr_1000\n");
	fwrite($log,"  $specifiedCashTxt[thousand]  $kr_1000\n");
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
	$txt1 = iconv($FromCharset, $ToCharset,$cashCountTxt['turnover']);
	$txt2=dkdecimal($omsatning,2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n");
	fwrite($log,"$txt1$txt2\n");

	$txt1 = $cashCountTxt['portfolio'];
	$txt2=dkdecimal($byttepenge,2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n");
	fwrite($log,"$txt1$txt2\n");
	$txt1 = $cashCountTxt['dayApproach'];
	$txt2=dkdecimal($tilgang,2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n");
	fwrite($log,"$txt1$txt2\n");
	$txt1=$cashCountTxt['expInv'];
	$txt2=dkdecimal($byttepenge+$tilgang,2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n");
	fwrite($log,"$txt1$txt2\n");
	$txt1=$cashCountTxt['countInv'];
	$txt2=dkdecimal($optalt,2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n");
	fwrite($log,"$txt1$txt2\n");
	$txt1=$cashCountTxt['diff'];
	$txt2=dkdecimal($optalt-($byttepenge+$tilgang+$kortdiff),2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n");
	fwrite($log,"$txt1$txt2\n");
	$txt1="$cashCountTxt[fromBox] $kasse $cashCountTxt[currency]";
	$txt2=dkdecimal($udtages,2);
	while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
	fwrite($fp,"$txt1$txt2\n\n\n\n");
	fwrite($log,"$txt1$txt2\n\n\n\n");
	for ($x=0;$x<count($valuta);$x++) {
		$txt1="Morgenbeholdning $valuta[$x]:";
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
		$txt1="Difference $valuta[$x]:";
		$txt2=dkdecimal($ValutaKasseDiff[$x],2);
		while (strlen($txt1)+strlen($txt2)<40) $txt2=" ".$txt2;
		fwrite($fp,"$txt1$txt2\n");
		fwrite($log,"$txt1$txt2\n");
		$txt1="Udtaget fra kasse $kasse  $valuta[$x]:";
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

