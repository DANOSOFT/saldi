<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/peppolInvoiceData.php --- ver 5.1.0 --- 2026-11-09 ---
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
// 20261109 CL/MMR SD-815 Created (draft). Reads an invoiced order and everything peppol_document_model() needs, as plain UTF-8 arrays.
//                 Only reads: no row is written, so it is safe on a copy of a customer's database.
// 20261109 CL/MMR SD-815 Also reads whether the seller has a sales VAT rate above 0 (settings vatRegistered) and whether the posting made an open item (openItem).

include_once(__DIR__ . '/../var2str.php');

if (!function_exists('peppol_rows_to_utf8')) {
	/**
	 * Converts every string in the rows to UTF-8.
	 *
	 * A LATIN9 install ($db_encode other than 'UTF8') gets ISO-8859-15 from the connection (pg_set_client_encoding() in
	 * includes/db_query.php), and the document model must be UTF-8. On a UTF-8 install a value that is not valid UTF-8
	 * (an old MySQL row) is read as Windows-1252, as db_escape_string() does.
	 *
	 * @param mixed  $value    Rows, a row or a value.
	 * @param string $dbEncode $db_encode of the install.
	 * @return mixed The same structure with UTF-8 strings.
	 */
	function peppol_rows_to_utf8($value, $dbEncode) {
		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$value[$key] = peppol_rows_to_utf8($item, $dbEncode);
			}
			return $value;
		}
		if (!is_string($value) || $value === '') {
			return $value;
		}
		if ($dbEncode !== 'UTF8') {
			return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-15');
		}
		return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
	}
}

if (!function_exists('peppol_rows_assoc')) {
	/**
	 * A fetched row without the numeric keys db_fetch_array() also returns.
	 *
	 * @param array|false|null $row The row.
	 * @return array<string, mixed>|null Null when there is no row.
	 */
	function peppol_rows_assoc($row) {
		if (!is_array($row)) {
			return null;
		}
		$assoc = array();
		foreach ($row as $key => $value) {
			if (is_string($key)) {
				$assoc[$key] = $value;
			}
		}
		return $assoc;
	}
}

if (!function_exists('peppol_rows_group')) {
	/**
	 * The grupper row of one art and kodenr for the fiscal year the invoice belongs to, else the newest one.
	 *
	 * @param string   $art        'DG' or 'VG'.
	 * @param int      $kodenr     The group number.
	 * @param int|null $fiscalYear grupper.kodenr of the RA row covering the invoice date, or null.
	 * @return array<string, mixed>|null
	 */
	function peppol_rows_group($art, $kodenr, $fiscalYear) {
		$qtxt = "SELECT * FROM grupper WHERE art = '" . db_escape_string($art) . "' AND kodenr = '" . (int)$kodenr . "' ORDER BY fiscal_year DESC";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		$first = null;
		while ($r = db_fetch_array($q)) {
			$row = peppol_rows_assoc($r);
			if ($first === null) {
				$first = $row;
			}
			if ($fiscalYear !== null && (int)($row['fiscal_year'] ?? 0) === (int)$fiscalYear) {
				return $row;
			}
		}
		return $first;
	}
}

if (!function_exists('peppol_rows_fiscal_year')) {
	/**
	 * The fiscal year (grupper art='RA' kodenr) that covers a date; box1/box2 are the start month and year, box3/box4 the end.
	 *
	 * @param string|null $date 'YYYY-MM-DD'.
	 * @return int|null
	 */
	function peppol_rows_fiscal_year($date) {
		if (!preg_match('/^(\d{4})-(\d{2})/', (string)$date, $m)) {
			return null;
		}
		$ym = (int)($m[1] . $m[2]);
		$q = db_select("SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$start = (int)$r['box2'] * 100 + (int)$r['box1'];
			$end = (int)$r['box4'] * 100 + (int)$r['box3'];
			if ($ym >= $start && $ym <= $end) {
				return (int)$r['kodenr'];
			}
		}
		return null;
	}
}

if (!function_exists('peppol_rows_setting')) {
	/**
	 * One value from the settings table, or $default.
	 *
	 * @param string      $name    var_name.
	 * @param string|null $group   var_grp, or null for any group.
	 * @param string      $default Returned when there is no row.
	 * @return string
	 */
	function peppol_rows_setting($name, $group, $default = '') {
		$qtxt = "SELECT var_value FROM settings WHERE var_name = '" . db_escape_string($name) . "'";
		if ($group !== null) {
			$qtxt .= " AND var_grp = '" . db_escape_string($group) . "'";
		}
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		return ($r && $r['var_value'] !== null) ? (string)$r['var_value'] : $default;
	}
}

if (!function_exists('peppol_rows_vat_registered')) {
	/**
	 * Whether the seller is VAT registered, read from its sales VAT groups (grupper art='SM', box2 = the rate): a seller
	 * that is not VAT registered is set up with 0 % on every Salgsmoms row, so it is registered when one rate is above 0.
	 * The rows of the invoice's fiscal year count; without any for that year, all rows count. No SM row at all counts as
	 * registered (the default the builder had).
	 *
	 * @param list<array{fiscal_year?: int|string|null, box2?: string|null}> $groups     The SM rows.
	 * @param int|null                                                        $fiscalYear grupper.kodenr of the RA row covering the invoice date.
	 * @return bool
	 */
	function peppol_rows_vat_registered(array $groups, $fiscalYear) {
		$ofYear = array();
		foreach ($groups as $group) {
			if ($fiscalYear !== null && (int)($group['fiscal_year'] ?? 0) === (int)$fiscalYear) {
				$ofYear[] = $group;
			}
		}
		$counted = $ofYear ? $ofYear : $groups;
		if (!$counted) {
			return true;
		}
		foreach ($counted as $group) {
			if ((float)str_replace(',', '.', trim((string)($group['box2'] ?? ''))) > 0) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('peppol_invoice_rows')) {
	/**
	 * Reads everything the Peppol document model needs for one order, without any interpretation; peppol_document_model()
	 * does the rest.
	 *
	 * The lines are those momsupdat() (includes/ordrefunc.php) counts when it books ordrer.sum and ordrer.moms: posnr >= 0,
	 * in the PDF's order (posnr, id). A line text with a variable ($maaned, $aar, $serienr ...) is resolved with var2str() as
	 * the PDF does. Strings are UTF-8 whatever the database encoding ($db_encode).
	 *
	 * @param int $orderId ordrer.id.
	 * @return array{
	 *   order: array<string, string|null>|null,        The ordrer row; null when the order does not exist.
	 *   lines: list<array<string, string|null>>,       ordrelinjer rows with posnr >= 0, ordered by posnr, id.
	 *   seller: array<string, string|null>|null,       Stamdata, adresser art='S'.
	 *   customer: array<string, string|null>|null,     The debitor card, adresser id = ordrer.konto_id.
	 *   debtorGroup: array<string, string|null>|null,  grupper art='DG' of the card's group (box1 VAT group, box9 reverse charge, box10 EU zone).
	 *   itemGroups: array<int, array{group: int, type: string, reverseCharge: string, vatExempt: string}>,  Per vare_id: varer.gruppe and its grupper art='VG' box5/box6/box7.
	 *   creditedInvoice: array{id: int, fakturanr: string|null, fakturadate: string|null, art: string|null}|null,  The order ordrer.kred_ord_id points at.
	 *   settings: array{baseCurrency: string, baseCountry: string, sendOrderNote: string, cashAccount: string, cardAccount: string, vatRegistered: bool},
	 *                                                  vatRegistered: peppol_rows_vat_registered() for the invoice's fiscal year.
	 *   openItem: bool|null,                           Whether bogfor_nu() made an open item for the invoice (openpost with
	 *                                                  refnr = ordrer.id and faktnr = fakturanr); null without a customer account.
	 *   fikLayout: array{accountLength: int, invoiceLength: int},  betalingsid layout of the invoice form, as modulus_10() reads it.
	 *   encoding: string,                              $db_encode the strings were converted from.
	 * }
	 */
	function peppol_invoice_rows(int $orderId): array {
		global $db_encode;

		$orderId = (int)$orderId;
		$rows = array(
			'order' => null,
			'lines' => array(),
			'seller' => null,
			'customer' => null,
			'debtorGroup' => null,
			'itemGroups' => array(),
			'creditedInvoice' => null,
			'settings' => array('baseCurrency' => 'DKK', 'baseCountry' => '', 'sendOrderNote' => 'off', 'cashAccount' => '', 'cardAccount' => '', 'vatRegistered' => true),
			'openItem' => null,
			'fikLayout' => array('accountLength' => 0, 'invoiceLength' => 14),
			'encoding' => (string)($db_encode ?? 'UTF8'),
		);

		$order = peppol_rows_assoc(db_fetch_array(db_select("SELECT * FROM ordrer WHERE id = $orderId", __FILE__ . " linje " . __LINE__)));
		if ($order === null) {
			return $rows;
		}
		$rows['order'] = $order;

		$q = db_select("SELECT * FROM ordrelinjer WHERE ordre_id = $orderId AND posnr >= 0 ORDER BY posnr, id", __FILE__ . " linje " . __LINE__);
		$vareIds = array();
		while ($r = db_fetch_array($q)) {
			$line = peppol_rows_assoc($r);
			if (strpos((string)$line['beskrivelse'], '$') !== false) {
				// As formfunk.php prints it: Danish quantity, the line's own price, procent, serial numbers, VAT rate, discount
				$line['beskrivelse'] = var2str($line['beskrivelse'], $orderId, $line['posnr'], $line['varenr'], str_replace('.', ',', (string)(float)$line['antal']),
					$line['enhed'], $line['pris'], $line['procent'] ?? null, $line['serienr'] ?? null, $line['momssats'], $line['rabat']);
			}
			if ((int)($line['vare_id'] ?? 0) > 0) {
				$vareIds[(int)$line['vare_id']] = true;
			}
			$rows['lines'][] = $line;
		}

		$rows['seller'] = peppol_rows_assoc(db_fetch_array(db_select("SELECT * FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1", __FILE__ . " linje " . __LINE__)));
		$kontoId = (int)($order['konto_id'] ?? 0);
		if ($kontoId > 0) {
			$rows['customer'] = peppol_rows_assoc(db_fetch_array(db_select("SELECT * FROM adresser WHERE id = $kontoId", __FILE__ . " linje " . __LINE__)));
		}

		$fiscalYear = peppol_rows_fiscal_year($order['fakturadate'] ?? null);
		$debtorGroupNo = (int)($rows['customer']['gruppe'] ?? 0);
		if ($debtorGroupNo > 0) {
			$rows['debtorGroup'] = peppol_rows_group('DG', $debtorGroupNo, $fiscalYear);
		}

		if ($vareIds) {
			$ids = implode(',', array_map('intval', array_keys($vareIds)));
			$q = db_select("SELECT id, gruppe FROM varer WHERE id IN ($ids)", __FILE__ . " linje " . __LINE__);
			$itemGroupCache = array();
			while ($r = db_fetch_array($q)) {
				$groupNo = (int)$r['gruppe'];
				if (!array_key_exists($groupNo, $itemGroupCache)) {
					$itemGroupCache[$groupNo] = ($groupNo > 0) ? peppol_rows_group('VG', $groupNo, $fiscalYear) : null;
				}
				$group = $itemGroupCache[$groupNo];
				$rows['itemGroups'][(int)$r['id']] = array(
					'group' => $groupNo,
					'type' => trim((string)($group['box5'] ?? '')),
					'reverseCharge' => trim((string)($group['box6'] ?? '')),
					'vatExempt' => trim((string)($group['box7'] ?? '')),
				);
			}
		}

		$kredOrdId = (int)($order['kred_ord_id'] ?? 0);
		if ($kredOrdId > 0) {
			$r = db_fetch_array(db_select("SELECT id, fakturanr, fakturadate, art FROM ordrer WHERE id = $kredOrdId", __FILE__ . " linje " . __LINE__));
			if ($r) {
				$rows['creditedInvoice'] = array('id' => (int)$r['id'], 'fakturanr' => $r['fakturanr'], 'fakturadate' => $r['fakturadate'], 'art' => $r['art']);
			}
		}

		$rows['settings']['baseCurrency'] = peppol_rows_setting('baseCurrency', null, 'DKK');
		$rows['settings']['baseCountry'] = peppol_rows_setting('baseCountry', null, '');
		// The same switch as easyUblOrderNote() in debitor/api.php (SST-885)
		$rows['settings']['sendOrderNote'] = peppol_rows_setting('sendOrderNote', 'easyUBL', 'off');
		// bogfor_nu() posts Kontant/Kreditkort straight to these accounts instead of an open item
		$r = db_fetch_array(db_select("SELECT box7, box10 FROM grupper WHERE art = 'DIV' AND kodenr = '3'", __FILE__ . " linje " . __LINE__));
		if ($r) {
			$rows['settings']['cashAccount'] = trim((string)$r['box7']);
			$rows['settings']['cardAccount'] = trim((string)$r['box10']);
		}
		// Whether the posting left an open item: today's DIV 3 may differ from what bogfor_nu() saw at posting
		$fakturanr = trim((string)($order['fakturanr'] ?? ''));
		if ($kontoId > 0 && $fakturanr !== '') {
			$qtxt = "SELECT id FROM openpost WHERE refnr = $orderId AND faktnr = '" . db_escape_string($fakturanr) . "'";
			// The order's account first (openpost is indexed on konto_id); then any account, as an open item keeps the
			// account it was posted to when the order's account is changed later
			$rows['openItem'] = (bool)db_fetch_array(db_select("$qtxt AND konto_id = $kontoId", __FILE__ . " linje " . __LINE__))
				|| (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		}
		// A seller that is not VAT registered has no sales VAT rate above 0 (category O, no VAT numbers)
		$smGroups = array();
		$q = db_select("SELECT fiscal_year, box2 FROM grupper WHERE art = 'SM'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$smGroups[] = array('fiscal_year' => $r['fiscal_year'], 'box2' => $r['box2']);
		}
		$rows['settings']['vatRegistered'] = peppol_rows_vat_registered($smGroups, $fiscalYear);

		if (trim((string)($rows['seller']['bank_fi'] ?? '')) !== '') {
			// modulus_10() in includes/formfunk.php: the invoice form's language, xa/ya or betalingsid(account,invoice)
			$language = strtolower(trim((string)($order['sprog'] ?? '')));
			if ($language === '') {
				$language = 'dansk';
			}
			$languageSql = db_escape_string($language);
			$r = db_fetch_array(db_select("SELECT xa, ya FROM formularer WHERE formular = 4 AND art = 0 AND lower(sprog) = '$languageSql'", __FILE__ . " linje " . __LINE__));
			if ($r) {
				$rows['fikLayout'] = array('accountLength' => (int)$r['ya'], 'invoiceLength' => (int)$r['xa']);
			} else {
				$r = db_fetch_array(db_select("SELECT beskrivelse FROM formularer WHERE formular = 4 AND beskrivelse LIKE '%betalingsid(%' AND lower(sprog) = '$languageSql'", __FILE__ . " linje " . __LINE__));
				if ($r && preg_match('/betalingsid\(\s*(\d+)\s*,\s*(\d+)\s*\)/', (string)$r['beskrivelse'], $m)) {
					$rows['fikLayout'] = array('accountLength' => (int)$m[1], 'invoiceLength' => (int)$m[2]);
				}
			}
		}

		return peppol_rows_to_utf8($rows, $rows['encoding']);
	}
}
