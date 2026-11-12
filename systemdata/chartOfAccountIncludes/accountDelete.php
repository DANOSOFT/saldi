<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/chartOfAccountIncludes/accountDelete.php --- ver 5.1.1 --- 2026-11-12 ---
//                           LICENSE
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
// 20261112 CL/MMR SST-1012 Created: the delete check and the delete action of the account card (systemdata/kontokort.php).
//                  The year check includes the first and last day of the fiscal year.

if (!function_exists('accountHasPostingsInYear')) {
	/**
	 * Whether an account has postings in the given fiscal year, first and last day included.
	 *
	 * @param int $kontonr Account number
	 * @param int $regnaar Fiscal year (grupper art RA kodenr)
	 * @return bool
	 */
	function accountHasPostingsInYear($kontonr, $regnaar) {
		$kontonr = (int)$kontonr;
		$regnaar = (int)$regnaar;
		$r = db_fetch_array(db_select("select * from grupper where kodenr='$regnaar' and art='RA'", __FILE__ . " linje " . __LINE__));
		if (!$r) {
			return false;
		}
		$startmaaned = (int)$r['box1'];
		$startaar = (int)$r['box2'];
		$slutmaaned = (int)$r['box3'];
		$slutaar = (int)$r['box4'];
		$slutdato = 31;
		while (!checkdate($slutmaaned, $slutdato, $slutaar)) {
			$slutdato = $slutdato - 1;
			if ($slutdato < 28) {
				break 1;
			}
		}
		$regnstart = $startaar . "-" . $startmaaned . "-" . '01';
		$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
		$qtxt = "select id from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' ";
		$qtxt .= "and kontonr='$kontonr'";
		return (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	}
}

if (!function_exists('deleteAccountAction')) {
	/**
	 * The «Slet» action of the account card: deletes the account unless it has postings in the fiscal year,
	 * and picks the account the card shows next.
	 *
	 * @param int $id            kontoplan.id of the account to delete
	 * @param int $postedKontonr The account number the form posted (used to pick the next account, as before)
	 * @param int $regnaar       Fiscal year
	 * @return array{deleted: bool, refusedKontonr: int, nextId: int}
	 */
	function deleteAccountAction($id, $postedKontonr, $regnaar) {
		$id = (int)$id;
		$postedKontonr = (int)$postedKontonr;
		$regnaar = (int)$regnaar;
		$result = array('deleted' => false, 'refusedKontonr' => 0, 'nextId' => 0);

		// The button is only shown for an account without postings, but check again: the form may be old or hand-made.
		$r = db_fetch_array(db_select("select kontonr from kontoplan where id = $id", __FILE__ . " linje " . __LINE__));
		if ($r && accountHasPostingsInYear($r['kontonr'], $regnaar)) {
			$result['refusedKontonr'] = (int)$r['kontonr'];
		} else {
			db_modify("delete from kontoplan where id = $id", __FILE__ . " linje " . __LINE__);
			$result['deleted'] = true;
		}
		$qtxt = "select id from kontoplan where kontonr >= $postedKontonr and regnskabsaar = '$regnaar' order by kontonr";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$result['nextId'] = (int)$r['id'];
		}
		return $result;
	}
}
