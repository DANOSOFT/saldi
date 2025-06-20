<?php
// ------------lager/lagerregulering.php------------lap 3.0.3------2010-06-01---
// LICENS
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------



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
$transdate=date("Y-m-d");
$logtime=date("H:i");
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
	$bilag=if_isset($_POST['bilag']);
	if(!is_numeric($bilag) || strlen($bilag)>9) {
		print "<BODY onLoad=\"javascript:alert('Bilagsnummer skal v&aelig;re et positivt tal og m&aring; maks indeholder 9 cifre')\">";	
		$fejl=1;
	}
	
# echo "antal $antal, id $id[0], ny $ny_beholdning[0], bilag $bilag<br>"; 
}
if (!$fejl && $antal>=1) {
	if ($bilag || $bilag=='0') {
		$bilag=$bilag*1;
		transaktion('begin');
		for($x=0;$x<$antal;$x++) {
#echo "antal $antal, id $id[0], ny $ny_beholdning[0], bilag $bilag<br>"; 
			$id[$x]=$id[$x]*1;
			$$ny_beholdning[$x]=$ny_beholdning[$x]*1;
#echo "select varenr,kostpris,beholdning,gruppe from varer where id = '$id[$x]'<br>";
			if ($r=db_fetch_array(db_select("select varenr,kostpris,beholdning,gruppe from varer where id = '$id[$x]'"))) {
#echo "antal $antal, id $id[0], ny $ny_beholdning[0], bilag $bilag<br>"; 
				$varenr=addslashes($r['varenr']);
				$beholdning=$r['beholdning'];
				$regulering=$ny_beholdning[$x]-$beholdning;
				$kostpris=abs($r['kostpris']*$regulering);
				$gruppe=$r['gruppe'];
			
				$r=db_fetch_array(db_select("select * from grupper where art = 'VG' and kodenr = '$gruppe'"));
				$lagertilgang=$r['box1'];
				$lagertraek=$r['box2'];
				$varekob=$r['box3'];
				$varesalg=$r['box4'];
				$lagerregulering=$r['box5'];
#echo "lagerreg $lagerregulering<br>";				
				if ($lagerregulering) {
#				db_fetch_array(db_select("select id from kontoplan where kontonr = '$lagerregulering'"))) {
					if ($lagertraek && $kostpris && $lagerregulering) {
						if ($regulering > 0) {
#echo "insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
#								values
#								($lagerregulering, '$bilag', '$transdate', '$transdate', '$logtime', 'Lagerregulering varenr: $varenr', '$kostpris', '', '0', '0', '0', '0', '1', '100', '0')";							
							db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
								values
								($lagerregulering, '$bilag', '$transdate', '$transdate', '$logtime', 'Lagerregulering varenr: $varenr ($brugernavn)', '$kostpris', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
#echo "insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
#								values
#								($lagertraek, '0', '$transdate', '$transdate', '$logtime', 'Lagerregulering varenr: $varenr', '$kostpris', '', '0', '0', '0', '0', '1', '100', '0')";							
							db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
								values
								($lagertraek, '$bilag', '$transdate', '$transdate', '$logtime', 'Lagerregulering varenr: $varenr ($brugernavn)', '$kostpris', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
						} else {
							db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id) 
								values
					  		($lagerregulering, '$bilag', '$transdate', '$transdate', '$logtime', 'Lagerregulering varenr: $varenr ($brugernavn)', '$kostpris', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
							db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
							values
							($lagertraek, '$bilag', '$transdate', '$transdate', '$logtime', 'Lagerregulering varenr: $varenr ($brugernavn)', '$kostpris', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
						}	
					}
#echo "insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr) 
#						values 
#						(0, $id[$x], 0, '$transdate', 0, $regulering, '$kostpris', '1')<br>";					
						$tmp=$regulering*-1;						
						db_modify("insert into batch_salg(batch_kob_id,vare_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr) 
						values 
						(0,$id[$x],0,'$transdate','$transdate',0,$tmp,'$kostpris','1')",__FILE__ . " linje " . __LINE__);
				}
				db_modify("update varer set beholdning='$ny_beholdning[$x]' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}	
		transaktion('commit');
		print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id[0]\">";
#		print  "<body onload=\"javascript:window.close();\">";
	} else {
		print "<table><tbody>";
		print "<form name=\"lagerregulering\" action=\"lagerregulering.php?antal=$antal\" method=\"post\">";
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

?>
