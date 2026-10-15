<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/orders/orderTotals.php --- ver 5.0.0 --- 2026.10.02
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
// 20261008 CL/LH Priced text lines (no vare_id, numeric pris; Saldi Assist services) count, as in ordrelinjer().

/**
 * Arithmetic used by ordrelinjer(); inputs are the page's effective VAT and signed quantity discount.
 * @return array{net:float,vat:float,mixed:bool,amount:float,discount_net:float,discount_vat:float}
 */
function order_line_amounts(array $line, array $orderCtx): array
{
    $price = (float)ifset($line, 'pris', 0);
    $quantity = (float)ifset($line, 'antal', 0);
    $discount = (float)ifset($line, 'rabat', 0);
    $rate = (float)ifset($line, 'momssats', 0);
    $amount = ifset($line, 'rabatart') === 'amount'
        ? ($price - $discount) * $quantity : ($price - $price / 100 * $discount) * $quantity;
    if (ifset($orderCtx, 'procentfakt', false)) {
        $amount *= (float)ifset($line, 'procent', 100) / 100;
    }
    $amount = afrund($amount, 3);
    $exempt = ifset($line, 'momsfri') === 'on';
    $mixed = $exempt || $rate != (float)ifset($orderCtx, 'momssats', 0);
    $net = (!ifset($line, 'saet', 0) || ifset($line, 'samlevare') !== 'on') ? $amount : 0.0;
    $vat = $exempt ? 0.0 : afrund($amount * $rate / 100, 3);
    $quantityDiscount = (float)ifset($line, 'm_rabat', 0);
    $discountNet = $discountVat = 0.0;
    if (!$discount && $quantityDiscount && !ifset($line, 'rabatgruppe', 0)) {
        $discountNet = afrund($quantityDiscount * $quantity, 2);
        $discountVat = $exempt ? 0.0 : afrund($quantityDiscount * $quantity * $rate / 100, 2);
    }
    return ['net'=>(float)($net+$discountNet),'vat'=>(float)($vat+$discountVat),'mixed'=>$mixed,
        'amount'=>(float)$amount,'discount_net'=>(float)$discountNet,'discount_vat'=>(float)$discountVat];
}

/** @return array{momssats:float,procentfakt:bool,incl_moms:bool} Tenant/customer display context. */
function order_totals_context(array $order): array
{
    $customerId = (int)$order['konto_id'];
    $customer = db_fetch_array(db_select("SELECT kontotype FROM adresser WHERE id=$customerId", __FILE__.' linje '.__LINE__));
    $setting = ifset($customer, 'kontotype') === 'erhverv' ? 'vatBusinessCustomers' : 'vatPrivateCustomers';
    $division = db_fetch_array(db_select("SELECT box12 FROM grupper WHERE art='DIV' AND kodenr=3", __FILE__.' linje '.__LINE__));
    return ['momssats'=>(float)$order['momssats'],'procentfakt'=>(bool)ifset($division,'box12',false),
        'incl_moms'=>(bool)get_settings_value($setting,'ordre','')];
}

/** @return array{sum:float,moms:float,kostpris:float} Engine-exact totals for ordinary catalogue lines. */
function order_totals_compute(int $ordreId): array
{
    $order = db_fetch_array(db_select("SELECT * FROM ordrer WHERE id=$ordreId", __FILE__.' linje '.__LINE__));
    if (!$order) { throw new InvalidArgumentException('Order missing'); }
    $ctx = order_totals_context($order);
    $sum = $vat = $cost = 0.0; $mixed = false;
    $query = db_select("SELECT * FROM ordrelinjer WHERE ordre_id=$ordreId ORDER BY posnr,id", __FILE__.' linje '.__LINE__);
    while ($line = db_fetch_array($query)) {
        if ((int)$line['posnr'] <= 0 || is_numeric($line['samlevare']) || ((int)$line['saet'] && $line['samlevare']==='on') || (int)$line['folgevare'] < 0) {
            continue;
        }
        $rate = (float)$line['momssats'];
        if (!$line['momsfri'] && !$rate) { $rate = $ctx['momssats']; }
        elseif ($rate > $ctx['momssats']) { $rate = $ctx['momssats']; }
        elseif ($line['momsfri'] || $line['omvbet']) { $rate = 0; }
        $line['momssats'] = $rate;
        $line['m_rabat'] = -(float)$line['m_rabat'];
        if ($line['varenr'] || ((int)$line['saet'] && $line['samlevare']) || (!(int)$line['vare_id'] && is_numeric($line['pris']))) {
            $amount = order_line_amounts($line, $ctx);
            $sum += $amount['net']; $vat += $amount['vat']; $mixed = $mixed || $amount['mixed'];
        }
        if ((int)$line['vare_id']) {
            $unitCost = (float)$line['fast_db'] ? (float)$line['pris'] * (float)$line['fast_db'] : (float)$line['kostpris'];
            $cost += $unitCost * (float)$line['antal'];
        }
    }
    $surcharge = $sum * (float)$order['procenttillag'] / 100;
    $sum += $surcharge; $vat += $surcharge * $ctx['momssats'] / 100;
    if (!$mixed && !$ctx['incl_moms']) { $vat = $sum * $ctx['momssats'] / 100; }
    return ['sum'=>$sum,'moms'=>(float)afrund($vat,3),'kostpris'=>$cost];
}

/**
 * The web supplies its rendered aggregates to retain its complex set/follow-on display rules.
 * REST computes from persisted lines; both use order_line_amounts() for the arithmetic.
 * @return array{sum:float,moms:float,kostpris:float}
 */
function order_totals_persist(int $ordreId, ?array $rendered = null): array
{
    $totals = $rendered ?? order_totals_compute($ordreId);
    $sum = (float)$totals['sum']; $cost = (float)$totals['kostpris']; $vat = (float)$totals['moms'];
    if (!is_finite($sum) || !is_finite($cost) || !is_finite($vat) || $sum >= 100000000000 || $cost >= 100000000000) {
        throw new InvalidArgumentException('Order total too large');
    }
    db_modify("UPDATE ordrer SET sum=$sum,kostpris=$cost,moms=$vat WHERE id=$ordreId", __FILE__.' linje '.__LINE__);
    return ['sum'=>$sum,'moms'=>$vat,'kostpris'=>$cost];
}
