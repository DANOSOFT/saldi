<?php
// -------------------------------------------------------finans/kontospec.php------lap 1.0.02------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------

$kontonr=$_GET['kontonr'];
$month=$_GET['month'];
$bilag=$_GET['bilag'];

if(!$month){$month=13;}
	
@session_start();
$s_id=session_id();
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/dkdecimal.php");
include("../includes/db_query.php");

$query = db_select("select * from grupper where art='RA' and kodenr='$regnaar'");
if ($row = db_fetch_array($query))
{
	$month=str_replace(" ","",$month);
	if ($month=='13')	{
		$start=$start=$row['box2'].'-'.$row['box1'].'-01';
		$slutdato=31;
		$month=str_replace(" ","",$row['box3']);
		$year=str_replace(" ","",$row['box4']);
	}
	else	{
		if ($month >= $row['box1']){$year=$row['box2'];}
		else {$year=$row['box2']+1;}
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
print "<table width=100% align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\"><small><a href=regnskab.php accesskey=T>Tilbage</a></small></td>";
print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Specifikation";
if($kontonr) {echo "konto nr: $kontonr";}
if($bilag) {print "bilag nr: $bilag";}
print " </small></td><td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small></a></small></td>";
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
print " <td align=right><b>$font Projektnr</a></b></td>";
print "</tr>";
print "<tr><td colspan=8><hr></td></tr>";

if ($kontonr){$valg="and kontonr = '$kontonr'";}
elseif ($bilag){$valg="and bilag = '$bilag'";}
$x=0;
$query = db_select("select * from transaktioner where transdate >= '$start' and transdate <= '$slut' $valg order by transdate");
while ($row = db_fetch_array($query)) {
	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
	else{$linjebg=$bgcolor5; $color='#000000';}
	print "<tr bgcolor=\"$linjebg\">";
	print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><a href=kontospec.php?bilag=$row[bilag] target=blank>$row[bilag]</a><br></small></td>";
	$dato=dkdato($row['transdate']);
	print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$dato</a><br></small></td>";
	print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[beskrivelse]</a><br></small></td>";
	$tal=dkdecimal($row['debet']);
	print "<td align=right><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$tal</a><br></small></td>";
	$tal=dkdecimal($row['kredit']);
	print "<td align=right><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$tal</a><br></small></td>";
	print "<td align=right><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[faktura]</a><br></small></td>";
	print "<td align=right><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><a href=kassekladde.php?kladde_id=$row[kladde_id]&returside=kontospec.php target=blank>$row[kladde_id]</a><br></small></td>";
	print "<td align=right><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[projekt_id]</a><br></small></td>";
	print "</tr>";
}
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
