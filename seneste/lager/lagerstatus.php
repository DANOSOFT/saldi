<?php
// -----------------lager/lagerstatus.php----lap 3.3.9-----2014-01-28--
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
// Copyright (c) 2003-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2014.01.28 Ved søgning på modtaget / leveret tjekkes ikke for dato hvis angivet dato = dags dato da det gav forkert lagerantal for 
//          leverancer med leveringsdato > dd. Søg 20140128   

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Lagerstatus";

$linjebg=NULL;
$kostvalue=0;$lagervalue=0;$salgsvalue=0;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

# if ($popup) $returside="../includes/luk.php";
# else $returside="rapport.php";
$returside="rapport.php";

if (isset($_GET['vare_id']) && isset($_GET['opdater']) && isset($_GET['beholdning'])) {
	db_modify("update varer set beholdning='$_GET[beholdning]' where id = '$_GET[vare_id]'",__FILE__ . " linje " . __LINE__);
}
$varegruppe=if_isset($_GET['varegruppe']);
if ($varegruppe=="0:Alle") $varegruppe=NULL;
else {
	setcookie("saldi_lagerstatus", $varegruppe);
	$returside="rapport.php?varegruppe=$varegruppe";
}
if (isset($_POST['dato']) && $_POST['dato']) {
	$dato=$_POST['dato'];
	$varegruppe=trim($_POST['varegruppe']);
	setcookie("saldi_lagerstatus", $varegruppe);
}
elseif(!$varegruppe)  {
	$dato=date("d-m-Y");
	$varegruppe=($_COOKIE['saldi_lagerstatus']);
	if (!$varegruppe) $varegruppe="0:Alle";
}	

$csv=if_isset($_GET['csv']);

$dd=date("Y-m-d");
$date=usdate($dato);
$dato=dkdato($date);

$x=0;
$q1= db_select("select kodenr, box9 from grupper where art = 'VG' and box8 = 'on'",__FILE__ . " linje " . __LINE__);
while ($r1=db_fetch_array($q1)) {
	$x++;
	$lagervare[$x]=$r1['kodenr'];
	$batchvare[$x]=$r1['box9'];
}
	
$x=0;
list($a,$b)=explode(":",$varegruppe);
if ($a) $query = "select * from varer where gruppe='$a' order by varenr";
else $query = "select * from varer order by varenr";
$q2=db_select($query,__FILE__ . " linje " . __LINE__);
while ($r2=db_fetch_array($q2)){
	if (in_array($r2['gruppe'], $lagervare)) {
		$x++;
		$vare_id[$x]=$r2['id'];
		$varenr[$x]=stripslashes($r2['varenr']);
		$enhed[$x]=stripslashes($r2['enhed']);
		$beholdning[$x]=$r2['beholdning'];
		$beskrivelse[$x]=stripslashes($r2['beskrivelse']);
		$salgspris[$x]=$r2['salgspris'];
		$kostpris[$x]=$r2['kostpris'];
	}
}
$vareantal=$x;

print "<table border=0 cellpadding=0 cellspacing=0 width=100%><tbody>";
print "<tr><td colspan=9><table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
print "<tr>";
print "<td width=10% $top_bund><a href=$returside accesskey=L>Luk</a></td>";
print "<td width=80% $top_bund align=center>Lagerstatus</td>";
print "<td width=10% $top_bund><a href=lagerstatus.php?dato=$dato&varegruppe=$varegruppe&csv=1' title=\"Klik her for at eksportere til csv\">CSV</a></td>";
print "</tr></td></tbody></table>\n";
print "<form action=lagerstatus.php method=post>";
print "<tr><td colspan=\"7\" align=\"center\"> Varegruppe: <select class=\"inputbox\" name=\"varegruppe\">";
if ($varegruppe) print "<option>$varegruppe</option>";
if ($varegruppe!="0:Alle") print "<option>0:Alle</option>";
$query = db_select("select * from grupper where art = 'VG' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)){
	if ($varegruppe!=$row['kodenr'].":".$row['beskrivelse']) {print "<option>$row[kodenr]:$row[beskrivelse]</option>";}
}
print "</select>";
print "Dato:<input class=\"inputbox\" type=\"text\" name=\"dato\" value=\"$dato\" size=\"10\"></td>";
print "<td  colspan=6 align=right><input type=submit value=OK></form></td></tr>";
print "<tr><td colspan=9><hr></td></tr>";
print "<tr><td width=8%>Varenr.</td><td width=5%>Enhed</td><td width=48%>Beskrivelse</td>
	<td align=right width=5%><span title='Antal enheder k&oslash;bt f&oslash;r den $dato'>K&oslash;bt</span></td>
	<td align=right width=5%><span title='Antal enheder solgt f&oslash;r den $dato'>Solgt</span></td>
	<td align=right width=5%><span title='Lagerbeholdning pr. $dato'>Antal</span></td>
	<td align=right width=8%><span title='K&oslash;bsv&aelig;rdi af lagerbeholdning (Reel k&oslash;bspris)'>K&oslash;bspris</span></td>
	<td align=right width=8%><span title='Kostpris af lagerbeholdning (fra varekort)'>Kostpris</span></td>
	<td align=right width=8%><span title='Salgsv&aelig;rdi af lagerbeholdning (fra varekort)'>Salgspris</span></td></tr>";

if ($csv) {
	$fp=fopen("../temp/$db/lagerstatus.csv","w");
	fwrite($fp,"Varenr".chr(9)."Enhed".chr(9)."Beskrivelse".chr(9)."Købt".chr(9)."Solgt".chr(9)."Antal".chr(9)."Købspris".chr(9)."Kostpris".chr(9)."Salgspris\n");
}
 

for($x=1; $x<=$vareantal; $x++) {
	$handlet[$x]=0;
	$batch_k_antal[$x]=0;$batch_t_antal[$x]=0;$batch_pris[$x]=0;$batch_s_antal[$x]=0;
	if ($date==$dd) $q1=db_select("select * from batch_kob where vare_id=$vare_id[$x]",__FILE__ . " linje " . __LINE__); #20140128
	else $q1=db_select("select * from batch_kob where vare_id=$vare_id[$x] and kobsdate <= '$date'",__FILE__ . " linje " . __LINE__);
	while ($r1=db_fetch_array($q1)){
		$batch_k_antal[$x]=$batch_k_antal[$x]+$r1['antal'];
		$batch_t_antal[$x]=$batch_t_antal[$x]+$r1['antal'];
		$batch_pris[$x]=$batch_pris[$x]+($r1['pris']*$r1['antal']);
		$handlet[$x]=1;
		if (isset($batchvare[$x]) && $batchvare[$x]) {
			if ($date==$dd) $q2=db_select("select * from batch_salg where batch_kob_id=$r1[id]",__FILE__ . " linje " . __LINE__); #20140128
			else $q2=db_select("select * from batch_salg where batch_kob_id=$r1[id] and salgsdate <= '$date'",__FILE__ . " linje " . __LINE__);
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
		if ($date==$dd) $q2=db_select("select * from batch_salg where vare_id=$vare_id[$x]",__FILE__ . " linje " . __LINE__); #20140128
		else $q2=db_select("select * from batch_salg where vare_id=$vare_id[$x] and salgsdate <= '$date'",__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)){
			$batch_s_antal[$x]=afrund($batch_s_antal[$x]+$r2['antal'],2);
			$batch_t_antal[$x]=afrund($batch_t_antal[$x]-$r2['antal'],2);
			$handlet[$x]=1;
#			$batch_pris[$x]=$batch_pris[$x]-($r1['pris']*$r2['antal']);
		}
		if ($tmp*$batch_t_antal[$x]!=0) $batch_pris[$x]=$batch_pris[$x]/$tmp*$batch_t_antal[$x];
		else $batch_pris[$x]=0;
	}
	if (isset($_GET['ajour']) && $_GET['ajour']==1 && $batch_t_antal[$x] != $beholdning[$x]) {
		db_modify("update varer set beholdning = '$batch_t_antal[$x]' where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
	}
	if ($batch_k_antal[$x]||$batch_s_antal[$x]||$beholdning[$x]||$handlet[$x]) {
		if ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		else {$linjebg=$bgcolor; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($popup) print "<td onClick=\"javascript:varespor=window.open('varespor.php?vare_id=$vare_id[$x]','varespor','$jsvars')\" onMouseOver=\"this.style.cursor = 'pointer'\"><u>$varenr[$x]</u><br></td>";
		else print "<td><a href=varespor.php?vare_id=$vare_id[$x]>$varenr[$x]<br></td>";
		print	"<td>$enhed[$x]<br></td><td>$beskrivelse[$x]<br></td>
		<td align=right>$batch_k_antal[$x]<br></td><td align=right>$batch_s_antal[$x]<br></td>";
		if ($date==$dd && $batch_t_antal[$x]!=$beholdning[$x]) print "<td align=right title=\"Beholdning (".$beholdning[$x]*1 .") stemmer ikke med det antal som er k&oslash;bt og solgt. Klik her for at opdatere beholdning\"><a href=".$_SERVER['PHP_SELF']."?vare_id=$vare_id[$x]&beholdning=$batch_t_antal[$x]&opdater=on onclick=\"return confirm('Opdater lagerbeholdning fra $beholdning[$x] til $batch_t_antal[$x] for denne vare?')\"><span style=\"color: rgb(255, 0, 0);\">$batch_t_antal[$x]</span></a><br></td>";
		else print "<td align=right>$batch_t_antal[$x]<br></td>";
		print "<td align=right>".dkdecimal($batch_pris[$x])."<br></td>
		<td align=right title='stkpris:".dkdecimal($kostpris[$x])."'>".dkdecimal($kostpris[$x]*$batch_t_antal[$x])."<br></td>
		<td align=right>".dkdecimal($salgspris[$x]*$batch_t_antal[$x])."<br></td></tr>";
		if ($csv) fwrite($fp,"$varenr[$x]".chr(9)."$enhed[$x]".chr(9)."$beskrivelse[$x]".chr(9)."$batch_k_antal[$x]".chr(9)."$batch_s_antal[$x]".chr(9).$batch_t_antal[$x].chr(9).dkdecimal($batch_pris[$x]).chr(9).dkdecimal($kostpris[$x]*$batch_t_antal[$x]).chr(9).dkdecimal($salgspris[$x]*$batch_t_antal[$x])."\n");
		$lagervalue=$lagervalue+$batch_pris[$x];$kostvalue=$kostvalue+$kostpris[$x]*$batch_t_antal[$x]; $salgsvalue=$salgsvalue+($salgspris[$x]*$batch_t_antal[$x]);
	} 
}
if ($csv){ 
	fclose($fp);
	print "<BODY onLoad=\"JavaScript:window.open('../temp/$db/lagerstatus.csv' ,'' ,'$jsvars');\">\n";
}
print "<tr><td colspan=9><hr></td></tr>";
print "<tr><td colspan=2><br></td><td>Samlet lagerv&aelig;rdi pr. $dato<br></td><td align=right><br></td><td align=right><br></td>
<td align=right><br></td><td align=right>".dkdecimal($lagervalue)."<br></td>
<td align=right>".dkdecimal($kostvalue)."<br></td>
<td align=right>".dkdecimal($salgsvalue)."<br></td></tr>";

?>
</tbody></table>
</body></html>
