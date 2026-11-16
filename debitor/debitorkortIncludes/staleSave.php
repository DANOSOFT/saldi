<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/debitorkortIncludes/staleSave.php --- ver 5.1.1 --- 2026-11-12 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261112 CL/SS MB-82 Created. The card's form carries a hash of the stored customer, and a save is refused when the customer changed after the card was opened.
//                The contacts' ids and positions are in the hash too, as the card's save writes the contact order.
//                The contacts' other fields are in the hash too, so a contact marked «-» isn't deleted after someone edited it.
//                debitorkortFillPrivateNames() fills in a private card's first and last name as plain text before the hash.

if (!function_exists('debitorkortRecordHash')) {
	/**
	 * MB-82: a hash of everything the card's save writes for one customer.
	 *
	 * Covers the adresser columns the save's UPDATE writes (plus email, which it writes from the e-mail rows),
	 * the customer's kontakt_emails and delivery_addresses rows, and its contacts (ansatte), whose order the save writes and which «-» deletes. adresser.modtime is not used, as invoicing
	 * and the card's own page load update the row without anyone editing the card.
	 *
	 * @param int $id adresser.id of the customer.
	 * Serialized byte for byte, so values in a LATIN9 connection hash as well as UTF-8 ones.
 *
 * @return string md5 of the values, the same for the same stored data.
	 */
	function debitorkortRecordHash($id) {
		$id = (int)$id;
		$columns = array(
			'kontonr', 'firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'land', 'kontakt', 'tlf', 'mobile', 'email',
			'mailfakt', 'web', 'betalingsdage', 'kreditmax', 'betalingsbet', 'cvrnr', 'ean', 'institution', 'notes',
			'gruppe', 'kontoansvarlig', 'bank_reg', 'bank_konto', 'swift', 'pbs_nr', 'pbs', 'kontotype', 'fornavn',
			'efternavn', 'lev_firmanavn', 'lev_fornavn', 'lev_efternavn', 'lev_addr1', 'lev_addr2', 'lev_postnr',
			'lev_bynavn', 'lev_land', 'lev_kontakt', 'lev_tlf', 'lev_email', 'felt_1', 'felt_2', 'felt_3', 'felt_4',
			'felt_5', 'vis_lev_addr', 'lukket', 'stripe_fravalg', 'kategori', 'rabatgruppe', 'status', 'productlimit',
			'enduser_type',
		);
		$data = array('adresser' => array(), 'kontakt_emails' => array(), 'delivery_addresses' => array(), 'ansatte' => array());
		$r = db_fetch_array(db_select("select * from adresser where id = '$id'", __FILE__ . " linje " . __LINE__));
		foreach ($columns as $column) {
			if ($r && array_key_exists($column, $r)) {
				$data['adresser'][$column] = (string)$r[$column];
			}
		}
		$q = db_select("select id, email, email_type from kontakt_emails where konto_id = '$id' order by id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$data['kontakt_emails'][] = array((string)$r['id'], (string)$r['email'], (string)$r['email_type']);
		}
		$q = db_select("select * from delivery_addresses where account_id = '$id' order by id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$row = array();
			foreach ($r as $column => $value) {
				if (is_string($column)) {
					$row[$column] = (string)$value;
				}
			}
			$data['delivery_addresses'][] = $row;
		}
		$q = db_select("select * from ansatte where konto_id = '$id' order by id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$row = array();
			foreach ($r as $column => $value) {
				if (is_string($column)) {
					$row[$column] = (string)$value;
				}
			}
			$data['ansatte'][] = $row;
		}
		return md5(serialize($data));
	}
}

if (!function_exists('debitorkortFillPrivateNames')) {
	/**
	 * MB-82: a private card without first and last name gets them from its name, before the card's hash is taken.
	 *
	 * The names are stored as plain text (the card escapes them when it shows them), and only while both are still
	 * empty, so names saved meanwhile by someone else are kept. split_navn() is the card's own name splitter.
	 *
	 * @param int $id adresser.id of the customer.
	 * @return bool True when the names were filled in.
	 */
	function debitorkortFillPrivateNames($id) {
		$id = (int)$id;
		$r = db_fetch_array(db_select("select kontotype, firmanavn, fornavn, efternavn from adresser where id = '$id'", __FILE__ . " linje " . __LINE__));
		if (!$r || trim((string)$r['kontotype']) != 'privat' || trim((string)$r['fornavn']) || trim((string)$r['efternavn']) || !trim((string)$r['firmanavn'])) {
			return false;
		}
		list($fornavn, $efternavn) = explode(",", split_navn(trim($r['firmanavn'])));
		$qtxt = "update adresser set fornavn = '" . db_escape_string($fornavn) . "', efternavn = '" . db_escape_string($efternavn) . "' ";
		$qtxt.= "where id = '$id' and trim(coalesce(fornavn, '')) = '' and trim(coalesce(efternavn, '')) = ''";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		return true;
	}
}

if (!function_exists('debitorkortSaveIsStale')) {
	/**
	 * MB-82: whether a posted card would overwrite changes saved after the card was opened.
	 *
	 * Only a save of an existing customer from a form that carries recordHash is checked; other posts
	 * (a new customer, delete, anonymize, forms without the field) go on as before.
	 *
	 * @param int   $id   adresser.id from the post.
	 * @param array $post The posted fields.
	 * @return bool True when the stored customer no longer matches the hash the card was opened with.
	 */
	function debitorkortSaveIsStale($id, $post) {
		if ((int)$id <= 0 || !isset($post['recordHash']) || !is_string($post['recordHash'])) {
			return false;
		}
		return !hash_equals(debitorkortRecordHash($id), $post['recordHash']);
	}
}
