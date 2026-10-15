<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/payments/paymentLinkToken.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Invoice payment links.

require_once __DIR__ . '/../../restapi/core/MobileSchema.php';

/** Request-scoped mail link, used while a delivery transaction prepares its MIME. */
final class PaymentLinkMailToken
{
    private static array $urls = [];
    public static function set(int $orderId, ?string $url): void
    {
        if ($url === null) {
            unset(self::$urls[$orderId]);
        } else {
            self::$urls[$orderId] = $url;
        }
    }
    public static function get(int $orderId): ?string
    {
        return ifset(self::$urls,$orderId);
    }
}

/** Resolve an existing active invoice link only; rendering mail never mints a payment/link. */
function paymentLinkUrl(int $orderId): string
{
    $override = PaymentLinkMailToken::get($orderId);
    if ($override !== null) {
        return $override;
    }
    if (in_array(strtolower((string)ifset($GLOBALS,'db_type','')),['mysql','mysqli'],true)) {
        return '';
    }
    $store = new MobileSchema();
    if ($store->rows("SELECT to_regclass('payment_links') AS name")[0]['name'] === null) {
        return '';
    }
    $rows = $store->rows("SELECT m.response_json,l.id FROM payment_links l JOIN mobile_idempotency m
        ON ((m.resource_type='payment_link' AND m.resource_id=l.id)
            OR (m.resource_type='document_delivery' AND m.response_json::jsonb #>> '{data,payment_link,id}'=l.id::text))
        WHERE l.ordre_id=$orderId AND l.state='active' AND l.expires_at>now() ORDER BY m.id DESC LIMIT 1");
    if (!$rows) {
        return '';
    }
    $data = ifset(json_decode($rows[0]['response_json'],true),'data',[]);
    $link = ifset($data,'payment_link',$data);
    return (string)ifset($link,'url','');
}

/** Template CTA uses the same opaque Saldi URL as QR/share; escape before HTML output. */
function paymentLinkHtml(string $url): string
{
    return $url === '' ? '' : '<a href="'.htmlspecialchars($url,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8').'">'.
        htmlspecialchars(findtekst('5480|Betal med MobilePay',1),ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8').'</a>';
}
