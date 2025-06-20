<?php
// -------------systemdata/valuta.php----lap 1.9.2b-------08.04.08-----------
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
$title="valuta";
$bgcolor=NULL; $bgcolor1=NULL; $kurs=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/dkdecimal.php");

include("top.php");
include("../includes/db_query.php");

print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=\"70%\"><tbody>";

?>
<tbody>
	<tr>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $bgcolor2 ?>">Valuta</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $bgcolor2 ?>">Beskrivelse</a></b></td>
		<td align=right><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $bgcolor2 ?>">Kurs</a></b></td>
	</tr>
	<?php
$x=0;
$dd=date("Y-m-d");
$q=db_select("select * from grupper where art = 'VK' order by box1");
while ($r = db_fetch_array($q)) {
	$x++;
	if ($r2=db_fetch_array(db_select("select kurs from valuta where gruppe='$r[kodenr]' and valdate <= '$dd' order by valdate desc"))){
		$kurs=dkdecimal($r2['kurs']);
	} else $kurs="-";
	if ($bgcolor1!=$bgcolor){$bgcolor1=$bgcolor; $color='#000000';}
	elseif ($bgcolor1!=$bgcolor5){$bgcolor1=$bgcolor5; $color='#000000';}
	print "<tr bgcolor=\"$bgcolor1\">";
	print "<td><a href=valutakort.php?kodenr=$r[kodenr]&valuta=$r[box1]>$font $r[box1]</a><br></small></td>";
	print "<td><small>$font $r[beskrivelse]<br></small></td>";
	print "<td align=right><small>$font $kurs<br></small></td>";
	print "</tr>";
}
print "<tr><td colspan=3><small><a href=valutakort.php?kodenr=-1>$font Tilf&oslash;j ny valuta</a><br></small></td></tr>"; 

#if ($x<1) {print "<meta http-equiv=refresh content=0;url=regnskabskort.php>";}


?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
