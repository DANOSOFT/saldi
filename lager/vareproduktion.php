<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------lager/vareproduktion.php------------lap 3.8.1------2019-06-26---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// 2012.10.16 Fejl i lagertræk v. varesalg efter produktionsordre med antal != 0. Søg 21121016
// 2014.12.23 Div ændringer i forbindelse med indførelse af aut lager.
// 2018.02.04 Div ændringer i forbindelse med varianter - funkionen kaldes ikke, hvis der anvendes varianter.
// 2018.03.02 Gevaldig omskrivning
// 2018.03.14 Lager blev ikke taget med fra form.
// 2019.06.26 Fjernet kald til transtjek.


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
$lager=if_isset($_GET['lager']);

#cho  __line__." Ny beh: $ny_beholdning[0]<br>";
#cho __line__." Antal $antal<br>";
if (!$lager) $lager=1;

if(isset($_POST['cancel'])) {
	$id=if_isset($_POST['id']);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id[0]\">";
	exit;
}

if ($_POST['OK']) {
	$id=if_isset($_POST['id']);
	$ny_beholdning=if_isset($_POST['ny_beholdning']);
	$bilag=if_isset($_POST['bilag']);
	if($bilag && (!is_numeric($bilag) || strlen($bilag)>9)) {
		print "<BODY onLoad=\"javascript:alert('Bilagsnummer skal v&aelig;re et positivt tal og m&aring; maks indeholder 9 cifre')\">";	
		$fejl=1;
	}
}

#cho __line__." ID $id[0]<br>";

if ($_POST['OK']) {
#cho __line__." Antal $antal<br>";
#	if ($bilag || $bilag=='0') {
#		$bilag=$bilag*1;
		if ($samlevare) {
#cho __line__." Antal $antal<br>";
#cho __line__." ID $id[0] NY $ny_beholdning[0]<br>";
		list($antal,$id,$stk_antal,$ny_beholdning)=samlevare($id[0],$ny_beholdning[0]);
			$kontonr=array();
		}
#cho __line__." Antal $antal<br>";
		transaktion('begin');
		$l=0;
		$afgangsum=0;

#cho __line__." Antal $antal<br>";

		for($x=0;$x<$antal;$x++) {
#cho "L $lager<br>";
#xit;
#cho __line__." Antal: $antal[$x], ID: $id[$x], Stk ant: $stk_antal[$x], Ny beh: $ny_beholdning[$x]<br>";
			$id[$x]*=1;
			$ny_beholdning[$x]*=1;
			$qtxt="select varenr,kostpris,beholdning,gruppe from varer where id = '$id[$x]'";
#cho __line__." $qtxt<br>";			
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$varenr[$x]=db_escape_string($r['varenr']);
				$beholdning[$x]=$r['beholdning'];
				$kostpris[$x]=$r['kostpris']; #*$regulering[$x]);
				$gruppe[$x]=$r['gruppe'];

				$qtxt="select id,beholdning from lagerstatus where vare_id = '$id[$x]' and lager='$lager' and variant_id='0'";
#cho __line__." $qtxt<br>";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$ls_id[$x]=$r['id'];
				$ls_beholdning[$x]=$r['beholdning'];
#cho "$ls_id[$x] -> $ls_beholdning[$x]<br>";
				if (!$x) {
#cho __line__." Gl beh $r[beholdning] Ny beh $ny_beholdning[$x]<br>";
					$regulering[$x]=$ny_beholdning[$x]-$r['beholdning'];
				} else {
#cho __line__." X $x<br>";
					$regulering[$x]=$stk_antal[$x]*$regulering[0];
#cho __line__." Gl beh $r[beholdning] Ny beh ".$r['beholdning']." - $regulering[$x]<br>";
				}
#cho __line__." $x $id[$x] -> $regulering[$x]=$stk_antal[$x]*$stk_antal[0]<br>";
				$qtxt="select * from grupper where art = 'VG' and kodenr = '$gruppe[$x]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$lagerfort[$x]=trim($r['box8']);
				if ($x==0) {
					$tilgang=$r['box3'];
					# 20121016 indsat "/$regulering[$x]" efter kostpris herunder for korrekt lagertræk v.salg. 
					$qtxt="insert into batch_kob(kobsdate,fakturadate,vare_id,linje_id,ordre_id,pris,antal,rest,lager,variant_id)";
					$qtxt.=" values ";
					$qtxt.="('$transdate','$transdate',$id[$x],'0','0','$kostpris[$x]','$regulering[$x]','$regulering[$x]','$lager','0')";
#cho __line__." $qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$tilgangsum=$kostpris[$x]*$regulering[$x];
#cho __line__." $id[$x] $tilgangsum<br>";
				} else {
					if (!in_array($r['box4'],$kontonr)) {
						$kontonr[$l]=$r['box4'];
						$amount[$l]=$kostpris[$x]*$regulering[$x];
#cho __line__." Vare: $id[$x] Kto $kontonr[$l] Amount $amount[$l]<br>";								
						$l++;
					} else {
						for ($i=0;$i<$l;$i++) {
							if ($kontonr[$i]==$r['box4']) {
								$amount[$i]+=$kostpris[$x]*$regulering[$x];
#cho __line__." Vare: $id[$x] Kto $kontonr[$i] Amount $amount[$i]<br>";								
							}
						}
					}
					$afgangsum+=$kostpris[$x]*$regulering[$x];
#cho __line__." No: $x Vare: $id[$x] -> $afgangsum | $kostpris[$x]*$regulering[$x]<br>";								
					$tmp=$regulering[$x]*-1;
					$qtxt="insert into batch_salg(batch_kob_id,vare_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr,variant_id)";
					$qtxt.=" values ";
					$qtxt.="('0','$id[$x]','0','$transdate','$transdate','0','$regulering[$x]','$kostpris[$x]','1','0')";
#cho __line__." $qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				if ($lagerfort[$x]) {
					if ($ls_id[$x]) {
						if ($x) $ny_lsb=$ls_beholdning[$x]-$regulering[$x];
						else $ny_lsb=$ls_beholdning[$x]+$regulering[$x];
						$qtxt="update lagerstatus set beholdning='$ny_lsb' where id=$ls_id[$x]";
#						if ($x) $qtxt="update lagerstatus set beholdning=beholdning-$regulering[$x] where id=$ls_id[$x]";
#						else $qtxt="update lagerstatus set beholdning=beholdning+$regulering[$x] where id=$ls_id[$x]";
					} else {
						$qtxt="insert into lagerstatus(vare_id,beholdning,lager,variant_id)";
						$qtxt.=" values ";
						if ($x) $qtxt.="('$id[$x]',$regulering[$x]*-1,'$lager','0')";
						else $qtxt.="('$id[$x]','$regulering[$x]','$lager','0')";
					}
#cho __line__." $qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					if ($x) $ny_beh=$beholdning[$x]-$regulering[$x];
					else $ny_beh=$beholdning[$x]+$regulering[$x];
					$qtxt="update varer set beholdning='$ny_beh' where id='$id[$x]'";
#cho __line__." $qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
		$tjeksum=0;
		$diff=0;
		if ($bilag || $bilag=='0') {
			for($x=0;$x<count($kontonr);$x++) {
				$qtxt="insert into transaktioner ";
				$qtxt.="(kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id)";
				$qtxt.=" values ";
				$qtxt.="($kontonr[$x],'$bilag','$transdate','$transdate','$logtime',";
				$qtxt.="'Produktionsordre: $varenr[0] ($brugernavn)','0','$amount[$x]','','0','0','0','0','1','100','0')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$tjeksum+=$amount[$x];
			}
			if (abs($tjeksum-$afgangsum)>0.01) {
				print "<BODY onLoad=\"javascript:alert('Ubalance i posteringssum -kontakt Saldi teamet på tlf. 4690 2208')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id[0]\">";
				exit;
			}
			$qtxt = "insert into transaktioner"; 
			$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id)";
			$qtxt.= "	values ";
			$qtxt.= "('$tilgang','$bilag','$transdate','$transdate','$logtime',";
			$qtxt.= "'Produktionsordre: $varenr[0] ($brugernavn)','$afgangsum','0','','0','0','0','0','1','100','0')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		transaktion('commit'); 

		print "<BODY onLoad=\"javascript:alert('Vareproduktion gennemført')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id[0]\">";
	} else {
		print "<table><tbody>";
		print "<form name=\"vareproduktion\" action=\"vareproduktion.php?antal=$antal&samlevare=$samlevare&lager=$lager\" method=\"post\">";
		for ($x=0;$x<$antal;$x++) {
			print "<tr><td><input type = \"hidden\" name=\"id[$x]\" value = $id[$x]>";
			print "<tr><td><input type = \"hidden\" name=\"ny_beholdning[$x]\" value = $ny_beholdning[$x]>";
		}
		print "<tr><td>Skriv bilagsnummer for regulering hvis værdiændringen skal bogføres i 'finans'</td></tr>";
		print "<tr><td>Skrives bilagsnr bliver kostprisen for de varer som indgår, bogført som vare-/ ydelsessalg</td></tr>";
		print "<tr><td>og den samlede vare bogført som varekøb</td></tr>";
		print "<tr><td>Efterlades feltet tomt bliver reguleringen ikke bogført i 'finans'</td></tr>";
		print "<tr><td><input type = \"tekst\" name=\"bilag\" value=\"\"></td></tr>";
		print "<tr><td><input type = \"submit\" name=\"OK\" value=\"OK\">&nbsp;";
		print "<input type = \"submit\" name=\"cancel\" value=\"Afbryd\"></td></tr>";
		print "</form>";
	}

#xit;

function samlevare ($v_id,$ny_v_beholdning) {
	include ("../includes/fuld_stykliste.php");	
#cho __line__." $v_id, '', 'basisvarer'<br>";
	list($vare_id, $stk_antal, $antal) = fuld_stykliste($v_id, '', 'basisvarer');
	$id[0]=$v_id;
	$ny_beholdning[0]=$ny_v_beholdning;
	$r=db_fetch_array(db_select("select beholdning from varer where id='$v_id'",__FILE__ . " linje " . __LINE__));
	for ($x=1; $x<=$antal; $x++) {
		if ($r=db_fetch_array(db_select("select beholdning from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__))) {
			$id[$x]=$vare_id[$x];
		}
	}
	return array($x,$id,$stk_antal,$ny_beholdning);
}

?>
