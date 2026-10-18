<?php
// ---- /systemdata/exporter_posmenu.php -------- ver. 4.0.4 -- 2021-11-19 --
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med Saldi.dk ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2021 Saldi.dk ApS
// --------------------------------------------------------------------------
// 20211119 CA  Export all menus for PoS
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260916 Sawaneh Declared $permission_key (roles & permissions, phase 3)
// 20261008 Sawaneh The page on the new page head (page_bar) instead of the blue bar: title, Tilbage only outside the shell.

@session_start();
$s_id=session_id();
$saldifileformat="saldi_posmenus"; 
$saldifileformatversion="4_0_4"; # Change this if the format changes (more tables, fields, field names)
$title="Eksporter PoS-menuer";
$css="../css/standard.css";

include("../includes/connect.php");
$permission_key = 'settings.import_export';
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
$regnskabsaar=if_isset($_GET['aar']);

// Ensure $charset is defined
if ($db_encode == "UTF8") $charset = "UTF-8";
else $charset = "ISO-8859-1";

$returside="../includes/luk.php"; // WP-6.4: opened as a popup from diverse.php; Tilbage closes it

$filnavn="../temp/".$db."/".$saldifileformat."_".$saldifileformatversion."_".date("Y-m-d").".csv";

$fp=fopen($filnavn,"w");
if (fwrite($fp,"fileformat".chr(9)."version".chr(9)."delimiter".chr(9)."charset".chr(9)."table1".chr(9)."fields1".chr(9)."table2".chr(9)."fields2\r\n")) {
	fwrite($fp,$saldifileformat.chr(9).$saldifileformatversion.chr(9)."Tabulator".chr(9).$charset.chr(9)."pos_buttons".chr(9)."9".chr(9)."grupper where art='POSBUT'".chr(9)."16\r\n");
	fwrite($fp,"\r\n");
	fwrite($fp,"menu_id".chr(9)."row".chr(9)."col".chr(9)."beskrivelse".chr(9)."color".chr(9)."funktion".chr(9)."vare_id".chr(9)."colspan".chr(9)."rowspan\r\n");
	$q=db_select("select * from pos_buttons order by menu_id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		 $beskrivelse=$r['beskrivelse'];
		 if ($charset=="UTF-8") $beskrivelse=mb_convert_encoding($beskrivelse, 'ISO-8859-1', 'UTF-8');
		 $r['col']=(int)$r['col'];$r['row']=(int)$r['row'];$r['colspan']=(int)$r['colspan'];$r['rowspan']=(int)$r['rowspan'];
		 $linje=str_replace("\n","",$r['menu_id'].chr(9).$r['row'].chr(9).$r['col'].chr(9).$beskrivelse.chr(9).$r['color'].chr(9).$r['funktion'].chr(9).$r['vare_id'].chr(9).$r['colspan'].chr(9).$r['rowspan']);
		 fwrite($fp, $linje."\r\n");
	} 
        fwrite($fp,"\r\n");
	fwrite($fp,"beskrivelse".chr(9)."art".chr(9)."kode".chr(9)."kodenr".chr(9)."box1".chr(9)."box2".chr(9)."box3".chr(9)."box4".chr(9)."box5".chr(9)."box6".chr(9)."box7".chr(9)."box8".chr(9)."box9".chr(9)."box10".chr(9)."box11".chr(9)."box12\r\n");
	$q=db_select("select * from grupper where art='POSBUT' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$beskrivelse=$r['box1'];
		if ($charset=="UTF-8") $beskrivelse=mb_convert_encoding($beskrivelse, 'ISO-8859-1', 'UTF-8');
		$r['kode']=(int)$r['kode'];$r['kodenr']=(int)$r['kodenr'];$r['box2']=(int)$r['box2'];$r['box3']=(int)$r['box3'];$r['box4']=(int)$r['box4'];$r['box5']=(int)$r['box5'];
		$linje=str_replace("\n","",$r['beskrivelse'].chr(9).$r['art'].chr(9).$r['kode'].chr(9).$r['kodenr'].chr(9).$beskrivelse.chr(9).$r['box2'].chr(9).$r['box3'].chr(9).$r['box4'].chr(9).$r['box5'].chr(9).$r['box6'].chr(9).$r['box7'].chr(9).$r['box8'].chr(9).$r['box9'].chr(9).$r['box10'].chr(9).$r['box11'].chr(9).$r['box12']);
		fwrite($fp, $linje."\r\n");
	}
} 
fclose($fp);

include_once("../includes/stdFunc/pageBar.php");
page_bar(array('title' => findtekst(1932, $sprog_id), 'back' => $returside));
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1


print "</tr></tbody></table></td></tr>";
print "<td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

print "<tr><td align=center> ".findtekst(1362, $sprog_id).": </td><td $top_bund><a href='$filnavn'>".findtekst(1933, $sprog_id)."</a></td></tr>";
print "<tr><td align=center colspan=2> ".findtekst(1363, $sprog_id)."</td></tr>";

print "</tbody></table>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
