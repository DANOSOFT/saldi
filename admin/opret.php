<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------/admin/opret.php-----patch 5.0.0 ----2026-09-18--------------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 
// 2013.05.14 Slutmd blev sat til 1 ved oprettelse af regnskabsår
// 2013.06.03 Apoststroffer omkring $startmd & $slutmd
// 2013.06.28 Div tilføjelser til ver 3.3.0
// 2013.10.07 Tilføjet kasse_nr numeric(15,0) til "create transaktioner";
// 2013.11.15 Tilføjet variant_id integer til "create regulering";
// 2014.01.06 Tilføjet tmp_kode text til "create brugere";
// 2014.01.07 Tilføjet dokument text til "create ordrer";
// 2014.05.08 Tilføjet password text & overtid numeric (1,0) til "create ansatte";
// 2014.06.18 Tilføjet indhold numeric (15,3) til "create varer";
// 2014.10.02 Tilføjet omvbet varchar(2) "create ordrelinjer";
// 2014.10.02 Tilføjet omvbet varchar(2) "create ordrer";
// 2014.12.05 PK - Tilføjet table "mappe og mappebilag"
// 2015.01.02 PHR Tilføjet div. see opdat_3.4.php ver 3.4.6-3.4.8
// 2015.01.22 PHR Tilføjet saet integer i ordrelinjer. se opdat_3.4.php ver 3.4.9
// 2015.02.03 PHR Tilføjet tabel kostpriser, se opdat_3.4.php ver 3.5.0
// 2015.02.03 PHR Tilføjet lager til regulering, se opdat_3.5.php ver 3.5.0
// 2015.02.03 PHR Tilføjet afd & lager til ordrerlinjer, se opdat_3.5.php ver 3.5.0
// 2015.02.03 PHR Tilføjet afd til ordrer, se opdat_3.5.php ver 3.5.0
// 2015.02.03 PHR Tilføjet div index til openpost & ordrer, se opdat_3.5.php ver 3.5.4
// 2015.08.10 PHR Tilføjet tilfravalg til ordrelinjer, se opdat_3.5.php ver 3.5.6
// 2016.01.16 PHR Tilføjet valuta & valutakurs til kontoplan, se opdat_3.6.php ver 3.6.1
// 2016.01.16 PHR Tilføjet fotonavn til varer, se opdat_3.6.php ver 3.6.2
// 2016.01.26 PK Tilføjet kontakt_tlf til ordrer, se opdat_3.6.php ver 3.6.3
// 2016.01.27 PK Tilføjet saldo til adresser, se opdat_3.6.php ver 3.6.3
// 2016.01.28 PHR Tilføjet uxtid til openpost, se opdat_3.6.php ver 3.6.3
// 2016.02.15 PHR Tilføjet tabel varetilbud, se opdat_3.6.php ver 3.6.4
// 2016.05.14 PHR Tilføjet valuta & valutakors til tabel pos_betalinger, se opdat_3.6.php ver 3.6.5
// 2017.02.16 PHR	Indextabeller oprettes kun for postgresql baser da der opstår fejl på mysql
// 2017.03.02 PK Tilføjet tabel bilag_tjekskema, se opdat_3.6.php ver 3.6.6
// 2017.05.02 PHR Tilføjet lager til tabel ordrer se opdat_3.6.php ver 3.6.8
// 2017.09.07 PHR Advokaternes Inkassoservice indsættes som default inkassovirksomhed. ver. 3.6.9  
// 2017.11.17 PHR variant_id indsat i lagerstatus,batch_køb & batch_salg samt shop_varer som shop_variant & saldi_variant. 3.7.0
// 2018.11.26 PHR Tabel settings oprettes og værdier trækkes fra importfiler/settings.txt
// Fra og med 2019 er udviklingssprog engelsk.
// 2019.01.24 PHR Added vat_price in 'ordrelinjer' & 'varer' and vat_account in 'ordrelinjer'. 
// 2019.03.13 LN Added varius tables related to pos orders (3.7.7).
// 2019.04.07 RG (Rune Grysbæk) Mysqli implementation (3.7.8)
// 2019.07.04 PHR Added 'provision' to table 'varer' (3.7.9).
// 2019.09.26 PHR Added field copied til table ordrer and corrected varius price fields to numeric (3.8.3) 
// 2019.10.01 PHR Added fiels houtType in table loen. (3.8.4)
// 2019.10.25 PHR index at ordrer.fakturadate & ordrer.felt_5 (3.8.5)
// 2019.11.08 PHR Added field grossweight & netweight index at varer (3.8.6)
// 2019.11.25 PHR changed id from serial/autoincrement til integer in table drawer, corrections, price_corrections (3.8.7)
// 2019.12.17 PHR Added field modtime at adresser,batch_kob,batch_salg & varer and modtime update trigger (3.8.8)
// 2020.02.28 PHR Added ',0"' to function call formularimport. 
// 2020.05.06 PHR Added tabel mylabel. 
// 2020.06.22 PHR Added tabel labeltemplate. 
// 2020.09.29 PHR Added tabel stocklog. 
// 2020.11.14 PHR Added field netweightunit,grossweightunit,address_id to table varer (3.9.6)
// 2020.11.14 PHR Added field netweight,grossweight,phone to table ordrer (3.9.6)
// 2020.11.24 PHR Added tabel rental & rentalperiod (3.9.7)
// 2020.11.29 PHR Added column lastprint to mylabel (3.9.8)
// 2021.01.08 PHR Added tables voucher & voucheruse (3.9.9)
// 2021.02.23 PHR Added table rentalitems and column consignmentid to table ordrer (4.0.0)
// 2021.04.01 PHR Added colunm shop_status and shop_id to table ordrer  (4.0.1)
// 2021.04.01 PHR Added colunm rental_id to table ordrelinjer  (4.0.1)
// 2021.09.21 PHR Added colunms discounttxt & comment to table ordrer  (4.0.2)
// 2021.10.07 PHR Added colunm scan_id to table ordrer  (4.0.3)
// 2021.11.15 PHR Added colunm account_group to table kontoplan to make i usable in Norway (4.0.4)
// 2021.12.15 PHR Added missing . in 'qtxt =' line 434 (4.0.4)
// 20211216 PHR Disabled UTF-8 encoding as importfiles now is UTF-8 (4.0.4)
// 20220117 PHR Addad column rt_no to table rental (4.0.5)
// 20200220 PHR	Changed column condition to state in table mylabel as name condition can't be used in MariaDB 
// 20200220 PHR	Changed column lines to linecount in table paperflow as name lines can't be used in MariaDB 
// 20220927 PHR Added column productlimit to table adresser and created to mylabel (4.0.6)
// 20221209 MHS Added several columns to table varer (4.0.7)
// 20230718 LOE Minor modification of initial data insertion  
// 20230804 LOE where $fra_formular, saldi details are set as default
// 20231021 PHR Added column 'barcode' to 'ordrelinjer','report_number' & 'settletime' to 'ordrer',
// 20231129 LOE Minor bug fixes
// 20240215 LOE Fixed some bugs + 20240219.
// 20240407 PHR	Removed function getFiscalYear() and changed $fiscalYear to '1' everywhere 
// 20240531 PHR removed changes 20230804 made by LOE 
// 20240621 LOE modified list function block causing undefined "PHP Warning:  Undefined array key 2 in" error during account creation
// 20250121 connection as first parameter in pg_*
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20250503 LOE Use conditionals to adjust for MySQL vs PostgreSQL
// 20250510 LOE Optimized to work with mysql database, long and lat now using decimal instead of int
// 20250804	PHR Corrected error in create  proforma
// 20260803 CL/SZ Require an authenticated session unconditionally, before any
//                $_POST handling (SD-615)
// 20260804 SZ Also terminate on the webservice "Session expired" include return,
//             instead of relying only on $db != $sqdb (SD-615)
// 20260818 CL/LH Corrected Stripe table boolean default definitions
// 20260908 CL/NTR Reject account names over 60 and usernames over 80 characters (is_input_too_long)
//                  before creating the account, matching login.php and varchar(60) on regnskab.regnskab
// 20260918 CDX/PHR Add ordrer.performed_by when creating a new account.
// 20260928 CL/LH Create ordrer.shop_status as varchar(20), matching api/rest_api.php and the
//                Stripe paid-invoice index predicate in includes/betweenUpdates.php.
// 20261012 CL/LH stripe_events gets the webhook lease columns (claim_token, claimed_at, attempts, event_created).
// 20261012 CL/NTR Extracted account creation into includes/opretfunc.php (create_regnskab);
//                 this page now calls it. opret() removed from here and shared with the
//                 signed sign-up endpoint api/signup_api.php.

@session_start();
$s_id=session_id();

$css="../css/standard.css";
		
include("../includes/connect.php");
include("../includes/std_func.php");
include(__DIR__ . "/../includes/opretfunc.php"); // create_regnskab() / opret_build_tenant(), shared with api/signup_api.php

$modulnr=101;

$online_result=include("../includes/online.php"); // the only auth check, must run for every request regardless of $_POST content (SD-615)
if ($online_result === 'Session expired' || $db != $sqdb) {
	print "<BODY onLoad=\"javascript:alert('".findtekst('1905|Hmm du har vist ikke noget at gøre her! Dit IP nummer, brugernavn og regnskab er registreret!', $sprog_id)."')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}

# if (!$top_bund) $top_bund="style=\"border: 1px solid rgb(0, 0, 0); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/knap_bg.gif\";";
$fra_formular=NULL;

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
	<tr><td align=\"center\" valign=\"top\" height=\"25\">
		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>
			<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=../index/admin_menu.php accesskey=L>".findtekst('2172|Luk', $sprog_id)."</a></td>
			<td width=\"80%\" $top_bund align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">".findtekst('339|Opret regnskab', $sprog_id)."</td>
			<td width=\"10%\" $top_bund align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>
		</tbody></table>
	</td></tr>
<td align = center valign = center>
<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
if ($db_type=="mysql" or $db_type=="mysqli") {

	echo '<p style="color: #1E90FF; font-weight: bold;">This installation may take up to 15 minutes. Thank you for your patience.</p>';
}

if ($_POST){
	if (isset($_POST['fra_formular'])) {
		$fra_formular = $_POST['fra_formular'];
		$kontakt=db_escape_string(trim($_POST['kontakt']));
		$firmanavn=db_escape_string(trim($_POST['firmanavn']));
		$addr1=db_escape_string(trim($_POST['addr1']));
		$addr2=db_escape_string(trim($_POST['addr2']));
		$postnr=db_escape_string(trim($_POST['postnr']));
		$bynavn=db_escape_string(trim($_POST['bynavn']));
		$tlf=db_escape_string(trim($_POST['tlf']));
		$email=db_escape_string(trim($_POST['email']));
		$cvrnr=db_escape_string(trim($_POST['cvrnr']));
	}	else {
		$kontakt=$firmanavn=$addr1=$addr2=$postnr=$bynavn=$tlf=$email=$cvrnr=NULL;

	}
	$regnskab=db_escape_string(trim($_POST['regnskab']));
	$brugernavn=db_escape_string(trim($_POST['brugernavn']));
	$passwd=db_escape_string(trim($_POST['passwd']));
	$passwd2=db_escape_string(trim($_POST['passwd2']));
	(isset($_POST['posteringer']))?$posteringer=(int)$_POST['posteringer']:$posteringer=0;
	(isset($_POST['brugerantal']))?$brugerantal=(int)$_POST['brugerantal']:$brugerantal=0;
	(isset($_POST['std_kto_plan']))?$std_kto_plan=$_POST['std_kto_plan']:$std_kto_plan=NULL;
	if (is_input_too_long(trim($_POST['regnskab']), 60)) {
		print "<BODY onLoad=\"javascript:alert('".findtekst('5150|Regnskabsnavnet må højst være 60 tegn', $sprog_id)."')\">";
		forside($regnskab,$brugernavn);
		exit;
	}
	if (is_input_too_long(trim($_POST['brugernavn']))) {
		print "<BODY onLoad=\"javascript:alert('".findtekst('5149|Brugernavnet må højst være 80 tegn', $sprog_id)."')\">";
		forside($regnskab,$brugernavn);
		exit;
	}
	if ((($revisorregnskab && $passwd) || !$revisorregnskab)  && $passwd!=$passwd2 ) {
		print "<BODY onLoad=\"javascript:alert('Adgangskoder er ikke ens')\">";
		forside($regnskab,$brugernavn);
		exit;
	}
/*
	if ($db_encode=="UTF8") {
		$firmanavn=mb_convert_encoding($firmanavn, 'UTF-8', 'ISO-8859-1');
		$addr1=mb_convert_encoding($addr1, 'UTF-8', 'ISO-8859-1');
		$addr2=mb_convert_encoding($addr2, 'UTF-8', 'ISO-8859-1');
		$bynavn=mb_convert_encoding($bynavn, 'UTF-8', 'ISO-8859-1');
		$kontakt=mb_convert_encoding($kontakt, 'UTF-8', 'ISO-8859-1');
		$regnskab=mb_convert_encoding($regnskab, 'UTF-8', 'ISO-8859-1');
		$brugernavn=mb_convert_encoding($brugernavn, 'UTF-8', 'ISO-8859-1');
		$passwd=mb_convert_encoding($passwd, 'UTF-8', 'ISO-8859-1');
	}
*/


	$x=0;
#	$tmp=db_escape_string($regnskab);
	$query = db_select("select * from regnskab where regnskab='$regnskab'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "<BODY onLoad=\"javascript:alert('".findtekst('2682|Regnskab', $sprog_id)." $regnskab ".findtekst('2683|findes allerede', $sprog_id)."')\">";
		forside($regnskab,$brugernavn);
		exit;
	} elseif ((!$revisorregnskab && (!$brugernavn||!$passwd)) || (!$regnskab))  {
		print "<BODY onLoad=\"javascript:alert('".findtekst('2684|Alle felter SKAL udfyldes', $sprog_id)."')\">";
		forside($regnskab,$brugernavn);
		exit();
	} else {
		$companyFields = array();
		if ($fra_formular) {
			$companyFields = array(
				'kontakt'   => $kontakt,
				'firmanavn' => $firmanavn,
				'addr1'     => $addr1,
				'addr2'     => $addr2,
				'postnr'    => $postnr,
				'bynavn'    => $bynavn,
				'tlf'       => $tlf,
				'email'     => $email,
				'cvrnr'     => $cvrnr,
			);
		}
		$res = create_regnskab($regnskab, $brugernavn, $passwd, $email, $posteringer, $brugerantal, $std_kto_plan, $companyFields);
		if ($res['ok']) {
			print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab er oprettet og aktiveret')\">";
			if ($fra_formular) {
				$regnskab_enc = urlencode($regnskab);
				$brugernavn_enc = urlencode($brugernavn);
				print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/index.php?regnskab=$regnskab_enc&navn=$brugernavn_enc&tlf=$tlf\">";
			} else {
				print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/admin_menu.php\">";
			}
		} else {
			print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab kunne ikke oprettes')\">";
			forside($regnskab, $brugernavn);
		}
		exit();
	}
} else {
	if (!isset($regnskab)) $regnskab='';
	if (!isset($brugernavn)) $brugernavn='';
	forside($regnskab,$brugernavn);
print "</tbody></table";
}

function forside($regnskab,$brugernavn) {
	global $charset;
	global $sprog_id;

	print "<form name=debitorkort action=opret.php method=post>";
	print "<tr><td>".findtekst('2685|Navn på regnskab', $sprog_id)."</td><td><br></td><td><input type=text size=25 maxlength=60 name=regnskab value='$regnskab'></td></tr>";
	print "<tr><td>".findtekst('2686|Administrators navn', $sprog_id)."</td><td><br></td><td><input type=text size=25 maxlength=80 name=brugernavn value='$brugernavn'></td></tr>";
	print "<tr><td>".findtekst('2687|Administrators adgangskode', $sprog_id)."</td><td><br></td><td><input type=password size=25 name=passwd></td></tr>";
	print "<tr><td>".findtekst('2688|Gentag adgangskode', $sprog_id)."</td><td><br></td><td><input type=password size=25 name=passwd2></td></tr>";
	print "<tr><td>".findtekst('2689|Opret standardkontoplan', $sprog_id)."</td><td><br></td><td><input type=checkbox name=std_kto_plan checked></td></tr>";
	print "<tr><td><br></td></tr><tr><td><br></td></tr><tr><td><br></td></tr>";
	print "<tr><td colspan=3 align = center><input type=submit accesskey=\"g\" value=\"".findtekst('471|Gem/opdatér', $sprog_id)."\" name=\"submit\"></td></tr>";
	print "</form>";
}

?>
</tbody></table>
</body></html>
