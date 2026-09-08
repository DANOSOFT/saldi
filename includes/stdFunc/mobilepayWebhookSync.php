<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/mobilepayWebhookSync.php --- patch 5.0.0 --- 2026-08-12 ---
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
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// Reconciles the MobilePay/Vipps webhook registration for one account with the callback
// url this installation expects: fetches an access token, lists the registered webhooks,
// deletes the ones pointing at an old url for this db and registers the right one.
//
// This lives in its own file rather than inline in includes/betweenUpdates.php so the
// api base url can be pointed at Vipps' test environment - or at a local stub - and the
// outcome asserted. It touches no database: the caller reads the credentials and writes
// the webhook secret and the reconciliation marker, and decides what to log.
//
// 20260812 Sawaneh Extracted from includes/betweenUpdates.php so the reconciliation can be
//                  tested: happy path, 4xx/5xx from each of the four calls, a 2xx list with
//                  an unexpected payload, and a stale webhook that cannot be deleted.

if (!function_exists('mobilepay_webhook_call')) {
	/**
	 * Performs one request against the Vipps API.
	 *
	 * @param string      $method   'GET', 'POST' or 'DELETE'.
	 * @param string      $url      Absolute url.
	 * @param array       $headers  Request headers.
	 * @param string|null $body     Request body, or null for none.
	 * @param array       $options  'connectTimeout' and 'timeout' in seconds.
	 * @return array{
	 *   ok: bool,        True only on a 2xx response.
	 *   httpCode: int,   Status code, 0 when no response was received.
	 *   body: string,    Response body, '' when the transport failed.
	 *   error: string,   Curl error, or '' when the transport succeeded.
	 * }
	 */
	function mobilepay_webhook_call($method, $url, array $headers, $body = null, array $options = array())
	{
		$options += array('connectTimeout' => 5, 'timeout' => 10);
		$ch = curl_init($url);
		if ($ch === false) {
			return array('ok' => false, 'httpCode' => 0, 'body' => '', 'error' => 'curl_init failed');
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$options['connectTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, (int)$options['timeout']);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		if ($method === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			if ($body !== null) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			}
		} elseif ($method !== 'GET') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}
		$raw  = curl_exec($ch);
		$err  = curl_error($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return array(
			'ok'       => ($raw !== false && $code >= 200 && $code < 300),
			'httpCode' => $code,
			'body'     => ($raw === false ? '' : (string)$raw),
			'error'    => $err,
		);
	}
}

if (!function_exists('mobilepay_webhook_events')) {
	/**
	 * The events the POS webhook subscribes to.
	 *
	 * @return array  Vipps event names.
	 */
	function mobilepay_webhook_events()
	{
		return array(
			'epayments.payment.authorized.v1',
			'user.checked-in.v1',
			'epayments.payment.cancelled.v1',
			'epayments.payment.aborted.v1',
			'epayments.payment.expired.v1',
			'epayments.payment.terminated.v1',
		);
	}
}

if (!function_exists('mobilepay_webhook_sync')) {
	/**
	 * Reconciles the registered webhooks for one account with $config['expectedUrl'].
	 *
	 * Reconciled is deliberately strict: it is true only when the expected webhook is
	 * registered AND every stale webhook for this db was actually deleted. The caller uses
	 * it as the one-shot marker, so anything less has to be retried on the next login
	 * rather than remembered as done.
	 *
	 * @param array $config  Keys:
	 *                       'apiBase'         Base url, e.g. 'https://api.vipps.no'.
	 *                       'expectedUrl'     The callback url this installation wants.
	 *                       'db'              Account database name, used to spot stale rows.
	 *                       'clientId', 'clientSecret', 'subscriptionKey', 'msn'  Credentials.
	 *                       'connectTimeout', 'timeout'  Optional, seconds.
	 * @return array{
	 *   reconciled: bool,          True when the expected webhook is registered and no stale one remains.
	 *   secret: string|null,       Secret of a newly registered webhook, or null when none was registered.
	 *   registered: bool,          True when this call registered the webhook.
	 *   staleDeleted: int,         Number of stale webhooks deleted.
	 *   staleDeleteFailed: bool,   True when a stale webhook could not be deleted.
	 *   errors: array,             Human-readable failures, in the order they happened.
	 * }
	 */
	function mobilepay_webhook_sync(array $config)
	{
		$config += array('apiBase' => 'https://api.vipps.no', 'connectTimeout' => 5, 'timeout' => 10);
		$result = array(
			'reconciled'        => false,
			'secret'            => null,
			'registered'        => false,
			'staleDeleted'      => 0,
			'staleDeleteFailed' => false,
			'errors'            => array(),
		);

		$base        = rtrim((string)$config['apiBase'], '/');
		$expectedUrl = (string)$config['expectedUrl'];
		$db          = (string)$config['db'];
		if ($expectedUrl === '' || $db === '') {
			$result['errors'][] = 'expectedUrl and db are both required';
			return $result;
		}
		$timeouts = array('connectTimeout' => $config['connectTimeout'], 'timeout' => $config['timeout']);

		$token = mobilepay_webhook_call('POST', $base . '/accesstoken/get', array(
			'Content-Type: application/json',
			'Client_id: ' . $config['clientId'],
			'Client_secret: ' . $config['clientSecret'],
			'Ocp-Apim-Subscription-Key: ' . $config['subscriptionKey'],
			'Merchant-Serial-Number: ' . $config['msn'],
			'Content-Length: 0',
		), null, $timeouts);
		if (!$token['ok']) {
			$result['errors'][] = 'accesstoken request failed (curl: ' . $token['error'] . ', http: ' . $token['httpCode'] . ')';
			return $result;
		}
		$tokenBody   = json_decode($token['body'], true);
		$accessToken = is_array($tokenBody) && isset($tokenBody['access_token']) ? $tokenBody['access_token'] : null;
		if (!$accessToken) {
			$result['errors'][] = 'accesstoken response held no access_token';
			return $result;
		}

		$headers = array(
			'Authorization: Bearer ' . $accessToken,
			'Ocp-Apim-Subscription-Key: ' . $config['subscriptionKey'],
			'Merchant-Serial-Number: ' . $config['msn'],
			'Content-Type: application/json',
		);

		$list = mobilepay_webhook_call('GET', $base . '/webhooks/v1/webhooks', $headers, null, $timeouts);
		if (!$list['ok']) {
			// A non-2xx list must not be read as "no webhooks registered": that would
			// register a duplicate and report success.
			$result['errors'][] = 'webhook list request failed (curl: ' . $list['error'] . ', http: ' . $list['httpCode'] . ')';
			return $result;
		}
		$listBody = json_decode($list['body'], true);
		if (!is_array($listBody) || !isset($listBody['webhooks']) || !is_array($listBody['webhooks'])) {
			// A 2xx body still has to have the expected shape.
			$result['errors'][] = 'webhook list returned http ' . $list['httpCode'] . ' with no webhooks array';
			return $result;
		}

		$expectedExists = false;
		foreach ($listBody['webhooks'] as $webhook) {
			// Entries from a 2xx body are still untrusted input.
			if (!is_array($webhook) || !isset($webhook['url'])) {
				continue;
			}
			if ($webhook['url'] === $expectedUrl) {
				$expectedExists = true;
				continue;
			}
			// The db has to match exactly. A substring test would treat every account whose
			// name merely starts with this one's as ours - with databases 'acme' and 'acme2'
			// a run for 'acme' would delete acme2's webhook and stop Vipps delivering that
			// tenant's payment callbacks.
			$webhookPath  = (string)parse_url($webhook['url'], PHP_URL_PATH);
			$webhookQuery = (string)parse_url($webhook['url'], PHP_URL_QUERY);
			$webhookArgs  = array();
			parse_str($webhookQuery, $webhookArgs);
			if (basename($webhookPath) !== 'webhook_recive.php'
				|| !isset($webhookArgs['db']) || $webhookArgs['db'] !== $db) {
				continue; // another account's webhook, or not a POS callback at all
			}
			// The id comes from the same untrusted body: an array or object here would make
			// rawurlencode() raise a TypeError and take down the login request that runs
			// this reconciliation.
			if (!isset($webhook['id']) || !is_scalar($webhook['id']) || (string)$webhook['id'] === '') {
				$result['errors'][] = 'stale webhook ' . $webhook['url'] . ' has no usable id and cannot be deleted';
				$result['staleDeleteFailed'] = true;
				continue;
			}
			$delete = mobilepay_webhook_call('DELETE', $base . '/webhooks/v1/webhooks/' . rawurlencode($webhook['id']), $headers, null, $timeouts);
			// Transport success is not deletion: on a 4xx/5xx the stale webhook stays
			// active and Vipps keeps delivering payment callbacks to the old url.
			if (!$delete['ok']) {
				$result['errors'][] = 'delete failed for ' . $webhook['url'] . ' (curl: ' . $delete['error'] . ', http: ' . $delete['httpCode'] . ')';
				$result['staleDeleteFailed'] = true;
				continue;
			}
			$result['staleDeleted']++;
		}

		if (!$expectedExists) {
			$register = mobilepay_webhook_call('POST', $base . '/webhooks/v1/webhooks', $headers, json_encode(array(
				'url'    => $expectedUrl,
				'events' => mobilepay_webhook_events(),
			)), $timeouts);
			if (!$register['ok']) {
				$result['errors'][] = 'webhook register request failed (curl: ' . $register['error'] . ', http: ' . $register['httpCode'] . ')';
				return $result;
			}
			$registerBody = json_decode($register['body'], true);
			if (!is_array($registerBody) || empty($registerBody['secret'])) {
				$result['errors'][] = 'webhook register response held no secret';
				return $result;
			}
			$result['secret']     = $registerBody['secret'];
			$result['registered'] = true;
		}

		// Strict on purpose: a stale webhook that is still registered means this db is not
		// reconciled, however well the registration itself went.
		$result['reconciled'] = !$result['staleDeleteFailed'];
		return $result;
	}
}
?>
