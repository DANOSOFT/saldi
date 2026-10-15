<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/invoicing/invoiceFinalize.php --- ver 5.0.0 --- 2026.10.04
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
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Map downstream batch/VAT errors and record stock keys for post-commit sync.

require_once __DIR__ . '/../../restapi/core/Idempotency.php';

/**
 * Deliver the remaining quantities and invoice atomically through Saldi's engines.
 * Requires the caller's transaction and order/numbering lock; never commits.
 * @return array{result:string,fakturanr:?string,posted:bool,stock_keys:array<array{vare_id:int,variant_id:int,lager:int}>}
 */
function invoice_finalize_app(int $ordreId, string $fakturadate): array
{
    global $connection;
    if (!$connection instanceof \PgSql\Connection || pg_transaction_status($connection)!==PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Finalization requires caller transaction');
    }
    $order = db_fetch_array(db_select("SELECT status FROM ordrer WHERE id=$ordreId FOR UPDATE",__FILE__.' linje '.__LINE__));
    if (!$order) { throw new MobileT2Error('not_found',404); }
    if ((int)$order['status']>=3) { throw new MobileT2Error('conflict',409,['reason'=>'already_invoiced']); }
    if ($reason=invoice_date_check($fakturadate)) { throw new MobileT2Error('validation_failed',422,['reason'=>$reason]); }
    if ($error=check_periode_luk($fakturadate)) { throw new MobileT2Error('validation_failed',422,['reason'=>'period_locked']); }
    $date = db_escape_string($fakturadate);
    db_modify("UPDATE ordrer SET fakturadate='$date',levdate=COALESCE(levdate,'$date') WHERE id=$ordreId",__FILE__.' linje '.__LINE__);
    // Connection-local rows participate in this transaction and disappear on commit/rollback.
    // No persistent tenant schema is added; only the successful caller receives the keys.
    db_modify('CREATE TEMP TABLE IF NOT EXISTS mobile_invoice_stock_sync (
        ordre_id integer NOT NULL, vare_id integer NOT NULL, variant_id integer NOT NULL, lager integer NOT NULL,
        PRIMARY KEY (ordre_id,vare_id,variant_id,lager)) ON COMMIT DROP',__FILE__.' linje '.__LINE__);
    $recordStock = static function (int $vareId,int $variantId,int $lager) use ($ordreId): void {
        db_modify("INSERT INTO pg_temp.mobile_invoice_stock_sync (ordre_id,vare_id,variant_id,lager)
            VALUES ($ordreId,$vareId,$variantId,$lager) ON CONFLICT DO NOTHING",__FILE__.' linje '.__LINE__);
    };
    $result = levering($ordreId,'on',null,'on',true,$recordStock);
    if ($result !== 'OK') {
        if (is_array($result)) { throw new MobileT2Error('validation_failed',422,$result); }
        $reason = 'missing_delivery_date';
        if (str_contains($result,'variantinformation')) { $reason='variant_missing'; }
        elseif (str_contains($result,'for meget')) { $reason='over_delivery'; }
        elseif (str_contains($result,'serienumre')) { $reason='serial_numbers_missing'; }
        elseif (str_contains($result,'varegruppe')) { $reason='product_group_missing'; }
        $details = ['reason'=>$reason];
        if (preg_match('/pos nr[.:]\s*([0-9]+)/u',$result,$match)) { $details['line']=(int)$match[1]; }
        throw new MobileT2Error('validation_failed',422,$details);
    }
    db_modify("UPDATE ordrer SET status=2 WHERE id=$ordreId AND status<2",__FILE__.' linje '.__LINE__);
    $result = bogfor($ordreId,'on',false,true,true);
    if ($result !== 'OK') {
        if (is_array($result)) { throw new MobileT2Error('validation_failed',422,$result); }
        $reason = str_contains($result,'valutakurs') ? 'missing_exchange_rate' : 'invalid_order_configuration';
        throw new MobileT2Error('validation_failed',422,['reason'=>$reason]);
    }
    $invoice = db_fetch_array(db_select("SELECT status,fakturanr FROM ordrer WHERE id=$ordreId",__FILE__.' linje '.__LINE__));
    $stockKeys=[];
    $query=db_select("SELECT vare_id,variant_id,lager FROM pg_temp.mobile_invoice_stock_sync WHERE ordre_id=$ordreId ORDER BY vare_id,variant_id,lager",__FILE__.' linje '.__LINE__);
    while ($key=db_fetch_array($query)) {
        $stockKeys[]=['vare_id'=>(int)$key['vare_id'],'variant_id'=>(int)$key['variant_id'],'lager'=>(int)$key['lager']];
    }
    return ['result'=>$result,'fakturanr'=>$invoice['fakturanr'],'posted'=>(int)$invoice['status']>=4,'stock_keys'=>$stockKeys];
}
