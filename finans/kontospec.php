<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kontospec.php --- rev 4.1.1 --- 2025.01.13 ---
// LICENSE
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
//
// Copyright (c) 2003-2025 saldi.dk ApS
// ----------------------------------------------------------------------
// 20150218 Tilføjet funktion lagerbev.
// 20210211 PHR some cleanup
// 20210708 LOE - Translated some of these texts from Danish to English and Norsk
// 20250113 PHR fiscal_year

$fakturanr = array();
$ordrenr   = array();
$transdate = array();
$varekob = $varelager_i = $varelager_u = array();

$linjebg = NULL;

@session_start();
$s_id=session_id();
$title="Kontospecifikation";
$css="../css/standard.css";

global $menu;
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

$kontonr=if_isset($_GET['kontonr']);
$month=if_isset($_GET['month']);
$bilag=if_isset($_GET['bilag']);
#if(!$month){$month=13;}

$query = db_select("select * from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
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
	$qtxt = "select box1,box2,box3 from grupper where art = 'VG' and box8 = 'on' and fiscal_year = '$regnaar'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['box1'] && $r['box2'] && !in_array($r['box3'],$varekob)) {
			$varelager_i[$x]=$r['box1'];
			$varelager_u[$x]=$r['box2'];
			$varekob[$x]=$r['box3'];
			$x++;
		}
	}
}

$txt2131 = findtekst('2131|konto', $sprog_id);
$txt2132 = findtekst('2132|bilag', $sprog_id);
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\"><a href=regnskab.php accesskey=L title='Klik for at komme tilbage til regnskab'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
	print "<div class=\"headerTxt\">".findtekst(1196,$sprog_id)." ";
	if($kontonr) print "$txt2131: $kontonr";
	if($bilag) print "$txt2132: $bilag";
	print "</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu=='S') {
	print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=100% align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";

	print "<td width='10%'><a href=regnskab.php accesskey=L title='Klik for at komme tilbage til regnskab'>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst(30,$sprog_id)."</button></a></td>";

	print "<td width='80%' align='center' style='$topStyle'>".findtekst(1196,$sprog_id)." ";
	if($kontonr) print "$txt2131: $kontonr";
	if($bilag) print "$txt2132: $bilag";

	print " </td><td width='10%' align='center' style='$topStyle'><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<tr><td valign=\"top\">";
} else {
	print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=100% align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=regnskab.php accesskey=L title='Klik for at komme tilbage til regnskab'>".findtekst(30,$sprog_id)."</a></td>"; #20210708
	print "<td width=\"80%\" $top_bund>".findtekst(1196,$sprog_id)." ";
	if($kontonr) print "$txt2131: $kontonr";
	if($bilag) print "$txt2132: $bilag";
	print " </td><td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<tr><td valign=\"top\">";
}

	print "<table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\" class='dataTable'>";
	print "<tbody>";
	print "<tr>";
	print " <td><b> ".findtekst(671,$sprog_id)."</a></b></td>";
	print "<td><b> ".findtekst(635,$sprog_id)."</a></b></td>";
	print " <td><b> ".findtekst(1068,$sprog_id)."</a></b></td>";
	print " <td align=right><b> ".findtekst(804,$sprog_id)."</a></b></td>";
	print "<td align=right><b> ".findtekst(1000,$sprog_id)."</a></b></td>";
	print "<td align=right><b> ".findtekst(1001,$sprog_id)."</a></b></td>";
	print "<td align=right><b> ".findtekst(828,$sprog_id)."</a></b></td>";
	print " <td align=right><b> ".findtekst(1197,$sprog_id)."</a></b></td>";
	print " <td align=right><b> ".findtekst(1198,$sprog_id)."</a></b></td>";
	print " <td align=right><b> ".findtekst(1199,$sprog_id)."</a></b></td>";
	print "</tr>";


if ($kontonr) {
	$transdate=array();
	list ($transdate,$faktura,$ordrenr,$bilag,$beskrivelse,$debet,$kredit)=lagerbev($kontonr,$varekob,$varelager_i,$varelager_u,$start,$slut);
	$valg="and kontonr = '$kontonr'";
	$x=count($transdate);
} elseif ($bilag) {
	$valg="and bilag = '$bilag'";
	$x=0;
}
$qtxt = "select * from transaktioner where transdate >= '$start' and transdate <= '$slut' $valg order by transdate";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$transdate[$x]=$r['transdate'];
	$faktura[$x]=$r['faktura'];
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
	if (!isset($ordrenr[$x])) $ordrenr[$x] = 0;
	if ($debet[$x] || $kredit[$x]) {
		if (!$faktura[$x]) $faktura[$x] = '';
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
	global $regnaar;

	$beskrivelse = $bilag = $debet = $fakturanr = $kredit = $ordrenr = $transdate = array();
	
	$r=db_fetch_array(db_select("select kontotype from kontoplan where kontonr='$kontonr' order by regnskabsaar desc limit 1",__FILE__ . " linje " . __LINE__));
	$kontotype=$r['kontotype'];
	if (in_array($kontonr,$varekob) || in_array($kontonr,$varelager_i) || in_array($kontonr,$varelager_u)) {
		$z=0;
		$lager=array();
		$gruppe=array();
		$qtxt = "select kodenr,box1,box2 from grupper where art = 'VG' and box8 = 'on' and ";
		$qtxt.= "(box1 = '$kontonr' or box2 = '$kontonr' or box3 = '$kontonr') and fiscal_year = $regnaar";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
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

print "</tbody>
</table>
	</td></tr>
</tbody></table>
";

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
#} else {
#	include_once '../includes/oldDesign/footer.php';
}

?>
