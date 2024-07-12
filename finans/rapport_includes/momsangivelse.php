<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/momsangivelse.php --patch 4.0.8 ----2023-08-31----
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20210107 PHR Corrected error in 'deferred financial year'.
// 20210301 PHR php 7x issues
// 20220119 PHR	Made i possible to use SUM accounts inside the this repport
// 20230110 MSC - Implementing new design
// 20230829 MSC - Copy pasted new design into code


function momsangivelse($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til, $simulering, $lagerbev)
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

	$medtag_primo = if_isset($_GET['medtag_primo']);

	if ($row = db_fetch_array(db_select("select firmanavn from adresser where art='S'", __FILE__ . " linje " . __LINE__)))
		$firmanavn = $row['firmanavn'];
	if (($afd) && ($row = db_fetch_array(db_select("select beskrivelse from grupper where art='AFD' and kodenr='$afd'", __FILE__ . " linje " . __LINE__))))
		$afd_navn = $row['beskrivelse'];
	if (!isset($afgiftssum))
		$afgiftssum = NULL;

	$regnaar = $regnaar * 1; #fordi den er i tekstformat og skal vaere numerisk

	#	list ($x, $maaned_fra) = explode(" ", $maaned_fra);
#	list ($x, $maaned_til) = explode(" ", $maaned_til);

	$maaned_fra = trim($maaned_fra);
	$maaned_til = trim($maaned_til);
	$konto_fra = trim($konto_fra);
	$konto_til = trim($konto_til);

	$mf = $maaned_fra;
	$mt = $maaned_til;

	for ($x = 1; $x <= 12; $x++) {
		if ($maaned_fra == $md[$x])
			$maaned_fra = $x;
		if ($maaned_til == $md[$x])
			$maaned_til = $x;
		if (strlen($maaned_fra) == 1)
			$maaned_fra = "0" . $maaned_fra;
		if (strlen($maaned_til) == 1)
			$maaned_til = "0" . $maaned_til;
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'", __FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	#	$regnaar=$row[kodenr];
	$startmaaned = $row['box1'] * 1;
	$startaar = $row['box2'] * 1;
	$slutmaaned = $row['box3'] * 1;
	$slutaar = $row['box4'] * 1;
	$slutdato = 31;

	if ($aar_fra < $aar_til) { #20210107
		if ($maaned_til > $slutmaaned)
			$aar_til = $aar_fra;
		elseif ($maaned_fra < $startmaaned)
			$aar_fra = $aar_til;
	}
	$regnaarstart = $startaar . "-" . $startmaaned . "-" . '01';

	if ($maaned_fra)
		$startmaaned = $maaned_fra;
	if ($maaned_til)
		$slutmaaned = $maaned_til;
	if ($dato_fra)
		$startdato = $dato_fra;
	if ($dato_til)
		$slutdato = $dato_til;

	#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}

	while (!checkdate($startmaaned, $startdato, $startaar)) {
		$startdato = $startdato - 1;
		if ($startdato < 28)
			break 1;
	}

	while (!checkdate($slutmaaned, $slutdato, $slutaar)) {
		$slutdato = $slutdato - 1;
		if ($slutdato < 28)
			break 1;
	}
	if (strlen($startdato) < 2)
		$startdato = "0" . $startdato;


	$regnstart = $aar_fra . "-" . $startmaaned . "-" . $startdato;
	$regnslut = $aar_til . "-" . $slutmaaned . "-" . $slutdato;
	#	$regnstart = $startaar. "-" . $startmaaned . "-" . $startdato;
#	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

	#	print "  <a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Luk</a><br><br>";

	if ($menu == 'T') {
		$title = "Rapport • Momsangivelse";

		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">";
		print "<div class=\"headerbtnLft headLink\"><a href=rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst(30, $sprog_id) . "</a></div>";
		print "<div class=\"headerTxt\">$title</div>";
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
		print "</div>";
		print "<div class='content-noside'>";
		print "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
#	} elseif ($menu == 'S') {
#		include("../includes/sidemenu.php");
	} else {
		print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\" align='center'> ";
		print "<tr><td colspan=\"6\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\">Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Rapport - " . ucfirst($rapportart) . "</td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
	}
	print "<tr><td colspan=\"4\"><big><big>" . ucfirst($rapportart) . "</span></big></big></td>";
	print "<td colspan=2 align=right><table style=\"text-align: left; width: 400px;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
	if ($afd) {
		print "<td>Afdeling</span></td>";
		print "<td>$afd: $afd_navn</span></td></tr>";
	}
	print "<td>Regnskabs&aring;r</span></td>";
	print "<td>$regnaar.</span></td></tr>";
	print "<tr><td>Periode</span></td>";
	print "<td>Fra </td><td>" . dkdato($regnstart) . "</td></tr><tr><td></td><td>Til &nbsp;&nbsp;</td><td>" . dkdato($regnslut) . "</span></td></tr>";
	if ($ansat_fra) {
		if (!$ansat_til || $ansat_fra == $ansat_til)
			print "<tr><td>Medarbejder</span></td><td>$ansatte</span></td></tr>";
		else
			print "<tr><td>Medarbejdere</span></td><td>$ansatte</span></td></tr>";
	}
	if ($afd)
		print "<tr><td>Afdeling</span></td><td>$afd_navn</span></td></tr>";
	if ($projekt_fra) {
		print "<tr><td>Projekt</td>";
		if (!strstr($projekt_fra, "?")) {
			print "<td>$prj_navn_fra ";
			if ($projekt_til && $projekt_fra != $projekt_til)
				print "- $prj_navn_til ";
		} else
			print "<td>$projekt_fra ";
		print "</td></tr>";
	}
	print "</tbody></table></td></tr>";

	print "<tr><td colspan=4><big><b>$firmanavn</b></big></td></tr>";
	print "<tr><td colspan=6><hr></td></tr>";

	$dim = '';
	if ($afd || $ansat_fra || $projekt_fra) {
		if ($afd)
			$dim = "and afd = $afd ";
		if ($ansat_fra)
			$dim = $dim . "and ansat = '$ansat_fra' ";
		$projekt_fra = str2low($projekt_fra);
		$projekt_til = str2low($projekt_til);
		if ($projekt_fra && $projekt_til && $projekt_fra != $projekt_til)
			$dim = $dim . " and lower(projekt) >= '$projekt_fra' and lower(projekt) <= '$projekt_til' ";
		elseif ($projekt_fra) {
			$tmp = str_replace("?", "_", $projekt_fra);
			if (substr($tmp, -1) == '_') {
				while (substr($tmp, -1) == '_')
					$tmp = substr($tmp, 0, strlen($tmp) - 1);
				$tmp = str2low($tmp) . "%";
			}
			$dim = $dim . "and lower(projekt) LIKE '$tmp' ";
		}
	}

	$row = db_fetch_array($query = db_select("select box1, box2 from grupper where art='MR'", __FILE__ . " linje " . __LINE__));
	if (($row['box1']) && ($row['box2'])) {
		$konto_fra = $row['box1'];
		$konto_til = $row['box2'];

		$x = 0;
		$qtxt = "select * from kontoplan where regnskabsaar='$regnaar' and kontonr>=$konto_fra and kontonr<=$konto_til order by kontonr";
		$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$x++;
			$kontonr[$x] = $row['kontonr'] * 1;
			$kontobeskrivelse[$x] = $row['beskrivelse'];
			$kontotype[$x] = $row['kontotype'];
			$fra_kto[$x] = $row['fra_kto'];
			$primo[$x] = $row['primo'];
			$aarsum[$x] = 0;
		}


		$kontoantal = $x;
		$kto_aar[$x] = 0;
		$kto_periode[$x] = 0;
		$ktonr = array();
		$x = 0;
		$query = db_select("select * from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr>='$konto_fra' and kontonr<='$konto_til' $dim order by kontonr", __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (!in_array($row['kontonr'], $ktonr)) { # Her fanges konto med bevaegelser i perioden.
				$x++;
				$ktonr[$x] = $row['kontonr'] * 1;
				$kto_aar[$x] = 0;
				if (($medtag_primo && !$afd) && ($r2 = db_fetch_array(db_select("select primo from kontoplan where regnskabsaar='$regnaar' and kontonr=$ktonr[$x] and kontotype='S'", __FILE__ . " linje " . __LINE__)))) {
					$kto_aar[$x] = afrund($r2['primo'], 2);
				}
			}
			$kto_aar[$x] = $kto_aar[$x] + afrund($row['debet'], 2) - afrund($row['kredit'], 2);
		}
		$kto_antal = $x;
		if ($medtag_primo && !$afd) {
			for ($x = 1; $x <= $kontoantal; $x++) { # Her fanges konto med primovaerdi og ingen bevaegelser i perioden.
				if (!in_array($kontonr[$x], $ktonr)) {
					if ($primo[$x]) {
						$kto_antal++;
						$ktonr[$kto_antal] = $kontonr[$x];
						$kto_aar[$kto_antal] = $primo[$x];
					}
				}
			}
		}
		for ($x = 1; $x <= $kontoantal; $x++) {
			for ($y = 1; $y <= $kto_antal; $y++) {
				if (($kontotype[$x] == 'D') || ($kontotype[$x] == 'S')) {
					if ($kontonr[$x] == $ktonr[$y]) {
						$aarsum[$x] = $aarsum[$x] + $kto_aar[$y];
					}
				} elseif ($kontotype[$x] == 'Z') {
					if (($fra_kto[$x] <= $ktonr[$y]) && ($kontonr[$x] >= $ktonr[$y]) && ($kontonr[$x] != $ktonr[$y])) {
						$aarsum[$x] = $aarsum[$x] + $kto_aar[$y];
					}
				}
			}
		}

		for ($x = 1; $x <= $kontoantal; $x++) {
			if (($kontonr[$x] >= $konto_fra) && ($kontonr[$x] <= $konto_til)) {
				print "<tr>";
				$aarsum[$x] = afrund($aarsum[$x], 0);
				print "<td>$kontonr[$x] </td>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				print "<td $tmp colspan=3>$kontobeskrivelse[$x] </td>";
				$qtxt = "select art from grupper where box1='$kontonr[$x]' and art<>'MR'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				if (isset($r['art']) && ($r['art'] == 'SM' || $r['art'] == 'YM' || $r['art'] == 'EM')) {
					print "<td>&nbsp;</td>";
					$tmp = dkdecimal($aarsum[$x] * -1, 2);
				} else
					$tmp = dkdecimal($aarsum[$x], 2);
				print "<td align=right>$tmp </td>";
				print "</tr>\n";
				$afgiftssum = $afgiftssum + $aarsum[$x];
			}
		}
		$tmp = dkdecimal($afgiftssum * -1, 2);
		print "<tr><td colspan=6><hr></td></tr>";
		print "<tr><td></td><td>  Afgiftsbel&oslash;b i alt </td><td colspan=4 align=right>$tmp </td></tr>";
		print "<tr><td colspan=6><hr style=\"border: 1px solid #9a9a9a;\"></td></tr>";

		# Kommentering fjernes, naar Rubrik-konti er klar
#		# Tilfoejer de fem Rubrik-konti: A1, A2, B1, B2 og C
#		$row = db_fetch_array($query = db_select("select box3, box4, box5, box6, box7 from grupper where art='MR'",__FILE__ . " linje " . __LINE__));
#
#		momsrubrik($row[box3], "Rubrik A. Værdien uden moms af varekøb i andre EU-lande (EU-erhvervelser)", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box4], "Rubrik A. Værdien uden moms af ydelseskøb i andre EU-lande", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box5], "Rubrik B. Værdien af varesalg uden moms til andre EU-lande (EU-leverancer)", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box6], "Rubrik B. Værdien af visse ydelsessalg uden moms til andre EU-lande", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box7], "Rubrik C. Værdien af andre varer og ydelser, der leveres uden afgift", $regnaar, $regnstart, $regnslut);

		$x = 0;

		/**************************************************************************************************** */
		// Automatisk momsindberetning til skat

		// This is a response from 'kalenderHent'
		$kalenderResponse = <<<XML
		<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
		<ns4:VirksomhedKalenderHent_O xmlns="http://rep.oio.dk/skat.dk/basis/kontekst/xml/schemas/2006/09/01/" xmlns:ns2="http://rep.oio.dk/skat.dk/motor/class/virksomhed/xml/schemas/20080401/" xmlns:ns4="urn:oio:skat:nemvirksomhed:ws:1.0.0" xmlns:ns3="urn:oio:skat:nemvirksomhed:1.0.0">
			<HovedOplysningerSvar>
				<TransaktionIdentifikator>f26fe3e6-771b-4b3d-aeeb-0b500a9bc709</TransaktionIdentifikator>
				<ServiceIdentifikator>NTSE</ServiceIdentifikator>
				<TransaktionTid>2024-01-23T10:34:15.651+01:00</TransaktionTid>
				<SvarStruktur/>
			</HovedOplysningerSvar>
			<ns2:VirksomhedSENummerIdentifikator>41250313</ns2:VirksomhedSENummerIdentifikator>
			<ns3:PligtKode>63</ns3:PligtKode>
			<ns4:AngivelseFrekvensListe>
				<ns4:AngivelseFrekvens>
					<ns3:AngivelseFrekvensForholdGyldigFraDate>2014-01-02</ns3:AngivelseFrekvensForholdGyldigFraDate>
					<ns3:AngivelseFrekvensTypeCode>8</ns3:AngivelseFrekvensTypeCode>
					<ns4:AngivelseBetalingFristDatoListe>
						<ns4:AngivelseBetalingFristDato>
							<ns3:AngivelseFristKalenderFristDato>2018-03-01</ns3:AngivelseFristKalenderFristDato>
							<ns3:AngivelseFristKalenderBetalingDato>2018-03-01</ns3:AngivelseFristKalenderBetalingDato>
						</ns4:AngivelseBetalingFristDato>
						<ns4:AngivelseBetalingFristDato>
							<ns3:AngivelseFristKalenderFristDato>2018-06-01</ns3:AngivelseFristKalenderFristDato>
							<ns3:AngivelseFristKalenderBetalingDato>2018-06-01</ns3:AngivelseFristKalenderBetalingDato>
						</ns4:AngivelseBetalingFristDato>
						<ns4:AngivelseBetalingFristDato>
							<ns3:AngivelseFristKalenderFristDato>2018-09-03</ns3:AngivelseFristKalenderFristDato>
							<ns3:AngivelseFristKalenderBetalingDato>2018-09-03</ns3:AngivelseFristKalenderBetalingDato>
						</ns4:AngivelseBetalingFristDato>
						<ns4:AngivelseBetalingFristDato>
							<ns3:AngivelseFristKalenderFristDato>2018-12-03</ns3:AngivelseFristKalenderFristDato>
							<ns3:AngivelseFristKalenderBetalingDato>2018-12-03</ns3:AngivelseFristKalenderBetalingDato>
						</ns4:AngivelseBetalingFristDato>
					</ns4:AngivelseBetalingFristDatoListe>
				</ns4:AngivelseFrekvens>
			</ns4:AngivelseFrekvensListe>
		</ns4:VirksomhedKalenderHent_O>
		XML;

		$dom = new DOMDocument;

		$dom->loadXML($kalenderResponse);

		$angivelseFrekvensTypeCode = $dom->getElementsByTagName('AngivelseFrekvensTypeCode')->item(0);
		$kalenderHent = $dom->getElementsByTagName('AngivelseFristKalenderFristDato');

		$frekvensTypeCode = array(
			0 => "Ingen",
			1 => "Straks",
			2 => "Daglig",
			5 => "Ugentlig",
			6 => "14 dage",
			7 => "Månedlig",
			8 => "Kvartal",
			9 => "Halvårlig",
			10 => "Årlig",
			16 => "Variabel",
			17 => "Lejlighedsvis"
		);

		$errorCodes = array(
			4801 => "RSU er ikke delegeret af virksomheden",
			4802 => "Ikke åben periode",
			4803 => "Periode ikke afsluttet",
			4804 => "Periode er mere end 3 år gammel",
			4810 => "Foreløbig statement ikke godkendt",
			4813 => "Der findes ingen foreløbig momsindberetning",
			4816 => "Værdien i <Angivelsestype> findes ikke. Skal være \"Moms\".",
			4811 => "Ingen kvittering, den foreløbige momsangivelse er afvist",
			4812 => "Kvittering findes ikke",
			4817 => "Søgedato start er efter søgedatoslut"
		);

		/**
		 * Create a readable date from standart date format '2018-02-01' to '1. februar 2018'
		 * @param mixed $newDate Date to convert
		 */
		function newDate($newDate)
		{
			global $md; // global variable with danish monthnames
			$date = DateTime::createFromFormat('Y-m-d', $newDate);
			$mn = $date->format('n');
			$day = $date->format('j');
			$year = $date->format('Y');
			return $day . ". " . $md[$mn] . " " . $year;
		}

		// If RSU (Regnskabsudbyder) is NOT delegated to handle VAT returns for klient. Text and link to skat
		print "<tr><td colspan=\"6\" style=\"font-size: large;font-weight: lighter;padding: 15px 0;\">Momsindberetning</td></tr>";
		if (true) {
			print "<tr><td colspan=\"6\" style=\"padding-bottom:5px;\"><p>Hvis du vil indberettes moms direkte til skat skal du give SALDI tilladelse til at indsende dine momsdata. Det gøres ved at tilmelde SALDI <a href=\"https://skat.dk/erhverv/moms/moms-saadan-goer-du/saadan-indberetter-du-moms\" target=\"_blank\" style=\"color:blue;\">Her</a>.</p></td></tr>";
			print "<tr><td colspan=\"6\" style=\"padding-bottom:5px;\"><p>Under punktet <mark class=\"mark\"><b>Indberet moms direkte fra dit regnskabsprogram</b></mark> kan du finde en udførlig vejledning i hvordan du giver SALDI lov til at logge på TastSelv Erhverv på dine vegne.</p></td></tr>";
		}
		// If RSU is delegated to handle VAT returns for klient. Text and button to get VAT return kalender.
		if (true) {
			print "<tr><td colspan=\"6\">Her kan du hente en kalender som viser de datoer som der senest skal indberettes moms.</td></tr>";
			print "<tr><td colspan=\"6\" style=\"padding-bottom:5px;\"><button>Hent kalender</button></td></tr>";
		}
		// If VAT return kalender is present. Text and button for return VAT.
		if (true) {
			print "<tr><td colspan=\"6\" style=\"padding-bottom:5px;\"><p>Kalender for momsindberetning hvor frekvensen er: " . $frekvensTypeCode[$angivelseFrekvensTypeCode->nodeValue] . "</p></td></tr>";
			print "<tr><td colspan=\"6\"><table class=\"kalender\">";
			print "<tr><th>Skal være indberettet og betalt senest</th></tr>";
			foreach ($kalenderHent as $kalender) {
				print "<tr><td>" . newDate($kalender->nodeValue) . "</td></tr>";
			}
			print "</table></td></tr>";
			print "<tr><td colspan=\"6\" style=\"padding-bottom:5px;\"><button>Indberet moms</button></td></tr>";
		}




		/**************************************************************************************************** */

		print "<tr><td colspan=6><hr></td></tr>";
		print "</tbody></table>";
		if ($menu == 'T') {
			include_once '../includes/topmenu/footer.php';
		} else {
			include_once '../includes/oldDesign/footer.php';
		}
	} else {
		print "<BODY onLoad=\"javascript:alert('Rapportspecifikation ikke defineret (Indstillinger -> Moms)')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\">";
	}
}
?>
