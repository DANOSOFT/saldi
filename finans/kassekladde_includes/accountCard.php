<?php
// 20260929 CL/SZ SD-698: Resolve the page behind a journal account number, for the card button in the account
//                lookup popup and the account numbers in a posted journal (both go through openAccountCard.php).

/**
 * Build the URL of the page behind an account number in a journal line (SD-698):
 * a debtor opens the debitorkort, a creditor the kreditorkort and a finance account its
 * kontospec for the fiscal year. The URL is relative to the finans/ folder.
 *
 * @param string     $account_type  Journal line type: 'F', 'D' or 'K' ('' counts as 'F').
 * @param string|int $account_no    Account number from the line (kontonr).
 * @param int        $regnaar       Active fiscal year (kontoplan.regnskabsaar).
 * @param string     $returside     Page the opened card's Tilbage goes back to.
 * @return string  URL that carries $returside, or '' when the number is no known account.
 */
function kk_account_card_url($account_type, $account_no, $regnaar, $returside) {
	$account_type = is_scalar($account_type) ? strtoupper(trim((string)$account_type)) : '';
	$account_no   = is_scalar($account_no) ? trim((string)$account_no) : '';
	if ($account_type === '') $account_type = 'F';
	if (!in_array($account_type, array('F', 'D', 'K'), true) || !ctype_digit($account_no)) {
		return '';
	}
	$regnaar = (int)$regnaar;
	$url = '';
	$account_sql = db_escape_string($account_no);
	if ($account_type === 'F') {
		$qtxt = "select kontonr from kontoplan where kontonr = '$account_sql' and regnskabsaar = '$regnaar'";
		if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			// no month: kontospec shows the whole fiscal year
			$url = "../finans/kontospec.php?" . http_build_query(array(
				'kontonr'   => $account_no,
				'returside' => $returside,
			));
		}
	} else {
		$qtxt = "select id from adresser where kontonr = '$account_sql' and art = '$account_type' order by id limit 1";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$card = ($account_type === 'D') ? '../debitor/debitorkort.php' : '../kreditor/kreditorkort.php';
			// the cards read ordre_id/fokus unguarded when a returside is given
			$url = $card . "?" . http_build_query(array(
				'id'        => (int)$r['id'],
				'ordre_id'  => '',
				'fokus'     => '',
				'returside' => $returside,
			));
		}
	}
	return $url;
}

/**
 * Link from a journal page to openAccountCard.php, which resolves the account on click, so
 * rendering a journal needs no lookup per line. Relative to the finans/ folder.
 *
 * @param string     $account_type  Journal line type: 'F', 'D' or 'K' ('' counts as 'F').
 * @param string|int $account_no    Account number from the line (kontonr).
 * @param int        $kladde_id     Journal the opened page returns to.
 * @return string  URL, or '' when the value cannot be an F/D/K account number.
 */
function kk_account_card_link($account_type, $account_no, $kladde_id) {
	$account_type = is_scalar($account_type) ? strtoupper(trim((string)$account_type)) : '';
	$account_no   = is_scalar($account_no) ? trim((string)$account_no) : '';
	if ($account_type === '') $account_type = 'F';
	if (!in_array($account_type, array('F', 'D', 'K'), true) || !ctype_digit($account_no)) {
		return '';
	}
	return "kassekladde_includes/openAccountCard.php?" . http_build_query(array(
		'art'       => $account_type,
		'kontonr'   => $account_no,
		'kladde_id' => (int)$kladde_id,
	));
}
