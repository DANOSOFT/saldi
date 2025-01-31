<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------systemdata/laas_lager.php---------------------Patch 3.7.0-----2017.05.15---
// LICENS
//
// // Dette program er fri software. Du kan gendistribuere det og / eller
// // modificere det under betingelserne i GNU General Public License (GPL)
// // som er udgivet af "The Free Software Foundation", enten i version 2
// // af denne licens eller en senere version, efter eget valg.
// // Fra og med version 3.2.2 dog under iagttagelse af følgende:
// // 
// // Programmet må ikke uden forudgående skriftlig aftale anvendes
// // i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
// //
// // Dette program er udgivet med haab om at det vil vaere til gavn,
// // men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// // GNU General Public Licensen for flere detaljer.
// //
// // En dansk oversaettelse af licensen kan laeses her:
// // http://www.saldi.dk/dok/GNU_GPL_v2.html
// //
// // Copyright (c) 2017 saldi.dk ApS
// -----------------------------------------------------------------------------------

@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$regnaar=if_isset($_GET['regnaar']);
$regnaar_id=if_isset($_GET['regnaar_id']);
$print=if_isset($_GET['print']);
$logdate=date("Y-m-d");
$logtime=date("H:i:s");

$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$startmaaned=$row['box1']*1;
$startaar=$row['box2']*1;
$slutmaaned=$row['box3']*1;
$slutaar=$row['box4']*1;
$startdato='01';
$slutdato='31';

$regnaarstart= $startaar. "-" . $startmaaned . "-" . '01';

$maaned_fra=$startmaaned;
$maaned_til=$slutmaaned;
$aar_fra=$startaar;
$aar_til=trim($slutaar);

$mf=$maaned_fra;
$mt=$maaned_til;

$x=0;
$varekob=array();
$q=db_select("select box1,box2,box3 from grupper where art = 'VG' and box8 = 'on'",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($r['box1'] && $r['box2'] && !in_array($r['box3'],$varekob)) {
		$varelager_i[$x]=$r['box1'];
		$varelager_u[$x]=$r['box2'];
		$varekob[$x]=$r['box3'];
		$x++;
	}
}
	
$startmaaned*=1;
if ($startmaaned < 10) $startmaaned='0'.$startmaaned; 

while (!checkdate($startmaaned,$startdato,$startaar)) {
	$startdato=$startdato-1;
	if ($startdato<28) break 1;
}
	
while (!checkdate($slutmaaned,$slutdato,$slutaar)) {
	$slutdato=$slutdato-1;
	if ($slutdato<28) break 1;
}

$regnstart = $startaar. "-" . $startmaaned . "-" . $startdato;
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

#	print "  <a accesskey=L href=\"rapport.php?rapportart=Kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Luk</a><br><br>";

$x=0;
$valdate=array();
$valkode=array();
$q=db_select("select * from valuta order by gruppe,valdate desc",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$y=$x-1;	
	if ((!$x) || (isset($valkode[$x]) && $r['gruppe']!=$valkode[$x]) || (isset($valkode[$x]) && $valdate[$x]>=$regnstart)) {
		$valkode[$x]=$r['gruppe'];
		$valkurs[$x]=$r['kurs'];
		$valdate[$x]=$r['valdate'];
		$x++;
	}
}
$ktonr=array();
for ($i=0;$i<count($varekob);$i++) { 
	if (!in_array($varekob[$i],$ktonr)) {
		$ktonr[$x]=$varekob[$i];
		$x++;
	}
}
for ($i=0;$i<count($varelager_i);$i++) { 
	if (!in_array($varelager_i[$i],$ktonr)) {
		$ktonr[$x]=$varelager_i[$i];
		$x++;
	}
}
for ($i=0;$i<count($varelager_u);$i++) { 
	if (!in_array($varelager_u[$i],$ktonr)) {
		$ktonr[$x]=$varelager_u[$i];
		$x++;
	}
}

$x=0;
$qtxt="select * from kontoplan where regnskabsaar='$regnaar' order by kontonr";
#cho "$qtxt<br>";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($q)){
	if (in_array($row['kontonr'],$ktonr)) {
	$kontonr[$x]=$row['kontonr']*1;
	$kontobeskrivelse[$x]=$row['beskrivelse'];
	$kontotype[$x]=$row['kontotype'];
	$kontomoms[$x]=$row['moms'];
	$kontovaluta[$x]=$row['valuta'];
	$kontokurs[$x]=$row['valutakurs'];
#	if ($kontotype[$x]=="S") $primo[$x]=afrund($row['primo'],2);
	$primo[$x]=0;
#	if ($primo[$x] && $kontovaluta[$x]) {
#		for ($y=0;$y<=count($valkode);$y++){
#			if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $regnstart) {
#				$primokurs[$x]=$valkurs[$y];
#				break 1;
#			}
#		}
#	} else $primokurs[$x]=100;
	$x++;
}}
$x=0;
/*
$ktonr=array();
$qtxt = "select distinct(kontonr) as kontonr from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' order by kontonr";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$ktonr[$x]=$r['kontonr'];
echo "K: $ktonr[$x]<br>";
	$x++;
}
*/	
sort($kontonr);
$kontosum=0;
$founddate=false;
if ($print) {
	print "<table><tbody>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr><td width=\"100px\">Dato</td><td width=\"60px\">Bilag</td><td>Tekst</td><td width=\"100px\" align=\"right\">Debet</td><td width=\"100px\" align=\"right\">Kredit</td><td width=\"100px\" align=\"right\">Saldo</td></tr>";
} else transaktion('begin');
for ($x=0;$x<count($kontonr);$x++){
	$linjebg=$bgcolor5;
	if (in_array($kontonr[$x],$ktonr)||$primo[$x]){
		if ($print) {
			print "<tr><td colspan=6><hr></td></tr>";
			print "<tr bgcolor=\"$bgcolor5\"><td></td><td></td><td colspan=4>$kontonr[$x] : $kontobeskrivelse[$x] : $kontomoms[$x]</tr>";
			print "<tr><td colspan=6><hr></td></tr>";
		}
		$kontosum=$primo[$x];
		$query = db_select("select debet, kredit from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)){
		 	$kontosum=$kontosum+afrund($row['debet'],2)-afrund($row['kredit'],2);
		}
		#if ($primokurs[$x]) $tmp=$kontosum*100/$primokurs[$x];
		$tmp=$kontosum;
		if ($print) print "<tr bgcolor=\"$linjebg\"><td></td><td></td><td>Primosaldo </td><td></td><td></td><td align=right>".dkdecimal($tmp,2)."</td></tr>";
		$tr=0;
		$transdate=array();
/*
		$query = db_select("select * from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnstart' and transdate<='$regnslut' order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)){
			$transdate[$tr]=$row['transdate'];
			$bilag[$tr]=$row['bilag'];
			$beskrivelse[$tr]=$row['beskrivelse'];
			$debet[$tr]=$row['debet'];
			$kredit[$tr]=$row['kredit'];
			$transvaluta[$tr]=$row['valuta'];
			if ($kontovaluta[$x]) {
				for ($y=0;$y<=count($valkode);$y++){
#cho "$valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $transdate[$tr]<br>";
					if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $transdate[$tr]) {
						$transkurs[$tr]=$valkurs[$y];
						break 1;
					}
				}
			} else $transkurs[$tr]=100; 
#cho "TK1 $transkurs[$tr]<br>";
			$tr++;
		}
*/
		if (in_array($kontonr[$x],$varekob) || in_array($kontonr[$x],$varelager_i) || in_array($kontonr[$x],$varelager_u)) {
			$z=0;
			$lager=array();
			$gruppe=array();
			$qtxt="select kodenr,box1,box2 from grupper where art = 'VG' and box8 = 'on' and (box1 = '$kontonr[$x]' or box2 = '$kontonr[$x]' or box3 = '$kontonr[$x]')";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				if ($r['box1']) {
					$lager_i[$z]=$r['box1'];
					$lager_u[$z]=$r['box2'];
					$gruppe[$z]=$r['kodenr'];
					$z++;
				}
			} 
			$y=0;
			$vare_id=array();
			for ($z=0;$z<count($gruppe);$z++) {
				$q=db_select("select id,kostpris from varer where gruppe = '$gruppe[$z]' order by id",__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					$vare_id[$y]=$r['id'];
					$kostpris[$y]=$r['kostpris'];
					$y++;
				}
			}
			$z=-1;
			$kobsfaktdate=array();
			$kobsdebet=array();
			$kobskredit=array();
			$q=db_select("select vare_id,ordre_id,antal,kobsdate from batch_kob where kobsdate >= '$regnstart' and kobsdate <= '$regnslut' order by kobsdate,ordre_id,vare_id",__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					if ($z>=0 && isset($kobsfaktdate[$z]) && $r['kobsdate']==$kobsfaktdate[$z] && $r['ordre_id'] && $r['ordre_id']==$koid[$z]) {
						for ($y=0;$y<count($vare_id);$y++) {
							if($r['vare_id']==$vare_id[$y]) {
								if($kontotype[$x]=='D')	{ 
									if ($r['antal']>0) $kobskredit[$z]+=$r['antal']*$kostpris[$y];
									else $kobsdebet[$z]-=$r['antal']*$kostpris[$y];
								} elseif(in_array($kontonr[$x],$varelager_i)) {
								if ($r['antal']>0) $kobsdebet[$z]+=$r['antal']*$kostpris[$y];
								else $kobskredit[$z]-=$r['antal']*$kostpris[$y];
							}
						}
					}
				} else {
					for ($y=0;$y<count($vare_id);$y++) {
						if($r['vare_id']==$vare_id[$y]) {
							if($kontotype[$x]=='D')	{ 
								$z++;
								$kobsfaktdate[$z]=$r['kobsdate'];
								$koid[$z]=$r['ordre_id'];
								if ($koid[$z]==$koid[$z-1]) $kobsfakt[$z]=$kobsfakt[$z-1];
								else {
									$r2=db_fetch_array(db_select("select fakturanr from ordrer where id='$koid[$z]'",__FILE__ . " linje " . __LINE__));
									$kobsfakt[$z]=$r2['fakturanr'];
								}
								if ($r['antal']>0) {
									$kobskredit[$z]=$r['antal']*$kostpris[$y];
									$kobsdebet[$z]=0;
								} else {
									$kobsdebet[$z]=$r['antal']*$kostpris[$y]*-1;
									$kobskredit[$z]=0;
								}
								#$z++;
							} elseif(in_array($kontonr[$x],$varelager_i)) {
								$z++;
#								$kobsfakt[$z]=$r2['fakturanr'];
								$kobsfaktdate[$z]=$r['kobsdate'];
								$koid[$z]=$r['ordre_id'];
								if ($koid[$z]==$koid[$z-1]) $kobsfakt[$z]=$kobsfakt[$z-1];
								else {
									$r2=db_fetch_array(db_select("select fakturanr from ordrer where id='$koid[$z]'",__FILE__ . " linje " . __LINE__));
									$kobsfakt[$z]=$r2['fakturanr'];
								}
								if ($r['antal']>0) {
									$kobsdebet[$z]=$r['antal']*$kostpris[$y];
									$kobskredit[$z]=0;
								} else {
									$kobskredit[$z]=$r['antal']*$kostpris[$y]*-1;
									$kobsdebet[$z]=0;
								}
							}
						}
					}
				}
			}
			$z=-1;
			$salgsfaktdate=array();
			$salgsdebet=array();
			$salgkredit=array();
			$q=db_select("select ordre_id,vare_id,antal,salgsdate from batch_salg where salgsdate >= '$regnstart' and salgsdate <= '$regnslut' order by salgsdate,vare_id,ordre_id,vare_id",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				if ($z>=0 && isset($salgsfaktdate[$z]) && $r['salgsdate']==$salgsfaktdate[$z] && $r['ordre_id'] && $r['ordre_id'] == $soid[$z]) {
					for ($y=0;$y<count($vare_id);$y++) {
						if($r['vare_id']==$vare_id[$y]) {
							if($kontotype[$x]=='D')	{ 
								if ($r['antal']>0) $salgsdebet[$z]+=$r['antal']*$kostpris[$y];
								else $salgskredit[$z]-=$r['antal']*$kostpris[$y];
							} elseif(in_array($kontonr[$x],$varelager_u)) {
								if ($r['antal']>0) $salgskredit[$z]+=$r['antal']*$kostpris[$y];
								else $salgsdebet[$z]-=$r['antal']*$kostpris[$y];
							}
						}
					}
				} else {
					for ($y=0;$y<count($vare_id);$y++) {
						if($r['vare_id']==$vare_id[$y]) {
							if($kontotype[$x]=='D')	{ 
								$z++;
								$soid[$z]=$r['ordre_id'];
								if ($soid[$z]==$soid[$z-1]) $salgsfakt[$z]=$salgsfakt[$z-1];
								else {
									$r2=db_fetch_array(db_select("select fakturanr from ordrer where id='$soid[$z]'",__FILE__ . " linje " . __LINE__));
									$salgsfakt[$z]=$r2['fakturanr'];
								}
								$salgsfaktdate[$z]=$r['salgsdate'];
								if ($r['antal']>0) {
									$salgsdebet[$z]=$r['antal']*$kostpris[$y];
									$salgskredit[$z]=0;
								} else {
									$salgskredit[$z]=$r['antal']*$kostpris[$y]*-1;
									$salgsdebet[$z]=0;
								}
								#$z++;
							} elseif(in_array($kontonr[$x],$varelager_u)) { 
								$z++;
								$soid[$z]=$r['ordre_id'];
								if ($soid[$z]==$soid[$z-1]) $salgsfakt[$z]=$salgsfakt[$z-1];
								else {
									$r2=db_fetch_array(db_select("select fakturanr from ordrer where id='$soid[$z]'",__FILE__ . " linje " . __LINE__));
									$salgsfakt[$z]=$r2['fakturanr'];
								}
								$salgsfaktdate[$z]=$r['salgsdate'];
								if ($r['antal']>0) {
									$salgskredit[$z]=$r['antal']*$kostpris[$y];
									$salgsdebet[$z]=0;
								} else {
									$salgsdebet[$z]=$r['antal']*$kostpris[$y]*-1;
									$salgskredit[$z]=0;
								}
								#$z++;
							}
						}
					}
				}
			}
			$dato=$regnstart;
			$y=0;
			$tr=0;
			$kd=0;
			$sd=0;
			$trd=array();
			while ($dato<=$regnslut) {
				while (isset($transdate[$tr]) && $transdate[$tr]==$dato) {
					$trd[$y]=$dato;
					$bil[$y]=$bilag[$tr];
					$besk[$y]=$beskrivelse[$tr];
					$deb[$y]=$debet[$tr];
					$kre[$y]=$kredit[$tr];
					$tr++;
					$y++;
				}
				while (isset($kobsfaktdate[$kd]) && $kobsfaktdate[$kd]==$dato) {
					$trd[$y]=$dato;
					$bil[$y]=0;
					if ($kobsfakt[$kd]) $besk[$y]="lagertransaktion - Køb F: $kobsfakt[$kd]";
					else $besk[$y]="lagertransaktion - regulering";
					$faktnr[$y]=$kobsfakt[$kd];
					$deb[$y]=$kobsdebet[$kd];
					$kre[$y]=$kobskredit[$kd];
					if ($deb[$y] && $kre[$y]) {
						$z=$y+1;
						$trd[$z]=$trd[$y];
						$bil[$z]=$bil[$y];
						$besk[$z]=$besk[$y];
						$faktnr[$z]=$faktnr[$y];
						$deb[$z]=0;
						$kre[$z]=$kre[$y];
						$kre[$y]=0;
						$y++;
					}
					$kd++;
					$y++;
				}
				while (isset($salgsfaktdate[$sd]) && $salgsfaktdate[$sd]==$dato) {
					$trd[$y]=$dato;
					$bil[$y]=0;
					if ($salgsfakt[$sd]) $besk[$y]="lagertransaktion - Salg F: $salgsfakt[$sd]";
					else $besk[$y]="lagertransaktion - regulering";
					$faktnr[$y]=$salgsfakt[$sd];
					$deb[$y]=$salgsdebet[$sd];
					$kre[$y]=$salgskredit[$sd];
					if ($deb[$y] && $kre[$y]) {
						$z=$y+1;
						$trd[$z]=$trd[$y];
						$bil[$z]=$bil[$y];
						$besk[$z]=$besk[$y];
						$faktnr[$z]=$faktnr[$y];
						$deb[$z]=0;
						$kre[$z]=$kre[$y];
						$kre[$y]=0;
						$y++;
					}
					$sd++;
					$y++;
				}
				list($yy,$mm,$dd)=explode("-",$dato);
				$dd++;
				if (!checkdate($mm,$dd,$yy)) {
					$dd=1;
					$mm++;
					if ($mm>12) {
						$mm=1;
						$yy++;
					}
				}
				$dd*=1;
				$mm*=1;
				if (strlen($dd)<2) $dd='0'.$dd;
				if (strlen($mm)<2) $mm='0'.$mm;
				$dato=$yy."-".$mm."-".$dd;
			}
			for ($y=0;$y<count($trd);$y++){
				$transdate[$y]=$trd[$y];
				$bilag[$y]=$bil[$y];
				$beskrivelse[$y]=$besk[$y];
				$debet[$y]=$deb[$y];
				$kredit[$y]=$kre[$y];
			}
		}
		for ($tr=0;$tr<count($transdate);$tr++) {
			if ($transdate[$tr] && ($debet[$tr] || $kredit[$tr])) {
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				if ($print) print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($transdate[$tr])." </td><td>$bilag[$tr] </td><td>$kontonr[$x] : $beskrivelse[$tr] </td>";
				if ($kontovaluta[$x]) {
					if ($transvaluta[$tr]=='-1') $tmp=0;
					else $tmp=$debet[$tr]*100/$transkurs[$tr];
					$title="DKK ".dkdecimal($debet[$tr]*1,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
				} else {
					$tmp=$debet[$tr];
					$title=NULL;
				}
				if ($print) print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
				if ($kontovaluta[$x]) {
					if ($transvaluta[$tr]=='-1') $tmp=0;
					else $tmp=$kredit[$tr]*100/$transkurs[$tr];
					if ($print) $title="DKK ".dkdecimal($kredit[$tr]*1,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
				} else {
					$tmp=$kredit[$tr];
					$title=NULL;
				}
				if ($print) print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
				$kontosum=$kontosum+afrund($debet[$tr],2)-afrund($kredit[$tr],2);
				if ($kontovaluta[$x]) {
					$tmp=$kontosum*100/$transkurs[$tr];
					if ($print) $title="DKK ".dkdecimal($kontosum,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
				} else {
					$tmp=$kontosum;
					$title=NULL;
				}
				if ($print) print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td></tr>";
				else {
					$qtxt="insert into transaktioner";
					$qtxt.="(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id)";
					$qtxt.=" values ";
					$qtxt.="('0','$transdate[$tr]','$beskrivelse[$tr]','$kontonr[$x]','$fakturanr[$tr]','$debet[$tr]','$kredit[$tr]','0',0,";
					$qtxt.="'$logdate','$logtime','','0','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			} 
		}
	}
}
if ($print) {
	print "<tr><td colspan=6><hr></td></tr>";
	print "</tbody></table>";
} else {
	$qtxt="update grupper set box9 = 'on' where art='RA' and kodenr = '$regnaar'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=regnskabskort.php?&id=$regnaar_id\">";
}

?>