<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/invoicePaymentRuntime.php --- 2026.10.12
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
// 20261007 CL/LH Created: wires InvoicePaymentService to the pinned Stripe
//                tenant (stripeConfigDb) on a dedicated PDO connection, with
//                stripeHttpRequest() as transport and stripeAlertMail() for
//                bookkeeper alerts. Callers include stripeBootstrap.php or
//                connect.php + db_connect first.
//                stripe_invoice_staff_card() serves debitor/debitorkort.php.
// 20261012 CL/LH Require every invoice-payment schema object before constructing the service.
// 20261012 CL/LH Expose held invoices and a scoped staff release handler.

// 20261012 CL/LH Let the durable outbox observe false mail results and retry delivery.
// 20261012 CL/LH Staff card view: effective autopay reasons, and reachable while invoice payment is off.
// 20261012 CL/LH Require CSRF and debtor-module write permission before staff payment mutations.

require_once __DIR__ . '/invoicePaymentStaffActions.php';
require_once __DIR__ . '/stripeSettings.php';
require_once __DIR__ . '/stripeHttp.php';
require_once __DIR__ . '/stripeRateLimit.php';
require_once __DIR__ . '/stripeAlertMail.php';
require_once __DIR__ . '/InvoicePaymentMath.php';
require_once __DIR__ . '/InvoicePaymentStore.php';
require_once __DIR__ . '/InvoicePaymentSchema.php';
require_once __DIR__ . '/InvoicePaymentLedger.php';
require_once __DIR__ . '/InvoicePaymentService.php';

/** @return \Saldi\Stripe\InvoicePaymentService Service bound to the Stripe tenant; throws when the session is another tenant. */
function stripe_invoice_payment_service(): \Saldi\Stripe\InvoicePaymentService
{
    global $sqhost, $squser, $sqpass, $db, $db_type;
    if (in_array($db_type, ['mysql', 'mysqli'], true)) throw new RuntimeException('Kortbetaling af fakturaer kræver PostgreSQL');
    $target = stripeConfigDb();
    if (!$target || $db !== $target) throw new RuntimeException('Stripe-regnskabet matcher ikke den aktuelle session');
    $pdo = new PDO('pgsql:host=' . $sqhost . ';dbname=' . $target, $squser, $sqpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $store = new \Saldi\Stripe\InvoicePaymentStore($pdo);
    // 20261012 CL/LH Collection requires complete provisioning; requests never run DDL.
    \Saldi\Stripe\InvoicePaymentSchema::assertProvisioned($store);
    $alert = static function (string $subject, string $body, string $key = '', int $throttle = 3600): bool {
        return stripeAlertMail($subject, $body, $key, $throttle);
    };
    return new \Saldi\Stripe\InvoicePaymentService($store, 'stripeHttpRequest', new \Saldi\Stripe\InvoicePaymentLedger($store), $alert, $target);
}

/**
 * Staff view on debitorkort.php: the debtor's saved card plus
 * 'autopay_reasons' (empty = automatic payment active), or null when this
 * session is not the Stripe tenant or no card is saved. Never throws.
 * @return array<string,mixed>|null
 */
function stripe_invoice_staff_card(int $kontoId): ?array
{
    global $db;
    try {
        if ($kontoId < 1 || !$db || stripeConfigDb() !== $db) return null;
        // 20261012 CL/LH Staff can still see and remove a saved card while invoice payment is off (AP-21).
        $svc = stripe_invoice_payment_service();
        $card = $svc->card($kontoId);
        if (!$card) return null;
        $card['autopay_reasons'] = $svc->autopayReasons($kontoId);
        return $card;
    } catch (Throwable $e) {
        stripe_log('debitorkort: card lookup failed: ' . $e->getMessage());
        return null;
    }
}

/** @return list<array{id:int,order_id:int,fakturanr:mixed,hold_reason:mixed}> Holds remain visible without a saved card. */
function stripe_invoice_staff_holds(int $kontoId): array
{
    global $db;
    try {
        if ($kontoId < 1 || !$db || stripeConfigDb() !== $db) return [];
        return stripe_invoice_payment_service()->collectionHolds($kontoId);
    } catch (Throwable $e) {
        stripe_log('debitorkort: hold lookup failed: ' . $e->getMessage());
        return [];
    }
}

/** Execute the authenticated page's dedicated hold POST before rendering. */
function stripe_invoice_release_hold_request(array $request, int $kontoId, string $actor, string $csrf, string $rights = ''): void
{
    stripe_invoice_staff_payment_request($request, $kontoId, $actor, $csrf, $rights, 'stripe_invoice_payment_service');
}
