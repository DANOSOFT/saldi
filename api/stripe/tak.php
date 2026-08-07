<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/tak.php --- 2026.08.07
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260807 CL/LH Created: Checkout success landing page. DELIBERATELY dumb:
//                reads nothing, writes nothing, books nothing - a query
//                parameter is not proof of payment. The webhook is the only
//                source of truth for what happened.

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
?>
<!DOCTYPE html><html lang="da"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Tak for din tilmelding</title>
<style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f4f4;margin:0;padding:2em 1em}
.card{max-width:560px;margin:0 auto;background:#fff;border:1px solid #ddd;border-radius:6px;padding:2em}
h1{font-size:1.3em;margin-top:0}.muted{color:#666;font-size:.85em}</style></head><body>
<div class="card">
<h1>Tak for din tilmelding!</h1>
<p>Automatisk betaling er nu sat op. Du modtager en bekræftelse og kvittering fra Stripe på mail.</p>
<p>Du behøver ikke foretage dig mere - fremtidige fakturaer betales automatisk, og du modtager dem fortsat på mail.</p>
<p class="muted">Spørgsmål? Besvar blot fakturamailen.</p>
</div></body></html>
