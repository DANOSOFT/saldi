<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------------lager/lagerstatus.php----lap 3.7.1-----2018-02-04--
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2018 saldi.dk aps
// ----------------------------------------------------------------------
// 2014.01.28 Ved søgning på modtaget / leveret tjekkes ikke for dato hvis angivet dato = dags dato da det gav forkert lagerantal for 
//          leverancer med leveringsdato > dd. Søg 20140128   
// 2016.10.05 Lagerstatus opdateres ved ajourføring 20161005
// 2016.12.22 $opdater flyttet sammen med $ret_behold;
// 2017.11.02 CSV fil utf8_dekodes og dseparer med ;  
// 2018.02.04 Div. tilretninger i forhold til varianter så beholdninger opdateres ikke ved diff'er - skal laves?  20180204
// 2018.03.27 Større omskrivning omkring købspriser & kostpriser.
 
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

$opdater=if_isset($_GET['opdater']);
$ret_behold=if_isset($_GET['ret_behold']);
$varegruppe=if_isset($_GET['varegruppe']);
if ($varegruppe=="0:Alle") $varegruppe=NULL;
else {
	setcookie("saldi_lagerstatus", $varegruppe);
	$returside="rapport.php?varegruppe=$varegruppe";
}
if (isset($_POST['dato']) && $_POST['dato']) {
	$dato=$_POST['dato'];
	$varegruppe=trim($_POST['varegruppe']);
	$lagervalg=trim($_POST['lagervalg']);
	setcookie("saldi_lagerstatus", $varegruppe);
} elseif (isset($_GET['dato']) && $_GET['dato']) {
	$dato=$_GET['dato'];
	$varegruppe=trim($_GET['varegruppe']);
	$lagervalg=trim($_GET['lagervalg']);
	# setcookie("saldi_lagerstatus", $varegruppe);
} elseif (!$varegruppe)  {
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
$lager[1]=1;
$lagernavn[1]='';
$x=0;
$q1= db_select("select kodenr,beskrivelse from grupper where art = 'LG' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($r1=db_fetch_array($q1)) {
	$x++;
	$lager[$x]=$r1['kodenr'];
	$lagernavn[$x]=$r1['beskrivelse'];
}
if (count($lager)>=1) {
	$lager[0]=0;
	$lagernavn[0]='Alle';
	db_modify("update batch_kob set lager='1' where lager='0' or lager is NULL",__FILE__ . " linje " . __LINE__);
	db_modify("update batch_salg set lager='1' where lager='0' or lager is NULL",__FILE__ . " linje " . __LINE__);
}

$x=0;
list($a,$b)=explode(":",$varegruppe);

if ($a) {
	if ($lagervalg) {
		$qtxt="select varer.id,varer.varenr,varer.enhed,varer.beskrivelse,varer.salgspris,varer.kostpris,varer.varianter,varer.gruppe,";
		$qtxt.="lagerstatus.beholdning ";
		$qtxt.="from varer,lagerstatus where varer.gruppe='$a' and lagerstatus.vare_id=varer.id and lagerstatus.lager='$lagervalg' ";
		$qtxt.="order by varer.varenr";
	} else $qtxt = "select * from varer where gruppe='$a' order by varenr";
} else {
	if ($lagervalg) {
		$qtxt="select varer.id,varer.varenr,varer.enhed,varer.beskrivelse,varer.salgspris,varer.kostpris,varer.varianter,varer.gruppe,";
		$qtxt.="lagerstatus.beholdning ";
		$qtxt.="from varer,lagerstatus where lagerstatus.vare_id=varer.id and lagerstatus.lager='$lagervalg' order by varer.varenr";
	} else $qtxt = "select * from varer order by varenr";
}
$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r2=db_fetch_array($q2)){
	if (in_array($r2['gruppe'], $lagervare)) {
		$x++;
		$vare_id[$x]=$r2['id'];
		$varenr[$x]=stripslashes($r2['varenr']);
		$enhed[$x]=stripslashes($r2['enhed']);
		$beholdning[$x]=$r2['beholdning'];
		$varianter[$x]=$r2['varianter']; #20180204
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
print "<tr><td colspan=\"7\" align=\"center\">";
if (count($lager)) {
	print " Lager: <select class=\"inputbox\" name=\"lagervalg\">";
	for ($x=0;$x<=count($lager);$x++){
		if ($lagervalg==$lager[$x]) print "<option value='$lager[$x]'>$lagernavn[$x]</option>";
	}
	for ($x=0;$x<=count($lager);$x++){
		if ($lagervalg!=$lager[$x]) print "<option value='$lager[$x]'>$lagernavn[$x]</option>";
	}
	print "</select>";
}
print " Varegruppe: <select class=\"inputbox\" name=\"varegruppe\">";
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
	$linje="Varenr".";"."Enhed".";"."Beskrivelse".";"."Købt".";"."Solgt".";"."Antal".";"."Købspris".";"."Kostpris".";"."Salgspris";
	$linje=utf8_decode($linje);
	fwrite($fp,"$linje\n");
}
 
for($x=1; $x<=$vareantal; $x++) {
	$handlet[$x]=0;
	$batch_k_antal[$x]=0;$batch_t_antal[$x]=0;$batch_pris[$x]=0;$batch_s_antal[$x]=0;
	$qtxt="select sum(antal) as antal from batch_kob where vare_id=$vare_id[$x]";
	if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
	if ($date!=$dd) $qtxt.=" and kobsdate <= '$date'";
#if ($vare_id[$x]==454) #cho "$qtxt<br>";		
	$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#if ($vare_id[$x]==454) 	#cho $r1['antal']."<br>";
	$batch_k_antal[$x]=$r1['antal'];
#if ($vare_id[$x]==454) #cho "Bk $batch_k_antal[$x]<br>";		
	$batch_t_antal[$x]=$r1['antal'];
	$qtxt="select sum(antal) as antal from batch_salg where vare_id=$vare_id[$x]";
	if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
	if ($date!=$dd) $qtxt.=" and salgsdate <= '$date'";
	$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$batch_s_antal[$x]=$r1['antal'];
#if ($vare_id[$x]==454) #cho "Bs $batch_s_antal[$x]<br>";		
	$batch_t_antal[$x]-=$r1['antal'];
#if ($vare_id[$x]==454) #cho "Bk $batch_k_antal[$x] Bs $batch_s_antal[$x] Bt $batch_t_antal[$x]<br>";		
/*
	if ($vare_id[$x]==454) #cho "Bt $batch_t_antal[$x]<br>";		
	$qtxt="select * from batch_kob where vare_id=$vare_id[$x]"; #20140128
	if ($lagervalg)	$qtxt.=" and lager='$lagervalg'"; #20140128
	if ($date!=$dd) $qtxt.=" and kobsdate <= '$date'";
	$qtxt.=" order by kobsdate desc";
if ($vare_id[$x]==454) #cho "BP $qtxt	<br>";		
	$antal=0;
	$pris=0;
	$q1=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r1=db_fetch_array($q1)){
		if ($antal+=$r1['antal']<=$batch_t_antal[$x]) {
			$pris+=$r1['antal']*$r1['pris'];
			$antal+=$r1['antal'];
		} else {
			$pris+=($batch_t_antal[$x]-$antal)*$r1['pris'];
		}
	}
	$batch_pris[$x]=$pris;
*/	
/*
		$batch_k_antal[$x]=$batch_k_antal[$x]+$r1['antal'];
		$batch_t_antal[$x]=$batch_t_antal[$x]+$r1['antal'];
		$batch_pris[$x]=$batch_pris[$x]+($r1['pris']*$r1['antal']);
if ($vare_id[$x]==454) #cho "BP $batch_pris[$x]<br>";		
		$handlet[$x]=1;
		if (isset($batchvare[$x]) && $batchvare[$x]) {
			$qtxt="select * from batch_salg where batch_kob_id=$r1[id]"; #20140128
			if ($date!=$dd) $qtxt.=" and salgsdate <= '$date'";
			$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)){
				$batch_s_antal[$x]=$batch_s_antal[$x]+$r2['antal'];
				$batch_t_antal[$x]=$batch_t_antal[$x]-$r2['antal'];
				$batch_pris[$x]=$batch_pris[$x]-($r1['pris']*$r2['antal']);
			}
		}	
#	db_modify("update varer set beholdning = '$batch_t_antal[$x]' where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);  
	}
*/

	if (!isset($batchvare[$x])) $batchvare[$x]=NULL;
	if (!$batchvare[$x]) {
/*
	$tmp=$batch_t_antal[$x];
		$qtxt="select * from batch_salg where vare_id=$vare_id[$x]"; #20140128
		if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
		if ($date!=$dd) $qtxt.=" and salgsdate <= '$date'";
		$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)){
			$batch_s_antal[$x]=afrund($batch_s_antal[$x]+$r2['antal'],2);
			$batch_t_antal[$x]=afrund($batch_t_antal[$x]-$r2['antal'],2);
			$handlet[$x]=1;
#			$batch_pris[$x]=$batch_pris[$x]-($r1['pris']*$r2['antal']);
		}
		if ($tmp*$batch_t_antal[$x]!=0) $batch_pris[$x]=$batch_pris[$x]/$tmp*$batch_t_antal[$x];
		else $batch_pris[$x]=0;
*/	
	if ($batch_k_antal[$x]) {
		$pris=0;
		$antal=0;
		$qtxt="select antal,pris from batch_kob where vare_id=$vare_id[$x] and antal >= 1"; #20140128
		if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
		if ($date!=$dd) $qtxt.=" and kobsdate <= '$date'";
		$qtxt.=" order by kobsdate desc";
#if ($vare_id[$x]=='454') #cho __line__." $qtxt<br>";		
		$q1=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while($r1=db_fetch_array($q1)) {
			if ($antal+$r1['antal'] <= $batch_t_antal[$x]) {
#if ($vare_id[$x]=='454') #cho __line__." $antal+$r1[antal] <= $batch_t_antal[$x]<br>";
#if ($vare_id[$x]=='454') #cho __line__." Pris=$pris<br>";
				$antal+=$r1['antal'];
				$pris+=$r1['antal']*$r1['pris'];
#if ($vare_id[$x]=='454') #cho __line__." Pris=$pris+=$r1[antal]*$r1[pris]<br>";
			} elseif ($antal < $batch_t_antal[$x] && $antal+$r1['antal'] > $batch_t_antal[$x]) {
#if ($vare_id[$x]=='454') #cho __line__." Pris=$pris<br>";
				$pris+=$r1['pris']*($batch_t_antal[$x]-$antal);
				$antal=$batch_t_antal[$x];
#if ($vare_id[$x]=='454') #cho __line__." Pris=$pris<br>";
			}
		}
		($antal)?$batch_pris[$x]=$pris:$batch_pris[$x]=0;
	}
	}
	if (isset($_GET['ajour']) && $_GET['ajour']==1 && $batch_t_antal[$x] != $beholdning[$x]) {
		$diff=$batch_t_antal[$x];
		db_modify("update varer set beholdning = '$batch_t_antal[$x]' where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
		$ls_id=array();
		$ls=0;
		$q2=db_select("select * from lagerstatus where vare_id='$vare_id[$x]' order by lager,variant_id",__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)){
			$diff-=$r2['beholdning'];
			$ls_id[$ls]=$r2['id'];
			$ls_lager[$ls]=$r2['lager'];
			$ls_variant[$x]=$r2['variant_id'];
			$ls++;
		}
		if ($diff && !$varianter[$x]) { #20161005 + 20180204 
			db_modify("insert into lagerstatus(vare_id,beholdning,lager) values ('$vare_id[$x]','$diff','$tmp')",__FILE__ . " linje " . __LINE__);
		}
	}
	if ($batch_k_antal[$x]||$batch_s_antal[$x]||$beholdning[$x]||$handlet[$x]) {
		if ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		else {$linjebg=$bgcolor; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($popup) print "<td onClick=\"javascript:varespor=window.open('varespor.php?vare_id=$vare_id[$x]','varespor','$jsvars')\" onMouseOver=\"this.style.cursor = 'pointer'\"><u>$varenr[$x]</u><br></td>";
		else print "<td><a href=varespor.php?vare_id=$vare_id[$x]>$varenr[$x]<br></td>";
		print	"<td>$enhed[$x]<br></td><td>$beskrivelse[$x]<br></td>
		<td align=right>".str_replace(".",",",$batch_k_antal[$x]*1)."<br></td><td align=right>".str_replace(".",",",$batch_s_antal[$x]*1)."<br></td>";
		if ($date==$dd && afrund($batch_t_antal[$x],1)!=afrund($beholdning[$x],1) && !$lagervalg) {
#cho __line__." $vare_id[$x] | $varenr[$x] | $beholdning[$x]<br>";
			if ($ret_behold==2 || ($opdater && $vare_id[$x]==$opdater)) {
				if (count($lager) >= 1) {
					$ny_beholdning[$x]=0;
					for ($y=1;$y<count($lager);$y++) {
#cho "select sum(antal) as antal from batch_kob where vare_id='$vare_id[$x]' and lager='$lager[$y]'<br>";
						$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_kob where vare_id='$vare_id[$x]' and lager='$lager[$y]'",__FILE__ . " linje " . __LINE__));
						$lagerbeh[$y]=$r2['antal'];
						$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_salg where vare_id='$vare_id[$x]' and lager='$lager[$y]'",__FILE__ . " linje " . __LINE__));
						$lagerbeh[$y]-=$r2['antal'];
						$ny_beholdning[$x]+=$lagerbeh[$y];
						if (!$varianter[$x]) { #20180204
							db_modify("update lagerstatus set beholdning = '$lagerbeh[$y]' where lager='$lager[$y]' and vare_id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
						}
						db_modify("update varer set beholdning = '$ny_beholdning[$x]' where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
					}	
				}  
/* else { #overflødig ->
				$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_kob where vare_id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
					$lagerbeh[$y]=$r2['antal'];
					$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_salg where vare_id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
					$lagerbeh[$y]-=$r2['antal'];
					$ny_beholdning[$x]+=$lagerbeh[$y];
					$qtxt="select id from lagerstatus where vare_id='$vare_id[$x]'";
					$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					if ($r2['id']) {
						$qtxt="delete from lagerstatus where vare_id='$vare_id[$x]' and id !='$r2[id]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt="update lagerstatus set beholdning = '$ny_beholdning[$x]' where id='$r2[id]'";
					} else $qtxt="insert into lagerstatus(vare_id,beholdning,lager) values ('$vare_id[$x]','$ny_beholdning[$x]','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
*/
#				db_modify("update varer set beholdning = '$ny_beholdning[$x]' where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
				$beholdning[$x]=$ny_beholdning[$x];
				print "<td align=right>".str_replace(".",",",$batch_t_antal[$x]*1)."<br></td>";
			} else {
				print "<td align=right title=\"Beholdning (".str_replace(".",",",$beholdning[$x]*1).") stemmer ikke med det antal som er k&oslash;bt og solgt. Klik her for at opdatere beholdning\"><a href=".$_SERVER['PHP_SELF']."?opdater=$vare_id[$x] onclick=\"return confirm('Opdater lagerbeholdning fra ".dkdecimal($beholdning[$x],2)." til ".dkdecimal($batch_t_antal[$x],2)." for denne vare?')\"><span style=\"color: rgb(255, 0, 0);\">".str_replace(".",",",$batch_t_antal[$x]*1)."</span></a><br></td>";
				$ret_behold=1;
			}
		} else print "<td align=right>".str_replace(".",",",$batch_t_antal[$x]*1)."<br></td>";
		
		print "<td align=right>".dkdecimal($batch_pris[$x])."<br></td>
		<td align=right title='stkpris:".dkdecimal($kostpris[$x])."'>".dkdecimal($kostpris[$x]*$batch_t_antal[$x])."<br></td>
		<td align=right>".dkdecimal($salgspris[$x]*$batch_t_antal[$x])."<br></td></tr>";
		if ($csv) {
			$linje="$varenr[$x]".";"."$enhed[$x]".";"."$beskrivelse[$x]".";"."$batch_k_antal[$x]".";"."$batch_s_antal[$x]".";".$batch_t_antal[$x].";".dkdecimal($batch_pris[$x]).";".dkdecimal($kostpris[$x]*$batch_t_antal[$x]).";".dkdecimal($salgspris[$x]*$batch_t_antal[$x]);
			$linje=utf8_decode($linje);
			fwrite($fp,"$linje\n");
		}
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
if ($ret_behold==1) print "<tr><td><a href=\"lagerstatus.php?varegruppe=$varegruppe&ret_behold=2\">Ret skæve lagertal</a></td></tr>";
?>
</tbody></table>
</body></html>
