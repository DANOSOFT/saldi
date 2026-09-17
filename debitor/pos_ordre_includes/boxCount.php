<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre.php ---------- lap 3.7.4----2019.01.07-------
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
// LN 20190215 Make function to the pos_ordre file, handle kasseoptaeling frontend depending on the country


function setSpecifiedCashText() 
{ 
    $country = db_fetch_array(db_select("select land from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))['land'];
    if ($country == "Switzerland") {
        return ["fiveRappen" => "5 rappen", "tenRappen" => "10 rappen", "twentyRappen" => "20 rappen", "half" => "½ franc", "one" => "1 franc", "two" => "2 franc", "five" => "5 franc", "ten" => "10 franc", "twenty" => "20 franc", "fifty" => "50 franc", "hundred" => "100 franc", "twoHundred" => "200 franc", "fiveHundred" => "500 franc", "thousand" => "1000 franc", "other" => "Anderes franc",
        "headline" => "Zählkasse für Box ", "subline" => "(Anzahl Münzen / Banknoten jeder Art)"];
    } else {
        return ["half" => "50 øre", "one" => "1 kr",
                "two" => "2 kr", "five" => "5 kr", "ten" => "10 kr", "twenty" => "20 kr", "fifty" => "50 kr", "hundred" => "100 kr",
                "twoHundred" => "200 kr", "fiveHundred" => "500 kr", "thousand" => "1000 kr", "other" => "Andet",
                "headline" => "Optæl kassebeholdning for kasse ", "subline" => "(Antal mønter/sedler af hver slags)"];
    }
}

function setCashCountText() 
{
    $country = db_fetch_array(db_select("select land from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))['land'];
    if ($country == "Switzerland") {
        return ["portfolio" => "Morgen portfolio", "newPortfolio" => "Neues morgen portfolio", "dayApproach" => "Heutiger Ansatz", 
                "expInv" => "Erwartetes inventar", "countInv" => "Gezähltes inventar", "diff" => "Unterschied", "fromBox" => "Aus der Box genommen", "currency" => "SFR", "calculate" => "Berechnen", "cancel" => "Rückgängig", "printLast" => "Zuletzt drucken",
                "accept" => "Genehmigen"];
    } else {
        return ["portfolio" => "Morgenbeholdning", "newPortfolio" => "Ny Morgenbeholdning", "dayApproach" => "Dagens tilgang", 
                "expInv" => "Forventet beholdning", "countInv" => "Optalt beholdning", "diff" => "Difference", "fromBox" => "Udtages fra kasse", "currency" => "DKK", "calculate" => "Beregn", "cancel" => "Fortryd", "printLast" => "Udskriv sidste",
                "accept" => "Godkend"];
    }
}

function specifyAmount($omsatning, $kassediff, $optalt, $db, $kasse, $log, $ifs, $ore_50, $kr_1, $kr_2, $kr_5, $kr_10, $kr_20, $kr_50, $kr_100, $kr_200, $kr_500, $kr_1000, $kr_andet, $fiveRappen = 0, $tenRappen = 0, $twentyRappen = 0) 
{
    $txt = setSpecifiedCashText();

    $country = db_fetch_array(db_select("select land from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))['land'];

    print "<input type=\"hidden\" name=\"omsatning\" value=\"$omsatning\">\n";
	print "<input type=\"hidden\" name=\"kassediff\" value=\"$kassediff\">\n";
	print "<input type=\"hidden\" name=\"tidl_optalt\" value=\"$optalt\">\n";
	$logfil="../temp/".$db."/kasseopg".str_replace("-","",$kasse).".log";
	$log=fopen("$logfil","a");
	print "<tr><td colspan=\"3\" align=\"center\"><b><big>$txt[headline] $kasse</big></b></td></tr>\n";
	fwrite($log,"\n".date("Y-m-d H:i")."\n\n");
	print "<tr><td colspan=\"3\" align=\"center\">$txt[subline]</td></tr>\n";

	if ($country == "Switzerland")  {
        $temp = $txt['fiveRappen'];
        print "<tr><td align=\"right\">$temp</td><td> </td> <td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"rappen_5\" value=\"$fiveRappen\"></td></tr>\n";
        $temp = $txt['tenRappen'];
        print "<tr><td align=\"right\">$temp</td><td> </td> <td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"rappen_10\" value=\"$tenRappen\"></td></tr>\n";
        $temp = $txt['twentyRappen'];
        print "<tr><td align=\"right\">$temp</td><td> </td> <td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"rappen_20\" value=\"$twentyRappen\"></td></tr>\n";
	}
	$temp = $txt['half'];
	print "<tr><td align=\"right\">$temp</td> <td> </td><td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"ore_50\" value=\"$ore_50\"></td></tr>\n";
	
	$temp = $txt['one'];
	print "<tr><td align=\"right\">$temp</td> <td> </td><td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"kr_1\" value=\"$kr_1\"></td></tr>\n";
	$temp = $txt['two'];
	print "<tr><td align=\"right\">$temp</td> <td> </td><td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"kr_2\" value=\"$kr_2\"></td></tr>\n";
	$temp = $txt['five'];
	print "<tr><td align=\"right\">$temp</td> <td> </td><td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"kr_5\" value=\"$kr_5\"></td></tr>\n";
	$temp = $txt['ten'];
	print "<tr><td align=\"right\">$temp</td> <td> </td><td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"kr_10\" value=\"$kr_10\"></td></tr>\n";
	$temp = $txt['twenty'];
	print "<tr><td align=\"right\">$temp</td> <td> </td><td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"kr_20\" value=\"$kr_20\"></td></tr>\n";
	$temp = $txt['fifty'];
	print "<tr><td align=\"right\">$temp</td> <td> </td><td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"kr_50\" value=\"$kr_50\"></td></tr>\n";
	$temp = $txt['hundred'];
	print "<tr><td align=\"right\">$temp</td> <td> </td><td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"kr_100\" value=\"$kr_100\"></td></tr>\n";
	$temp = $txt['twoHundred'];
	print "<tr><td align=\"right\">$temp</td> <td> </td><td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"kr_200\" value=\"$kr_200\"></td></tr>\n";
	$temp = $txt['fiveHundred'];
	print "<tr><td align=\"right\">$temp</td> <td> </td><td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"kr_500\" value=\"$kr_500\"></td></tr>\n";
	$temp = $txt['thousand'];
	print "<tr><td align=\"right\">$temp</td><td> </td> <td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"kr_1000\" value=\"$kr_1000\"></td></tr>\n";
	$temp = $txt['other'];
	print "<tr><td align=\"right\">$temp</td> <td> </td> <td align=\"right\"><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"kr_andet\" value=\"".dkdecimal($kr_andet,2)."\"></td></tr>\n";
	
	
    fwrite($log,"Five rappen $fiveRappen \n Ten rappen $tenRappen \n Twenty rappen $twentyRappen \n1 kr $kr_1\n2 kr $kr_2\n5 kr $kr_5\n10 kr $kr_10\n20 kr $kr_20\n50 kr $kr_50\n100 kr $kr_100\n500 kr $kr_500\n");
    fwrite($log,"1000 kr $kr_1000\nAndet kr $kr_andet\n");
}


function cashCountResult($pfnavn, $kasse, $id, $byttepenge, $ny_morgen, $tilgang, $forventet, $optalt, 
            $kassediff, $color, $mellemkonto, $udtages)
{
    $txtArray = setCashCountText();
    
    $calc = $txtArray['calculate'];
    $cancel = $txtArray['cancel'];
    $printLast = $txtArray['printLast'];
    $portfolio = $txtArray['portfolio'];
    $newPortfolio = $txtArray['newPortfolio'];
    $dayApproach = $txtArray['dayApproach'];
    $expInv = $txtArray['expInv'];
    $countInv = $txtArray['countInv'];
    $diff = $txtArray['diff'];
    $fromBox = $txtArray['fromBox'];
    $curr = $txtArray['currency'];
    
    print "<tr><td align=\"center\" colspan=\"3\"><input type=\"submit\" name=\"optael\" value=\"$calc\">&nbsp;<input type=\"submit\" name=\"optael\" value=\"$cancel\"></td></tr>\n";
	if (file_exists("$pfnavn")) print "<tr><td align=\"center\" colspan=\"3\"><a href=pos_ordre.php?id=$id&kasse=$kasse&udskriv_kasseopg=$pfnavn><input type=\"button\" name=\"optael\" value=\"$printLast\"></a></td></tr>\n";
	print "<tr><td colspan=\"2\"><b>$portfolio</b></td><td align=\"right\"><b>".dkdecimal($byttepenge,2)."</b> $curr</td></tr>\n";
	print "<tr><td colspan=\"2\"><b>$newPortfolio</b></td><td align=\"right\"><b>".dkdecimal($ny_morgen,2)." $curr</b></td></tr>\n";
	print "<tr><td colspan=\"2\"><b>$dayApproach</b></td><td align=\"right\"><b>".dkdecimal($tilgang,2)."</b> $curr</td></tr>\n";
	print "<tr><td colspan=\"2\"><b>$expInv</b></td><td align=\"right\"><b>".dkdecimal($forventet,2)."</b> $curr</td></tr>\n";
	print "<tr><td colspan=\"2\"><b>$countInv</b></td><td align=\"right\"><b>".dkdecimal($optalt,2)."</b> $curr</td></tr>\n";
	(abs($kassediff)>0)?$color='red':$color='black';
	print "<tr><td colspan=\"2\"><span style='color:$color;'><b>$diff</b></span</td>";
	print "<td align=\"right\"><span style='color:$color;'><b>".dkdecimal($kassediff,2)."</b> $curr</span></td></tr>\n";
	
	fwrite($log,"$portfolio $byttepenge\n");
	fwrite($log,"$dayApproach $tilgang\n");
	fwrite($log,"$expInv ");
	fwrite($log,$forventet."\n");
	fwrite($log,"$countInv $optalt $curr\n");
	fwrite($log,"$diff ");
	fwrite($log,$optalt-($byttepenge+$tilgang)."\n");
	
    if ($optalt || $optalt=='0'){
        if ($mellemkonto) {
            $optplusbyt=$optalt-$byttepenge;
            print "<tr><td colspan=\"2\"><b>$fromBox</b></td><td align=\"right\">";
            print "<input type=\"hidden\" name='optplusbyt' value='$optplusbyt'>";
            print "<input type=\"text\" style=\"width:100;text-align:right;font-size:$ifs;\"";
            (!$udtages && $udtages!='0')?$tmp=NULL:$tmp=dkdecimal(pos_afrund($udtages,'',''),2);
            print "name=\"udtages\" value=\"".dkdecimal(pos_afrund($udtages,'',''),2)."\"> $curr</td></tr>\n";
        } else {
            ($udtages=0);
        }
        fwrite($log,"Udtages $udtages\n");
    }

  
}


?>

