<?php
// 20260907 CDX/LH Preserve suggestion account types and match posted duplicates by currency and counterparty.
// 20261001 CDX/LH Extract typed counter rows with optional posted-only, date and VAT evidence.
// 20261001 CDX/LH Carry VAT exemption alongside the counter-account override.

/**
 * Load the web's counter rows in their original order; zero limit means unbounded.
 * The selected tenant connection comes from online.php or LegacyEngine's context.
 *
 * @return list<array{bilag:mixed,transdate:string,beskrivelse:string,kontonr:mixed,kontoart:?string,vat:?string,momsfri:?string}>
 */
function journal_counter_rows($kontonr, string $art, string $dk, int $excludeKladdeId, bool $postedOnly, int $limit, ?string $since): array
{
	$art = strtoupper(trim($art)) ?: 'F';
	if (!is_numeric($kontonr) || !in_array($art, ['D', 'K', 'F'], true)) {
		return [];
	}
	$counter = $dk === 'D' ? 'debet' : 'kredit';
	$counterType = $dk === 'D' ? 'd_type' : 'k_type';
	$counterVat = $dk === 'D' ? 'debetvat' : 'kreditvat';
	$party = $dk === 'D' ? 'kredit' : 'debet';
	$partyType = $dk === 'D' ? 'k_type' : 'd_type';
	$typeCondition = $art === 'F'
		? "(k.$partyType = 'F' OR k.$partyType = '' OR k.$partyType IS NULL)"
		: "k.$partyType = '$art'";
	$sql = "SELECT k.bilag,k.transdate,k.beskrivelse,k.$counter AS kontonr,k.$counterType AS kontoart,k.$counterVat AS vat,k.momsfri FROM kassekladde k";
	if ($postedOnly) {
		$sql .= " JOIN kladdeliste j ON j.id=k.kladde_id AND j.bogfort='V'";
	}
	$sql .= " WHERE $typeCondition AND k.$party='" . db_escape_string($kontonr) . "' AND k.kladde_id != " . (int)$excludeKladdeId;
	if ($since !== null) {
		$sql .= " AND k.transdate >= '" . db_escape_string($since) . "'";
	}
	$sql .= ' ORDER BY k.transdate DESC,k.id DESC';
	if ($limit > 0) {
		$sql .= ' LIMIT ' . (int)$limit;
	}
	$result = db_select($sql, __FILE__ . ' line ' . __LINE__);
	$rows = [];
	while ($row = db_fetch_array($result)) {
		$rows[] = $row;
	}
	return $rows;
}

/**
 * Find recent counter-accounts without losing their customer/supplier/finance identity.
 *
 * @return array{
 *   heading: string,
 *   rows: array<array{bilag: mixed, dato: string, tekst: string, kontonr: mixed, art: string}>
 * }
 */
function sidste_5_forslag($kontonr, $art, $dk, $kladde_id, $charset, $sprog_id)
{
	$forslag = array(
		'heading' => '',
		'rows' => array()
	);

	if (!is_numeric($kontonr)) {
		return $forslag;
	}
	$art = strtoupper(trim((string)$art)) ?: 'F';
	if (!in_array($art, array('D', 'K', 'F'), true)) {
		return $forslag;
	}


	if ($art == 'K') {
		$forslag['heading'] = "Sidste 5 posteringer for kreditor: $kontonr";
	} elseif ($art == 'D') {
		$forslag['heading'] = "Sidste 5 posteringer for debitor: $kontonr";
	} else {
		$heading = findtekst('5140|Sidste 5 posteringer for konto', $sprog_id) . ": $kontonr";
		if ($charset && strtoupper($charset) != 'UTF-8' && function_exists('mb_convert_encoding')) {
			$heading = mb_convert_encoding($heading, 'UTF-8', $charset);
		}
		$forslag['heading'] = $heading;
	}

	foreach (journal_counter_rows($kontonr, $art, (string)$dk, (int)$kladde_id, false, 0, null) as $r) {
		if (count($forslag['rows']) >= 5) {
			break;
		}
		$counterType = strtoupper(trim((string)$r['kontoart'])) ?: 'F';
		if ($r['kontonr'] && in_array($counterType, array('D', 'K', 'F'), true)) {
			$tekst = stripslashes($r['beskrivelse']);
			if ($charset && strtoupper($charset) != 'UTF-8' && function_exists('mb_convert_encoding')) {
				$tekst = mb_convert_encoding($tekst, 'UTF-8', $charset);
			}
			$forslag['rows'][] = array(
				'bilag' => $r['bilag'],
				'dato' => dkdato($r['transdate']),
				'tekst' => $tekst,
				'kontonr' => $r['kontonr'],
				'art' => $counterType
			);
		}
	}

	return $forslag;
}
##########################################################################################################
/** @return string HTML attribute containing the historical counter-accounts. */
function sidste_5_forslag_attr($kontonr, $art, $dk, $charset, $kladde_id, $sprog_id)
{
	$forslag = sidste_5_forslag($kontonr, $art, $dk, $kladde_id, $charset, $sprog_id);
	if (!count($forslag['rows'])) {
		return '';
	}

	$json = json_encode($forslag);
	if ($json === false) {
		return '';
	}
	return " data-last-postings=\"" . htmlspecialchars($json, ENT_QUOTES, $charset) . "\"";
}
##########################################################################################################
/**
 * Match draft rows in their entered currency and posted rows in base currency.
 *
 * @return string "bilag,kladde_id,kilde", where kilde is kladde or bogfort; "0,0,0" when absent.
 */
function find_dublet($id, $transdate, $d_type, $debet, $k_type, $kredit, $amount, $faktura, $baseAmount, $regnstart, $regnslut) {
	if ($id) {
		$id = (int)$id;
		$transdate_sql = db_escape_string($transdate);
		$d_type_sql    = db_escape_string($d_type);
		$debet_sql     = db_escape_string($debet);
		$k_type_sql    = db_escape_string($k_type);
		$kredit_sql    = db_escape_string($kredit);
		$amount_sql    = db_escape_string($amount);
		$faktura_sql   = db_escape_string($faktura);
		$qtxt = "select bilag,kladde_id from kassekladde where transdate='$transdate_sql' and d_type='$d_type_sql' ";
		$qtxt.= "and debet='$debet_sql' and k_type='$k_type_sql' and kredit='$kredit_sql' and amount = '$amount_sql' ";
		$qtxt.= "and faktura = '$faktura_sql' and id!='$id' limit 1";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			return ($r['bilag'] . "," . $r['kladde_id'] . ",kladde");
		}
		# Ingen dublet i aabne kladder - slaa op blandt bogfoerte posteringer. En bogfoert linje ligger
		# som separate debet-/kreditraekker i transaktioner (samme bilag+kladde_id), og momsbaerende
		# sider er gemt ekskl. moms, saa bruttobeloebet matches mod mindst een af de to sider.
		# D/K-konti kræver en tilsvarende openpost for den konkrete kunde/leverandør.
		# Beløb i transaktioner er i basisvaluta, mens kassekladde bruger bilagets valuta.
		$base_amount_sql = number_format((float)$baseAmount, 2, '.', '');
		$sider = array();
		foreach (array(array('t1', $d_type, $debet, 1), array('t2', $k_type, $kredit, -1)) as $side) {
			list($alias, $type, $account, $sign) = $side;
			$type = strtoupper(trim((string)$type)) ?: 'F';
			if ($type === 'F') {
				$sider[] = "$alias.kontonr='" . (int)$account . "'";
			} elseif ($type === 'D' || $type === 'K') {
				$account_sql = db_escape_string($account);
				$signed_amount_sql = number_format((float)$baseAmount * $sign, 2, '.', '');
				$sider[] = "exists (select 1 from openpost o join adresser a on a.id=o.konto_id "
					. "where a.art='$type' and a.kontonr='$account_sql' "
					. "and o.kladde_id=$alias.kladde_id and o.refnr=$alias.bilag "
					. "and o.transdate=$alias.transdate and o.faktnr='$faktura_sql' "
					. "and round(o.amount * coalesce(nullif(o.valutakurs,0),100) / 100,2)=$signed_amount_sql)";
			} else {
				return "0,0,0";
			}
		}
		if (count($sider) && $regnstart && $regnslut) {
			$regnstart_sql = db_escape_string($regnstart);
			$regnslut_sql  = db_escape_string($regnslut);
			$qtxt = "select t1.bilag from transaktioner t1, transaktioner t2 ";
			$qtxt.= "where t1.transdate='$transdate_sql' and t2.transdate='$transdate_sql' ";
			$qtxt.= "and t1.transdate>='$regnstart_sql' and t1.transdate<='$regnslut_sql' ";
			$qtxt.= "and t1.kladde_id=t2.kladde_id and t1.bilag=t2.bilag and t1.id!=t2.id ";
			$qtxt.= "and t1.debet>0 and t2.kredit>0 and (t1.debet='$base_amount_sql' or t2.kredit='$base_amount_sql') ";
			$qtxt.= "and t1.faktura='$faktura_sql' and t2.faktura='$faktura_sql' and " . implode(' and ', $sider) . " limit 1";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				return ($r['bilag'] . ",0,bogfort");
			}
		}
	}
	return ("0,0,0");
}
