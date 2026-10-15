<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/assist_b3_router.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Exercise chat upload storage, staging and revision-bound records.

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server' || !hash_equals((string)getenv('SALDI_B3_TEST_KEY'), (string)($_SERVER['HTTP_X_B3_TEST'] ?? ''))) {
	http_response_code(404);
	exit;
}
require_once __DIR__ . '/../../../tests/TestCredentials.php';
require_once __DIR__ . '/../../../includes/runtime/bootstrap.php';
$cfg = saldi_runtime_config();
foreach (['HOST' => 'host', 'PORT' => 'port', 'USER' => 'user', 'PASSWORD' => 'password', 'MASTER' => 'master_db'] as $suffix => $key) {
	putenv('SALDI_ASSIST_DB_' . $suffix . '=' . $cfg[$key]);
}
putenv('SALDI_ASSIST_RECORDS_ENABLED=1');
if ($_SERVER['REQUEST_URI'] === '/record') {
	session_id((string)getenv('SALDI_B3_TEST_SESSION'));
	session_start();
	session_write_close();
	require __DIR__ . '/../../../includes/saldi_assist_record.php';
	exit;
}
require_once __DIR__ . '/../../../includes/docsIncludes/invoiceExtractionApi.php';
$GLOBALS['invoiceExtractionApiDependencies'] = [
	'key_resolver' => static fn(): string => (string)getenv('SALDI_B3_TEST_KEY'),
	'transport' => static function ($url, $headers, $body, $options): array {
		$input = json_decode($body, true);
		$pdf = base64_decode($input['image'], true);
		if (!str_starts_with($pdf, '%PDF-')) {
			throw new RuntimeException('Extractor must receive a PDF');
		}
		if ($_SERVER['REQUEST_URI'] === '/extract-failed') {
			return ['response' => '{}', 'http_code' => 200, 'error' => '', 'errno' => 0];
		}
		return ['response' => json_encode(['status' => 'success', 'extracted_data' => ['vendor' => 'Syntetisk leverandør', 'invoice_date' => '2025-06-04', 'total_amount' => '12.50', 'invoice_number' => 'B3-1', 'invoice_description' => str_repeat('Æ', 5000), 'currency' => 'DKK']]), 'http_code' => 200, 'error' => '', 'errno' => 0];
	},
];
if ($_SERVER['REQUEST_URI'] === '/web') {
	require_once __DIR__ . '/../../../includes/saldi_assist_upload.php';
	$_SESSION = ['inbox_csrf' => (string)getenv('SALDI_B3_TEST_CSRF')];
	$response = saldi_assist_upload_handle($_SERVER, $_FILES, $_POST, (string)getenv('SALDI_B3_TEST_SESSION'));
	http_response_code($response['status']);
	header('Content-Type: application/json');
	echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	exit;
}
require_once __DIR__ . '/../../../restapi/endpoints/v1/assistant/upload.php';
(new AssistantUploadEndpoint())->handleRequestMethod();
