<?php

// ------- finans/listeangivelse.php ------------- lap 3.4.3 --- 2014-07-29 ---
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------------
// 
// 20130210 Break ændret til break 
// 20140729 CA  - Listeanvisning ændres fra kvartal til måned - ca. Søg 20140729


@session_start();
$s_id=session_id();
$title="Listeangivelse";
$modulnr=4;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");


$listefilnavn = "../temp/listeangivelse_".trim($db)."_".date('ymdH').".csv";
#$tophtml="<html>\n<head><title>$title</title></head>\n<body>\n";
$tophtml= "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>\n";
$tophtml.= "<tr><td height = 25 align=center valign=top>";
$tophtml.= "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><td width=10% $top_bund>\n";

$debughtml="<!-- DEBUG INFO - start -->\n<div style='background: yellow'>\n";

$bodyhtml="";

$bottomhtml="</td></tr>\n";
$bottomhtml.="<tr><td colspan=$colspan width=100%><hr></td></tr>\n";
$bottomhtml.="</tbody></table>\n\n";
$bottomhtml.="\n</body>\n</html>";

$md[1]="januar"; $md[2]="februar"; $md[3]="marts"; $md[4]="april"; $md[5]="maj"; $md[6]="juni"; $md[7]="juli"; $md[8]="august"; $md[9]="september"; $md[10]="oktober"; $md[11]="november"; $md[12]="december";

if ($popup) {
	$returside="../includes/luk.php";
} else {
	$returside="rapport.php";
}

$query=db_select("select cvrnr, firmanavn, addr1, addr2, postnr, bynavn from adresser where art = 'S'",__FILE__ . " linje " . __LINE__);
while ($row=db_fetch_array($query)) {
        $egetcvrnr=preg_replace('/\D/', '', $row['cvrnr']);
	$adrhtml = $row['firmanavn']."<br />\n";
	$adrhtml.= $row['addr1']."<br />\n";
	if ( $addr2 ) $adrhtml.= $row['addr2']."<br />\n";
	$adrhtml.= $row['postnr']." ".$row['bynavn'];
}

# Kan forbedres ved at slaa op i kontoplan og se om kontiene er samlekonti og i stedet have euvarekonti[] og euydelsekonti[]
$query=db_select("select box3, box4 from grupper where art = 'MR'",__FILE__ . " linje " . __LINE__);
while ($row=db_fetch_array($query)) {
        $euvarekonto=$row['box3'];
        $euydelseskonto=$row['box4'];
}

$debughtml.="<p>[".$euvarekonto."|".$euydelseskonto."]</p>\n";

if ($_POST){ # 20140729 Start afsnit 1
        $listeperiode=isset($_POST['listeperiode'])? $_POST['listeperiode']:NULL;
} elseif ($_GET) {
        $listeperiode=isset($_GET['listeperiode'])? $_GET['listeperiode']:NULL;
} else {
	# $bodyhtml.=vis_alle_kvartaler();
	$bodyhtml.="\n<h1>Ingen perioder valgt</h1>\n<p>Klik p&aring; linket Luk og v&aelig;lg en periode.</p>\n\n";
	print $tophtml.$bodyhtml.$bottomhtml;
	exit;
}

list($liste_md, $liste_aar) = explode(".", $listeperiode);

if ( $liste_md < 10 ) $liste_md="0".$liste_md; 
$liste_startdato=$liste_aar."-".$liste_md."-01";
$liste_slutdato=$liste_aar."-".$liste_md."-".sidste_dag_i_maaned($liste_aar, $liste_md);
$liste_slutdato_yymmdd=substr($liste_aar,2,2).$liste_md.sidste_dag_i_maaned($liste_aar, $liste_md); # 20140729 slut afsnit 1

#$query=db_select("select * from grupper where art = 'RA' order by box2 desc",__FILE__ . " linje " . __LINE__);
#$x=0;
#while ($row = db_fetch_array($query)) {
#}
#$regnaar[0]=

$datafil = "0,".$egetcvrnr.",LISTE,,,,,,";

$eu_debitorgrp[0] = 2;
$totalsumdkk=0;
$antal_poster=0;

$query=db_select("select id, cvrnr from adresser where art = 'D'",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	$varesumdkk=0;
	$ydelsessumdkk=0;
	$fakturaer=0;
	$debitorcvrnr=$row[cvrnr];
	if ( cvrnr_omr(cvrnr_land($debitorcvrnr)) == "EU" ) { 
		echo "<!-- <p>Hvis i EU </p> -->\n" ;
	} else { 
		continue;
	}
	$debughtml.="<p>Debitor: ".$row[id]."</p>\n";
	$debughtml.= "\n<table>\n";
	$debughtml.= "<tr><th>Dato</th><th>Cvrnr.</th><th>Bel&oslash;b</th><th>Valuta</th></tr>\n";
#cho "select id, fakturadate, kontonr, sum, cvrnr, valuta, valutakurs from ordrer where konto_id = '$row[id]' and fakturadate >= '$kvartal_startdato' and fakturadate <= '$kvartal_slutdato' and status = '4' order by cvrnr<br>";
	$q=db_select("select id, fakturadate, kontonr, sum, cvrnr, valuta, valutakurs from ordrer where konto_id = '$row[id]' and fakturadate >= '$liste_startdato' and fakturadate <= '$liste_slutdato' and status = '4' order by cvrnr",__FILE__ . " linje " . __LINE__); # 20140729 afsnit 2
	while ($r = db_fetch_array($q)) {
		$fakturaer++;
		if ( $r[cvrnr] ) { 
			$modtagercvrnr=$r['cvrnr'];
		} else {
			$modtagercvrnr=$debitorcvrnr;
		}
		$modtagerlandekode = strtoupper(substr($modtagercvrnr, 0, 2));
		$modtagercvrnr = strtoupper(substr($modtagercvrnr, 2));
#		$modtagercvrnr = strtoupper(preg_replace('/W', '', preg_replace('_', '', $modtagercvrnr)));
#cho "$modtagerlandekode $modtagercvrnr>br>";
		# EU-vare- og ydelseskoeb
#cho "select ordrelinjer.pris as pris, ordrelinjer.antal as antal, ordrelinjer.rabat as rabat, grupper.box11 as konto from ordrelinjer, varer, grupper where ordrelinjer.ordre_id = '$r[id]' and ordrelinjer.varenr=varer.varenr and varer.gruppe=grupper.kodenr and grupper.art='VG'<br>";
		$qq=db_select("select ordrelinjer.pris as pris, ordrelinjer.antal as antal, ordrelinjer.rabat as rabat, grupper.box11 as konto from ordrelinjer, varer, grupper where ordrelinjer.ordre_id = '$r[id]' and ordrelinjer.varenr=varer.varenr and varer.gruppe=grupper.kodenr and grupper.art='VG'",__FILE__ . " linje " . __LINE__);
		while ($rr = db_fetch_array($qq)) {
#cho $rr[konto]." a ".$rr[antal]." a ".$rr[pris]." $euvarekonto $euydelseskonto<br>";
			$debughtml.="<tr><td>".$rr['antal']." a ".$rr['pris']." (-".$rr['rabat']."</td><td>Konto: ".$rr['konto']."</td></tr>\n";
			if ( $rr['konto'] == $euvarekonto ) {
				$varesumdkk+= number_format($r['valutakurs']*$rr['pris']*$rr['antal']*((100-$rr['rabat'])/100)/100,0,'','');
			}	
			if ( $rr['konto'] == $euydelseskonto ) $ydelsessumdkk+= number_format($r['valutakurs']*$rr['pris']*$rr['antal']*((100-$rr['rabat'])/100)/100,0,'','');
		}


		if ( $varesumdkk || $ydelsessumdkk ) {
			if ( strlen($r['kontonr']) > 10 ) {
				$internref="xxxxxxxxxx";
			} else {
				$internref=substr($r['kontonr'],0,10);
			} 
		}
	}

	if ( $fakturaer > 0 ) {
		if ( $varesumdkk <> 0 || $ydelsessumdkk <> 0 ) {
			$totalsumdkk+=$varesumdkk+$ydelsessumdkk;
			$antal_poster++;
			$datalinje="2,".$internref.",".$kvartal_slutdato.",".$egetcvrnr.",".$modtagerlandekode.",".$modtagercvrnr.",".$varesumdkk.",0,".$ydelsessumdkk;
			$datafil .= "\n".$datalinje;
		}

                if ($linjebg!=$bgcolor) {
			$linjebg=$bgcolor; $color='#000000';
		} else {
			$linjebg=$bgcolor5; $color='#000000';
		}

		$listehtml.= "<tr style='background: $linjebg'><td style='font-weight: bold; background: $linjebg; color: $color'>".$modtagerlandekode."</td><td style='font-weight: bold; background: $linjebg; color: $color'>".$modtagercvrnr."</td><td style='font-weight: bold; background: $linjebg; color: $color'>".$varesumdkk."</td><td style='font-weight: bold; background: $linjebg; color: $color'>&nbsp;</td><td style='font-weight: bold; background: $linjebg; color: $color'>".$ydelsessumdkk."</td></tr>\n";
	}
	$debughtml.= "</table>\n\n";
} 

$tophtml.= "<a href='$returside' accesskey=L>Luk</a></td>\n";
$tophtml.= "<td width=80% $top_bund align=center>Listeangivelse ".$liste_md.". måned ".$liste_aar."</td>\n"; # 20140729 afsnit 3
$tophtml.= "<td width=10% $top_bund >&nbsp;</td>\n";
$tophtml.= "</tr></tbody></table></td>\n";
$tophtml.= " </td></tr>\n<tr><td align=\"center\" valign=\"top\" width=\"100%\">\n";

if ( $antal_poster > 0 ) {
	$datafil .= "\n10,".$antal_poster.",".$totalsumdkk.",,,,,,\n";
	
	$debughtml.="<p>fopen: '".fopen($listefilnavn,"w")."'</p>\n";
	$fp=fopen($listefilnavn,"w");
	if ($fp) {
		fwrite($fp,$datafil);
		$bodyhtml.="<h2>Indsend via SKAT's hjemmeside</h2>\n\n";
		$bodyhtml.="<p>Hent denne <a href='".$listefilnavn."' title='Listeangivelsesfil som kan l&aelig;gges op via SKATs hjemmeside'>listeangivelsesfil</a>\n";
		$bodyhtml.="og send den via <a href='http://skat.dk/'>SKAT's hjemmeside</a>.</p>\n";
		$bodyhtml.="<hr />\n\n";
	} else {
		$debughtml.="<p>\$fp = '".$fp."'<br />\$listefilnavn = '".$listefilnavn."'</p>\n";
	}
	fclose($fp);

	$bodyhtml.="<h2>Data til udfyldelse af papirblanket</h2>\n\n";
	$bodyhtml.="<p>S&aelig;lgers CVR-/SE-nr.: <strong>".$egetcvrnr."</strong></p>\n";
	$bodyhtml.="<p>S&aelig;lgers navn og adresse:<br /><strong>".$adrhtml."</strong></p>\n";
	$bodyhtml.="<p>Periode (1): <strong>".$kvartal_slutdato_yymmdd."</strong></p>\n"; # Sidste dag i perioden i formatet YYMMDD
	$bodyhtml.="<p>Periodens samlede varesalg, ydelsessalg og trekantshandel til EUlande<br />\nuden moms i hele danske kr. (2):<br />\n";
	$bodyhtml.="<strong>".$totalsumdkk."</strong></p>\n";
	$bodyhtml.="<table>\n";
	$bodyhtml.="<tr>\n";
	$bodyhtml.="<td>Landekode for <br />varemodtager <strong>(3)</strong></td>\n";
	$bodyhtml.="<td>Varemodtagerens <br />moms-nr.<strong>(4)</strong></td>\n";
	$bodyhtml.="<td>Samlet varesalg mv. <br />i danske kroner <strong>(5)</strong></td>\n";
	$bodyhtml.="<td style=\"color:#666\" title=\"Trekantshandel er endnu ikke underst&oslash;ttet.\">";
	$bodyhtml.="Bel&oslash;b for trekants-<br />handel i danske kroner <strong>(6)</strong></td>\n";
	$bodyhtml.="<td>Bel&oslash;b for tjeneste-<br />ydelser i danske kroner</td>\n";
	$bodyhtml.="</tr>\n";
	$bodyhtml.=$listehtml;
	$bodyhtml.="</table>\n\n";
	$bodyhtml.="<p>Husk at underskrive papirblanketten inden afsendelse.</p>\n\n";
} else { # Hvis ingen poster
	$bodyhtml.="<h2>Ingen poster i perioden!</h2>\n";
	$bodyhtml.="<p>Der er ikke sendt nogen fakturaer med CVR-nr. (momsnr.) tilh&oslash;rende EU-lande i perioden.</p>\n";
	$bodyhtml.="<p>Hvis der er sendt fakturaer til EU-lande i perioden, så kontroll&eacute;r at CVR-nummeret er angivet med \n";
	$bodyhtml.="landekoden forrest - eksempelvis DE for tyske kunder og FR for franske.</p>\n";

}

$debughtml .= "<pre>$datafil</pre>\n\n";

$debughtml .= "</div>\n<!-- DEBUG INFO - end -->\n\n";

if ( isset($_POST[debug]) || isset($_GET[debug]) ) {
	print $tophtml.$bodyhtml.$debughtml.$bottomhtml;
} else {
	print $tophtml.$bodyhtml.$bottomhtml;
}


function vis_alle_perioder() { # 20140729 afsnit 4
	$retur="";
	
	$retur.="\n\n<h1>Her bliver alle perioder listet</h1>\n\n";

	return $retur;
}


function varelinjer($ordre_id, $faktdate, $udlign_date, $provision, $faktnr, $firmanavn, $pro_procent)
{
	global $kostkilde;

	$linje_id=array();
#	$q1 = db_select("SELECT DISTINCT ordrelinjer.id as linje_id, ordrelinjer.vare_id as vare_id, ordrelinjer.antal as antal, ordrelinjer.pris as pris, ordrelinjer.rabat as rabat, varer.kostpris as kostpris, varer.gruppe as gruppe, batch_salg.batch_kob_id as batch_kob_id from ordrelinjer, varer, batch_salg where ordrelinjer.ordre_id='$ordre_id' and varer.id = ordrelinjer.vare_id and batch_salg.linje_id=ordrelinjer.id");
	$q1 = db_select("SELECT DISTINCT ordrelinjer.id as linje_id, ordrelinjer.vare_id as vare_id, ordrelinjer.antal as antal, ordrelinjer.pris as pris, ordrelinjer.rabat as rabat, varer.kostpris as kostpris, varer.gruppe as gruppe from ordrelinjer, varer where ordrelinjer.ordre_id='$ordre_id' and varer.id = ordrelinjer.vare_id");
	$y=1000;
	while ($r1 = db_fetch_array($q1)) {
		if (!in_array($r1[linje_id], $linje_id)) {
			$y++;
			$linje_id[$y]=$r1['linje_id'];
			$pris[$y]=0;
			$kostpris[$y]=0;
			$pris[$y]=($r1['pris']-($r1['pris']/100*$r1['rabat']))*$r1['antal'] ;
			$pris[$x]=$pris[$x]+$pris[$y];
			if ($kostkilde=='kort') {
				$kostpris[$y]=$r1['kostpris']*$r1['antal'];
				$kostpris[$x]=$kostpris[$x]+$kostpris[$y];
			} else {
				$r2=db_fetch_array(db_select("SELECT box8 from grupper where art='VG' and kodenr = '$r1[gruppe]'"));
				if ($r2[box8]=='on') {
					$q3=db_select("SELECT batch_salg.antal as antal, batch_kob.pris as kostpris from batch_kob, batch_salg where batch_salg.linje_id='$r1[linje_id]' and batch_kob.id=batch_salg.batch_kob_id");
					while ($r3=db_fetch_array($q3)) {
			#		$r3=db_fetch_array(db_select("SELECT pris as kostpris from batch_kob where id= '$r1[batch_kob_id]'"));
						$kostpris[$y]=$r3['kostpris']*$r3['antal'];
# if ($faktnr==168) echo "168 - $pris[$y]=($r1[pris]-($r1[pris]/100*$r1[rabat]))*$r1[antal]  ---  $kostpris[$y]=$r3[kostpris]*$r3[antal]<br>";
# if ($faktnr==173) echo "173 - $pris[$y]=($r1[pris]-($r1[pris]/100*$r1[rabat]))*$r1[antal]  ---  $kostpris[$y]=$r3[kostpris]*$r3[antal]<br>";
# if ($faktnr==174) echo "174 - $pris[$y]=($r1[pris]-($r1[pris]/100*$r1[rabat]))*$r1[antal]  ---  $kostpris[$y]=$r3[kostpris]*$r3[antal]<br>";
						$kostpris[$x]=$kostpris[$x]+$kostpris[$y];
					}
				} else {
					$kostpris[$y]=$r1['kostpris']*$r1['antal'];
					$kostpris[$x]=$kostpris[$x]+$kostpris[$y];
				}
			}
		}
	}
	$tmp=$pris[$x] - $kostpris[$x];
	$tmp2=$tmp/100*$provision/100*$pro_procent;
	print "<tr><td>".dkdato($faktdate)."</td><td> ".dkdato($udlign_date)."</td>";
	print "<td align=right onClick=\"javascript:d_ordre=window.open('../debitor/ordre.php?id=$ordre_id','d_ordre','scrollbars=yes,resizable=yes,dependent=yes');d_ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"><u><span title=\"$firmanavn\">$faktnr</span></u></td>";
	print "<td align=right>".dkdecimal($kostpris[$x])."</td><td align=right>".dkdecimal($pris[$x])."</td><td align=right>".dkdecimal($tmp)."</td><td align=right>".dkdecimal($tmp2)."</td></tr>";

	return array($pris[$x],$kostpris[$x],$tmp2);	
}

function predato($dato)
{
	list($dag, $md, $aar)=explode("-",$dato);
	if ($md==1) {
		$md=12;
		$aar=$aar-1;
	}
	else $md=$md-1;
	$dag=$dag*1;
	$md=$md*1;
	if($dag<10) $dag="0".$dag;
	if($md<10) $md="0".$md;
	$dato=$dag."-".$md."-".$aar;
	return $dato;
}

function slutdato($dato)
{
	list($dag, $md, $aar)=explode("-",$dato);
	if ($dag==1) {
		$dag=31;
		while (!checkdate($md,$dag,$aar)) {
			$dag=$dag-1;
			if ($dag<28) break 1;
		}
	} elseif($md==12) {
		$md=1;
		$aar=$aar+1;
		$dag=$dag-1;
	} else {
		$dag=$dag-1;
		$md=$md+1;
	}
	$dag=$dag*1;
	$md=$md*1;
	if($dag<10) $dag="0".$dag;
	if($md<10) $md="0".$md;
	$dato=$dag."-".$md."-".$aar;
	return $dato;
}

?>
