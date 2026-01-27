<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/orderIncludes/closedOrder.php ---patch 4.1.1 --2025-11-13--
//                           LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20221106 PHR - Various changes to fit php8 / MySQLi		
// 20220629 MSC - Implementing new design
// 20231219 MSC - Copy pasted new design into code
// 20251113 PHR - Changed text 1001(Kredit) to 2014(Kreditér)

print "<input type=\"hidden\" name=\"konto_id\" value=$konto_id>";
print "<input type=\"hidden\" name=\"kontonr\" value=\"$kontonr\">";
print "<input type=\"hidden\" name=\"firmanavn\" value=\"$firmanavn\">";
print "<input type=\"hidden\" name=\"addr1\" value=\"$addr1\">";
print "<input type=\"hidden\" name=\"addr2\" value=\"$addr2\">";
print "<input type=\"hidden\" name=\"postnr\" value=\"$postnr\">";
print "<input type=\"hidden\" name=\"bynavn\" value=\"$bynavn\">";
print "<input type=\"hidden\" name=\"land\" value=\"$land\">";
print "<input type=\"hidden\" name=\"kontakt\" value=\"$kontakt\">";
print "<input type=\"hidden\" name=\"lev_navn\" value=\"$lev_navn\">";
print "<input type=\"hidden\" name=\"lev_addr1\" value=\"$lev_addr1\">";
print "<input type=\"hidden\" name=\"lev_addr2\" value=\"$lev_addr2\">";
print "<input type=\"hidden\" name=\"lev_postnr\" value=\"$lev_postnr\">";
print "<input type=\"hidden\" name=\"lev_bynavn\" value=\"$lev_bynavn\">";
print "<input type=\"hidden\" name=\"lev_kontakt\" value=\"$lev_kontakt\">";
print "<input type=\"hidden\" name=\"levdato\" value=\"$levdato\">";
print "<input type=\"hidden\" name=\"cvrnr\" value=\"$cvrnr\">";
print "<input type=\"hidden\" name=\"betalingsbet\" value=\"$betalingsbet\">";
print "<input type=\"hidden\" name=\"betalingsdage\" value=\"$betalingsdage\">";
print "<input type=\"hidden\" name=\"momssats\" value=\"" . dkdecimal($momssats) . "\">";
print "<input type=\"hidden\" name=\"ref\" value=\"$ref\">";
print "<input type=\"hidden\" name=\"fakturanr\" value=\"$fakturanr\">";
print "<input type=\"hidden\" name=\"modtagelse\" value=\"$modtagelse\">";
print "<input type=\"hidden\" name=\"lev_adr\" value=\"$lev_adr\">";
print "<input type=\"hidden\" name=\"valuta\" value=\"$valuta\">";

if ($menu == 'T') {
	$border = "border='0'";
} else {
	$border = "border='1' bordercolor='#FFF'";
}

print "<table cellpadding='0' cellspacing='0' $border valign = 'top' class='dataTableForm' width='100%'><tbody>";
#	$ordre_id=$id;
print "<tr><td width='25%'><table cellpadding=0 cellspacing=0 border=0 width=100%>";
print "<tr><td width=100><b>" . findtekst(284, $sprog_id) . "</td><td width=100>$kontonr</td></tr>\n"; #2021.05.14
print "<tr><td><b>" . findtekst(360, $sprog_id) . "</td><td>$firmanavn</td></tr>\n";
print "<tr><td><b>" . findtekst(140, $sprog_id) . "</td><td>$addr1</td></tr>\n";
print "<tr><td></td><td>$addr2</td></tr>\n";
print "<tr><td><b>" . findtekst(549, $sprog_id) . "</td><td>$postnr $bynavn</td></tr>\n";
print "<tr><td><b>" . findtekst(364, $sprog_id) . "</td><td>$land</td></tr>\n";
print "<tr><td><b>" . findtekst(398, $sprog_id) . "</td><td>$kontakt</td></tr>\n";
print "</tbody></table></td>";
print "<td width='50%'><table cellpadding=0 cellspacing=0 border=0 width='100%'>";
print "<tr><td width='100px'><b>" . findtekst(881, $sprog_id) . "</td><td width='100px'>$ordredato</td><td width='10px'></td>\n";
print "<td><b>" . findtekst(550, $sprog_id) . "</td><td>$levdato</td></tr>\n";
print "<tr><td><b>" . findtekst(376, $sprog_id) . "</td><td>$cvrnr</td><td></td>\n";
print "<td><b>Bilag</b></td>";
$qtxt = "select id from documents where source = 'creditorOrder' and source_id = '$id'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $clip = 'paper.png';
else $clip = 'clip.png';
print "<td align = 'center'>";
print "<a href='../includes/documents.php?source=creditorOrder&&ny=ja&sourceId=$id'>";
print "<img src='../ikoner/$clip' style='width:20px;height:20px;'></a></td>";
print "</tr>";
print "<tr><td><b>" . findtekst(935, $sprog_id) . "</td><td>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td><td></td>";
print "<td><b>" . findtekst(815, $sprog_id) . "</td><td>$ref</td></tr>\n";
print "<tr><td><b>" . findtekst(828, $sprog_id) . "</td><td>$fakturanr</td><td></td>\n";
print "<td><b>" . findtekst(551, $sprog_id) . "</td><td>$modtagelse</td></tr>\n";
$tmp = dkdecimal($valutakurs, 2);
print "<tr>";
if ($valuta) print "<td><b>" . findtekst(552, $sprog_id) . "</td><td>$valuta / $tmp</td><td></td>\n";
if ($projekt[0]) print "<td><b>" . findtekst(553, $sprog_id) . "</td><td>$projekt[0]</td>\n";
print "</tr></tbody></table></td>";
print "<td width='25%'><table cellpadding=0 cellspacing=0 border = 0 width=240>";
print "<tr><td><b>" . findtekst(554, $sprog_id) . "</td></tr>\n";
print "<tr><td>" . findtekst(360, $sprog_id) . "</td><td colspan=2>$lev_navn</td></tr>\n";
print "<tr><td>" . findtekst(361, $sprog_id) . "</td><td colspan=2>$lev_addr1</td></tr>\n";
print "<tr><td></td><td colspan=2>$lev_addr2</td></tr>\n";
print "<tr><td>" . findtekst(549, $sprog_id) . "</td><td>$lev_postnr $lev_bynavn</td></tr>\n";
print "<tr><td>" . findtekst(398, $sprog_id) . "</td><td colspan=2>$lev_kontakt</td></tr>\n";
print "<tr><td>$lev_adr</td></tr>\n";
print "</td></tr></tbody></table></td>";
print "</td></tr><tr><td align='center' colspan='3'>";
print "<table cellpadding='0' cellspacing='0' $border width='100%'><tbody>";
print "<tr><td colspan=7></td></tr><tr>";
#	print "<td align=center><b>pos</td><td align=center><b>varenr</td><td align=center><b>ant.</td><td align=center><b>enhed</td><td align=center><b>beskrivelse</td><td align=center><b>".findtekst(915,$sprog_id)."</td><td align=center><b>%</td><td align=center><b>ialt</td><td align=center><b>solgt</td>";
print "<td align=center title='" . findtekst(1502, $sprog_id) . "'><b>Pos.</td><td align=center><b>" . findtekst(917, $sprog_id) . ".</td><td align=center><b>" . findtekst(916, $sprog_id) . "</td><td align=center><b>" . findtekst(945, $sprog_id) . "</td><td align=center><b>" . findtekst(914, $sprog_id) . "</td><td align=center><b>" . findtekst(915, $sprog_id) . "</td><td align=center title='" . findtekst(1503, $sprog_id) . "'><b>%</td><td align=center><b>" . findtekst(947, $sprog_id) . "</td>"; #20210716
if (db_fetch_array(db_select("select * from grupper where art = 'PRJ' order by kodenr", __FILE__ . " linje " . __LINE__))) {
	$vis_projekt = '1';
} else $vis_projekt = '0';
if ($vis_projekt && !$projekt[0]) print "<td align=center title='" . findtekst(1504, $sprog_id) . "'><b>proj.</b></td>";
else print "<td></td>";
if (!$hurtigfakt) print "<td align=\"center\"><b>solgt</b></td>";
print "</tr>\n";
$x = 0;
if (!$ordre_id) $ordre_id = 0;
$qtxt = "select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr";
$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	if ($row['posnr'] > 0) {
		$x++;
		$linje_id[$x] = $row['id'];
		$vare_id[$x] = $row['vare_id'];
		$posnr[$x] = $row['posnr'];
		$varenr[$x] = $row['varenr'];
		$lev_varenr[$x] = $row['lev_varenr'];
		$beskrivelse[$x] = $row['beskrivelse'];
		$enhed[$x] = $row['enhed'];
		$pris[$x] = $row['pris'];
		$rabat[$x] = $row['rabat'];
		$antal[$x] = $row['antal'];
		$serienr[$x] = $row['serienr'];
		$momsfri[$x] = $row['momsfri'];
		$varemomssats[$x] = $row['momssats']; #20141106
		$projekt[$x] = $row['projekt'];
		$variant[$x] = $row['variant_id'];
		$omvbet[$x] = $row['omvbet'];
		if ($vare_id[$x]) {
			$r = db_fetch_array(db_select("select gruppe from varer where id = $vare_id[$x]", __FILE__ . " linje " . __LINE__));
			$r = db_fetch_array(db_select("select box6,box9 from grupper where kodenr='$r[gruppe]' and art='VG'", __FILE__ . " linje " . __LINE__));
			$box9[$x] = trim($r['box9']);
			(trim($r['box6'])) ? $omvare[$x] = 'on' : $omvare[$x] = '';
		}
	}
}
$linjeantal = $x;
print "<input type=\"hidden\" name=\"linjeantal\" value=\"$x\">";
$totalrest = 0;
$sum = 0;
for ($x = 1; $x <= $linjeantal; $x++) {
	if (!$vare_id[$x] && $varenr[$x]) {
		$query = db_select("select id from varer where varenr = '$varenr[$x]' or stregkode = '$varenr[$x]'", __FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $vare_id[$x] = $row['id'];
	}
	if (($varenr[$x]) && ($vare_id[$x])) {
		$rest[$x] = 0;
		$qtxt = "select id, rest from batch_kob where linje_id = '$linje_id[$x]' ";
		$qtxt .= "and ordre_id = '$ordre_id' and vare_id = '$vare_id[$x]'";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) $rest[$x] += $r['rest'];
		$solgt[$x] = $antal[$x] - $rest[$x];
		$totalrest = $totalrest + $rest[$x];

		$ialt = ($pris[$x] - ($pris[$x] / 100 * $rabat[$x])) * $antal[$x];
		$ialt = afrund($ialt, 2);
		$sum = $sum + $ialt;
		if ($momsfri[$x] != 'on' && !$omvbet[$x]) $momssum += $ialt;
		#		$ialt=dkdecimal($ialt);
		$dkpris = dkdecimal($pris[$x], 2);
		$dkrabat = dkdecimal($rabat[$x], 2);
		if ($antal[$x]) {
			if ($art == 'KK') $dkantal[$x] = dkdecimal($antal[$x] * -1, 2);
			else $dkantal[$x] = dkdecimal($antal[$x], 2);
			if (substr($dkantal[$x], -1) == '0') $dkantal[$x] = substr($dkantal[$x], 0, -1);
			if (substr($dkantal[$x], -1) == '0') $dkantal[$x] = substr($dkantal[$x], 0, -2);
		}
	} else {
		$antal[$x] = '';
		$dkpris = '';
		$dkrabat = '';
		$ialt = '';
	}
	print "<tr>";
	print "<input type=\"hidden\" name=posn$x value=$posnr[$x]><td align=right>$posnr[$x]</td>";
	print "<input type=\"hidden\" name=vare$x value=\"$varenr[$x]\"><td align=right>$varenr[$x]</td>";
	print "<input type=\"hidden\" name=anta$x value=$dkantal[$x]><td align=right>$dkantal[$x]</td>";
	print "<td align=right>$enhed[$x]</td>";
	print "<input type=\"hidden\" name=beskrivelse$x value=\"$beskrivelse[$x]\"><td>$beskrivelse[$x]</td>";
	print "<input type=\"hidden\" name=pris$x value=$dkpris><td align=right>$dkpris</td>";
	print "<input type=\"hidden\" name=raba$x value=$dkrabat><td align=right>$dkrabat</td>";
	print "<input type=\"hidden\" name=linje_id[$x] value=$linje_id[$x]>";
	print "<input type=\"hidden\" name=serienr[$x] value=$serienr[$x]>";
	print "<input type=\"hidden\" name=vare_id[$x] value=$vare_id[$x]>";
	print "<input type=\"hidden\" name=lev_varenr[$x] value=\"$lev_varenr[$x]\">";
	print "<input type=\"hidden\" name=momsfri[$x] value=\"$momsfri[$x]\">";
	print "<input type=\"hidden\" name=omvbet[$x] value=\"$omvbet[$x]\">"; #20150415
	print "<input type=\"hidden\" name=varemomssats[$x] value=\"$varemomssats[$x]\">"; #20141106
	if (($ialt) && ($art == 'KK')) {
		$ialt = $ialt * -1;
	}
	print "<td align=right>" . dkdecimal($ialt, 2) . "</td>";
	print "<input type=\"hidden\" name=projekt[$x] value=\"$projekt[$x]\">";
	if ($vis_projekt && !$projekt[0] && $projekt[$x]) {
		$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'PROJ' and kodenr='$projekt[$x]'", __FILE__ . " linje " . __LINE__));
		print "<td align=right title='$r[projekt]'>$projekt[$x]</td>";
	}
	if ($labelprint) {
		if ($varenr[$x]) {
			$txt = "<a href=\"../lager/labelprint.php?id=$vare_id[$x]&beskrivelse=" . urlencode($beskrivelse[$x]);
			$txt .= "&stregkode=" . urlencode($varenr[$x]) . "&pris=$salgspris[$x]&enhed=$enhed[$x]\" target=\"blank\">";
			$txt .= "<img src=\"../ikoner/print.png\" style=\"border: 0px solid;\"></a>";
		} else $txt = NULL;
		print "<td>$txt</td>";
	}
	if ($box9[$x] == 'on') {
		if ($art == 'KK') $solgt[$x] = $solgt[$x] * -1;
		if ($serienr[$x]) print "<td onClick=\"serienummer($linje_id[$x])\" align=right><u>$solgt[$x]</u></td>";
		else print "<td align=right>$solgt[$x]</td>";
	} elseif ($serienr[$x])  print "<td onClick=\"serienummer($linje_id[$x])\" align=right><u>Snr</u></td>";
	else print "<td align=right><br></td>";
	print "</tr>\n";
}
if ($art == 'KK') {
	$sum = $sum * -1;
	$momssum = $momssum * -1;
}
$moms = $momssum / 100 * $momssats;
$moms = afrund($moms, 3);
$ialt = dkdecimal($sum + $moms, 2);
$sum = dkdecimal($sum, 2);
$moms = dkdecimal($moms, 2);
print "<tr><td colspan=8></td></tr>\n";
print "<tr><td colspan=8><table $border cellspacing='0' cellpadding='0' width='100%'><tbody>";
print "<tr>";
print "<td align=center>" . findtekst(887, $sprog_id) . "</td><td align=center>$sum</td>";
print "<td align=center>Moms</td><td align=center>$moms</td>";
print "<td align=center>I alt</td><td align=right>$ialt</td>";
print "</tbody></table></td></tr>\n";
print "<tr><td align=center colspan=9>";
print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>";
if ($art != 'KK') {
	print "<td align=center><span title=\"" . findtekst(1459, $sprog_id) . "\">";
	print "<input type='submit' style='width:120px; border-radius:4px;' value=\"" . findtekst(1493, $sprog_id) . "\" ";
	print "name='copy' onclick=\"javascript:docChange = false;\"></span></td>";
	print "<td align=center><span title=\"" . findtekst(1505, $sprog_id) . "\">";
	print "<input type='submit' style='width:120px; border-radius:4px;' value=\"" . findtekst(2014, $sprog_id) . "\" ";
	print "name = 'credit' onclick=\"javascript:docChange = false;\"></span></td>";
	print "<td align=center><span title=\"" . findtekst(1506, $sprog_id) . "\">";
	print "<input type='submit' style='width:120px; border-radius:4px;' value='Udskriv' name='print' ";
	print "onclick=\"javascript:docChange = false;\"></span></td>";
}
