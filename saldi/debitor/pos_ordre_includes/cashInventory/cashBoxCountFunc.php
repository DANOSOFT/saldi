<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/cashInventory/cashBoxCountFunc.php ---------- lap 3.7.9----2019.05.09-------
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
// LN 20190310 LN Set the function kasseoptalling here

function kasseoptalling ($kasse,$optalt,$ore_50,$kr_1,$kr_2,$kr_5,$kr_10,$kr_20,$kr_50,$kr_100,$kr_200,$kr_500,$kr_1000,$kr_andet,$optval, $fiveRappen = 0, $tenRappen = 0, $twentyRappen = 0) {
	global $bordnr;
	global $bruger_id;
	global $db;
	global $ifs;
	
	$country = getCountry();	
	$udtages=if_isset($_POST['udtages']);
	if ($udtages) $udtages=usdecimal($udtages); 
	$optplusbyt=if_isset($_POST['optplusbyt']);
	$ny_kortsum=if_isset($_POST['ny_kortsum']);
	$tidl_optalt=if_isset($_POST['tidl_optalt']);
	
	$r=db_fetch_array(db_select("select var_value from settings where var_name = 'change_cardvalue' limit 1",__FILE__ . " linje " . __LINE__));
	$change_cardvalue=$r['var_value'];

	if (db_fetch_array(db_select("select id from grupper where art = 'POS' and kodenr='2' and box7 != ''",__FILE__ . " linje " . __LINE__))) { 
		$qtxt="select ordrer.id,ordrer.nr from ordrer,ordrelinjer where ordrer.art = 'PO' and ordrer.status < 3 and ";
		$qtxt.="ordrer.nr >= '0' and ordrer.felt_5 = '$kasse' and ordrelinjer.ordre_id=ordrer.id and ordrelinjer.id > 0";
		$txt='';
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while  ($r=db_fetch_array($q)) {
			($txt)?$txt.=", $r[id]":$txt="Ordre ID $r[id]";
		}
		if ($txt) {	
			$txt="Der er uafsluttede bestillinger: $txt";
			print tekstboks($txt);
		}
	}
	$svar=find_kassesalg($kasse,$optalt,'DKK');
	$byttepenge=$svar[0];
	$tilgang=$svar[1];
	$diff=$svar[2];
	$kortantal=$svar[3];
	$kontkonto=explode(chr(9),$svar[4]);
	$kortnavn=explode(chr(9),$svar[5]);
	$kortsum=explode(chr(9),$svar[6]);
	$kontosum=$svar[7];
#	$kontosalg=$svar[8];
#cho "$svar[5] $svar[6]<br>";
	$omsatning=$tilgang+$kontosum;

#cho "DKK TG $tilgang Om $omsatning<br>"; 	
	$r=db_fetch_array(db_select("select box8,box9,box14 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$mellemkonti=explode(chr(9),$r['box8']);
	$mellemkonto=$mellemkonti[$kasse-1];
	$diffkonti=explode(chr(9),$r['box9']);
	$diffkonto=$diffkonti[$kasse-1];
	($r['box14'])?$udtag0='on':$udtag0=NULL;

	$kortdiff=0;
	if ($change_cardvalue) {
		for ($x=0;$x<count($kortsum);$x++) {
			$kortdiff+=$kortsum[$x]-usdecimal($ny_kortsum[$x],2);
		}
		$kortdiff=afrund($kortdiff,2);
	}

	$x=0;
	$k=$kasse-1;
	$tmp=array();
	$q = db_select("select * from grupper where art = 'VK' order by box1",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$valuta[$x]=$r['box1'];
		$tmp=explode(chr(9),$r['box4']);
		$ValutaKonti[$x]=$tmp[$k];
		$tmp=explode(chr(9),$r['box5']);
		$ValutaMlKonti[$x]=$tmp[$k];
		$tmp=explode(chr(9),$r['box6']);
		$ValutaDifKonti[$x]=$tmp[$k];
		$kodenr=$r['kodenr'];
		$r2=db_fetch_array(db_select("select kurs from valuta where gruppe = '$kodenr' order by valdate desc limit 1",__FILE__ . " linje " . __LINE__));
		$valutakurs[$x]=$r2['kurs'];
		$x++;
	}

	print "<table><tbody>\n";
	print "<tr><td width='30%'>";
	print "<table><tbody>\n";
	print "<form name=\"optael\" action=\"pos_ordre.php?id=$id&kasse=$kasse&kassebeholdning=on&bordnr=$bordnr\" method=\"post\" autocomplete=\"off\">\n"; 

	print "<input type=\"hidden\" name=\"byttepenge\" value=\"$byttepenge\">\n";
	print "<input type=\"hidden\" name=\"optalt\" value=\"$optalt\">\n";
	print "<input type=\"hidden\" name=\"tilgang\" value=\"$tilgang\">\n";
#	print "<input type=\"hidden\" name=\"kontosalg\" value=\"$kontosalg\">\n";
	print "<input type=\"hidden\" name=\"kontosum\" value=\"$kontosum\">\n";
	for ($x=0;$x<count($kontkonto);$x++) {
		print "<input type=\"hidden\" name=\"kontkonto[$x]\" value=\"$kontkonto[$x]\">\n";
		print "<input type=\"hidden\" name=\"kortnavn[$x]\" value=\"$kortnavn[$x]\">\n";
		print "<input type=\"hidden\" name=\"kortsum[$x]\" value=\"$kortsum[$x]\">\n";
		$omsatning+=$kortsum[$x];
	}
	$kassediff=$optalt-($byttepenge+$tilgang);
	$kassediff-=$kortdiff;

	if (!$optalt) {
		$optalt=$ore_50*0.5+$kr_1+$kr_2*2+$kr_5*5+$kr_10*10+$kr_20*20+$kr_50*50+$kr_100*100+$kr_200*200+$kr_500*500+$kr_1000*1000+$kr_andet + $fiveRappen*0.05 + $tenRappen*0.1 + $twentyRappen*0.2;
	}
#cho __line__." $optalt != $tidl_optalt || $udtages -> $tilgang<br>";
	if ((!$optalt && $optalt!='0')  || $optalt != $tidl_optalt) {
		($udtag0)?$udtages=0:$udtages=$tilgang+$kassediff;
	}
#cho __line__." $udtages -> $tilgang<br>";
#	if (!$udtag0 && !$udtages && ($optalt || $optalt=='0')) {
#		if ($optplusbyt!=$optalt-$byttepenge) $udtages=afrund($optalt-$byttepenge,3); #20170314
#	}

#cho __line__." $udtages<br>";
	
	$forventet=$byttepenge+$tilgang+$kortdiff;
	($optalt)?$ny_morgen=$optalt-$udtages:$ny_morgen=0;

	specifyAmount($omsatning, $kassediff, $optalt, $$db, $kasse, $log, $ifs, $ore_50, $kr_1, $kr_2, $kr_5, $kr_10, $kr_20, $kr_50, $kr_100, $kr_200, $kr_500, $kr_1000, $kr_andet, $fiveRappen, $tenRappen, $twentyRappen);
	if ($valuta[0]) {
		for ($x=0;$x<count($valuta);$x++) {
			print "<tr><td align=\"right\">$valuta[$x]</td><td></td><td align=\"right\">";
			print "<input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"optval[$x]\" value=\"".dkdecimal($optval[$x],2)."\"></td></tr>\n";
			fwrite($log,"$valuta[$x] $optval[$x]\n");
		}
	}
    $pfnavn="../temp/".$db."/kasseopg".str_replace("-","",$kasse).".txt";
	cashCountResult($pfnavn, $kasse, $id, $byttepenge, $ny_morgen, $tilgang, $forventet, $optalt, $kassediff, $color, $mellemkonto, $udtages);
	for ($x=0;$x<count($valuta);$x++) {
			if ($valuta[$x]) {
			$svar=find_kassesalg($kasse,$optval[$x]*$valutakurs[$x]/100,$valuta[$x]);
			if (is_array($svar)){ #20160824
				$byttepenge=$svar[0]*100/$valutakurs[$x];
				$omsatning+=$svar[1];
				$tilgang=$svar[1]*100/$valutakurs[$x];
				$diff=$svar[2]*100/$valutakurs[$x];
				$ValutaKasseDiff[$x]=$optval[$x]-($byttepenge+$tilgang);
#cho "$valuta[$x] TG $tilgang Om $omsatning<br>"; 	
				print "<tr><td colspan=\"3\" align=\"center\">";
				print "<input type=\"hidden\" name=\"kontosum\" value=\"$valuta[$x]\">\n";
				print "<input type=\"hidden\" name=\"valuta[$x]\" value=\"$valuta[$x]\">\n";
				print "<input type=\"hidden\" name=\"ValutaKasseDiff[$x]\" value=\"$ValutaKasseDiff[$x]\">\n";
				print "<input type=\"hidden\" name=\"ValutaByttePenge[$x]\" value=\"$byttepenge\">\n";
				print "<input type=\"hidden\" name=\"ValutaTilgang[$x]\" value=\"$tilgang\">\n";
				print "<b>--- $valuta[$x] ---</b></td></tr>\n";
				print "<tr><td colspan=\"2\"><b>Morgenbeholdning</b></td><td align=\"right\"><b>".dkdecimal($byttepenge,2)."</b> $valuta[$x]</td></tr>\n";
				print "<tr><td colspan=\"2\"><b>Dagens tilgang</b></td><td align=\"right\"><b>".dkdecimal($tilgang,2)."</b> $valuta[$x]</td></tr>\n";
				print "<tr><td colspan=\"2\"><b>Forventet beholdning</b></td><td align=\"right\"><b>".dkdecimal($byttepenge+$tilgang,2)."</b> $valuta[$x]</td></tr>\n";
				print "<tr><td colspan=\"2\"><b>Optalt beholdning</b></td><td align=\"right\"><b>".dkdecimal($optval[$x],2)."</b> $valuta[$x]</td></tr>\n";
				print "<tr><td colspan=\"2\"><b>Difference</b></td><td align=\"right\">";
				print "<b>".dkdecimal($ValutaKasseDiff[$x],2)."</b> $valuta[$x]</td></tr>\n";
				fwrite($log,"Morgenbeholdning $byttepenge\n");
				fwrite($log,"Dagens tilgang $tilgang\n");
				fwrite($log,"Forventet beholdning ".$byttepenge+$tilgang."\n");
				fwrite($log,"Optalt beholdning $optalt DKK\n");
				fwrite($log,"Difference $ValutaKasseDiff[$x]\n");
			} else { #20160824
				print "<tr><td colspan=\"2\" align=\"center\">$svar</td></tr>\n"; 
			}
			if ($optalt || $optalt=='0'){
				if ($ValutaMlKonti[$x]) {
					print "<tr><td colspan=\"2\"><b>Udtages fra kasse</b></td>";
					print "<td align=\"right\">";
					print "<input type=\"text\" style=\"width:100;text-align:right;font-size:$ifs;\" name=\"ValutaUdtages[$x]\" value=\"".dkdecimal(pos_afrund($optval[$x]-$byttepenge,'',''),2)."\">"; 
					print "$valuta[$x]</td></tr>\n";
				} else ($ValutaUdtages[$x]=0);
				fwrite($log,"Udtages $ValutaUdtages[$x] $valuta[$x]\n");
			}
		}
	}
	$calcTxtArr = setCashCountText();
	if (($optalt || $optalt=='0') && $_POST['optael']==$calcTxtArr['calculate']) { #LN 20190219
#		if($kortdiff) {
#			$disabled='disabled';
#			$title='Der kan ikke godkendes når der er differencer på betalingskort';
#		} else {
			$disabled=NULL; 
			$title='Klik her når du er sikker på at have talt korrekt op';
#		}
        $acceptPrint = acceptPrint();
		print "<tr><td align='center' colspan='3' title='$title'><input $disabled type='submit' name='optael' value=\"$calcTxtArr[accept]\" onclick=\"javascript:return confirm('$acceptPrint')\"></td></tr>\n";	}
	if ($kontosum) {
		print "<tr><td colspan=\"2\"><b>Konto</b></td><td align=\"right\"><b>".dkdecimal($kontosum,2)."</b> DKK</td></tr>\n";
		fwrite($log,"Konto $kontosum\n");
	}
	setCreditCards($kontkonto, $kortnavn, $change_cardvalue, $kortsum, $ny_kortsum, $ifs, $kortdiff, $omsatning, $log, $id);
	print "</tr></tbody></table>\n";
	exit;
}


?>

