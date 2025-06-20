<?php
// -------------systemdata/valutakort.php----lap 2.3.8-------23.05.2013-----------
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------
//
// 2013.05.13 - Opdateret liste over valutakoder

@session_start();
$s_id=session_id();

$modulnr=2;
$title="valutakort";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("top.php");

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
	
	if (!$r = db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and kontotype = 'D' and regnskabsaar= '$regnaar'"))){
		print "<BODY onLoad=\"javascript:alert('Driftkonto $difkto eksisterer ikke')\">";
		$difkto='';$kodenr=-1;
	}	
	if ($difkto && is_numeric($kodenr) && $dato && $kurs && $dato!="-" && $kurs!="-") {
		$usdate=usdate($dato);
		$uskurs=usdecimal($kurs);
		if ($id) db_modify("update valuta set kurs='$uskurs', valdate='$usdate' where id = '$id'");
			elseif ($r = db_fetch_array(db_select("select id from valuta where gruppe = '$kodenr' and valdate = '$usdate'"))) {
			db_modify("update valuta set kurs='$uskurs', valdate='$usdate' where id = $r[id]");
		} else db_modify("insert into valuta(kurs, valdate, gruppe) values('$uskurs', '$usdate', '$kodenr')"); 
	} elseif (($dato=="-" || $kurs=="-") && ($id)) {
		db_modify("delete from valuta where id = '$id'");
		$id=0;
	} elseif ($difkto && $kodenr == 'ny') {
		if ($r = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art = 'VK'"))) {
			$kodenr=$r['kodenr'];
			print "<BODY onLoad=\"javascript:alert('$valuta eksisterer allerede')\">";
		}	elseif ($valuta=='DKR') {
			$kodenr="-1";
			print "<BODY onLoad=\"javascript:alert('$valuta er reserveret og kan ikke anvendes som valutabetegnelse')\">";
		}	else {	
			$r = db_fetch_array(db_select("select kodenr from grupper where art = 'VK' order by kodenr desc"));
			$kodenr=$r['kodenr']+1;
			db_modify("insert into grupper(art, kodenr, beskrivelse, box1, box3) values('VK', '$kodenr', '$beskrivelse', '$valuta', '$difkto')");
		}
	}
	if ($difkto && $r = db_fetch_array(db_select("select kurs from valuta where gruppe = '$kodenr' order by valdate desc"))) {
		$kurs=dkdecimal($r['kurs']);	
		db_modify("update grupper set box2 = '$kurs', box3 = '$difkto' where art = 'VK' and kodenr = '$kodenr'");
	}
	$dato="";
	$kurs="";
	$id=0;
}

if ($kodenr < 0) $bredde = "width=\"500px\"";
else $bredde = "width=\"300px\"";

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" $bredde><tbody>";

print "<tbody>";
if ($kodenr < 0) ny_valuta(); 
if ($kodenr) {
	$r = db_fetch_array(db_select("select * from grupper where art = 'VK' and kodenr = '$kodenr'"));
	$valuta=$r['box1'];
	$difkto=$r['box3'];
	print "<tr><td colspan=3 align=center><b> $r[box1] - $r[beskrivelse]</b></td></tr>";
	print "<tr><td title='Den dato hvorfra kursen er g&aelig;ldende'> Dato</td>
		<td align=center title='V&aelig;rdien i DKR af 100 $valuta'> Kurs</td>
		<td align=center title='Kontonummer fra kontoplanen som skal bruges til valutakursdifferencer og &oslash;reafrunding'> Diff. konto</td>
		</tr>";
	print "<form name=valutakort action=valutakort.php?kodenr=$kodenr&id=$id method=post>";
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
	print "<tr><td title='Den dato hvorfra kursen er g&aelig;ldende'><input type=text name=dato size=16 value=$dato></td>
			<td align=right title='V&aelig;rdien i DKR af 100 $valuta'><input type=text name=kurs size=8 value=$kurs></td>
			<td align=right title='Kontonummer fra kontoplanen som skal bruges til valutakursdifferencer og &oslash;reafrunding'><input type=text name=difkto size=8 value=$difkto></td>
			<td align=center><input type=submit name=submit value=$knaptext></td>
		</tr>";
	print "</form>";	
	$x=0;
	$kodenr=$kodenr*1;
	$query = db_select("select * from valuta where gruppe = '$kodenr' order by valdate desc");
	while ($row = db_fetch_array($query)) {
		$x++;
		if ($bgcolor1!=$bgcolor){$bgcolor1=$bgcolor; $color='#000000';}
		elseif ($bgcolor1!=$bgcolor5){$bgcolor1=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$bgcolor1\">";
		$kurs=dkdecimal($row['kurs']);
		$dato=dkdato($row['valdate']);
		print "<td> &nbsp; $dato</td>";
		print "<td align=right> $kurs &nbsp;</td>";
		print "<td align=center><a href=valutakort.php?id=$row[id]&kodenr=$kodenr&ret=1> Ret</a><br></td>";
		print "</tr>";
	}
}

function ny_valuta()
{
	$isovaluta = array("AED","AFA","ALL","AMD","ANG","AOA","ARS","AUD","AWG","AZM","BAM","BBD","BDT","BGL","BHD","BIF","BMD","BND","BOB","BRL","BSD","BTN","BWP","BYR","BZD","CAD","CDF","CHF","CLP","COP","CRC","CSD","CUP","CZK","DKK","DOP","DZD","EGP","ERN","ETB","EUR","FJD","FKP","GBP","GEL","GGP","GHC","GIP","GMD","GNF","GTQ","GYD","HKD","HNL","HRK","HTG","HUF","IDR","ILS","IMP","INR","IRR","ISK","JEP","JMD","JOD","JPY","KES","KHR","KMF","KPW","KRW","KWD","KYD","LAK","LBP","LKR","LRD","LSL","LTL","LVL","LYD","MAD","MDL","MGA","MKD","MMK","MNT","MRO","MUR","MVR","MWK","MXN","MYR","MZM","NAD","NGN","NID","NIO","NOK","NPR","NZD","OMR","PAB","PEN","PGK","PHP","PKR","PLN","PYG","QAR","RMB","ROL","RUB","RWF","SAR","SAT","SBD","SDD","SEK","SGD","SHP","SLL","SRD","STD","SYP","SZL","THB","TMM","TND","TOP","TRL","TTD","TWD","TZS","UAH","UGX","USD","UYU","UZS","VEB","VND","VUV","XAF","XCD","XOF","XPF","YER","ZAR","ZMK","ZWD");
	
	$r = db_fetch_array(db_select("select * from grupper where art = 'VK'"));
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
