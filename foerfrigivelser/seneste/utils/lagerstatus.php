<?php
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
$q1= db_select("select kodenr from grupper where box8 = 'on'");
while ($r1=db_fetch_array($q1)) {
	$x++;
	$lagervare[$x]=$r1['kodenr'];
}
	
$x=0;
$q2=db_select("select * from varer order by varenr");
while ($r2=db_fetch_array($q2)){
	if (in_array($r2['gruppe'], $lagervare)) {
		$x++;
		$vare_id[$x]=$r2['id'];
		$varenr[$x]=$r2['varenr'];
		$beholdning[$x]=$r2['beholdning'];
		$beskrivelse[$x]=$r2['beskrivelse'];
		$salgspris[$x]=$r2['salgspris'];
	}
}
$vareantal=$x;

print "<table border=0 cellpadding=0 cellspacing=0 width=100%><tbody>";
print "<tr><td colspan=7><table width=100% align=center border=0 cellspacing=0 cellpadding=0><tbody>";
print "<tr>";
print "<td width=25% bgcolor=$bgcolor2>$font<small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
print "<td width=50% bgcolor=$bgcolor2 align=center>$font<small>Lagerstatus</small></td>";
print "<td width=25% bgcolor=$bgcolor2  align=right>$font<small></small></td>";
print "</tr></td></tbody></table>\n";
print "<form action=lagerstatus.php method=post>";
print "<tr><td colspan=6 align=center><small>Dato:&nbsp;<input type=text name=dato value=$dato size=10></small></td>";
print "<td align=right><input type=submit value=OK></form></td></tr>";
print "<tr><td colspan=7><hr></td></tr>";
print "<tr><td><small>Varenr</small></td><td><small>Beskrivelse</small></td><td align=right><small>Købt</small></td>
	<td align=right><small>Solgt</small></td><td align=right><small>Antal</small></td><td align=right><small>Købspris</small></td>
	<td align=right><small>Salgspris</small></td></tr>";

for($x=1; $x<=$vareantal; $x++) {
	$q1=db_select("select * from batch_kob where vare_id=$vare_id[$x] and kobsdate <= '$date';");
	while ($r1=db_fetch_array($q1)){
		$batch_k_antal[$x]=$batch_k_antal[$x]+$r1[antal];
		$batch_t_antal[$x]=$batch_t_antal[$x]+$r1[antal];
		$batch_pris[$x]=$batch_pris[$x]+($r1[pris]*$r1[antal]);
		$q2=db_select("select * from batch_salg where batch_kob_id=$r1[id] and salgsdate <= '$date';");
		while ($r2=db_fetch_array($q2)){
			$batch_s_antal[$x]=$batch_s_antal[$x]+$r2[antal];
			$batch_t_antal[$x]=$batch_t_antal[$x]-$r2[antal];
			$batch_pris[$x]=$batch_pris[$x]-($r1[pris]*$r2[antal]);
		}
#	db_modify("update varer set beholdning = '$batch_t_antal[$x]' where id='$vare_id[$x]'");  
	}
	if (($beholdning[$x] != 0)||($batch_k_antal[$x] != 0)||($batch_s_antal[$x] != 0)) {
		if ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		else {$linjebg=$bgcolor; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><small>$varenr[$x]</small></td><td><small>$beskrivelse[$x]</small></td>
			<td align=right><small>$batch_k_antal[$x]</small></td><td align=right><small>$batch_s_antal[$x]</small></td>
			<td align=right><small>$batch_t_antal[$x]($beholdning[$x])</small></td>
			<td align=right><small>".dkdecimal($batch_pris[$x])."</small></td>
			<td align=right><small>".dkdecimal($salgspris[$x]*$batch_t_antal[$x])."</small></td></tr>";
		$lagervalue=$lagervalue+$batch_pris[$x]; $salgsvalue=$salgsvalue+($salgspris[$x]*$batch_t_antal[$x]);
	}
}
print "<tr><td colspan=7><hr></td></tr>";
print "<tr><td><small></td><td><small>Samlet lagerværdi pr. $dato</small></td><td align=right></td><td align=right></td>
	<td align=right></td><td align=right><small>".dkdecimal($lagervalue)."</small></td>
	<td align=right><small>".dkdecimal($salgsvalue)."</small></td></tr>";
?>
</tbody></table>
</body></html>
