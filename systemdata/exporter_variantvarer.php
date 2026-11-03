<?php
// ----/systemdata/exporter_variantvarer.php-----ver 5.1.0 ----2026-11-02--
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20130412 Rettet i formatet
// 20140516 Grundet timeout ved mange varer eksporteres nu max 1000 hvorefter rutinen genstarter der hvor den er nået til. Søg $start,$slut & $z; 
// 20140516 Indsat dkdecimal ved udskrivning af kost',salgs', og vejl.pris. 
// 20140526 Rettet $varianter_id til $varianttype_id 
// 20210714 LOE - Translated some text.
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260916 SS Declared $permission_key (roles & permissions, phase 3)
// 20261008 SS The page on the new page head (page_bar) instead of the blue bar: title, Tilbage only outside the shell.
// 20261031 CL/ASR Tilbage goes to Indstillinger > Import & eksport (settingsSection.php?s=import_export.data), not via the old diverse.php?sektion=div_io redirect.
// 20261101 CL/ASR Tilbage is the shared settings button above the title (settings_back_button(), css/saldi-kit.css), as on every settings page, instead of page_bar's link.
//                 It goes to the settings page the user came from, or the hub; Alt+L kept.
// 20261102 CL/ASR Tilbage goes to the hub, a fixed target, so it never loops between settings pages; settings_back_button() adds Alt+L itself.
//                 The kit is linked as ?v=20261102.
// 20261102 CL/ASR Fix of a fatal error that was already on master: on a ledger without variants (or variant types) the export died with count(null) (PHP 8), before the page was printed.
//                 The two lists start empty.
// 20261102 CL/ASR The standard settings layout (settings_layout_open(), includes/settings/layout.php; owner, 2 Nov 2026: every page reached from Indstillinger keeps the left menu).
//                 The left menu of Finans with Import & eksport marked, Tilbage above the title back to Import & eksport, the breadcrumb Indstillinger / Finans / Import & eksport / this page.
//                 The page's own Tilbage and page_bar title are gone; its form and steps are unchanged.

@session_start();
$s_id=session_id();
$title="Eksporter variantvarer";
$css="../css/standard.css";

include("../includes/connect.php");
$permission_key = 'settings.import_export';
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
$returside="settingsSection.php?s=import_export.data";

$filnavn="../temp/variantvarer.csv";
(isset($_GET['start']))?$start=$_GET['start']:$start=1;
$slut=$start+999;
$x=0;

$r=db_fetch_array(db_select("SELECT relfilenode FROM pg_class WHERE relname = 'variant_varer'",__FILE__ . " linje " . __LINE__)) ;
$relfilenode=$r['relfilenode']*1;
$r=db_fetch_array(db_select("SELECT * FROM pg_attribute WHERE attrelid= '$relfilenode' and attname = 'variant_salgspris'",__FILE__ . " linje " . __LINE__));
if ($r['attisdropped']!='f' || !$r['attname']) {
	db_modify("alter TABLE variant_varer ADD variant_salgspris numeric(15,3)",__FILE__ . " linje " . __LINE__);
}
$r=db_fetch_array(db_select("SELECT * FROM pg_attribute WHERE attrelid= '$relfilenode' and attname = 'variant_kostpris'",__FILE__ . " linje " . __LINE__));
if ($r['attisdropped']!='f' || !$r['attname']) {
	db_modify("alter TABLE variant_varer ADD variant_kostpris numeric(15,3)",__FILE__ . " linje " . __LINE__);
}
$r=db_fetch_array(db_select("SELECT * FROM pg_attribute WHERE attrelid= '$relfilenode' and attname = 'variant_vejlpris'",__FILE__ . " linje " . __LINE__));
if ($r['attisdropped']!='f' || !$r['attname']) {
	db_modify("alter TABLE variant_varer ADD variant_vejlpris numeric(15,3)",__FILE__ . " linje " . __LINE__);
}

$varianter_id=$varianter_beskrivelse=$varianter_shop_id=array(); // 20261102 CL/ASR count() below needs an array also when there are no variants
$q=db_select("select * from varianter",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$varianter_id[$x]=$r['id'];
	$varianter_beskrivelse[$x]=$r['beskrivelse'];
	$varianter_shop_id[$x]=$r['shop_id'];
	$x++;
}

$x=0;
$varianttyper_id=$varianttyper_beskrivelse=$varianttyper_shop_id=array();
$q=db_select("select * from variant_typer",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$varianttyper_id[$x]=$r['id'];
	$varianttyper_beskrivelse[$x]=$r['beskrivelse'];
	$varianttyper_shop_id[$x]=$r['shop_id'];
#echo "$varianttyper_id[$x] $varianttyper_beskrivelse[$x]<br>";
	$x++;
}
if ($start==1) $fp=fopen($filnavn,"w");
else $fp=fopen($filnavn,"a");
$overskrift="varenr".chr(9)."beskrivelse".chr(9)."stregkode".chr(9)."kostpris".chr(9)."salgspris".chr(9)."vejl.pris";
for ($x=0;$x<count($varianter_id);$x++) {
	$overskrift.=chr(9).$varianter_beskrivelse[$x];
}
if ($charset=="UTF-8") $overskrift=mb_convert_encoding($overskrift, 'ISO-8859-1', 'UTF-8');

if (fwrite($fp, "$overskrift\r\n")) {
	$z=0;
	$q=db_select("select varer.id,varer.varenr,varer.beskrivelse,varer.varenr_alias,variant_varer.variant_stregkode,variant_varer.variant_type,variant_varer.variant_salgspris,variant_varer.variant_kostpris,variant_varer.variant_vejlpris from varer,variant_varer where varer.id=variant_varer.vare_id order by varer.varenr,variant_varer.variant_stregkode",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$z++;
		if ($z>=$start && $z<=$slut) {
			$vare_id=$r['id'];
			$varenr=$r['varenr'];
			$beskrivelse=$r['beskrivelse'];
			$variant_stregkode=$r['variant_stregkode'];
			$varenr_alias=$r['varenr_alias'];
			$variant_type=explode(chr(9),$r['variant_type']);

#			$variant_lager=$r['lager'];
			$linje='"'.$varenr.'"'.chr(9).'"'.$beskrivelse.'"'.chr(9).'"'.$variant_stregkode.'"'.$varenr_alias.'"'.chr(9).dkdecimal($r['variant_kostpris']).chr(9).dkdecimal($r['variant_salgspris']).chr(9).dkdecimal($r['variant_vejlpris']);
			for ($x=0;$x<count($varianter_id);$x++) {
#			$linje.=chr(9)."$varianter_beskrivelse[$x]";
				$tmp=NULL;
				for ($y=0;$y<count($varianttyper_id);$y++) {#20140526
					if (isset($varianttyper_id[$y]) && isset($variant_type[$x]) && $varianttyper_id[$y]==$variant_type[$x]) $tmp=$varianttyper_beskrivelse[$y];
				}
				$linje.=chr(9).'"'."$tmp".'"';
			}
			$linje=str_replace("\n","",$linje);
			if ($charset=="UTF-8") $linje=mb_convert_encoding($linje, 'ISO-8859-1', 'UTF-8');
			fwrite($fp, $linje."\r\n");
		} elseif ($z>$slut) {
			break(1);
		}
	}
} 
fclose($fp);
if ($z>$slut) {
	print "Udlæser variantvarer ";
	for ($x=1000;$x<=$start;$x+=1000) print " *";
	print "<br>";
	print "<META HTTP-EQUIV=REFRESH CONTENT=\"0; URL=exporter_variantvarer.php?start=$z\">";
	exit;
}			


// The standard settings layout (includes/settings/layout.php): Tilbage, the title and the left menu with the page's row marked.
include_once("../includes/settings/layout.php");
settings_layout_open();
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1


print "</tr></tbody></table></td></tr>";
print "<td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
$xa=findtekst(1375, $sprog_id); $variantvarer = explode(" ", $xa);
print "<tr><td align=center> ".findtekst(1362, $sprog_id).": </td><td $top_bund><a href='$filnavn'>$variantvarer[1]</a></td></tr>";
print "<tr><td align=center colspan=2> ".findtekst(1363, $sprog_id)."</td></tr>";

print "</tbody></table>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
<?php settings_layout_close(); ?>
</body></html>
