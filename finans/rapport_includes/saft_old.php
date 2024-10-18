<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/saft.php --- patch 4.0.8 --- 2023-09-07 ---
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
//

function saft($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart) // , $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til, $simulering, $lagerbev
{
	global $db;
	global $bruger_id;
	global $md, $menu;
	global $top_bund;


	$AuditFileDateCreated = date("Y-m-d");
	$AuditFileDateTimeCreated = date("Y-m-d\\TH.i.s");

	$AuditFileVersion = "1.0";
	$SoftwareCompanyName = "Saldi.dk ApS";
	$SoftwareID = "Saldi";
	$AddressType = "StreetAddress";
	$TaxType = "VAT"; // Skal der stå VAT, moms eller...???
	$TaxAccountingBasis = "Regnskab";
	echo $db;

	/**
	 * Function that convert countryname to taxauthority
	 */
	function TaxAuthorityName($NameOfCountry)
	{
		$TaxAuthorityName = '';
		switch ($NameOfCountry) {
			case "Denmark":
				$TaxAuthorityName = "Skat";
				break;
			case "Norway":
				$TaxAuthorityName = "Skatteetaten";
				break;
			case "Switzerland":
				$TaxAuthorityName = "FTA/ESTV";
				break;
			default:
				$TaxAuthorityName = "Skat";
		}
		return $TaxAuthorityName;
	}

	/**
	 * Function that convert countryname to ISO 4217 currencycode
	 */
	function defaultCurrency($NameOfCountry)
	{
		$currencyCode = '';
		switch ($NameOfCountry) {
			case "Denmark":
				$currencyCode = "DKK";
				break;
			case "Norway":
				$currencyCode = "NOK";
				break;
			case "Switzerland":
				$currencyCode = "CHF";
				break;
			default:
				$currencyCode = "DKK";
		}
		return $currencyCode;
	}

	/**
	 * Function that will take one parameter
	 * and convert countryname to countrycode
	 */
	function countrycode($NameOfCountry)
	{
		$countryCode = '';
		switch ($NameOfCountry) {
			case "Denmark":
				$countryCode = "DK";
				break;
			case "Norway":
				$countryCode = "NO";
				break;
			case "Switzerland":
				$countryCode = "CH";
				break;
			default:
				$countryCode = "DK";
		}
		return $countryCode;
	}

	/**
	 * Function that take to parameters ($cipcode and $NameOfCountry)
	 * and convert to ISO 3166-2 codes for denmark
	 */
	function regionNumber($cipcode, $NameOfCountry)
	{
		$region = '';
		if ($NameOfCountry != 'Denmark') {
			$region = 'NA';
		} else {
			$HovedstadenRange1 = range(1, 2635);
			$HovedstadenRange2 = range(2650, 2665);
			$HovedstadenRange3 = range(2700, 3670);
			$HovedstadenRange4 = range(3700, 3790);
			$HovedstadenRange5 = range(4050, 4050);
			$SjaellandRange1 = range(2640, 2644);
			$SjaellandRange2 = range(2670, 2690);
			$SjaellandRange3 = range(4000, 4040);
			$SjaellandRange4 = range(4060, 4990);
			$SyddanmarkRange1 = range(5000, 6870);
			$SyddanmarkRange2 = range(7000, 7120);
			$SyddanmarkRange3 = range(7173, 7260);
			$SyddanmarkRange4 = range(7300, 7323);
			$MidtjyllandRange1 = range(6880, 6990);
			$MidtjyllandRange2 = range(7130, 7171);
			$MidtjyllandRange3 = range(7270, 7280);
			$MidtjyllandRange4 = range(7330, 7680);
			$MidtjyllandRange5 = range(7790, 7884);
			$MidtjyllandRange6 = range(8000, 8990);
			$NordjyllandRange1 = range(7700, 7770);
			$NordjyllandRange2 = range(7900, 7990);
			$NordjyllandRange3 = range(9000, 9990);

			switch (true) {
				case (in_array($cipcode, $HovedstadenRange1) || in_array($cipcode, $HovedstadenRange2) || in_array($cipcode, $HovedstadenRange3) || in_array($cipcode, $HovedstadenRange4) || in_array($cipcode, $HovedstadenRange5)):
					$region = 'DK-84';
					break;
				case (in_array($cipcode, $SjaellandRange1) || in_array($cipcode, $SjaellandRange2) || in_array($cipcode, $SjaellandRange3) || in_array($cipcode, $SjaellandRange4)):
					$region = 'DK-85';
					break;
				case (in_array($cipcode, $SyddanmarkRange1) || in_array($cipcode, $SyddanmarkRange2) || in_array($cipcode, $SyddanmarkRange3) || in_array($cipcode, $SyddanmarkRange4)):
					$region = 'DK-83';
					break;
				case (in_array($cipcode, $MidtjyllandRange1) || in_array($cipcode, $MidtjyllandRange2) || in_array($cipcode, $MidtjyllandRange3) || in_array($cipcode, $MidtjyllandRange4) || in_array($cipcode, $MidtjyllandRange5) || in_array($cipcode, $MidtjyllandRange6)):
					$region = 'DK-82';
					break;
				case (in_array($cipcode, $NordjyllandRange1) || in_array($cipcode, $NordjyllandRange2) || in_array($cipcode, $NordjyllandRange3)):
					$region = 'DK-81';
					break;
				default:
					$region = 'NA';
			}
		}
		return $region;
	}

	/**
	 * Function that split address into street and number
	 * return an array:
	 * Full address [0]
	 * Address name [1]
	 * Address number [2]
	 */
	function splitAddress($FullAddress)
	{
		if (preg_match('/([^\d]+)\s?(.+)/i', $FullAddress, $result)) {
			if (preg_match('/^\pL+$/u', $result[2])) {
				$result[1] = $FullAddress;
				$result[2] = '';
				return $result;
			}
			return $result;
		}
		return $FullAddress;
	}

	$qtxt = "select box1 from grupper where art = 'VE'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$SoftwareVersion = $r['box1'];
	}

	$qtxt = "select * from adresser where art='S'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$firmanavn = $r['firmanavn'];
		$Address = $r['addr1'];
		$StreetName = splitAddress($Address)[1];
		$StreetNumber = splitAddress($Address)[2];

		$AdditionalAddressDetail = $r['addr2'];
		$City = $r['bynavn'];
		$PostalCode = $r['postnr'];
		$CountryName = $r['land'];
		$Region = regionNumber($PostalCode, $CountryName);
		$Country = countrycode($CountryName);
		$DefaultCurrencyCode = defaultCurrency($CountryName);
		$Contact = $r['kontakt'];
		$PhoneNumber = $r['tlf'];
		$FaxNumber = $r['fax'];
		$Email = $r['email'];
		$WebSite = $r['web'];
		$BankAccountName = $r['bank_navn'];
		$BankRegNumber = $r['bank_reg'];
		$BankAccountNumber = $r['bank_konto'];
		$TaxRegistrationNumber = $r['cvrnr'];
		$TaxAuthority = TaxAuthorityName($CountryName);
	}

	$qtxt = "select var_value from settings where var_name='globalId'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$RegistrationNumber = $r['var_value'];
	}

	$qtxt = "select * from ansatte where id='1'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$ContactName = $r['navn'];
		$ContactInitials = $r['initialer'];
	}

	/************************************************************ */

	$maaned_fra = trim($maaned_fra);
	$maaned_til = trim($maaned_til);

	$mf = $maaned_fra;
	$mt = $maaned_til;

	for ($x = 1; $x <= 12; $x++) {
		if ($maaned_fra == $md[$x]) {
			$maaned_fra = $x;
		}
		if ($maaned_til == $md[$x]) {
			$maaned_til = $x;
		}
		if (strlen($maaned_fra) == 1) {
			$maaned_fra = "0" . $maaned_fra;
		}
		if (strlen($maaned_til) == 1) {
			$maaned_til = "0" . $maaned_til;
		}
		if (strlen($dato_fra) == 1) {
			$dato_fra = "0" . $dato_fra;
		}
		if (strlen($dato_til) == 1) {
			$dato_til = "0" . $dato_til;
		}
	}

	$qtxt = "select * from grupper where kodenr='$regnaar' and art='RA'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$startmaaned = $r['box1'] * 1; //1
		$startaar = $r['box2'] * 1; //2021
		$slutmaaned = $r['box3'] * 1; //12
		$slutaar = $r['box4'] * 1; //2021
		$slutdato = 31;
	}

	if ($aar_fra < $aar_til) { #20210107
		if ($maaned_til > $slutmaaned)
			$aar_til = $aar_fra;
		elseif ($maaned_fra < $startmaaned)
			$aar_fra = $aar_til;
	}
	// $regnaarstart = $startaar . "-" . $startmaaned . "-" . '01';


	// if ($rapportart=='budget') {
	// 	$startmd=$maaned_fra-$startmaaned+1;
	// 	$slutmd=$maaned_til-$startmaaned+1;
	// 	if ($slutaar>$startaar && $maaned_fra>$maaned_til) $slutmd=$slutmd+12;
	// }

	if (strlen($startmaaned) == 1)
		$startmaaned = "0" . $startmaaned;
	if (strlen($slutmaaned) == 1)
		$slutmaaned = "0" . $slutmaaned;

	// $regnAarStart = $startaar . "-" . $startmaaned . "-" . '01';
	// $lastYearBegin = $startaar - 1 . "-" . $startmaaned . "-" . '01';
	// if ($rapportart=='lastYear')$regnAarStart= $startaar-1 . "-" . $startmaaned . "-" . '01'; 


	if ($maaned_fra)
		$startmaaned = $maaned_fra;
	if ($maaned_til)
		$slutmaaned = $maaned_til;
	if ($dato_fra)
		$startdato = $dato_fra;
	if ($dato_til)
		$slutdato = $dato_til;

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
	#cho "1008 $projekt_fra $prj_navn_fra - $projekt_til $prj_navn_til<br>"; 

	// $regnstart = $aar_fra . "-" . $startmaaned . "-" . $startdato;
	// $regnslut = $aar_til . "-" . $slutmaaned . "-" . $slutdato;
	// $lastYearBegin = $aar_fra - 1 . "-" . $startmaaned . "-" . $startdato; #20190506
	// $lastYearEnd = $aar_til - 1 . "-" . $slutmaaned . "-" . $slutdato;

	// ($startaar >= '2015')?$aut_lager='on':$aut_lager=NULL;

	// if ($aut_lager && $lagerbev) {
	// 	$x=0;
	// 	$varekob=array();
	// 	$q=db_select("select box1,box2,box3 from grupper where art = 'VG' and box8 = 'on'",__FILE__ . " linje " . __LINE__);
	// 	while ($r=db_fetch_array($q)) {
	// 		if ($r['box1'] && $r['box2'] && !in_array($r['box3'],$varekob)) {
	// 			$varelager_i[$x]=$r['box1'];
	// 			$varelager_u[$x]=$r['box2'];
	// 			$varekob[$x]=$r['box3'];
	// 			$x++;
	// 		}
	// 	}
	// 	$q=db_select("select box1,box2,box11 from grupper where art = 'VG' and box8 = 'on' and box11 != ''",__FILE__ . " linje " . __LINE__);
	// 	while ($r=db_fetch_array($q)) {
	// 		if ($r['box1'] && $r['box2'] && !in_array($r['box11'],$varekob)) {
	// 			$varelager_i[$x]=$r['box1'];
	// 			$varelager_u[$x]=$r['box2'];
	// 			$varekob[$x]=$r['box11'];
	// 			$x++;
	// 		}
	// 	}
	// 	$q=db_select("select box1,box2,box13 from grupper where art = 'VG' and box8 = 'on' and box13 != ''",__FILE__ . " linje " . __LINE__);
	// 	while ($r=db_fetch_array($q)) {
	// 		if ($r['box1'] && $r['box2'] && !in_array($r['box13'],$varekob)) {
	// 			$varelager_i[$x]=$r['box1'];
	// 			$varelager_u[$x]=$r['box2'];
	// 			$varekob[$x]=$r['box13'];
	// 			$x++;
	// 		}
	// 	}
	// }
	// $x=0;
	// $valdate=array();
	// $valkode=array();
	// $q=db_select("select * from valuta order by gruppe,valdate desc",__FILE__ . " linje " . __LINE__);
	// while ($r=db_fetch_array($q)) {
	// 	$y=$x-1;
	// 	if (!isset($valkode[$x])) $valkode[$x]=NULL;
	// 	if ((!$x) || $r['gruppe']!=$valkode[$x] || $valdate[$x]>=$regnstart) {
	// 		$valkode[$x]=$r['gruppe'];
	// 		$valkurs[$x]=$r['kurs'];
	// 		$valdate[$x]=$r['valdate'];
	// 		$x++;
	// 	}
	// }
	// $csvfile="../temp/$db/regnskab.csv";
	// $csv=fopen($csvfile,"w");

	if ($rapportart == "saft")
		$newTitle = "SAF-T";
	if ($menu == 'T') {
		$title = "Rapport • $newTitle";

		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">";
		print "<div class=\"headerbtnLft headLink\"><a href=rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst(30, $sprog_id) . "</a></div>"; // &ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev
		print "<div class=\"headerTxt\">$title</div>";
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
		print "</div>";
		print "<div class='content-noside'>";
		print "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
	} elseif ($menu == 'S') {
		include("../includes/sidemenu.php");
	} else {
		print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\" align='center'> ";
		print "<tr><td height=\"8\" colspan=\"2\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til\">Luk</a></td>"; // &ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev
		print "<td width=\"80%\" $top_bund> Rapport - $newTitle </td>";
		print "<td width=\"10%\" $top_bund></td>"; // <a href='$csvfile'>csv</a>
		print "</tbody></table>"; #B slut
		print "</td></tr>";
	}
	// if ($rapportart=='resultat') {
	// 	($simulering)?$tmp="Simuleret resultat":$tmp="Resultat";
	// } elseif ($rapportart=='budget') {
	// 	($simulering)?$tmp="Simuleret resultat/budget":$tmp="Resultat/budget";
	// } elseif ($rapportart=='lastYear') {
	// 	($simulering)?$tmp="Simuleret resultat/sidste år":$tmp="Resultat/sidste år";
	// } elseif ($rapportart=='balance') {
	// 	($simulering)?$tmp="Simuleret Balance":$tmp="Balance";
	// } else {
	// 	($simulering)?$tmp="Simuleret Regnskab":$tmp="Regnskab";
	// }

	print "<tr><td width=\"70%\"><big><big>$newTitle</big></big></td>";

	print "<td align=right><table style=\"text-align: left; width: 100%;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
	// if ($afd || $afd == '0') {
	// 	print "<td>Afdeling</td>";
	// 	print "<td>$afd: $afd_navn</td></tr>";
	// }
	print "<td>Regnskabs&aring;r</td>";
	print "<td>$regnaar.</td></tr>";
	print "<tr><td>Periode</td>";
	if ($startdato < 10)
		$startdato = "0" . $startdato * 1;
	print "<td>Fra " . $startdato . ". $mf $aar_fra<br />Til " . $slutdato . ". $mt $aar_til</td></tr>";
	// if ($ansat_fra) {
	// 	if (!$ansat_til || $ansat_fra == $ansat_til)
	// 		print "<tr><td>Medarbejder</td><td>$ansatte</td></tr>";
	// 	else
	// 		print "<tr><td>Medarbejdere</td><td>$ansatte</td></tr>";
	// }
	// if ($afd || $afd == '0')
	// 	print "<tr><td>Afdeling</span></td><td>$afd_navn</span></td></tr>";
	// if ($projekt_fra) {
	// 	print "<td>Projekt:</td><td>";
	// 	#		print "<tr><td>Projekt $prj_navn_fra</td>";
	// 	if (!strstr($projekt_fra, "?")) {
	// 		if ($projekt_til && $projekt_fra != $projekt_til)
	// 			print "Fra: $projekt_fra, $prj_navn_fra<br>Til : $projekt_til, $prj_navn_til";
	// 		else
	// 			print "$projekt_fra, $prj_navn_fra";
	// 	} else
	// 		print "$projekt_fra, $prj_navn_fra";
	// 	print "</td></tr>";
	// }
	print "</tbody></table></td></tr>";
	print "<tr><td colspan=\"2\"><big><b>$firmanavn</b></big></td>";
	// fwrite($csv, "\"\";\"$firmanavn\";\"Perioden\";");
	// print "<td align=right> Perioden </td>";
	// if ($rapportart=='budget') {
	// 	print "<td align=right> Budget </td><td align=right> Afvigelse </td></tr>";
	// 	fwrite($csv, "\"Budget\";\"Afvigelse\"\n");
	// } elseif ($rapportart=='lastYear') {
	// 	print "<td align=right> Sidste år </td></tr>";
	// 	fwrite($csv, "\"Sidste år\"\n");
	// }else {
	// 	print "<td align=right> &Aring;r til dato </td></tr>";
	// 	fwrite($csv, "\"År til dato\"\n");
	// }
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	/******************************************************** */
	// Lav knap til at gennerere SAF-T rapport her
	print "<tr><td><p>Her kan du oprette en SAF-T rapport</p></td></tr>";
	print "<form method='post' action='../finans/saftCreator.php'>";
	print "<tr><td><button type='submit'>Opret</button></td></tr>";
	print "</form>";
	print "<tr><td colspan=\"2\"><hr></td></tr>";

	print "<tr><td>";
	echo "<br>";
	// echo "$AuditFileDateTimeCreated";
	// echo "-Header- <br>";
	// echo "AuditFileVersion: $AuditFileVersion<br>"; // skal vist bare være 1.0
	// echo "AuditFileCountry: $Country<br>";
	// echo "AuditFileRegion: $Region<br>";
	// echo "AuditFileDateCreated: $AuditFileDateCreated<br>";
	// echo "SoftwareCompanyName: $SoftwareCompanyName<br>"; // Find firmanavn (Saldi ApS) i db
	// echo "SoftwareID: $SoftwareID<br>"; // Find navn på software (Saldi) i db
	// echo "SoftwareVersion: $SoftwareVersion<br>";
	// echo "--Company-- <br>";
	// echo "RegistrationNumber: $RegistrationNumber<br>";
	// echo "Name: $firmanavn<br>";
	// echo "---Address--- <br>";
	// echo "StreetName: $StreetName<br>";
	// echo "Number: $StreetNumber<br>";
	// echo "AdditionalAddressDetail: $AdditionalAddressDetail<br>";
	// echo "City: $City<br>";
	// echo "PostalCode: $PostalCode<br>";
	// echo "Region: $Region<br>";
	// echo "Country: $Country<br>";
	// echo "AddressType: $AddressType<br>";
	// echo "---Address--- <br>";
	// echo "---Contact--- <br>";
	// echo "----ContactPerson---- <br>";
	// echo "Initials: $ContactInitials<br>";
	// echo "BirthName: $ContactName<br>";
	// echo "----ContactPerson---- <br>";
	// echo "Telephone: $PhoneNumber<br>";
	// echo "Fax: $FaxNumber<br>";
	// echo "Email: $Email<br>";
	// echo "Website: $WebSite<br>";
	// echo "MobilePhone: $PhoneNumber<br>";
	// echo "---Contact--- <br>";
	// echo "---TaxRegistration--- <br>";
	// echo "TaxRegistrationNumber: $TaxRegistrationNumber<br>";
	// echo "TaxType: 	$TaxType<br>";
	// echo "TaxNumber: $TaxRegistrationNumber<br>";
	// echo "TaxAuthority: $TaxAuthority<br>";
	// // echo "Country: $Country<br>"; //???
	// //echo "TaxVerificationDate: NA<br>"; // Information skal hentes i cvr register??
	// echo "---TaxRegistration--- <br>";
	// echo "---BankAccount--- <br>";
	// echo "BankAccountNumber: $BankAccountNumber<br>";
	// echo "BankAccountName: $BankAccountName<br>";
	// echo "CurrencyCode: $DefaultCurrencyCode<br>";
	// echo "AccountID: $BankRegNumber<br>";
	// echo "---BankAccount--- <br>";
	// echo "--Company-- <br>";
	// echo "DefaultCurrencyCode: $DefaultCurrencyCode<br>";
	// echo "--SelectionCriteria-- <br>";
	// echo "PeriodStart: $startmaaned<br>";
	// echo "PeriodStartYear: $aar_fra<br>";
	// echo "PeriodEnd: $slutmaaned<br>";
	// echo "PeriodEndYear: $aar_til<br>";
	// echo "--SelectionCriteria-- <br>";
	// echo "TaxAccountingBasis: $TaxAccountingBasis<br>";
	// echo "TaxEntity: $firmanavn<br>";
	// echo "UserID: $bruger_id<br>"; // Den bruger som opretter SAF-T rapport
	// echo "-Header- <br>";
	print "</td></tr>";




	// fwrite($csv, "\"\";\"-------------------\"\n");
	// $x = 0;
	// $query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr", __FILE__ . " linje " . __LINE__);
	// while ($row = db_fetch_array($query)) {
	// 	$x++;
	// 	$kontonr[$x] = $row['kontonr'] * 1;
	// 	$ktonr[$x] = $kontonr[$x];
	// 	$kontobeskrivelse[$x] = $row['beskrivelse'];
	// 	$kontotype[$x] = $row['kontotype'];
	// 	$fra_kto[$x] = $row['fra_kto'] * 1;
	// 	$primo[$x] = afrund($row['primo'], 2);
	// 	$saldo[$x] = $row['saldo'] * 1;
	// 	$lukket[$x] = $row['lukket']; #20120927
	// 	$aarsum[$x] = 0;
	// 	$kto_aar[$x] = 0;
	// 	$kto_periode[$x] = 0;
	// 	$vis_kto[$x] = 1;
	// 	$kontovaluta[$x] = $row['valuta'];
	// 	$kontokurs[$x] = $row['valutakurs'];
	// 	if (!$dim && $kontotype[$x] == "S")
	// 		$primo[$x] = afrund($row['primo'], 2);
	// 	else
	// 		$primo[$x] = 0;
	// 	if ($primo[$x] && $kontovaluta[$x]) {
	// 		for ($y = 0; $y <= count($valkode); $y++) {
	// 			if ($valkode[$y] == $kontovaluta[$x] && $valdate[$y] <= $slutdato) {
	// 				$kontokurs[$x] = $valkurs[$y];
	// 				break 1;
	// 			}
	// 		}
	// 	} else
	// 		$primokurs[$x] = 100;

	// }
	// $kto_antal = $kontoantal = $x;

	// $x = 0;

	// for ($x = 1; $x <= $kontoantal; $x++) {
	// 	$qtxt = "select * from transaktioner where transdate>='$regnAarStart' and transdate<='$regnslut' $dim and kontonr=$ktonr[$x]";
	// 	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	// 		$vis_kto[$x] = 1;
	// 	}
	// 	$qtxt = "select * from transaktioner where transdate>='$regnAarStart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim";
	// 	if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	// 		$vis_kto[$x] = 1;
	// 	}
	// 	if ($simulering) {
	// 		$qtxt = "select * from simulering where transdate>='$regnAarStart' and transdate<='$regnslut' $dim and kontonr=$ktonr[$x]";
	// 		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	// 			$vis_kto[$x] = 1;
	// 		}
	// 		$qtxt = "select * from simulering where transdate>='$regnAarStart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim";
	// 		if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	// 			$vis_kto[$x] = 1;
	// 		}
	// 	}
	// 	if ($aut_lager && $lagerbev) {
	// 		if (in_array($kontonr[$x], $varekob))
	// 			$vis_kto[$x] = 1;
	// 		if (in_array($kontonr[$x], $varelager_i))
	// 			$vis_kto[$x] = 1;
	// 		if (in_array($kontonr[$x], $varelager_u))
	// 			$vis_kto[$x] = 1;
	// 	}
	// 	if ($kontotype[$x] == 'R')
	// 		$vis_kto[$x] = 1;
	// }

	// if ($rapportart == 'budget') {
	// 	for ($x = 1; $x <= $kontoantal; $x++) {
	// 		if (!$lukket[$x]) { #20120927	
	// 			$qtxt = "select sum(amount) as amount from budget where regnaar='$regnaar' and kontonr='$ktonr[$x]' ";
	// 			$qtxt .= "and md >= '$startmd' and md <= '$slutmd'";
	// 			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	// 				$vis_kto[$x] = 1;
	// 			}
	// 		}
	// 	}
	// }
	//////////////////
	// for ($x = 1; $x <= $kontoantal; $x++) {
	// 	$kto_aar[$x] = 0;
	// 	$kto_periode[$x] = 0; # Herunder tilfoejes primovaerdi.
	// 	$qtxt = "select primo from kontoplan where regnskabsaar='$regnaar' and kontonr=$ktonr[$x] and kontotype='S'";
	// 	if ((($rapportart == 'balance' || $rapportart == 'regnskab') && !$afd && $afd != '0' && !$projekt_fra && !$ansat_fra) && ($r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))) {
	// 		$kto_aar[$x] = afrund($r2['primo'], 2);
	// 	}
	// 	$qtxt = "select * from transaktioner where transdate>='$regnAarStart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim";
	// 	$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	// 	while ($row = db_fetch_array($query)) {
	// 		if ($row['transdate'] >= $regnstart) {
	// 			$kto_periode[$x] = $kto_periode[$x] + afrund($row['debet'], 2) - afrund($row['kredit'], 2);

	// 		}
	// 		if ($rapportart != 'budget') {
	// 			$kto_aar[$x] = $kto_aar[$x] + afrund($row['debet'], 2) - afrund($row['kredit'], 2); #


	// 		}
	// 	}

	// 	if ($simulering) {
	// 		$query = db_select("select * from simulering where transdate>='$regnAarStart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim", __FILE__ . " linje " . __LINE__);
	// 		while ($row = db_fetch_array($query)) {
	// 			if ($row['transdate'] >= $regnstart)
	// 				$kto_periode[$x] = $kto_periode[$x] + afrund($row['debet'], 2) - afrund($row['kredit'], 2);
	// 			if ($rapportart != 'budget') {
	// 				$kto_aar[$x] = $kto_aar[$x] + afrund($row['debet'], 2) - afrund($row['kredit'], 2);
	// 			}
	// 		}
	// 	}
	// 	if ($aut_lager && $lagerbev) {
	// 		if (in_array($ktonr[$x], $varekob)) {
	// 			$l_a_primo[$x] = find_lagervaerdi($ktonr[$x], $regnAarStart, 'start');
	// 			$l_a_sum[$x] = find_lagervaerdi($ktonr[$x], $regnslut, 'slut');
	// 			$l_p_primo[$x] = find_lagervaerdi($ktonr[$x], $regnstart, 'start');
	// 			# Varekøb (debet) debiteres lager primo og krediteres lager saldo. Dvs tallet mindskes hvis lager øges 
	// 			$kto_aar[$x] += $l_a_primo[$x];
	// 			$kto_aar[$x] -= $l_a_sum[$x];
	// 			$kto_periode[$x] += $l_p_primo[$x];
	// 			$kto_periode[$x] -= $l_a_sum[$x];
	// 		}
	// 		if (in_array($ktonr[$x], $varelager_i) || in_array($ktonr[$x], $varelager_u)) {
	// 			$l_a_primo[$x] = find_lagervaerdi($ktonr[$x], $regnAarStart, 'start');
	// 			$l_a_sum[$x] = find_lagervaerdi($ktonr[$x], $regnslut, 'slut');
	// 			$l_p_primo[$x] = find_lagervaerdi($ktonr[$x], $regnstart, 'start');
	// 			$kto_aar[$x] -= $l_a_primo[$x]; #20150125 + næste 3 linjer
	// 			$kto_aar[$x] += $l_a_sum[$x];
	// 			$kto_periode[$x] -= $l_p_primo[$x];
	// 			$kto_periode[$x] += $l_a_sum[$x];
	// 		}
	// 	}
	// }
	#return alert("$dim");
	////////////////////
// 	if ($rapportart == 'lastYear') {
// 		for ($x = 1; $x <= $kontoantal; $x++) {
// 			$lastYearYear[$x] = 0;
// 			$lastYearPeriod[$x] = 0; # Herunder tilfoejes primovaerdi.
// 			$query = db_select("select * from transaktioner where transdate>='$lastYearBegin' and transdate<='$lastYearEnd' and kontonr='$ktonr[$x]' $dim", __FILE__ . " linje " . __LINE__);
// 			while ($row = db_fetch_array($query)) {
// 				if ($row['transdate'] >= $lastYearBegin) {
// 					$lastYearYear[$x] += afrund($row['debet'], 2) - afrund($row['kredit'], 2);
// 				}
// 			}
// 			if ($aut_lager && $lagerbev) {
// 				if (in_array($ktonr[$x], $varekob)) {
// 					#				$lastYearPrimo[$x]=find_lagervaerdi($ktonr[$x],$regnAarStart,'start');
// 					$lastYearSum[$x] = find_lagervaerdi($ktonr[$x], $regnslut, 'slut');
// 					#					$lastYearPeriodPrimo[$x]=find_lagervaerdi($ktonr[$x],$regnstart,'start');
// 					# Varekøb (debet) debiteres lager primo og krediteres lager saldo. Dvs tallet mindskes hvis lager øges 
// #					$lastYearYear[$x]+=$lastYearPrimo[$x];				
// 					$lastYearYear[$x] -= $lastYearSum[$x];
// 					#					$lastYearPeriod[$x]+=$lastYearPeriodPrimo[$x];
// 					$lastYearPeriod[$x] -= $lastYearSum[$x];
// 				}
// 			}
// 		}
// 	} elseif ($rapportart == 'budget') {
// 		for ($x = 1; $x <= $kontoantal; $x++) {
// 			if ($vis_kto[$x] && $kontotype[$x] == 'D') { #20120927 + 20181031
// 				$qtxt = "select sum(amount) as amount from budget where ";
// 				$qtxt .= "regnaar='$regnaar' and kontonr='$ktonr[$x]' and md >= '$startmd' and md <= '$slutmd'";
// 				$r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
// 				$kto_aar[$x] = afrund($r2['amount'], 2);
// 			}
// 		}
// 		$kto_antal = $kontoantal;
// 	}

	// 	for ($x = 1; $x <= $kontoantal; $x++) { # Her fanges konti med primovaerdi og ingen bevaegelser i perioden.
// 		if (!in_array($kontonr[$x], $ktonr) && !$afd && $afd != '0' && !$projekt_fra && !$ansat_fra) {
// 			if ($primo[$x]) {
// 				$kto_antal++;
// 				$ktonr[$kto_antal] = $kontonr[$x];
// 				$kto_aar[$kto_antal] = $primo[$x];
// 				#				if (in_array($ktonr[$kto_antal],$varekob)) {
// #			$l_a_primo[$kto_antal]=find_lagervaerdi($ktonr[$kto_antal],$varekob,$regnstart);
// #			$l_a_sum[$kto_antal]=find_lagervaerdi($ktonr[$kto_antal],$varekob,$regnslut);
// #				$l_p_primo[$x]=find_lagervaerdi($kontonr[$x],$varekob,$regnAarStart);
// #			$kto_aar[$kto_antal]-=$l_a_primo[$kto_antal];
// #			$kto_aar[$kto_antal]+=$l_a_sum[$kto_antal];
// #				$periodesum[$x]-=$l_p_primo[$x];
// #				$periodesum[$x]+=$l_a_sum[$x]; 
// #		}
// 			}
// 		}
// 	}
	// for ($x = 1; $x <= $kontoantal; $x++) { # Her fanges konti med lagerrelation & primovaerdi og ingen bevaegelser i perioden.
	// 	if (in_array($kontonr[$x], $varelager_i) || in_array($kontonr[$x], $varelager_u)) {
	// 		if (in_array($kontonr[$x], $ktonr)) {
	// 			$kto_antal++;
	// 			$ktonr[$kto_antal] = $kontonr[$x];
	// 			$kto_aar[$kto_antal] = 0;
	// 		}
	// 	}
	// }

	// for ($x = 1; $x <= $kontoantal; $x++) {
	// 	if ($kontotype[$x] == 'R') {
	// 		for ($y = 1; $y <= $kontoantal; $y++) { #20140825
	// 			if ($ktonr[$y] == $fra_kto[$x]) {
	// 				$aarsum[$x] = $aarsum[$y];
	// 				$periodesum[$x] = $periodesum[$y];
	// 				$kto_aar[$x] = $aarsum[$x]; #20140909 rettet fra = $kto_aar[$y] 
	// 				$kto_periode[$x] = $periodesum[$x]; #20140909 rettet fra = $kto_periode[$y]
	// 			}
	// 		}
	// 	}
	// 	if (!isset($periodesum[$x]))
	// 		$periodesum[$x] = 0;
	// 	for ($y = 1; $y <= $kto_antal; $y++) {
	// 		if (!isset($kto_periode[$y]))
	// 			$kto_periode[$y] = 0;
	// 		if (($kontotype[$x] == 'D') || ($kontotype[$x] == 'S')) {
	// 			if ($kontonr[$x] == $ktonr[$y]) {
	// 				$aarsum[$x] = $aarsum[$x] + $kto_aar[$y];
	// 				$periodesum[$x] = $periodesum[$x] + $kto_periode[$y];
	// 			}
	// 		} elseif ($kontotype[$x] == 'Z') {
	// 			if (($fra_kto[$x] <= $ktonr[$y]) && ($kontonr[$x] >= $ktonr[$y]) && ($kontonr[$x] != $ktonr[$y])) {
	// 				$aarsum[$x] = $aarsum[$x] + $kto_aar[$y];
	// 				$periodesum[$x] = $periodesum[$x] + $kto_periode[$y];
	// 			}
	// 		}
	// 	}
	// }
	// if ($lastYear) {
	// 	for ($x = 1; $x <= $kontoantal; $x++) {
	// 		if (!isset($lastYearPeriodSum[$x]))
	// 			$lastYearPeriodSum[$x] = 0;
	// 		if (!isset($lastYearYearSum[$x]))
	// 			$lastYearYearSum[$x] = 0;
	// 		for ($y = 1; $y <= $kto_antal; $y++) {
	// 			if (!isset($lastYearPeriod[$y]))
	// 				$lastYearPeriod[$y] = 0;
	// 			if (($kontotype[$x] == 'D') || ($kontotype[$x] == 'S')) {
	// 				if ($kontonr[$x] == $ktonr[$y]) {
	// 					$lastYearYearSum[$x] += $lastYear[$y];
	// 					$lastYearPeriodSum[$x] += $lastYearPeriod[$y];
	// 				}
	// 			} elseif ($kontotype[$x] == 'Z') {
	// 				if (($fra_kto[$x] <= $ktonr[$y]) && ($kontonr[$x] >= $ktonr[$y]) && ($kontonr[$x] != $ktonr[$y])) {
	// 					$lastYearYearSum[$x] += $lastYearYear[$y];
	// 					$lastYearPeriodSum[$x] += $lastYearPeriod[$y];
	// 				}
	// 			}
	// 			#cho "$kontonr[$x] lYPS $lastYearYearSum[$x] $lastYearPeriodSum[$x]<br>";						

	// 		}
	// 	}
	// }



	// for ($x = 1; $x <= $kontoantal; $x++) {
	// 	if ($kontonr[$x] >= $konto_fra && $kontonr[$x] <= $konto_til && ($aarsum[$x] || $periodesum[$x] || $kontotype[$x] == 'H' || $kontotype[$x] == 'R' || $show0 || ($kontotype[$x] == 'Z' && $x == $kontoantal))) { #20190220
	// 		if ($kontotype[$x] == 'H') {
	// 			$linjebg = $bgcolor;
	// 			print "<tr><td><br></td></tr>";
	// 			fwrite($csv, "\"\"\n");
	// 			$tmp = kontobemaerkning($kontobeskrivelse[$x]);
	// 			print "<tr bgcolor=\"$bgcolor5\"><td $tmp colspan=\"$cols6\"><b>$kontobeskrivelse[$x]</b></td></tr>";
	// 			fwrite($csv, "\" \";\"$kontobeskrivelse[$x]\"\n");
	// 			print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
	// 			fwrite($csv, "\" \";\"---------------------\"\n");
	// 		} elseif ($kontotype[$x] == 'Z') {
	// 			print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
	// 			fwrite($csv, "\" \";\"---------------------\"\n");
	// 			$tmp = kontobemaerkning($kontobeskrivelse[$x]);
	// 			if (!$budget && !$lastYear) {
	// 				print "<td><br></td>";
	// 				#					fwrite($csv, "\"\";");
	// 			}
	// 			print "<td $tmp colspan=\"$cols3\"><b> $kontobeskrivelse[$x] </b></td>";
	// 			fwrite($csv, "\"\";\"$kontobeskrivelse[$x]\";");
	// 			if ($kontovaluta[$x]) {
	// 				for ($y = 0; $y <= count($valkode); $y++) {
	// 					if ($valkode[$y] == $kontovaluta[$x] && $valdate[$y] <= $slutdate) {
	// 						$transkurs[$x] = $valkurs[$y];
	// 						break 1;
	// 					}
	// 				}
	// 				$tmp = $periodesum[$x] * 100 / $kontokurs[$y];
	// 				$title = "DKK " . dkdecimal($periodesum[$x], 2) . " Kurs: " . dkdecimal($kontokurs[$x], 2);
	// 			} else {
	// 				$tmp = $periodesum[$x];
	// 				$title = NULL;
	// 			}
	// 			#cho $aarsum[$x]."<br>";
	// 			print "<td align=\"right\" title=\"$title\"><b>" . dkdecimal($tmp, 2) . "</b></td>";
	// 			fwrite($csv, "\"" . dkdecimal($tmp, 2) . "\";");
	// 			if ($kontovaluta[$x]) {
	// 				$tmp = $aarsum[$x] * 100 / $kontokurs[$x];
	// 				$title = "DKK " . dkdecimal($aarsum[$x], 2) . " Kurs: " . dkdecimal($kontokurs[$x], 2);
	// 			} else {
	// 				if ($lastYear)
	// 					$tmp = $lastYearYearSum[$x];
	// 				else
	// 					$tmp = $aarsum[$x];
	// 				$title = NULL;
	// 			}
	// 			print "<td align=\"right\" title=\"$title\"><b>" . dkdecimal($tmp, 2) . "</b></td>";
	// 			fwrite($csv, "\"" . dkdecimal($tmp, 2) . "\";");
	// 			if ($rapportart == 'budget') {
	// 				if ($kontovaluta[$x]) {
	// 					$tmp = $aarsum[$x] * 100 / $kontokurs[$x];
	// 					$title = "DKK " . dkdecimal($aarsum[$x], 2) . " Kurs: " . dkdecimal($kontokurs[$x], 2);
	// 				} else {
	// 					if ($aarsum[$x])
	// 						$tmp = ($periodesum[$x] - $aarsum[$x]) * 100 / $aarsum[$x];
	// 					else
	// 						$tmp = "--";
	// 					$title = NULL;
	// 				}
	// 				print "<td align=\"right\" title=\"$title\"><b>" . dkdecimal($tmp, 2) . "%</b></td>";
	// 				fwrite($csv, "\"" . dkdecimal($tmp, 2) . "\"");
	// 			}
	// 			print "</tr>";
	// 			fwrite($csv, "\n");
	// 			print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
	// 			fwrite($csv, "\"\";\"--------------------\"\n");
	// 		} else {
	// 			if ($kontovaluta[$x]) {
	// 				$qtxt = "select box1 from grupper where art = 'VK' and kodenr = '$kontovaluta[$x]'";
	// 				$valname[$x] = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))[0];
	// 			} else
	// 				$valname[$x] = 'DKK';
	// 			if (in_array($kontonr[$x], $varekob)) {
	// 				$title = "Heraf på lager: " . dkdecimal($l_a_sum[$x] - $l_p_primo[$x], 2);
	// 			} else
	// 				$title = '';
	// 			($linjebg != $bgcolor5) ? $linjebg = $bgcolor5 : $linjebg = $bgcolor;
	// 			print "<tr bgcolor=\"$linjebg\"><td>$kontonr[$x]</td>";
	// 			fwrite($csv, "\"$kontonr[$x]\";");
	// 			$tmp = kontobemaerkning($kontobeskrivelse[$x]);
	// 			print "<td $tmp colspan=\"3\">$kontobeskrivelse[$x]</td>";
	// 			fwrite($csv, "\"$kontobeskrivelse[$x]\";");
	// 			if ($kontovaluta[$x]) {
	// 				$tmp = $periodesum[$x]; #*100/$kontokurs[$x];
	// 				$title = "$valname[$x] " . dkdecimal($periodesum[$x] / $kontokurs[$x] * 100, 2) . " Kurs: " . dkdecimal($kontokurs[$x], 2);
	// 			} else {
	// 				$tmp = $periodesum[$x];
	// 				$title = NULL;
	// 			}
	// 			print "<td align=\"right\" title=\"$title\">" . dkdecimal($tmp, 2) . "</td>";
	// 			fwrite($csv, "\"" . dkdecimal($tmp, 2) . "\";");
	// 			if ($kontovaluta[$x]) {
	// 				$tmp = $aarsum[$x] * 100 / $kontokurs[$x];
	// 				$title = "$valname[$x] " . dkdecimal($aarsum[$x] / $kontokurs[$x] * 100, 2) . " Kurs: " . dkdecimal($kontokurs[$x], 2);
	// 			} else {
	// 				$tmp = $aarsum[$x];
	// 				$title = NULL;
	// 			}
	// 			if ($lastYear)
	// 				$tmp = dkdecimal($lastYearYear[$x], 2); #aar til dato
	// 			else
	// 				$tmp = dkdecimal($aarsum[$x], 2);
	// 			print "<td align=\"right\" title=\"$title\">" . $tmp . "</td>"; #20210317
	// 			fwrite($csv, "\"" . $tmp . "\";");
	// 			if ($rapportart == 'budget') {
	// 				if ($kontovaluta[$x] && $aarsum[$x]) {
	// 					$tmp = (($periodesum[$x] - $aarsum[$x]) * 100 / $aarsum[$x]) * 100 / $kontokurs[$x];
	// 					$title = "$valname[$x]  " . dkdecimal($periodesum[$x], 2) . " Kurs: " . dkdecimal($kontokurs[$x], 2);
	// 				} elseif ($aarsum[$x]) {
	// 					$tmp = ($periodesum[$x] - $aarsum[$x]) * 100 / $aarsum[$x];
	// 					$title = NULL;
	// 				} else
	// 					$tmp = "--";
	// 				print "<td align=\"right\">" . dkdecimal($tmp, 2) . "%</td>"; #afvigelse fra budget
	// 				fwrite($csv, "\"" . dkdecimal($tmp, 2) . "\"");
	// 			}
	// 			print "</tr>";
	// 			fwrite($csv, "\n");
	// 		}
	// 	}
	// }
	// fclose($csv);
	print "<tr><td colspan=2><hr></td></tr>";
	print "</tbody></table>";
	function redirect()
	{
		echo '<script>location.replace("../finans/rapport_includes/saft.php");</script>';
		echo "saft.php";
	}
	/**
	 * function that redirect back to index after create SAF-T rapport
	 */

	if ($menu == 'T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}
	print "<!--Function regnskab slut-->\n";
}

?>