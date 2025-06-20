<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ finans/rapport_includes/momsangivelse.php -------- lap 3.7.8 --- 2019-03-24 ---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2019 saldi.dk ApS
// ----------------------------------------------------------------------

function momsangivelse ($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering,$lagerbev)
{
	global $connection;
	global $top_bund;
	global $md;
	global $ansatte;
	global $ansatte_id;
	global $afd_navn;
	global $prj_navn_fra;
	global $prj_navn_til;
	global $menu;

	$medtag_primo=if_isset($_GET['medtag_primo']);

	if ($row = db_fetch_array(db_select("select firmanavn from adresser where art='S'",__FILE__ . " linje " . __LINE__))) $firmanavn=$row['firmanavn'];
	if (($afd)&&($row = db_fetch_array(db_select("select beskrivelse from grupper where art='AFD' and kodenr='$afd'",__FILE__ . " linje " . __LINE__)))) $afd_navn=$row['beskrivelse'];
	if (!isset ($afgiftssum)) $afgiftssum = NULL;

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

#	list ($x, $maaned_fra) = explode(" ", $maaned_fra);
#	list ($x, $maaned_til) = explode(" ", $maaned_til);

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);
	$konto_fra=trim($konto_fra);
	$konto_til=trim($konto_til);

	$mf=$maaned_fra;
	$mt=$maaned_til;

	for ($x=1; $x<=12; $x++){
		if ($maaned_fra==$md[$x]) $maaned_fra=$x;
		if ($maaned_til==$md[$x]) $maaned_til=$x;
		if (strlen($maaned_fra)==1) $maaned_fra="0".$maaned_fra;
		if (strlen($maaned_til)==1) $maaned_til="0".$maaned_til;
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
#	$regnaar=$row[kodenr];
	$startmaaned=$row['box1']*1;
	$startaar=$row['box2']*1;
	$slutmaaned=$row['box3']*1;
	$slutaar=$row['box4']*1;
	$slutdato=31;

	##
	$regnaarstart= $startaar. "-" . $startmaaned . "-" . '01';

	if ($maaned_fra) $startmaaned=$maaned_fra;
	if ($maaned_til) $slutmaaned=$maaned_til;
	if ($dato_fra) $startdato=$dato_fra;
	if ($dato_til) $slutdato=$dato_til;

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}

	while (!checkdate($startmaaned,$startdato,$startaar)){
		$startdato=$startdato-1;
		if ($startdato<28) break 1;
	}

	while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
		$slutdato=$slutdato-1;
		if ($slutdato<28) break 1;
	}
	if (strlen($startdato)<2) $startdato="0".$startdato;


	$regnstart = $aar_fra. "-" . $startmaaned . "-" . $startdato;
	$regnslut = $aar_til . "-" . $slutmaaned . "-" . $slutdato;
#	$regnstart = $startaar. "-" . $startmaaned . "-" . $startdato;
#	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

#	print "  <a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Luk</a><br><br>";

if ($menu=='T') {
	$leftbutton="<a class='button red small' title=\"Klik her for at komme til forsiden af rapporter\" href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\" accesskey=\"L\">Luk</a>";
	$rightbutton="";
	include("../includes/top_header.php");
	include("../includes/top_menu.php");
	print "<div id=\"header\"> 
	<div class=\"headerbtnLft\">$leftbutton</div>
	<span class=\"headerTxt\">Rapport - Kontokort</span>";     
	print "<div class=\"headerbtnRght\"></div>";       
	print "</div><!-- end of header -->
		<div class=\"maincontentLargeHolder\">\n";
		print  "<table class='dataTable2' border='0' cellspacing='1' width='100%'>";
} elseif ($menu=='S') {
	include("../includes/sidemenu.php");
} else {
	print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\" align='center'> ";
		print "<tr><td colspan=\"6\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\">Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Rapport - ".ucfirst($rapportart)."</td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
	}
 	print "<tr><td colspan=\"4\"><big><big>".ucfirst($rapportart)."</span></big></big></td>";
	print "<td colspan=2 align=right><table style=\"text-align: left; width: 400px;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
	if ($afd) {
		print "<td>Afdeling</span></td>";
		print "<td>$afd: $afd_navn</span></td></tr>";
	}
	print "<td>Regnskabs&aring;r</span></td>";
	print "<td>$regnaar.</span></td></tr>";
	print "<tr><td>Periode</span></td>";
	print "<td>Fra </td><td>".dkdato($regnstart)."</td></tr><tr><td></td><td>Til &nbsp;&nbsp;</td><td>".dkdato($regnslut)."</span></td></tr>";
	if ($ansat_fra) {
		if (!$ansat_til || $ansat_fra==$ansat_til) print "<tr><td>Medarbejder</span></td><td>$ansatte</span></td></tr>";
		else print "<tr><td>Medarbejdere</span></td><td>$ansatte</span></td></tr>";
	}
	if ($afd) print "<tr><td>Afdeling</span></td><td>$afd_navn</span></td></tr>";
	if ($projekt_fra) {
		print "<tr><td>Projekt</td>";
		if (!strstr($projekt_fra,"?")) {
			print "<td>$prj_navn_fra ";
			if ($projekt_til && $projekt_fra != $projekt_til) print "- $prj_navn_til ";
		} else print "<td>$projekt_fra ";
		print "</td></tr>";
	}	
	print "</tbody></table></td></tr>";

	print "<tr><td colspan=4><big><b>$firmanavn</b></big></td></tr>";
	print "<tr><td colspan=6><hr></td></tr>";

	$dim='';
	if ($afd||$ansat_fra||$projekt_fra) {
		if ($afd) $dim = "and afd = $afd ";
		if ($ansat_fra) $dim = $dim."and ansat = '$ansat_fra' ";
		$projekt_fra=str2low($projekt_fra);
		$projekt_til=str2low($projekt_til);
		if ($projekt_fra && $projekt_til && $projekt_fra!=$projekt_til) $dim = $dim." and lower(projekt) >= '$projekt_fra' and lower(projekt) <= '$projekt_til' ";
		elseif ($projekt_fra) {
			$tmp=str_replace("?","_",$projekt_fra);
			if (substr($tmp,-1)=='_') {
				while (substr($tmp,-1)=='_') $tmp=substr($tmp,0,strlen($tmp)-1);
				$tmp=str2low($tmp)."%";
			}
			$dim = $dim."and lower(projekt) LIKE '$tmp' ";
		}
	}

	$row = db_fetch_array($query = db_select("select box1, box2 from grupper where art='MR'",__FILE__ . " linje " . __LINE__));
	if (($row['box1']) && ($row['box2'])) {
		$konto_fra=$row['box1'];
		$konto_til=$row['box2'];

		$x=0;
		$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' and kontonr>=$konto_fra and kontonr<=$konto_til order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)){
			$x++;
			$kontonr[$x]=$row['kontonr']*1;
			$kontobeskrivelse[$x]=$row['beskrivelse'];
			$kontotype[$x]=$row['kontotype'];
			$primo[$x]=$row['primo'];
			$aarsum[$x]=0;
		}

			
		$kontoantal=$x;
		$kto_aar[$x]=0;
		$kto_periode[$x]=0;
		$ktonr=array();
		$x=0;
		$query = db_select("select * from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr>='$konto_fra' and kontonr<='$konto_til' $dim order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (!in_array($row['kontonr'], $ktonr)) { # Her fanges konto med bevaegelser i perioden.
				$x++;
				$ktonr[$x]=$row['kontonr']*1;
				$kto_aar[$x]=0;
				if (($medtag_primo && !$afd) && ($r2 = db_fetch_array(db_select("select primo from kontoplan where regnskabsaar='$regnaar' and kontonr=$ktonr[$x] and kontotype='S'",__FILE__ . " linje " . __LINE__)))) {
					$kto_aar[$x]=afrund($r2['primo'],2);
				}
			}
			$kto_aar[$x]=$kto_aar[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
		}
		$kto_antal=$x;
		if ($medtag_primo && !$afd) {
			for ($x=1; $x<=$kontoantal; $x++) { # Her fanges konto med primovaerdi og ingen bevaegelser i perioden.
				if (!in_array($kontonr[$x], $ktonr)) {
					if ($primo[$x]) {
						$kto_antal++;
						$ktonr[$kto_antal]=$kontonr[$x];
						$kto_aar[$kto_antal]=$primo[$x];
					}
				}
			}
		}
		for ($x=1; $x<=$kontoantal; $x++) {
			for ($y=1; $y<=$kto_antal; $y++) {
				if (($kontotype[$x] == 'D')||($kontotype[$x] == 'S')) {
					if ($kontonr[$x]==$ktonr[$y]) {
						$aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
					}
				 } elseif ($kontotype[$x] == 'Z') {
					if (($fra_kto[$x]<=$ktonr[$y])&&($kontonr[$x]>=$ktonr[$y])&&($kontonr[$x]!=$ktonr[$y])) {
						$aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
					}
				}
			}
		}

		for ($x=1; $x<=$kontoantal; $x++) {
			if (($kontonr[$x]>=$konto_fra)&&($kontonr[$x]<=$konto_til)) {
				print "<tr>";
				$aarsum[$x]=afrund($aarsum[$x],0);
				print "<td>$kontonr[$x] </td>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				print "<td $tmp colspan=3>$kontobeskrivelse[$x] </td>";
				$row = db_fetch_array($query = db_select("select art from grupper where box1='$kontonr[$x]' and art<>'MR'",__FILE__ . " linje " . __LINE__));		
				if (($row['art']=='SM')||($row['art']=='YM')||($row['art']=='EM')) {
					print "<td>&nbsp;</td>";
					$tmp=dkdecimal($aarsum[$x]*-1,2);
				} else $tmp=dkdecimal($aarsum[$x],2);
				print "<td align=right>$tmp </td>";
			print "</tr>\n";
			$afgiftssum=$afgiftssum+$aarsum[$x];
			}
		}
		$tmp=dkdecimal($afgiftssum*-1,2);
		print "<tr><td colspan=6><hr></td></tr>";
		print "<tr><td></td><td>  Afgiftsbel&oslash;b i alt </td><td colspan=4 align=right>$tmp </td></tr>";
		print "<tr><td colspan=6><hr></td></tr>";

# Kommentering fjernes, naar Rubrik-konti er klar
#		# Tilfoejer de fem Rubrik-konti: A1, A2, B1, B2 og C
#		$row = db_fetch_array($query = db_select("select box3, box4, box5, box6, box7 from grupper where art='MR'",__FILE__ . " linje " . __LINE__));
#
#		momsrubrik($row[box3], "Rubrik A. Værdien uden moms af varekøb i andre EU-lande (EU-erhvervelser)", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box4], "Rubrik A. Værdien uden moms af ydelseskøb i andre EU-lande", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box5], "Rubrik B. Værdien af varesalg uden moms til andre EU-lande (EU-leverancer)", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box6], "Rubrik B. Værdien af visse ydelsessalg uden moms til andre EU-lande", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box7], "Rubrik C. Værdien af andre varer og ydelser, der leveres uden afgift", $regnaar, $regnstart, $regnslut);

		$x=0;
			


		print "<tr><td colspan=6><hr></td></tr>";
		print "</tbody></table>";
	} else {
		print "<BODY onLoad=\"javascript:alert('Rapportspecifikation ikke defineret (Indstillinger -> Moms)')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\">";
	}
}
?>
