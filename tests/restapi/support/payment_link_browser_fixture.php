<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/payment_link_browser_fixture.php --- ver 5.0.0 --- 2026-10-04 ---
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
// 20261001 CDX/LH Created. Disposable fixture for the browser payment redirect test.

require_once __DIR__ . '/PaymentLinkTestEnv.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
try {
    PaymentLinkTestEnv::setup();
    $origin = ifset($argv,1,'https://api.vipps.no');
    if (!in_array($origin, ['https://api.vipps.no','https://apitest.vipps.no',
        'https://pay.vipps.no','https://pay-mt.vipps.no','https://pay.mobilepay.dk',
        'https://pay-mt.mobilepay.dk','https://pay.mobilepay.fi','https://pay-mt.mobilepay.fi'],true)) {
        throw new InvalidArgumentException('Invalid browser fixture origin');
    }
    $service = PaymentLinkTestEnv::service();
    $link = $service->create(PaymentLinkTestEnv::body())['body']['data'];
    if ($origin !== 'https://api.vipps.no') {
        $attempt = $service->startAttempt($link['id'],$link['url']);
        $reference = RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT provider_reference FROM payment_attempts WHERE id=$1',[$attempt['attempt_id']])[0]['provider_reference'];
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'UPDATE payment_attempts SET redirect_url=$1 WHERE id=$2',[$origin.'/pay/'.$reference,$attempt['attempt_id']]);
    }
    echo json_encode(['query'=>parse_url($link['url'],PHP_URL_QUERY)],JSON_THROW_ON_ERROR)."\n";
    flush();
    if (trim((string)fgets(STDIN)) === 'verify') {
        $attempts = RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT * FROM payment_attempts WHERE link_id=$1',[$link['id']]);
        echo json_encode(['attempts'=>count($attempts),'state'=>ifset($attempts,[0,'state'])],JSON_THROW_ON_ERROR)."\n";
    }
} finally {
    PaymentLinkTestEnv::teardown();
}
