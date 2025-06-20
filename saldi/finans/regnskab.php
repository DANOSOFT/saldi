<?php
// ------------------------------------------------------------------finans/regnskab.php----lap 1.0.9----------
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
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/dkdecimal.php");
	
print "<div align=\"center\">";

print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "	<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "		<table width=100% align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "			<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\"><small><a href=../includes/luk.php accesskey=T>Tilbage</a></small></td>";
print "			<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font <small>Regnskab</small></td> ";
print "			<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\">$font <small><a href=kontokort.php accesskey=N>Ny</a></small></td> ";
print "			</tbody></table> ";
print "	</td></tr> ";
print " <tr><td valign=\"top\"> ";
print "<table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"1\" valign = \"top\"> ";
print "<tbody> ";
print "	<tr> ";
print "		<td><b>$font Kontonr</b></td> ";
print "		<td><b>$font Kontotekst</b></td> ";
print "		<td align=right><b>$font Primo</a></b></td> ";
print "		<td align=right><b>$font md01</a></b></td> ";
print "		<td align=right><b>$font md02</a></b></td> ";
print "		<td align=right><b>$font md03</a></b></td> ";
print "		<td align=right><b>$font md04</a></b></td> ";
print "		<td align=right><b>$font md05</a></b></td> ";
print "		<td align=right><b>$font md06</a></b></td> ";
print "		<td align=right><b>$font md07</a></b></td> ";
print "		<td align=right><b>$font md08</a></b></td> ";
print "		<td align=right><b>$font md09</a></b></td> ";
print "		<td align=right><b>$font md10</a></b></td> ";
print "		<td align=right><b>$font md11</a></b></td> ";
print "		<td align=right><b>$font md12</a></b></td> ";
print "		<td align=right><b>$font I Alt</a></b></td> ";
print "	</tr>";

$x=0;
$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr");
while ($row = db_fetch_array($query)) {
	$d=trim($row[kontotype]);
# echo "S = $s<br>";
	if  ((($d!="D")&&($d!="S"))||($row[primo]!=0)||($row[md01])||($row[md02])||($row[md03])||($row[md04])||($row[md05])||($row[md06])||($row[md07])||($row[md08])||($row[md09])||($row[md10])||($row[md11])||($row[md12])) {
		$x++;
		$id[$x]=$row[id];
		$kontonr[$x]=$row[kontonr];
		$beskrivelse[$x]=$row[beskrivelse];
		$kontotype[$x]=$row[kontotype];
		$fra_kto[$x]=$row[fra_kto];
		$til_kto[$x]=$row[til_kto];
		$lukket[$x]=$row[lukket];
		$primo[$x]=$row[primo];
		$md01[$x]=$row[md01];
		$md02[$x]=$row[md02];
		$md03[$x]=$row[md03];
		$md04[$x]=$row[md04];
		$md05[$x]=$row[md05];
		$md06[$x]=$row[md06];
		$md07[$x]=$row[md07];
		$md08[$x]=$row[md08];
		$md09[$x]=$row[md09];
		$md10[$x]=$row[md10];
		$md11[$x]=$row[md11];
		$md12[$x]=$row[md12];
		if ($kontotype[$x]=="S"){$ialt[$x]=$primo[$x];}
		else {$ialt[$x]=0;}
		$ialt[$x]=$ialt[$x]+$md01[$x]+$md02[$x]+$md03[$x]+$md04[$x]+$md05[$x]+$md06[$x]+$md07[$x]+$md08[$x]+$md09[$x]+$md10[$x]+$md11[$x]+$md12[$x];
	}
}
$kontoantal=$x;

for ($x=1; $x<=$kontoantal; $x++) {
	if ($kontotype[$x]=='Z') {
		$primo[$x]=0;
		$md01[$x]=0;
		$md02[$x]=0;
		$md03[$x]=0;
		$md04[$x]=0;
		$md05[$x]=0;
		$md06[$x]=0;
		$md07[$x]=0;
		$md08[$x]=0;
		$md09[$x]=0;
		$md10[$x]=0;
		$md11[$x]=0;
		$md12[$x]=0;
		$ialt[$x]=0;
		for ($y=1; $y<=$x; $y++){
			if (($kontonr[$y]>=$fra_kto[$x])&&($kontonr[$y]<=$til_kto[$x])&&($kontotype[$y]!='H')&&($kontotype[$y]!='Z')){
				$primo[$x]=$primo[$x]+$primo[$y];
				$md01[$x]=$md01[$x]+$md01[$y];
				$md02[$x]=$md02[$x]+$md02[$y];
				$md03[$x]=$md03[$x]+$md03[$y];
				$md04[$x]=$md04[$x]+$md04[$y];
				$md05[$x]=$md05[$x]+$md05[$y];
				$md06[$x]=$md06[$x]+$md06[$y];
				$md07[$x]=$md07[$x]+$md07[$y];
				$md08[$x]=$md08[$x]+$md08[$y];
				$md09[$x]=$md09[$x]+$md09[$y];
				$md10[$x]=$md10[$x]+$md10[$y];
				$md11[$x]=$md11[$x]+$md11[$y];
				$md12[$x]=$md12[$x]+$md12[$y];
				$ialt[$x]=$ialt[$x]+$ialt[$y];
			}
		}
	}
}

# -> Finder de konti hvor der har været transaktioner i indeværende regnskabsaar

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

# <- Finder de konti hvor der har vaeret transaktioner i indevaerende regnskabsaaar

$y='';
for ($x=1; $x<=$kontoantal; $x++){
	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
	else {$linjebg=$bgcolor5; $color='#000000';}
	print "<tr bgcolor=$linjebg>";
	if ($kontotype[$x]=='H') {
		print "<td><b>$font $kontonr[$x]</a><br></b></td>";
		print "<td colspan=15 align=center><b>$font $beskrivelse[$x]</a><br></b></td>";
	}
	else	{
		if ($kontotype[$x]!='Z') {$link="<a href=kontospec.php?kontonr=$kontonr[$x]&month=";}
		else {$link='';}
		print "<td><small>$font $kontonr[$x]</a><br></small></td>";
		print "<td><small>$font $beskrivelse[$x]</a><br></small></td>";
		$tal=dkdecimal($primo[$x]);
		print "<td align=right><small>$font $tal</a><br></small></td>";
		$tal=dkdecimal($md01[$x]); if ($link) {$y='01>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($md02[$x]); if ($link) {$y='02>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($md03[$x]); if ($link) {$y='03>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($md04[$x]); if ($link) {$y='04>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($md05[$x]); if ($link) {$y='05>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($md06[$x]); if ($link) {$y='06>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($md07[$x]); if ($link) {$y='07>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($md08[$x]); if ($link) {$y='08>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($md09[$x]); if ($link) {$y='09>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($md10[$x]); if ($link) {$y='10>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($md11[$x]); if ($link) {$y='11>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($md12[$x]); if ($link) {$y='12>';}
		print "<td align=right><small>$link$y $font $tal</a><br></small></td>";
		$tal=dkdecimal($ialt[$x]); if ($link) {$y='13>';}
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
