<?php
// ------------------systemdata/kontoplan.php-----lap 3.6.2-----2016-01-16----
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
// ----------------------------------------------------------------------
//
// 20160116 Tilføjet valuta  
// 20160129	Valutakode og kurs blev ikke sat ved oprettelse af ny driftskonti.

@session_start();
$s_id=session_id();
$title="Kontoplan";
$css="../css/standard.css";
$modulnr="0";
$linjebg='';
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

if ($menu=='T') {
#	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"></div>\n";
#	print "<span class=\"headerTxt\">Systemsetup</span>\n";     
#	print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=../debitor/debitorkort.php;title=debitor\" class=\"button green small right\">Ny debitor</a>--></div>";       
	print "</div><!-- end of header -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<div id=\"leftmenuholder\">\n";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"rightContent\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\"><tbody>\n"; # -> 1
} else {
	print "<div align=\"center\">";
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height=\"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund align=\"left\"><a href=$returside accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund align=\"center\">Kontoplan</td>";
	print "<td width=\"10%\" $top_bund align=\"right\"><a href=kontokort.php accesskey=N>Ny</a></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<tr><td valign=\"top\">";
	print "<table cellpadding=\"0\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
	print "<tbody>";
}
print "<tr>\n";
print "<td><b> Kontonr.</b></td>\n";
print "<td><b> Kontonavn</b></td>\n";
print "<td><b> Type</b></td>\n";
print "<td align=\"center\"><b>Moms</b></td>\n";
print "<td align=\"center\"><b>Saldo</b></td>\n";
print "<td align=\"center\"><b>Valuta</b></td>\n";
print "<td align=\"center\"><b>Genvej</b></td>\n";
print "</tr>\n";

	$valutakode[0]=0;
	$valutanavn[0]='DKK';
	$x=1;	
	$q=db_select("select kodenr,box1 from grupper where art='VK' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$valutakode[$x]=$r['kodenr'];
		$valutanavn[$x]=$r['box1'];
		$x++;
	}
	if (!$regnaar) {$regnaar=1;} 
	$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		$valuta=$row['valuta'];
		if ($valuta == '') { # 20160129
			$valuta=0;
			$valutakurs=100;
			db_modify("update kontoplan set valuta='$valuta',valutakurs='$valutakurs' where id='$row[id]'",__FILE__ . " linje " . __LINE__);
		}
		if ($row['lukket']=='on') $beskrivelse="Lukket ! - ".stripslashes($row['beskrivelse']);
		else $beskrivelse=stripslashes($row['beskrivelse']);
		if ($linjebg!=$bgcolor) {$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5) {$linjebg=$bgcolor5; $color='#000000';}
		if ($row['kontotype']=='H') {$linjebg=$bgcolor4; $color='$000000';}
		print "<tr bgcolor=\"$linjebg\">\n";
		print "<td><a href=\"kontokort.php?id=$row[id]\"><span style=\"color:$color;\">$row[kontonr]</span></a><br></td>\n";
		print "<td><span style=\"color:$color;\">$beskrivelse<br></span></td>\n";
		if ($row['kontotype']=='H') print "<td><span style=\"color:$color;\"><br></span></td>\n";
		elseif ($row['kontotype']=='D') print "<td><span style=\"color:$color;\">Drift<br></span></td>\n";
		elseif ($row['kontotype']=='S') print "<td><span style=\"color:$color;\">Status<br></span></td>\n";
		elseif ($row['kontotype']=='Z') print "<td><span style=\"color:$color;\">Sum $row[fra_kto] - $row[til_kto]<br></span></td>\n";
		elseif ($row['kontotype']=='R') print "<td><span style=\"color:$color;\">Resultat = $row[fra_kto]<br></span></td>\n";
		else print "<td><span style=\"color:$color;\">Sideskift<br></span></td>\n";
		print "<td align=\"center\"><span style=\"color:$color;\">$row[moms]<br></span></td>\n";
		if (($row['kontotype']!='H')&&($row['kontotype']!='X'))print "<td align=\"right\" title=\"DKK ".dkdecimal($row['saldo']*1,2)."\"><span style=\"color:$color;\">".dkdecimal($row['saldo']*100/$row['valutakurs'],2)."<br></span></td>\n";
		else print "<td><br></td>\n";
		print "<td align=\"center\"><span style=\"color:$color;\">$valutanavn[$valuta]<br></span></td>\n";		
		print "<td align=\"center\"><span style=\"color:$color;\">$row[genvej]<br></span></td>\n";		
		print "</tr>\n";
		if ($row['kontotype']=='H') {$linjebg=$bgcolor4; $color='#000000';}
	
}

if (!$menu =='T') {
print "</tbody>
</table>
	</td></tr>
</tbody></table>";
} else {
print "</tbody></table></div><!-- end of rightContent --></div><!-- end of maincontentLargeHolder -->";
}
?>
</div><!-- end of wrapper --></body></html>
