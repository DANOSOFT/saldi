<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//--- includes/orderFuncIncludes/commissionSettlement.php ---ver 5.1.1 ----2026-11-13 ---
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
// 20261113 CDX/NTR SD-599: Extract the disabled commission entry point into its dedicated module.

if (!function_exists('settleCommision')) {
	/**
	 * Preserve the disabled legacy commission-settlement entry point.
	 *
	 * @param int|string $idOrder Order id.
	 * @return void
	 */
	function settleCommision($idOrder)
	{
		return;
		exit;
		global $db;
		$customer = array();
		$x = 0;
		$qtxt = "select * from ordrelinjer where ordre_id='$idOrder' and fast_db > 0 and (varenr like 'kb%' or varenr like 'kn%')";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			(is_numeric(substr($r['varenr'], 5))) ? $tmp = substr($r['varenr'], 5) : $tmp = NULL;
			if ($tmp) {
				if (in_array($tmp, $kundenr)) {
					for ($k = 0; $k < count($kundenr); $k++) {
						if ($kundenr[$k] == $tmp) {
							$amnt = $r['pris'] * $r['antal'] - ($r['pris'] * $r['antal'] * $r['rabat']);
							$amount[$k] += $amnt;
							$commission[$k] += $amnt - ($amnt * $r['fast_db']);
						}
					}
				} else {
					$kundenr[$x] = $tmp;
					$amount[$x] = $r['pris'] * $r['antal'] - ($r['pris'] * $r['antal'] * $r['rabat'] / 100);
					$commission[$x] = $amount[$x] * $r['fast_db'];
					$x++;
				}
			}
		}
		for ($k = 0; $k < count($kundenr); $k++) {
		}
	}
}
