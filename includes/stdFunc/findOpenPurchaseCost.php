<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/findOpenPurchaseCost.php --- ver 5.1.0 --- 2026-10-29 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
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
// 20260923 MJ SST-796 Open-line eligibility now follows lager/modtagelse.php exactly - antal less
//                  the batch_kob booked against the line - instead of ordrelinjer.leveret, which the
//                  receipt path never writes on a kreditorordre. Also matches on variant, as two
//                  variants of one item can be bought at different prices.
// 20261028 CL/MMR SST-907 deficit_cost_price() no longer shows the cost-price alert on a POS sale (ordrer.art 'PO').
//                 The alert sat in the same page as the receipt redirect, so the receipt only printed after OK, on every sale off negative stock.
//                 The cost price and the order log line are unchanged.
// 20261029 CL/MMR SD-786 The cost-price message is off unless "Vis besked når kostprisen er anslået" is ticked under Ordrerelaterede valg.
//                 When on, it is only shown on an interactive delivery of an order (not POS, webshop/API or a negative line/credit note),
//                 and only when the price came from the item card or from an open purchase line that differs from the item card.
//                 It names each item, the price and where the price came from, all lines of a delivery in one message.
//                 The cost price and the order log line are unchanged.

if (!function_exists('find_open_purchase_cost')) {
	/**
	 * Cost price for $vare_id from the newest open, unreceived purchase order line, in base currency.
	 *
	 * "Open" is defined exactly as lager/modtagelse.php defines it for the goods-receipt screen, so
	 * this cannot disagree with what a user sees as receivable: ordrer.art = 'KO' with status 1 or 2
	 * (:254), and a line still outstanding by that screen's own measure - ordrelinjer.antal less the
	 * batch_kob rows already booked against the line (:258-261).
	 *
	 * Note it is batch_kob and not ordrelinjer.leveret that decides this. The receipt path never
	 * touches leveret: it writes batch_kob (:277) and decrements leveres (:284), and on the purchase
	 * side the "update ordrelinjer set leveret" statements in kreditor/ordre.php and ordreM.php are
	 * commented out. Reading leveret would therefore see 0 on every KO line and treat fully received
	 * orders as open. leveres is not the answer either - it holds the remaining quantity, not the
	 * delivered one.
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
	 * Takes no warehouse: a purchase order line carries none of its own, and the goods-receipt screen
	 * does not narrow by one either when it decides what is still outstanding.
	 *
	 * It does match on variant, because kreditor/ordre.php:984 writes variant_id onto purchase lines
	 * and two variants of one item can be bought at different prices. The match is strict: if nothing
	 * open exists for this variant the caller falls back to varer.kostpris rather than borrow another
	 * variant's price, since a wrong cost from a different variant is harder to spot than a stale one.
	 * Comparison is on the integer value, as ordrelinjer.variant_id is a text column that legacy rows
	 * carry as '' while batch_kob.variant_id is an int defaulting to 0.
	 *
	 * @param int|string $vare_id    The item being sold.
	 * @param int|string $variant_id Variant of that item, '' or 0 when it has none.
	 * @return array|null ['pris' => float, 'ordre_id' => int, 'linje_id' => int, 'ordrenr' => string]
	 *                    or null when no open purchase line covers this item and variant.
	 */
	function find_open_purchase_cost($vare_id, $variant_id = 0)
	{
		$vare_id = (int) $vare_id;
		if (!$vare_id) {
			return NULL;
		}
		$variant_id = (int) $variant_id;

		$qtxt  = "select ol.id as linje_id, ol.ordre_id, ol.pris, ol.rabat, ol.antal, ol.variant_id, ";
		$qtxt .= "coalesce((select sum(bk.antal) from batch_kob bk where bk.linje_id = ol.id), 0) as modtaget, ";
		$qtxt .= "o.valutakurs, o.ordrenr ";
		$qtxt .= "from ordrelinjer ol, ordrer o ";
		$qtxt .= "where ol.vare_id = '$vare_id' and ol.ordre_id = o.id ";
		$qtxt .= "and o.art = 'KO' and (o.status = '1' or o.status = '2') ";
		$qtxt .= "order by o.ordredate desc, ol.id desc";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

		while ($r = db_fetch_array($q)) {
			// Filtered here rather than in SQL: variant_id is a text column holding '', NULL and
			// numbers across tenants, so casting it in the query would be the fragile part.
			if ((int) (isset($r['variant_id']) ? $r['variant_id'] : 0) !== $variant_id) {
				continue;   // a different variant of the same item, bought at its own price
			}

			$antal    = (float) $r['antal'];
			$modtaget = (float) $r['modtaget'];
			if ($antal - $modtaget <= 0) {
				continue;   // nothing outstanding on this line
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

if (!function_exists('deficit_cost_is_pos_line')) {
	/**
	 * Whether an order line belongs to a POS sale (ordrer.art 'PO').
	 *
	 * @param int|string $linje_id The order line being priced.
	 * @return bool True for a line on a POS order.
	 */
	function deficit_cost_is_pos_line($linje_id)
	{
		$qtxt = "select o.art from ordrelinjer ol join ordrer o on o.id = ol.ordre_id where ol.id = '" . (int) $linje_id . "'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		return $r && $r['art'] === 'PO';
	}
}

if (!function_exists('deficit_cost_notice_relevant')) {
	/**
	 * Whether the cost-price message is shown for this line (SD-786).
	 *
	 * Only when all of these hold, cheapest test first:
	 * - "Vis besked når kostprisen er anslået" is ticked (settings showCostEstimateNotice, group ordre; off by default);
	 * - it is not a webservice/API delivery: rest_api.php, mysale/api.php, remoteBooking/api.php and
	 *   soapserver/invoice.php emit JSON or XML, which printed markup would invalidate;
	 * - it is not linjeopdat()'s negative-sale/credit-note branch (the label it passes as $kontekst);
	 * - the price is worth a look: it came from the item card (no open purchase line), or from an open
	 *   purchase line whose price differs from the item card by 0,005 or more (the Den-Tec case, SST-795/796);
	 * - it is not a POS sale (ordrer.art 'PO'): the message held back the receipt redirect (SST-907).
	 *
	 * @param int|string        $linje_id     The order line being priced.
	 * @param string            $kontekst     linjeopdat()'s label for the calling branch.
	 * @param bool|string       $webservice   Truthy on an API/SOAP delivery.
	 * @param array|null        $kostkilde    find_open_purchase_cost()'s result, NULL when no line was open.
	 * @param float             $kostpris     The cost price that was used.
	 * @param float             $varekortpris varer.kostpris of the item.
	 * @return bool
	 */
	function deficit_cost_notice_relevant($linje_id, $kontekst, $webservice, $kostkilde, $kostpris, $varekortpris)
	{
		if ($webservice || $kontekst === 'negativt salg/kreditnota') {
			return false;
		}
		// Compared in thousandths, the precision ordrelinjer.kostpris and batch_kob.pris are stored with
		// (numeric(15,3)): a float difference of 100.005 - 100.000 is 0.00499999…, which would hide a
		// difference of exactly 0,005.
		if ($kostkilde && abs((int) round((float) $kostpris * 1000) - (int) round((float) $varekortpris * 1000)) < 5) {
			return false;   // the open purchase line agrees with the item card: nothing to look at
		}
		if (get_settings_value("showCostEstimateNotice", "ordre", "off") !== "on") {
			return false;
		}
		return !deficit_cost_is_pos_line($linje_id);
	}
}

if (!function_exists('deficit_cost_notice')) {
	/**
	 * Prints the cost-price message for one item (SD-786).
	 *
	 * All items of one request end up in a single alert: the first call prints the heading and an
	 * onload handler, every call adds its own line. The texts go through jsString(), so an item number
	 * or translation with a quote cannot break the script.
	 *
	 * @param string     $varenr       Item number shown to the user.
	 * @param array|null $kostkilde    find_open_purchase_cost()'s result, NULL when the item card price was used.
	 * @param float      $kostpris     The cost price that was used.
	 * @param float      $varekortpris varer.kostpris of the item.
	 * @param int|string $sprog_id     Language for the texts.
	 * @return void
	 */
	function deficit_cost_notice($varenr, $kostkilde, $kostpris, $varekortpris, $sprog_id)
	{
		static $started = false;
		if ($kostkilde) {
			$line = sprintf(findtekst('7637|%s: %s fra åben indkøbsordre %s (varekortet: %s)', $sprog_id),
				$varenr, dkdecimal($kostpris, 2), $kostkilde['ordrenr'], dkdecimal($varekortpris, 2));
		} else {
			$line = sprintf(findtekst('7638|%s: %s fra varekortet. Der er ingen åben indkøbsordre, så prisen kan være forældet', $sprog_id),
				$varenr, dkdecimal($kostpris, 2));
		}
		if (!$started) {
			$started = true;
			$head = findtekst('7636|Kostprisen er anslået på disse varer, fordi der ikke var et købsparti at tage den fra:', $sprog_id);
			print "<script>window.saldiCostNotice = [" . jsString(array($head)) . "];";
			print "window.addEventListener('load', function () { alert(window.saldiCostNotice.join('\\n')); });</script>\n";
		}
		print "<script>window.saldiCostNotice.push(" . jsString(array($line)) . ");</script>\n";
	}
}

if (!function_exists('deficit_cost_price')) {
	/**
	 * Cost price for a quantity that no batch_kob covers, for both of linjeopdat()'s deficit paths.
	 *
	 * Prefers an open purchase line (see find_open_purchase_cost()) and falls back to the static
	 * varer.kostpris. Either way the choice is written to the order log so a stale cost price can be
	 * traced afterwards instead of first surfacing on a customer's invoice. The user only gets a
	 * message when the setting is ticked and the case is relevant (deficit_cost_notice_relevant()),
	 * and then one message per delivery listing the items (deficit_cost_notice()), not one per line.
	 *
	 * Both callers reach their deficit branch only when there is no purchase reference to inherit a
	 * cost from: the negative/credit-note branch zeroes its remainder whenever $kred_linje_id is set,
	 * so a return booked against a specific original sale line never lands here. That is why an open
	 * purchase line is the better estimate in both places and not just on the sale path.
	 *
	 * @param int|string   $vare_id  The item.
	 * @param int|string   $linje_id The order line being priced, for the log and the POS check.
	 * @param int|string   $variant_id Variant of the item, '' or 0 when it has none.
	 * @param resource|null $fp      Open order log handle, or NULL when the caller has none.
	 * @param int|string   $sprog_id Language for the message texts.
	 * @param string       $kontekst Short label naming the calling branch, for the log; the credit-note
	 *                            branch's label also keeps the message off.
	 * @param bool|string  $webservice Truthy on an API/SOAP delivery, where the HTML warning must be
	 *                            suppressed: those callers emit JSON or XML, and printed markup would
	 *                            precede and invalidate the response. The log line is still written,
	 *                            since that is where an API caller can see what happened at all.
	 * @return float The cost price, in base currency.
	 */
	function deficit_cost_price($vare_id, $linje_id, $variant_id, $fp, $sprog_id, $kontekst, $webservice = false)
	{
		$vare         = db_fetch_array(db_select("select varenr, kostpris from varer where id = '" . (int) $vare_id . "'", __FILE__ . " linje " . __LINE__));
		$varekortpris = $vare ? (float) $vare['kostpris'] : 0.0;

		$kostkilde = find_open_purchase_cost($vare_id, $variant_id);
		if ($kostkilde) {
			$kostpris      = $kostkilde['pris'];
			$kostkilde_txt = "open purchase line " . $kostkilde['linje_id'] . " on order " . $kostkilde['ordrenr'];
		} else {
			$kostpris      = $varekortpris;
			$kostkilde_txt = "varer.kostpris";
		}

		if ($fp) {
			fwrite($fp, date("Y-m-d H:i:s") . " SST-796 deficit cost price ($kontekst): vare_id $vare_id, linje_id $linje_id, source $kostkilde_txt, pris $kostpris\n");
		}

		// SD-786: the message is off by default and, when ticked, only shown where it tells the user
		// something. The delivery goes through either way, and the source is always in the order log.
		if (deficit_cost_notice_relevant($linje_id, $kontekst, $webservice, $kostkilde, $kostpris, $varekortpris)) {
			deficit_cost_notice(isset($vare['varenr']) ? $vare['varenr'] : (string) $vare_id, $kostkilde, $kostpris, $varekortpris, $sprog_id);
		}

		return $kostpris;
	}
}
?>
