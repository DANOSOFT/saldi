<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/invoicePaymentStaffActions.php --- 2026.10.12
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
// 20261012 CL/LH Authorize debtor payment POSTs with session CSRF and explicit module write permission.

/** @return bool Whether the session has write permission for the debitor module (online.php module 6). */
function stripe_invoice_staff_can_write(string $rights): bool
{
    return substr($rights, 6, 1) === '1';
}

/**
 * Validate before constructing the payment service or making any address/Stripe write.
 * @return string Danish confirmation of the completed action.
 */
function stripe_invoice_staff_payment_request(array $request, int $kontoId, string $actor, string $csrf, string $rights, callable $serviceFactory): string
{
    // 20261012 CL/LH Share the hold-release session token with card removal; never accept absent tokens.
    if ($csrf === '' || !is_string($request['stripe_hold_csrf'] ?? null) || !hash_equals($csrf, $request['stripe_hold_csrf'])) {
        throw new RuntimeException('Formularen er udløbet. Genindlæs debitorkortet og prøv igen.');
    }
    if (!stripe_invoice_staff_can_write($rights)) {
        throw new RuntimeException('Du har ikke skriverettighed til debitorer.');
    }
    if ($kontoId <= 0) throw new RuntimeException('Debitoren mangler.');
    if (isset($request['stripe_fjern_kort'])) {
        $result = $serviceFactory()->removeCardResult($kontoId, $actor);
        if (!$result['stopped']) throw new RuntimeException('Der er ikke et aktivt kort at fjerne.');
        return $result['detached'] ? 'Automatisk betaling er stoppet, og kortet er fjernet.'
            : 'Automatisk betaling er stoppet i Saldi. Kortet kunne ikke fjernes hos Stripe; bogholderen er adviseret.';
    }
    $paymentId = (int)($request['stripe_release_hold'] ?? 0);
    if ($paymentId <= 0 || !$serviceFactory()->releaseCollectionHold($paymentId, $kontoId, $actor)) {
        throw new RuntimeException('Opkrævningen er allerede frigivet eller tilhører ikke denne debitor.');
    }
    return 'Opkrævningen er frigivet. Bogføring og tidligere betalinger er bevaret.';
}
