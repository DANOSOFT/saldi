<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/pbsfunc.php --- patch 5.0.0 --- 2026-09-08 ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20260908 CL/Sawaneh SST-763: Single home for pbsfakt() (was duplicated in debitor/pbsfakt.php
//                     and includes/ordrefunc.php). Adds per-invoice PBS attempt status, an
//                     explicit resend (pbs_gensend) and a lock so concurrent requests can only
//                     create one open batch / one attempt per batch.

/**
 * Serialises "find or create the open PBS batch" + "add attempt" across requests.
 * Session-level lock: released by pbs_frigiv() or when the connection ends.
 *
 * @return bool false when the lock could not be taken (MySQL timeout)
 */
if (!function_exists('pbs_laas')) {
	function pbs_laas() {
		global $db_type;
		if ($db_type == 'mysql' || $db_type == 'mysqli') {
			$r = db_fetch_array(db_select("select get_lock('pbs_liste', 10) as lock_ok", __FILE__ . " linje " . __LINE__));
			return ($r && $r['lock_ok'] == 1);
		}
		db_select("select pg_advisory_lock(hashtext('pbs_liste'))", __FILE__ . " linje " . __LINE__);
		return true;
	}
}

if (!function_exists('pbs_frigiv')) {
	function pbs_frigiv() {
		global $db_type;
		if ($db_type == 'mysql' || $db_type == 'mysqli') {
			db_select("select release_lock('pbs_liste')", __FILE__ . " linje " . __LINE__);
		} else {
			db_select("select pg_advisory_unlock(hashtext('pbs_liste'))", __FILE__ . " linje " . __LINE__);
		}
	}
}

/**
 * Id of the open (not yet sent) PBS batch, creating one when none exists. Call with pbs_laas() held.
 *
 * @return int
 */
if (!function_exists('pbs_aaben_liste')) {
	function pbs_aaben_liste() {
		$qtxt = "select id from pbs_liste where coalesce(afsendt, '') = '' order by id limit 1";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			return intval($r['id']);
		}
		$liste_date = date("Y-m-d");
		db_modify("insert into pbs_liste (liste_date, afsendt) values ('$liste_date', '')", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		return intval($r['id']);
	}
}

/**
 * Paid evidence: a settled open-post row for the invoice with the invoice amount.
 * ordrer.betalt is not used - dunning sets it to 'on' without any payment.
 *
 * @param array $ordre row from ordrer (fakturanr, konto_id, sum, moms)
 * @return bool
 */
if (!function_exists('pbs_faktura_betalt')) {
	function pbs_faktura_betalt($ordre) {
		$fakturanr = db_escape_string($ordre['fakturanr']);
		$konto_id = intval($ordre['konto_id']);
		$total = number_format(round($ordre['sum'] + $ordre['moms'], 2), 2, '.', '');
		$qtxt = "select id from openpost where faktnr = '$fakturanr' and konto_id = '$konto_id' and udlignet = '1' ";
		$qtxt .= "and (abs(amount - $total) < 0.01 or abs(amount + $total) < 0.01)";
		return (bool) db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	}
}

/**
 * PBS state of one invoice, derived from its attempts in pbs_ordrer and the open-post ledger.
 *
 * status: ikke_faktureret | betalt | ikke_sendt | i_koe | afventer | afvist
 *   i_koe     - in the open batch (not sent yet)
 *   afventer  - in a sent batch, no result registered; Nets' answer is not ingested
 *               automatically, so a resend requires the user to register the rejection
 *   afvist    - last attempt registered as rejected by Nets
 *
 * @param int $ordre_id
 * @return array{
 *   status: string,
 *   kan_gensendes: bool,
 *   kraever_ref: bool,
 *   forklaring: string,
 *   ordre: array|null,
 *   forsoeg: array<int, array>,
 *   sidste: array|null
 * }
 */
if (!function_exists('pbs_ordre_status')) {
	function pbs_ordre_status($ordre_id) {
		global $sprog_id;
		$ordre_id = intval($ordre_id);
		$s = array('status' => 'ikke_faktureret', 'kan_gensendes' => false, 'kraever_ref' => false,
			'forklaring' => '', 'ordre' => NULL, 'forsoeg' => array(), 'sidste' => NULL);

		$qtxt = "select id, ordrenr, fakturanr, fakturadate, konto_id, kontonr, firmanavn, sum, moms, status, art, ";
		$qtxt .= "betalingsbet, betalingsdage from ordrer where id = '$ordre_id'";
		$ordre = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if (!$ordre) {
			$s['forklaring'] = findtekst('5176|Ordren er ikke bogført som faktura', $sprog_id);
			return $s;
		}
		$s['ordre'] = $ordre;

		$qtxt = "select pbs_ordrer.*, pbs_liste.liste_date, coalesce(pbs_liste.afsendt, '') as afsendt ";
		$qtxt .= "from pbs_ordrer left join pbs_liste on pbs_liste.id = pbs_ordrer.liste_id ";
		$qtxt .= "where pbs_ordrer.ordre_id = '$ordre_id' order by pbs_ordrer.id";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		$i_koe = 0;
		while ($r = db_fetch_array($q)) {
			$r['resultat'] = if_isset($r, '', 'resultat');
			$s['forsoeg'][] = $r;
			$s['sidste'] = $r;
			if (!$r['afsendt']) {
				$i_koe = intval($r['liste_id']);
			}
		}
		$fakturanr = $ordre['fakturanr'];

		if ($ordre['status'] < 3 || $ordre['art'] != 'DO') {
			$s['forklaring'] = findtekst('5176|Ordren er ikke bogført som faktura', $sprog_id);
		} elseif (pbs_faktura_betalt($ordre)) {
			$s['status'] = 'betalt';
			$s['forklaring'] = findtekst('5174|Fakturaen er udlignet/betalt og kan ikke gensendes til PBS', $sprog_id);
		} elseif (!$s['sidste']) {
			$s['status'] = 'ikke_sendt';
			$s['forklaring'] = findtekst('5175|Fakturaen har ikke været med i nogen PBS-leverance. Den kommer på leverancen ved bogføring.', $sprog_id);
		} elseif ($i_koe) {
			$s['status'] = 'i_koe';
			$s['forklaring'] = findtekst('5180|Fakturanr', $sprog_id) . " $fakturanr " . findtekst('5172|findes allerede i den åbne PBS-leverance', $sprog_id) . " $i_koe";
		} elseif ($s['sidste']['resultat'] == 'afvist') {
			$s['status'] = 'afvist';
			$s['kan_gensendes'] = true;
			$s['forklaring'] = findtekst('3011|Afvist', $sprog_id) . ": " . $s['sidste']['resultat_ref'];
		} else {
			$s['status'] = 'afventer';
			$s['kan_gensendes'] = true;
			$s['kraever_ref'] = true;
			$s['forklaring'] = findtekst('5180|Fakturanr', $sprog_id) . " $fakturanr " . findtekst('5173|afventer svar fra Nets på leverance', $sprog_id) . " " . $s['sidste']['liste_id'] . ". ";
			$s['forklaring'] .= findtekst('5178|Angiv afvisningen fra Nets for at kunne gensende. Uden registreret afvisning regnes fakturaen som afventende hos Nets.', $sprog_id);
		}
		return $s;
	}
}

/**
 * Adds one attempt for the invoice to the open batch. Call with pbs_laas() held.
 *
 * @param int $ordre_id
 * @param int $gensendt_fra id of the pbs_ordrer attempt this one replaces, 0 for a first send
 * @return int batch id
 */
if (!function_exists('pbs_tilfoej')) {
	function pbs_tilfoej($ordre_id, $gensendt_fra = 0) {
		global $bruger_id, $brugernavn;
		$ordre_id = intval($ordre_id);
		$gensendt_fra = intval($gensendt_fra);
		$bruger = intval($bruger_id);
		$navn = db_escape_string($brugernavn);
		$liste_id = pbs_aaben_liste();
		$nu = date('Y-m-d H:i:s');
		$qtxt = "insert into pbs_ordrer (liste_id, ordre_id, oprettet, bruger_id, brugernavn, gensendt_fra) ";
		$qtxt .= "values ('$liste_id', '$ordre_id', '$nu', '$bruger', '$navn', '$gensendt_fra')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		return $liste_id;
	}
}

/**
 * Explicit resend of an invoice Nets rejected. Registers the rejection on the last attempt
 * (reference required while the attempt is still 'afventer') and adds a new attempt to the
 * open batch. The earlier attempt and its batch are kept as history.
 *
 * @param int $ordre_id
 * @param string $ref rejection reference/reason from Nets
 * @param int $sidste_id id of the attempt the caller saw as the latest; a mismatch means the
 *                       state changed since the page was opened (reload, other user) and nothing is done
 * @return array{ok: bool, besked: string}
 */
if (!function_exists('pbs_gensend')) {
	function pbs_gensend($ordre_id, $ref, $sidste_id) {
		global $sprog_id, $bruger_id, $db_modify_fejl;
		$ordre_id = intval($ordre_id);
		$sidste_id = intval($sidste_id);
		$ref = trim($ref);

		if (!pbs_laas()) {
			return array('ok' => false, 'besked' => findtekst('5187|Kunne ikke låse PBS-leverancen - prøv igen', $sprog_id));
		}
		transaktion('begin');
		$s = pbs_ordre_status($ordre_id);
		$fejl = '';
		if (!$s['kan_gensendes']) {
			$fejl = $s['forklaring'];
		} elseif (intval($s['sidste']['id']) != $sidste_id) {
			$fejl = findtekst('5184|Status er ændret siden siden blev åbnet. Kontrollér historikken og prøv igen.', $sprog_id);
		} elseif ($s['kraever_ref'] && $ref == '') {
			$fejl = findtekst('5178|Angiv afvisningen fra Nets for at kunne gensende. Uden registreret afvisning regnes fakturaen som afventende hos Nets.', $sprog_id);
		}
		if ($fejl) {
			transaktion('rollback');
			pbs_frigiv();
			return array('ok' => false, 'besked' => $fejl);
		}

		if ($s['kraever_ref'] || $ref != '') {
			$ref_sql = db_escape_string($ref);
			$dato = date('Y-m-d');
			$bruger = intval($bruger_id);
			$qtxt = "update pbs_ordrer set resultat = 'afvist', resultat_ref = '$ref_sql', resultat_dato = '$dato', ";
			$qtxt .= "resultat_bruger_id = '$bruger' where id = '$sidste_id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		$liste_id = pbs_tilfoej($ordre_id, $sidste_id);
		if ($db_modify_fejl) {
			transaktion('rollback');
			pbs_frigiv();
			return array('ok' => false, 'besked' => findtekst('5189|Databasefejl - intet er gemt', $sprog_id));
		}
		transaktion('commit');
		pbs_frigiv();
		$besked = findtekst('5180|Fakturanr', $sprog_id) . " " . $s['ordre']['fakturanr'] . " ";
		$besked .= findtekst('5171|er tilføjet PBS-leverance', $sprog_id) . " $liste_id";
		return array('ok' => true, 'besked' => $besked);
	}
}

/**
 * Called from the posting flows (ordre/bogfor/genfakturer/ret_genfakt): puts the newly posted
 * invoice on the open batch. Refuses when the invoice is already queued or is sent and awaiting
 * Nets - those go through pbs_gensend() from debitor/pbs_gensend.php.
 *
 * @param int $id ordrer.id
 */
if (!function_exists('pbsfakt')) {
	function pbsfakt($id) {
		global $sprog_id;
		$id = intval($id);
		if ($id <= 0) {
			return;
		}
		if (!pbs_laas()) {
			print findtekst('5187|Kunne ikke låse PBS-leverancen - prøv igen', $sprog_id) . "<br>";
			return;
		}
		$s = pbs_ordre_status($id);
		if ($s['ordre'] && ($s['status'] == 'ikke_sendt' || $s['status'] == 'afvist')) {
			$liste_id = pbs_tilfoej($id, $s['sidste'] ? $s['sidste']['id'] : 0);
			print findtekst('5180|Fakturanr', $sprog_id) . " " . $s['ordre']['fakturanr'] . " ";
			print findtekst('5171|er tilføjet PBS-leverance', $sprog_id) . " $liste_id<br>";
		} else {
			print $s['forklaring'] . "<br>";
		}
		pbs_frigiv();
	}
}
