<?php
// -----------------lager/lagerstatus.php----lap 2.0.7-----2009-05-16--
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
	// Copyright (c) 2006-2009 DANOSOFT ApS
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Lagerstatus";

$linjebg=NULL;
$lagervalue=0;$salgsvalue=0;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if ($popup) $returside="../includes/luk.php";
else $returside="rapport.php";

if (isset($_POST['dato']) && $_POST['dato']) {
	$dato=$_POST['dato'];
}
else $dato=date("d-m-Y");



$date=usdate($dato);
$dato=dkdato($date);

$x=0;
$q1= db_select("select kodenr, box9 from grupper where box8 = 'on'",__FILE__ . " linje " . __LINE__);
while ($r1=db_fetch_array($q1)) {
	$x++;
	$lagervare[$x]=$r1['kodenr'];
	$batchvare[$x]=$r1['box9'];
}
	
$x=0;
$q2=db_select("select * from varer order by varenr",__FILE__ . " linje " . __LINE__);
while ($r2=db_fetch_array($q2)){
	if (in_array($r2['gruppe'], $lagervare)) {
		$x++;
		$vare_id[$x]=$r2['id'];
		$varenr[$x]=$r2['varenr'];
		$enhed[$x]=$r2['enhed'];
		$beholdning[$x]=$r2['beholdning'];
		$beskrivelse[$x]=$r2['beskrivelse'];
		$salgspris[$x]=$r2['salgspris'];
	}
}
$vareantal=$x;

print "<table border=0 cellpadding=0 cellspacing=0 width=100%><tbody>";
print "<tr><td colspan=8><table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
print "<tr>";
print "<td width=10% $top_bund><a href=$returside accesskey=L>Luk</a></td>";
print "<td width=80% $top_bund align=center>Lagerstatus</td>";
print "<td width=10% $top_bund  align=right><br></td>";
print "</tr></td></tbody></table>\n";
print "<form action=lagerstatus.php method=post>";
print "<tr><td colspan=7 align=center>Dato:&nbsp;<input type=text name=dato value=$dato size=10></td>";
print "<td align=right><input type=submit value=OK></form></td></tr>";
print "<tr><td colspan=8><hr></td></tr>";
print "<tr><td width=8%>Varenr.</td><td width=5%>Enhed</td><td width=56%>Beskrivelse</td>
	<td align=right width=5%><span title='Antal enheder k&oslash;bt f&oslash;r den $dato'>K&oslash;bt</span></td>
	<td align=right width=5%><span title='Antal enheder solgt f&oslash;r den $dato'>Solgt</span></td>
	<td align=right width=5%><span title='Lagerbeholdning pr. $dato'>Antal</span></td>
	<td align=right width=8%><span title='K&oslash;bsv&aelig;rdi af lagerbeholdning'>K&oslash;bspris</span></td>
	<td align=right width=8%><span title='Salgsv&aelig;rdi af lagerbeholdning'>Salgspris</span></td></tr>";

for($x=1; $x<=$vareantal; $x++) {
	$batch_k_antal[$x]=0;$batch_t_antal[$x]=0;$batch_pris[$x]=0;$batch_s_antal[$x]=0;
	$q1=db_select("select * from batch_kob where vare_id=$vare_id[$x] and kobsdate <= '$date';",__FILE__ . " linje " . __LINE__);
	while ($r1=db_fetch_array($q1)){
		$batch_k_antal[$x]=$batch_k_antal[$x]+$r1['antal'];
		$batch_t_antal[$x]=$batch_t_antal[$x]+$r1['antal'];
		$batch_pris[$x]=$batch_pris[$x]+($r1['pris']*$r1['antal']);
		if (isset($batchvare[$x]) && $batchvare[$x]) {
			$q2=db_select("select * from batch_salg where batch_kob_id=$r1[id] and salgsdate <= '$date';",__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)){
				$batch_s_antal[$x]=$batch_s_antal[$x]+$r2['antal'];
				$batch_t_antal[$x]=$batch_t_antal[$x]-$r2['antal'];
				$batch_pris[$x]=$batch_pris[$x]-($r1['pris']*$r2['antal']);
			}
		}	
#	db_modify("update varer set beholdning = '$batch_t_antal[$x]' where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);  
	}
	if (!isset($batchvare[$x])) $batchvare[$x]=NULL;
	if (!$batchvare[$x]) {
		$tmp=$batch_t_antal[$x];
		$q2=db_select("select * from batch_salg where  vare_id=$vare_id[$x] and salgsdate <= '$date';",__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)){
			$batch_s_antal[$x]=$batch_s_antal[$x]+$r2['antal'];
			$batch_t_antal[$x]=$batch_t_antal[$x]-$r2['antal'];
#			$batch_pris[$x]=$batch_pris[$x]-($r1['pris']*$r2['antal']);
		}
		if ($tmp*$batch_t_antal[$x]!=0) $batch_pris[$x]=$batch_pris[$x]/$tmp*$batch_t_antal[$x];
		else $batch_pris[$x]=0;
	}
	if (($batch_k_antal[$x] != 0)||($batch_s_antal[$x] != 0)) {
		if ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		else {$linjebg=$bgcolor; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($popup) print "<td onClick=\"javascript:varespor=window.open('varespor.php?vare_id=$vare_id[$x]','varespor','$jsvars')\" onMouseOver=\"this.style.cursor = 'pointer'\"><u>$varenr[$x]&nbsp;</u></td>";
		else print "<td><a href=varespor.php?vare_id=$vare_id[$x]>$varenr[$x]&nbsp;</td>";
		print	"<td>$enhed[$x]&nbsp;</td><td>$beskrivelse[$x]</td>
		<td align=right>$batch_k_antal[$x]&nbsp;</td><td align=right>$batch_s_antal[$x]&nbsp;</td>
		<td align=right>$batch_t_antal[$x]&nbsp;</td>
		<td align=right>".dkdecimal($batch_pris[$x])."</td>
		<td align=right>&nbsp;".dkdecimal($salgspris[$x]*$batch_t_antal[$x])."</td></tr>";
		$lagervalue=$lagervalue+$batch_pris[$x]; $salgsvalue=$salgsvalue+($salgspris[$x]*$batch_t_antal[$x]);
	}
}
print "<tr><td colspan=8><hr></td></tr>";
print "<tr><td colspan=2></td><td>Samlet lagerv&aelig;rdi pr. $dato</td><td align=right></td><td align=right></td>
	<td align=right></td><td align=right>".dkdecimal($lagervalue)."</td>
	<td align=right>".dkdecimal($salgsvalue)."</td></tr>";
?>
</tbody></table>
</body></html>
