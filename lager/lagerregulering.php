<?php
// ------------lager/lagerregulering.php------------lap 3.5.3------2015-03-09---
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
// 2014.12.23 Div ændringer i forbindelse med indførelse at aut lager.
// 2015.03.09 Auto lager blev ikke sat. Ændret $row til $r


@session_start();
$s_id=session_id();
 
$title="Lagerregulering";
$modulnr=9;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");	

$id=array();
$beholdning=array();
$ny_beholdning=array();
$logdate=date("Y-m-d");
$logtime=date("H:i");

$r=db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
$y=trim($r['box2']);
$aarstart=str_replace(" ","",$y.$r['box1']);
$y=trim($r['box4']);
$aarslut=str_replace(" ","",$y.$r['box3']);

$fejl=0;

$antal=if_isset($_GET['antal']);
$id[0]=if_isset($_GET['id']);
$ny_beholdning[0]=if_isset($_GET['ny_beholdning']);
	
if(isset($_POST['cancel'])) {
	$id=if_isset($_POST['id']);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id[0]\">";
	exit;
}
if ($_POST['bilag'] || $_POST['bilag']=='0') {
	$id=if_isset($_POST['id']);
	$ny_beholdning=if_isset($_POST['ny_beholdning']);
	$dato=if_isset($_POST['dato']);
	$bilag=if_isset($_POST['bilag']);
	if(!is_numeric($bilag) || strlen($bilag)>9) {
		print "<BODY onLoad=\"javascript:alert('Bilagsnummer skal v&aelig;re et positivt tal og m&aring; maks indeholder 9 cifre')\">";	
		$fejl=1;
	}
	$transdate=usdate($dato);
	list($y,$m,$d)=explode("-",$transdate);
	$ym=$y.$m;
	if (checkdate($m,$d,$y)) {
		if ($ym<$aarstart || $ym>$aarslut) {
			print "<BODY onLoad=\"javascript:alert('Dato udenfor regnskabs&aring;r')\">";
			$fejl=1;
		}
	} else {
		print "<BODY onLoad=\"javascript:alert('Dato skal v&aelig;re i formatet 'dd-mm-yyyy')\">";	
		$fejl=1;
	}
}
if ($antal>=1) {
	$r=db_fetch_array(db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__));
	$startaar=$r['box2']*1;
	($startaar >= '2015')?$aut_lager='on':$aut_lager=NULL;
	if (!$fejl && ($bilag || $bilag=='0')) {
		$bilag=$bilag*1;
		transaktion('begin');
		for($x=0;$x<$antal;$x++) {
			$id[$x]=$id[$x]*1;
			$ny_beholdning[$x]*=1;
			if ($r=db_fetch_array(db_select("select varenr,kostpris,beholdning,gruppe from varer where id = '$id[$x]'",__FILE__ . " linje " . __LINE__))) {
				$varenr=db_escape_string($r['varenr']);
				$beholdning=$r['beholdning']*1;
				$regulering=$ny_beholdning[$x]-$beholdning;
				$kostpris=abs($r['kostpris']*$regulering);
				$stkpris=$r['kostpris'];
				$gruppe=$r['gruppe'];
			
				$r=db_fetch_array(db_select("select * from grupper where art = 'VG' and kodenr = '$gruppe'",__FILE__ . " linje " . __LINE__));
				$lagertilgang=$r['box1'];
				$lagertraek=$r['box2'];
				$varekob=$r['box3'];
				$varesalg=$r['box4'];
				$lagerregulering=$r['box5'];
				if ($lagerregulering) {
					if (!$aut_lager && $lagertraek && $kostpris && $lagerregulering) {
						if ($regulering < 0) {
							db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
								values
								($lagerregulering, '$bilag', '$transdate', '$logdate', '$logtime', 'Lagerregulering varenr: $varenr ($brugernavn)', '$kostpris', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
							db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
								values
								($lagertraek, '$bilag', '$transdate', '$logdate', '$logtime', 'Lagerregulering varenr: $varenr ($brugernavn)', '$kostpris', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
						} else {
							db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id) 
								values
					  		($lagerregulering, '$bilag', '$transdate', '$logdate', '$logtime', 'Lagerregulering varenr: $varenr ($brugernavn)', '$kostpris', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
							db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
							values
							($lagertraek, '$bilag', '$transdate', '$logdate', '$logtime', 'Lagerregulering varenr: $varenr ($brugernavn)', '$kostpris', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
						}	
					}
					if ($regulering > 0) { 
						db_modify("insert into batch_kob(vare_id,linje_id,kobsdate,fakturadate,ordre_id,antal,pris,rest) 
						values 
						('$id[$x]','0','$transdate','$transdate','0','$regulering','$stkpris','0')",__FILE__ . " linje " . __LINE__);
					} else {
						$tmp=$regulering*-1;
						db_modify("insert into batch_salg(batch_kob_id,vare_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr) 
						values 
						('0','$id[$x]','0','$transdate','$transdate','0','$tmp','$stkpris','1')",__FILE__ . " linje " . __LINE__);
					}
					db_modify("update varer set beholdning='$ny_beholdning[$x]' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
				} else print "<BODY onLoad=\"javascript:alert('Kontonummer for lagerregulering mangler (Indstillinger -> Varegrp.)')\">";	
			}
		}	
		transaktion('commit');
		print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id[0]\">";
	} else {
		if (!isset($dato)) {
			$y=date("Y");
			$m=date("m");
			$d=31;
			if (strlen($m)<2) $m="0".$m;
			$ym=$y.$m;
			if ($ym>$aarslut) {
				$y=substr($aarslut,0,4);
				$m=substr($aarslut,4,2);
				while(!checkdate($m,$d,$y)) {
					$d--; 
					if ($d<1) break;
				}
				$dato=$d."-".$m."-".$y;
			} elseif ($ym<$aarstart) {
				$y=substr($aarstart,0,4);
				$m=substr($aarstart,4,2);
				$dato="01-".$m."-".$y;
			} else $dato=date("d-m-Y");
		}
		print "<table><tbody>";
		print "<form name=\"lagerregulering\" action=\"lagerregulering.php?antal=$antal\" method=\"post\">";
		for ($x=0;$x<$antal;$x++) {
			print "<input type = \"hidden\" name=\"id[$x]\" value = $id[$x]>";
			print "<input type = \"hidden\" name=\"ny_beholdning[$x]\" value = $ny_beholdning[$x]>";
		}
#		print "<tr><td>Bilagsnummer</td></tr>";
		print "<tr><td>Bilagsnummer</td><td><input type = \"tekst\" name=\"bilag\" value=\"0\"></td></tr>";
		print "<tr><td>Dato</td><td><input type = \"tekst\" name=\"dato\" value=\"$dato\"></td></tr>";
		print "<tr><td colspan=\"2\"><input type = \"submit\" name=\"OK\" value=\"OK\">&nbsp;";
		print "<input type = \"submit\" name=\"cancel\" value=\"Afbryd\"></td></tr>";
		print "</form>";
	}
}
?>
