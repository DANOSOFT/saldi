<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|

// ----------includes/udlign_openpost.php-------patch 4.0.8 ----2023-07-12---
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------

// 2012.11.06 Kontrol for aktivt regnskabsaar v. bogføring af ørediff Søg 20121106
// 2013.02.10 Diverse fejl v. bogføring af ørediff.
// 2013.02.23 endnu en fejl v. bogføring af ørediff. Søg 20130223
// 2013.05.05 Større omskrivning til at imødegå div. valutafejl.
// 2013.05.25 Fejl v. manglende forfaldsdate. Søg 20130525
// 2013.05.29 Fejl v. manglende omregningskurs. Søg 20130529
// 2013.11.29 Tilføjet && $valuta[$x]!='DKK'. Søg 20131129
// 2014.05.03 Indsat valutakurs=100 ved DKK.(PHR Danosoft) Søg 20140503
// 2014.05.05 Fjernet fejmelding og exit og sat bogføringsdato til dd. (PHR Danosoft) Søg 20140505
// 2014.05.05 Flyttet $dkkamount[$x]=$amount[$x]*$valutakurs[$x]/100; ned under if ($valuta[$x]=='DKK') $valutakurs[$x]=100; (PHR Danosoft) Søg 20140505
// 2014.05.27 indsat afrund forskellige steder for at undgå differ på 1 øre.(PHR Danosoft) Søg 20140527
// 2015.03.01 Kontrol for at diff ikke overstiger maxdiff da det er lykkes for sundkiosken at oprette diffposteringer uden årsag 20150311
// 2015.09.07	Fejl i rettelse fra 20130529. =! rettet til !=.
// 2016.04.12 PHR !='DKK' rettet =='DKK' da maxdiff ikke relaterer til valutadiff.
// 2016.04.14 PHR fjernet - foran 100 da der blev reguleret omvendt! #20160414
// 2016.04.26 PHR '==' rettet til '='.  #20160426-1
// 2016.04.26 PHR Indsat 'desc limit 1'. #20160426-2
// 2016.04.26	PHR Rettet $diff til $tmp.  #20160426-3
// 2016.10.28	PHR Rettet < til <=  da den gav posteringsdifference #20161028 

@session_start();
$s_id=session_id();

$modulnr=12;
$kontonr=array();$post_id=array();
$linjebg=$sum=NULL;
$title="&Aring;benpostudligning";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");

if (isset($_POST['submit'])) {
 	$submit=strtolower(trim($_POST['submit']));
	$post_id=if_isset($_POST['post_id']);
	$konto_id=if_isset($_POST['konto_id']);
	$udlign=if_isset($_POST['udlign']);
	(isset($_POST['kontrol']))?$kontrol = $_POST['kontrol']:$kontrol = array();
	$dato_fra=$_POST['dato_fra'];
	$dato_til=$_POST['dato_til'];
	$konto_fra=$_POST['konto_fra'];
	$konto_til=$_POST['konto_til']; 
	$retur=$_POST['retur'];
	$returside=$_POST['returside'];
	$diff=$_POST['diff'];
	$dkkdiff=$_POST['dkkdiff'];
	$maxdiff=$_POST['maxdiff'];
	$diffkto=$_POST['diffkto'];
#cho "Diff ".round($diff,3)." DkkDiff ".round($dkkdiff,3)."<br>";
	$diffdato=if_isset($_POST['diffdato']);
	($diffdato)?$diffDate=usdate($diffdato):$diffDate=NULL;
	$diffbilag=$_POST['diffbilag'];
	$faktnr=$_POST['faktnr'];
	$amount=$_POST['amount'];
	$basisvaluta=$_POST['basisvaluta'];
	$valuta=$_POST['valuta'];
	$omregningskurs=$_POST['omregningskurs'];
	$belob=if_isset($_POST['belob']);
	if ($belob) $ny_amount = usdecimal($belob);
	else $ny_amount = 0;
	$faktnr[0]=trim($faktnr[0]);
	db_modify("update openpost set faktnr='$faktnr[0]' where id = '$post_id[0]'",__FILE__ . " linje " . __LINE__	);
	if ($submit=='udlign') {
		for($x=1;$x<=count($kontrol);$x++) {
			if ($udlign[$x] && !$kontrol[$x]) $submit="opdater";
			if (!$udlign[$x] && $kontrol[$x]) $submit="opdater";
		}
	}
	if (afrund($ny_amount,2) != afrund($amount[0],2)) {
		$alerttekst="";
		if (($amount[0]>0 && $amount[0]-$ny_amount>0) || ($amount[0]<0 && $amount[0]-$ny_amount<0)) {
			if (trim($faktnr[0])) {
				if ($basisvaluta!=$valuta[0] && $omregningskurs[0]) { #20130529 indsat && $omregningskurs[0]
					$ny_amount=afrund($ny_amount/$omregningskurs[0],2);
					$amount[0]=afrund($amount[0]/$omregningskurs[0],2);
				}
				$tmp=$amount[0]-$ny_amount;
				if ($r=db_fetch_array(db_select("select * from openpost where id='$post_id[0]'",__FILE__ . " linje " . __LINE__))) {
					$r['bilag_id']*=1; 
					if ($r['forfaldsdate']) $qtxt="insert into openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,kladde_id,udlign_id,valuta,valutakurs,bilag_id,projekt,forfaldsdate) values ('$r[konto_id]','$r[konto_nr]','','$tmp','$r[refnr]','$r[beskrivelse]','0','$r[transdate]','$r[kladde_id]','0','$r[valuta]','$r[valutakurs]','$r[bilag_id]','$r[projekt]','$r[forfaldsdate]')";
					else $qtxt="insert into openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,kladde_id,udlign_id,valuta,valutakurs,bilag_id,projekt) values ('$r[konto_id]','$r[konto_nr]','','$tmp','$r[refnr]','$r[beskrivelse]','0','$r[transdate]','$r[kladde_id]','0','$r[valuta]','$r[valutakurs]','$r[bilag_id]','$r[projekt]')"; #20130525
					db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt="update openpost set amount='$ny_amount' where id = '$post_id[0]'<br>";
					db_modify ("update openpost set amount='$ny_amount' where id = '$post_id[0]'",__FILE__ . " linje " . __LINE__);
				} else $alerttekst="Fakturanummer ikke gyldigt, postering ikke opsplittet";
			}	else $alerttekst="For at opsplitte en betaling skal posteringen tilknyttes et gyldigt fakturanummer";
		}	else $alerttekst="Bel&oslash;b m&aring; ikke &oslash;ges";
		if ($alerttekst) print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
	} 
} else {
	$post_id[0]=$_GET['post_id']*1;
	$dato_fra=$_GET['dato_fra'];
	$dato_til=$_GET['dato_til'];
	$konto_fra=$_GET['konto_fra'];
	$konto_til=$_GET['konto_til']; 
	$retur=$_GET['retur'];
	$returside=$_GET['returside'];
}

$query = db_select("select * from openpost where id='$post_id[0]'",__FILE__ . " linje " . __LINE__); #$post_id[0] er den post som skal udlignes.
if ($row = db_fetch_array($query)) {
	$konto_id[0]=$row['konto_id']*1;
	$refnr[0]=$row['refnr'];
	$amount[0]=afrund($row['amount'],2); #20140527
	$sum=$sum;
	$transdate[0]=$row['transdate'];
	$udligndate=$transdate[0];
	$faktnr[0]=$row['faktnr'];
	$kontonr[0]=$row['konto_nr'];
	$beskrivelse[0]=$row['beskrivelse'];
	$valuta[0]=$row['valuta'];
	$valutakurs[0]=$row['valutakurs']*1;
	if (!$valuta[0]) {
		$valuta[0]='DKK';
		$valutakurs[0]=100; #20140503
	}
	if (!$valutakurs[0]) $valutakurs[0]=100;
	$dkkamount[0]=afrund($amount[0]*$valutakurs[0]/100,2); #20140527
	$dkksum=$dkkamount[0];
	$dkkdiff=$dkksum;
	if ($valuta[0]!='DKK') {
		$beskrivelse[0].=" (DKK: ".dkdecimal($dkkamount[0]).", kurs: $valutakurs[0])"; 
	}
	$udlign[0]='on';
	print "<input type = hidden name=konto_id[0] value=$konto_id[0]>";
} else print "<meta http-equiv=\"refresh\" content=\";URL=$retur?rapport=accountChart.php\">";
$konto_id[0]*=1;
$r = db_fetch_array(db_select("select * from adresser where id=$konto_id[0]",__FILE__ . " linje " . __LINE__)); #Finder kontoinfo
$betalingsbet=trim($r['betalingsbet']);
$betalingsdage=$r['betalingsdage'];
$art=substr($r['art'],0,1)."G";
$r2 = db_fetch_array(db_select("select box3 from grupper where art='$art' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__)); # Finder valuta for konto.
$basisvaluta=trim($r2['box3']);
$r2=db_fetch_array(db_select("select box2 from grupper where art ='VK' and box1='$basisvaluta'",__FILE__ . " linje " . __LINE__)); # Finder valutakurs for konto.
$basiskurs=str_replace(",",".",$r2['box2']); #Valutaen kan være i dansk talformat (BUG).
#	cho "$valuta[0] basisvaluta $basisvaluta basiskurs $basiskurs<br>";
if ($basisvaluta=='DKK') $basiskurs=100; 
#cho "$basisvaluta != $valuta[0] | $basiskurs = 100<br>";
if ($basisvaluta != $valuta[0]) {
#cho "$basisvaluta != $valuta[0]<br>";
	if ($valuta[0]=='DKK') {
		$qtxt="select kodenr from grupper where box1 = '$basisvaluta' and art='VK'<br>";
		$r2=db_fetch_array(db_select("select kodenr from grupper where box1 = '$basisvaluta' and art='VK'",__FILE__ . " linje " . __LINE__));
		$qtxt="select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[0]' order by valdate desc limit 1"; #20160426-2
#cho "$db - $qtxt<br>";
		$r3=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r3['kurs']) $omregningskurs[0]=100/$r3['kurs'];
			$amount[0]=afrund($amount[0]*$omregningskurs[0],2); #20140527
			$sum=$amount[0];
	} elseif ($valuta[0] != $basisvaluta && $basisvaluta!='DKK') {
#cho "$valuta[0] != $basisvaluta && $basisvaluta!='DKK'<br>";
		$r2=db_fetch_array(db_select("select kodenr from grupper where box1 = '$basisvaluta' and art='VK'",__FILE__ . " linje " . __LINE__));
		$r2=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[0]' order by valdate desc",__FILE__ . " linje " . __LINE__));
		$dagskurs=$r2['kurs']*1;
		$beskrivelse[0].=" $valuta[0] ".dkdecimal($amount[0])." Kurs $valutakurs[0]";
		$amount[0]*=$valutakurs[0]/$dagskurs;
		$dkkamount[0]=$amount[0]*$valutakurs[0]/100;
	} elseif ($basisvaluta=='DKK') {
#cho "$basisvaluta=='DKK'<br>";
		$omregningskurs[0]=$valutakurs[0]/100;
		$amount[0]=$dkkamount[0];
		$sum=$amount[0];
	} else {
		$sum=$amount[0];
	}
}
$sum=$amount[0];
$diff=$sum;
$titlesum=$sum;

$konto_id[0]*=1;
$udlign_date="$transdate[0]";
$x=0;
$qtxt="select * from openpost where id!='$post_id[0]' and konto_id='$konto_id[0]' and udlignet != '1' order by transdate";
#cho "$qtxt<br>";
$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)){
	$x++;
	$post_id[$x]=$row['id'];
	$refnr[$x]=$row['refnr'];
	$amount[$x]=$row['amount'];
	$transdate[$x]=$row['transdate'];
	$faktnr[$x]=$row['faktnr'];
	$kontonr[$x]=$row['konto_nr'];
	$beskrivelse[$x]=$row['beskrivelse'];
	$valuta[$x]=$row['valuta'];
	$valutakurs[$x]=$row['valutakurs']*1;
	if (!$valuta[$x]) $valuta[$x]='DKK';
	if ($valuta[$x]=='DKK') $valutakurs[$x]=100;
	$dkkamount[$x]=afrund($amount[$x]*$valutakurs[$x]/100,2); #20140505
	$dkksum+=$dkkamount[$x];
	if ($valuta[$x]!='-' && $valuta[$x]!='DKK' && ($valutakurs[$x]==100 || !$valutakurs[$x])) {
		$qtxt="select kodenr from grupper where box1 = '$valuta[$x]' and art='VK'";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$qtxt="select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[$x]' order by valdate";
		$r3=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$valutakurs[$x]=$r3['kurs'];
		$dkkamount[$x]=$amount[$x]*100/$r3['kurs'];
	}
	$beskrivelse[$x].=" (DKK ".dkdecimal($dkkamount[$x]).")"; 
	if ($valuta[$x]!='DKK' && $basisvaluta == 'DKK') {
		$beskrivelse[$x].=" ($valuta[$x] ".dkdecimal($amount[$x])." kurs: ".dkdecimal($valutakurs[$x]).")"; 
		$amount[$x]=$dkkamount[$x];
		} elseif ($basisvaluta != 'DKK' && $basisvaluta != $valuta[$x]) {
		$qtxt="select kodenr from grupper where box1 = '$basisvaluta' and art='VK'";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$qtxt="select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[$x]' order by valdate desc";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$dagskurs=$r2['kurs']*1;
#		$beskrivelse[$x].=" $valuta[$x] ".dkdecimal($amount[$x])." kurs: ".dkdecimal($valutakurs[$x]).")";
		$amount[$x]*=$valutakurs[$x]/$dagskurs;
		$dkkamount[$x]=$amount[$x]*$dagskurs/100;
	} elseif ($basisvaluta == 'DKK' && $basisvaluta == 'DKK') {
			$amount[$x]=$dkkamount[$x];
		} elseif ($valuta[$x] != $basisvaluta) {
		$qtxt="select kodenr from grupper where box1 = '$basisvaluta' and art='VK'";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$qtxt="select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[$x]' order by valdate desc";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$dagskurs=$r2['kurs']*1;
		$beskrivelse[$x].=" $valuta[$x] ".dkdecimal($amount[$x])." kurs: ".dkdecimal($valutakurs[$x]).")";
		$amount[$x]*=$valutakurs[$x]/$dagskurs;
		$dkkamount[$x]=$amount[$x]*$valutakurs[$x]/100;
	}
	$amount[$x]=afrund($amount[$x],2); #20140527
	$dkkamount[$x]=afrund($dkkamount[$x],2); #20140527
	$sum+=$amount[$x];
	if (isset($udlign[$x]) && $udlign[$x]=='on') {
		if ($transdate[$x]>$udlign_date) $udlign_date=$transdate[$x];
		$diff+=$amount[$x];
		$dkkdiff+=$dkkamount[$x];
	}
}
$postantal=$x;
$r = db_fetch_array(db_select("select * from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__));
$maxdiff=$r['box1']*1;
$diffkto=$r['box2']*1;
if (!$diffkto) $maxdiff=0;

#cho __line__." SUB $submit<br>";
if (!isset($submit)) include ("../includes/alignOpenpostIncludes/findMatch.php");

print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
print "<tr><td colspan=8 align=center>";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=$retur?rapportart=accountChart&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&submit=ok>Luk</a></div></td>";
print "<td width=\"80%\" align=center><div class=\"top_bund\">Udlign &aring;bne poster<br></div></td>";
print "<td width=\"10%\"><div class=\"top_bund\"><br></div></td>";
print " </tr></tbody></table></td></tr>";
		
print "<tr><td><br></td></tr>";
if (isset($submit) && $submit=='udlign') {
	include ("../includes/alignOpenpostIncludes/doAlign.php");
}
print "<form name='alignOpenpost' action='../includes/udlign_openpost.php' method='post'>";
if ($diff==0 || abs($diff)<$maxdiff) print "<tr><td colspan=6>F&oslash;lgende poster vil blive udlignet:</td></tr>";
else print "<tr><td colspan=6>S&aelig;t \"flueben\" ud for de posteringer der skal udligne f&oslash;lgende post:</td></tr>";
print "<tr><td colspan=6><br></td>";
print "<tr><td>Dato</td><td>Bilag nr.</td><td>Fakturanummer</td><td>Beskrivelse</td><td align= right>Bel&oslash;b</td></tr>";
print "<tr><td colspan=6><br></td>";
print "<tr><td></td></tr><tr bgcolor=\"$linjebg\"><td>".dkdato($transdate[0])."</td><td>$refnr[0]</td>";
$spantekst="Skriv fakturanummer p&aring; den faktura som denne betaling vedr&oslash;rer.\nP&aring; forfaldslisten vil det forfaldne bel&oslash;b reduceres tilsvarende.";
if ($art=='DG' && $amount[0] < 0) print "<td title='$spantekst'><input class=\"inputbox\" type = \"text\" style=\"text-align:left;width:90px;\" name=faktnr[0] value = \"$faktnr[0]\"></td>";
elseif ($art=='KG') print "<td title='$spantekst'><input class=\"inputbox\" type = \"text\" style=\"text-align:left;width:90px;\" name=faktnr[0] value = \"$faktnr[0]\"></td>";
else {
	print "<td>$faktnr[0]</td>";
	print "<input type=\"hidden\" name=\"faktnr[0]\" value = \"$faktnr[0]\">";
}
#cho "amount $amount[0] dkkamount $dkkamount[0]<br>";
$spantekst="Hvis der skrives et andet bel&oslash;b i dette felt, kan posteringen splittes i 2. Kr&aelig;ver at der er påf&oslash;rt fakturanummer";
print "<td>$beskrivelse[0]</td><td align=right  title='$spantekst'><span style='color: rgb(0, 0, 0);'>";
if (($art=='DG' && $amount[0] < 0) || ($art=='KG' && $amount[0] > 0))	print "<input  class=\"inputbox\" type = \"text\" style=\"text-align:right;width:90px;\" name=belob value =\"".dkdecimal($amount[0])."\"></td></tr>";
else print dkdecimal($amount[0])."<input type=hidden name=belob value =\"".dkdecimal($amount[0])."\"></td></tr>";
if ($diff!=0) print "<tr><td colspan=6><hr></td></tr>";
if ($diff!=0) {
	for ($x=1; $x<count($post_id); $x++) {
	$titlesum+=$amount[$x];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\"><td>".dkdato($transdate[$x])."</td>
			<td>$refnr[$x]</td>
			<td>$faktnr[$x]</td>
			<td>$beskrivelse[$x]</td>
			<td align=right><span style=\"color: rgb(0, 0, 0);\" title=\"sum: ".dkdecimal($titlesum)."\">".dkdecimal($amount[$x])."</td>";
		if (isset($udlign[$x]) && $udlign[$x]=='on') {
			$udlign[$x]="checked";
			if($transdate[$x]>$udligndate) $udligndate=$transdate[$x]; 
		}	else $udlign[$x]=NULL;
		print "<td align=center><input type=\"checkbox\" name=\"udlign[$x]\" $udlign[$x]></td></tr>";
		print "<input type=\"hidden\" name=\"kontrol[$x]\" value=\"$udlign[$x]\"></td></tr>";
	}
} else {
	for ($x=1; $x<count($post_id); $x++) {
		if (isset($udlign[$x]) && $udlign[$x]=='on') {
			if($transdate[$x]>$udligndate) $udligndate=$transdate[$x]; 
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\"><td>".dkdato($transdate[$x])."</td>
				<td>$refnr[$x]</td>
				<td>$faktnr[$x]</td>
				<td>$beskrivelse[$x]</td>
				<td align=right><span style=\"color: rgb(0, 0, 0);\" title=\"sum: ".dkdecimal($titlesum)."\">".dkdecimal($amount[$x])."</span></td>";
			print "<input type = hidden name=udlign[$x] value=$udlign[$x]>";
		}
	}
}
if (!$diffDate) $diffDate=$udligndate;
$diffdato=dkdato($diffDate);
$diffbilag*=1;
print "<tr><td colspan=6><hr></td></tr>";
if (abs($dkkdiff)<$maxdiff || abs($diff)<0.009) {
	print "<tr><td colspan=\"1\"><input class=\"inputbox\" style=\"width:90px;\" type=\"text\" name=\"diffdato\" value=\"$diffdato\"</td>";
	print "<td colspan=\"1\"><input class=\"inputbox\" style=\"width:50px;text-align:right;\" type=\"text\" name=\"diffbilag\" value=\"$diffbilag\"</td>";
	print "<td colspan=\"1\"></td><td>Difference (DKK ".dkdecimal($dkkdiff).")</td>";
} else {
	print "<td colspan=\"3\"></td><td>Difference (DKK ".dkdecimal($dkkdiff).")</td>";
}
print "<td align=right>".dkdecimal($diff)."</td></tr>";
print "<tr><td colspan=6><hr></td></tr>";
print "<input type = hidden name=omregningskurs[0] value=$omregningskurs[0]>";
print "<input type = hidden name=konto_id[0] value=$konto_id[0]>";
print "<input type = hidden name=post_id[0] value=$post_id[0]>";
print "<input type = hidden name=amount[0] value=$amount[0]>";
print "<input type = hidden name=dato_fra value=$dato_fra>";
print "<input type = hidden name=dato_til value=$dato_til>";
print "<input type = hidden name=konto_fra value=$konto_fra>";
print "<input type = hidden name=konto_til value=$konto_til>";
print "<input type = hidden name=retur value=$retur>";
print "<input type = hidden name=returside value=$returside>";
print "<input type = hidden name=diff value=$diff>";
print "<input type = hidden name=dkkdiff value=$dkkdiff>";
print "<input type = hidden name=maxdiff value=$maxdiff>";
print "<input type = hidden name=diffkto value=$diffkto>";
print "<input type = hidden name=valuta[0] value=$valuta[0]>";
print "<input type = hidden name=basisvaluta value=$basisvaluta>";
print "<tr><td colspan=10 align=center>";

$onclick='';

#cho round($diff,3)." != ".round($dkkdiff,3)." $diffDate<br>";

if ($diff != $dkkdiff && $bogfor!='OK' && $dkkdiff >= 0.005) {
	$txt="Der vil blive bogført en valutadifference på dkk ".dkdecimal($dkkdiff)."\\nKlik OK for at godkende, eller klik Cancel for at afbryde ";
	$onclick= "onclick=\"return confirm('$txt')\"";
	print "<input type=\"hidden\" name=\"stop\" value=\"on\">";
}

if (abs($diff)<0.005) print "<span title=\"".findtekst(178,$sprog_id)."\"><input type=\"submit\"  $onclick style=\"width:100px\" value=\"Udlign\" name=\"submit\"></span>&nbsp;";
elseif (abs($dkkdiff)<$maxdiff) {
	print "<span title=\"".findtekst(179,$sprog_id)."\"><input type=\"submit\" $onclick style=\"width:100px\" value=\"Udlign\" name=\"submit\"></span>&nbsp;";
}
print "<span title=\"".findtekst(180,$sprog_id)."\"><input type=\"submit\" style=\"width:100px\" value=\"Opdater\" name=\"submit\"></span>";
print "</td></tr></form>\n";

?>

