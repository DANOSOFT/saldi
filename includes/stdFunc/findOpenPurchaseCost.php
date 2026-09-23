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
// 20260921 MJ SST-796 Added find_open_purchase_cost(): the real purchase price for an item that
//                  is being sold off negative stock, taken from an open, unreceived kreditorordre
//                  line. linjeopdat()'s deficit fallback used the static varer.kostpris instead,
//                  which at Den-Tec was three years stale - a machine bought at EUR 18.500 (kurs
//                  7,50 = 138.750 kr) invoiced out at a cost price of 88.100,93 kr.
// 20260923 MJ SST-796 Added deficit_cost_price(): linjeopdat() has two branches that price a
//                  quantity no batch_kob covers - the normal-sale one and the negative/credit-note
//                  one - and both read varer.kostpris. Sharing one resolver keeps them from
//                  disagreeing, which is what they did when only the first was fixed.

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
	 * Newest qualifying line wins, which is deliberately the opposite of the FIFO consumption loop
	 * in linjeopdat() just above the caller. That loop spends real stock, where FIFO is the correct
	 * valuation. This function is not valuing stock - there is none, the item is on negative
	 * beholdning - it is estimating what the quantity will cost once an incoming order covers it, so
	 * the most recently ordered price is the closest estimate available. Den-Tec's case is exactly
	 * that shape: the machine had just been bought, and the stale figure was the old one.
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

if (!function_exists('deficit_cost_price')) {
	/**
	 * Cost price for a quantity that no batch_kob covers, for both of linjeopdat()'s deficit paths.
	 *
	 * Prefers an open purchase line (see find_open_purchase_cost()) and falls back to the static
	 * varer.kostpris. Either way the choice is written to the order log so a stale cost price can be
	 * traced afterwards instead of first surfacing on a customer's invoice, and the user is warned
	 * once per delivery - not once per line, so a large order does not produce a row of dialogs.
	 *
	 * Both callers reach their deficit branch only when there is no purchase reference to inherit a
	 * cost from: the negative/credit-note branch zeroes its remainder whenever $kred_linje_id is set,
	 * so a return booked against a specific original sale line never lands here. That is why an open
	 * purchase line is the better estimate in both places and not just on the sale path.
	 *
	 * @param int|string   $vare_id  The item.
	 * @param int|string   $linje_id The order line being priced, for the log only.
	 * @param int|string   $lager    Warehouse, 0 for any.
	 * @param resource|null $fp      Open order log handle, or NULL when the caller has none.
	 * @param int|string   $sprog_id Language for the warning text.
	 * @param string       $kontekst Short label naming the calling branch, for the log.
	 * @param bool|string  $webservice Truthy on an API/SOAP delivery, where the HTML warning must be
	 *                            suppressed: those callers emit JSON or XML, and printed markup would
	 *                            precede and invalidate the response. The log line is still written,
	 *                            since that is where an API caller can see what happened at all.
	 * @return float The cost price, in base currency.
	 */
	function deficit_cost_price($vare_id, $linje_id, $lager, $fp, $sprog_id, $kontekst, $webservice = false)
	{
		// One warning per request. A delivery is a single request, so this is "once per delivery".
		static $advaret = 0;

		$kostkilde = find_open_purchase_cost($vare_id, $lager);
		if ($kostkilde) {
			$kostpris      = $kostkilde['pris'];
			$kostkilde_txt = "open purchase line " . $kostkilde['linje_id'] . " on order " . $kostkilde['ordrenr'];
		} else {
			$r             = db_fetch_array(db_select("select kostpris from varer where id = '" . (int) $vare_id . "'", __FILE__ . " linje " . __LINE__));
			$kostpris      = $r['kostpris'] * 1;
			$kostkilde_txt = "varer.kostpris";
		}

		if ($fp) {
			fwrite($fp, date("Y-m-d H:i:s") . " SST-796 deficit cost price ($kontekst): vare_id $vare_id, linje_id $linje_id, source $kostkilde_txt, pris $kostpris\n");
		}

		// Non-blocking: the delivery goes through either way, but the user is told the cost price was
		// estimated rather than taken from stock. Same alert idiom linjeopdat() already uses for its
		// serial-number warning. There is nobody to show a dialog to on an API delivery, and the
		// markup would corrupt the JSON/XML response, so the flag is left untouched as well - a later
		// interactive delivery in the same request still gets its one warning.
		if (!$webservice && !$advaret) {
			$advaret = 1;
			$txt = $kostkilde
				? findtekst('5241|Kostprisen er anslået ud fra en åben indkøbsordre, da varen ikke var på lager', $sprog_id)
				: findtekst('5242|Kostprisen er anslået ud fra varekortet, da varen hverken var på lager eller på en åben indkøbsordre', $sprog_id);
			print "<BODY onLoad=\"javascript:alert('" . str_replace("'", "\\'", $txt) . "')\">";
		}

		return $kostpris;
	}
}
?>
