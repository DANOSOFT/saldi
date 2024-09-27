<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/boxCountMethods/boxCount.php --- lap 4.1.1 - 2024.07.30 -
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
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// LN 20190215 Make function to count the box when submitting
// 20190314 PHR Added $ny_kortsum to function setCreditCards
// 20190319 LN Change "Heutiger Umsatz" to "Dagens omsætning" in the danish version
// 20190320 LN Add new button to box count page
// 20200111 PHR changed "if ($optalt)" to if "($_POST['$optael'])" to avoid 'Red' Difference until [Beregn] clicked
// 20200202 PHR function specifyAmount. Added $db as global, kr_200 to logfile and fclose $log(); 
// 20200202 PHR function cashCountResult. Added $db as global, specification of $log, fopen $log, and fclose $log(); 
// 20200202 PHR function cashCountResult. Added $db and $kasse as globals, specification of $log, fopen $log, and fclose $log(); 
// 20200925 PHR Added button [Kasse] to make it possible to open drawer.
// 20210517 PHR Outcommented setting '$ny_kortsum' to '$kortsum' and made 'card diff' red. (whish from Havemøbelland)
// 20230655 PHR	if (count) changed to if ($count) 
// 20240729 PHR Various translations

function setSpecifiedCashText() { 
  global $baseCurrency,$sprog_id;
#  $country = db_fetch_array(db_select("select land from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))['land'];
  if ($baseCurrency == "EUR") {
        return ["tenth" => "10 ¢", "fiveth" => "20 ¢", "half" => "50 ¢", "one" => "1 €", "two" => "2 €", "five" => "5 €", "ten" => "10 €", 
        "twenty" => "20 €", "fifty" => "50 f€", "hundred" => "100 €", "twoHundred" => "200 €", 
        "fiveHundred" => "500 €", "other" => findtekst('630|Andet',$sprog_id),
        "turnover" => findtekst('3075|Dagens omsætning',$sprog_id), "headline" => findtekst('3086|Optæl kassebeholdning for kasse',$sprog_id), 
        "subline" => "(". findtekst('3087|Antal mønter/sedler af hver slags',$sprog_id) .")"];
    } else {
        return ["half" => "50 øre", "one" => "1 kr", "two" => "2 kr", "five" => "5 kr", "ten" => "10 kr", 
                "twenty" => "20 kr", "fifty" => "50 kr", "hundred" => "100 kr", "twoHundred" => "200 kr", 
                "fiveHundred" => "500 kr", "thousand" => "1000 kr", "other" => "Andet",
                "turnover" => "Dagens omsætning", "headline" => "Optæl kassebeholdning for kasse ", 
                "subline" => "(Antal mønter/sedler af hver slags)"];
    }
}
/*
function setCashCountText() {
	global $baseCurrency,$sprog_id;
		echo "<!-- setCashCountText Begin -->\n";
		$country = db_fetch_array(db_select("select land from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))['land'];
    if ($country == "Switzerland") {
        return ["portfolio" => "Morgen portfolio", "newPortfolio" => "Neues morgen portfolio", "dayApproach" => "Heutiger Ansatz", 
                "expInv" => "Erwartetes inventar", "countInv" => "Gezähltes inventar", "diff" => "Unterschied", "fromBox" => "Aus der Box genommen", "currency" => "SFR", "calculate" => "Berechnen", "cancel" => "Rückgängig", "printLast" => "Zuletzt drucken",
                "accept" => "Genehmigen","drawer" => "Schublade"];
    } else {
        return ["portfolio" => "Morgenbeholdning", "newPortfolio" => "Ny Morgenbeholdning", "dayApproach" => "Dagens tilgang", 
                "expInv" => "Forventet beholdning", "countInv" => "Optalt beholdning", "diff" => "Difference", "fromBox" => "Udtages fra kasse", "currency" => "DKK", "calculate" => "Beregn", "cancel" => "Fortryd", "printLast" => "Udskriv sidste",
                "accept" => "Godkend","drawer" => "Skuffe"];
#    }
		echo "<!-- setCashCountText End -->\n";
}
*/
function specifyAmount($omsatning, $kassediff, $optalt, $db, $kasse, $ifs, $ore_10, $ore_20, $ore_50, $kr_1, $kr_2, $kr_5, $kr_10, $kr_20, $kr_50, $kr_100, $kr_200, $kr_500, $kr_1000, $kr_andet, $fiveRappen = 0, $tenRappen = 0, $twentyRappen = 0) {
	global $baseCurrency,$db;
	
	$txt = setSpecifiedCashText();
	$country = db_fetch_array(db_select("select land from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))['land'];

	print "<input type=\"hidden\" name=\"omsatning\" value=\"$omsatning\">\n";
	print "<input type=\"hidden\" name=\"kassediff\" value=\"$kassediff\">\n";
	print "<input type=\"hidden\" name=\"tidl_optalt\" value=\"$optalt\">\n";
	$logfil="../temp/".$db."/kasseopg".str_replace("-","",$kasse).".log";
	print "<tr><td colspan=\"3\" align=\"center\"><b><big>$txt[headline] $kasse</big></b></td></tr>\n";
	file_put_contents($logfil,"\n".date("Y-m-d H:i")."\n\n",FILE_APPEND);
	print "<tr><td colspan=\"3\" align=\"center\">$txt[subline]</td></tr>\n";

	if ($country == "Switzerland")  {
        $temp = $txt['fiveRappen'];
        print "<tr><td align='right'>$temp</td><td> </td> <td align='right'><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"rappen_5\" value=\"$fiveRappen\"></td></tr>\n";
        $temp = $txt['tenRappen'];
        print "<tr><td align='right'>$temp</td><td> </td> <td align='right'><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"rappen_10\" value=\"$tenRappen\"></td></tr>\n";
        $temp = $txt['twentyRappen'];
        print "<tr><td align='right'>$temp</td><td> </td> <td align='right'><input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"rappen_20\" value=\"$twentyRappen\"></td></tr>\n";
	}
	if ($baseCurrency =='EUR') {
		$temp = $txt['tenth'];
		print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='ore_10' value='$ore_10'></td></tr>\n";
		$temp = $txt['fiveth'];
		print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='ore_20' value='$ore_20'></td></tr>\n";
	}
	else {
		print "<input type = 'hidden' name = name='ore_10' value='$ore_10'>";
		print "<input type = 'hidden' name = name='ore_20' value='$ore_20'>";
	}
	$temp = $txt['half'];
	print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='ore_50' value='$ore_50'></td></tr>\n";
	$temp = $txt['one'];
	print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='kr_1' value='$kr_1'></td></tr>\n";
	$temp = $txt['two'];
	print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='kr_2' value='$kr_2'></td></tr>\n";
	$temp = $txt['five'];
	print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='kr_5' value='$kr_5'></td></tr>\n";
	$temp = $txt['ten'];
	print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='kr_10' value='$kr_10'></td></tr>\n";
	$temp = $txt['twenty'];
	print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='kr_20' value='$kr_20'></td></tr>\n";
	$temp = $txt['fifty'];
	print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='kr_50' value='$kr_50'></td></tr>\n";
	$temp = $txt['hundred'];
	print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='kr_100' value='$kr_100'></td></tr>\n";
	$temp = $txt['twoHundred'];
	print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='kr_200' value='$kr_200'></td></tr>\n";
	$temp = $txt['fiveHundred'];
	print "<tr><td align='right'>$temp</td> <td> </td><td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='kr_500' value='$kr_500'></td></tr>\n";
	$temp = $txt['thousand'];
	if ($baseCurrency =='EUR') {
		print "<input type = 'hidden' name='kr_1000' value='$kr_1000'>";
	} else {
		print "<tr><td align='right'>$temp</td><td> </td> <td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='kr_1000' value='$kr_1000'></td></tr>\n";
	}
	$temp = $txt['other'];
	print "<tr><td align='right'>$temp</td> <td> </td> <td align='right'><input style='width:100;text-align:right;font-size:$ifs;' name='kr_andet' value='".dkdecimal($kr_andet,2)."'></td></tr>\n";
#	$txt = "Five rappen $fiveRappen \n Ten rappen $tenRappen \n Twenty rappen $twentyRappen \n1 kr $kr_1\n2 kr $kr_2\n"; 
	$txt = "10 øre\n 20 øre \n 50 øre \n1 kr $kr_1\n2 kr $kr_2\n"; 
	file_put_contents($logfil,$txt,FILE_APPEND);
	$txt = "5 kr $kr_5\n10 kr $kr_10\n20 kr $kr_20\n50 kr $kr_50\n100 kr $kr_100\n200 kr $kr_200\n500 kr $kr_500\n";
	file_put_contents($logfil,$txt,FILE_APPEND);
  $txt = "1000 kr $kr_1000\nAndet kr $kr_andet\n"; 
	file_put_contents($logfil,$txt,FILE_APPEND);
}


function cashCountResult($pfnavn, $kasse, $id, $byttepenge, $ny_morgen, $tilgang, $forventet, $optalt, $kassediff, $color, $mellemkonto, $udtages) {
	global $baseCurrency;

	echo "<!-- ". __file__ ." cashCountResult Begin -->\n";
	global $bruger_id,$db,$ifs,$FromCharset,$sprog_id,$ToCharset;
	
	$qtxt="select box3 from grupper where art = 'POS' and kodenr='2'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
	$x=$kasse-1;
	$tmp=explode(chr(9),$r['box3']);
	$printserver=trim($tmp[$x]);
	if (!$printserver)$printserver='localhost';
	if ($printserver=='box' || $printserver=='saldibox') {
		$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
		if ($fp=fopen($filnavn,'r')) {
			$printserver=trim(fgets($fp));
			fclose ($fp);
		}
	}

	$logfil="../temp/".$db."/kasseopg".str_replace("-","",$kasse).".log";
	
 /*   
	$txtArray = setCashCountText();
	$calc = $txtArray['calculate'];
	$drawer = $txtArray['drawer'];
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
*/    
 $curr        = $baseCurrency;
 $printLast   = findtekst('3091|Udskriv sidste',$sprog_id);
 $portfolio   = findtekst('3088|Morgenbeholdning',$sprog_id);
 $dayApproach = findtekst('3092|Dagens kontanttilgang',$sprog_id);
 $expInv      = findtekst('3093|Forventet beholdning',$sprog_id);
 $countInv    = findtekst('3094|Optalt beholdning',$sprog_id);
 $diff        = findtekst('3095|Difference',$sprog_id);
 $fromBox     = findtekst('3096|Udtag fra kasse',$sprog_id);
 
	print "<tr><td align=\"center\" colspan=\"3\">";
	print "<span onclick='window.open(\"http://$printserver/saldiprint.php?skuffe=1\")'>";
	print "<button type='button' style='width:100px'>". findtekst('3090|Skuffe',$sprog_id) ."</button></span>&nbsp;";
	print "<input type='submit' style='width:100px' name='optael' value='". findtekst('3089|Beregn',$sprog_id) ."'>&nbsp;";
	print "<input type='submit' style='width:100px' name='optael' value='". findtekst('81|Afbryd',$sprog_id) ."'>";
	print "</td></tr>\n";
	print "<tr><td align=\"center\" colspan=\"3\">";
	print "<a href=pos_ordre.php?id=$id&kasse=$kasse&kassebeholdning=on&printXreport=1>";
	print "<input style='width:100px' type=\"button\" name=\"xReport\" value=\"X-rapport\"></a>\n";
	if (file_exists("$pfnavn")) {
		print "<a href=pos_ordre.php?id=$id&kasse=$kasse&udskriv_kasseopg=$pfnavn>";
		print "<input style='width:100px' type=\"button\" name=\"optael\" value=\"$printLast\"></a>\n";
	} else print "<td></td>";
	print "<td></td>";
	
	$txt1 = "         ----- X-report -----";
	$txt2 = "              ".date('d-m-Y');
	file_put_contents("../temp/$db/Xreport$kasse.txt","$txt1\n$txt2\n\n");
	createXreport($portfolio,$byttepenge,$curr);
	displayLine($portfolio,$byttepenge,$curr);
#	print "<tr><td colspan='2'><b>$portfolio</b></td><td align='right'><b>".dkdecimal($byttepenge)."</b> $curr</td></tr>\n";
	createXreport($dayApproach,$tilgang,$curr);
#	file_put_contents("../temp/$db/x-repport.txt",$txt1.$space.$txt2." $curr\n", FILE_APPEND);
	displayLine($dayApproach,$tilgang,$curr);
#	print "<tr><td colspan='2'><b>$txt1</b></td><td align='right'><b>".$txt2."</b> $curr</td></tr>\n";
	createXreport($expInv,$forventet,$curr);
#	file_put_contents("../temp/$db/x-repport.txt",$txt1.$space.$txt2." $curr\n", FILE_APPEND);
	displayLine($expInv,$forventet,$curr);
#	print "<tr><td colspan='2'><b>$txt1</b></td><td align='right'><b>".$txt2."</b> $curr</td></tr>\n";
	createXreport($countInv,$optalt,$curr);
#	file_put_contents("../temp/$db/x-repport.txt",$txt1.$space.$txt2." $curr\n", FILE_APPEND);
	displayLine($countInv,$optalt,$curr);
#	print "<tr><td colspan='2'><b>$txt1</b></td><td align='right'><b>".$txt2."</b> $curr</td></tr>\n";
	file_put_contents($logfil,"$portfolio $byttepenge\n",FILE_APPEND);
	file_put_contents($logfil,"$dayApproach $tilgang\n",FILE_APPEND);
	file_put_contents($logfil,"$expInv $forventet\n",FILE_APPEND);
	file_put_contents($logfil,"$countInv $optalt $curr\n",FILE_APPEND);
	file_put_contents($logfil,"$diff ".$optalt-($byttepenge+$tilgang),FILE_APPEND);
	$count = if_isset($_POST['optael'],NULL);
	if ($count) {
		(afrund($kassediff,2)*1)?$color='red':$color='black'; #20200111
		if ($kassediff) {
			createXreport($diff,$kassediff,$curr);
#			$txt1 = $diff;
#			$txt2 = dkdecimal($kassediff,2);
#			$space = ' ';
#			while (strlen($txt1.$space.$txt2." $curr") < 40) $space.= ' ';
#			file_put_contents("../temp/$db/x-repport.txt",$txt1.$space.$txt2." $curr\n", FILE_APPEND);
#			print "<tr><td colspan='2'><span style='color:$color;'><b>$txt1</b></span</td>";
#			print "<td align='right'><span style='color:$color;'><b>$txt2</b> $curr</span></td></tr>\n";
			displayLine($diff,$kassediff,$curr);
		}
		if ($optalt || $optalt=='0'){
			if ($mellemkonto) {
				$optplusbyt=$optalt-$byttepenge;
				createXreport($fromBox,pos_afrund($udtages,'',''),$curr);
				print "<tr><td colspan='2'><b>$fromBox</b></td><td align='right'>";
				print "<input type=\"hidden\" name='optplusbyt' value='$optplusbyt'>";
				print "<input type=\"text\" style=\"width:100;text-align:right;font-size:$ifs;\"";
				# (!$udtages && $udtages!='0')?$tmp=NULL:$tmp=dkdecimal(pos_afrund($udtages,'',''),2);
				print "name=\"udtages\" value=\"".dkdecimal(pos_afrund($udtages,'',''),2)."\"> $curr</td></tr>\n";
			} else {
				($udtages=0);
			}
			createXreport($fromBox,pos_afrund($udtages,'',''),$curr);
			file_put_contents($logfil,"Udtages $udtages\n",FILE_APPEND);
		}  
		#print "<tr><td colspan='2'><b>$newPortfolio</b></td><td align='right'><b>".dkdecimal($ny_morgen,2)." $curr</b></td></tr>\n";
	} else {
		($optalt)?$tmp=$udtages:$tmp=$byttepenge*-1; 
		print "<tr><td colspan='3'><input type='hidden'name='udtages' value=\"".dkdecimal($tmp,2)."\"><hr></td></tr>";
	}
	echo "<!-- ". __file__ ." cashCountResult End -->\n";
}

function setCreditCards($kontkonto, $kortnavn, $change_cardvalue, $kortsum, $ny_kortsum, $vatRates, $vatAmounts, $accountPayment, $ifs, $kortdiff, $omsatning, $log, $id) {
echo "<!-- function setCreditCards Begin -->\n";
	global $baseCurrency,$db,$db_encode,$kasse,$reportNumber,$sprog_id;

	if ($db_encode=="UTF8") $FromCharset = "UTF-8";
	else $FromCharset = "iso-8859-15";
	$ToCharset = "cp865";

	if (!$kortnavn)   $kortnavn   = array();
	if (!$kortsum)    $kortsum    = array();
	if (!$ny_kortsum) $ny_kortsum = array();

	$logfil="../temp/".$db."/kasseopg".str_replace("-","",$kasse).".log";
#	$curr = setCashCountText()['currency'];
	$curr = $baseCurrency;
	
	for ($x=0;$x<count($kontkonto);$x++) {
		$kortsum[$x]    = (float)if_isset($kortsum[$x],0);
		$ny_kortsum[$x] = (float)if_isset($ny_kortsum[$x],0);
		if ($change_cardvalue) {
			print "<tr><td colspan='2'><b>$kortnavn[$x]</b>(".dkdecimal($kortsum[$x],2).")</td><td align='right'>"; 
			print "<input type='text' style=\"width:100;text-align:right;font-size:$ifs;\" ";
			#if (!$ny_kortsum[$x] && $ny_kortsum[$x]!='0') $ny_kortsum[$x]=dkdecimal($kortsum[$x],2); #20210517
			print "name='ny_kortsum[$x]' value='$ny_kortsum[$x]'> $curr</td>";
			createXreport($kortnavn[$x],$kortsum[$x],$curr);
			displayLine($kortnavn[$x],$kortsum[$x],$curr);
			file_put_contents($logfil,"$kortnavn[$x]($kortsum[$x]) $ny_kortsum[$x]\n",FILE_APPEND);
		} elseif($kortsum[$x]) {
#			$txt1 = $kortnavn[$x];
#			$txt2 = dkdecimal($kortsum[$x],2);
#			$space = ' ';
#			while (strlen($txt1.$space.$txt2." $curr") < 40) $space.= ' ';
#			file_put_contents("../temp/$db/x-repport.txt",$txt1.$space.$txt2." $curr\n", FILE_APPEND);
#			print "<tr><td colspan='2'><b>$txt1</b></td><td align='right'>"; 
#			print "<b>$txt2</b> $curr</td>\n";
			createXreport($kortnavn[$x],$kortsum[$x],$curr);
			displayLine($kortnavn[$x],$kortsum[$x],$curr);
			file_put_contents($logfil,"$kortnavn[$x]($kortsum[$x]) $ny_kortsum[$x]\n",FILE_APPEND);
		}
#		print "</tr>\n";
	}
	if ($kortdiff) {
		print "<tr><td colspan='2'><span style='color:red;'><b>Difference på kort</b></span></td>"; #20210517
		print "<td align='right' ><span style='color:red;'><b>".dkdecimal($kortdiff,2)."</b> $curr</span></td></tr>"; 
	}
#	$txt1 = iconv($FromCharset, $ToCharset,$turnover);
#	$txt2 = dkdecimal($omsatning,2);
#	$space = ' ';
#	while (strlen($txt1.$space.$txt2." $curr") < 40) $space.= ' ';
#	file_put_contents("../temp/$db/x-repport.txt",$txt1.$space.$txt2." $curr\n", FILE_APPEND);
	$acountExists = array();
	array_multisort($vatRates,$vatAmounts);
	for ($i=0;$i<count($vatRates);$i++) {
		$a = "Omsætning ".dkdecimal($vatRates[$i])."% ". findtekst(770, $sprog_id);
		$b = $vatAmounts[$i] += $vatAmounts[$i]*$vatRates[$i]/100;
		createXreport($a,$b,$curr);
		displayLine($a,$b,$curr);
#		$dkAmount  = dkdecimal($vatAmounts[$i] += $vatAmounts[$i]*$vatRates[$i]/100 );
#		while (strlen($dkVatRate) < 5) $dkVatRate = '0' . $dkVatRate;
#		print "<tr><td colspan='2'>Omsætning $dkVatRate% ". findtekst(770, $sprog_id) ;
#		print "<td align='right'> $dkAmount $curr</td></tr>\n";
	}
	if ($accountPayment) {
		createXreport('Indbetalt på konto',$accountPayment,$curr);
		displayLine('Indbetalt på konto',$accountPayment,$curr);
		
#		print "<tr><td colspan='2'>Indbetalt på konto</td><td align='right'>".dkdecimal($accountPayment,2)." DKK</td></tr>";
	}
	$turnover = setSpecifiedCashText()['turnover'];
	createXreport($turnover,$omsatning,$curr);
	displayLine($turnover,$omsatning,$curr);
#	print "<tr><td colspan='2'><b>$turnover</b>";
#	print "<input type='hidden' name='card_total' value='".array_sum($kortsum)."'</td>";
#	print "<td align='right'><b>$txt2</b> $curr</td></tr>\n";
	file_put_contents($logfil,"Dagens omsætning $omsatning\n",FILE_APPEND);
	if (isset($_SESSION['boxZreport']) && $_SESSION['boxZreport'] == true) {
		print "<td><input type = 'hidden' name='reportNumber' value='$reportNumber'>";
		print "<input $disabled style=\"font-size: 20px; border-radius: 12px; position: relative; left: 80px; top: 10px;\" ";
		print "type=\"submit\" name=\"zRapport\" value=\"Z-Rapport\" onclick=\"javascript:return\"></td>";
		unset($_SESSION['boxZreport']);
	}
	print "</form></tbody></table></td>\n";
    if (getCountry() != "Switzerland") {
        print file_get_contents("pos_ordre_includes/boxCountMethods/vejl_kasseoptDK.html"); 
	}
	echo "<!-- function setCreditCards End -->\n";
}
function createXreport($txt,$amount,$curr) {
	echo "<!-- function createXreport Begin -->\n";
	global $db, $db_encode, $kasse;
	if ($db_encode=="UTF8") $FromCharset = "UTF-8";
	else $FromCharset = "iso-8859-15";
	$ToCharset = "cp865";
	$txt    = iconv($FromCharset, $ToCharset,$txt);
	$amount = trim(dkdecimal($amount,2));
	$curr   = trim($curr);
	$space = ' ';
	while (strlen($txt.$space.$amount." $curr") < 40) $space.= ' ';
	file_put_contents("../temp/$db/Xreport$kasse.txt",$txt.$space.$amount."\n",FILE_APPEND);
	echo "<!-- function createXreport End -->\n";
}
function displayLine($txt,$amount,$curr) {
	print "<tr><td colspan='2'><b>$txt</b></td><td align='right'><b>".dkdecimal($amount)."</b> $curr</td></tr>\n";
}
?>

