<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/stripe_event_requeue.php --- 2026.10.12
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
// 20261012 CL/LH Requeue stranded webhook rows without claiming to resend them.

/**
 * Injected by includes/connect.php and stripeBootstrap.php below.
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 * @var string $db_type
 * @var bool $stripe_boot_ok
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$options = getopt('', ['event:', 'force']);
$eventId = (string)($options['event'] ?? '');
if (!preg_match('/^evt_[A-Za-z0-9]+$/', $eventId)) {
    fwrite(STDERR, "Brug: php tools/stripe_event_requeue.php --event=evt_... [--force]\n");
    exit(2);
}
chdir(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/install/tools/stripe_event_requeue.php';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? 'cli';
require_once __DIR__ . '/../includes/db_query.php';
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/stripeIncludes/stripeBootstrap.php';
require_once __DIR__ . '/../includes/stripeIncludes/StripeEventLedger.php';
try {
    if (!$stripe_boot_ok || in_array($db_type, ['mysql', 'mysqli'], true)) {
        throw new RuntimeException('Stripe-regnskabet kunne ikke åbnes med PostgreSQL');
    }
    $pdo = new PDO('pgsql:host=' . $sqhost . ';dbname=' . stripeConfigDb(), $squser, $sqpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $result = (new \Saldi\Stripe\StripeEventLedger($pdo))->requeue($eventId, array_key_exists('force', $options));
    print "$eventId: {$result['previousStatus']} -> retry (lås frigivet).\nSend eventet igen fra Stripe Dashboard til webhook-endpointet.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(2);
}
