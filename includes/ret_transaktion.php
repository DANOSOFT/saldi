<?php
// ----------includes/ret_transaktion.php-------------lap 3.2.5-----2011-11-17-----
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

ob_start();
@session_start();
$s_id=session_id();
$title="Ret transaktion";
$modulnr=1;
$css="../css/standard.css";
$felter=array('projekt');

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$id = if_isset($_GET['id'])*1;
$felt= if_isset($_GET['felt']);
$returside=if_isset($_GET['returside']);
$ny_feltvaerdi=if_isset($_POST['ny_feltvaerdi']);

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #Tabel1 ->
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=\"100%\" align=center border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; # Tabel 1.1 ->
		print "<tr><td width=\"10%\" $top_bund>";
		print "<a href=\"../finans/kontrolspor.php\" accesskey=\"L\">Luk</a></td>";
		print "<td width=80% $top_bund>Kontrolspor</td>";
		print "<td width=10% $top_bund><br></td></tr>";
	print "</tbody></table></tr>\n"; # <- Tabel 1.1

if (!in_array($felt,$felter)) {
 print "<BODY onLoad=\"javascript:alert('Forsøg på manipulation konstateret - handling afbrudt')\">";
 exit;
}

$r=db_fetch_array(db_select("select $felt from transaktioner where id = '$id'",__FILE__ . " linje " . __LINE__));
$feltvaerdi=$r[$felt];

if ($id && $ny_feltvaerdi && $ny_feltvaerdi!=$feltvaerdi) {
	db_modify("update transaktioner set $felt = '".addslashes($ny_feltvaerdi)."' where id = '$id'");
	$feltvaerdi=$ny_feltvaerdi;
}
$r=db_fetch_array(db_select("select beskrivelse from grupper where art = 'PRJ' and kodenr = '".addslashes($feltvaerdi)."'",__FILE__ . " linje " . __LINE__));
$beskrivelse=$r['beskrivelse'];


print "<form name=\"ret_transaktion\" action=\"ret_transaktion.php?id=$id&felt=$felt&returside=$returside\" method=\"post\">";
 print "<td align=\"center\"><table border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; # Tabel 1.2 ->
print "<tr><td align=\"center\" colspan=\"2\"><b>Vælg nyt projektnr og tryk OK</br><hr></br></b></td></tr>";
print "<tr><td><select name=ny_feltvaerdi>";
print "<option value=\"$feltvaerdi\">$feltvaerdi - $beskrivelse</option>";
$q=db_select("select * from grupper where art = 'PRJ' and kodenr != '0' and kodenr != '".addslashes($feltvaerdi)."' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) print "<option value=\"$r[kodenr]\">$r[kodenr] - $r[beskrivelse]</option>";
#print "<tr><td><input type=\"text\" name=ny_feltvaerdi value=\"$feltvaerdi\">";
print "<td><input type=submit value=\"OK\" name=\"submit\"></td></tr>";
print "</tbody></table></td>"; # <- Tabel 1.2
print "</form>\n";
print "</tbody></table>"; # <- Tabel 1
print "</body></html>"; 
  
?>