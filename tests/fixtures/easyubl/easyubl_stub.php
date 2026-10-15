<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/fixtures/easyubl/easyubl_stub.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Created.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($path, '/'));
$scenario = $parts[0];
file_put_contents('/tmp/saldi-b5-stub-events.log', $path . "\n", FILE_APPEND | LOCK_EX);
if (str_starts_with($scenario, 'slow-') && str_contains($path, '/SendDocuments/')) {
    usleep(800000);
}
header('Content-Type: application/json');
if (str_contains($path, '/Company/')) {
    echo json_encode($scenario === 'company_failure' ? ['error' => 'Rejected company'] :
        ['companyID' => 'local-company', 'hasEndpointPeppol' => true]);
} elseif (str_contains($path, '/HumanReadable/')) {
    echo json_encode(['html' => '<html>Local invoice</html>']);
} elseif ($scenario === 'empty') {
    http_response_code(500);
} elseif ($scenario === 'invalid') {
    echo '{invalid';
} elseif ($scenario === 'api_error') {
    echo json_encode(['errNo' => 4003, 'message' => 'Recipient is not registered']);
} else {
    echo json_encode(['errNo' => 0, 'base64EncodedDocumentXml' => base64_encode('<Invoice>Local document</Invoice>')]);
}
