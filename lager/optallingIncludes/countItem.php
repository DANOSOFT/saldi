<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/optallingIncludes/countItem.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261112 CL/MMR SST-1014 Created the item lookup and variant check that the stock count's guard and recount (lager/optalling.php) share.

if (!function_exists('countItemLookup')) {
	/**
	 * The item the stock count recounts for an entered or linked number: an exact item number or barcode first,
	 * else the same comparison ignoring case (as optalling.php has done since 20140103).
	 *
	 * @param string $varenr Item number or barcode, already trimmed
	 * @return array<string, mixed>|false The varer row, or false when no item matches
	 */
	function countItemLookup($varenr) {
		$varenr = (string)$varenr;
		if ($varenr === '') {
			return false;
		}
		$v = db_escape_string($varenr);
		$r = db_fetch_array(db_select("select * from varer where varenr='$v' or stregkode='$v'", __FILE__ . " linje " . __LINE__));
		if (!$r) {
			$lower = db_escape_string(strtolower($varenr));
			$upper = db_escape_string(strtoupper($varenr));
			$qtxt = "select * from varer where lower(varenr)='$lower' or lower(stregkode)='$lower' or upper(varenr)='$upper' or upper(stregkode)='$upper'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		}
		return $r;
	}
}

if (!function_exists('countItemHasVariants')) {
	/**
	 * Whether an item has variants. Such items are counted on the item card or by CSV import, never here (20230224).
	 *
	 * @param int $vareId varer.id
	 * @return bool
	 */
	function countItemHasVariants($vareId) {
		$vareId = (int)$vareId;
		return (bool)db_fetch_array(db_select("select id from variant_varer where vare_id='$vareId' limit 1", __FILE__ . " linje " . __LINE__));
	}
}
