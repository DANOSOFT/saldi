<?php
// -------------systemdata/valuta.php------------------- 3.5.5 -- 2015-03-13 --
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------------
// 20150313 CA  Topmenudesign tilføjet                             søg 20150313
// 2019.02.21 MSC - Rettet topmenu design
// 2019.02.25 MSC - Rettet topmenu design

@session_start();
$s_id=session_id();

$modulnr=2;
$title="valuta";
$bgcolor=NULL; $bgcolor1=NULL; $kurs=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/dkdecimal.php");

if ($menu=='T') {  # 20150313 start
        include_once '../includes/top_header.php';
        include_once '../includes/top_menu.php';
        print "<div id=\"header\">\n";
        print "<div class=\"headerbtnLft\"></div>\n";
        print "</div><!-- end of header -->";
        print "<div id=\"leftmenuholder\">";
        include_once 'left_menu.php';
        print "</div><!-- end of leftmenuholder -->\n";
		print "<div class=\"maincontentLargeHolder\">\n";;
        print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable2\"><tbody>";
} else {
        include("top.php");
        print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
}  # 20150313 stop


include("../includes/db_query.php");

#print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0px\" width=\"70%\"><tbody>";

?>
<tr><td valign="top"><center><table width='100%' border=0><tbody>
	<tr><td colspan="3" align="center"><b>Valutaer</b></td></tr>
	<tr>
<!--
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $bgcolor2 ?>">Valuta</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $bgcolor2 ?>">Beskrivelse</b></td>
		<td align="right"><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $bgcolor2 ?>">Kurs</a></b></td>
-->
		<td>Valuta</td>
		<td>Beskrivelse</td>
		<td>Kurs</td>
	</tr>
	<?php
$x=0;
$dd=date("Y-m-d");
$q=db_select("select * from grupper where art = 'VK' order by box1",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$x++;
	$qtxt="select kurs from valuta where gruppe='$r[kodenr]' and valdate <= '$dd' order by valdate desc";
	if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
		$kurs=dkdecimal($r2['kurs']);
	} else $kurs="-";
	if ($bgcolor1!=$bgcolor){$bgcolor1=$bgcolor; $color='#000000';}
	elseif ($bgcolor1!=$bgcolor5){$bgcolor1=$bgcolor5; $color='#000000';}
	print "<tr bgcolor=\"$bgcolor1\">";
	print "<td><a class='button gray medium' href=valutakort.php?kodenr=$r[kodenr]&valuta=$r[box1]>$font $r[box1]</a></td>";
	print "<td>$font $r[beskrivelse]</td>";
	print "<td align=right>$font $kurs</td>";
	print "</tr>";
}
?>
	</tbody>
	</table>
</td>
</tr>
<?php
print "<tr><td colspan=\"3\" align=\"center\"><a class='button green medium' href=valutakort.php?kodenr=-1>$font Tilf&oslash;j ny valuta</a><br></td></tr>"; 

#if ($x<1) {print "<meta http-equiv=refresh content=0;url=regnskabskort.php>";}


?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
