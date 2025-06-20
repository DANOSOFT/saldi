<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/boxCountMethods/printBoxCount.php ---------- lap 3.7.7----2019.03.14-------
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
// LN 20190312 Make functions to print the box count
// 2019-03-14	- PHR	Varius changes in function 'setPrintTxt' according to 'changeCardValue'

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

function setPrintTxt($fp, $log, $FromCharset, $ToCharset, $ore_50, $kr_1, $kr_2, $kr_5, $kr_10, $kr_20, $kr_50, $kr_100, $kr_200, $kr_500, $kr_1000, $kr_andet, $valuta, $optval,$changeCardValue) {

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
	
	$kortdiff=0;
	if ($changeCardValue) {
		for ($x=0;$x<count($kortsum);$x++) {
			$kortdiff+=$kortsum[$x]-usdecimal($ny_kortsum[$x],2);
		}
		$kortdiff=afrund($kortdiff,2);
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

