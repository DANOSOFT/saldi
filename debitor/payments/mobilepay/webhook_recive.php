<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/flatpay.php --- ver 5.0.0 --- 2026.10.05 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2024-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Stop processing when MobilePay HMAC authentication fails.
// 20261001 CDX/LH Fail closed on absent secrets and contain tenant/event paths.
// 20261001 CDX/LH Read provisioned tenant webhook secrets and reject stale signed requests.

// 20261001 CDX/LH Ignore invoice-link references before writing POS callback files.
// 20261005 CDX/LH Accept documented Authorization header with the legacy Vipps fallback.

/**
 * Injected by ../../../includes/connect.php, included below:
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 * @var string $sqdb
 */

// Validate the tenant path before reading any secret or writing payment data.
$db = $_GET['db'] ?? '';
if (!is_string($db) || !preg_match('/^[A-Za-z0-9_]+$/D', $db)) {
    http_response_code(400);
    exit;
}
$dbFolder = __DIR__ . '/../../../temp/' . $db;
if (!is_dir($dbFolder)) {
    http_response_code(401);
    exit;
}
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/connect.php';
$registry = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . ' linje ' . __LINE__);
$registered = $registry ? pg_query_params($registry, 'SELECT 1 FROM regnskab WHERE db=$1 LIMIT 1', [$db]) : false;
if (!$registered || !pg_fetch_assoc($registered)) {
    http_response_code(401);
    exit;
}
$tenant = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . ' linje ' . __LINE__);
$settings = $tenant ? pg_query_params($tenant, "SELECT var_value FROM settings
    WHERE var_grp='mobilepay' AND var_name='webhook_secret' ORDER BY id DESC LIMIT 1", []) : false;
$setting = $settings ? pg_fetch_assoc($settings) : false;
$secret = $setting ? $setting['var_value'] : null;
if (!is_string($secret) || strlen($secret) < 16) {
    http_response_code(401);
    exit;
}

$requestBody = file_get_contents('php://input');
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$pathAndQuery = $_SERVER['REQUEST_URI'] ?? '';
$xMsDate = $_SERVER['HTTP_X_MS_DATE'] ?? '';
$signedDate = DateTimeImmutable::createFromFormat(DateTimeInterface::RFC7231, $xMsDate, new DateTimeZone('UTC'))
    ?: DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $xMsDate);
if (!$signedDate || abs(time() - $signedDate->getTimestamp()) > 300) {
    http_response_code(401);
    exit;
}
$xMsContentSha256 = $_SERVER['HTTP_X_MS_CONTENT_SHA256'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
$expectedContentHash = base64_encode(hash('sha256', $requestBody, true));
$expectedSignedString = "$method\n$pathAndQuery\n$xMsDate;$host;$xMsContentSha256";
$expectedSignature = base64_encode(hash_hmac('sha256', $expectedSignedString, $secret, true));
$expectedAuth = "HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=$expectedSignature";
$requestHeaders = array_change_key_case(function_exists('getallheaders') ? (getallheaders() ?: []) : [], CASE_LOWER);
$authorization = ifset($requestHeaders, 'authorization',
    ifset($_SERVER, 'HTTP_AUTHORIZATION', ifset($_SERVER, 'HTTP_X_VIPPS_AUTHORIZATION', '')));
if (!hash_equals($expectedContentHash, $xMsContentSha256)
    || !is_string($authorization) || !hash_equals($expectedAuth, $authorization)) {
    http_response_code(401);
    exit;
}
if ($method !== 'POST') {
    http_response_code(405);
    exit;
}
$postData = json_decode($requestBody, true);
$filename = is_array($postData) ? ($postData['merchantQrId'] ?? $postData['reference'] ?? '') : '';
if (!is_string($filename) || !preg_match('/^[A-Za-z0-9_-]+$/D', $filename)) {
    http_response_code(400);
    exit;
}
if (is_string($postData['reference'] ?? null) && str_starts_with($postData['reference'], 'saldi-')) {
    http_response_code(200);
    exit;
}
$filePath = $dbFolder . '/data_' . $filename . '.json';
if (file_put_contents($filePath, $requestBody) === false) {
    http_response_code(500);
    exit;
}
echo "JSON data has been written to file with timestamp.";
