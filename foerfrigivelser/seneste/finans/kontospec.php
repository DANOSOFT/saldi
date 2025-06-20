<?php
// ----------------------finans/kontospec.php------lap 1.9.4------2008-04-16
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
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();
$title="Kontospecifikation";

$kontonr=$_GET['kontonr'];
$month=$_GET['month'];
$bilag=$_GET['bilag'];
#if(!$month){$month=13;}
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/dkdecimal.php");
include("../includes/db_query.php");

$query = db_select("select * from grupper where art='RA' and kodenr='$regnaar'");
if ($row = db_fetch_array($query)) {
	$month=trim($month);
	if (!$month)	{
		$start=$start=$row['box2'].'-'.$row['box1'].'-01';
		$slutdato=31;
		$month=str_replace(" ","",$row['box3']);
		$year=str_replace(" ","",$row['box4']);
	}
	else	{
			$month=$month-1+$row['box1'];
			$year=$row['box2'];
			while ($month > 12) {
				$year++;
				$month=$month-12;
			}
		$year=str_replace(" ","",$year);
		$start=$year.'-'.$month.'-01';
	}
	$slutdato=31;
	while (!checkdate($month, $slutdato, $year))	{
		$slutdato=$slutdato-1;
	}
	$slut=$year.'-'.$month.'-'.$slutdato;
	$start=str_replace(" ","",$start);
	$slut=str_replace(" ","",$slut);
}

print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=100% align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\"><small><a href=regnskab.php accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Specifikation for ";
if($kontonr) {print "konto: $kontonr";}
if($bilag) {print "bilag: $bilag";}
print " </small></td><td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small></a></small></td>";
print "</tbody></table>";
print "</td></tr>";
print "<tr><td valign=\"top\">";
print "<table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\">";
print "<tbody>";
print "<tr>";
print " <td><b>$font Bilag</a></b></td>";
print "<td><b>$font Dato</a></b></td>";
print " <td><b>$font Bilagstekst</a></b></td>";
print "<td align=right><b>$font Debet</a></b></td>";
print "<td align=right><b>$font Kredit</a></b></td>";
print "<td align=right><b>$font Fakturanr</a></b></td>";
print " <td align=right><b>$font Kladdenr</a></b></td>";
print " <td align=right><b>$font Afd. nr</a></b></td>";
print "</tr>";
print "<tr><td colspan=8><hr></td></tr>";

if ($kontonr){$valg="and kontonr = '$kontonr'";}
elseif ($bilag){$valg="and bilag = '$bilag'";}
$x=0;
$query = db_select("select * from transaktioner where transdate >= '$start' and transdate <= '$slut' $valg order by transdate");
while ($row = db_fetch_array($query)) {
	if ($linjebg!=$bgcolor) {
		$linjebg=$bgcolor; $color='#000000';
	} else {
		$linjebg=$bgcolor5; $color='#000000';
	}
	print "<tr bgcolor=\"$linjebg\">";
	print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">";
	if ($row[bilag]) print "<a href=kontospec.php?bilag=$row[bilag] target=blank>$row[bilag]</a><br>";
	print "</small></td>";
	$dato=dkdato($row['transdate']);
	print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$dato</a><br></small></td>";
	print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[beskrivelse]</a><br></small></td>";
	$tal=dkdecimal($row['debet']);
	print "<td align=right><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$tal</a><br></small></td>";
	$tal=dkdecimal($row['kredit']);
	print "<td align=right><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$tal</a><br></small></td>";
	print "<td align=right><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[faktura]</a><br></small></td>";
	print "<td align=right><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><a href=kassekladde.php?kladde_id=$row[kladde_id]&returside=kontospec.php target=blank>$row[kladde_id]</a><br></small></td>";
	print "<td align=right><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[afd]</a><br></small></td>";
	print "</tr>";
}
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
