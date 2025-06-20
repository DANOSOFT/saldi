<?php
// -------------systemdata/valutakort.php ----------- lap 3.6.2 -- 2016.01.16
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
// Copyright (c) 2004-2016 DANOSOFT ApS
// ----------------------------------------------------------------------------
// 2013.05.13 - Opdateret liste over valutakoder
// 20150313 CA  Topmenudesign tilføjet                             søg 20150313
// 20150327 CA  Dansk valutakode ændret DKR -> DKK                søg 20150327d
// 20150327 CA  Valutakoder opdateret fra ISO 4217 samt tilføjet XBT Bitcoin søg 20150327v
// 20160116	PHR	Kursgevinst / tab bogføres ved kursændringer og kursændringer blokeres hvis der er bogført efter anført dato søg 20160116

@session_start();
$s_id=session_id();

$modulnr=2;
$title="valutakort";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("../includes/genberegn.php");

if ($menu=='T') {  # 20150313 start
        include_once '../includes/top_header.php';
        include_once '../includes/top_menu.php';
        print "<div id=\"header\">\n";
        print "<div class=\"headerbtnLft\"></div>\n";
        print "</div><!-- end of header -->";
        print "<div id=\"leftmenuholder\">";
        include_once 'left_menu.php';
        print "</div><!-- end of leftmenuholder -->\n";
        print "<div class=\"maincontent\">\n";
        print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\"><tbody>";
} else {
        include("top.php");
        print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
}  # 20150313 stop

$bgcolor=NULL; $bgcolor1=NULL; $dato=date("d-m-Y"); $kurs=NULL; $valuta=NULL; $beskrivelse=NULL;
$kodenr=if_isset($_GET['kodenr']);
$id=if_isset($_GET['id']);



if (isset($_GET['ret'])) print "<BODY onLoad=\"javascript:alert('Ved kursændring skal du ikke rette kursen, men tilføje en ny kurs med angivelse af dato for kursændringen.\\nEllers risikerer du at lave rod i dit regnskab ')\">";

if (isset($_POST['submit'])) {
	$dato=addslashes(if_isset($_POST['dato']));
	$kurs=addslashes(if_isset($_POST['kurs']));
	$valuta=addslashes(if_isset($_POST['valuta']));
	$beskrivelse=addslashes(if_isset($_POST['beskrivelse']));
	$difkto=if_isset($_POST['difkto'])*1;
	$ny_valdate=usdate($dato);
	$ny_kurs=usdecimal($kurs);

	$r=db_fetch_array(db_select("select max(transdate) as transdate from transaktioner where valuta = '$kodenr'",__FILE__ . " linje " . __LINE__));
	$transdate=$r['transdate'];
	if ($ny_valdate <= $transdate) {
		print "<BODY onLoad=\"javascript:alert('Det er foretaget posteringer i $vauta efter $dato! Kursændring afbrudt')\">";
		$dato=NULL;
	}
	
	if (!$r = db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and kontotype = 'D' and regnskabsaar= '$regnaar'"))){
		print "<BODY onLoad=\"javascript:alert('Driftkonto $difkto eksisterer ikke')\">";
		$difkto='';$kodenr=-1;
	}	
#cho "$difkto && is_numeric($kodenr) && $dato && $kurs && $dato!=\"-\" && $kurs!=\"-\"<br>";
	if ($difkto && is_numeric($kodenr) && $dato && $kurs && $dato!="-" && $kurs!="-") {
		if ($id) {
			$r = db_fetch_array(db_select("select kurs from valuta where id = '$id'",__FILE__ . " linje " . __LINE__));
			$gl_kurs=$r['kurs'];
		}	elseif ($r = db_fetch_array(db_select("select id,kurs from valuta where gruppe = '$kodenr' and valdate = '$ny_valdate'",__FILE__ . " linje " . __LINE__))) {
			$id=$r['id'];
			$gl_kurs=$r['kurs'];
		} else {
			$r = db_fetch_array(db_select("select kurs from valuta where gruppe = '$kodenr' order by valdate desc limit 1",__FILE__ . " linje " . __LINE__));
			$gl_kurs=$r['kurs'];
		}
#cho  "$ny_kurs && $gl_kurs<br>";	
#cho "RA $regnaar<br>";
		$x=0;
		$qtxt="select id,kontonr,saldo from kontoplan where valuta = '$kodenr' and regnskabsaar='$regnaar'";
#cho "$qtxt<br>";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$konto_id[$x]=$r['id'];
			$kontonr[$x]=$r['kontonr'];
			$saldo[$x]=$r['saldo'];
			$x++;
		}
		for ($x=0;$x<count($konto_id);$x++){
			$r=db_fetch_array(db_select("select max(transdate) as transdate from transaktioner where kontonr = '$kontonr[$x]'",__FILE__ . " linje " . __LINE__));
			$transdate=$r['transdate'];
			if ($ny_valdate <= $transdate) {
				print "<BODY onLoad=\"javascript:alert('Det er foretaget posteringer på ".$valuta."konto $kontonr[$x] efter $dato! Kursændring afbrudt')\">";
				$dato=NULL;
			}
		}
		if ($dato && $ny_kurs && $gl_kurs){ #20160116
			transaktion('begin');
			for ($x=0;$x<count($konto_id);$x++){
				$posttekst="Kursændring $valuta fra ".dkdecimal($gl_kurs)." til ".dkdecimal($ny_kurs);
				$valutasaldo=$saldo[$x]*100/$gl_kurs;
				$ny_saldo=$valutasaldo*$ny_kurs/100;
#				$ny_value=$saldo[$x]*$kurs/100;
				$diff=afrund($ny_saldo-$saldo[$x],3);
#cho $saldo[$x]*$kurs/100."<br>";
#cho $saldo[$x]*$gl_kurs/100."<br>";
#cho "$diff=afrund($saldo[$x]*$kurs-$saldo[$x]*$gl_kurs,3)<br>";
#				$diff=afrund($saldo[$x]*$kurs/100-$saldo[$x]*$gl_kurs/100,3);
				if ($diff>0) $debkred='kredit';
				elseif($diff<0)  $debkred='debet';
				$diff=abs($diff);
				$qtxt="insert into transaktioner (kontonr,bilag,transdate,logdate,logtime,beskrivelse,$debkred,faktura,kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms)values('$difkto','0','$ny_valdate','".date("Y-m-d")."','".date("H:i")."','$posttekst','$diff','0','0','0','0','','-1','100','0','0')";
#cho "$qtxt<br>";
				if ($diff) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				($debkred=='debet')?$debkred='kredit':$debkred='debet';
				$qtxt="insert into transaktioner (kontonr,bilag,transdate,logdate,logtime,beskrivelse,$debkred,faktura,kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms)
				values
				('$kontonr[$x]','0','$ny_valdate','".date("Y-m-d")."','".date("H:i")."','$posttekst','$diff','0','0','0','0','','-1','100','0','0')";
#cho "$qtxt<br>";
				if ($diff) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#cho "update kontoplan set valutakurs='$ny_kurs' where kontonr='$kontonr[$x]' and regnskabsaar='$regnaar'<br>";
				db_modify("update kontoplan set valutakurs='$ny_kurs' where kontonr='$kontonr[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
				$y=0;	
				$q=db_select("select kodenr from grupper where (art = 'DG' or art = 'KG') and box2='$kontonr[$x]'",__FILE__ . " linje " . __LINE__);
				while($r=db_fetch_array($q)){
					$debkredgrp[$y]=$r['kodenr'];
					$y++;
				}
				for ($y=0;$y<count($debkredgrp);$y++){
					$z=0;
					$q=db_select("select id,kontonr from adresser where art = 'K' and gruppe='$debkredgrp[$y]'",__FILE__ . " linje " . __LINE__);
					while($r=db_fetch_array($q)){
						$adr_konto_id[$z]=$r['id'];
						$adr_kontonr[$z]=$r['kontonr'];
						$z++;
					}
					for ($z=0;$z<count($adr_konto_id);$z++){
						$dkksum[$z]=0;
#cho "select amount,valutakurs from openpost where udlignet='0' and konto_id='$adr_konto_id[$z]'<br>";
						$q=db_select("select amount,valutakurs from openpost where udlignet='0' and konto_id='$adr_konto_id[$z]'",__FILE__ . " linje " . __LINE__);
						while($r=db_fetch_array($q)){
							$dkksum[$z]+=$r['amount']*100/$r['valutakurs'];		
						}
						$valutasaldo=$dkksum[$z]*$gl_kurs/100;
						$ny_saldo=$valutasaldo*$ny_kurs/100;
						$diff=afrund($ny_saldo-$dkksum[$z],3);
						if ($diff) {
							$qtxt="insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values ('$adr_konto_id[$z]', '$adr_kontonr[$z]', '$diff', '$posttekst', '1', '".date("Y-m-d")."', '0', '0','-','0','0','".date("Y-m-d")."')";
#cho "$qtxt<br>";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}
					}
				}
			}
			if ($id) db_modify("update valuta set kurs='$ny_kurs', valdate='$ny_valdate' where id = '$id'");
			else db_modify("insert into valuta(kurs, valdate, gruppe) values('$ny_kurs', '$ny_valdate', '$kodenr')",__FILE__ . " linje " . __LINE__); 
#exit;
			transaktion('commit');
		} elseif ($dato && $ny_kurs){ #20160119
			db_modify("insert into valuta(kurs, valdate, gruppe) values('$ny_kurs', '$ny_valdate', '$kodenr')",__FILE__ . " linje " . __LINE__);
		}

	} elseif (($dato=="-" || $kurs=="-") && ($id)) {
		db_modify("delete from valuta where id = '$id'");
		$id=0;
	} elseif ($difkto && $kodenr == 'ny') {
		if ($r = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art = 'VK'",__FILE__ . " linje " . __LINE__))) {
			$kodenr=$r['kodenr'];
			print "<BODY onLoad=\"javascript:alert('$valuta eksisterer allerede.')\">";
		}	elseif ($valuta=='DKK') { # 20150327d
			$kodenr="-1";
			print "<BODY onLoad=\"javascript:alert('$valuta er reserveret og kan ikke anvendes som valutakode.')\">";
		}	else {	
			$r = db_fetch_array(db_select("select kodenr from grupper where art = 'VK' order by kodenr desc",__FILE__ . " linje " . __LINE__));
			$kodenr=$r['kodenr']+1;
			db_modify("insert into grupper(art, kodenr, beskrivelse, box1, box3) values('VK', '$kodenr', '$beskrivelse', '$valuta', '$difkto')",__FILE__ . " linje " . __LINE__);
		}
	}
	if ($difkto && $r = db_fetch_array(db_select("select kurs from valuta where gruppe = '$kodenr' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
		$kurs=dkdecimal($r['kurs']);	
		db_modify("update grupper set box2 = '$kurs', box3 = '$difkto' where art = 'VK' and kodenr = '$kodenr'",__FILE__ . " linje " . __LINE__);
	}
	$dato="";
	$kurs="";
	$id=0;
	genberegn($regnaar) ;
}

if ($kodenr < 0) $bredde = "width=\"500px\"";
else $bredde = "width=\"300px\"";

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" $bredde><tbody>";
print "<tbody>";
if ($kodenr < 0) ny_valuta(); 
if ($kodenr) {
	$r = db_fetch_array(db_select("select * from grupper where art = 'VK' and kodenr = '$kodenr'",__FILE__ . " linje " . __LINE__));
	$valuta=$r['box1'];
	$difkto=$r['box3'];

	print "<tr><td colspan=3 align=center><b> $r[box1] - $r[beskrivelse]</b></td></tr>\n";
	print "<tr><td title='Den dato kursen er g&aelig;ldende fra'> Dato</td>\n";
	print "	<td align=center title='V&aelig;rdien i DKK af 100 $valuta'> Kurs</td>\n"; # 20150327d 
	print "	<td align=center title='Kontonummer fra kontoplanen som skal bruges til valutakursdifferencer og &oslash;reafrunding'> Diff. konto</td>\n";
	print "</tr>\n";
	print "<form name=valutakort action=valutakort.php?kodenr=$kodenr&id=$id method=post>\n";
	if ($id) {
		$r = db_fetch_array(db_select("select * from valuta where id = '$id'"));
		$dato=dkdato($r['valdate']);
		$kurs=dkdecimal($r['kurs']);
		$knaptext="Opdater";
	} else {
		$dato=date("d-m-Y");
		$kurs="";
		$knaptext="Tilf&oslash;j";
	}
	print "<tr><td title='Den dato kursen er g&aelig;ldende fra'><input type=text name=dato size=16 value=$dato></td>\n";
	print "<td align=right title='V&aelig;rdien i DKK af 100 $valuta'><input type=text name=kurs size=8 value=$kurs></td>\n"; # 20150327d
	print "	<td align=right title='Kontonummer fra kontoplanen som skal bruges til valutakursdifferencer og &oslash;reafrunding'><input type=text name=difkto size=8 value=$difkto></td>\n";
	print "	<td align=center><input type=submit name=submit value=$knaptext></td>\n";
	print "</tr>\n";
	print "</form>\n";	
	$x=0;
	$kodenr=$kodenr*1;
	$r=db_fetch_array(db_select("select max(transdate) as transdate from transaktioner where valuta = '$kodenr'",__FILE__ . " linje " . __LINE__));
	$transdate=$r['transdate'];
	$q=db_select("select * from valuta where gruppe = '$kodenr' order by valdate desc",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		if ($bgcolor1!=$bgcolor){$bgcolor1=$bgcolor; $color='#000000';}
		elseif ($bgcolor1!=$bgcolor5){$bgcolor1=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$bgcolor1\">";
		$kurs=dkdecimal($r['kurs']);
		$dato=dkdato($r['valdate']);
		print "<td> $dato</td>";
		print "<td align=\"right\"> $kurs &nbsp;</td>";
		if ($r['valdate']>=$transdate) print "<td align=\"center\"><a $disabled href=\"valutakort.php?id=$r[id]&kodenr=$kodenr&ret=1\"> Ret</a><br></td>";
		print "</tr>";
	}
}

function ny_valuta() {
	$isovaluta = array("AED","AFN","ALL","AMD","ANG","AOA","ARS","AUD","AWG","AZN","BAM","BBD","BDT","BGN","BHD","BIF","BMD","BND","BOB","BOV","BRL","BSD","BTN","BWP","BYR","BZD","CAD","CDF","CHE","CHF","CHW","CLF","CLP","CNY","COP","COU","CRC","CUC","CUP","CVE","CZK","DJF","DKK","DOP","DZD","EGP","ERN","ETB","EUR","FJD","FKP","GBP","GEL","GHS","GIP","GMD","GNF","GTQ","GYD","HKD","HNL","HRK","HTG","HUF","IDR","ILS","INR","IQD","IRR","ISK","JMD","JOD","JPY","KES","KGS","KHR","KMF","KPW","KRW","KWD","KYD","KZT","LAK","LBP","LKR","LRD","LSL","LYD","MAD","MDL","MGA","MKD","MMK","MNT","MOP","MRO","MUR","MVR","MWK","MXN","MXV","MYR","MZN","NAD","NGN","NIO","NOK","NPR","NZD","OMR","PAB","PEN","PGK","PHP","PKR","PLN","PYG","QAR","RON","RSD","RUB","RWF","SAR","SBD","SDG","SEK","SGD","SHP","SLL","SOS","SRD","SSP","STD","SYP","SZL","THB","TJS","TMT","TND","TOP","TRY","TTD","TWD","TZS","UAH","UGX","USD","UYU","UZS","VEF","VND","VUV","XAF","XBT","XCD","XOF","XPF","XUA","YER","ZAR","ZMW","ZWL"); # 20150327v
	
	$r = db_fetch_array(db_select("select * from grupper where art = 'VK'",__FILE__ . " linje " . __LINE__));
	$difkto=$r['box3'];
	print "<form name=valutakort action=valutakort.php?kodenr=ny method=post>";
	print "<tr><td>Valutabetegnelse&nbsp;-&nbsp;f.eks.&nbsp;USD</td>";
	print "<td><select name=valuta>";
	$x=0;
	while ($isovaluta[$x]) {
		print "<option value='$isovaluta[$x]'>$isovaluta[$x]</option>";
		$x++;
	}
	print "</td></tr>";
	print "<tr><td>Valutabeskrivelse&nbsp;-&nbsp;f.eks.&nbsp;Amerikanske&nbsp;dollar</td><td><input type=text name=beskrivelse size=30></td></tr>";
	print "<tr><td>Kontonummer&nbsp;til&nbsp;valutakursdifferencer&nbsp;og&nbsp;&oslash;reafrunding</td><td title='Kontonummer fra kontoplanen som skal bruges til valutakursdifferencer og &oslash;reafrunding'><input type=text name=difkto size=8 value=$difkto></td>";
	print "<tr><td colspan=2 align=center><input type=submit name=submit value=Tilf&oslash;j></td></tr>";
	print "</form>";	
	exit;
}
?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
