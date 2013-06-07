<?php
// -------------finans/regnskab.php----lap 3.2.9------2012-11-06------------
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2012.10.11 Indsat "and (lukket != 'on' or saldo != 0)" søg 20121011
// 2012.11.06 Resultat føres ikke ned på resultatkonto. Søg 20121106

@session_start();
$s_id=session_id();
$css="../css/standard.css";
		
$modulnr=4;	
$title="Regnskabsoversigt";
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/db_query.php");
include("../includes/std_func.php");
include("../includes/finansfunk.php");
	
print "<div align=\"center\">";

print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";

if ($menu=='T') {
	$leftbutton="<a title=\"Klik her for at lukke kladdelisten\" href=\"../index/menu.php\" accesskey=\"L\">LUK</a>";
	$rightbutton="<a href=../finans/budget.php accesskey=b>Budget</a>";
	include("../includes/topmenu.php");
} elseif ($menu=='S') {
	include("../includes/sidemenu.php");
} else {
	print "	<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "		<table width=100% align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "			<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\">";
	if ($popup) print "<a href=../includes/luk.php accesskey=L>Luk</a></td>";
	else print "<a href=\"../index/menu.php\" accesskey=\"L\">Luk</a></td>";
	print "<td width=\"80%\" $top_bund> Regnskab</td> ";
	print "<td width=\"10%\" $top_bund><a href=\"budget.php\" accesskey=\"B\">Budget</a></td> ";
	print "</tbody></table> ";
	print "</td></tr> ";
}
$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
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
$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' and (lukket != 'on' or saldo != 0) order by kontonr",__FILE__ . " linje " . __LINE__); #20121011
while ($row = db_fetch_array($query)) {
	$x++;
	$konto_id[$x]=$row['id'];
	$kontonr[$x]=trim($row['kontonr']);
	$kontotype[$x]=$row['kontotype'];
	$beskrivelse[$x]=$row['beskrivelse'];
	$fra_kto[$x]=$row['fra_kto'];
	if ($row['kontotype']=='D' || $row['kontotype']=='S') {
		$primo[$x]=round($row['primo']+0.0001,2);
		$ultimo[$x]=round($row['primo']+0.0001,2);
		$q2 = db_select("select * from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr='$kontonr[$x]' order by transdate",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
		 	for ($y=1; $y<=$maanedantal; $y++) {
			 	if (($md[$y][1]<=$r2['transdate'])&&($md[$y+1][1]>$r2['transdate'])) {
			 		$md[$y][2]=$md[$y][2]+round($r2['debet']+0.0001,2)-round($r2['kredit']+0.0001,2);
					$belob[$x][$y]=$belob[$x][$y]+round($r2['debet']+0.0001,2)-round($r2['kredit']+0.0001,2);
				}
			}
			$ultimo[$x]=$ultimo[$x]+round($r2['debet']+0.0001,2)-round($r2['kredit']+0.0001,2);
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
				if (isset($primo[$z])) $primo[$x]+=$primo[$z];
					if (isset($belob[$z][$y])) {
						$belob[$x][$y]+=$belob[$z][$y];
						$ultimo[$x]+=$belob[$z][$y];
					}
				}
			} 		
 		}
		if ($kontotype[$x]=='R') { #20121106
			$primo[$x]=0;
 			$belob[$x][$y]=0;
			for ($z=1; $z<=$x; $z++){
				if ($kontonr[$z]==$fra_kto[$x]){
				if (isset($primo[$z])) $primo[$x]+=$primo[$z];
					if (isset($belob[$z][$y])) {
						$belob[$x][$y]+=$belob[$z][$y];
						$ultimo[$x]+=$belob[$z][$y];
					}
				}
			} 		
 		}
 	}
}
$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$startmaaned=$row['box1']*1;
$startaar=$row['box2']*1;
$slutmaaned=$row['box3']*1;
$slutaar=$row['box4']*1;
$slutdato=31;
$regnskabsaar=$row['beskrivelse'];

while (!checkdate($slutmaaned,$slutdato,$slutaar)){
	$slutdato=$slutdato-1;
	if ($slutdato<28){break;}
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

$ktonr=array();
$x=0;
$query = db_select("select kontonr from transaktioner where transdate>'$regnstart' and transdate<'$regnslut' order by transdate",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)){
	$x++;
	$ktonr[$x]=$row['kontonr']*1;
}

print " <tr><td valign=\"top\"> ";
print "<table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"1\" valign = \"top\"> ";
print "<tbody> ";
print "<tr><td><b> Kontonr.</b></td> ";
print "<td><b> Kontonavn</b></td> ";
print "<td title=\"Saldi ved regnskabs&aring;rets begyndelse. De fleste overf&oslash;rt fra regnskabet &aring;ret f&oslash;r.\" align=right><b> Primo</a></b></td> ";
#for ($z=1; $z<=$maanedantal; $z++) {
#	print "<td title=\"$z. regnskabsm&aring;ned\" align=right><b> MD_$z<b><br></td>";
#}
periodeoverskrifter($maanedantal, $startaar, $startmaaned, 1, "regnskabsmaaned", $regnskabsaar);
print "<td align=right><b> I alt</a></b></td> ";
print "</tr>";

$y='';
for ($x=1; $x<=$kontoantal; $x++){
	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
	else {$linjebg=$bgcolor5; $color='#000000';}
	print "<tr bgcolor=$linjebg>";
	if ($kontotype[$x]=='H') {
		print "<td><b> $kontonr[$x]<br></b></td>";
		print "<td colspan=15><b>$beskrivelse[$x]<br></b></td>";
	}
	else	{
#		if ($kontotype[$x]!='Z') {$link="<a href=kontospec.php?kontonr=$kontonr[$x]&month=";}
#		else {$link='';}
		print "<td>$kontonr[$x]<br></td>";
		print "<td>$beskrivelse[$x]<br></td>";
		$tal=dkdecimal($primo[$x]);
		print "<td align=right>$tal<br></td>";
		for ($z=1; $z<=$maanedantal; $z++) {
			$tal=dkdecimal($belob[$x][$z]); # if ($link) $y=$z.">";
			if ($kontotype[$x]!='Z') print "<td align=right><a href=kontospec.php?kontonr=$kontonr[$x]&month=$z>$tal<br></a></td>";
			else print "<td align=right>$tal<br></td>";
		}
		if ($kontotype[$x]=='Z') $ultimo[$x]=$ultimo[$x]+$primo[$x];  # if indsat 20.11.07 grundet fejl i sammentaeling paa statuskonti
		$tal=dkdecimal($ultimo[$x]); # if ($link) {$y='13>';}
		print "<td align=right>$y$tal<br></td>";
		$y='';
		print "</tr>";
	}
	if ($row['kontotype']=='H') {$linjebg='#ffffff'; $color='#ffffff';}
}
####################################################################################################
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
