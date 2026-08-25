<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//--- includes/order_creation.php ---patch 5.0.0 ----2026-08-25 ---
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
// -----------------------------------------------------------
//
// 20260825 CL/SZ Created for SD-600 scope item 1: shared order-creation
//                module. Replaces 4 duplicated "INSERT INTO ordrer" paths
//                (includes/ordrefunc.php::opret_ordre()/opret_ordre_kopi(),
//                debitor/ordre.php's plain new-order insert,
//                api/rest_api.php::insert_shop_order(), and the JWT REST
//                OrderService/OrderModel path) with one implementation.
//                Callers are migrated onto this one at a time - see
//                doc/ai/ (SD-600 plan) for the migration order and the two
//                JWT bugs (unlocked ordrenr, art='D' for creditor debtors)
//                this module fixes once JWT is migrated onto it.
// 20260825 CL/SZ Added $options['sql_filter'] to order_creation_insert() so
//                api/rest_api.php::insert_shop_order() can keep its existing
//                whole-string chk4utf8() encoding fixup unchanged (SD-600)
// 20260825 CL/SZ order_creation_insert() now returns 'ok' (db_modify()'s own
//                success/failure signal) so a caller that needs to detect a
//                failed insert - restapi OrderModel::save() does - still can;
//                callers that never checked this before are unaffected (SD-600)

// Union of every column touched by the 4 characterized INSERT INTO ordrer
// implementations. Additive-only as future callers migrate onto this module -
// a new caller needing a column not yet here just adds one line.
if (!function_exists('order_creation_known_columns')) {
	function order_creation_known_columns() {
		return array_fill_keys(array(
			'ordrenr', 'konto_id', 'kontonr', 'firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'land',
			'betalingsdage', 'betalingsbet', 'cvrnr', 'ean', 'institution', 'email', 'mail_fakt', 'notes',
			'art', 'ordredate', 'momssats', 'hvem', 'tidspkt', 'ref', 'valuta', 'sprog', 'kontakt',
			'kontakt_tlf', 'pbs', 'status', 'restordre', 'lev_navn', 'lev_addr1', 'lev_addr2', 'lev_postnr',
			'lev_bynavn', 'lev_kontakt', 'vis_lev_addr', 'felt_1', 'felt_2', 'felt_3', 'felt_4', 'felt_5',
			'sag_id', 'tilbudnr', 'datotid', 'nr', 'returside', 'sagsnr', 'procenttillag', 'kundeordnr',
			'afd', 'phone', 'lev_land', 'lev_email', 'omvbet', 'udskriv_til', 'projekt', 'betalings_id',
			'sum', 'moms', 'shop_status', 'shop_id', 'betalt', 'kostpris', 'fakturadate', 'valutakurs',
		), true);
	}
}

// Union of every column touched by the 2 characterized "create a new adresser
// row for an order" implementations (Webshop, JWT). Classic/OrdrePage never
// create a debtor, so they never touch this list.
if (!function_exists('order_creation_known_debtor_columns')) {
	function order_creation_known_debtor_columns() {
		return array_fill_keys(array(
			'kontonr', 'firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'land', 'cvrnr', 'ean', 'email',
			'tlf', 'gruppe', 'betalingsbet', 'betalingsdage', 'kontakt', 'lev_firmanavn', 'lev_addr1',
			'lev_addr2', 'lev_postnr', 'lev_bynavn', 'lev_land', 'lev_kontakt', 'lev_tlf', 'lev_email', 'lukket',
		), true);
		// Deliberately excludes 'art' - order_creation_resolve_debtor() always
		// sets that itself via order_creation_debtor_art_for_order_art(), a
		// caller-supplied 'art' here would be silently overwritten.
	}
}

// The single place that decides what art a NEW debtor gets when created for
// an order of a given art. Throws on an unmapped order art instead of
// silently guessing, so a future caller with a new art code must add an
// explicit, reviewable line here rather than accidentally recreating the
// JWT REST bug (OrderService::createNewDebtor() used to hardcode 'D' even
// for the creditor-orders endpoint).
if (!function_exists('order_creation_debtor_art_for_order_art')) {
	function order_creation_debtor_art_for_order_art($orderArt) {
		static $map = array(
			'DO' => 'D', 'DK' => 'D',
			'KO' => 'K', 'KK' => 'K',
		);
		if (!isset($map[$orderArt])) {
			throw new InvalidArgumentException(
				"order_creation_debtor_art_for_order_art: no debtor-art mapping for order art '$orderArt' - add it explicitly, do not guess."
			);
		}
		return $map[$orderArt];
	}
}

// Finds an existing adresser row by whichever of konto_id/kontonr/tlf the
// caller supplies (tried in that order), or creates one if $options['allow_create_debtor']
// is true and none is found. $newRowColumns is the caller-built column set
// for the INSERT if creation is needed (kontonr allocation stays the
// caller's own responsibility, unchanged from today - each of Webshop/JWT
// has its own numbering scheme and neither was flagged as a bug to fix).
// Returns null if no match and creation isn't allowed/didn't happen.
if (!function_exists('order_creation_resolve_debtor')) {
	function order_creation_resolve_debtor(array $lookup, string $orderArt, array $newRowColumns = array(), array $options = array()) {
		$row = null;
		if (!empty($lookup['konto_id'])) {
			$row = db_fetch_array(db_select("select * from adresser where id='" . (int)$lookup['konto_id'] . "'", __FILE__ . " linje " . __LINE__));
		}
		if (!$row && !empty($lookup['kontonr'])) {
			$row = db_fetch_array(db_select("select * from adresser where kontonr='" . db_escape_string($lookup['kontonr']) . "'", __FILE__ . " linje " . __LINE__));
		}
		if (!$row && !empty($lookup['tlf'])) {
			$row = db_fetch_array(db_select("select * from adresser where tlf='" . db_escape_string($lookup['tlf']) . "'", __FILE__ . " linje " . __LINE__));
		}
		if ($row) {
			$row['created'] = false;
			return $row;
		}

		if (empty($options['allow_create_debtor'])) {
			return null;
		}

		$known = order_creation_known_debtor_columns();
		$cols = array();
		$vals = array();
		foreach ($newRowColumns as $col => $val) {
			if (!isset($known[$col])) {
				throw new InvalidArgumentException("order_creation_resolve_debtor: unknown adresser column '$col' - add it to order_creation_known_debtor_columns() if this is a real column.");
			}
			$cols[] = $col;
			$vals[] = is_null($val) ? 'NULL' : "'" . $val . "'";
		}
		$art = !empty($options['debtor_art_override']) ? $options['debtor_art_override'] : order_creation_debtor_art_for_order_art($orderArt);
		$cols[] = 'art';
		$vals[] = "'" . $art . "'";

		$qtxt = "insert into adresser (" . implode(',', $cols) . ") values (" . implode(',', $vals) . ")";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);

		$row = db_fetch_array(db_select("select * from adresser where kontonr='" . db_escape_string($newRowColumns['kontonr']) . "'", __FILE__ . " linje " . __LINE__));
		if ($row) {
			$row['created'] = true;
		}
		return $row;
	}
}

// Thin wrapper around the EXISTING get_next_order_number($art) (includes/std_func.php)
// - not reimplemented, so the locking/uniqueness guarantees it already provides
// are unchanged. $override lets a caller supply its own number instead of
// minting a fresh one (e.g. a future copy/reversal-style caller).
if (!function_exists('order_creation_allocate_number')) {
	function order_creation_allocate_number($art, $override = null) {
		if ($override !== null) {
			return (int)$override;
		}
		return get_next_order_number($art);
	}
}

// The one and only INSERT INTO ordrer. $fields is a SPARSE assoc array keyed
// by column name, VALUE ALREADY ESCAPED/CAST BY THE CALLER (this module does
// not change escaping behavior for any of the 4 callers being migrated - see
// SD-600 plan). An absent key means "don't mention this column," reproducing
// each caller's own current column list. Throws on an unrecognized key.
//
// $options['sql_filter']: optional callable applied to the assembled query
// string right before db_modify() - e.g. api/rest_api.php's insert_shop_order()
// passes 'chk4utf8' to preserve its existing whole-string encoding fixup,
// which operates on the fully concatenated SQL (not per-field) and so can't
// be reproduced by filtering each value individually before it reaches here.
if (!function_exists('order_creation_insert')) {
	function order_creation_insert(array $fields, array $options = array()) {
		$known = order_creation_known_columns();
		$cols = array();
		$vals = array();
		foreach ($fields as $col => $val) {
			if (!isset($known[$col])) {
				throw new InvalidArgumentException("order_creation_insert: unknown ordrer column '$col' - add it to order_creation_known_columns() if this is a real column.");
			}
			$cols[] = $col;
			$vals[] = is_null($val) ? 'NULL' : "'" . $val . "'";
		}
		$qtxt = "insert into ordrer (" . implode(',', $cols) . ") values (" . implode(',', $vals) . ")";
		if (!empty($options['sql_filter'])) {
			$qtxt = call_user_func($options['sql_filter'], $qtxt);
		}
		$modifyResult = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		// db_modify()'s own success convention (includes/db_query.php) - "0\t..."
		// on success, "1\t<message>" on failure when $webservice is set (a caller
		// that needs to detect a failed insert, e.g. JWT REST, checks this;
		// callers that never checked it before this module existed still don't
		// have to).
		$ok = (explode("\t", (string)$modifyResult)[0] === '0');

		$row = db_fetch_array(db_select(
			"select id from ordrer where ordrenr='" . (int)$fields['ordrenr'] . "' and kontonr='" . db_escape_string($fields['kontonr']) . "' order by id desc limit 1",
			__FILE__ . " linje " . __LINE__
		));
		return array('id' => $row ? (int)$row['id'] : null, 'ordrenr' => (int)$fields['ordrenr'], 'ok' => $ok);
	}
}

// Facade: what all 4 in-scope callers use. See includes/ordrefunc.php,
// debitor/ordre.php, api/rest_api.php, restapi/services/OrderService.php for
// the migrated callers.
//
// $fields: sparse assoc array keyed by ordrer column name (see
//          order_creation_known_columns()). Must include 'art'. May include
//          'konto_id'/'kontonr' directly (an already-resolved debtor) OR
//          $options['identity'] (lookup hints for order_creation_resolve_debtor()).
// $options:
//   'allow_create_debtor'  (bool, default false)
//   'identity'             (array, passed to order_creation_resolve_debtor() when konto_id/kontonr absent)
//   'new_debtor_columns'   (array, the adresser column set to use if a debtor gets created)
//   'ordrenr_override'     (int|null)
//   'debtor_art_override'  (string|null)
if (!function_exists('order_creation_create')) {
	function order_creation_create(array $fields, array $options = array()) {
		if (empty($fields['art'])) {
			throw new InvalidArgumentException('order_creation_create: art is required');
		}

		$debtorCreated = false;
		if (empty($fields['konto_id']) && !empty($options['identity'])) {
			$debtor = order_creation_resolve_debtor(
				$options['identity'],
				$fields['art'],
				$options['new_debtor_columns'] ?? array(),
				array(
					'allow_create_debtor' => $options['allow_create_debtor'] ?? false,
					'debtor_art_override' => $options['debtor_art_override'] ?? null,
				)
			);
			if (!$debtor) {
				throw new RuntimeException('order_creation_create: no matching debtor found and creation was not possible');
			}
			$fields['konto_id'] = $debtor['id'];
			if (empty($fields['kontonr'])) {
				$fields['kontonr'] = $debtor['kontonr'];
			}
			$debtorCreated = !empty($debtor['created']);
		}

		if (empty($fields['ordrenr'])) {
			$fields['ordrenr'] = order_creation_allocate_number($fields['art'], $options['ordrenr_override'] ?? null);
		}

		$fields += array('ordredate' => date('Y-m-d'), 'tidspkt' => date('U'), 'status' => 0);

		$result = order_creation_insert($fields, $options);
		return $result + array(
			'konto_id' => $fields['konto_id'] ?? null,
			'kontonr' => $fields['kontonr'] ?? null,
			'debtor_created' => $debtorCreated,
		);
	}
}
