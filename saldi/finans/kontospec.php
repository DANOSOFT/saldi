<?php
// ----------------------finans/kontospec.php------rev 3.5.2-----2015-02-18
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
// 2015.02.18 Tilføjet funktion lagerbev.

@session_start();
$s_id=session_id();
$title="Kontospecifikation";
$css="../css/standard.css";

	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$kontonr=if_isset($_GET['kontonr']);
$month=if_isset($_GET['month']);
$bilag=if_isset($_GET['bilag']);
#if(!$month){$month=13;}

$query = db_select("select * from grupper where art='RA' and kodenr='$regnaar'");
if ($row = db_fetch_array($query)) {
	$startaar=$row['box2'];
	$month=trim($month);
	if (!$month)	{
		$start=$startaar.'-'.$row['box1'].'-01';
		$slutdato=31;
		$month=$row['box3']*1;
		$year=$row['box4']*1;
	}
	else	{
			$month=$month-1+$row['box1'];
			$year=$row['box2'];
			while ($month > 12) {
				$year++;
				$month=$month-12;
			}
		$year=$year;
		if ($month<10)$month='0'.$month*1;
		$start=$year.'-'.$month.'-01';
	}
	$slutdato=31;
	while (!checkdate($month, $slutdato, $year))	{
		$slutdato=$slutdato-1;
	}
	if ($month<10)$month='0'.$month*1;
	$slut=$year.'-'.$month.'-'.$slutdato;
	$start=trim($start);
	$slut=trim($slut);
}

($startaar >= '2015')?$aut_lager='on':$aut_lager=NULL;
if ($aut_lager) {
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
}


print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=100% align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=regnskab.php accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>Specifikation for ";
if($kontonr) print "konto: $kontonr";
if($bilag) print "bilag: $bilag";
print " </td><td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "<tr><td valign=\"top\">";
print "<table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\">";
print "<tbody>";
print "<tr>";
print " <td><b> Bilag</a></b></td>";
print "<td><b> Dato</a></b></td>";
print " <td><b> Bilagstekst</a></b></td>";
print " <td align=right><b> Kontonr</a></b></td>";
print "<td align=right><b> Debet</a></b></td>";
print "<td align=right><b> Kredit</a></b></td>";
print "<td align=right><b> Fakturanr</a></b></td>";
print " <td align=right><b> Kladdenr</a></b></td>";
print " <td align=right><b> Afd. nr</a></b></td>";
print " <td align=right><b> Projekt. nr</a></b></td>";
print "</tr>";
print "<tr><td colspan=11><hr></td></tr>";


if ($kontonr) {
	$transdate=array();
	list ($transdate,$faktura,$ordrenr,$bilag,$beskrivelse,$debet,$kredit)=lagerbev($kontonr,$varekob,$varelager_i,$varelager_u,$start,$slut);
	$valg="and kontonr = '$kontonr'";
	$x=count($transdate);
} elseif ($bilag) {
	$valg="and bilag = '$bilag'";
	$x=0;
}
$q=db_select("select * from transaktioner where transdate >= '$start' and transdate <= '$slut' $valg order by transdate");
while ($r = db_fetch_array($q)) {
	$transdate[$x]=$r['transdate'];
	$faktura[$x]=$r['fakturanr'];
	$bilag[$x]=$r['bilag'];
	$beskrivelse[$x]=$r['beskrivelse'];
	$debet[$x]=$r['debet'];
	$kredit[$x]=$r['kredit'];
	$kladde_id[$x]=$r['kladde_id'];
	$afd[$x]=$r['afd'];
	$projekt[$x]=$r['projekt'];
	$x++;
}

for ($x=0;$x<count($transdate);$x++){
	if ($debet[$x] || $kredit[$x]) {
		if (!$faktura[$x]) $faktura[$x]="ufakt";
		if ($linjebg!=$bgcolor) {
			$linjebg=$bgcolor; $color='#000000';
		} else {
			$linjebg=$bgcolor5; $color='#000000';
		}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td>";
		if ($bilag[$x]) print "<a href=kontospec.php?bilag=$bilag[$x] target=\"_blank\">$bilag[$x]</a><br>";
		print "</td>";
		print "<td>".dkdato($transdate[$x])."</a><br></td>";
		print "<td>$beskrivelse[$x]</a><br></td>";
		print "<td align=\"right\">$kontonr</a><br></td>";
		print "<td align=\"right\">".dkdecimal($debet[$x])."</a><br></td>";
		print "<td align=\"right\">".dkdecimal($kredit[$x])."</a><br></td>";
		print "<td align=\"right\" title=\"Ordrenr: $ordrenr[$x]\">$faktura[$x]</a><br></td>";
		print "<td align=\"right\"><a href=kassekladde.php?kladde_id=$kladde_id[$x]&returside=kontospec.php target=\"_blank\">$kladde_id[$x]</a><br></td>";
		print "<td align=\"right\">$afd[$x]</a><br></td>";
		print "<td align=\"right\">$projekt[$x]</a><br></td>";
		print "</tr>";
	}
}

function lagerbev ($kontonr,$varekob,$varelager_i,$varelager_u,$regnstart,$regnslut) {
	$r=db_fetch_array(db_select("select kontotype from kontoplan where kontonr='$kontonr' order by regnskabsaar desc limit 1",__FILE__ . " linje " . __LINE__));
	$kontotype=$r['kontotype'];
	if (in_array($kontonr,$varekob) || in_array($kontonr,$varelager_i) || in_array($kontonr,$varelager_u)) {
		$z=0;
		$lager=array();
		$gruppe=array();
		$q=db_select("select kodenr,box1,box2 from grupper where art = 'VG' and box8 = 'on' and (box1 = '$kontonr' or box2 = '$kontonr' or box3 = '$kontonr')",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['box1']) {
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
		$z=0;
		$kobsdate=array();
		$kobsdebet=array();
		$kobskredit=array();
		$q=db_select("select vare_id,ordre_id,antal,kobsdate,fakturanr,ordrenr from batch_kob,ordrer where kobsdate >= '$regnstart' and kobsdate <= '$regnslut' and ordrer.id=batch_kob.ordre_id order by kobsdate,vare_id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($z && isset($kobsdate[$z]) && $r['kobsdate']==$kobsdate[$z]) {
				for ($y=0;$y<count($vare_id);$y++) {
					if($r['vare_id']==$vare_id[$y]) {
						if($kontotype=='D')	{ 
							if ($r['antal']>0) $kobskredit[$z]+=$r['antal']*$kostpris[$y];
							else $kobsdebet[$z]-=$r['antal']*$kostpris[$y];
							} elseif(in_array($kontonr,$varelager_i)) {
							if ($r['antal']>0) $kobsdebet[$z]+=$r['antal']*$kostpris[$y];
							else $kobskredit[$z]-=$r['antal']*$kostpris[$y];
						}
					}
				}
			} else {
				for ($y=0;$y<count($vare_id);$y++) {
					if($r['vare_id']==$vare_id[$y]) {
						if($kontotype=='D')	{ 
							$k_fakturanr[$z]=$r['fakturanr'];
							$k_ordrenr[$z]=$r['ordrenr'];
							$kobsdate[$z]=$r['kobsdate'];
							if ($r['antal']>0) {
								$kobskredit[$z]=$r['antal']*$kostpris[$y];
								$kobsdebet[$z]=0;
							} else {
								$kobsdebet[$z]=$r['antal']*$kostpris[$y]*-1;
								$kobskredit[$z]=0;
							}
							$z++;
						} elseif(in_array($kontonr,$varelager_i)) {
							$k_fakturanr[$z]=$r['fakturanr'];
							$k_ordrenr[$z]=$r['ordrenr'];
							$kobsdate[$z]=$r['kobsdate'];
							if ($r['antal']>0) {
								$kobsdebet[$z]=$r['antal']*$kostpris[$y];
								$kobskredit[$z]=0;
							} else {
								$kobskredit[$z]=$r['antal']*$kostpris[$y]*-1;
								$kobsdebet[$z]=0;
							}
							$z++;
						}
					}
				}
			}
		}
		$z=0;
		$salgsdate=array();
		$salgsdebet=array();
		$salgkredit=array();
		$q=db_select("select ordre_id,vare_id,antal,salgsdate,fakturanr,ordrenr from batch_salg,ordrer where salgsdate >= '$regnstart' and salgsdate <= '$regnslut' and ordrer.id=batch_salg.ordre_id order by salgsdate,ordre_id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
		
			if ($z && isset($salgsdate[$z]) && $r['salgsdate']==$salgsdate[$z]) {
				for ($y=0;$y<count($vare_id);$y++) {
					if($r['vare_id']==$vare_id[$y]) {
						if($kontotype=='D')	{ 
							if ($r['antal']>0) $salgsdebet[$z]+=$r['antal']*$kostpris[$y];
							else $salgskredit[$z]-=$r['antal']*$kostpris[$y];
						} elseif(in_array($kontonr,$varelager_u)) {
							if ($r['antal']>0) $salgskredit[$z]+=$r['antal']*$kostpris[$y];
							else $salgsdebet[$z]-=$r['antal']*$kostpris[$y];
						}
					}
				}
			} else {
				for ($y=0;$y<count($vare_id);$y++) {
					if($r['vare_id']==$vare_id[$y]) {
						if($kontotype=='D')	{ 
							$s_fakturanr[$z]=$r['fakturanr'];
							$s_ordrenr[$z]=$r['ordrenr'];
							$salgsdate[$z]=$r['salgsdate'];
							if ($r['antal']>0) {
								$salgsdebet[$z]=$r['antal']*$kostpris[$y];
								$salgskredit[$z]=0;
							} else {
								$salgskredit[$z]=$r['antal']*$kostpris[$y]*-1;
								$salgsdebet[$z]=0;
							}
							$z++;
						} elseif(in_array($kontonr,$varelager_u)) { 
							$s_fakturanr[$z]=$r['fakturanr'];
							$s_ordrenr[$z]=$r['ordrenr'];
							$salgsdate[$z]=$r['salgsdate'];
							if ($r['antal']>0) {
								$salgskredit[$z]=$r['antal']*$kostpris[$y];
								$salgsdebet[$z]=0;
							} else {
								$salgsdebet[$z]=$r['antal']*$kostpris[$y]*-1;
								$salgskredit[$z]=0;
							}
							$z++;
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
/*
		while (isset($transdate[$tr]) && $transdate[$tr]==$dato) {
				$trd[$y]=$dato;
				$bil[$y]=$bilag[$tr];
				$besk[$y]=$beskrivelse[$tr];
				$deb[$y]=$debet[$tr];
				$kre[$y]=$kredit[$tr];
				$tr++;
				$y++;
			}
*/			
			while (isset($kobsdate[$kd]) && $kobsdate[$kd]==$dato) {
				$trd[$y]=$dato;
				$bil[$y]=0;
				$fakt[$y]=$k_fakturanr[$kd];
				$ordre[$y]=$k_ordrenr[$kd];
				$besk[$y]="lagertransaktion - Køb";
				$deb[$y]=$kobsdebet[$kd];
				$kre[$y]=$kobskredit[$kd];
				$kd++;
				$y++;
			}
			while (isset($salgsdate[$sd]) && $salgsdate[$sd]==$dato) {
				$trd[$y]=$dato;
				$bil[$y]=0;
				$fakt[$y]=$s_fakturanr[$sd];
				$ordre[$y]=$s_ordrenr[$sd];
				$besk[$y]="lagertransaktion - Salg";
				$deb[$y]=$salgsdebet[$sd];
				$kre[$y]=$salgskredit[$sd];
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
			$fakturanr[$y]=$fakt[$y];
			$ordrenr[$y]=$ordre[$y];
			$bilag[$y]=$bil[$y];
			$beskrivelse[$y]=$besk[$y];
			$debet[$y]=$deb[$y];
			$kredit[$y]=$kre[$y];
		}
	}
	return array($transdate,$fakturanr,$ordrenr,$bilag,$beskrivelse,$debet,$kredit);
}

?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
