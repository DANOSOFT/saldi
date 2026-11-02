<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//--- includes/stdFunc/createDebitor.php --- ver 5.1.0 --- 20261101 ---
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
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// This function finds the Vat account belonging to an account in ledger.
//
// 20260827 Sawaneh create_debtor: kontonr availability is checked across all arts (a creditor's
//                 number is refused too) and also for auto-assigned numbers right before
//                 insert, so a concurrent creation cannot slip in a duplicate (SST-753)
// 20261101 CL/SS SD-792 Core edit 8: a taken number throws DebtorNumberTakenException, and debitor/ordre.php shows its alert.
//                betalingsbet is escaped.
//                The number is chosen and inserted under one lock, shared with the Shopify worker (allocate_debtor_number()).
//                With $headless (the worker) a failed insert never alerts and exits; in webservice mode it throws instead of returning no id.

final class DebtorNumberTakenException extends RuntimeException {
	public function __construct($kontonr) {
		parent::__construct("Kontonr $kontonr er ikke ledigt!");
	}
}

/**
 * Takes the debtor-number lock until the transaction ends, so the number chosen and the insert of
 * it cannot interleave with another creation. A no-op outside PostgreSQL and outside a transaction.
 */
function debtor_number_lock() {
	global $db_type;
	if ($db_type == "mysql" || $db_type == "mysqli") return;
	db_select("select pg_advisory_xact_lock(hashtext('saldi:debtor_number'))", __FILE__ . " linje " . __LINE__);
}

/**
 * The next debtor number under the lock: get_next_number(), or from $start (PostgreSQL) the
 * highest number in use from there plus one, numbers never reused, as get_next_number(); the
 * first free number from $start when that passes eight digits.
 */
function allocate_debtor_number($start = 0) {
	debtor_number_lock();
	$start = (int)$start;
	if ($start <= 0) return (int)get_next_number('adresser', 'D');
	// the cast only where the pattern matched: PostgreSQL does not promise the order of where conditions
	$numbers = "select k from (select case when btrim(kontonr) ~ '^[0-9]{1,8}$' then btrim(kontonr)::bigint end as k from adresser) n where k >= $start";
	$r = db_fetch_array(db_select("select max(k) as k from ($numbers) n", __FILE__ . " linje " . __LINE__));
	$next = $r['k'] === null ? $start : (int)$r['k'] + 1;
	if ($next > 99999999) {
		$qtxt = "select k + 1 as k from (select k, lead(k) over (order by k) as nk from (select " . ($start - 1) . "::bigint as k union $numbers) d) x ";
		$qtxt.= "where nk is null or nk > k + 1 order by k limit 1";
		$next = (int)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))['k'];
		if ($next > 99999999) throw new RuntimeException("no free debtor number from $start");
	}
	return $next;
}

function create_debtor($kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$email,$tlf,$cvrnr,$grp,$ean,$betalingsbet,$betalingsdage,$kontakt,$headless=false) {
	global $connection, $db_type, $webservice;

	$own = !in_array($db_type, array('mysql', 'mysqli')) && pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE;
	if ($own) transaktion('begin');
	$page = $webservice;
	if ($headless) $webservice = true;
	try {
		if (!$kontonr) {
			$kontonr = allocate_debtor_number();
		} else {
			debtor_number_lock();
		}
		$qtxt="select id from adresser where kontonr='".db_escape_string($kontonr)."'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			throw new DebtorNumberTakenException($kontonr);
		}
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr); #20190423
		$betalingsdage = (int)$betalingsdage;

		$qtxt = "insert into adresser ";
		$qtxt.= "(kontonr,firmanavn,addr1,addr2,postnr,bynavn,email,tlf,cvrnr,ean,gruppe,kontakt,art,lukket,betalingsbet,betalingsdage)";
		$qtxt.=" values ";
		$qtxt.= "('".db_escape_string($kontonr)."','".db_escape_string($firmanavn)."','".db_escape_string($addr1)."',";
		$qtxt.= "'".db_escape_string($addr2)."','".db_escape_string($postnr)."','".db_escape_string($bynavn)."',";
		$qtxt.="'".db_escape_string($email)."','".db_escape_string($tlf)."','".db_escape_string($cvrnr)."','".db_escape_string($ean)."',";
		$qtxt.="'".db_escape_string($grp)."','".db_escape_string($kontakt)."','D','','".db_escape_string($betalingsbet)."','$betalingsdage')";
		$svar = db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if (substr((string)$svar, 0, 1) === '1') {
			throw new RuntimeException("create_debtor: debtor $kontonr was not saved");
		}
		$qtxt="select id from adresser where kontonr='".db_escape_string($kontonr)."' and art='D'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($own) transaktion('commit');
	} catch (Throwable $e) {
		if ($own) transaktion('rollback');
		throw $e;
	} finally {
		$webservice = $page;
	}
	return ($r['id']);

}
?>
