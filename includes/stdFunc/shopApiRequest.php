<?php
// --- includes/stdFunc/shopApiRequest.php --- ver 5.0.0 --- 2026-10-27 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Sends webshop API requests without a shell, captures the outcome and logs failures.
// Replaces the old "nohup curl '$url' > ../temp/$db/curl.txt &" pattern, which threw
// the exit status away, let unescaped item data reach the shell and had every call
// overwrite the same curl.txt.
// 20260727 Sawaneh Created.
// 20260812 Sawaneh Review: a wall-clock budget bounds the whole sync in one request, only
//                  transport-class failures degrade an endpoint (a 4xx is about one item,
//                  not the host), and the shared php error log gets scheme/host/path
//                  instead of the query string and the response body.
// 20261016 CL/MJ SST-876 A 2xx answer whose body is a rejection now counts as a failure.
//                  Havemoebelland's stock stopped reaching DirekteImport.dk and Saldi
//                  reported nothing for days, because the shop adapter answers 200 to
//                  everything and only the status was looked at.
// 20261022 CL/MJ SST-876 An endpoint is degraded by scheme, host, port and path, not by host
//                  alone. Three shop adapters on one hosting account shared one failure
//                  state, so one dead adapter stopped the other two shops for the rest of
//                  the request.
// 20261022 CL/MJ SST-876 Added shopApiRequestParallel(): the three webshops are called at
//                  once, so the budget is charged the slowest of them rather than the sum of
//                  all three. The degradation map, the failure counts and the budget moved
//                  into shopApiSyncState() so both senders share one copy, and the judgement
//                  of a shop's answer moved into shopApiOutcome() so they cannot drift apart.
// 20261023 CDX/MJ SST-876 Preserve each payload context when batching multiple items across shops.
// 20261027 CDX/MJ SST-876 Read parallel transfer completion results and shell curl exit status so incomplete HTTP 200 responses remain retryable failures.

if (!function_exists('shopApiUrl')) {
	/**
	 * Builds a webshop API url from an endpoint and a parameter list.
	 *
	 * Every value is url-encoded by http_build_query, so item numbers, barcodes and
	 * prices cannot break out of the query string. Endpoints stored without a scheme
	 * get http:// prepended, which is what the curl command line defaulted to.
	 *
	 * @param string $endpoint  Api endpoint from grupper.box4/box5/box6, may already carry a query string.
	 * @param array  $params    Parameters as name => value. null is sent as an empty value.
	 * @return string  The full url, or an empty string if the endpoint is unusable.
	 */
	function shopApiUrl($endpoint, array $params = array()) {
		$endpoint = trim((string)$endpoint);
		if ($endpoint === '') {
			return '';
		}
		if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $endpoint)) {
			$endpoint = 'http://' . $endpoint;
		}
		$parts = parse_url($endpoint);
		if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
			return '';
		}
		if (!in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
			return '';
		}
		$clean = array();
		foreach ($params as $name => $value) {
			if (is_array($value) || is_object($value)) {
				continue;
			}
			if (is_bool($value)) {
				$value = $value ? '1' : '0';
			}
			$clean[$name] = ($value === null ? '' : (string)$value);
		}
		// A fragment has to be lifted off first: appending the query after it would leave
		// the parameters inside the fragment, which is never sent to the server, so the
		// shop would receive no sku, price or stock at all.
		$fragment = '';
		$hash = strpos($endpoint, '#');
		if ($hash !== false) {
			$fragment = substr($endpoint, $hash);
			$endpoint = substr($endpoint, 0, $hash);
		}
		$query = http_build_query($clean);
		if ($query === '') {
			return $endpoint . $fragment;
		}
		return $endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . $query . $fragment;
	}
}

if (!function_exists('shopApiResponseRejected')) {
	/**
	 * Decides whether a response the transport accepted is really a rejection.
	 *
	 * A webshop adapter is a customer-owned script, and several answer 200 to everything -
	 * Havemoebelland's prints the stock figure and nothing else, whether it updated the shop
	 * or not (SST-876). Judging only the http status therefore reports a successful sync for
	 * an update that never happened, which is why those stock figures stood still for days
	 * without a single error anywhere in Saldi.
	 *
	 * Only shapes that say so explicitly count: a json payload denying success, or a body
	 * that begins as a php error page. A bare figure or an empty body stays a success,
	 * because that is what the working adapters answer and this must not turn every
	 * existing installation's sync into an error.
	 *
	 * Nothing here may depend on std_func.php: api/updateShopCostPrice.php requires this
	 * file on its own, so ifset() and its siblings are not available.
	 *
	 * @param string $body  Response body as received.
	 * @return string  Empty when the answer looks accepted, otherwise a short reason for the log.
	 */
	function shopApiResponseRejected($body) {
		$body = (string)$body;
		if (trim($body) === '') {
			return '';
		}
		$data = json_decode($body, true);
		if (is_array($data)) {
			$denied = (array_key_exists('success', $data) && $data['success'] === false)
				|| (array_key_exists('ok', $data) && $data['ok'] === false)
				|| !empty($data['error'])
				|| !empty($data['errors'])
				|| (isset($data['data']['status']) && (int)$data['data']['status'] >= 400)
				|| (isset($data['status']) && in_array($data['status'], array('error', 'failed', 'failure'), true));
			if ($denied) {
				return 'shop answered http 2xx with a rejection payload';
			}
		}
		if (preg_match('/^\s*(?:error\b|fatal error\b|<br\s*\/?>\s*<b>(?:warning|fatal error))/i', $body)) {
			return 'shop answered http 2xx with an error page';
		}
		return '';
	}
}

if (!function_exists('shopApiRequest')) {
	/**
	 * Performs a webshop API request and reports whether the shop actually accepted it.
	 *
	 * The request is executed with the curl extension when available, otherwise through
	 * a fully escaped command line. Connection errors and http status >= 400 are treated
	 * as failures; connection level errors are retried and then written to the caller's
	 * log and to the php error log, so a dead shop endpoint is never silently dropped.
	 * Once an endpoint has proved unreachable in this php process, the remaining calls to
	 * that same endpoint are skipped (and logged) instead of timing out one by one.
	 *
	 * @param string        $endpoint  Api endpoint from grupper.box4/box5/box6.
	 * @param array         $params    Query parameters as name => value.
	 * @param resource|null $log       Open handle to the account rest_api.log, or null.
	 * @param array{
	 *   context: string,         Short text naming the caller, used in log lines.
	 *   connectTimeout: int,     Seconds to wait for the connection, default 5.
	 *   timeout: int,            Seconds to wait for the whole request, default 10.
	 *   retries: int,            Extra attempts after a connection level failure, default 1.
	 *   userAgent: string,       User-Agent header to send.
	 *   maxBodyLog: int,         Characters of the response body kept in the log, default 500.
	 * } $options  All keys optional.
	 * @return array{
	 *   ok: bool,          True when the shop answered with http status < 400 and its body
	 *                      was not itself a rejection (see shopApiResponseRejected()).
	 *   url: string,       The url that was called, empty if the endpoint was rejected.
	 *   httpCode: int,     Http status code, 0 when no response was received.
	 *   error: string,     Empty on success, otherwise a short failure description.
	 *   body: string,      Response body, empty when it could not be captured.
	 *   attempts: int,     Number of attempts made.
	 *   skipped: bool,     True when the call was skipped because the endpoint is known dead.
	 *   transient: bool,   True when the latest transfer failed in a retryable way, including after HTTP 200.
	 * }
	 */
	function shopApiRequest($endpoint, array $params = array(), $log = null, array $options = array()) {
		$state = &shopApiSyncState();

		$options += array(
			'context'        => '',
			'connectTimeout' => 5,
			'timeout'        => 10,
			'retries'        => 1,
			'userAgent'      => 'Saldi shop sync',
			'maxBodyLog'     => 500,
			'failThreshold'  => 3,
			'timeBudget'     => 30,
		);
		$result = array(
			'ok'       => false,
			'url'      => '',
			'httpCode' => 0,
			'error'    => '',
			'body'     => '',
			'attempts' => 0,
			'skipped'  => false,
			'transient' => false,
		);

		$url = shopApiUrl($endpoint, $params);
		if ($url === '') {
			$result['error'] = 'invalid api endpoint';
			shopApiLogFailure($log, $options['context'], (string)$endpoint, $result);
			return $result;
		}
		$result['url'] = $url;

		$endpointKey = shopApiEndpointKey($url);
		if (isset($state['degraded'][$endpointKey])) {
			$result['skipped'] = true;
			$result['error']   = 'endpoint degraded earlier in this request: ' . $state['degraded'][$endpointKey];
			shopApiLogFailure($log, $options['context'], $url, $result);
			return $result;
		}

		// One item can fan out to three endpoints, and every part item in $partOf adds
		// more calls, so per-call timeouts alone do not bound how long the request
		// thread is held: a shop that answers slowly but successfully never counts as
		// degraded. The budget covers the whole sync in this php process. Running out
		// is logged exactly as loudly as a failure - a truncated sync must not be
		// quieter than the fire-and-forget behaviour this file replaced.
		$budget = (float)$options['timeBudget'];

		$attempts = 1 + max(0, (int)$options['retries']);
		for ($attempt = 1; $attempt <= $attempts; $attempt++) {
			// The budget is a deadline, not a pre-flight check. Testing it only before the
			// first attempt would let a call start with milliseconds left and then block for
			// the whole timeout, so what remains also caps this attempt's timeouts. Less
			// than a second left counts as used up: curl takes whole seconds, so a shorter
			// cap would overshoot the budget anyway.
			$remaining = ($budget > 0) ? $budget - $state['spent'] : 0;
			if ($budget > 0 && $remaining < 1) {
				if ($attempt === 1) {
					$result['skipped'] = true;
					$result['error']   = 'shop sync time budget of ' . (int)$budget . 's used up in this request';
					shopApiLogFailure($log, $options['context'], $url, $result);
					return $result;
				}
				break;
			}
			$callOptions = $options;
			if ($budget > 0) {
				$callOptions['timeout']        = max(1, min((int)$options['timeout'], (int)floor($remaining)));
				$callOptions['connectTimeout'] = max(1, min((int)$options['connectTimeout'], (int)$callOptions['timeout']));
			}
			$result['attempts'] = $attempt;
			$started = microtime(true);
			$call = function_exists('curl_init')
				? shopApiCurlCall($url, $callOptions)
				: shopApiShellCall($url, $callOptions);
			$state['spent'] += microtime(true) - $started;
			$result['httpCode'] = $call['httpCode'];
			$result['body']     = $call['body'];
			$result['transient'] = !empty($call['transient']);

			$outcome = shopApiOutcome($call);
			if ($outcome['state'] === 'ok') {
				$result['ok']    = true;
				$result['error'] = '';
				$state['failCount'][$endpointKey] = 0;
				if (is_resource($log)) {
					fwrite($log, date('Y-m-d H:i:s') . ' shop sync ok ' . $options['context'] . ' http ' . $call['httpCode'] . ' ' . $url . "\n");
				}
				return $result;
			}
			if ($outcome['state'] === 'refused') {
				// The shop is answering perfectly well and refusing this one item, so this says
				// nothing about the host: no retry, and the endpoint is not degraded for the items
				// that follow. It is logged like any other failure and the caller is told.
				$result['error'] = $outcome['error'];
				shopApiLogFailure($log, $options['context'], $url, $result, $options['maxBodyLog']);
				return $result;
			}

			$result['error'] = $outcome['error'];
			if (!$call['transient'] || $attempt === $attempts) {
				// Only transport-class failures say anything about the host. A 4xx is about
				// this one item - an unknown sku or a bad parameter - and the shop is
				// answering perfectly well, so it must not count towards degrading the
				// endpoint for every other item in this request.
				shopApiNoteTransportFailure($call, $endpointKey, $result['error'], (int)$options['failThreshold']);
				break;
			}
			// The pause before a retry is part of the time the request thread is held, so it
			// is counted and capped like the call itself.
			$pause = 300000 * $attempt;
			if ($budget > 0) {
				$pause = (int)min($pause, max(0, ($budget - $state['spent']) * 1000000));
			}
			if ($pause > 0) {
				usleep($pause);
				$state['spent'] += $pause / 1000000;
			}
		}

		shopApiLogFailure($log, $options['context'], $url, $result, $options['maxBodyLog']);
		return $result;
	}
}

if (!function_exists('shopApiCurlCall')) {
	/**
	 * Executes one request with the curl extension.
	 *
	 * @param string $url      Full url to call.
	 * @param array  $options  Normalised options from shopApiRequest().
	 * @return array{
	 *   httpCode: int,    Http status code, 0 when no response was received.
	 *   body: string,     Response body.
	 *   error: string,      Empty on transport success, otherwise the curl error.
	 *   transient: bool,    True when a retry may help (connection error or 5xx).
	 *   unreachable: bool,  True when no response at all was received.
	 * }
	 */
	function shopApiCurlCall($url, array $options) {
		$ch = curl_init($url);
		if ($ch === false) {
			return array('httpCode' => 0, 'body' => '', 'error' => 'curl_init failed', 'transient' => false, 'unreachable' => false);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$options['connectTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, (int)$options['timeout']);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
		curl_setopt($ch, CURLOPT_USERAGENT, (string)$options['userAgent']);
		if (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
			curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		}
		$body     = curl_exec($ch);
		$errno    = curl_errno($ch);
		$error    = $errno ? 'curl error ' . $errno . ': ' . curl_error($ch) : '';
		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return array(
			'httpCode'    => $httpCode,
			'body'        => is_string($body) ? $body : '',
			'error'       => $error,
			'transient'   => ($errno !== 0 || $httpCode === 0 || $httpCode >= 500),
			'unreachable' => ($httpCode === 0),
		);
	}
}

if (!function_exists('shopApiShellCall')) {
	/**
	 * Executes one request with the curl command line, used only when ext-curl is missing.
	 *
	 * The url is passed through escapeshellarg, so nothing in it can be interpreted by
	 * the shell, and the body goes to a private temporary file instead of a shared one.
	 *
	 * @param string $url      Full url to call.
	 * @param array  $options  Normalised options from shopApiRequest().
	 * @return array{
	 *   httpCode: int,    Http status code, 0 when no response was received.
	 *   body: string,     Response body.
	 *   error: string,      Empty on transport success, otherwise a short description.
	 *   transient: bool,    True when a retry may help.
	 *   unreachable: bool,  True when no response at all was received.
	 * }
	 */
	function shopApiShellCall($url, array $options) {
		if (!function_exists('shell_exec')) {
			return array('httpCode' => 0, 'body' => '', 'error' => 'no http client available (ext-curl and shell_exec both missing)', 'transient' => false, 'unreachable' => false);
		}
		$bodyFile = tempnam(sys_get_temp_dir(), 'shopsync');
		if ($bodyFile === false) {
			return array('httpCode' => 0, 'body' => '', 'error' => 'could not create temporary file', 'transient' => false, 'unreachable' => false);
		}
		$cmd = 'curl -s -S -L --max-redirs 3 --proto =http,https'
			. ' --connect-timeout ' . (int)$options['connectTimeout']
			. ' --max-time ' . (int)$options['timeout']
			. ' -A ' . escapeshellarg((string)$options['userAgent'])
			. ' -o ' . escapeshellarg($bodyFile)
			. ' -w ' . escapeshellarg("\nSALDI_HTTP:%{http_code}\n")
			. ' ' . escapeshellarg($url) . ' 2>&1; shop_curl_status=$?; printf "\nSALDI_EXIT:%s\n" "$shop_curl_status"';
		$out      = shell_exec($cmd);
		$body     = (string)@file_get_contents($bodyFile);
		@unlink($bodyFile);

		// stderr is merged into the output, so read the status from its own marker rather
		// than from trailing digits, which could come from an error message instead.
		$out      = trim((string)$out);
		$httpCode = preg_match('/SALDI_HTTP:(\d{3})/', $out, $m) ? (int)$m[1] : 0;
		$exitCode = preg_match('/(?:^|\n)SALDI_EXIT:(\d+)$/D', $out, $m) ? (int)$m[1] : -1;
		$error = $exitCode !== 0 || !$httpCode
			? 'curl command failed (exit ' . $exitCode . '): ' . ($out === '' ? 'no output' : $out) : '';

		return array(
			'httpCode'    => $httpCode,
			'body'        => $body,
			'error'       => $error,
			'transient'   => ($exitCode !== 0 || $httpCode === 0 || $httpCode >= 500),
			'unreachable' => ($httpCode === 0),
		);
	}
}

if (!function_exists('shopApiRedactUrl')) {
	/**
	 * Reduces a shop url to scheme, host and path for the shared php error log.
	 *
	 * @param string $url  Full url that was called.
	 * @return string  'https://host/path' with the query string replaced by '?...', or
	 *                 '(url withheld)' when the url cannot be parsed.
	 */
	function shopApiRedactUrl($url) {
		$parts = parse_url((string)$url);
		if (!$parts || empty($parts['host'])) {
			return '(url withheld)';
		}
		$redacted = (isset($parts['scheme']) ? $parts['scheme'] . '://' : '') . $parts['host'];
		if (isset($parts['port'])) {
			$redacted .= ':' . $parts['port'];
		}
		$redacted .= isset($parts['path']) ? $parts['path'] : '';
		if (isset($parts['query']) && $parts['query'] !== '') {
			$redacted .= '?...';
		}
		return $redacted;
	}
}

if (!function_exists('shopApiLogFailure')) {
	/**
	 * Writes a failed webshop call to the account log and to the php error log.
	 *
	 * @param resource|null $log         Open handle to the account rest_api.log, or null.
	 * @param string        $context     Short text naming the caller.
	 * @param string        $url         Url that was called, or the raw endpoint when it was rejected.
	 * @param array         $result      Result array as built by shopApiRequest().
	 * @param int           $maxBodyLog  Characters of the response body to keep.
	 * @return void
	 */
	function shopApiLogFailure($log, $context, $url, array $result, $maxBodyLog = 500) {
		$head = date('Y-m-d H:i:s') . ' SHOP SYNC FAILED ' . ($context !== '' ? $context . ' ' : '')
			. 'http ' . (int)$result['httpCode'] . ' after ' . (int)$result['attempts'] . ' attempt(s)'
			. ($result['skipped'] ? ' (skipped)' : '')
			. ' - ' . $result['error'];

		// The account log is inside the account's own temp directory, so it may hold the
		// whole url and the response. The php error log is shared between the accounts on
		// the server and read by operators, so it gets scheme, host and path only - the
		// query string carries item numbers, barcodes, cost and sales prices.
		$line = $head . ' - ' . $url;
		if (isset($result['body']) && $result['body'] !== '' && !$result['skipped']) {
			$body = preg_replace('/\s+/', ' ', $result['body']);
			$line .= ' - response: ' . substr($body, 0, max(0, (int)$maxBodyLog));
		}
		if (is_resource($log)) {
			fwrite($log, $line . "\n");
		}
		error_log('saldi: ' . $head . ' - ' . shopApiRedactUrl($url));
	}
}
if (!function_exists('shopApiSyncState')) {
	/**
	 * The state both senders share for the length of one php request.
	 *
	 * Returned by reference on purpose: shopApiRequest() and shopApiRequestParallel() have to
	 * work on one copy. An endpoint that one of them found dead must be skipped by the other,
	 * and the time budget has to be a single budget or it is not a budget at all.
	 *
	 * @return array{degraded: array<string,string>, failCount: array<string,int>, spent: float}
	 */
	function &shopApiSyncState() {
		static $state = array('degraded' => array(), 'failCount' => array(), 'spent' => 0.0);
		return $state;
	}
}

if (!function_exists('shopApiEndpointKey')) {
	/**
	 * The identity of an endpoint for the degradation map.
	 *
	 * Scheme, host, port and path. The path matters because an install's webshops are normally
	 * several adapter scripts in one hosting account, differing only in the file name, and
	 * keying on the host alone let one dead adapter skip the others.
	 *
	 * @param string $url  Full url that was called.
	 * @return string  Key for the degradation and failure maps.
	 */
	function shopApiEndpointKey($url) {
		$parts = parse_url((string)$url);
		if (!is_array($parts) || empty($parts['host'])) {
			return strtolower((string)$url);
		}
		$key = strtolower((isset($parts['scheme']) ? $parts['scheme'] : 'http') . '://' . $parts['host']);
		if (isset($parts['port'])) {
			$key .= ':' . $parts['port'];
		}
		return $key . (isset($parts['path']) ? $parts['path'] : '');
	}
}

if (!function_exists('shopApiOutcome')) {
	/**
	 * Decides what one completed call means, for both senders.
	 *
	 * One function, because two senders judging a shop's answer by two different rules is the
	 * defect this ticket is about. A 2xx whose body refuses the update is a refusal, not a
	 * success, and a refusal says nothing about the host.
	 *
	 * @param array $call  Result of shopApiCurlCall(), shopApiShellCall() or one slot of
	 *                     shopApiCurlMultiCall().
	 * @return array{state: string, error: string}  state is 'ok', 'refused' or 'failed'.
	 */
	function shopApiOutcome(array $call) {
		if ($call['error'] === '' && $call['httpCode'] > 0 && $call['httpCode'] < 400) {
			$rejected = shopApiResponseRejected($call['body']);
			if ($rejected === '') {
				return array('state' => 'ok', 'error' => '');
			}
			return array('state' => 'refused', 'error' => $rejected);
		}
		return array(
			'state' => 'failed',
			'error' => $call['error'] !== '' ? $call['error'] : 'http status ' . (int)$call['httpCode'],
		);
	}
}

if (!function_exists('shopApiNoteTransportFailure')) {
	/**
	 * Counts a transport-class failure against an endpoint, and degrades it when it is dead.
	 *
	 * Only transport-class failures say anything about the host. A 4xx is about one item - an
	 * unknown sku or a bad parameter - and the shop is answering perfectly well, so it must not
	 * count towards degrading the endpoint for every other item in the request.
	 *
	 * @param array  $call           The call that failed.
	 * @param string $endpointKey    From shopApiEndpointKey().
	 * @param string $error          The failure text to record.
	 * @param int    $failThreshold  Transient failures tolerated before the endpoint is degraded.
	 * @return void
	 */
	function shopApiNoteTransportFailure(array $call, $endpointKey, $error, $failThreshold = 3) {
		if (empty($call['transient'])) {
			return;
		}
		$state = &shopApiSyncState();
		$state['failCount'][$endpointKey] = (isset($state['failCount'][$endpointKey]) ? $state['failCount'][$endpointKey] : 0) + 1;
		// No response at all means every further call would pay the same timeout, so stop at
		// once. A 5xx needs to repeat before the endpoint counts as degraded.
		if (!empty($call['unreachable']) || $state['failCount'][$endpointKey] >= (int)$failThreshold) {
			$state['degraded'][$endpointKey] = $error;
		}
	}
}

if (!function_exists('shopApiCurlMultiCall')) {
	/**
	 * Executes one request per url at the same time.
	 *
	 * @param array<string|int,string> $urls      Slot => full url.
	 * @param int                      $timeout   Seconds for the whole request.
	 * @param int                      $connect   Seconds to wait for the connection.
	 * @param string                   $userAgent User-Agent header to send.
	 * @return array<string|int,array{httpCode:int,body:string,error:string,transient:bool,unreachable:bool}>
	 */
	function shopApiCurlMultiCall(array $urls, $timeout, $connect, $userAgent) {
		$multi   = curl_multi_init();
		$handles = array();
		$bodies  = array();
		$out     = array();
		foreach ($urls as $slot => $url) {
			$handle = curl_init($url);
			if ($handle === false) {
				$out[$slot] = array('httpCode' => 0, 'body' => '', 'error' => 'curl_init failed', 'transient' => false, 'unreachable' => false);
				continue;
			}
			$bodies[$slot] = '';
			curl_setopt_array($handle, array(
				CURLOPT_CONNECTTIMEOUT => (int)$connect,
				CURLOPT_TIMEOUT        => (int)$timeout,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 3,
				CURLOPT_USERAGENT      => (string)$userAgent,
				// An adapter that answers with a whole html page must not be able to exhaust
				// the request's memory, so the body is bounded as it arrives - the same bound
				// lager/shopStockIncludes/sync.php uses.
				CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$bodies, $slot) {
					if (strlen($bodies[$slot]) < 16384) {
						$bodies[$slot] .= substr($chunk, 0, 16384 - strlen($bodies[$slot]));
					}
					return strlen($chunk);
				},
			));
			if (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
				curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
				curl_setopt($handle, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			}
			$handles[$slot] = $handle;
			curl_multi_add_handle($multi, $handle);
		}
		$running = null;
		do {
			$status = curl_multi_exec($multi, $running);
			if ($running) {
				curl_multi_select($multi, 1.0);
			}
		} while ($running && $status === CURLM_OK);

		$completed = array();
		while ($info = curl_multi_info_read($multi)) {
			foreach ($handles as $slot => $handle) {
				if ($handle === $info['handle']) {
					$completed[$slot] = (int)$info['result'];
					break;
				}
			}
		}
		foreach ($handles as $slot => $handle) {
			// A missing DONE message is not a successful completed transfer.
			$errno = $completed[$slot] ?? CURLE_FAILED_INIT;
			$httpCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
			$out[$slot] = array(
				'httpCode'    => $httpCode,
				'body'        => isset($bodies[$slot]) ? $bodies[$slot] : '',
				'error'       => $errno ? 'curl error ' . $errno . ': ' . curl_strerror($errno) : '',
				'transient'   => ($errno !== 0 || $httpCode === 0 || $httpCode >= 500),
				'unreachable' => ($httpCode === 0),
			);
			curl_multi_remove_handle($multi, $handle);
			curl_close($handle);
		}
		curl_multi_close($multi);
		return $out;
	}
}

if (!function_exists('shopApiRequestParallel')) {
	/**
	 * Sends the same parameters to every configured webshop at once.
	 *
	 * Three shops answered one after the other cost three times one shop's latency, and that is
	 * what exhausts the time budget on a document with many lines. Answered together they cost
	 * the slowest one, and the budget is charged the wall clock rather than the sum - which is
	 * the whole reason for sending them together.
	 *
	 * The degradation map, the failure counts and the budget are the ones shopApiRequest() uses,
	 * through shopApiSyncState(), so the two senders cannot disagree about a dead shop or about
	 * how much time is left.
	 *
	 * @param array<string|int,string> $endpoints  Slot => endpoint, as box4/box5/box6.
	 * @param array                    $params     Query parameters as name => value.
	 * @param resource|null            $log        Open handle to the account rest_api.log, or null.
	 * @param array                    $options    As shopApiRequest(); optional contexts maps each slot to its log context.
	 * @return array<string|int,array>  Slot => result array shaped exactly like shopApiRequest()'s.
	 */
	function shopApiRequestParallel(array $endpoints, array $params = array(), $log = null, array $options = array()) {
		$options += array(
			'context'        => '',
			'connectTimeout' => 5,
			'timeout'        => 10,
			'retries'        => 1,
			'userAgent'      => 'Saldi shop sync',
			'maxBodyLog'     => 500,
			'failThreshold'  => 3,
			'timeBudget'     => 30,
		);
		// Without a multi handle there is nothing to gain and no reason to behave differently:
		// one at a time through the single sender, which is the code path every install used
		// before this function existed.
		if (!function_exists('curl_multi_init')) {
			$results = array();
			foreach ($endpoints as $slot => $endpoint) {
				$results[$slot] = shopApiRequest($endpoint, $params, $log, array('context' => ($options['contexts'][$slot] ?? $options['context'])) + $options);
			}
			return $results;
		}

		$state   = &shopApiSyncState();
		$results = array();
		$pending = array();
		foreach ($endpoints as $slot => $endpoint) {
			$result = array('ok' => false, 'url' => '', 'httpCode' => 0, 'error' => '', 'body' => '', 'attempts' => 0, 'skipped' => false, 'transient' => false);
			$url = shopApiUrl($endpoint, $params);
			if ($url === '') {
				$result['error'] = 'invalid api endpoint';
				shopApiLogFailure($log, ($options['contexts'][$slot] ?? $options['context']), (string)$endpoint, $result);
				$results[$slot] = $result;
				continue;
			}
			$result['url'] = $url;
			$key = shopApiEndpointKey($url);
			if (isset($state['degraded'][$key])) {
				$result['skipped'] = true;
				$result['error']   = 'endpoint degraded earlier in this request: ' . $state['degraded'][$key];
				shopApiLogFailure($log, ($options['contexts'][$slot] ?? $options['context']), $url, $result);
				$results[$slot] = $result;
				continue;
			}
			$results[$slot] = $result;
			$pending[$slot] = $url;
		}
		if (!$pending) {
			return $results;
		}

		$budget    = (float)$options['timeBudget'];
		$remaining = ($budget > 0) ? $budget - $state['spent'] : 0;
		if ($budget > 0 && $remaining < 1) {
			foreach ($pending as $slot => $url) {
				$results[$slot]['skipped'] = true;
				$results[$slot]['error']   = 'shop sync time budget of ' . (int)$budget . 's used up in this request';
				shopApiLogFailure($log, ($options['contexts'][$slot] ?? $options['context']), $url, $results[$slot]);
			}
			return $results;
		}
		$timeout = (int)$options['timeout'];
		$connect = (int)$options['connectTimeout'];
		if ($budget > 0) {
			$timeout = max(1, min($timeout, (int)floor($remaining)));
			$connect = max(1, min($connect, $timeout));
		}

		$attempts = 1 + max(0, (int)$options['retries']);
		$attempt  = 0;
		while ($pending) {
			$attempt++;
			$started = microtime(true);
			$calls   = shopApiCurlMultiCall($pending, $timeout, $connect, (string)$options['userAgent']);
			$state['spent'] += microtime(true) - $started;

			$retry = array();
			foreach ($pending as $slot => $url) {
				$call = isset($calls[$slot])
					? $calls[$slot]
					: array('httpCode' => 0, 'body' => '', 'error' => 'no answer from the webshop', 'transient' => true, 'unreachable' => true);
				$results[$slot]['attempts'] = $attempt;
				$results[$slot]['httpCode'] = $call['httpCode'];
				$results[$slot]['body']     = $call['body'];
				$results[$slot]['transient'] = !empty($call['transient']);
				$outcome = shopApiOutcome($call);
				if ($outcome['state'] === 'ok') {
					$results[$slot]['ok']    = true;
					$results[$slot]['error'] = '';
					$state['failCount'][shopApiEndpointKey($url)] = 0;
					if (is_resource($log)) {
						fwrite($log, date('Y-m-d H:i:s') . ' shop sync ok ' . ($options['contexts'][$slot] ?? $options['context']) . ' http ' . (int)$call['httpCode'] . ' ' . $url . "\n");
					}
					continue;
				}
				$results[$slot]['error'] = $outcome['error'];
				if ($outcome['state'] === 'refused') {
					shopApiLogFailure($log, ($options['contexts'][$slot] ?? $options['context']), $url, $results[$slot], $options['maxBodyLog']);
					continue;
				}
				if (!empty($call['transient']) && $attempt < $attempts) {
					$retry[$slot] = $url;
					continue;
				}
				shopApiNoteTransportFailure($call, shopApiEndpointKey($url), $outcome['error'], (int)$options['failThreshold']);
				shopApiLogFailure($log, ($options['contexts'][$slot] ?? $options['context']), $url, $results[$slot], $options['maxBodyLog']);
			}

			$pending = $retry;
			if (!$pending) {
				break;
			}
			// A retry that cannot be afforded is not an untried call: the endpoint is degraded
			// and the failure reported now, rather than leaving the caller with a result that
			// says one attempt and no explanation.
			if ($budget > 0 && $budget - $state['spent'] < 1) {
				foreach ($pending as $slot => $url) {
					$call = isset($calls[$slot]) ? $calls[$slot] : array('transient' => true, 'unreachable' => true);
					shopApiNoteTransportFailure($call, shopApiEndpointKey($url), $results[$slot]['error'], (int)$options['failThreshold']);
					shopApiLogFailure($log, ($options['contexts'][$slot] ?? $options['context']), $url, $results[$slot], $options['maxBodyLog']);
				}
				break;
			}
			// The pause before a retry is part of the time the request thread is held, so it is
			// counted and capped like the calls themselves.
			$pause = 300000 * $attempt;
			if ($budget > 0) {
				$pause = (int)min($pause, max(0, ($budget - $state['spent']) * 1000000));
			}
			if ($pause > 0) {
				usleep($pause);
				$state['spent'] += $pause / 1000000;
			}
			if ($budget > 0) {
				$remaining = $budget - $state['spent'];
				$timeout   = max(1, min((int)$options['timeout'], (int)floor($remaining)));
				$connect   = max(1, min((int)$options['connectTimeout'], $timeout));
			}
		}
		return $results;
	}
}

?>
