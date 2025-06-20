<?php
// ------------lager/vareproduktion.php------------lap 3.5.2------2015-02-17---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
//
// 2012.10.16 Fejl i lagertræk v. varesalg efter produktionsordre med antal != 0. Søg 21121016
// 2014.12.23 Div ændringer i forbindelse med indførelse at aut lager.


@session_start();
$s_id=session_id();
 
$title="Vareproduktion";
$modulnr=9;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");	

$id=array();
$beholdning=array();
$ny_beholdning=array();
$transdate=date("Y-m-d");
$logtime=date("H:i");
$fejl=0;

$antal=if_isset($_GET['antal']);
$id[0]=if_isset($_GET['id']);
$ny_beholdning[0]=if_isset($_GET['ny_beholdning']);
$samlevare=if_isset($_GET['samlevare']);
	
if(isset($_POST['cancel'])) {
	$id=if_isset($_POST['id']);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id[0]\">";
	exit;
}

if ($_POST['bilag'] || $_POST['bilag']=='0') {
	$id=if_isset($_POST['id']);
	$ny_beholdning=if_isset($_POST['ny_beholdning']);
	$bilag=if_isset($_POST['bilag']);
	if(!is_numeric($bilag) || strlen($bilag)>9) {
		print "<BODY onLoad=\"javascript:alert('Bilagsnummer skal v&aelig;re et positivt tal og m&aring; maks indeholder 9 cifre')\">";	
		$fejl=1;
	}
}

if (!$fejl && $antal>=1) {
	if ($bilag || $bilag=='0') {
		$bilag=$bilag*1;
		if ($samlevare && $antal) {
			list($antal,$id,$ny_beholdning)=samlevare($id[0],$ny_beholdning[0]);
			$kontonr=array();
		}
		$r=db_fetch_array(db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__));
		$startaar=$row['box2']*1;
		($startaar >= '2015')?$aut_lager='on':$aut_lager=NULL;
		
		transaktion('begin');
		$l=0;
		$afgangsum=0;

		for($x=0;$x<$antal;$x++) {
			$id[$x]*=1;
			$ny_beholdning[$x]*=1;

			if ($r=db_fetch_array(db_select("select varenr,kostpris,beholdning,gruppe from varer where id = '$id[$x]'",__FILE__ . " linje " . __LINE__))) {
				$varenr[$x]=db_escape_string($r['varenr']);
				$beholdning[$x]=$r['beholdning'];

				$regulering[$x]=$ny_beholdning[$x]-$beholdning[$x];
				$kostpris[$x]=abs($r['kostpris']*$regulering[$x]);
				$gruppe[$x]=$r['gruppe'];
				$r=db_fetch_array(db_select("select * from grupper where art = 'VG' and kodenr = '$gruppe[$x]'",__FILE__ . " linje " . __LINE__));
				$lagerfort[$x]=trim($r['box8']);
				if ($x==0) {
					if ($r['box1']) $tilgang=$r['box1'];
					else $tilgang=$r['box3'];
					# 20121016 indsat "/$regulering[$x]" efter kostpris herunder for korrekt lagertræk v.salg. 
					db_modify("insert into batch_kob(kobsdate,fakturadate,vare_id,linje_id,ordre_id,pris,antal,rest,lager) 
						values 
					('$transdate','$transdate',$id[$x],'0','0','".$kostpris[$x]/$regulering[$x]."','$regulering[$x]','$regulering[$x]','0')",__FILE__ . " linje " . __LINE__);
				} else {
					if ($r['box2']) $afgang[$x]=$r['box2'];
					else $afgang[$x]=$r['box4'];
					if (!in_array($afgang[$x],$kontonr)) {
						$kontonr[$l]=$afgang[$x];
						$amount[$l]=$kostpris[$x];
						$l++;
					} else {
						for ($i=0;$i<$l;$i++) {
							if ($kontonr[$i]==$afgang[$x]) $amount[$i]+=$kostpris[$x]; 
						}
					}
					$afgangsum+=$kostpris[$x];
					$tmp=$regulering[$x]*-1;
					db_modify("insert into batch_salg(batch_kob_id,vare_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr) 
					values 
					('0','$id[$x]','0','$transdate','$transdate','0','$tmp','$kostpris[$x]','1')",__FILE__ . " linje " . __LINE__);
				}
				if ($lagerfort[$x]) db_modify("update varer set beholdning='$ny_beholdning[$x]' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
		$tjeksum=0;
		if (!$aut_lager) {
			for($x=0;$x<count($kontonr);$x++) {
				db_modify("insert into transaktioner (kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id)
					values
					($kontonr[$x],'$bilag','$transdate','$transdate','$logtime','Produktionsordre: $varenr[0] ($brugernavn)','0','$amount[$x]','','0','0','0','0','1','100','0')",__FILE__ . " linje " . __LINE__);
				$tjeksum+=$amount[$x];
			}
			if (abs($tjeksum-$afgangsum)>0.01) {
				print "<BODY onLoad=\"javascript:alert('Ubalance i posteringssum -kontakt Saldi teamet på tlf. 4690 2208')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id[0]\">";
				exit;
			}
			db_modify("insert into transaktioner (kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id)
				values
			('$tilgang','$bilag','$transdate','$transdate','$logtime','Produktionsordre: $varenr[0] ($brugernavn)','$afgangsum','0','','0','0','0','0','1','100','0')",__FILE__ . " linje " . __LINE__);
		}	
		$diff=transtjek();
		if ($diff > 1) print "<BODY onLoad=\"javascript:alert('Ubalance i transaktioner -kontakt Saldi teamet på tlf. 4690 2208')\">";
		else transaktion('commit'); 
		print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id[0]\">";
	} else {
		print "<table><tbody>";
		print "<form name=\"vareproduktion\" action=\"vareproduktion.php?antal=$antal&samlevare=$samlevare\" method=\"post\">";
		for ($x=0;$x<$antal;$x++) {
			print "<tr><td><input type = \"hidden\" name=\"id[$x]\" value = $id[$x]>";
			print "<tr><td><input type = \"hidden\" name=\"ny_beholdning[$x]\" value = $ny_beholdning[$x]>";
		}
		print "<tr><td>Skriv bilagsnummer for regulering</td></tr>";
		print "<tr><td><input type = \"tekst\" name=\"bilag\" value=\"0\"></td></tr>";
		print "<tr><td><input type = \"submit\" name=\"OK\" value=\"OK\">&nbsp;";
		print "<input type = \"submit\" name=\"cancel\" value=\"Afbryd\"></td></tr>";
		print "</form>";
	}
}

function samlevare ($v_id,$ny_v_beholdning) {
	include ("../includes/fuld_stykliste.php");
	list($vare_id, $stk_antal, $antal) = fuld_stykliste($v_id, '', 'basisvarer');
	$id[0]=$v_id;
	$ny_beholdning[0]=$ny_v_beholdning;
	$r=db_fetch_array(db_select("select beholdning from varer where id='$v_id'",__FILE__ . " linje " . __LINE__));
	$diff=$ny_v_beholdning-$r['beholdning'];
	for ($x=1; $x<=$antal; $x++) {
		if ($r=db_fetch_array(db_select("select beholdning from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__))) {
			$id[$x]=$vare_id[$x];
			$ny_beholdning[$x]=$r['beholdning']-$stk_antal[$x]*$diff;
		}
	}
	return array($x,$id,$ny_beholdning);
}

?>
