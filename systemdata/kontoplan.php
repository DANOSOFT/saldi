<?php
// -----------systemdata/kontoplan.php-----patch 4.0.8 ----2023-07-22-------
//                           LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20160116     Tilføjet valuta  
// 20160129	    Valutakode og kurs blev ikke sat ved oprettelse af ny driftskonti.
// 20210707 LOE Translated these texts with findtekst function 
// 20220607 MSC Implementing new design

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
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
	print "<div id=\"leftmenuholder\">\n";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"rightContent\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\" style='width:100%'><tbody>\n"; # -> 1
} else {
	print "<div align=\"center\">";
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height=\"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund align=\"left\"><a href=$returside accesskey=L>".findtekst(30,$sprog_id)."</a></td>"; #20210707
	print "<td width=\"80%\" $top_bund align=\"center\">".findtekst(113,$sprog_id)."</td>";
	print "<td width=\"10%\" $top_bund align=\"right\"><a href=kontokort.php accesskey=N>".findtekst(39,$sprog_id)."</a></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<tr><td valign=\"top\">";
	print "<table cellpadding=\"0\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
	print "<tbody>";
}
print "<tr>\n";
print "<td><b> ".findtekst(43,$sprog_id)."</b></td>\n";
print "<td><b> ".findtekst(805,$sprog_id)."</b></td>\n";
print "<td><b> Type</b></td>\n";
print "<td align=\"center\"><b>".findtekst(770,$sprog_id)."</b></td>\n";
print "<td align=\"center\"><b>".findtekst(1073,$sprog_id)."</b></td>\n";
print "<td align=\"center\"><b>".findtekst(1069,$sprog_id)."</b></td>\n";
print "<td align=\"center\"><b>".findtekst(1191,$sprog_id)."</b></td>\n";
print "<td align=\"center\"><b>Map til</b></td>\n";
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
		($row['map_to'])?$mapTo=$row['map_to']:$mapTo=NULL;
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
		if ( $row['kontotype']!='H' && $row['kontotype']!='X' ) {
			print "<td align=\"right\" title=\"DKK ".dkdecimal($row['saldo']*1,2)."\"><span style=\"color:$color;\">";
		  if ($row['valutakurs']) print dkdecimal($row['saldo']*100/$row['valutakurs'],2);
			else print dkdecimal($row['saldo'],2);
			print "<br></span></td>\n";
		} else print "<td><br></td>\n";
		print "<td align=\"center\"><span style=\"color:$color;\">$valutanavn[$valuta]<br></span></td>\n";		
		print "<td align=\"center\"><span style=\"color:$color;\">$row[genvej]<br></span></td>\n";		
		print "<td align=\"center\"><span style=\"color:$color;\">$mapTo<br></span></td>\n";
		print "</tr>\n";
		if ($row['kontotype']=='H') {$linjebg=$bgcolor4; $color='#000000';}
	
}

if (!$menu =='T') {
print "</tbody>
</table>
	</td></tr>
</tbody></table>";
} else {
print "</tbody></table></div>";
}

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>
