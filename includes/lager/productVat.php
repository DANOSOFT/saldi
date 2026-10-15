<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/lager/productVat.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Preserve the unset legacy rate until the order engine's late pricing fallback.

/**
 * Resolve the order engine's product-group VAT without customer-specific reverse charge.
 * The legacy fields retain pre-alert pricing, literal exemption flags and the unconfigured SM failure.
 * @return array{error:?string,momsfri:bool,omvare:bool,lagerfort:bool,revenue_account:int,vat_account:int,rate:float,missing_vat_account:bool,legacy_rate:?float,legacy_vat_account:?int,legacy_momsfri:string,legacy_lookup:array|false|null,legacy_lookup_applied:bool,legacy_vat_code:?int}
 */
function product_vat_rate(int $varegruppe, int $regnaar, int $afd, float $orderMomssats, string $callerMomsfri = ''): array
{
    $result = ['error' => null, 'momsfri' => false, 'omvare' => false, 'lagerfort' => false,
        'revenue_account' => 0, 'vat_account' => 0, 'rate' => 0.0, 'missing_vat_account' => false,
        'legacy_rate' => 0.0, 'legacy_vat_account' => 0, 'legacy_momsfri' => $callerMomsfri,
        'legacy_lookup' => null, 'legacy_lookup_applied' => false, 'legacy_vat_code' => null];
    $group = db_fetch_array(db_select("SELECT box4,box6,box7,box8 FROM grupper
        WHERE art='VG' AND kodenr=$varegruppe AND fiscal_year=$regnaar", __FILE__ . ' linje ' . __LINE__));
    if (!$group) {
        $result['error'] = 'product_group_missing';
        return $result;
    }
    $result['revenue_account'] = (int)$group['box4'];
    $result['omvare'] = (bool)$group['box6'];
    $result['lagerfort'] = (bool)$group['box8'];
    $result['legacy_momsfri'] = $callerMomsfri ?: (string)$group['box7'];
    $result['momsfri'] = (bool)$result['legacy_momsfri'];
    if (!$result['revenue_account']) {
        $result['error'] = 'revenue_account_missing';
        return $result;
    }
    if ($result['momsfri']) {
        return $result;
    }
    $account = $result['revenue_account'];
    $result['legacy_lookup_applied'] = true;
    $override = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name='maxDepVatRate_$afd'", __FILE__ . ' linje ' . __LINE__));
    $code = 0;
    if ($override) {
        $code = $afd;
    } else {
        $row = db_fetch_array(db_select("SELECT moms FROM kontoplan WHERE kontonr=$account AND regnskabsaar=$regnaar", __FILE__ . ' linje ' . __LINE__));
        $code = (int)substr((string)ifset($row, 'moms', ''), 1);
        $result['legacy_vat_code'] = $code;
        $result['legacy_lookup'] = $row;
    }
    $rawRate = $override || $code ? null : $orderMomssats;
    $rawAccount = $override || $code ? null : 0;
    if ($override || $code) {
        $vat = db_fetch_array(db_select("SELECT box1,box2 FROM grupper WHERE art='SM' AND kodenr=$code AND fiscal_year=$regnaar", __FILE__ . ' linje ' . __LINE__));
        $result['legacy_lookup'] = $vat;
        if (ifset($vat, 'box1')) {
            $rawAccount = (int)$vat['box1'];
        }
        if (ifset($vat, 'box2')) {
            $rawRate = (float)$vat['box2'];
        }
    }
    $result['legacy_rate'] = $rawRate;
    $result['legacy_vat_account'] = $rawAccount;
    $result['vat_account'] = (int)$rawAccount;
    $effectiveRate = $rawRate ?? $orderMomssats;
    $result['missing_vat_account'] = $effectiveRate != 0 && !$rawAccount;
    $result['rate'] = $result['missing_vat_account'] ? 0.0 : $effectiveRate;
    return $result;
}
