<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_assist_b1.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Created for the mobile Assist contract.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$assistInput = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
function getallheaders() { global $assistInput; return ['Authorization' => 'Bearer ' . $assistInput['token']]; }
$_SERVER['REQUEST_METHOD'] = $assistInput['method'];
$_SERVER['REQUEST_URI'] = '/restapi/endpoints/v1/assistant/' . $assistInput['endpoint'] . '.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['REQUEST_URI'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
putenv('SALDI_ASSIST_CHAT_BASE_URL=' . $assistInput['chat_url']);
putenv('SALDI_ASSIST_CONTEXT_SECRET=' . $assistInput['context_secret']);
require_once __DIR__ . '/../../../restapi/endpoints/v1/assistant/consent.php';
class AssistSessionProbe extends AssistantSessionEndpoint
{
	protected function decodeJsonBody($rawInput) { global $assistInput; return (object)$assistInput['body']; }
}
class AssistConsentProbe extends AssistantConsentEndpoint
{
	protected function decodeJsonBody($rawInput) { global $assistInput; return (object)$assistInput['body']; }
}
register_shutdown_function(static function (): void { file_put_contents('php://fd/3', (string)http_response_code()); });
($assistInput['endpoint'] === 'session' ? new AssistSessionProbe() : new AssistConsentProbe())->handleRequestMethod();
