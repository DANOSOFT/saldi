<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/payments/PaymentLinkProvider.php --- ver 5.0.0 --- 2026-10-05 ---
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
// 20261005 CDX/LH Document the authoritative capture timestamp returned by provider observations.

/** Provider calls never book an invoice; settlement belongs to PaymentSettlementService. */
interface PaymentLinkProvider
{
    public function configured(): bool;
    /** @return array{reference:string,state:string,redirect_url:string} */
    public function create(string $reference, int $amountMinor, string $currency, string $returnUrl, string $description): array;
    /** @return array{reference:string,state:string,amount_minor:int,currency:string,captured_minor:int,captured_at:string|null,psp_reference:string|null} */
    public function get(string $reference): array;
    public function cancel(string $reference): bool;
    /** @return array{reference:string,state:string,amount_minor:int,currency:string,captured_minor:int,captured_at:string|null,psp_reference:string|null} */
    public function capture(string $reference, int $amountMinor, string $currency): array;
}
