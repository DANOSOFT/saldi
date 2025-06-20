<?php
// -------------debitor/saetpris.php----------lap 3.4.9-----2015-01-11----
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
// Copyright (c) 2015 DANOSOFT ApS
// -----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Sætpris";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
#include("../includes/usdecimal.php");
include("../includes/ordrefunc.php");

print "<div align=\"center\">";

$id=if_isset($_GET['id']);
$saet=if_isset($_GET['saet']);
$forfra=if_isset($_GET['forfra']);

if ($id && $forfra && $saet) {
	db_modify("update ordrelinjer set saet='0',rabat='0' where ordre_id='$id' and saet='$saet'",__FILE__ . " linje " . __LINE__);
}
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
/*
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\"$top_bund><a href=pos_ordre.php?id=$id accesskey=L>Luk</a></td>";
print "<td width=\"80%\"$top_bund>$title</td>";
print "<td width=\"10%\"$top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";
*/

if ($saet=$_POST['saetvalg']) {
	if ($saet=='nyt_saet') {
		$r=db_fetch_array(db_select("select max(saet) as saet from ordrelinjer where ordre_id='$id'",__FILE__ . " linje " . __LINE__));
		$saet=$r['saet']+1;
	}
} elseif ($linje_id=$_POST['linje_id']) {
	$saet=$_POST['saet'];
	$medtag=$_POST['medtag'];
	$ny_saetpris=usdecimal($_POST['ny_saetpris'])*1;
	$saetpris=$_POST['saetpris'];
	$kostsum=$_POST['kostsum'];
	$normalsum=$_POST['normalsum'];
	$ny_rabat=0;
	
	if ($saetpris && $ny_saetpris && $saetpris!=$ny_saetpris) {
		$ny_rabat=$normalsum-$ny_saetpris;
		$ny_rabat=$ny_rabat*100/$normalsum;
	}

	for ($x=0;$x<count($linje_id);$x++) {
		if ($medtag[$x]=='on') {
			db_modify("update ordrelinjer set saet='$saet' where id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
			if ($ny_rabat) {
				db_modify("update ordrelinjer set rabat='$ny_rabat' where id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
			}
		} elseif($saet) {
			db_modify("update ordrelinjer set saet='0' where id = $linje_id[$x] and saet='$saet'",__FILE__ . " linje " . __LINE__);
		}
	}
} 

if (!$saet) $saet=1;

$r=db_fetch_array(db_select("select box2 from grupper where art='OreDif'",__FILE__ . " linje " . __LINE__));
$difkto=$r['box2'];
$x=0;
$linje_id=array();
$r=db_fetch_array(db_select("select count(saet) as saet from ordrelinjer where ordre_id='$id' and saet='$saet'",__FILE__ . " linje " . __LINE__));
$valgt=$r['saet'];
$q=db_select("select * from ordrelinjer where ordre_id='$id' and (saet='0' or saet='$saet' or saet is NULL) order by ordrelinjer.posnr desc",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array ($q)) {
	if (!$valgt || $r['saet']) {
		$linje_id[$x]=$r['id'];
		$antal[$x]=$r['antal']*1;
		$pris[$x]=$r['pris']*1;
		$kostpris[$x]=$r['kostpris']*1;
		$rabat[$x]=$r['rabat']*1;
		$beskrivelse[$x]=$r['beskrivelse'];
		$medtag[$x]=$r['saet'];
		$momsfri[$x]=$r['momsfri'];
		$momssats[$x]=$r['momssats'];
		$normalpris[$x]=$antal[$x]*$pris[$x];
		if (!$momsfri[$x]){
			$kostpris[$x]+=$kostpris[$x]*$momssats[$x]/100;
			$normalpris[$x]+=$normalpris[$x]*$momssats[$x]/100;
		}
		$linjepris[$x]=$normalpris[$x]-($normalpris[$x]*$rabat[$x]/100);
		$x++;
	}
}
$saetpris=0;
$normalsum=0;
$kostsum=0;
print "<tr><td width=\"100%\" align=\"center\" colspan=\"3\"><big><b>Sæt $saet<b></big></td></tr>";
print "<tr><td width=\"45%\" align=\"right\" valign=\"top\"><table cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tbody>";
print "<form name=\"saetpris\" align=\"center\" action=\"saetpris.php?id=$id\" method=post autocomplete=\"off\">\n";
print "<tr><td>&nbsp;Beskrivelse&nbsp;</td><td align=\"right\">&nbsp;Antal&nbsp;</td><td align=\"right\">&nbsp;Pris&nbsp;</td><td align=\"right\">&nbsp;Medtag&nbsp;</td></tr>";
for ($x=0;$x<count($linje_id);$x++) {
	if (!$valgt || $medtag[$x]) {
		$kostsum+=$kostpris[$x];
		$normalsum+=$normalpris[$x];
		$saetpris+=$linjepris[$x];
		print "<tr>";
		print "<td>&nbsp;
			<input type=\"hidden\" name=\"linje_id[$x]\" value=\"$linje_id[$x]\">
			<input type=\"hidden\" name=\"antal[$x]\" value=\"$antal[$x]\">
			<input type=\"hidden\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\">
<!--			<input type=\"hidden\" name=\"medtag[$x]\" value=\"$medtag[$x]\"> -->
			<input type=\"hidden\" name=\"leveret[$x]\" value=\"$leveret[$x]\">
			<input type=\"hidden\" name=\"notes[$x]\" value=\"$notes[$x]\">
			$beskrivelse[$x]&nbsp;
		</td>";
		print "<td align=\"right\">".str_replace(".",",",$antal[$x])."</td>";
		print "<td align=\"right\">".dkdecimal($normalpris[$x])."</td>";
		($medtag[$x])?$medtag[$x]="checked":$medtag[$x]=NULL;
		print "<td align=\"right\"><input style=\"width:50px;height:30px;text-align:right\" name=\"medtag[$x]\" type=\"checkbox\" $medtag[$x] onfocus=\"document.forms[0].fokus.value=this.name;\"></td>"; 
		print "<tr>";
	}
}
$saetpris=afrund($saetpris,2);
$saetpris=pos_afrund($saetpris,$difkto);
print "<tr><td>
	<input type=\"hidden\" name=\"kostsum\" value=\"$kostsum\">
	<input type=\"hidden\" name=\"normalsum\" value=\"$normalsum\">
	<input type=\"hidden\" name=\"saetpris\" value=\"$saetpris\">
	<input type=\"hidden\" name=\"saet\" value=\"$saet\">
Kostpris</td><td colspan=\"3\" align=\"right\">".dkdecimal($kostsum)."</td></tr>";
print "<tr><td>Normalpris</td><td colspan=\"3\" align=\"right\">".dkdecimal($normalsum)."</td></tr>";
if (in_array("checked",$medtag)) {
	print "<tr><td>Sætpris</td><td colspan=\"3\" align=\"right\"><input type=\"text\" style=\"text-align:right\" value=\"".dkdecimal($saetpris)."\" name=\"ny_saetpris\"></td></tr>";
}
print "<tr><td colspan=\"4\"><hr></td></tr>";
print "<tr><td colspan=\"4\"><input type=\"hidden\" name=\"fokus\"><input type=\"hidden\" name=\"pre_fokus\" value=\"$fokus\">";
print "<input style=\"width:100%;height:40px;font-size:120%\" type=\"submit\" name=\"opdater\" value=\"Opdater\"></td></tr>";
print "</form>";
print "</tbody></table></td><td width=\"10%\"><br></td>";
$fokus="ny_saetpris";
tastatur($id,$fokus,$saet);

function tastatur($id,$fokus,$saet) {

	$x=0;
	$q=db_select("select saet from ordrelinjer where ordre_id='$id' and saet>'0' group by saet order by saet",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$saets[$x]=$r['saet'];
		$x++;
	}
	if (!$x) $saets[0]=1; 
	
	print "\n<!-- Function tastatur (start)-->\n";
	print "<TD height=\"100%\" valign=\"top\" align=\"left\" width=\"45%\"><TABLE BORDER=\"0\" CELLPADDING=\"4\" CELLSPACING=\"4\"><TBODY>\n";
	print "<TR>\n";
		$stil="STYLE=\"width:80px;height:40px;font-size:120%;\"";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"one\"   VALUE=\"1\" OnClick=\"saetpris.$fokus.value += '1';saetpris.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"two\"   VALUE=\"2\" OnCLick=\"saetpris.$fokus.value += '2';saetpris.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"three\" VALUE=\"3\" OnClick=\"saetpris.$fokus.value += '3';saetpris.$fokus.focus();\"></TD>\n";
		print "</TR><TR>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"four\"  VALUE=\"4\" OnClick=\"saetpris.$fokus.value += '4';saetpris.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"five\"  VALUE=\"5\" OnCLick=\"saetpris.$fokus.value += '5';saetpris.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"six\"   VALUE=\"6\" OnClick=\"saetpris.$fokus.value += '6';saetpris.$fokus.focus();\"></TD>\n";
		print "</TR><TR>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"seven\" VALUE=\"7\" OnClick=\"saetpris.$fokus.value += '7';saetpris.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"eight\" VALUE=\"8\" OnCLick=\"saetpris.$fokus.value += '8';saetpris.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"nine\"  VALUE=\"9\" OnClick=\"saetpris.$fokus.value += '9';saetpris.$fokus.focus();\"></TD>\n";
		print "</TR><TR>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"zero\"  VALUE=\",\" OnClick=\"saetpris.$fokus.value += ',';saetpris.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"zero\"  VALUE=\"0\" OnClick=\"saetpris.$fokus.value += '0';saetpris.$fokus.focus();\"></TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"clear\" VALUE=\"Ryd\" OnClick=\"saetpris.$fokus.value = '';saetpris.$fokus.focus();\"></TD>\n";
		print "</TR><TR>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"forfra\"  VALUE=\"Forfra\" OnClick=\"window.location.href='saetpris.php?id=$id&forfra=1&saet=$saet'\"></TD>\n";
		print "<FORM ACTION=\"saetpris.php?id=$id\" method=\"post\" autocomplete=\"off\">\n";
		print "<TD collspan=\"2\"><SELECT $stil NAME=\"saetvalg\" OnChange=\"this.form.submit()\">>";
		if ($saet) print "<OPTION VALUE=\"$saet\">Sæt $saet</OPTION>";		
		for($x=0;$x<count($saets);$x++){
			if ($saets[$x]!=$saet) print "<OPTION VALUE=\"$saets[$x]\">Sæt $saets[$x]</OPTION>";		
		}
		print "<OPTION VALUE=\"nyt_saet\">Nyt sæt</OPTION>";
		print "</SELECT>";
		print "</FORM>";
		print "</TD>\n";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"tilbage\"  VALUE=\"Tilbage\" OnClick=\"window.location.href='pos_ordre.php?id=$id'\"></TD>\n";
		print "</TR><TR>\n";
	print "</TR>\n";
	print "</TBODY></TABLE></TD></TR>\n";
	print "\n<!-- Function tastatur (slut)-->\n";
}

?>
<script language="javascript">
document.saetpris.<?php echo $fokus?>.focus();
</script>
<?php
#cho $fokus;

print "</tbody></table>";
#####################################################################################################

