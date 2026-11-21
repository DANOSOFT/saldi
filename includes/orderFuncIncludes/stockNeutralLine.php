<?php
// --- includes/orderFuncIncludes/stockNeutralLine.php ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Order lines that are delivered without moving stock, called from linjeopdat() for a stocked item:
// - a credit line that asks for no restock (ordrelinjer.retur_lager 'of'), or a dekort (kredit_art 'dekort'),
//   where the customer keeps the unit;
// - a set's own line (samlevare 'on' with a saet number); its components carry the stock, and invoicing
//   already gives it cost 0 on the same test.
//
// History:
// 20261118 CL/SS SD-833 Created (SD-746 core edit 4).
// 20261118 CL/SS SD-833 Codex review: invoicing costs the line nothing and dates its batch with the delivery row.
// 20261120 CL/SS SD-833 review: the column check looks in the current schema only.
// 20261121 CL/SS SD-833 review: on MySQL/MariaDB the column check looks in the current database.

// retur_lager and kredit_art come with the Shopify schema (includes/shopify/schema.php), so other tenants lack them.
function order_line_has_credit_columns(): bool
{
	static $exists = null;
	if ($exists === null) {
		$schema = in_array($GLOBALS['db_type'] ?? '', array('mysql', 'mysqli'), true) ? 'DATABASE()' : 'current_schema()';
		$qtxt = "select count(*) as n from information_schema.columns where table_schema = $schema and table_name = 'ordrelinjer'";
		$qtxt .= " and column_name in ('retur_lager', 'kredit_art')";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$exists = (int)$r['n'] == 2;
	}
	return $exists;
}

function order_line_is_stock_neutral(int $lineId, float $antal): bool
{
	$columns = order_line_has_credit_columns() ? 'samlevare, saet, retur_lager, kredit_art' : 'samlevare, saet';
	$r = db_fetch_array(db_select("select $columns from ordrelinjer where id = '$lineId'", __FILE__ . " linje " . __LINE__));
	if (!$r) {
		return false;
	}
	if (trim((string)$r['samlevare']) == 'on' && (int)$r['saet'] > 0) {
		return true;
	}
	if ($antal >= 0) {
		return false;
	}
	return trim((string)($r['retur_lager'] ?? '')) == 'of' || trim((string)($r['kredit_art'] ?? '')) == 'dekort';
}

// The line gets its delivery row like any other, so it counts as delivered and invoicing keeps it.
// The row draws from a batch of its own holding the same quantity with nothing left, so bought minus
// sold does not move: the batches keep counting the stock the item card shows. Following the row's
// batch_kob_id explains it. The batch has linje_id and ordre_id 0, because debitor/ordre.php deletes a
// batch_kob on an order line's id and invoicing reads batch_kob by the order's id. It gets its invoice date
// together with the row (stock_neutral_line_invoiced()), so stock counted by invoice date nets to 0 as well.
function deliver_stock_neutral_line(int $orderId, int $lineId, int $vareId, int $variantId, int $lager, float $antal, $pris, $levNr, string $levdate): void
{
	$qtxt = "insert into batch_kob(vare_id, linje_id, ordre_id, kobsdate, fakturadate, antal, rest, pris, lager, variant_id)";
	$qtxt .= " values ('$vareId', '0', '0', '$levdate', NULL, '$antal', '0', '0', '$lager', '$variantId')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "select max(id) as id from batch_kob where vare_id = '$vareId' and linje_id = '0' and ordre_id = '0' and rest = '0' and antal = '$antal'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$batchId = (int)$r['id'];
	$pris = (float)$pris;
	$levNr = (int)$levNr;
	$qtxt = "insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr, lager, variant_id)";
	$qtxt .= " values ('$batchId', '$vareId', '$lineId', '$levdate', '$orderId', '$antal', '$pris', '$levNr', '$lager', '$variantId')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

// Called by invoicing (batch_salg()) for a stock-neutral line: it costs nothing, because no stock moved, and its
// batch takes the invoice date the delivery row gets.
function stock_neutral_line_invoiced(int $lineId, int $batchId, string $fakturadate): void
{
	db_modify("update ordrelinjer set kostpris = 0 where id = '$lineId'", __FILE__ . " linje " . __LINE__);
	if ($batchId && $fakturadate) {
		$qtxt = "update batch_kob set fakturadate = '" . db_escape_string($fakturadate) . "' where id = '$batchId' and linje_id = 0 and ordre_id = 0 and rest = 0";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
}
