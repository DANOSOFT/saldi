<?php
// --- includes/stdFunc/shopApiRequest.php --- ver 5.0.0 --- 2026-07-27 ---
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
// Sends webshop API requests without a shell, captures the outcome and logs failures.
// Replaces the old "nohup curl '$url' > ../temp/$db/curl.txt &" pattern, which threw
// the exit status away, let unescaped item data reach the shell and had every call
// overwrite the same curl.txt.
// 20260727 Sawaneh Created.

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
		$query = http_build_query($clean);
		if ($query === '') {
			return $endpoint;
		}
		return $endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . $query;
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
	 *   ok: bool,          True when the shop answered with http status < 400.
	 *   url: string,       The url that was called, empty if the endpoint was rejected.
	 *   httpCode: int,     Http status code, 0 when no response was received.
	 *   error: string,     Empty on success, otherwise a short failure description.
	 *   body: string,      Response body, empty when it could not be captured.
	 *   attempts: int,     Number of attempts made.
	 *   skipped: bool,     True when the call was skipped because the endpoint is known dead.
	 * }
	 */
	function shopApiRequest($endpoint, array $params = array(), $log = null, array $options = array()) {
		static $deadEndpoints = array();

		$options += array(
			'context'        => '',
			'connectTimeout' => 5,
			'timeout'        => 10,
			'retries'        => 1,
			'userAgent'      => 'Saldi shop sync',
			'maxBodyLog'     => 500,
		);
		$result = array(
			'ok'       => false,
			'url'      => '',
			'httpCode' => 0,
			'error'    => '',
			'body'     => '',
			'attempts' => 0,
			'skipped'  => false,
		);

		$url = shopApiUrl($endpoint, $params);
		if ($url === '') {
			$result['error'] = 'invalid api endpoint';
			shopApiLogFailure($log, $options['context'], (string)$endpoint, $result);
			return $result;
		}
		$result['url'] = $url;

		$parts = parse_url($url);
		$endpointKey = strtolower($parts['scheme'] . '://' . $parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
		if (isset($deadEndpoints[$endpointKey])) {
			$result['skipped'] = true;
			$result['error']   = 'endpoint unreachable earlier in this request: ' . $deadEndpoints[$endpointKey];
			shopApiLogFailure($log, $options['context'], $url, $result);
			return $result;
		}

		$attempts = 1 + max(0, (int)$options['retries']);
		for ($attempt = 1; $attempt <= $attempts; $attempt++) {
			$result['attempts'] = $attempt;
			$call = function_exists('curl_init')
				? shopApiCurlCall($url, $options)
				: shopApiShellCall($url, $options);
			$result['httpCode'] = $call['httpCode'];
			$result['body']     = $call['body'];

			if ($call['error'] === '' && $call['httpCode'] > 0 && $call['httpCode'] < 400) {
				$result['ok']    = true;
				$result['error'] = '';
				if (is_resource($log)) {
					fwrite($log, date('Y-m-d H:i:s') . ' shop sync ok ' . $options['context'] . ' http ' . $call['httpCode'] . ' ' . $url . "\n");
				}
				return $result;
			}

			$result['error'] = $call['error'] !== ''
				? $call['error']
				: 'http status ' . $call['httpCode'];
			if (!$call['transient'] || $attempt === $attempts) {
				if ($call['unreachable']) {
					$deadEndpoints[$endpointKey] = $result['error'];
				}
				break;
			}
			usleep(300000);
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
			. ' -w ' . escapeshellarg('%{http_code}')
			. ' ' . escapeshellarg($url) . ' 2>&1';
		$out      = shell_exec($cmd);
		$body     = (string)@file_get_contents($bodyFile);
		@unlink($bodyFile);

		$out      = trim((string)$out);
		$httpCode = preg_match('/(\d{3})\s*$/', $out, $m) ? (int)$m[1] : 0;
		$error    = $httpCode ? '' : ('curl command failed: ' . ($out === '' ? 'no output' : $out));

		return array(
			'httpCode'    => $httpCode,
			'body'        => $body,
			'error'       => $error,
			'transient'   => ($httpCode === 0 || $httpCode >= 500),
			'unreachable' => ($httpCode === 0),
		);
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
		$line = date('Y-m-d H:i:s') . ' SHOP SYNC FAILED ' . ($context !== '' ? $context . ' ' : '')
			. 'http ' . (int)$result['httpCode'] . ' after ' . (int)$result['attempts'] . ' attempt(s)'
			. ($result['skipped'] ? ' (skipped)' : '')
			. ' - ' . $result['error'] . ' - ' . $url;
		if (isset($result['body']) && $result['body'] !== '' && !$result['skipped']) {
			$body = preg_replace('/\s+/', ' ', $result['body']);
			$line .= ' - response: ' . mb_substr($body, 0, max(0, (int)$maxBodyLog));
		}
		if (is_resource($log)) {
			fwrite($log, $line . "\n");
		}
		error_log('saldi: ' . $line);
	}
}
?>
