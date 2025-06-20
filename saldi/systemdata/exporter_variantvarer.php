<?php
// ---------/systemdata/exporter_variantvarer.php---lap 3.4.1--2014-05-26------------------------
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
// Copyright (c) 2004-2016 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20130412 Rettet i formatet
// 20140516 Grundet timeout ved mange varer eksporteres nu max 1000 hvorefter rutinen genstarter der hvor den er nået til. Søg $start,$slut & $z; 
// 20140516 Indsat dkdecimal ved udskrivning af kost',salgs', og vejl.pris. 
// 20140526 Rettet $varianter_id til $varianttype_id 

@session_start();
$s_id=session_id();
$title="Eksporter variantvarer";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$returside="../diverse.php";

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

$q=db_select("select * from varianter",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$varianter_id[$x]=$r['id'];
	$varianter_beskrivelse[$x]=$r['beskrivelse'];
	$varianter_shop_id[$x]=$r['shop_id'];
	$x++;
}

$x=0;
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
if ($charset=="UTF-8") $overskrift=utf8_decode($overskrift);

if (fwrite($fp, "$overskrift\r\n")) {
	$z=0;
	$q=db_select("select varer.id,varer.varenr,varer.beskrivelse,variant_varer.variant_stregkode,variant_varer.variant_type,variant_varer.variant_salgspris,variant_varer.variant_kostpris,variant_varer.variant_vejlpris from varer,variant_varer where varer.id=variant_varer.vare_id order by varer.varenr,variant_varer.variant_stregkode",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$z++;
		if ($z>=$start && $z<=$slut) {
			$vare_id=$r['id'];
			$varenr=$r['varenr'];
			$beskrivelse=$r['beskrivelse'];
			$variant_stregkode=$r['variant_stregkode'];
			$variant_type=explode(chr(9),$r['variant_type']);

#			$variant_lager=$r['lager'];
			$linje='"'.$varenr.'"'.chr(9).'"'.$beskrivelse.'"'.chr(9).'"'.$variant_stregkode.'"'.chr(9).dkdecimal($r['variant_kostpris']).chr(9).dkdecimal($r['variant_salgspris']).chr(9).dkdecimal($r['variant_vejlpris']);
			for ($x=0;$x<count($varianter_id);$x++) {
#			$linje.=chr(9)."$varianter_beskrivelse[$x]";
				$tmp=NULL;
				for ($y=0;$y<count($varianttyper_id);$y++) {#20140526
					if (isset($varianttyper_id[$y]) && isset($variant_type[$x]) && $varianttyper_id[$y]==$variant_type[$x]) $tmp=$varianttyper_beskrivelse[$y];
				}
				$linje.=chr(9).'"'."$tmp".'"';
			}
			$linje=str_replace("\n","",$linje);
			if ($charset=="UTF-8") $linje=utf8_decode($linje);
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


print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=diverse.php?sektion=div_io accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>$title</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

print "<tr><td align=center> H&oslash;jreklik her: </td><td $top_bund><a href='$filnavn'>variantvarer</a></td></tr>";
print "<tr><td align=center colspan=2> V&aelig;lg \"gem destination som\"</td></tr>";

print "</tbody></table>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
