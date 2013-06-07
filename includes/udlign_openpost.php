<?php
@session_start();
$s_id=session_id();
// ------------includes/udlign_openpost.php-------patch 3.2.9----2013-05-29--------
// LICENS>
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

// 2012.11.06 Kontrol for aktivt regnskabsaar v. bogføring af ørediff Søg 20121106
// 2013.02.10 Diverse fejl v. bogføring af ørediff.
// 2013.02.23 endnu en fejl v. bogføring af ørediff. Søg 20130223
// 2013.05.05 Større omskrivning til at imødegå div. valutafejl.
// 2013.05.25 Fejl v. manglende forfaldsdate. Søg 20130525
// 2013.05.29 Fejl v. manglende omregningskurs. Søg 20130529

$modulnr=12;
$kontonr=array();$post_id=array();
$linjebg=NULL;
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
	$kontrol=if_isset($_POST['kontrol']);
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
	$diffdato=if_isset($_POST['diffdato']);
	($diffdato)?$diffdate=usdate($diffdato):$diffdate=NULL;
#cho "diffdato ".$_POST['diffdato']."<br>";	
	$diffbilag=$_POST['diffbilag'];
	$faktnr=$_POST['faktnr'];
	$amount=$_POST['amount'];
#cho "anount0 $amount[0]<br>";	
	$basisvaluta=$_POST['basisvaluta'];
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
	#cho "Ny $ny_amount != $amount[0]<br>";
		$alerttekst="";
		if (($amount[0]>0 && $amount[0]-$ny_amount>0) || ($amount[0]<0 && $amount[0]-$ny_amount<0)) {
				if (trim($faktnr[0])) {
				#cho "$ny_amount=$ny_amount/$omregningskurs[0]<br>";
					if ($basisvaluta=!$valuta[0] && $omregningskurs[0]) { #20130529 indsat && $omregningskurs[0]
#cho "$amount[0]=afrund($amount[0]/$omregningskurs[0],2)<br>";
						$ny_amount=afrund($ny_amount/$omregningskurs[0],2);
						$amount[0]=afrund($amount[0]/$omregningskurs[0],2);
					}
	#cho "Ny $ny_amount != $amount[0]<br>";
	$tmp=$amount[0]-$ny_amount;

			#cho "select * from openpost where id='$post_id[0]'<br>";
				if ($r=db_fetch_array(db_select("select * from openpost where id='$post_id[0]'",__FILE__ . " linje " . __LINE__))) { 
					if ($r['forfaldsdate']) $qtxt="insert into openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,kladde_id,udlign_id,valuta,valutakurs,bilag_id,forfaldsdate) values ('$r[konto_id]','$r[konto_nr]','','$tmp','$r[refnr]','$r[beskrivelse]','0','$r[transdate]','$r[kladde_id]','0','$r[valuta]','$r[valutakurs]','$r[bilag_id]','$r[forfaldsdate]')";
					else $qtxt="insert into openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,kladde_id,udlign_id,valuta,valutakurs,bilag_id) values ('$r[konto_id]','$r[konto_nr]','','$tmp','$r[refnr]','$r[beskrivelse]','0','$r[transdate]','$r[kladde_id]','0','$r[valuta]','$r[valutakurs]','$r[bilag_id]')"; #20130525
#cho "qtxt $qtxt<br>";
					db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
		#			$r=db_select(db_fetch_array("select konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,kladde_id,udlign_id,udlign_date,valuta,valutakurs,bilag_id,forfaldsdate from openpost where id='$post_id[0]'",__FILE__ . " linje " . __LINE__);
					db_modify ("update openpost set amount='$ny_amount' where id = '$post_id[0]'",__FILE__ . " linje " . __LINE__);
#					$r=db_fetch_array(db_select("select max(id) as id from openpost where faktnr='$faktnr[0]' and amount='$amount[0]'",__FILE__ . " linje " . __LINE__));
#					db_modify ("update openpost set faktnr='',amount='$tmp' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
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


#cho "select * from openpost where id='$post_id[0]'<br>";
$query = db_select("select * from openpost where id='$post_id[0]'",__FILE__ . " linje " . __LINE__); #$post_id[0] er den post som skal udlignes.
if ($row = db_fetch_array($query)) {
	$konto_id[0]=$row['konto_id']*1;
	$refnr[0]=$row['refnr'];
	$amount[0]=$row['amount'];
	$sum=$sum;
#cho "amount0  $amount[0]<br>";
	#cho "Sum $sum<br>";	
#	$diff=$sum;
	$transdate[0]=$row['transdate'];
	$udligndate=$transdate[0];
	$faktnr[0]=$row['faktnr'];
	$kontonr[0]=$row['konto_nr'];
	$beskrivelse[0]=$row['beskrivelse'];
	$valuta[0]=$row['valuta'];
	if (!$valuta[0])$valuta[0]='DKK';
	$valutakurs[0]=$row['valutakurs']*1;
	if (!$valutakurs[0]) $valutakurs[0]=100;
	$dkkamount[0]=afrund($amount[0]*$valutakurs[0]/100,2);  
#	#cho "DKK 0 $dkkamount[0]<br>";
	$dkksum=$dkkamount[0];
	$dkkdiff=$dkksum;
	if ($valuta[0]!='DKK') {
		$beskrivelse[0].=" (DKK: ".dkdecimal($dkkamount[0]).", kurs: $valutakurs[0])"; 
	}
	$udlign[0]='on';
	print "<input type = hidden name=konto_id[0] value=$konto_id[0]>";
} else print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapport=kontokort.php\">";
#cho "select * from adresser where id=$konto_id[0]<br>";
$konto_id[0]*=1;
$r = db_fetch_array(db_select("select * from adresser where id=$konto_id[0]",__FILE__ . " linje " . __LINE__)); #Finder kontoinfo
$betalingsbet=trim($r['betalingsbet']);
$betalingsdage=$r['betalingsdage'];
$art=substr($r['art'],0,1)."G";
#cho "select box3 from grupper where art='$art' and kodenr='$r[gruppe]'<br>";
$r2 = db_fetch_array(db_select("select box3 from grupper where art='$art' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__)); # Finder valuta for konto.
$basisvaluta=trim($r2['box3']);
#cho "select box2 from grupper where art ='VK' and box1='$basisvaluta'<br>";
$r2=db_fetch_array(db_select("select box2 from grupper where art ='VK' and box1='$basisvaluta'",__FILE__ . " linje " . __LINE__)); # Finder valutakurs for konto. 
$basiskurs=str_replace(",",".",$r2['box2']); #Valutaen kan være i dansk talformat (BUG).
if ($basisvaluta=='DKK') $basiskurs=100; 
#cho "BV $basisvaluta BK $basiskurs<br>";
#cho "Am $amount[0] V $valuta[0] VK $valutakurs[0]<br>";
#cho "($basisvaluta != $valuta[0])<br>";
if ($basisvaluta != $valuta[0]) {
	if ($valuta[0]=='DKK') {
#cho "select kodenr from grupper where box1 = '$valuta[0]' and art='VK'<br>";
		$r2 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$basisvaluta' and art='VK'",__FILE__ . " linje " . __LINE__));
#cho "select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[0]' order by valdate<br>";
		$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[0]' order by valdate",__FILE__ . " linje " . __LINE__));
#cho "$amount[0] => ";
			$omregningskurs[0]=100/$r3['kurs'];
			$amount[0]=afrund($amount[0]*$omregningskurs[0],2);
#cho "x $amount[0]<br>";
			$sum=$amount[0];
	} elseif ($valuta[0] != $basisvaluta && $basisvaluta!='DKK') {
#cho "select kodenr from grupper where box1 = '$basisvaluta' and art='VK'<br>";
		$r2=db_fetch_array(db_select("select kodenr from grupper where box1 = '$basisvaluta' and art='VK'",__FILE__ . " linje " . __LINE__));
#cho "select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[0]' order by valdate desc<br>";
		$r2=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[0]' order by valdate desc",__FILE__ . " linje " . __LINE__));
		$dagskurs=$r2['kurs']*1;
#cho "$dagskurs<br>";
		$beskrivelse[0].=" $valuta[0] ".dkdecimal($amount[0])." Kurs $valutakurs[0]";
		$amount[0]*=$valutakurs[0]/$dagskurs;
		$dkkamount[0]=$amount[0]*$valutakurs[0]/100;
	} elseif ($basisvaluta=='DKK') {
		$amount[0]=$dkkamount[0];
		$sum=$amount[0];
	} else {
#		$amount[0]=$dkkamount[0];
		$sum=$amount[0];
	}
}
$sum=$amount[0];
$diff=$sum;

#cho "amount[0] $amount[0] <br>";

#cho "diff $diff<br>";

#cho "Linje 170: $dkksum V:$valuta[0] K:$valutakurs[0] sum $sum<br>";
$titlesum=$sum;

#if (!$basisvaluta || $basisvaluta=='DKK') { #Kontoen er i DKK
#	$basisvaluta='DKK';
#	$basiskurs='100';
#	$dkkamount[0]=$amount[0];
#} 
#else { #Kontoen er i anden valuta og valutakoden findes??? (Hvorfor det? den har vi jo)
#	$r2 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$basisvaluta' and art='VK'",__FILE__ . " linje " . __LINE__));
#	$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[0]' order by valdate",__FILE__ . " linje " . __LINE__));
#	$basiskurs=$r3['kurs'];
#	
#	#cho "Basikurs $basiskurs Kode $valutakode[0]<br>";
#}

##cho "$valuta[0]!='DKK'<br>";
#if ($valuta[0]!='DKK') { # Kursen for transaktionen findes.
##cho "select box2 from grupper where art ='VK' and box1='$valuta[0]'<br>";
#	$r2=db_fetch_array(db_select("select kodenr from grupper where art ='VK' and box1='$valuta[0]'",__FILE__ . " linje " . __LINE__)); # Finder valutakoden for valutaen. 
#	$valutakode[0]=$r2['kodenr'];
##cho "select kurs from valuta where gruppe ='$valutakode[0]' and valdate <= '$transdate[0]' order by valdate desc<br>";
#	$r2=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode[0]' and valdate <= '$transdate[0]' order by valdate desc",__FILE__ . " linje " . __LINE__)); # Finder valutakursen.
#	$valutakurs[0]=$r2['kurs'];
#	#cho "Transaktionskurs $transdate[0] Kurs $valutakurs[0]<br>";
#} 
#cho "A $amount[0]<br>"; 
#		$dkkamount[0]=afrund($amount[0]*$valutakurs[0]/100,2);  
##cho "$post_id[0] DKK $dkkamount[0] USD $amount[0]<br>";

		#cho "B $amount[0]<br>"; 
#} 
# if ($valuta[0]!=$basisvaluta && $basisvaluta!="DKK") $amount[0]=afrund($amount[0]*$valutakurs[0]/100,2);

# if ($basisvaluta!='DKK') $$dkksum=afrund($amount[0]*$valutakurs[0]/100,2);

#cho "$diff | $dkkdiff<br>";

$konto_id[0]*=1;
$udlign_date="$transdate[0]";
$x=0;

#cho "select * from openpost where id!='$post_id[0]' and konto_id='$konto_id[0]' and udlignet != '1' order by transdate<br>";
$query = db_select("select * from openpost where id!='$post_id[0]' and konto_id='$konto_id[0]' and udlignet != '1' order by transdate",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)){
#cho "A dkkdiff $dkkdiff<br>";
	$x++;
	$post_id[$x]=$row['id'];
#cho $post_id[$x]."<br>";	
	$refnr[$x]=$row['refnr'];
#	$amount[$x]=afrund($row['amount'],2);
	$amount[$x]=$row['amount'];
	$transdate[$x]=$row['transdate'];
	$faktnr[$x]=$row['faktnr'];
	$kontonr[$x]=$row['konto_nr'];
	$beskrivelse[$x]=$row['beskrivelse'];
	$valuta[$x]=$row['valuta'];
	$valutakurs[$x]=$row['valutakurs']*1;
#cho "227: $valutakurs[$x]<br>";
	$dkkamount[$x]=$amount[$x]*$valutakurs[$x]/100;
	$dkksum+=$dkkamount[$x];
	if (!$valuta[$x]) $valuta[$x]='DKK';
	if ($valuta[$x]=='DKK') $valutakurs[$x]=100;
#cho "231: $valutakurs[$x]<br>";
#	if (!$valutakurs[$x] && $valuta[$x]) $valutakurs[$x]=100;
	if ($valuta[$x]!='DKK' && ($valutakurs[$x]==100 || !$valutakurs[$x])) {
#cho "select kodenr from grupper where box1 = '$valuta[$x]' and art='VK'<br>";
		$r2 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta[$x]' and art='VK'",__FILE__ . " linje " . __LINE__));
#cho "select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[$x]' order by valdate<br>";
		$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[$x]' order by valdate",__FILE__ . " linje " . __LINE__));
#cho "$valutakurs[$x]=100/$r3[kurs]<br>";
		$valutakurs[$x]=$r3['kurs'];
		$dkkamount[$x]=$amount[$x]*100/$r3['kurs'];
		#cho "237: $valutakurs[$x]<br>";
	}
	$beskrivelse[$x].=" (DKK ".dkdecimal($dkkamount[$x]).")"; 
#cho "A $basisvaluta != 'DKK' && $basisvaluta != $valuta[$x]<br>";
	if ($valuta[$x]!='DKK' && $basisvaluta == 'DKK') {
		$beskrivelse[$x].=" ($valuta[$x] ".dkdecimal($amount[$x])." kurs: ".dkdecimal($valutakurs[$x]).")"; 
		$amount[$x]=$dkkamount[$x];
#cho "249 -- $beskrivelse[$x]<br>";
		} elseif ($basisvaluta != 'DKK' && $basisvaluta != $valuta[$x]) {
#cho 	"$basisvaluta	<==> $valuta[$x]<br>";
#cho "select kodenr from grupper where box1 = '$basisvaluta' and art='VK'<br>";
		$r2=db_fetch_array(db_select("select kodenr from grupper where box1 = '$basisvaluta' and art='VK'",__FILE__ . " linje " . __LINE__));
#cho "select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[$x]' order by valdate desc<br>";
		$r2=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[$x]' order by valdate desc",__FILE__ . " linje " . __LINE__));

		
		$dagskurs=$r2['kurs']*1;
#cho "$dagskurs<br>";
		$beskrivelse[$x].=" $valuta[$x] ".dkdecimal($amount[$x])." kurs: ".dkdecimal($valutakurs[$x]).")";
		$amount[$x]*=$valutakurs[$x]/$dagskurs;
		$dkkamount[$x]=$amount[$x]*$dagskurs/100;
#cho "DKK $dkkamount[$x]<br>";
		
		
		
		
		#cho "kurs $r3[kurs]<br>";
#	$beskrivelse[$x].=" ($valuta[$x] $amount[$x] kurs: $valutakurs[$x])";
#	$omregningskurs[$x]=100/$r3['kurs'];
		# $amount[$x]=$amount[$x]*$omregningskurs[$x];

	} elseif ($basisvaluta == 'DKK' && $basisvaluta == 'DKK') {
#cho "$amount[$x]=$dkkamount[$x]<br>";
		$amount[$x]=$dkkamount[$x];
#cho "$amount[$x]=$dkkamount[$x]<br>";
	} elseif ($valuta[$x] != $basisvaluta) {
#cho "select kodenr from grupper where box1 = '$basisvaluta' and art='VK'<br>";
		$r2=db_fetch_array(db_select("select kodenr from grupper where box1 = '$basisvaluta' and art='VK'",__FILE__ . " linje " . __LINE__));
#cho "select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[$x]' order by valdate desc<br>";
		$r2=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r2[kodenr]' and valdate <= '$transdate[$x]' order by valdate desc",__FILE__ . " linje " . __LINE__));
		$dagskurs=$r2['kurs']*1;
		$beskrivelse[$x].=" $valuta[$x] ".dkdecimal($amount[$x])." kurs: ".dkdecimal($valutakurs[$x]).")";
#cho "zzzzz $amount[$x]*=$valutakurs[$x]/$dagskurs<br>";
		$amount[$x]*=$valutakurs[$x]/$dagskurs;
		$dkkamount[$x]=$amount[$x]*$valutakurs[$x]/100;
#cho "DKK $dkkamount[$x]<br>";
	}
	$sum+=$amount[$x];
#cho "DkkDiff - $dkkdiff  | $dkkamount[$x] Diff($valuta[$x]) $diff | $amount[$x]<br>";		
#cho afrund($dkksum,2) ." - $valutakurs[$x] $valuta[$x] sum $sum<br>";
	#################################
#cho "$post_id[$x] $beskrivelse[$x] $valuta[$x] $amount[$x] DKK $dkkamount[$x]<br>";
/*
#cho "V=$basisvaluta K=$basiskurs<br>";	
	if ($valutakurs[$x] != 100 && $valuta[$x]) { # Gammel post uden kurs
		if (!$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode[0]' and valdate <= '$transdate[$x]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
			$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode[0]' order by valdate",__FILE__ . " linje " . __LINE__));
		}
		if ($basiskurs=$r3['kurs']) { #Kurs paa transaktionsdagen 
			$amount[$x]=$amount[$x]*100/$basiskurs;
			if ($valutakurs[$x]==100) {
			} else { #postering foert i anden fremmed valuta end kreditors
				$amount[$x]=$amount[$x]*$valutakurs[$x]/100;
			}
		} else $basiskurs=100;
#cho "Basiskurs $basiskurs<br>";
#cho "179 V=$basisvaluta K=$basiskurs<br>";	
	} elseif ($valuta[$x]!='DKK' && $basisvaluta=='DKK' && $valutakurs[$x]!=100) { #postering foert i anden valuta end kreditors som er DKK 
		$amount[$x]=$amount[$x]*$valutakurs[$x]/100;
		$basiskurs=100;
#cho "182 V=$basisvaluta K=$basiskurs<br>";	
	} else {
	$basiskurs=100;
#cho "186 Basiskurs=$basiskurs<br>";	
}
*/

if (isset($udlign[$x]) && $udlign[$x]=='on') {
###############################
#cho "A $diff | $dkkdiff<br>";
#		$diff+=$amount[$x];
#		$kontroldiff+=$amount[$x];
		#cho "B $diff | $dkkdiff<br>";
#cho "DKKdiff $dkkdiff<br>";
#	$dkkdiff+=$amount[$x]*$valutakurs[$x]/($basiskurs*1); # 20130223 $basiskurs*100 rettet til $basiskurs*1
#cho "$diff | $dkkdiff<br>";
# $sum+=$amount[$x];
		#cho "Diff $diff<br>";
#cho "Dkkdiff $dkkdiff $dkkamount[$x]<br>";
		if ($transdate[$x]>$udlign_date) $udlign_date=$transdate[$x];
		$diff+=$amount[$x];
		$dkkdiff+=$dkkamount[$x];
#cho "B dkkdiff $dkkdiff -- $dkkamount[$x]<br>";
	}
}
$postantal=$x;
#cho "ID's ".count($post_id)."<br>";

#cho "$postantal<br>";
$r = db_fetch_array(db_select("select * from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__));
$maxdiff=$r['box1']*1;
$diffkto=$r['box2']*1;
if (!$diffkto) $maxdiff=0;



print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
print "<tr><td colspan=8 align=center>";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=$retur?rapportart=Kontokort&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&submit=ok>Luk</a></div></td>";
print "<td width=\"80%\" align=center><div class=\"top_bund\">Udlign &aring;bne poster<br></div></td>";
print "<td width=\"10%\"><div class=\"top_bund\"><br></div></td>";
print " </tr></tbody></table></td></tr>";
		
print "<tr><td><br></td></tr>";
########################### UDLIGN ##########################
#cho "260 dkkdiff $dkkdiff<br>";
if (isset($submit) && $submit=='udlign') {
	if ($diffdato) $udlign_date=usdate($diffdato); 
	transaktion(begin);
	$query = db_select("select MAX(udlign_id) as udlign_id from openpost",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) $udlign_id=$row['udlign_id']+1;
	
	if ($dkkdiff && $diffkto) {
// 20121106 ->
		$q = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
		if ($r = db_fetch_array($q)){
			$year=trim($r['box2']);
			$aarstart=str_replace(" ","",$year.$r['box1']);
			$year=trim($r['box4']);
			$aarslut=str_replace(" ","",$year.$r['box3']);
		}
		list ($year, $month, $day) = explode ('-', $udlign_date);
		$year=trim($year);
		$ym=$year.$month;

		if (($ym<$aarstart || $ym>$aarslut))	{
#cho 	"$udlign_date .. $ym<$aarstart || $ym>$aarslut<br>";	
			print "<BODY onLoad=\"javascript:alert('Udligningsdato udenfor regnskabs&aring;r')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;../includes/udlign_openpost.php?post_id=$post_id[0]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=$retur\">";
			exit;
		}
	// <- 20121106
		
		if ($basisvaluta!='DKK') {
			$r=db_fetch_array(db_select("select box3 from grupper where art='VK' and box1='$basisvaluta'",__FILE__ . " linje " . __LINE__));
			$diffkto=$r['box3']; 
		
#		$dkkdiff=$diff*$basiskurs/100;
#cho "XX $dkkdiff=$diff*$basiskurs/100<br>";
		}	
#cho "$diff | $dkkdiff<br>";
		if (!$dkkdiff)$dkkdiff=$diff;	
#	$dkkdiff=afrund($dkkdiff,2);
#		$transdate=date("Y-m-d");
		$logdate=date("Y-m-d");
		$logtime=date("H:i");
		$diff=afrund($diff,2);
		$r=db_fetch_array(db_select("select art, kontonr, gruppe, art from adresser where id = '$konto_id[0]'",__FILE__ . " linje " . __LINE__));
		$kontoart==$r['art'];
		$kontonr[0]=$r['kontonr'];
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
#cho "382 $bogf_besk<br>";
		($kontoart=='D')?$bogf_besk="Debitor: $kontonr[0]":$bogf_besk="Kreditor: $kontonr[0]";
#cho "383 $bogf_besk<br>";
			// 		$bogf_besk.=" $kontonr[0] ".findtekst(176,$sprog_id);
#		$diff=afrund($diff,2)*1;
#		$dkkdiff=afrund($dkkdiff,2)*1;
		if ($dkkdiff!=$diff) {
#cho "388 $bogf_besk<br>";
			$bogf_besk.=" Udligning af valutadiff, ($valuta[$x] ".dkdecimal($diff).", DKK ".dkdecimal($dkkdiff).")";
#cho "390 $bogf_besk<br>";
		}
		
		
		if (abs($dkkdiff)>$maxdiff) {
#cho "Diff $dkkdiff $maxdiff<br>";
		$message=$db." | udlign_openpost | ".$brugernavn." ".date("Y-m-d H:i:s")." | Diff: $diff DKKdiff: $dkkdiff Maxdiff $maxdiff";
			$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'SALDI Opdat fejl', $message, $headers);
			print "<BODY onLoad=\"javascript:alert('Der er sket en fejl, udligning ikke udført')\">";
			exit;
		}
#cho "403 $bogf_besk Diff $diff Dkkdiff $dkkdiff<br>";
		if ($dkkdiff >= 0.01) {
#cho "A insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values('$diffkto', '0', '$udlign_date', '$logdate', '$logtime', '$bogf_besk', '$dkkdiff', '0', '0', '0', '0')<br>"; 
			db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values('$diffkto', '0', '$udlign_date', '$logdate', '$logtime', '$bogf_besk', '$dkkdiff', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
#cho "B insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$udlign_date', '$logdate', '$logtime', '$bogf_besk', '$dkkdiff', '0', '0', '0', '0')<br>";
 			db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$udlign_date', '$logdate', '$logtime', '$bogf_besk', '$dkkdiff', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
#			$tmp=$diff*1;
#cho "diff $diff<br>";
if ($diff) {
#cho "Tmp1 $tmp<br>";
				$vkurs=abs($dkkdiff/$diff*100);
				$tmp=$dkkdiff/$vkurs*100;
				if ($diff>0) $tmp*=-1;
				else $vkurs*=-1;
#cho "Tmp2 $tmp<br>";
#cho "C vkurs $vkurs <br>";
#cho "a insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values ('$konto_id[0]', '$kontonr[0]', '$tmp', '$bogf_besk', '1', '$udlign_date', '0', '0','$basisvaluta','$vkurs','$udlign_id','$udlign_date')<br>";
			db_modify("insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values 
			('$konto_id[0]', '$kontonr[0]', '$tmp', '$bogf_besk', '1', '$udlign_date', '0', '0','$basisvaluta','$vkurs','$udlign_id','$udlign_date')",__FILE__ . " linje " . __LINE__);
			}	else {
				$vkurs=$dkkdiff/0.001*100;
				$tmp=$dkkdiff/$vkurs*100;
				if ($diff<=0)$tmp*=-1;
#cho "C vkurs $vkurs <br>";
#cho "D insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values ('$konto_id[0]', '$kontonr[0]', '$tmp', '$bogf_besk', '1', '$udlign_date', '0', '0','$basisvaluta','$vkurs','$udlign_id','$udlign_date')<br>";
				db_modify("insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values 
				('$konto_id[0]', '$kontonr[0]', '$tmp', '$bogf_besk', '1', '$udlign_date', '0', '0','$basisvaluta','$vkurs','$udlign_id','$udlign_date')",__FILE__ . " linje " . __LINE__);
			}
		} elseif ($dkkdiff <= -0.01) {
			$dkkdiff=$dkkdiff*-1;
#cho "E insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values($diffkto, '0', '$udlign_date', '$logdate', '$logtime', '$bogf_besk', '$dkkdiff', '0', '0', '0', '0')<br>";
			db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values($diffkto, '0', '$udlign_date', '$logdate', '$logtime', '$bogf_besk', '$dkkdiff', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
#cho "F insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$udlign_date', '$logdate', '$logtime', '$bogf_besk', '$dkkdiff', '0', '0', '0', '0')<br>";
			db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$udlign_date', '$logdate', '$logtime', '$bogf_besk', '$dkkdiff', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
			if ($diff) {
			#cho "diff $diff";
				$tmp=$diff*-1;
#cho "G insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values ('$konto_id[0]', '$kontonr[0]', '$tmp', '$bogf_besk', '1', '$udlign_date', '0', '0','$basisvaluta','$basiskurs','$udlign_id','$udlign_date')<br>";
				db_modify("insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values ('$konto_id[0]', '$kontonr[0]', '$tmp', '$bogf_besk', '1', '$udlign_date', '0', '0','$basisvaluta','$basiskurs','$udlign_id','$udlign_date')",__FILE__ . " linje " . __LINE__);
			} else {
				$vkurs=$dkkdiff/0.001*100;
				$tmp=$dkkdiff/$vkurs*-100;
			
#cho "H vkurs $vkurs <br>";
				
#cho "I insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values ('$konto_id[0]', '$kontonr[0]', '$tmp', '$bogf_besk', '1', '$udlign_date', '0', '0','$basisvaluta','$vkurs','$udlign_id','$udlign_date')<br>";
				db_modify("insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values 
				('$konto_id[0]', '$kontonr[0]', '$tmp', '$bogf_besk', '1', '$udlign_date', '0', '0','$basisvaluta','$vkurs','$udlign_id','$udlign_date')",__FILE__ . " linje " . __LINE__);
				
			}
		}
	}
	for ($x=0; $x<=$postantal; $x++) {
if ($udlign[$x]=='on') #cho "UPDATE openpost set udlignet='1', udlign_id='$udlign_id', udlign_date='$udlign_date' where id = $post_id[$x]<br>";
		if ($udlign[$x]=='on') db_modify("UPDATE openpost set udlignet='1', udlign_id='$udlign_id', udlign_date='$udlign_date' where id = $post_id[$x]",__FILE__ . " linje " . __LINE__);
	}
#	}
#xit;
	transaktion(commit);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapportart=Kontokort&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok\">";
}
print "<form name=kontoudtog action=../includes/udlign_openpost.php method=post>";
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
#cho "antal opster ".count($post_id)."<br>";
#cho "Sum $sum<br>";
if ($diff!=0) print "<tr><td colspan=6><hr></td></tr>";
#cho "$sum ID's ".count($post_id)."<br>";
if ($diff!=0) {
	for ($x=1; $x<count($post_id); $x++) {
	#cho "T $titlesum<br>";
	$titlesum+=$amount[$x];
	#cho "ZZ";
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\"><td>".dkdato($transdate[$x])."</td>
			<td>a $refnr[$x]</td>
			<td>$faktnr[$x]</td>
			<td>$beskrivelse[$x]</td>
			<td align=right><span style=\"color: rgb(0, 0, 0);\" title=\"sum: ".dkdecimal($titlesum)."\">".dkdecimal($amount[$x])."</td>";
		if (isset($udlign[$x]) && $udlign[$x]=='on') {
			$udlign[$x]="checked";
#cho "$transdate[$x]>$udligndate<br>";
			if($transdate[$x]>$udligndate) $udligndate=$transdate[$x]; 
		}	else $udlign[$x]=NULL;
		print "<td align=center><input type=\"checkbox\" name=\"udlign[$x]\" $udlign[$x]></td></tr>";
		print "<input type=\"hidden\" name=\"kontrol[$x]\" value=\"$udlign[$x]\"></td></tr>";
	}
} else {
	for ($x=1; $x<count($post_id); $x++) {
		if ($udlign[$x]=='on') {
#cho "$transdate[$x]>$udligndate<br>";
			if($transdate[$x]>$udligndate) $udligndate=$transdate[$x]; 
#cho "$transdate[$x]>$udligndate<br>";
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
#cho "$diffdate $udligndate<br>";
if (!$diffdate) $diffdate=$udligndate;
$diffdato=dkdato($diffdate);
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
print "<tr><td colspan=10 align=center>";

$onclick='';

if ($diff != $dkkdiff && $bogfor!='OK' && $dkkdiff >= 0.005) {
	$txt="Der vil blive bogført en valutadifference på dkk ".dkdecimal($dkkdiff)."\\nKlik OK for at godkende, eller klik Cancel for at afbryde ";
	$onclick= "onclick=\"return confirm('$txt')\"";
	print "<input type=\"hidden\" name=\"stop\" value=\"on\">";
}

if (abs($diff)<0.009) print "<span title=\"".findtekst(178,$sprog_id)."\"><input type=\"submit\"  $onclick style=\"width:100px\" value=\"Udlign\" name=\"submit\"></span>&nbsp;";
elseif (abs($dkkdiff)<$maxdiff) print "<span title=\"".findtekst(179,$sprog_id)."\"><input type=\"submit\" $onclick style=\"width:100px\" value=\"Udlign\" name=\"submit\"></span>&nbsp;";
print "<span title=\"".findtekst(180,$sprog_id)."\"><input type=\"submit\" style=\"width:100px\" value=\"Opdater\" name=\"submit\"></span>";
print "</td></tr></form>\n";

?>

