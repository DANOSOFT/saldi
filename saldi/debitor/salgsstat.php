<?php
// ----------includes/salgsstat.php-----------------------lap 3.6.0---2015-11-29--
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
// Copyright (c) 2003-2015 DANOSOFT ApS
// ----------------------------------------------------------------------------
// 
// 20140704 Oprydning og sproglig forbedring                                 ca

@session_start();
$s_id=session_id();
$modulnr=12;
$title="Salgsstatistik";

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("../includes/db_query.php");


$dato_fra=if_isset($_GET['dato_fra']);
$dato_til=if_isset($_GET['dato_til']);
$konto_fra=if_isset($_GET['konto_fra']);
$konto_til=if_isset($_GET['konto_til']);
$kontonr=if_isset($_GET['kontonr']);
$firmanavn=if_isset($_GET['firmanavn']);
$adresse=if_isset($_GET['adresse']);
$postnr=if_isset($_GET['postnr']);
$bynavn=if_isset($_GET['bynavn']);
$varenr=if_isset($_GET['varenr']);
$varetekst=if_isset($_GET['varetekst']);
$detaljer=if_isset($_GET['detaljer']);
$ret=if_isset($_GET['ret']);
$art=if_isset($_GET['art']);
if ($ret) {
	begraens($dato_fra,$dato_til,$konto_fra,$konto_til,$kontonr,$firmanavn,$adresse,$postnr,$bynavn,$varenr,$varetekst,$detaljer,$art);
	exit;
}
if (isset($_POST['find']) && $_POST['find']) {
	$kontonr=if_isset($_POST['kontonr']);
	$firmanavn=if_isset($_POST['firmanavn']);
	$adresse=if_isset($_POST['adresse']);
	$postnr=if_isset($_POST['postnr']);
	$bynavn=if_isset($_POST['bynavn']);
	$varenr=if_isset($_POST['varenr']);
	$varetekst=if_isset($_POST['varetekst']);
	$detaljer=if_isset($_POST['detaljer']);
}
($detaljer)?$summeret=NULL:$summeret='on';

$day=date("d");
$month=date("m");
$year=date("y");

$tmp=$year-1;
if ($tmp<10) $tmp="0".$tmp;
list($fra,$til)=explode(":",$periode);
if (!$til) $til=date("dmY");
$rtekst="Klik her for at v&aelig;lge en anden periode";

if ($popup) $luk="../includes/luk.php";
else $luk="rapport.php";

print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
if ($menu=='T') {
	$leftbutton="<a title=\"Klik her for at lukke\" href=\"../includes/luk.php\" accesskey=\"L\" accesskey=\"L\">Luk</a>";
	$rightbutton="<a  title='$rtekst' href=\"salgsstat.php?begraens=$begraens&ret=on\" accesskey=\"B\">Søgning</a>";
	$vejledning=NULL;
	include("../includes/topmenu.php");
	print "<div id=\"topmenu\" style=\"position:absolute;top:6px;right:0px\">";
} elseif ($menu=='S') {
	include("../includes/sidemenu.php");
} else {
	print "<tr><td colspan=\"4\" height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	$tekst="Klik her for at lukke \"Top100\"";
	print "<td width=\"10%\" $top_bund title='$tekst'><a href=\"$luk\" accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Salgstatsstik</td>";
	print "<td width=\"10%\" $top_bund title='$rtekst'><a href=salgsstat.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontonr=$kontonr&firmanavn=$firmanavn&adresse=$adresse&postnr=$postnr&bynavn=$bynavn&varenr=$varenr&varetekst=$varetekst&detaljer=$detaljer&ret=$art&ret=on accesskey=B>Søgning<br></a></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
}
#$art='D';
/*
$qtxt="select * from adresser where art = $art order by firmanavn";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)){
	$konto_id[$x]=$r['konto_id'];
	$kontonr[$x]=$r['kontonr'];
	$firmanavn[$x]=$r['firmanavn'];
	$x;
}
*/
$x=0;
$y=0;

$qtxt="select ordrelinjer.vare_id,ordrelinjer.varenr,ordrelinjer.beskrivelse,ordrelinjer.antal,ordrelinjer.pris,ordrelinjer.rabat,";
$qtxt.="ordrer.konto_id,ordrer.kontonr,ordrer.firmanavn,ordrer.id,ordrer.fakturadate from ordrer,ordrelinjer,adresser ";
$qtxt.="where ordrelinjer.ordre_id=ordrer.id and adresser.id=ordrer.konto_id and adresser.art='$art' ";
if ($dato_fra && $dato_til) $qtxt.="and ordrer.fakturadate>='".usdate($dato_fra)."' and ordrer.fakturadate<='".usdate($dato_til)."' ";
if ($konto_fra && $konto_til) $qtxt.="and ordrer.kontonr>='$konto_fra' and ordrer.kontonr<='$konto_til' ";
elseif ($kontonr) $qtxt.="and ordrer.kontonr like '".str_replace('*','%',$kontonr)."' ";
if ($firmanavn) $qtxt.="and lower(ordrer.firmanavn) like '".str_replace('*','%',strtolower($firmanavn))."' ";
if ($adresse) $qtxt.="and ordrer.adresse like '".str_replace('*','%',strtolower($adresse))."' ";
if ($postnr) $qtxt.="and ordrer.postnr like '".str_replace('*','%',strtolower($postnr))."' ";
if ($bynavn) $qtxt.="and ordrer.bynavn like '".str_replace('*','%',strtolower($bynavn))."' ";
if ($varenr) $qtxt.="and ordrelinjer.varenr like '".str_replace('*','%',strtolower($varenr))."' ";
if ($varetekst) $qtxt.="and ordrelinjer.beskrivelse like '".str_replace('*','%',strtolower($varetekst))."' ";
$qtxt.="order by ordrer.kontonr,ordrelinjer.varenr";
#cho $qtxt."<br>";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)){
	if ($q_konto_id[$x] && $q_konto_id[$x]!=$r['konto_id']) {
		$x++;
		$y=0;
		$q_vare_id[$x]=array();
	}
	$q_konto_id[$x]=$r['konto_id'];
	$q_kontonr[$x]=$r['kontonr'];
	$q_firmanavn[$x]=$r['firmanavn'];
	if ($summeret) {
		if ($q_vare_id[$x][$y] && $q_vare_id[$x][$y]!=$r['vare_id']) {
			$q_pris[$x][$y]=$q_sum[$x][$y]/$q_antal[$x][$y];
			$y++;
		}
		$q_faktdato[$x][$y]=dkdecimal($r['fakturadate']);
		$q_vare_id[$x][$y]=$r['vare_id'];
		$q_varenr[$x][$y]=$r['varenr'];
		$q_beskrivelse[$x][$y]=$r['beskrivelse'];
#		$antal[$x][$y]=$r['antal'];
		$q_pris[$x][$y]=$r['pris'];
		$q_rabat[$x][$y]=$r['rabat'];
		$q_antal[$x][$y]+=$r['antal'];
		$q_sum[$x][$y]+=$q_antal[$x][$y]*($q_pris[$x][$y]-($q_pris[$x][$y]/100*$q_rabat[$x][$y]));
	} else {
		$q_vare_id[$x][$y]=$r['vare_id'];
		$q_varenr[$x][$y]=$r['varenr'];
		$q_beskrivelse[$x][$y]=$r['beskrivelse'];
#		$antal[$x][$y]=$r['antal'];
		$q_pris[$x][$y]=$r['pris'];
		$q_rabat[$x][$y]=$r['rabat'];
		$q_antal[$x][$y]=$r['antal'];
		$q_sum[$x][$y]=$q_antal[$x][$y]*($q_pris[$x][$y]-($q_pris[$x][$y]/100*$q_rabat[$x][$y]));
		$y++;
	}
} 
print "<tr><td width=\"100%\"><table width=\"100%\"><tbody>";
for ($x=0;$x<count($q_konto_id);$x++) {
#	print "<tr><td>$konto_id[$x]</td></tr>";
	if ($x) print "<tr><td colspan=\"5\"><hr></td></tr>";
	print "<tr><td><b>Kontonr</b></td><td>$q_kontonr[$x]</td></tr>";
	print "<tr><td><b>Firmanavn</b></td><td>$q_firmanavn[$x]</td></tr>";
	if ($periode) print "<tr><td><b>Periode</b></td><td>$periode</td></tr>";
	print "<tr><td colspan=\"5\"><hr></td></tr>";
	print "<tr><td><b>Varenr</b></td><td><b>Beskrivelse</b></td><td align=\"right\"><b>Antal</b>";
	if (!$summeret) print "</td><td align=\"right\"><b>Rabat</b></td>";
	print "<td align=\"right\"><b>Pris</b></td><td align=\"right\"><b>Sum</b></td></tr>";
	for ($y=0;$y<count($q_vare_id[$x]);$y++) {
		print "<tr>";
		print "<td>".$q_varenr[$x][$y]."</td>";
		print "<td>".$q_beskrivelse[$x][$y]."</td>";
		print "<td align=\"right\">".dkdecimal($q_antal[$x][$y])."</td>";
		if (!$summeret) {
			print "<td align=\"right\">".dkdecimal($q_rabat[$x][$y])."</td>";
		}
		print "<td align=\"right\">".dkdecimal($q_pris[$x][$y])."</td>";
		print "<td align=\"right\">".dkdecimal($q_sum[$x][$y])."</td>";
		print "</tr>";
	}
}
function begraens($dato_fra,$dato_til,$konto_fra,$konto_til,$kontonr,$firmanavn,$adresse,$postnr,$bynavn,$varenr,$varetekst,$detaljer,$art) {
	global $db;
	($detaljer)?$detaljer='checked':$detaljer=NULL;
	print "<center>";
	print "<form name=\"salgsstat\" action=\"salgsstat.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontonr=$kontonr&firmanavn=$firmanavn&adresse=$adresse&postnr=$postnr&bynavn=$bynavn&varenr=$varenr&varetekst=$varetekst&detaljer=$detaljer&art=$art\" method=\"post\">";
	print "<table><tbody>";
	print "<tr><td>Kontonr</td><td><input type=\"text\" name=\"kontonr\" value=\"$kontonr\"></td></tr>";
	print "<tr><td>Firmanavn</td><td><input type=\"text\" name=\"firmanavn\" value=\"$firmanavn\"></td></tr>";
	print "<tr><td>Adresse</td><td><input type=\"text\" name=\"adresse\" value=\"$adresse\"></td></tr>";
	print "<tr><td>Postnr</td><td><input type=\"text\" name=\"postnr\" value=\"$postnr\"></td></tr>";
	print "<tr><td>Bynavn</td><td><input type=\"text\" name=\"bynavn\" value=\"$bynavn\"></td></tr>";
	print "<tr><td>Varenr</td><td><input type=\"text\" name=\"varenr\" value=\"$varenr\"></td></tr>";
	print "<tr><td>Varetekst</td><td><input type=\"text\" name=\"varetekst\" value=\"$varetekst\"></td></tr>";
	print "<tr><td>Vis detaljer</td><td align=\"right\"><input type=\"checkbox\" name=\"detaljer\" $detaljer></td></tr>";
	print "<tr><td colspan=\"2\" align=\"center\">";
	print "<input style=\"width:80px\" type=\"submit\" name=\"find\" value=\"Søg\">";
	print "&nbsp;";
	print "<input style=\"width:80px\" type=\"submit\" name=\"fortryd\" value=\"Fortyd\">";
	print "</td></tr>";

	print "</tbody></table>";
}
?>
