<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/saldi_assist_upload.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Add the same-origin chat upload twin.

declare(strict_types=1);

require_once __DIR__ . '/runtime/bootstrap.php';
require_once __DIR__ . '/automation/inboxCsrf.php';
require_once __DIR__ . '/automation/SaldiAttachmentDescriptor.php';

/** Same-origin multipart uploads, sharing storage and extraction with mobile. @return array{status:int,body:array} */
function saldi_assist_upload_handle(array $server, array $files, array $post, string $sessionId): array
{
	$respond = static fn(int $status, array $data): array => ['status' => $status, 'body' => $data];
	$method = $server['REQUEST_METHOD'] ?? '';
	if ($method !== 'POST') {
		return $respond(405, ['error' => 'VALIDATION_ERROR']);
	}
	$origin = saldi_runtime_request_origin($server);
	$source = $server['HTTP_ORIGIN'] ?? $server['HTTP_REFERER'] ?? '';
	if ($origin === null || saldi_runtime_origin($source) !== $origin
		|| (isset($server['HTTP_ORIGIN']) && !preg_match('~^https?://[^/?#]+$~D', $source))
		|| !saldi_inbox_csrf_check($server['HTTP_X_SALDI_CSRF'] ?? null)) {
		return $respond(403, ['error' => 'PERMISSION_DENIED']);
	}
	$db = null;
	try {
		$online = saldi_runtime_master()->one('SELECT db, brugernavn, revisor FROM online WHERE session_id = ? ORDER BY logtime DESC LIMIT 1', [$sessionId]);
		if ($sessionId === '' || !$online) {
			return $respond(401, ['error' => 'UNAUTHENTICATED']);
		}
		if (in_array($online['revisor'], [true, 1, '1', 't', 'true', 'on'], true) || $online['db'] === saldi_runtime_config()['master_db']) {
			return $respond(403, ['error' => 'PERMISSION_DENIED']);
		}
		$entry = saldi_runtime_tenant_by_db($online['db']);
		$db = $entry['db'];
		$userId = (int)$db->value('SELECT id FROM brugere WHERE brugernavn = ?', [$online['brugernavn']]);
		$ctx = saldi_runtime_context_for_user($db, $entry['regnskab'], $userId, 'user:' . $userId);
		SaldiPermissionService::require($ctx, $db, 'inbox.read');
		$purpose = ifset($post, 'purpose');
		if ($purpose !== null && !is_string($purpose)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid purpose', ['reason' => 'invalid_purpose']);
		}
		return $respond(200, SaldiAttachmentDescriptor::upload($ctx, $db, $files, $purpose));
	} catch (SaldiRuntimeError $e) {
		return $respond($e->errorCode === 'VALIDATION_ERROR' ? 422 : $e->httpStatus(), ['error' => $e->errorCode, 'details' => $e->detail]);
	} catch (Throwable $e) {
		error_log('Assist upload unavailable');
		return $respond(503, ['error' => 'UNAVAILABLE']);
	} finally {
		if ($db !== null && PHP_SAPI === 'cli') {
			$db->close();
		}
	}
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
	session_start();
	$assistUploadResponse = saldi_assist_upload_handle($_SERVER, $_FILES, $_POST, session_id());
	session_write_close();
	http_response_code($assistUploadResponse['status']);
	header('Content-Type: application/json');
	header('Cache-Control: no-store');
	echo json_encode($assistUploadResponse['body'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}
