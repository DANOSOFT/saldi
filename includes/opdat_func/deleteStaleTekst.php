<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/deleteStaleTekst.php --- patch 5.0.0 --- 2026-09-30 ---
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
// Copyright (c) 2026 Danosoft.ApS
// ----------------------------------------------------------------------
// 20260930 CL/NTR Created from the repeated tekster clean-ups in betweenUpdates.php.

if (!function_exists('deleteStaleTekst')) {
	/**
	 * Deletes tekster rows that still hold an old text, so findtekst() re-seeds them from
	 * importfiler/tekster.csv on the next call.
	 *
	 * findtekst() always prefers an existing database row over tekster.csv, so a reworded text in
	 * the csv never reaches a tenant that already has the old row. Only rows whose text matches an
	 * old value are deleted; texts a customer has edited are left alone. That guard matters because
	 * betweenUpdates.php runs at every login.
	 *
	 * @param int|array{0: int, 1: int} $tekstId    One tekst_id, or [from, to] for an inclusive range of ids.
	 * @param string|string[]           $oldTexts   One old text or a list of them. Matched exactly, or as a
	 *                                              start-of-text match when $prefix is true.
	 * @param int|null                  $sprogId    Language (1 = da, 2 = en, 3 = no). Null matches every language.
	 * @param bool                      $prefix     True to match rows whose text starts with one of $oldTexts.
	 * @return void
	 */
	function deleteStaleTekst($tekstId, $oldTexts, $sprogId = null, $prefix = false) {
		if (is_string($oldTexts)) {
			$oldTexts = array($oldTexts);
		}
		if (!is_array($oldTexts) || !$oldTexts) {
			return;
		}
		if (is_array($tekstId)) {
			$where = "tekst_id between '" . intval($tekstId[0]) . "' and '" . intval($tekstId[1]) . "'";
		} else {
			$where = "tekst_id = '" . intval($tekstId) . "'";
		}
		if ($sprogId !== null) {
			$where .= " and sprog_id = '" . intval($sprogId) . "'";
		}
		$matches = array();
		foreach ($oldTexts as $oldText) {
			$escaped = db_escape_string($oldText);
			// left() compares a fixed number of characters, so the text needs no LIKE-wildcard escaping.
			$matches[] = $prefix ? "left(tekst, " . mb_strlen($oldText, 'UTF-8') . ") = '$escaped'" : "tekst = '$escaped'";
		}
		db_modify("delete from tekster where $where and (" . implode(" or ", $matches) . ")", __FILE__ . " linje " . __LINE__);
	}
}
