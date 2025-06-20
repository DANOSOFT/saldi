<?php
// -------------------------------------------------------lager/lagerstatus.php----lap 1.1.0------
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
	// Copyright (c) 2006-2007 DANOSOFT ApS
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();

$title="Lagerstatus";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/dkdecimal.php");
include("../includes/usdate.php");
include("../includes/dkdato.php");

if ($_POST['dato']) {
	$dato=$_POST['dato'];

}
else $dato=date("d-m-Y");


$date=usdate($dato);
$dato=dkdato($date);

$x=0;
$q1= db_select("select kodenr, box9 from grupper where box8 = 'on'");
while ($r1=db_fetch_array($q1)) {
	$x++;
	$lagervare[$x]=$r1['kodenr'];
	$batchvare[$x]=$r1['box9'];
}
	
$x=0;
$q2=db_select("select * from varer order by varenr");
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
print "<td width=10% $top_bund>$font<small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
print "<td width=80% $top_bund align=center>$font<small>Lagerstatus</small></td>";
print "<td width=10% $top_bund  align=right>$font<small><br></small></td>";
print "</tr></td></tbody></table>\n";
print "<form action=lagerstatus.php method=post>";
print "<tr><td colspan=7 align=center>$font<small>Dato:&nbsp;<input type=text name=dato value=$dato size=10></small></td>";
print "<td align=right><input type=submit value=OK></form></td></tr>";
print "<tr><td colspan=8><hr></td></tr>";
print "<tr><td width=8%>$font<small>Varenr</small></td><td width=5%>$font<small>Enhed</small></td><td width=56%>$font<small>Beskrivelse</small></td>
	<td align=right width=5%>$font<small><span title='Antal enheder k&oslash;bt f&oslash;r den $dato'>K&oslash;bt</span></small></td>
	<td align=right width=5%>$font<small><span title='Antal enheder solgt f&oslash;r den $dato'>Solgt</span></small></td>
	<td align=right width=5%>$font<small><span title='Lagerbeholdning pr. $dato'>Antal</span></small></td>
	<td align=right width=8%>$font<small><span title='K&oslash;bsv&aelig;rdi af lagerbeholdning'>K&oslash;bspris</span></small></td>
	<td align=right width=8%>$font<small><span title='Salgsv&aelig;rdi af lagerbeholdning'>Salgspris</span></small></td></tr>";

for($x=1; $x<=$vareantal; $x++) {
	$q1=db_select("select * from batch_kob where vare_id=$vare_id[$x] and kobsdate <= '$date';");
	while ($r1=db_fetch_array($q1)){
		$batch_k_antal[$x]=$batch_k_antal[$x]+$r1[antal];
		$batch_t_antal[$x]=$batch_t_antal[$x]+$r1[antal];
		$batch_pris[$x]=$batch_pris[$x]+($r1[pris]*$r1[antal]);
		if ($batchvare[$x]) {
			$q2=db_select("select * from batch_salg where batch_kob_id=$r1[id] and salgsdate <= '$date';");
			while ($r2=db_fetch_array($q2)){
				$batch_s_antal[$x]=$batch_s_antal[$x]+$r2[antal];
				$batch_t_antal[$x]=$batch_t_antal[$x]-$r2[antal];
				$batch_pris[$x]=$batch_pris[$x]-($r1[pris]*$r2[antal]);
			}
		}	
#	db_modify("update varer set beholdning = '$batch_t_antal[$x]' where id='$vare_id[$x]'");  
	}
	if (!$batchvare[$x]) {
		$tmp=$batch_t_antal[$x];
		$q2=db_select("select * from batch_salg where  vare_id=$vare_id[$x] and salgsdate <= '$date';");
		while ($r2=db_fetch_array($q2)){
			$batch_s_antal[$x]=$batch_s_antal[$x]+$r2[antal];
			$batch_t_antal[$x]=$batch_t_antal[$x]-$r2[antal];
#			$batch_pris[$x]=$batch_pris[$x]-($r1[pris]*$r2[antal]);
		}
		if ($tmp*$batch_t_antal[$x]!=0) $batch_pris[$x]=$batch_pris[$x]/$tmp*$batch_t_antal[$x];
		else $batch_pris[$x]=0;
	}
	if (($batch_k_antal[$x] != 0)||($batch_s_antal[$x] != 0)) {
		if ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		else {$linjebg=$bgcolor; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td onClick=\"javascript:varespor=window.open('varespor.php?vare_id=$vare_id[$x]','varespor','width=800,height=400,scrollbars=1,resizable=1')\" onMouseOver=\"this.style.cursor = 'pointer'\"><u>$font<small>$varenr[$x]&nbsp;</small></u></td>
			<td>$font<small>$enhed[$x]&nbsp;</small></td><td>$font<small>$beskrivelse[$x]</small></td>
		<td align=right>$font<small>$batch_k_antal[$x]&nbsp;</small></td><td align=right>$font<small>$batch_s_antal[$x]&nbsp;</small></td>
		<td align=right>$font<small>$batch_t_antal[$x]&nbsp;</small></td>
		<td align=right>$font<small>".dkdecimal($batch_pris[$x])."</small></td>
		<td align=right>$font<small>&nbsp;".dkdecimal($salgspris[$x]*$batch_t_antal[$x])."</small></td></tr>";
		$lagervalue=$lagervalue+$batch_pris[$x]; $salgsvalue=$salgsvalue+($salgspris[$x]*$batch_t_antal[$x]);
	}
}
print "<tr><td colspan=8><hr></td></tr>";
print "<tr><td colspan=2>$font<small></td><td>$font<small>Samlet lagerværdi pr. $dato</small></td><td align=right></td><td align=right></td>
	<td align=right></td><td align=right>$font<small>".dkdecimal($lagervalue)."</small></td>
	<td align=right>$font<small>".dkdecimal($salgsvalue)."</small></td></tr>";
?>
</tbody></table>
</body></html>
