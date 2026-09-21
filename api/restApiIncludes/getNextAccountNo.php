<?php
//          ___   _   _   ___  _     ___  _ _
//         / __| / \ | | |   \| |   |   \| / /
//         \__ \/ _ \| |_| |) | | _ | |) |  <
//         |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/api/restApiIncludes/getNextAccountNo --- lap 4.0.5 --- 2022-03-09 ---
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
//
// Copyright (c) 2016-2022 saldi.dk aps
// ----------------------------------------------------------------------

// 20260920 CDX/LH Allocate within validated bounds and report exhausted account ranges.
/** @return int|string Next account number, or an actionable validation error. */
function getNextAccountNo($accountType) {
	$accountType = $accountType ?: ifset($_GET, 'accountType', 'D');
	if (!in_array($accountType, array('D', 'K'), true)) {
		return 'Invalid accountType; use D or K';
	}
	$minNo = ifset($_GET, 'minNo');
	$maxNo = ifset($_GET, 'maxNo');
	$minNo = ($minNo === null || $minNo === '' || $minNo === '0') ? '1' : (string)$minNo;
	$maxNo = ($maxNo === null || $maxNo === '' || $maxNo === '0') ? '999999' : (string)$maxNo;
	foreach (array($minNo, $maxNo) as $bound) {
		if (!preg_match('/^[0-9]+$/D', $bound) || filter_var(ltrim($bound, '0'), FILTER_VALIDATE_INT) === false || (int)$bound < 1) {
			return 'Invalid account number range; use positive whole numbers';
		}
	}
	$minNo = (int)$minNo;
	$maxNo = (int)$maxNo;
	if ($minNo > $maxNo) {
		return 'Invalid account number range; minNo exceeds maxNo';
	}
	$next = $minNo;
	$q = db_select("SELECT kontonr FROM adresser WHERE art='$accountType'", __FILE__ . ' linje ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$number = trim((string)$row['kontonr']);
		if (preg_match('/^[0-9]+$/D', $number) && filter_var(ltrim($number, '0'), FILTER_VALIDATE_INT) !== false) {
			$number = (int)$number;
			if ($number >= $minNo && $number <= $maxNo && $number >= $next) {
				if ($number === $maxNo) {
					return "No available account number in range $minNo-$maxNo";
				}
				$next = $number + 1;
			}
		}
	}
	return $next;
}
