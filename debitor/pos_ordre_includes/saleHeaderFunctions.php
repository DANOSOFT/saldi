<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/saleHeaderFunctions.php --- ver 5.0.0 ---- 2026-10-21 ----
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261022 CL/MJ SD-758 The copied phone number is cut to ordrer.phone's 50 characters.
//                  adresser.tlf holds 60, and postgresql refuses the whole update on an overlong
//                  value, which left the customer unattached to the sale over a phone number.
//
// 20261021 CL/MJ SD-758 [S12] WP12: opret_posordre() and opdater_konto() moved here from
//                  debitor/pos_ordre.php, which is the sale header in one place rather than two
//                  function bodies in the middle of a 4000-line page. Three things are new with the
//                  move (legacy core edit 9, POS part): the sale is created with the terminal's
//                  department and brand instead of picking the department up later from the receipt
//                  print, and attaching a customer copies their phone number onto the order.

# The brand list is read through pos_sale_brands(), which is also what the settings page writes, so
# the storage layout of that row is defined in one place only.
include_once(__DIR__ . "/../../systemdata/diverseIncludes/posSaleSettings.php");

/**
 * The department and brand a terminal sells under.
 *
 * Both are per-terminal lists in grupper, tab separated and indexed by the terminal number less
 * one, which is how every other POS setting is stored (see systemdata/diverseIncludes/posOptions.php):
 *
 *  - the department from art='POS' kodenr='1' box3, the list the receipt print used to read
 *  - the brand through pos_sale_brands(), from the row the settings page owns
 *
 * An unconfigured terminal gives back empty values, and the sale is then created exactly as it was
 * before SD-758 - no department, and a brand the rest of Saldi reads as "Dansk". A terminal number
 * that is not a terminal is answered without asking the database anything.
 *
 * @param int $kasse   Terminal number, 1-based.
 * @param int $regnaar Fiscal year the settings belong to.
 * @return array{afd: int, brand: string}
 */
function pos_terminal_sale_defaults($kasse, $regnaar) {
	$kasse = (int) $kasse;
	$regnaar = (int) $regnaar;
	$defaults = array('afd' => 0, 'brand' => '');
	if ($kasse < 1) {
		return $defaults;
	}
	$index = $kasse - 1;
	$qtxt = "select box3 from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$afdelinger = explode(chr(9), (string) $r['box3']);
		$defaults['afd'] = (int) trim(ifset($afdelinger, $index, ''));
	}
	$brands = pos_sale_brands($regnaar);
	$defaults['brand'] = trim(ifset($brands, $index, ''));
	return $defaults;
}

function opret_posordre($konto_id, $kasse) {
	$posNavigationQuery = nav_popup_query($_GET, $_POST);
	global $baseCurrency, $bordnr, $bruger_id, $brugernavn;
	global $db;
	global $firmanavn;
	global $kontonr;
	global $momssats;
	global $notes, $regnaar;
	global $varenr_ny;

	if (!$kasse)
		$kasse = find_kasse(0);
	if (!$kasse)
		$kasse = 1;

	if (file_exists("../temp/$db/$kasse.tid") && file_get_contents("../temp/$db/$kasse.tid") >= date("U")) { #20181024
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?{$posNavigationQuery}\">\n";
		exit;
	} else
		file_put_contents("../temp/$db/$kasse.tid", date("U") + 1);

	hent_shop_ordrer('', '');

	$r = db_fetch_array(db_select("select box4 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	$x = $kasse - 1;
	$tmp = explode(chr(9), $r['box4']);
	$terminal_ip = trim($tmp[$x]);
/*
	if (isset($_COOKIE['salditerm']) && $_COOKIE['salditerm'])
		$terminal_ip = $_COOKIE['salditerm'];
	if ($terminal_ip == 'box' || $terminal_ip == 'saldibox') {
		$filnavn = "http://saldi.dk/kasse/" . $_SERVER['REMOTE_ADDR'] . ".ip";
		if ($fp = fopen($filnavn, 'r')) {
			$terminal_ip = trim(fgets($fp));
			fclose($fp);
		}
	}
*/
	if ($terminal_ip) {
		setcookie("salditerm", $terminal_ip, time() + 3600, '/');
	}
	if ($kasse && !$_GET['bordnr'] && !isset($_GET['flyt_til'])) {
		$qtxt = "select box13 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r['box13']) {
			$tmparray = explode(chr(9), $r['box13']);
			$bordnr = $tmparray[$kasse - 1];
		}
	}

	if (!$bordnr && $bordnr != '0')
		$bordnr = if_isset($_GET['bordnr']);
	if (!$bordnr && $bordnr != '0')
		$bordnr = if_isset($_GET['flyt_til']);
	if (!$varenr_ny && ($bordnr || $bordnr == '0')) {
		$qtxt = "select id from ordrer where art='PO' and nr = '$bordnr' and felt_5='$kasse' and status < '3'";
		if ($r = db_fetch_array($q = db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?{$posNavigationQuery}id=$r[id]\">\n";
		}
	}
		$ordrenr = get_next_order_number('PO');
	$ordredate = date("Y-m-d");
	$tidspkt = date("U");
	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	#	$id=$r['id'];
#	$kasseantal=$r['box1']*1;
	$moms = explode(chr(9), $r['box7']);
	$x = $kasse - 1;
	if ($moms[$x]) {
		$r = db_fetch_array(db_select("select * from grupper where art = 'SM' and kodenr = '$moms[$x]'", __FILE__ . " linje " . __LINE__));
		$momssats = $r['box2'];
	} else
		$momssats = '0';
	if (!is_numeric($bordnr))
		$bordnr = 0;
	if (!is_numeric($kontonr))
		$kontonr = 0;
	// 20261021 CL/MJ SD-758 The sale carries the terminal's department and brand from the moment it
	// is created. The department used to arrive only when the receipt was printed, which meant an
	// unprinted sale had none and a reprint could overwrite one; the brand decides which Shopify
	// shop the sale belongs to and is stored in sprog, the order's form variant. Both are empty on
	// an unconfigured terminal, and the sale is then written exactly as it was before.
	$salgsdefault = pos_terminal_sale_defaults($kasse, $regnaar);
	$afd = $salgsdefault['afd'];
	$brand = db_escape_string($salgsdefault['brand']);
	# 20141210 Tilføjet felt_5
	$qtxt = "insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,";
	$qtxt .= "betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,";
	$qtxt .= "ordredate,momssats,hvem,tidspkt,ref,valuta,sprog,kontakt,pbs,status,nr,felt_5,afd)";
	$qtxt .= "	values ";
	$qtxt .= "('$ordrenr','0','$kontonr','$firmanavn','','','','','','0','Kontant','','','','','','$notes','PO',";
	$qtxt .= "'$ordredate','$momssats','$brugernavn','$tidspkt','$brugernavn','$baseCurrency','$brand','','','0','$bordnr','$kasse','$afd')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "select id from ordrer where hvem='$brugernavn' and tidspkt='$tidspkt' order by id desc";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$id = $r['id'];
	$qtxt = "insert into pos_events (ev_type,ev_time,cash_register_id,employee_id,order_id,file,line) ";
	$qtxt .= "values ";
	$qtxt .= "('13003','" . date('U') . "','$kasse','$bruger_id','$id','" . __FILE__ . "','" . __LINE__ . "')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	return ($id);
} # endfunc opret_posordre()

function opdater_konto($konto_id, $kontonr, $id, $o_art = 'PO') {
	#Opdaterer kontoinformation på ordren
	global $baseCurrency, $db, $kasse;
	global $kundeordnr;

	if (!$id || $id == 0)
		$id = opret_posordre(0, $kasse);
	$konto_id *= 1;
	$kontonr *= 1;
	$r = db_fetch_array(db_select("select status from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
	$status = $r['status'];
	if ($status < 3) {
		# In supplier lookup mode (KO) only accept creditor accounts
		if ($konto_id) {
			$art_filter = ($o_art == 'KO') ? " and art = 'K'" : "";
			$r = db_fetch_array(db_select("select * from adresser where id = '$konto_id'$art_filter", __FILE__ . " linje " . __LINE__));
		} else {
			$adr_art = ($o_art == 'KO') ? 'K' : 'D';
			$r = db_fetch_array(db_select("select * from adresser where kontonr = '$kontonr' and art = '$adr_art'", __FILE__ . " linje " . __LINE__));
		}
		if (!$r || !$r['id']) {
			return ($id); # unknown or wrong-art account: leave the order untouched
		}
		$konto_id = $r['id'];
		if ($r['lukket']) {
			$betalingsbet = 'Kontant';
			$betalingsdage = '0';
		} else {
			($r['betalingsbet']) ? $betalingsbet = $r['betalingsbet'] : $betalingsbet = 'Kontant';
			$betalingsdage = (int) $r['betalingsdage'];
		}
		$konto_id *= 1;
		$qtxt = "update ordrer set konto_id='$konto_id', kontonr='$r[kontonr]',firmanavn='" . db_escape_string($r['firmanavn']) . "',";
		$qtxt .= "addr1='" . db_escape_string($r['addr1']) . "',addr2='" . db_escape_string($r['addr2']) . "',postnr='" . db_escape_string($r['postnr']) . "',";
		$qtxt .= "bynavn='" . db_escape_string($r['bynavn']) . "',land='" . db_escape_string($r['land']) . "',";
		$qtxt .= "betalingsdage='$betalingsdage',betalingsbet='$betalingsbet',cvrnr='" . db_escape_string($r['cvrnr']) . "',";
		$qtxt .= "ean='" . db_escape_string($r['ean']) . "',institution='" . db_escape_string($r['institution']) . "',";
		// 20261021 CL/MJ SD-758 The customer's phone number comes along with the rest of their details
		// (legacy core edit 9, POS part). A delivery or pickup sale has to carry a way to reach the
		// customer, and this is the only place a POS sale learns who they are.
		// adresser.tlf is varchar(60) but ordrer.phone is varchar(50), so a longer number is cut to
		// fit: postgresql refuses the whole statement on an overlong value, which would cost the
		// customer their attachment to the sale over a phone number. mb_substr, so that a cut
		// cannot land inside a multibyte character and leave the value invalid utf-8 - which
		// postgresql would refuse as well.
		$phone = mb_substr((string) $r['tlf'], 0, 50, 'UTF-8');
		$qtxt .= "phone='" . db_escape_string($phone) . "',";
		$qtxt .= "email='" . db_escape_string($r['email']) . "',kontakt='" . db_escape_string($r['kontakt']) . "',art='PO',valuta='$baseCurrency',valutakurs='100' ";
		$qtxt .= "where id = '$id'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	return ($id);
} # endfunc opdater_konto()
