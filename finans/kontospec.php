<?php
// ----------------------finans/kontospec.php------lap 2.0.9-----2009-06-28
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();
$title="Kontospecifikation";
$css="../css/standard.css";

	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$kontonr=if_isset($_GET['kontonr']);
$month=if_isset($_GET['month']);
$bilag=if_isset($_GET['bilag']);
#if(!$month){$month=13;}

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
print "<td width=\"10%\" $top_bund><a href=regnskab.php accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>Specifikation for ";
if($kontonr) {print "konto: $kontonr";}
if($bilag) {print "bilag: $bilag";}
print " </td><td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "<tr><td valign=\"top\">";
print "<table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\">";
print "<tbody>";
print "<tr>";
print " <td><b> Bilag</a></b></td>";
print "<td><b> Dato</a></b></td>";
print " <td><b> Bilagstekst</a></b></td>";
print " <td align=right><b> Kontonr</a></b></td>";
print "<td align=right><b> Debet</a></b></td>";
print "<td align=right><b> Kredit</a></b></td>";
print "<td align=right><b> Fakturanr</a></b></td>";
print " <td align=right><b> Kladdenr</a></b></td>";
print " <td align=right><b> Afd. nr</a></b></td>";
print " <td align=right><b> Projekt. nr</a></b></td>";
print "</tr>";
print "<tr><td colspan=11><hr></td></tr>";

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
	print "<td>";
	if ($row['bilag']) print "<a href=kontospec.php?bilag=$row[bilag] target=\"_blank\">$row[bilag]</a><br>";
	print "</td>";
	$dato=dkdato($row['transdate']);
	print "<td>$dato</a><br></td>";
	print "<td>$row[beskrivelse]</a><br></td>";
	print "<td align=right>$row[kontonr]</a><br></td>";
	$tal=dkdecimal($row['debet']);
	print "<td align=right>$tal</a><br></td>";
	$tal=dkdecimal($row['kredit']);
	print "<td align=right>$tal</a><br></td>";
	print "<td align=right>$row[faktura]</a><br></td>";
	print "<td align=right><a href=kassekladde.php?kladde_id=$row[kladde_id]&returside=kontospec.php target=\"_blank\">$row[kladde_id]</a><br></td>";
	print "<td align=right>$row[afd]</a><br></td>";
	print "<td align=right>$row[projekt]</a><br></td>";
	print "</tr>";
}
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
