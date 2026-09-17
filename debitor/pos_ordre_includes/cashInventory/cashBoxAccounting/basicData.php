<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/cashInventory/cashBoxAccounting/basicData.php ---------- lap 3.7.9----2019.05.09-------
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
// LN 20190509 LN Make the most basic data to the ../cashBoxAccounting.php file

	$dd=date("Y-m-d");
	$logtime=date("H:i:s");
	$udtages=if_isset($_POST['udtages']);
	$kassediff=if_isset($_POST['kassediff']);
	$optalt=if_isset($_POST['optalt']);
	if ($optalt) $optalt=usdecimal($optalt,2)*1;
	$kassediff=afrund($kassediff,2);
	if ($udtages) $udtages=usdecimal($udtages,2)*1;
	$valuta=if_isset($_POST['valuta']);
	$ValutaUdtages=if_isset($_POST['ValutaUdtages']);
	$ValutaKasseDiff=if_isset($_POST['ValutaKasseDiff']);
	$ValutaTilgang=if_isset($_POST['ValutaTilgang']);
	for ($x=0;$x<count($valuta);$x++) {
		$ValutaUdtages[$x]=usdecimal($ValutaUdtages[$x],2)*1;
		$ValutaKasseDiff[$x]=$ValutaKasseDiff[$x]*1;
		$ValutaTilgang[$x]=$ValutaTilgang[$x]*1;
	} 
	$r=db_fetch_array(db_select("select var_value from settings where var_name = 'change_cardvalue' limit 1",__FILE__ . " linje " . __LINE__));
	$change_cardvalue=$r['var_value'];
	
	$r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
	$ansat_id=$r['ansat_id']*1;

	$kassekonti=explode(chr(9),$r['box2']);
	$kassekonto=$kassekonti[$kasse-1];
	$afdelinger=explode(chr(9),$r['box3']);
	$afd=$afdelinger[$kasse-1]*1;

	$r=db_fetch_array(db_select("select box2,box3 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$kassekonti=explode(chr(9),$r['box2']);
	$kassekonto=$kassekonti[$kasse-1];
	$afdelinger=explode(chr(9),$r['box3']);
	$afd=$afdelinger[$kasse-1]*1;
	
	# --> 20140709
	$r=db_fetch_array(db_select("select box8,box9 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$mellemkonti=explode(chr(9),$r['box8']);
	$mellemkonto=$mellemkonti[$kasse-1];
	$diffkonti=explode(chr(9),$r['box9']); 
	$diffkonto=$diffkonti[$kasse-1];
	# <-- 20140709

	$x=0;
	if ($vis_saet) $qtxt="select distinct(fakturadate) as fakturadate from ordrer where felt_5='$kasse' and (art = 'PO' or art like 'D%') and status='3' and fakturadate >= '$regnstart' order by fakturadate"; #20150310
	else $qtxt="select distinct(fakturadate) as fakturadate from ordrer where felt_5='$kasse' and (konto_id='0' or betalingsbet='Kontant') and art = 'PO' and status='3' and fakturadate >= '$regnstart' order by fakturadate";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['fakturadate']) {
			$fakturadate[$x]=$r['fakturadate'];
			$x++;
		}
	}
	$x=0;
	$qtxt="select distinct(pos_betalinger.betalingstype) as betaling from pos_betalinger,ordrer where ordrer.felt_5='$kasse' and ordrer.status='3' and ordrer.fakturadate >= '$regnstart' and ordrer.id=pos_betalinger.ordre_id order by pos_betalinger.betalingstype";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['betaling']) {
			$betaling[$x]=$r['betaling'];
			$x++;
		}
	}
	$x=0;
	$k=$kasse-1;
	for ($x=0;$x<count($valuta);$x++) {
		$r=db_fetch_array(db_select("select * from grupper where art = 'VK' and box1 = '$valuta[$x]'",__FILE__ . " linje " . __LINE__));
		$tmp=explode(chr(9),$r['box4']);
		$ValutaKonti[$x]=$tmp[$k];
		$tmp=explode(chr(9),$r['box5']);
		$ValutaMlKonti[$x]=$tmp[$k];
		$tmp=explode(chr(9),$r['box6']);
		$ValutaDifKonti[$x]=$tmp[$k];
		$kodenr=$r['kodenr'];
		$r2=db_fetch_array(db_select("select kurs from valuta where gruppe = '$kodenr' and valdate <= '$dd' order by valdate desc limit 1",__FILE__ . " linje " . __LINE__));
		$valutakurs[$x]=$r2['kurs'];
		$ValutaUdtages[$x]*=$valutakurs[$x]/100;
		$ValutaKasseDiff[$x]*=$valutakurs[$x]/100;
		$ValutaTilgang[$x]*=$valutakurs[$x]/100;
	}
	$x=count($valuta);
	$valuta[$x]='DKK';
	db_modify("update pos_betalinger set valuta = 'DKK' where valuta is NULL or valuta = ''",__FILE__ . " linje " . __LINE__); #20161010


?>

