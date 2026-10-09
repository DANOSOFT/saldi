<?php
// 20260907 CDX/LH Preserve suggestion account types and match posted duplicates by currency and counterparty.
// 20261003 CL/SZ SD-722 sidste_5_forslag() for a kreditor searches posted entries first (openpost to transaktioner, journal and kreditor invoices), then journal lines.
//                A posted bilag that is also a journal line is shown once. Every row says where it came from (kilde: bogfort or kladde).

/**
 * Find recent counter-accounts without losing their customer/supplier/finance identity.
 * For a kreditor, posted entries come first and journal lines after them.
 *
 * @return array{
 *   heading: string,
 *   rows: array<array{bilag: mixed, dato: string, tekst: string, kontonr: mixed, art: string, kilde: string}>
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

	$kontonr_sql = db_escape_string($kontonr);
	$kladde_id_sql = (int)$kladde_id;
	if ($art == 'F') {
		$d_artcond = "(d_type = 'F' or d_type = '' or d_type is null)";
		$k_artcond = "(k_type = 'F' or k_type = '' or k_type is null)";
	} else {
		$d_artcond = "d_type = '$art'";
		$k_artcond = "k_type = '$art'";
	}
	if ($dk == "D") {
		$txt = "select bilag,kladde_id,transdate,beskrivelse,debet as kontonr,d_type as kontoart from kassekladde where $k_artcond and kredit = '$kontonr_sql' and kladde_id != '$kladde_id_sql' order by transdate desc,id desc";
	} else {
		$txt = "select bilag,kladde_id,transdate,beskrivelse,kredit as kontonr,k_type as kontoart from kassekladde where $d_artcond and debet = '$kontonr_sql' and kladde_id != '$kladde_id_sql' order by transdate desc,id desc";
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

	$toUtf8 = function ($tekst) use ($charset) {
		$tekst = stripslashes((string)$tekst);
		if ($charset && strtoupper($charset) != 'UTF-8' && function_exists('mb_convert_encoding')) {
			$tekst = mb_convert_encoding($tekst, 'UTF-8', $charset);
		}
		return $tekst;
	};
	// Posted bilag already shown, so its journal line is not shown again
	$postedBilag = array();
	if ($art == 'K') {
		foreach (sidste_5_bogfoerte_kreditor($kontonr, $dk) as $r) {
			$postedBilag[(int)$r['kladde_id'] . '|' . (int)$r['bilag']] = true;
			if (count($forslag['rows']) < 5) {
				$forslag['rows'][] = array(
					'bilag' => $r['bilag'],
					'dato' => dkdato($r['transdate']),
					'tekst' => $toUtf8($r['beskrivelse']),
					'kontonr' => $r['kontonr'],
					'art' => 'F',
					'kilde' => 'bogfort'
				);
			}
		}
	}

	$q = count($forslag['rows']) < 5 ? db_select($txt, __FILE__ . " linje " . __LINE__) : false;
	while ($q && count($forslag['rows']) < 5 && ($r = db_fetch_array($q))) {
		$counterType = strtoupper(trim((string)$r['kontoart'])) ?: 'F';
		if (isset($postedBilag[(int)$r['kladde_id'] . '|' . (int)$r['bilag']])) continue;
		if ($r['kontonr'] && in_array($counterType, array('D', 'K', 'F'), true)) {
			$forslag['rows'][] = array(
				'bilag' => $r['bilag'],
				'dato' => dkdato($r['transdate']),
				'tekst' => $toUtf8($r['beskrivelse']),
				'kontonr' => $r['kontonr'],
				'art' => $counterType,
				'kilde' => 'kladde'
			);
		}
	}

	return $forslag;
}
##########################################################################################################
/**
 * The finance accounts a kreditor's posted bilag went to, newest first: the kreditor's open posts (invoices for Debet,
 * credit notes and payments for Kredit) joined to the bilag's lines in transaktioner. A journal bilag is found by
 * kladde_id and bilag, a kreditor invoice by its order (kladde_id 0, refnr = ordre_id).
 * The VAT accounts and the debitor/kreditor collective accounts are left out: they are not what the bilag was spent on.
 *
 * @param string|int $kontonr Kreditor account number.
 * @param string     $dk      'D' for the Debet side (kreditor in Kredit), 'K' for the Kredit side.
 * @param int        $limit   Open posts to look at.
 * @return array<array{kontonr: mixed, transdate: string, beskrivelse: string, bilag: mixed, kladde_id: mixed}>
 */
function sidste_5_bogfoerte_kreditor($kontonr, $dk, $limit = 30)
{
	// The journal asks once per line, often for the same kreditor
	static $cache = array();
	static $skip = null;
	$rows = array();
	if (!is_numeric($kontonr)) return $rows;
	$key = $kontonr . '|' . $dk . '|' . (int)$limit;
	if (isset($cache[$key])) return $cache[$key];
	if ($skip === null) {
		$skip = array();
		$q = db_select("select art, box1, box2, box3 from grupper where art in ('SM','KM','EM','YM','KG','DG')", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$accounts = in_array($r['art'], array('KG', 'DG'), true) ? array($r['box2']) : array($r['box1'], $r['box3']);
			foreach ($accounts as $account) {
				if (ctype_digit(trim((string)$account))) $skip[(int)$account] = true;
			}
		}
	}
	$side = $dk == 'D' ? 'debet' : 'kredit';
	$sign = $dk == 'D' ? 'o.amount < 0' : 'o.amount > 0';
	$qtxt = "select t.kontonr, t.transdate, t.beskrivelse, t.bilag, t.kladde_id from ";
	$qtxt .= "(select o.id, o.transdate, o.kladde_id, o.refnr from openpost o join adresser a on a.id = o.konto_id ";
	$qtxt .= "where a.art = 'K' and a.kontonr = '" . db_escape_string($kontonr) . "' and $sign order by o.transdate desc, o.id desc limit " . (int)$limit . ") o ";
	$qtxt .= "join transaktioner t on t.transdate = o.transdate and ((o.kladde_id > 0 and t.kladde_id = o.kladde_id and t.bilag = o.refnr) ";
	$qtxt .= "or (coalesce(o.kladde_id, 0) = 0 and t.ordre_id = o.refnr)) ";
	$qtxt .= "where t.$side > 0";
	if ($skip) $qtxt .= " and t.kontonr not in (" . implode(',', array_keys($skip)) . ")";
	$qtxt .= " order by t.transdate desc, t.id desc";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ((int)$r['kontonr']) $rows[] = $r;
	}
	$cache[$key] = $rows;
	return $rows;
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
