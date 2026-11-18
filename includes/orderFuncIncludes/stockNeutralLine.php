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
// - a credit line that asks for no restock (ordrelinjer.retur_lager 'of'), so the customer keeps the unit;
// - a set's own line (samlevare 'on' with a saet number); its components carry the stock, and invoicing
//   already gives it cost 0 on the same test.
//
// History:
// 20261118 CL/SS SD-833 Created (SD-746 core edit 4).

function order_line_has_restock_column(): bool
{
	static $exists = null;
	if ($exists === null) {
		$qtxt = "select column_name from information_schema.columns where table_name = 'ordrelinjer' and column_name = 'retur_lager'";
		$exists = (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	}
	return $exists;
}

function order_line_is_stock_neutral(int $lineId, float $antal): bool
{
	$columns = order_line_has_restock_column() ? 'samlevare, saet, retur_lager' : 'samlevare, saet';
	$r = db_fetch_array(db_select("select $columns from ordrelinjer where id = '$lineId'", __FILE__ . " linje " . __LINE__));
	if (!$r) {
		return false;
	}
	if (trim((string)$r['samlevare']) == 'on' && (int)$r['saet'] > 0) {
		return true;
	}
	return $antal < 0 && trim((string)($r['retur_lager'] ?? '')) == 'of';
}

// The line gets its delivery row like any other, so it counts as delivered and invoicing keeps it.
// The row draws from a batch of its own holding the same quantity with nothing left, so bought minus
// sold does not move: the batches keep counting the stock the item card shows. Following the row's
// batch_kob_id explains it. The batch has linje_id and ordre_id 0, because debitor/ordre.php deletes a
// batch_kob on an order line's id and invoicing reads batch_kob by the order's id.
function deliver_stock_neutral_line(int $orderId, int $lineId, int $vareId, int $variantId, int $lager, float $antal, $pris, $levNr, string $levdate): void
{
	$qtxt = "insert into batch_kob(vare_id, linje_id, ordre_id, kobsdate, fakturadate, antal, rest, pris, lager, variant_id)";
	$qtxt .= " values ('$vareId', '0', '0', '$levdate', '$levdate', '$antal', '0', '0', '$lager', '$variantId')";
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
