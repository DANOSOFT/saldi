<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/cors.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Exact configured CORS origins, Vary and request correlation.
// 20261001 CDX/LH Preserve exact production origins when configuration is unset.
// 20261001 CDX/LH Allow mobile conditional and idempotent request headers and expose ETag.

require_once __DIR__ . '/RequestId.php';

/** Configured origins are exact scheme/host/port strings; never reflect unknown origins. */
function setCorsHeaders(): void
{
    RequestId::emit();
    header('Vary: Origin');
    $origin = ifset($_SERVER, 'HTTP_ORIGIN', '');
    $configured = getenv('SALDI_CORS_ORIGINS');
    $allowed = $configured === false ? ['https://saldi.dk', 'https://ssl12.saldi.dk'] : array_map('trim', explode(',', $configured));
    if ($origin !== '' && preg_match('~^https?://[^/?#\s@]+$~D', $origin)
        && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Tenant-ID, X-DB, X-SaldiUser, X-Requested-With, X-Request-Id, Idempotency-Key, If-Match');
    header('Access-Control-Expose-Headers: X-Request-Id, ETag');
    header('Access-Control-Max-Age: 3600');
    if ((ifset($_SERVER, 'REQUEST_METHOD', '')) === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

setCorsHeaders();
