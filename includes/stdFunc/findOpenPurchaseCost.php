<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/findOpenPurchaseCost.php --- patch 5.0.0 --- 2026-09-21 ---
// LICENS
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
// Copyright (c) 2003-2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260921 CDX/MJ SST-796 Added find_open_purchase_cost(): the real purchase price for an item that
//                  is being sold off negative stock, taken from an open, unreceived kreditorordre
//                  line. linjeopdat()'s deficit fallback used the static varer.kostpris instead,
//                  which at Den-Tec was three years stale - a machine bought at EUR 18.500 (kurs
//                  7,50 = 138.750 kr) invoiced out at a cost price of 88.100,93 kr.

if (!function_exists('find_open_purchase_cost')) {
	/**
	 * Cost price for $vare_id from the newest open, unreceived purchase order line, in base currency.
	 *
	 * "Open" is defined exactly as lager/modtagelse.php:254 defines it for the goods-receipt screen -
	 * ordrer.art = 'KO' and status 1 or 2 - so this cannot disagree with what a user sees as
	 * receivable. A line counts only while it still has quantity outstanding, either because it has
	 * not been fully delivered (leveret < antal) or because a receipt is queued for it in
	 * modtagelser.
	 *
	 * The conversion mirrors includes/ordrefunc.php:1925, the path a real receipt takes:
	 * (pris - pris * rabat / 100) * valutakurs / 100. Note that line then converts back into the
	 * sales order's currency; this one deliberately does not, because the fallback it feeds writes
	 * varer.kostpris unconverted, so ordrelinjer.kostpris is a base-currency figure there.
	 *
	 * @param int|string $vare_id The item being sold.
	 * @param int|string $lager   Warehouse, 0 for any. Purchase lines carry no warehouse of their
	 *                            own, so this only narrows the modtagelser check.
	 * @return array|null ['pris' => float, 'ordre_id' => int, 'linje_id' => int, 'ordrenr' => string]
	 *                    or null when no open purchase line covers this item.
	 */
	function find_open_purchase_cost($vare_id, $lager = 0)
	{
		$vare_id = (int) $vare_id;
		if (!$vare_id) {
			return NULL;
		}

		$qtxt  = "select ol.id as linje_id, ol.ordre_id, ol.pris, ol.rabat, ol.antal, ol.leveret, ";
		$qtxt .= "o.valutakurs, o.ordrenr ";
		$qtxt .= "from ordrelinjer ol, ordrer o ";
		$qtxt .= "where ol.vare_id = '$vare_id' and ol.ordre_id = o.id ";
		$qtxt .= "and o.art = 'KO' and (o.status = '1' or o.status = '2') ";
		$qtxt .= "order by o.ordredate desc, ol.id desc";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

		while ($r = db_fetch_array($q)) {
			$antal   = (float) $r['antal'];
			$leveret = (float) $r['leveret'];
			$udestaaende = $antal - $leveret;
			if ($udestaaende <= 0) {
				// Fully delivered on the line itself, but a receipt may still be queued for it.
				$linje_id = (int) $r['linje_id'];
				$mqtxt  = "select sum(antal) as antal from modtagelser where vare_id = '$vare_id' ";
				$mqtxt .= "and ordre_id = '" . (int) $r['ordre_id'] . "'";
				if ($lager) {
					$mqtxt .= " and lager = '" . (int) $lager . "'";
				}
				$m = db_fetch_array(db_select($mqtxt, __FILE__ . " linje " . __LINE__));
				if (!$m || (float) $m['antal'] <= 0) {
					continue;
				}
			}

			$pris = (float) $r['pris'];
			$pris -= $pris * (float) $r['rabat'] / 100;
			$valutakurs = (float) $r['valutakurs'];
			if (!$valutakurs) {
				$valutakurs = 100;
			}
			$pris = $pris * $valutakurs / 100;

			if (!$pris) {
				continue;   // a zero-priced line tells us nothing; keep looking
			}

			return array(
				'pris'     => $pris,
				'ordre_id' => (int) $r['ordre_id'],
				'linje_id' => (int) $r['linje_id'],
				'ordrenr'  => $r['ordrenr'],
			);
		}
		return NULL;
	}
}
?>
