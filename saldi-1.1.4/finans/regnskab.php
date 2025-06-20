<?php
// -------------finans/regnskab.php----lap 1.1.2b------20.11.2007------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg

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
		
$modulnr=4;	
$title="Regnskabsoversigt";
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/db_query.php");
include("../includes/dkdecimal.php");
	
print "<div align=\"center\">";

print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "	<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "		<table width=100% align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "			<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\"><small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
print "			<td width=\"80%\" $top_bund>$font <small>Regnskab</small></td> ";
print "			<td width=\"10%\" $top_bund><br></td> ";
print "			</tbody></table> ";
print "	</td></tr> ";

$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
$row = db_fetch_array($query);
$startmaaned=$row[box1];
$startaar=$row[box2];
$slutmaaned=$row[box3];
$slutaar=$row[box4];
$slutdato=31;
		
while (!checkdate($slutmaaned, $slutdato, $slutaar)) {
#echo "$slutdato, $slutmaaned, $slutaar	";				
	$slutdato=$slutdato-1;
	if ($slutdato<28) break;
}
#echo "slutdato $slutdato<br>";		
$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

$tmpaar=$startaar;
$md[1][0]=$startmaaned;
$md[1][1]=$regnstart;
$md[1][2]=0;
$x=1;
while ($md[$x][1]<$regnslut) {
	$x++;
	$md[$x][0]=$md[$x-1][0]+1;
	if ($md[$x][0]>12) {
		$tmpaar++;
		$md[$x][0]=1;
	}
	if ($md[$x][0]<10) $tmp="0".$md[$x][0];
	else $tmp=$md[$x][0];
	$md[$x][1]=$tmpaar. "-" .$tmp."-01"; 
	$md[$x][2]=0;
}
$maanedantal=$x-1;
$x=0;
$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr");
while ($row = db_fetch_array($query)) {
	$x++;
	$konto_id[$x]=$row['id'];
	$kontonr[$x]=trim($row['kontonr']);
	$kontotype[$x]=$row['kontotype'];
	$beskrivelse[$x]=$row['beskrivelse'];
	$fra_kto[$x]=$row['fra_kto'];
	if ($row[kontotype]=='D' or $row[kontotype]=='S') {
		$primo[$x]=$row[primo];
		$ultimo[$x]=$row[primo];
		$q2 = db_select("select * from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr='$kontonr[$x]' order by transdate");
		while ($r2 = db_fetch_array($q2)) {
		 	for ($y=1; $y<=$maanedantal; $y++) {
			 	if (($md[$y][1]<=$r2[transdate])&&($md[$y+1][1]>$r2[transdate])) {
			 		$md[$y][2]=$md[$y][2]+$r2[debet]-$r2[kredit];
					$belob[$x][$y]=$belob[$x][$y]+$r2[debet]-$r2[kredit];
				}
			}
			$ultimo[$x]=$ultimo[$x]+$r2[debet]-$r2[kredit];
		}
	}	
}
$kontoantal=$x;

for ($x=1; $x<=$kontoantal; $x++) {
#	echo $kontonr[$x];
	for ($y=1; $y<=$maanedantal; $y++) {
		if ($kontotype[$x]=='Z') {
			$primo[$x]=0;
 			$belob[$x][$y]=0;
			for ($z=1; $z<=$x; $z++){
				if (($kontonr[$z]>=$fra_kto[$x])&&($kontonr[$z]<=$kontonr[$x])&&($kontotype[$z]!='H')&&($kontotype[$z]!='Z')){
					$primo[$x]=$primo[$x]+$primo[$z];
					$belob[$x][$y]=$belob[$x][$y]+$belob[$z][$y];
					$ultimo[$x]=$ultimo[$x]+$belob[$z][$y];
				}
			} 		
 		}
 	}
}
$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
$row = db_fetch_array($query);
$startmaaned=$row[box1]*1;
$startaar=$row[box2]*1;
$slutmaaned=$row[box3]*1;
$slutaar=$row[box4]*1;
$slutdato=31;

while (!checkdate($slutmaaned,$slutdato,$slutaar)){
	$slutdato=$slutdato-1;
	if ($slutdato<28){break;}
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

$ktonr=array();
$x=0;
$query = db_select("select kontonr from transaktioner where transdate>'$regnstart' and transdate<'$regnslut' order by transdate");
while ($row = db_fetch_array($query)){
	$x++;
	$ktonr[$x]=$row[kontonr]*1;
}

print " <tr><td valign=\"top\"> ";
print "<table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"1\" valign = \"top\"> ";
print "<tbody> ";
print "<tr><td><b>$font Kontonr</b></td> ";
print "<td><b>$font Kontotekst</b></td> ";
print "<td align=right><b>$font Primo</a></b></td> ";
for ($z=1; $z<=$maanedantal; $z++) {
	print "<td align=right><small><b>$font MD_$z<b><br></small></td>";
}
print "<td align=right><b>$font I Alt</a></b></td> ";
print "</tr>";

$y='';
for ($x=1; $x<=$kontoantal; $x++){
	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
	else {$linjebg=$bgcolor5; $color='#000000';}
	print "<tr bgcolor=$linjebg>";
	if ($kontotype[$x]=='H') {
		print "<td><b>$font $kontonr[$x]</a><br></b></td>";
		print "<td colspan=15><b>$font $beskrivelse[$x]</a><br></b></td>";
	}
	else	{
		if ($kontotype[$x]!='Z') {$link="<a href=kontospec.php?kontonr=$kontonr[$x]&month=";}
		else {$link='';}
		print "<td><small>$font $kontonr[$x]</a><br></small></td>";
		print "<td><small>$font $beskrivelse[$x]</a><br></small></td>";
		$tal=dkdecimal($primo[$x]);
		print "<td align=right><small>$font $tal</a><br></small></td>";
		for ($z=1; $z<=$maanedantal; $z++) {
			$tal=dkdecimal($belob[$x][$z]); if ($link) $y=$z;
			print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		}
		if ($kontotype[$x]=='Z') $ultimo[$x]=$ultimo[$x]+$primo[$x];  # if indsat 20.11.07 grundet fejl i sammentæling på statuskonti
		$tal=dkdecimal($ultimo[$x]); if ($link) {$y='13>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$y='';
		print "</tr>";
	}
	if ($row[kontotype]=='H') {$linjebg='#ffffff'; $color='#ffffff';}
}
####################################################################################################
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
