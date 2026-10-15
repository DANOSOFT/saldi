<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/jobs/SaldiSupportDeliverJob.php --- ai/mcp-assistant --- 2026.10.03
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
//
// Deliver only owner-approved support payloads through a signed, bounded HTTP request.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Add raw-body HMAC delivery and safe support receipts.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.

declare(strict_types=1);

require_once __DIR__ . '/../../runtime/bootstrap.php';

final class SaldiSupportDeliverJob
{
	/** @return array{status:string,result?:array{case_id?:string,value?:string},error?:string} */
	public static function run(SaldiDb $db, array $job, ?callable $transport = null): array
	{
		return $db->transaction(static function () use ($db, $job, $transport): array {
			try {
				$payload = json_decode($job['payload'], true, 64, JSON_THROW_ON_ERROR);
			} catch (JsonException) {
				return ['status' => 'failed', 'error' => 'invalid support payload'];
			}
			if (!is_array($payload) || !is_int($payload['item_id'] ?? null)) {
				return ['status' => 'failed', 'error' => 'invalid support payload'];
			}
			$item = $db->one("SELECT payload, result FROM inbox_items WHERE id = ? AND kind = 'support.report' AND status = 'applied' FOR UPDATE", [$payload['item_id']]);
			if (!$item) {
				return ['status' => 'failed', 'error' => 'support item not approved'];
			}
			$approved = json_decode($item['payload'], true, 64, JSON_THROW_ON_ERROR);
			$result = $item['result'] ? json_decode($item['result'], true, 64, JSON_THROW_ON_ERROR) : [];
			foreach (['category', 'summary', 'details', 'screen'] as $field) {
				if (($payload[$field] ?? null) !== ($approved[$field] ?? null)) {
					return ['status' => 'failed', 'error' => 'support payload changed'];
				}
			}
			if (is_string($result['support_case'] ?? null) && !in_array($result['support_case'], ['not_configured', 'failed'], true)) {
				return ['status' => 'done', 'result' => ['case_id' => $result['support_case']]];
			}
			$fail = static function (string $error) use ($db, $job): array {
				if (isset($job['attempts'], $job['max_attempts']) && (int)$job['attempts'] >= (int)$job['max_attempts']) {
					self::markFailed($db, $job);
				}
				return ['status' => 'failed', 'error' => $error];
			};
			$url = getenv('SALDI_SUPPORT_INTAKE_URL');
			$secret = getenv('SALDI_SUPPORT_INTAKE_SECRET');
			if (!$url || !$secret) {
				$result['support_case'] = 'not_configured';
				$db->exec('UPDATE inbox_items SET result = ? WHERE id = ?', [saldi_runtime_json($result), $payload['item_id']]);
				return ['status' => 'skipped', 'error' => 'not configured', 'result' => ['value' => 'not_configured']];
			}
			if (saldi_runtime_origin($url) === null) {
				return $fail('invalid support endpoint');
			}
			$keys = ['install_id', 'tenant_hash', 'item_id', 'category', 'summary', 'details', 'screen', 'company_name', 'saldi_version', 'submitted_by_hash', 'approved_at'];
			$body = [];
			foreach ($keys as $key) {
				if (!array_key_exists($key, $payload)) {
					return $fail('invalid support payload');
				}
				$body[$key] = $payload[$key];
			}
			$raw = saldi_runtime_json($body);
			$timestamp = (string)time();
			$headers = ['Content-Type: application/json', 'X-Saldi-Signature: sha256=' . hash_hmac('sha256', $timestamp . '.' . $raw, $secret), 'X-Saldi-Timestamp: ' . $timestamp];
			try {
				$response = $transport ? $transport($url, $raw, $headers, 10, false) : self::post($url, $raw, $headers);
				if (!is_array($response) || ($response['status'] ?? 0) < 200 || $response['status'] >= 300) {
					return $fail('support delivery failed');
				}
				$receipt = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
				$case = $receipt['case_id'] ?? null;
				if (!is_string($case) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $case)) {
					return $fail('invalid support response');
				}
			} catch (Throwable) {
				return $fail('support delivery failed');
			}
			$result['support_case'] = $case;
			$db->exec('UPDATE inbox_items SET result = ? WHERE id = ?', [saldi_runtime_json($result), $payload['item_id']]);
			return ['status' => 'done', 'result' => ['case_id' => $case]];
		});
	}
	/** Persist only a terminal failure, preserving a real case id or unconfigured receipt. */
	public static function markFailed(SaldiDb $db, array $job): void
	{
		$payload = json_decode($job['payload'], true);
		if (!is_array($payload) || !is_int($payload['item_id'] ?? null)) {
			return;
		}
		$row = $db->one("SELECT result FROM inbox_items WHERE id = ? AND kind = 'support.report' AND status = 'applied' FOR UPDATE", [$payload['item_id']]);
		if (!$row) {
			return;
		}
		$result = $row['result'] ? json_decode($row['result'], true, 64, JSON_THROW_ON_ERROR) : [];
		if (isset($result['support_case']) && $result['support_case'] !== 'failed') {
			return;
		}
		$result['support_case'] = 'failed';
		$db->exec('UPDATE inbox_items SET result = ? WHERE id = ?', [saldi_runtime_json($result), $payload['item_id']]);
	}
	/** @return array{status:int,body:string} */
	private static function post(string $url, string $body, array $headers): array
	{
		$handle = curl_init($url);
		$responseBody = '';
		curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
			CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_WRITEFUNCTION => static function ($stream, string $chunk) use (&$responseBody): int {
				if (strlen($responseBody) + strlen($chunk) > 65536) {
					return 0;
				}
				$responseBody .= $chunk;
				return strlen($chunk);
			}]);
		try {
			if (curl_exec($handle) === false) {
				throw new RuntimeException('Support HTTP request failed');
			}
			return ['status' => (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $responseBody];
		} finally {
			curl_close($handle);
		}
	}
	public static function shouldEnqueue(SaldiDb $db, DateTimeImmutable $nowLocal): bool
	{
		return false;
	}
}
