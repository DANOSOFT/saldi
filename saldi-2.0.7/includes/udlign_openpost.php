<?php
@session_start();
$s_id=session_id();
// ------------includes/udlign_openpost.php-------patch 2.0.7----2009-05-18--------
// LICENS>
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------
$modulnr=12;
$title="&Aring;benpostudligning";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");

if ($_POST['submit']) {
 	$submit=strtolower(trim($_POST['submit']));
	$post_id=$_POST['post_id'];
	$udlign=$_POST['udlign'];
	$regnaar=$_POST['regnaar'];
	$maaned_fra=$_POST['maaned_fra'];
	$maaned_til=$_POST['maaned_til'];
	$konto_fra=$_POST['konto_fra'];
	$konto_til=$_POST['konto_til']; 
	$retur=$_POST['retur'];
	$diff=$_POST['diff'];
	$maxdiff=$_POST['maxdiff'];
	$diffkto=$_POST['diffkto'];
} else {
	$post_id[0]=$_GET['post_id'];
	$regnaar=$_GET['regnaar'];
	$maaned_fra=$_GET['maaned_fra'];
	$maaned_til=$_GET['maaned_til'];
	$konto_fra=$_GET['konto_fra'];
	$konto_til=$_GET['konto_til']; 
	$retur=$_GET['retur'];
}

$query = db_select("select * from openpost where id='$post_id[0]'",__FILE__ . " linje " . __LINE__); #$post_id[0] er den post som skal udlignes.
if ($row = db_fetch_array($query)) {
	$konto_id[0]=$row[konto_id];
	$refnr[0]=$row['refnr'];
	$amount[0]=$row['amount'];
#echo "amount $amount[0]<br>";	
	$transdate[0]=$row['transdate'];
	$faktnr[0]=$row['faktnr'];
	$kontonr[0]=$row['kontonr'];
	$beskrivelse[0]=$row['beskrivelse'];
	$valuta[0]=$row['valuta'];
	if (!$valuta[0])$valuta[0]='DKK';
	$valutakurs[0]=$row['valutakurs']*1;
	if (!$valutakurs[0]) $valutakurs[0]=100;
	$udlign[0]='on';
	print "<input type = hidden name=konto_id[0] value=$konto_id[0]>";
} else print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapport=kontokort.php\">";

$r = db_fetch_array(db_select("select * from adresser where id=$konto_id[0]",__FILE__ . " linje " . __LINE__));
$betalingsbet=trim($r['betalingsbet']);
$betalingsdage=$r['betalingsdage'];
$art=substr($r['art'],0,1)."G";
#echo "select box3 from grupper where art='$art' and kodenr='$r[gruppe]'<br>";
$r2 = db_fetch_array(db_select("select box3 from grupper where art='$art' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
$basisvaluta=trim($r2['box3']);
#echo "basis $basisvaluta | $valuta[$x]<br>";	
if (!$basisvaluta) $basisvaluta='DKK';
else {
	$r2 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$basisvaluta' and art='VK'",__FILE__ . " linje " . __LINE__));
	$valutakode=$r2['kodenr'];
}	
if ($valutakode && $valuta[0]!=$basisvaluta) {  
		if (!$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[0]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
			$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' order by valdate",__FILE__ . " linje " . __LINE__));
		}
		$amount[0]=$amount[0]*100/$r3['kurs'];
} 
if ($valuta[0]!=$basisvaluta) $amount[0]=$amount[0]*$valutakurs[0]/100;

$diff=$amount[0];
$udlign_date="$transdate[0]";
$x=0;
$query = db_select("select * from openpost where id!='$post_id[0]' and konto_id='$konto_id[0]' and udlignet != '1'",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)){
	$x++;
	$post_id[$x]=$row[id];
	$refnr[$x]=$row['refnr'];
	$amount[$x]=$row['amount'];
#echo "amount $amount[$x]<br>";	
	$transdate[$x]=$row['transdate'];
	$faktnr[$x]=$row['faktnr'];
	$kontonr[$x]=$row['kontonr'];
	$beskrivelse[$x]=$row['beskrivelse'];
	$valuta[$x]=$row['valuta']*1;
	$valutakurs[$x]=$row['valutakurs']*1;
	if (!$valutakurs[$x]) $valutakurs[$x]=100;
#echo "basis $basisvaluta | $valuta[$x]<br>";	
#	if ($basisvaluta == 'DKK' && $basisvaluta != $valuta[$x]) $amount[$x]=$amount[$x]*$valutakurs[$x]/100;
#		elseif ($basisvaluta != $valuta[$x]) $amount[$x]=$amount[$x]*100/$valutakurs[$x]; 

#################################
	if ($basisvaluta!="DKK") { # kreditors valuta er fremmed.
		if (!$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$x]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
			$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' order by valdate",__FILE__ . " linje " . __LINE__));
		}
		if ($$basiskurs=$r3['kurs']) { #Kurs paa transaktionsdagen 
			$amount[$x]=$amount[$x]*100/$$basiskurs;
			if ($valutakurs[$x]==100) {
			} else { #postering foert i anden fremmed valuta end kreditors
				$amount[$x]=$amount[$x]*$valutakurs[$x]/100;
			}
		}
	} elseif ($valuta[$x]!='DKK' && $basisvaluta=="DKK" && $valutakurs[$x]!=100) { #postering foert i anden valuta end kreditors som er DKK 
		$amount[$x]=$amount[$x]*$valutakurs[$x]/100;
	}
###############################
	if ($udlign[$x]=='on') {
		$diff=$diff+$amount[$x];	
		if ($transdate[$x]>$udlign_date) $udlign_date=$transdate[$x];
	}


	
	
}
$postantal=$x;

$r = db_fetch_array(db_select("select * from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__));
$maxdiff=$r['box1']*1;
$diffkto=$r['box2']*1;
if (!$diffkto) $maxdiff=0;

print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
print "<tr><td colspan=8 align=center>";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=$retur?rapportart=Kontokort&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok>Luk</a></div></td>";
print "<td width=\"80%\" align=center><div class=\"top_bund\">Udlign &aring;bne poster<br></div></td>";
print "<td width=\"10%\"><div class=\"top_bund\"><br></div></td>";
print " </tr></tbody></table></td></tr>";
		
#		<a href=$retur?rapportart=Kontokort&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok>Luk</a></td></tr>";
print "<tr><td><br></td></tr>";
########################### UDLIGN ##########################
if ($submit=='udlign') {
	transaktion(begin);
	$query = db_select("select MAX(udlign_id) as udlign_id from openpost",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) $udlign_id=$row['udlign_id']+1;
	
	if ($diff && $diffkto) {
		
	if ($basisvaluta!='DKK') {
#		echo "Basis $basisvaluta Kurs $valutakurs[0]<br>";
		$dkkdiff=$diff*$valutakurs[0]/100;
	}	
	if (!$dkkdiff)$dkkdiff=$diff;	
	$dkkdiff=round($dkkdiff,2);
		$transdate=date("Y-m-d");
		$logdate=date("Y-m-d");
		$logtime=date("H:i");
		$diff=round($diff,2);
		$r=db_fetch_array(db_select("select kontonr, gruppe, art from adresser where id = '$konto_id[0]'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['kontonr'];
		$gruppe=trim($r['gruppe']);
		$art=trim($r['art']);
		if (substr($art,0,1)=='D') $art='DG';
		else $art='KG';
		$r=db_fetch_array(db_select("select box2 from grupper where art='$art' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
		$samlekonto=$r['box2'];
		$r=db_fetch_array(db_select("select max(regnskabsaar) as tmp from kontoplan",__FILE__ . " linje " . __LINE__));
		if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$samlekonto' and regnskabsaar='$r[tmp]'",__FILE__ . " linje " . __LINE__))) {
			$tekst=findtekst(177,$sprog_id);
			print "<BODY onLoad=\"javascript:alert('$tekst')\">";
		}
		$beskrivelse=findtekst(176,$sprog_id);
		if ($diff >= 0.01) {
#echo "insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values($diffkto, '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0'<br>";
			db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values('$diffkto', '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
#echo "insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0'<br>";
 			db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
			$tmp=$diff*-1;
#echo "insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs) values ('$konto_id[0]', '$kontonr', '$tmp', '$beskrivelse', '0', '$transdate', '0', '0','$basisvaluta','$valutakurs[0]')<br>";
			db_modify("insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values ('$konto_id[0]', '$kontonr', '$tmp', '$beskrivelse', '1', '$transdate', '0', '0','$basisvaluta','$valutakurs[0]','$udlign_id','$udlign_date')",__FILE__ . " linje " . __LINE__);
		} elseif ($diff <= -0.01) {
			$dkkdiff=$dkkdiff*-1;
#echo "insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values($diffkto, '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0'<br>";
			db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values($diffkto, '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
#echo "insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0'<br>";
			db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
			$tmp=$diff*-1;
#echo "insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs) values ('$konto_id[0]', '$kontonr', '$tmp', '$beskrivelse', '0', '$transdate', '0', '0','$basisvaluta','$valutakurs[0]')<br>";
			db_modify("insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values ('$konto_id[0]', '$kontonr', '$tmp', '$beskrivelse', '1', '$transdate', '0', '0','$basisvaluta','$valutakurs[0]','$udlign_id','$udlign_date')",__FILE__ . " linje " . __LINE__);
		}
	}
	for ($x=0; $x<=$postantal; $x++) {
		if ($udlign[$x]=='on') db_modify("UPDATE openpost set udlignet='1', udlign_id='$udlign_id', udlign_date='$udlign_date' where id = $post_id[$x]",__FILE__ . " linje " . __LINE__);
	}
	transaktion(commit);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapportart=Kontokort&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok\">";
}
#
if ($diff==0 || abs($diff)<$maxdiff) print "<tr><td colspan=6>F&oslash;lgende poster vil blive udlignet:</td></tr>";
else print "<tr><td colspan=6>S&aelig;t \"flueben\" ud for de posteringer der skal udligne f&oslash;lgende post:</td></tr>";
print "<tr><td colspan=6><br></td>";
print "<tr><td></td></tr><tr bgcolor=\"$linjebg\"><td>".dkdato($transdate[0])."</td>
	<td>$refnr[0]</td>
	<td>$faktnr[0]</td>
	<td>$beskrivelse[0]</td>
	<td align=right><span style='color: rgb(0, 0, 0);'>".dkdecimal($amount[0])."</td></tr>";
if ($diff!=0) print "<tr><td colspan=6><hr></td></tr>";
print "<form name=kontoudtog action=../includes/udlign_openpost.php method=post>";
if ($diff!=0) {
	for ($x=1; $x<=$postantal; $x++) {
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\"><td>".dkdato($transdate[$x])."</td>
			<td>$refnr[$x]</td>
			<td>$faktnr[$x]</td>
			<td>$beskrivelse[$x]</td>
			<td align=right><span style='color: rgb(0, 0, 0);'>".dkdecimal($amount[$x])."</td>";
		if ($udlign[$x]!='on') print "<td align=center><input type=checkbox name=udlign[$x]></td></tr>";
		else print "<td align=center><input type=checkbox name=udlign[$x] checked></td></tr>";
	}
} else {
	for ($x=1; $x<=$postantal; $x++) {
		if ($udlign[$x]=='on') {
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\"><td>".dkdato($transdate[$x])."</td>
				<td>$refnr[$x]</td>
				<td>$faktnr[$x]</td>
				<td>$beskrivelse[$x]</td>
				<td align=right><span style='color: rgb(0, 0, 0);'>".dkdecimal($amount[$x])."</td>";
			print "<input type = hidden name=udlign[$x] value=$udlign[$x]>";
		}
	}
}
print "<tr><td colspan=6><hr></td></tr>";
print "<tr><td colspan=3></td><td>Difference</td><td align=right>".dkdecimal($diff)."</td></tr>";
print "<tr><td colspan=6><hr></td></tr>";

print "<input type = hidden name=post_id[0] value=$post_id[0]>";
print "<input type = hidden name=maaned_fra value=$maaned_fra>";
print "<input type = hidden name=maaned_til value=$maaned_til>";
print "<input type = hidden name=konto_fra value=$konto_fra>";
print "<input type = hidden name=konto_til value=$konto_til>";
print "<input type = hidden name=regnaar value=$regnaar>";
print "<input type = hidden name=retur value=$retur>";
print "<input type = hidden name=diff value=$diff>";
print "<input type = hidden name=maxdiff value=$maxdiff>";
print "<input type = hidden name=diffkto value=$diffkto>";
if (abs($diff)<0.009) print "<tr><td colspan=10 align=center><span title=\"".findtekst(178,$sprog_id)."\"><input type=submit value=\"Udlign\" name=\"submit\"></span></td></tr>";
elseif (abs($diff)<$maxdiff) print "<tr><td colspan=10 align=center><span title=\"".findtekst(179,$sprog_id)."\"><input type=submit value=\"Udlign\" name=\"submit\"></span></td></tr>";
else print "<tr><td colspan=10 align=center><span title=\"".findtekst(180,$sprog_id)."\"><input type=submit value=\"Vis\" name=\"submit\"></span></td></tr>";
print "</form>\n";

?>

