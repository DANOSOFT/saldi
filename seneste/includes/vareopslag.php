<?php
//----------------- includes/vareopslag.php -----ver 3.2.6---- 2011.11.29 ----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2003-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

function vareopslag ($sort, $fokus, $id, $vis_kost, $ref, $find, $retur) {

	global $bgcolor;
	global $bgcolor5;
 
	
	if ($find) {
		$find=str_replace("*","%",$find);
		$find=" and $fokus like $find and id!='$id'";
	} else $find=" and id!='$id'";
	
#	sidehoved($id, "$retur", "../lager/$retur", $fokus, "Kundeordre $id - vareopslag");

#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print "<td><a href=\"$retur?fokus=$fokus&id=$id\">Luk</a></td>";
	if ($vis_kost) {print "<td colspan=7 align=center><a href=$retur?sort=varenr&funktion=vareopslag&x=$x&fokus=$fokus&id=$id&find=$find>Udelad kostpriser</a></td></tr>";}
	else {print "<td colspan=3 align=center><a href=$retur?sort=varenr&funktion=vareopslag&x=$x&fokus=$fokus&id=$id&vis_kost=on&find=$find>Vis kostpriser</a></td></tr>";}
	print"<td><b><a href=$retur?sort=varenr&funktion=vareopslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost&find=$find>Varenr</a></b></td>";
	print"<td><b> Enhed</b></td>";
	print"<td><b><a href=$retur?sort=beskrivelse&funktion=vareopslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost&find=$find>Beskrivelse</a></b></td>";
	print"<td align=right><b><a href=$retur?sort=salgspris&funktion=vareopslag&x=$x&fokus=$fokus&id=$id&find=$find>Salgspris</a></b></td>";
	if ($vis_kost) {print"<td align=right><b> Kostpris</b></td>";}
	print"<td align=right><b><a href=$retur?sort=beholdning&funktion=vareopslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost&find=$find>Beh.</a></b></td>";
	print"<td><br></td>";
#	print"<td><br></td><td><b>Kunde</b></td>";
	print" </tr>\n";
	print "<tr><td colspan=\"6\"><hr></td></tr>";
	if ($ref){
		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'"))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'"))) {$lager=$row['kodenr'];}
		}
	}
	$lager=$lager*1;
	if (!$sort) {$sort = varenr;}
	if ($find) $query = db_select("select * from varer where lukket != '1' $find order by $sort");
	else $query = db_select("select * from varer where lukket != '1' order by $sort");
	while ($row = db_fetch_array($query))
	{
		$query2 = db_select("select box8 from grupper where art='VG' and kodenr='$row[gruppe]'");
		$row2 =db_fetch_array($query2);
		if (($row2[box8]=='on')||($row[samlevare]=='on')){
			if (($row[beholdning]!='0')and(!$row[beholdning])){db_modify("update varer set beholdning='0' where id=$row[id]");}
		}
		elseif ($row[beholdning]){db_modify("update varer set beholdning='0' where id=$row[id]");}

		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=\"$retur?vare_id=$row[id]&fokus=$fokus&id=$id\">$row[varenr]</a></td>";	
		print "<td>$row[enhed]<br></td>";
		print "<td>$row[beskrivelse]<br></td>";
		$salgspris=dkdecimal($row[salgspris]);
		print "<td align=right>$salgspris<br></td>";
		if ($vis_kost=='on') {
			$query2 = db_select("select kostpris from vare_lev where vare_id = $row[id] order by posnr");
			$row2 = db_fetch_array($query2);
			$kostpris=dkdecimal($row2[kostpris]);
			print "<td align=right>$kostpris<br></td>";
		}
		$reserveret=0;
#		$linjetext="<span title= 'Der er $y i tilbud og $z i ordre '>";
		if ($lager>=1){
			$q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest>0 and lager=$lager");
			while ($r2 = db_fetch_array($q2)) {
				$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]");
				while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3[antal];}
			}
			$linjetext="<span title= 'Reserveret: $reserveret'>";
			if ($r2= db_fetch_array(db_select("select beholdning from lagerstatus where vare_id=$row[id] and lager=$lager"))) {
				print "<td align=right>$linjetext $r2[beholdning]</span></td>";
			} 
		}
		else { 
			$q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest > 0");
			while ($r2 = db_fetch_array($q2)) {
				$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]");
				while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3[antal];}
			}
			$linjetext="<span title= 'Reserveret: $reserveret'>";
			print "<td align=right>$linjetext $row[beholdning]</span></td>";
		}
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
?>