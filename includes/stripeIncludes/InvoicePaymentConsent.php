<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/InvoicePaymentConsent.php --- 2026.10.12
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
// 20261012 CL/LH Created: the one versioned consent text for automatic invoice
//                payment (AP-15). The hosted page renders it next to the
//                unticked box; the service stores VERSION and sha256() on the
//                payment row when the customer ticks it and copies both onto
//                the card when the card is saved. Any wording change needs a
//                new VERSION, otherwise stored hashes stop matching the text.

namespace Saldi\Stripe;

final class InvoicePaymentConsent
{
    /** Placeholder version until Adam approves the wording. */
    public const VERSION = 'v1-2026-10';

    /** False until Adam has approved TITLE and TEXT; shown in the settings UI. */
    public const APPROVED = false;

    public const TITLE = 'Betal fremtidige fakturaer automatisk';

    public const TEXT = 'Jeg giver Danosoft lov til at gemme mit kort hos Stripe. '
        . 'Danosoft må automatisk trække fremtidige fakturaer fra Danosoft på kortet på forfaldsdatoen. '
        . 'Der trækkes det beløb, som stadig er åbent på den enkelte faktura. '
        . 'Jeg får stadig fakturaen på mail som hidtil. '
        . 'Jeg kan stoppe aftalen med linket »Fjern kort« på fakturasiden eller ved at kontakte Danosoft.';

    /** The exact text the customer accepts: box label and body. */
    public static function text(): string
    {
        return self::TITLE . "\n" . self::TEXT;
    }

    /** Stable fingerprint of text(); stored as consent_text_sha256. */
    public static function sha256(): string
    {
        return hash('sha256', self::text());
    }
}
