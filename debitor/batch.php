<?php
// ------------------debitor/batch.php--------lap 3.2.9----2013.02.10--
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
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2012.09.06 break ændret til break 1

@session_start();
$s_id=session_id();

$css="../css/standard.css";

$title="batch";

$linje_id=$_GET['linje_id'];
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

if ($_POST['submit']){
	$submit=trim($_POST['submit']);
	if ($submit=="Luk"){print "<body onload=\"javascript:window.close();\">";}
	$vare_id=$_POST['vare_id'];
	$leveres=$_POST['antal'];
	$kred_linje_id=$_POST['kred_linje_id'];
	$batch_antal=$_POST['batch_antal'];
	$art=trim($_POST['art']);
	$lager=$_POST['lager'];

	if ($_POST['status']<3) {
		for ($x=1; $x<=$batch_antal; $x++) {
			$temp="valg_".$x;
			$valg[$x]=$_POST[$temp]*1;
			$temp="batch_kob_id_".$x;
			$batch_kob_id[$x]=$_POST[$temp];
			$temp="ordrenr_".$x;
			$ordrenr[$x]=$_POST[$temp];
			$temp="batch_salg_id_".$x;
			$batch_salg_id[$x]=$_POST[$temp];
			$temp="res_linje_id_".$x;
			$res_linje_id[$x]=$_POST[$temp];
			$temp="rest_".$x;
			$rest[$x]=$_POST[$temp];

			if ($leveres > 0) {
				if ($valg[$x]>$rest[$x]) {$max_antal[$x]=$rest[$x];}
				else {$max_antal[$x]=$valg[$x];}
				if ($valg[$x] > $max_antal[$x])	{print "<BODY onLoad=\"javascript:alert('Ordrenr: $ordrenr[$x] - Der kan ikke v&aelig;lges flere end $max_antal[$x]!')\">";}
				$valgt_antal=$valgt_antal+$valg[$x];
				$rest_antal=$rest_antal+$rest[$x];
			}
		}
		if ($leveres >= 0) {
			if ($leveres>$rest_antal) {$max_antal=$rest_antal;}
			else {$max_antal=$leveres;}
			if ($valgt_antal > $max_antal)	{print "<BODY onLoad=\"javascript:alert('Der kan ikke v&aelig;lges flere end $max_antal !')\">";}
			else {
				 db_modify("delete from reservation where linje_id=$linje_id",__FILE__ . " linje " . __LINE__);
				 $temp=$linje_id*-1;
				 db_modify("delete from reservation where batch_salg_id=$temp",__FILE__ . " linje " . __LINE__);
				 for ($x=1; $x<=$batch_antal; $x++) {
					 $lager=$lager*1;
					if (($valg[$x]>0)&&(!$res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_kob_id, antal, lager) values ($linje_id, $vare_id, $batch_kob_id[$x], $valg[$x], $lager)",__FILE__ . " linje " . __LINE__);}
					elseif (($valg[$x]>0)&&($res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal, lager) values ($res_linje_id[$x], $vare_id, $temp, $valg[$x], $lager)",__FILE__ . " linje " . __LINE__);}
			 	} 
		 	}
		} else {
			if ($valg[1]==$batch_antal+1){
				db_modify("update ordrelinjer set kred_linje_id=-1 where id=$linje_id",__FILE__ . " linje " . __LINE__);
			}
			else {
				for ($x=1; $x<=$batch_antal; $x++)	{
					if ($valg[1]==$x){
						db_modify("update ordrelinjer set kred_linje_id=$kred_linje_id[$x] where id=$linje_id",__FILE__ . " linje " . __LINE__);
					}
				}
			}	
		}	
	}
}

$leveres=0;
# echo "select * from ordrelinjer where id = '$linje_id'<br>";
$query = db_select("select * from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
if ($row = db_fetch_array($query)) {
	$antal=$row['antal'];
	$leveres=$row['leveres'];
	$posnr=$row['posnr'];
	$vare_id=$row['vare_id'];
	$varenr=$row['varenr'];
	$serienr=$row['serienr'];
	$query = db_select("select status, art, konto_id, ref from ordrer where id = '$row[ordre_id]'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id=$row['konto_id'];
	$status=$row['status'];
	$art=$row[art];

	if ($art=="DK") {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=serienummer.php?linje_id=$linje_id\">";
		exit;
	}
	if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$row[ref]'",__FILE__ . " linje " . __LINE__))) {
		if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr']*1;}
	}
}

$query = db_select("select * from batch_salg where linje_id = $linje_id",__FILE__ . " linje " . __LINE__);
while($row = db_fetch_array($query)) {
	$leveres=$antal-$row[antal];
}

print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"1\" valign = \"top\" align=\"center\"><tbody>";
print "<tr><td align=center><b>Posnr: $posnr - Varenr: $varenr</td></tr>\n";
print "<form name=ordre batch.php?linje_id=$linje_id method=post>";
print "<tr><td><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\" align=\"center\" width=\"100%\"><tbody>";
if (($antal>=0)&&($art!="DK")){	
	print "<tr><td><b>K&oslash;bsordre nr.</td><td align = right><b>Antal</td><td align = right><b>V&aelig;lg</td></tr>\n";
#	print "<tr><td colspan=3><hr></td></tr>\n";
	$x=0;
	$rest=array();
	$lev_rest=$leveres;
	if ($lager) $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 and lager = $lager order by kobsdate",__FILE__ . " linje " . __LINE__);
	else $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 order by kobsdate",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$batch_kob_id[$x]=$row['id'];
		$kobsdate[$x]=$row['kobsdate'];
		$rest[$x]=$row['rest'];
		$reserveret[$x]=0;
		$pris[$x]=dkdecimal($row['pris']);
		$q2 = db_select("select ordrenr from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
		$r2 = db_fetch_array($q2);
		$ordrenr[$x]=$r2['ordrenr'];
		$q2 = db_select("select * from reservation where batch_kob_id=$row[id]",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			if ($r2['linje_id']!=$linje_id) $reserveret[$x]=$reserveret[$x]+$r2['antal'];
			else {
				$valg[$x]=$r2['antal'];
				$valgt.=$r2['antal'];
			} 
		}
		print "<tr>";
		print "<td><span title= 'kostpris: $pris[$x]'>$ordrenr[$x]</td>";
		print "<td align = right>".reducer($rest[$x])."</td>";
		print "<td align = right><input type=text style=\"text-align:right\" size=3 name=valg_$x value=".reducer($valg[$x])."></td>";
			if (($status>0)&&($valg[$x]>0)&&($serienr)){
				print "<td onClick=\"javascript:batch=window.open('serienummer.php?linje_id=$linje_id&batch_kob_id=$batch_kob_id[$x]','batch','left=60,top=60,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no')\">";
				print "<input type=button value=\"Serienr.\" name=\"vis_snr$x\"></td>";
			}
		print "</tr>\n";
		print "<input type=hidden name=ordrenr_$x value=$ordrenr[$x]><input type=hidden name=batch_kob_id_$x value=$batch_kob_id[$x]><input type=hidden name=rest_$x value=$rest[$x]>";
	}
	print "<tr><td colspan=3><hr></td></tr>\n";
	
	# Soeger efter reservationer i ikke modtagne koebsordrer
	$query = db_select("select * from reservation where batch_salg_id='0' and vare_id = '$vare_id' and antal > '0'",__FILE__ . " linje " . __LINE__); 
	if ($row = db_fetch_array($query)) {
		print "<tr><td colspan=3 align=center> Varer p&aring; vej</tr>\n";
		print "<tr><td><b>K&oslash;bsordre nr.</td><td align = right><b>Antal</td><td align = right><b>V&aelig;lg</td></tr>\n";
		print "<tr><td colspan=3><hr></td></tr>\n";
	}
	$temp=$linje_id*-1;
	$query = db_select("select * from reservation where batch_salg_id=0 and vare_id = $vare_id",__FILE__ . " linje " . __LINE__); 
	while ($row = db_fetch_array($query)) {
		$x++;
		$res_linje_id[$x]=$row['linje_id'];
		$valg[$x]=0;
		$rest[$x]=0;
		$rest[$x]=$row['antal'];
		$q2 = db_select("select ordre_id from ordrelinjer where id=$res_linje_id[$x]",__FILE__ . " linje " . __LINE__); 
		$r2 = db_fetch_array($q2);
		$ordre_id[$x]=$r2['ordre_id'];
		$q2 = db_select("select art, ordrenr, status from ordrer where id=$ordre_id[$x]",__FILE__ . " linje " . __LINE__); 
		$r2 = db_fetch_array($q2);
		if ($r2[status]<3) {
			$ordrenr[$x]=$r2['ordrenr'];
			$art[$x]=$r2['art'];
		}
		else { #Tilfoejet 200606 PHR 
			 db_modify("delete from reservation where linje_id=$res_linje_id[$x]",__FILE__ . " linje " . __LINE__);
			break 1;
		}
		if ($row[batch_kob_id]>0) {
			$q2 = db_select("select rest from batch_kob where id=$row[batch_kob_id]",__FILE__ . " linje " . __LINE__); 
			$r2 = db_fetch_array($q2);
			$rest[$x]=$rest[$x]+$r2[rest];	
		}
		$q2 = db_select("select * from reservation where linje_id=$res_linje_id[$x] and batch_salg_id != 0",__FILE__ . " linje " . __LINE__); 
		while ($r2 = db_fetch_array($q2)) {
			if ($r2[batch_salg_id]==$temp) {$valg[$x]=$valg[$x]+$r2['antal'];}
			else {$rest[$x]=$rest[$x]-$r2['antal'];}
		}
		if ($rest[$x]>0) {
			print "<tr>";
			if ($art[$x]=='DK') print "<td> $ordrenr[$x] (KN)</td><td align = right>".recucer($rest[$x])."</td>";
			else print "<td> $ordrenr[$x]</td><td align = right>".reducer($rest[$x])."</td>";
			print "<td align = right><input type=text style=\"text-align:right\" size=3 name=valg_$x value=$valg[$x]></td>";
			print "</tr>\n";
		}
		print "<input type=hidden name=ordrenr_$x value=$ordrenr[$x]><input type=hidden name=rest_$x value=$rest[$x]><input type=hidden name=batch_salg_id_$x value=0><input type=hidden name=res_linje_id_$x value=$res_linje_id[$x]><input type=hidden name=antal_$x value=$leveres[$x]>";
	}
	$batch_antal=$x;
	print "<input type=hidden name=antal value='$leveres'>";
} else {	
	print "<tr><td><b>Ordre nr.</td><td></td><td></td><td align = right><b>Antal</td><td align = right><b>V&aelig;lg</td></tr>\n";
	print "<tr><td colspan=5><hr></td></tr>\n";
	$query = db_select("select kred_linje_id from ordrelinjer where id=$linje_id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$kred_linje_id=$row['kred_linje_id'];
	$x=0;
	$query = db_select("select id, ordrenr from ordrer where konto_id=$konto_id and status>2 and art !='DK' and art !='KK'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$kred_ordre_id[$x]=$row['id'];
		$kred_ordrenr[$x]=$row['ordrenr'];
	}	
	$id_antal=$x;
	$y=0; 
	for ($x=1; $x<=$id_antal; $x++) {
		$query = db_select("select id, antal from ordrelinjer where ordre_id=$kred_ordre_id[$x] and vare_id = $vare_id and antal > 0",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$y++;
			print "<tr><td onClick=\"javascript:window.open('ordre.php?id=$kred_ordre_id[$x]','$kred_ordrenr[$x]','width=400,height=400,scrollbars=1,resizable=1,menubar=no,location=no');\";><a href> $kred_ordrenr[$x]</a></td><td></td><td><td align = right>".reducer($row['antal'])."</td>";
	#		 print "<td align = right><input type=text style=\"text-align:right\" size=3 name=valg_$x value=$valg[$x]></td></tr>\n";
			print "<td align = center><input type=radio name=valg_1 value=$y";
			if ($kred_linje_id==$row[id]) print " checked='checked'></td>";
			else print "></td>";
			if ($serienr){
				print "<td onClick=\"javascript:batch=window.open('serienummer.php?linje_id=$linje_id&batch_kob_id=$batch_kob_id[$x]','batch','left=60,top=60,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no')\">";
				print "<input type=button value=\"Serienr.\" name=\"vis_snr$x\"></td>";
			}
			print "<input type=hidden name=kred_linje_id[$y] value=$row[id]>";
			$batch_antal=$y;
			print "</tr>";
		}
	}
	$y++;
	print "<tr><td> Opret som indk&oslash;b</td><td></td><td></td><td></td><td align = center><input type=radio name=valg_1 value=$y";
	if ($kred_linje_id==-1){print " checked='checked'></td></tr>\n"; }
	else {print "></td></tr>\n";}
	print "<input type=hidden name=antal value='$antal'>";
}

print "</td></tr>\n";
print "<input type=hidden name=rest value='$rest'>";
print "<input type=hidden name=vare_id value='$vare_id'>";
# print "<input type=hidden name=batch_kob_id value=$batch_kob_id>";
print "<input type=hidden name=batch_antal value='$batch_antal'>";
print "<input type=hidden name=status value='$status'>";
print "<input type=hidden name=art value='$art'>";
print "<input type=hidden name=lager value='$lager'>";
print "</tbody></table>";
print "<tr><td><table cellpadding=\"0\" cellspacing=\"0\" border=\"1\" valign = \"top\" align=\"center\" width=\"100%\"><tbody>";
if ($batch_antal==0) {
	if (($antal>0)||($leveres!=0)) {print "<tr><td collspan=3> Der skal som minumum oprettes en godkendt indk&oslash;bsordre f&oslash;r der kan v&aelig;lges batch</td></tr>\n";}
	elseif (($antal<0)||($leveres!=0)) {print "<tr><td collspan=3> Denne vare er aldrig blevet solgt til kunden</td></tr>\n";}
	print "<td align=center><input type=submit value=\"Luk\" name=\"submit\"></td></tr>\n";
} else {
	print "<td align=center width=50%><input type=submit value=\"Gem\" name=\"submit\"></td><td align=center width=50%><input type=submit value=\"Luk\" name=\"submit\"></td></tr>\n";
}
print "</tbody></table></td>";
print "</form> </tr>\n";
print "</td></tr>\n</tbody></table>";
print "</form>";
?>
