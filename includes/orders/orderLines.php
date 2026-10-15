<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/orders/orderLines.php --- ver 5.0.0 --- 2026.10.03
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
// 20261001 CDX/LH Refuse unrepresentable accounting overrides and clear dependent pricing after app overrides.

require_once __DIR__ . '/../lager/productVat.php';

/** @return string|null The web's refusal text, or null after deletion/no matching line. */
function order_line_delete(int $ordreId, int $linjeId): ?string
{
    global $regnaar, $sprog_id;
    $year = (int)$regnaar;
    $line = db_fetch_array(db_select("SELECT * FROM ordrelinjer WHERE id=$linjeId AND ordre_id=$ordreId",__FILE__.' linje '.__LINE__));
    if (!$line) { return null; }
    $productId = (int)$line['vare_id'];
    $stock = false;
    if ($productId) {
        $product = db_fetch_array(db_select("SELECT gruppe FROM varer WHERE id=$productId",__FILE__.' linje '.__LINE__));
        $groupNo = (int)ifset($product,'gruppe',0);
        $group = db_fetch_array(db_select("SELECT box8 FROM grupper WHERE art='VG' AND kodenr=$groupNo AND fiscal_year=$year",__FILE__.' linje '.__LINE__));
        $stock = (bool)ifset($group,'box8',false);
    }
    if ($stock) {
        $received = db_fetch_array(db_select("SELECT 1 FROM batch_kob WHERE linje_id=$linjeId AND antal!=0 LIMIT 1",__FILE__.' linje '.__LINE__));
        if ($received) {
            return findtekst('1839|Du kan ikke slette en varelinje, når der ér modtaget',$sprog_id).' varer fra linjen.';
        }
        $sale = db_fetch_array(db_select("SELECT batch_salg.vare_id,varer.gruppe FROM batch_salg,varer WHERE batch_salg.linje_id=$linjeId AND batch_salg.antal!=0",__FILE__.' linje '.__LINE__));
        $saleGroup = (int)ifset($sale,'gruppe',0);
        $group = $sale && $sale['vare_id'] ? db_fetch_array(db_select("SELECT box8 FROM grupper WHERE art='VG' AND kodenr=$saleGroup AND fiscal_year=$year",__FILE__.' linje '.__LINE__)) : null;
        if (ifset($group,'box8',false)) {
            return findtekst('1840|Du kan ikke slette en varelinje, når der ér leveret varer fra linjen.',$sprog_id);
        }
        db_modify("DELETE FROM batch_kob WHERE linje_id=$linjeId",__FILE__.' linje '.__LINE__);
        db_modify("DELETE FROM batch_salg WHERE linje_id=$linjeId",__FILE__.' linje '.__LINE__);
        db_modify("DELETE FROM reservation WHERE linje_id=$linjeId",__FILE__.' linje '.__LINE__);
        db_modify("DELETE FROM reservation WHERE batch_salg_id=-$linjeId",__FILE__.' linje '.__LINE__);
        db_modify("DELETE FROM ordrelinjer WHERE id=$linjeId",__FILE__.' linje '.__LINE__);
        if ($line['folgevare']) {
            db_modify("DELETE FROM ordrelinjer WHERE folgevare=-$linjeId AND ordre_id=$ordreId",__FILE__.' linje '.__LINE__);
        }
        db_modify("DELETE FROM ordrelinjer WHERE samlevare='$linjeId'",__FILE__.' linje '.__LINE__);
        db_modify("UPDATE serienr SET salgslinje_id=0 WHERE salgslinje_id=$linjeId",__FILE__.' linje '.__LINE__);
    } else {
        db_modify("DELETE FROM ordrelinjer WHERE id=$linjeId",__FILE__.' linje '.__LINE__);
    }
    return null;
}

/** @return string|null Reason the mobile line model cannot safely round-trip the persisted order. */
function order_app_editable(int $ordreId): ?string
{
    global $regnaar;
    $order = db_fetch_array(db_select("SELECT * FROM ordrer WHERE id=$ordreId",__FILE__.' linje '.__LINE__));
    if (!$order) { return 'missing_order'; }
    if ((int)$order['status'] >= 3) { return 'invoiced'; }
    if ($order['art']!=='DO') { return 'order_type'; }
    if ((int)$order['sag_id'] || (int)$order['afd'] || trim((string)$order['projekt']) || (float)$order['procenttillag']) { return 'order_context'; }
    $year=(int)$regnaar; $customerId=(int)$order['konto_id'];
    $customerGroup=db_fetch_array(db_select("SELECT g.box9 FROM adresser a JOIN grupper g ON g.kodenr=a.gruppe
        WHERE a.id=$customerId AND g.art='DG' AND g.fiscal_year=$year",__FILE__.' linje '.__LINE__));
    $query = db_select("SELECT * FROM ordrelinjer WHERE ordre_id=$ordreId ORDER BY id",__FILE__.' linje '.__LINE__);
    while ($line = db_fetch_array($query)) {
        $lineId=(int)$line['id'];
        if ((float)$line['leveret'] || db_fetch_array(db_select("SELECT 1 FROM batch_salg WHERE linje_id=$lineId LIMIT 1",__FILE__.' linje '.__LINE__))) { return 'line_delivered'; }
        if ((int)$line['variant_id']) { return 'variant'; }
        if ((int)$line['lager'] !== 1) { return 'warehouse'; }
        if ($line['rabatart']==='amount') { return 'amount_discount'; }
        if (abs((float)$line['pris']-round((float)$line['pris'],2))>0.00000001) { return 'price_precision'; }
        if (!(int)$line['vare_id'] || (int)$line['bogf_konto'] && !$line['varenr']) { return 'text_line'; }
        if (trim((string)$line['samlevare']) || (int)$line['saet']) { return 'component'; }
        if ((int)$line['folgevare']) { return 'follow_on'; }
        if (trim((string)$line['serienr']) || db_fetch_array(db_select("SELECT 1 FROM serienr WHERE salgslinje_id=$lineId LIMIT 1",__FILE__.' linje '.__LINE__))) { return 'serial'; }
        if ((float)$line['procent'] !== 100.0 || trim((string)$line['projekt']) && $line['projekt']!=='0') { return 'line_context'; }
        $productId=(int)$line['vare_id'];
        $product=db_fetch_array(db_select("SELECT gruppe FROM varer WHERE id=$productId",__FILE__.' linje '.__LINE__));
        if (!$product) { return 'line_accounting'; }
        $vat=product_vat_rate((int)$product['gruppe'],$year,(int)$order['afd'],(float)$order['momssats']);
        $reverse=ifset($customerGroup,'box9',false) && $vat['omvare'] ? 'on' : '';
        $rate=min($vat['rate'],(float)$order['momssats']);
        if ($vat['error'] || (string)$line['momsfri']!==$vat['legacy_momsfri'] || (float)$line['momssats']!==$rate
            || (string)$line['omvbet']!==$reverse || (int)$line['vat_account']!==$vat['vat_account']
            || (int)$line['bogf_konto']!==$vat['revenue_account']) { return 'line_accounting'; }
    }
    return null;
}

/** Replace only representable lines; caller must hold the order lock and own a transaction. @return void */
function order_replace_lines(int $ordreId, array $lines): void
{
    global $connection;
    if (!$connection instanceof \PgSql\Connection || pg_transaction_status($connection)!==PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Replacing lines requires caller transaction');
    }
    if ($why=order_app_editable($ordreId)) { throw new MobileT2Error('conflict',409,['reason'=>'order_not_editable','why'=>$why]); }
    $query=db_select("SELECT id FROM ordrelinjer WHERE ordre_id=$ordreId ORDER BY id",__FILE__.' linje '.__LINE__);
    while ($line=db_fetch_array($query)) {
        if (order_line_delete($ordreId,(int)$line['id']) !== null) { throw new MobileT2Error('validation_failed',422,['reason'=>'line_delivered']); }
    }
    order_insert_app_lines($ordreId,$lines);
}

/** Create lines with the existing engine and reserve an approved order as the web does. @return void */
function order_insert_app_lines(int $ordreId, array $lines): void
{
    $order=db_fetch_array(db_select("SELECT * FROM ordrer WHERE id=$ordreId",__FILE__.' linje '.__LINE__));
    $position=0;
    $overrides=[];
    foreach ($lines as $input) {
        $position++; $productId=(int)$input['vare_id'];
        $price=ifset($input,'unit_price','');
        $discount=ifset($input,'discount_pct',0);
        $quantity=(float)$input['quantity'];
        opret_ordrelinje($ordreId,$productId,'',$quantity,'',$price,$discount,100,'DO','',$position,0,0,0,'percent',0,0,0,'',1,0);
        $line=db_fetch_array(db_select("SELECT id FROM ordrelinjer WHERE ordre_id=$ordreId AND posnr=$position ORDER BY id DESC LIMIT 1",__FILE__.' linje '.__LINE__));
        if (!$line) { throw new MobileT2Error('validation_failed',422,['reason'=>'line_creation_failed','line'=>$position-1]); }
        $lineId=(int)$line['id'];
        order_apply_app_line_overrides($lineId,$input,$order);
        $overrides[$lineId]=$input;
    }
    // A later insertion can apply quantity pricing to earlier rows of the same product.
    foreach ($overrides as $lineId=>$input) {
        order_apply_app_line_overrides((int)$lineId,$input,$order);
    }
    if ((int)$order['status']>0) {
        db_modify("UPDATE ordrelinjer SET leveres=antal WHERE ordre_id=$ordreId",__FILE__.' linje '.__LINE__);
        // B6b supplies order_approve(); until then invoke exactly its per-line reservation engine.
        if (function_exists('order_approve')) {
            order_approve($ordreId,$order['levdate']);
        } else {
            $query=db_select("SELECT id FROM ordrelinjer WHERE ordre_id=$ordreId ORDER BY id",__FILE__.' linje '.__LINE__);
            while ($line=db_fetch_array($query)) { batch((int)$line['id']); }
        }
    } else {
        db_modify("UPDATE ordrelinjer SET leveres=0 WHERE ordre_id=$ordreId",__FILE__.' linje '.__LINE__);
    }
}

/** Keep explicit net prices and discounts consistent with their dependent stored values. @return void */
function order_apply_app_line_overrides(int $lineId, array $input, array $order): void
{
    if (array_key_exists('unit_price',$input)) {
        $netPrice=(float)$input['unit_price']; $productId=(int)$input['vare_id'];
        $product=db_fetch_array(db_select("SELECT salgspris,kostpris FROM varer WHERE id=$productId",__FILE__.' linje '.__LINE__));
        $cost=(float)$product['kostpris']; $fastDb=0.0;
        if (!(float)$product['salgspris'] && $cost>0 && $cost<1) {
            $fastDb=$cost;
            $discount=(float)ifset($input,'discount_pct',0);
            $cost=$netPrice*(1-$discount/100)*$fastDb;
        } elseif ((float)$order['valutakurs']) {
            $cost*=100/(float)$order['valutakurs'];
        }
        db_modify("UPDATE ordrelinjer SET m_rabat=CASE WHEN $netPrice=0 OR pris<>$netPrice THEN 0 ELSE m_rabat END,
            kostpris=CASE WHEN $netPrice=0 OR pris<>$netPrice THEN $cost ELSE kostpris END,
            fast_db=CASE WHEN $netPrice=0 OR pris<>$netPrice THEN $fastDb ELSE fast_db END,
            vat_price=CASE WHEN momsfri<>'' THEN $netPrice ELSE $netPrice*(1+momssats/100) END,pris=$netPrice WHERE id=$lineId",__FILE__.' linje '.__LINE__);
    }
    if (array_key_exists('discount_pct',$input)) {
        $percent=(float)$input['discount_pct'];
        db_modify("UPDATE ordrelinjer SET rabat=$percent,rabatart='percent' WHERE id=$lineId",__FILE__.' linje '.__LINE__);
    }
}
