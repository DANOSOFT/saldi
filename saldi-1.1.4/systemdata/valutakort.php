<?php
// -------------systemdata/valutakort.php----lap 1.1.4-------18.12.07-----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$modulnr=2;
$title="valutakort";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("top.php");

$bgcolor=NULL; $bgcolor1=NULL; $dato=date("d-m-Y"); $kurs=NULL; $valuta=NULL; $beskrivelse=NULL;
$kodenr=if_isset($_GET['kodenr']);
$id=if_isset($_GET['id']);

if (isset($_POST['submit'])) {
	$dato=if_isset($_POST['dato']);
	$kurs=if_isset($_POST['kurs']);
	$valuta=if_isset($_POST['valuta']);
	$beskrivelse=if_isset($_POST['beskrivelse']);
	
	if ($dato && $kurs && $dato!="-" && $kurs!="-") {
		$usdate=usdate($dato);
		$uskurs=usdecimal($kurs);
		if ($id) {
			db_modify("update valuta set kurs='$uskurs', valdate='$usdate' where id = '$id'");
		}
			elseif ($r = db_fetch_array(db_select("select id from valuta where gruppe = '$kodenr' and valdate = '$usdate'"))) {
			db_modify("update valuta set kurs='$uskurs', valdate='$usdate' where id = $r[id]");
		} else db_modify("insert into valuta(kurs, valdate, gruppe) values('$uskurs', '$usdate', '$kodenr')"); 
	} elseif (($dato=="-" || $kurs=="-") && ($id)){
		db_modify("delete from valuta where id = '$id'");
		$id=0;
	} elseif ($kodenr == 'ny') {
		if ($r = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art = 'VK'"))) {
			$kodenr=$r['kodenr'];
			print "<BODY onLoad=\"javascript:alert('$valuta eksisterer allerede')\">";
		}	elseif ($valuta=='DKR') {
			$kodenr="-1";
			print "<BODY onLoad=\"javascript:alert('$valuta er reserveret og kan ikke anvendes som valutabetegnelse')\">";
		}	else {	
			$r = db_fetch_array(db_select("select kodenr from grupper where art = 'VK' order by kodenr desc"));
			$kodenr=$r['kodenr']+1;
			db_modify("insert into grupper(art, kodenr, beskrivelse, box1) values('VK', '$kodenr', '$beskrivelse', '$valuta')");
		}
	}
	if ($r = db_fetch_array(db_select("select kurs from valuta where gruppe = '$kodenr' order by valdate desc"))) {
		$kurs=dkdecimal($r['kurs']);	
		db_modify("update grupper set box2 = '$kurs' where art = 'VK' and kodenr = '$kodenr'");
	}
}

if ($kodenr < 0) $bredde = "width=\"500px\"";
else $bredde = "width=\"300px\"";

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" $bredde><tbody>";

print "<tbody>";
if ($kodenr < 0) ny_valuta(); 
if ($kodenr) {
	$r = db_fetch_array(db_select("select * from grupper where art = 'VK' and kodenr = '$kodenr'"));
	$valuta=$r['box1'];
	print "<tr><td colspan=3 align=center><b>$font $r[box1] - $r[beskrivelse]</b></td></tr>";
	print "<tr><td title='Den dato hvorfra kursen er g&aelig;ldende'>$font Dato</td><td align=right title='V&aelig;rdien i DKR af 100 $valuta'>$font Kurs</td></tr>";
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
		<td align=center><input type=submit name=submit value=$knaptext></td>
		</tr>";
	print "</form>";	
	$x=0;
	$query = db_select("select * from valuta where gruppe = '$kodenr' order by valdate desc");
	while ($row = db_fetch_array($query)) {
		$x++;
		if ($bgcolor1!=$bgcolor){$bgcolor1=$bgcolor; $color='#000000';}
		elseif ($bgcolor1!=$bgcolor5){$bgcolor1=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$bgcolor1\">";
		$kurs=dkdecimal($row['kurs']);
		$dato=dkdato($row['valdate']);
		print "<td><small>$font &nbsp; $dato</small></td>";
		print "<td align=right><small>$font $kurs &nbsp;</small></td>";
		print "<td align=center><small><a href=valutakort.php?id=$row[id]&kodenr=$kodenr>$font Ret</a><br></small></td>";
		print "</tr>";
	}
}

function ny_valuta()
{
	print "<form name=valutakort action=valutakort.php?kodenr=ny method=post>";
	print "<tr><td>Valutabetegnelse - f.eks. USD</td><td><input type=text name=valuta size=6></td></tr>";
	print "<tr><td>Valutabeskrivelse - f.eks. Amerikanske dollar</td><td><input type=text name=beskrivelse size=30></td></tr>";
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
