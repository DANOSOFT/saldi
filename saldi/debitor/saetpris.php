<?php
// -------------debitor/saetpris.php----------lap 3.5.3-----2015-03-04----
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
// 2015.03.04	PHR Tilføjet mulighed for at slette et sæt

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Sætpris";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

print "<div align=\"center\">";

$id=if_isset($_GET['id']);
$saet=if_isset($_GET['saet']);
$forfra=if_isset($_GET['forfra']);

$r=db_fetch_array(db_select("select art,momssats from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
$art=$r['art'];
$momssats=$r['momssats'];

$r=db_fetch_array(db_SELECT("select box1 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
$incl_moms=$r['box1'];
if($r=db_fetch_array($q = db_SELECT("select box8 from grupper where art = 'DIV' and kodenr = '5' and box8!=''",__FILE__ . " linje " . __LINE__))) {
 $svid=$r['box8']*1;
	$r=db_fetch_array(db_SELECT("select varenr from varer where id = '$svid'",__FILE__ . " linje " . __LINE__));
	$svnr=$r['varenr'];
} else $svnr='S';
$r=db_fetch_array(db_SELECT("select id from varer where varenr = '$svnr'",__FILE__ . " linje " . __LINE__));
if (!$r['id']) print "<BODY onLoad=\"JavaScript:alert('Varenummer $svnr for sæt ikke gyldigt')\">";

if ($id && $forfra && $saet) {
	db_modify("delete from ordrelinjer where ordre_id='$id' and saet='$saet' and varenr='$svnr'",__FILE__ . " linje " . __LINE__);
	db_modify("update ordrelinjer set saet='0',rabat='0' where ordre_id='$id' and saet='$saet'",__FILE__ . " linje " . __LINE__);
} elseif ($slet=$_POST['slet']) {
	db_modify("delete from ordrelinjer where ordre_id='$id' and saet='$saet'",__FILE__ . " linje " . __LINE__);
	db_modify("update ordrelinjer set saet=saet-1 where ordre_id='$id' and saet > $saet",__FILE__ . " linje " . __LINE__);

}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";

if ($saet=$_POST['saetvalg']) {
	if ($saet=='nyt_saet') {
		$r=db_fetch_array(db_select("select max(saet) as saet from ordrelinjer where ordre_id='$id'",__FILE__ . " linje " . __LINE__));
		$saet=$r['saet']+1;
#		$r=db_fetch_array(db_select("select * from varer where varenr='$svnr'",__FILE__ . " linje " . __LINE__));
#		opret_ordrelinje($id,$r['id'],$r['varenr'],1,$r['beskrivelse'],0,0,100,'$art','','','0','on','','','',$saet);
#		db_modify("update ordrelinjer set samlevare='on' where ordre_id='$id' and saet='$saet' and varenr='$svnr'",__FILE__ . " linje " . __LINE__);
	}
} elseif ($linje_id=$_POST['linje_id']) {
	$saet=$_POST['saet'];
	$medtag=$_POST['medtag'];
	$pris=$_POST['pris'];
	$antal=$_POST['antal'];
	$varenr=$_POST['varen'];
	$varemomssats=$_POST['momssats'];
	$normalpris=$_POST['normalpris'];
	$ny_saetpris=usdecimal($_POST['ny_saetpris'])*1;
	$saetpris=$_POST['saetpris'];
	$kostsum=$_POST['kostsum'];
	$normalsum=$_POST['normalsum'];
	$ny_rabat=0;
	$rabatsum=0;
	$saetsum=0;
	$tjeksum=0;

	if ($r=db_fetch_array(db_select("select varenr from ordrelinjer where ordre_id='$id' and saet='$saet' and samlevare='on'",__FILE__ . " linje " . __LINE__))) {
		$svnr=$r['varenr'];
	}
	db_modify("update ordrelinjer set posnr=posnr+100 where ordre_id = $id",__FILE__ . " linje " . __LINE__);
	
	if ($ny_saetpris) {
		if (!db_fetch_array(db_select("select id from ordrelinjer where ordre_id='$id' and varenr='$svnr' and saet='$saet'",__FILE__ . " linje " . __LINE__))){
			$r=db_fetch_array(db_select("select * from varer where varenr='$svnr'",__FILE__ . " linje " . __LINE__));
			opret_ordrelinje($id,$r['id'],$r['varenr'],1,$r['beskrivelse'],0,0,100,'$art','','','0','on','','','',$saet);
			db_modify("update ordrelinjer set posnr='99',samlevare='on',lev_varenr='$ny_saetpris' where ordre_id='$id' and saet='$saet' and varenr='$svnr'",__FILE__ . " linje " . __LINE__);
		}
		$ny_rabat=$normalsum-$ny_saetpris;
		$ny_rabat=afrund($ny_rabat*100/$normalsum,3);
	}
	for ($x=0;$x<count($linje_id);$x++) {
		if ($medtag[$x]=='on') {
			db_modify("update ordrelinjer set saet='$saet' where id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
			if ($ny_rabat && $varenr[$x]!=$svnr) {
				db_modify("update ordrelinjer set posnr='$x',rabat='$ny_rabat' where id = $linje_id[$x] and varenr!= '$svnr'",__FILE__ . " linje " . __LINE__);
				$linjesum=$antal[$x]*$pris[$x];
				$linjesum-=$linjesum*$ny_rabat/100;
				if ($incl_moms) $linjesum+=$linjesum*$varemomssats[$x]/100;
				$linjesum=afrund($linjesum,3);
				$tjeksum+=$linjesum;
			}
		} elseif($saet) {
			db_modify("update ordrelinjer set saet='0',rabat='0' where id = $linje_id[$x] and saet='$saet' and varenr != '$svnr'",__FILE__ . " linje " . __LINE__);
		}
	}
	if ($ny_rabat) {
		$diff=afrund($ny_saetpris-$tjeksum,3);
		$lev_varenr=$ny_saetpris."|".$ny_rabat;
		db_modify("update ordrelinjer set pris='$diff',lev_varenr='$lev_varenr',rabat='20',momsfri='' where ordre_id='$id' and saet='$saet' and varenr = '$svnr'",__FILE__ . " linje " . __LINE__);
	}
}
$x=1;
$q=db_select("select id from ordrelinjer where ordre_id='$id'order by saet,posnr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	db_modify("update ordrelinjer set posnr='$x' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
	$x++;
}

if (!$saet) {
	$s=array();
	$x=0;
	$q=db_select("select distinct(saet) as saet from ordrelinjer where ordre_id='$id' and samlevare='on' and varenr!='$svnr' order by saet",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$s[$x]=$r['saet'];
	}
	$saet=1;
	while(in_array($saet,$s)) $saet++; 
}

$r=db_fetch_array(db_select("select box2 from grupper where art='OreDif'",__FILE__ . " linje " . __LINE__));
$difkto=$r['box2'];
$x=0;
$linje_id=array();
$r=db_fetch_array(db_select("select count(saet) as saet from ordrelinjer where ordre_id='$id' and saet='$saet'",__FILE__ . " linje " . __LINE__));
$valgt=$r['saet'];
$q=db_select("select * from ordrelinjer where ordre_id='$id' and (saet='0' or saet='$saet' or saet is NULL) order by ordrelinjer.posnr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array ($q)) {
	if (!$valgt || $r['saet']) {
		$linje_id[$x]=$r['id'];
		$varenr[$x]=$r['varenr'];
		$antal[$x]=$r['antal']*1;
		$pris[$x]=$r['pris']*1;
		$kostpris[$x]=$r['kostpris']*1;
		$rabat[$x]=$r['rabat']*1;
		$beskrivelse[$x]=$r['beskrivelse'];
		$medtag[$x]=$r['saet'];
		$momsfri[$x]=$r['momsfri'];
		$varemomssats[$x]=$r['momssats'];
		$samlevare[$x]=$r['samlevare'];
		if ($samlevare[$x]){
			$svnr=$varenr[$x];
			$snavn=$beskrivelse[$x];
		}
		$normalpris[$x]=$antal[$x]*$pris[$x];
		if ($momsfri[$x]) $varemomssats[$x]=0;
		elseif ($incl_moms) {
			$kostpris[$x]+=$kostpris[$x]*$varemomssats[$x]/100;
			$normalpris[$x]+=$normalpris[$x]*$varemomssats[$x]/100;
		} 
		$linjepris[$x]=afrund($normalpris[$x]-($normalpris[$x]*$rabat[$x]/100),3);
		$x++;
	}
}
$saetpris=0;
$normalsum=0;
$kostsum=0;

print "<tr><td width=\"100%\" align=\"center\" colspan=\"3\"><big><b>Sæt $saet<b></big></td></tr>";
print "<tr><td width=\"45%\" align=\"right\" valign=\"top\"><table cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tbody>";
print "<form name=\"saetpris\" align=\"center\" action=\"saetpris.php?id=$id\" method=post autocomplete=\"off\">\n";
print "<tr><td align=\"center\" colspan=\"4\">$snavn</td></tr>";
print "<tr><td colspan=\"4\"><hr></td></tr>";
print "<tr><td><b>Beskrivelse</b></td><td style=\"width:40px;\" align=\"right\"><b>Antal</b></td><td style=\"width:80px;\" align=\"right\"><b>Pris</b></td><td align=\"center\"><b>Medtag</b></td></tr>";
for ($x=0;$x<count($linje_id);$x++) {
	if (!$valgt || $medtag[$x]) {
		if (!$samlevare[$x]) { 
			$kostsum+=$kostpris[$x]*$antal[$x];
			$normalsum+=$normalpris[$x];
		}
		$saetpris+=$linjepris[$x];
		print "<input type=\"hidden\" name=\"linje_id[$x]\" value=\"$linje_id[$x]\">\n
					<input type=\"hidden\" name=\"varenr[$x]\" value=\"$varenr[$x]\">\n
					<input type=\"hidden\" name=\"pris[$x]\" value=\"$pris[$x]\">\n
					<input type=\"hidden\" name=\"antal[$x]\" value=\"$antal[$x]\">\n
					<input type=\"hidden\" name=\"normalpris[$x]\" value=\"$normalpris[$x]\">\n
					<input type=\"hidden\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\">\n
					<input type=\"hidden\" name=\"momssats[$x]\" value=\"$varemomssats[$x]\">\n
					<input type=\"hidden\" name=\"leveret[$x]\" value=\"$leveret[$x]\">\n
					<input type=\"hidden\" name=\"notes[$x]\" value=\"$notes[$x]\">\n";
	if (!$samlevare[$x]) {
			print "<tr>";
			print "<td>$beskrivelse[$x]&nbsp;</td>\n";
			print "<td align=\"right\">".str_replace(".",",",$antal[$x])."</td>\n";
			print "<td align=\"right\">".dkdecimal($normalpris[$x])."</td>\n";
			($medtag[$x])?$medtag[$x]="checked":$medtag[$x]=NULL;
			print "<td align=\"right\"><input style=\"width:50px;height:30px;\" name=\"medtag[$x]\" type=\"checkbox\" $medtag[$x] onfocus=\"document.forms[0].fokus.value=this.name;\"></td>"; 
			print "<tr>";
		}
	}
}
print "<tr><td colspan=\"4\"><hr></td></tr>";
$saetpris=afrund($saetpris,2);
# $saetpris=pos_afrund($saetpris,$difkto);
print "<tr><td>
	<input type=\"hidden\" name=\"kostsum\" value=\"$kostsum\">
	<input type=\"hidden\" name=\"normalsum\" value=\"$normalsum\">
	<input type=\"hidden\" name=\"saetpris\" value=\"$saetpris\">
	<input type=\"hidden\" name=\"saet\" value=\"$saet\">
	<!--Kostpris</td><td colspan=\"2\" align=\"right\">".dkdecimal($kostsum)."--></td></tr>";
print "<tr><td>Normalpris</td><td title=\"Kostpris: ".dkdecimal($kostsum)."\" colspan=\"2\" align=\"right\">".dkdecimal($normalsum)."</td></tr>";
if (in_array("checked",$medtag)) {
	print "<tr><td>Sætpris</td><td  title=\"Kostpris: ".dkdecimal($kostsum)."\" colspan=\"2\" align=\"right\"><input type=\"text\" style=\"text-align:right\" value=\"".dkdecimal($saetpris)."\" name=\"ny_saetpris\"></td></tr>";
}
print "<tr><td colspan=\"4\"><hr></td></tr>";
print "<tr><td colspan=\"4\"><input type=\"hidden\" name=\"fokus\"><input type=\"hidden\" name=\"pre_fokus\" value=\"$fokus\">";
print "<input style=\"width:100%;height:40px;font-size:120%\" type=\"submit\" name=\"opdater\" value=\"Opdater\"></td></tr>";
print "</form>";
print "</tbody></table></td><td width=\"10%\"><br></td>";
$fokus="ny_saetpris";
tastatur($id,$fokus,$saet);

function tastatur($id,$fokus,$saet) {
	global $art;

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
		($art=='PO')?$href="pos_ordre.php?id=$id":$href="ordre.php?id=$id";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"tilbage\"  VALUE=\"Tilbage\" OnClick=\"window.location.href='$href'\"></TD>\n";
		print "</TR><TR>\n";
		print "<FORM ACTION=\"saetpris.php?id=$id&saet=$saet\" method=\"post\" autocomplete=\"off\">\n";
		print "<TD><INPUT TYPE=\"submit\" $stil OnClick=\"return confirm('Slet hele sættet fra ordren?');\" NAME=\"slet\" VALUE=\"Slet\"></TD>\n";
		print "</FORM>";
		print "<TD></TD>\n";
		print "<TD><TD>\n";
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

