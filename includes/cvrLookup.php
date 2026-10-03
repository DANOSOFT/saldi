<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/cvrLookup.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261003 CL/SZ SD-721 Created: the call to cvrapi.dk, moved here from sager/cvrLookupProxy.php so the server can use it too.
//                The proxy (the browser's lookup on the kreditor and debitor cards) and the automatic kreditor creation call the same function.
//                Answers for a CVR number are cached in temp/cvrcache: a company for 30 days, "not found" for 1 day; errors are not cached.

if (!function_exists('cvrLookupFetch')) {
	/**
	 * Looks a CVR or phone number up at cvrapi.dk, through the cache.
	 *
	 * @param string $type 'vat' or 'phone'.
	 * @param string $param 8 digits.
	 * @param string $country Two lowercase letters, e.g. 'dk'.
	 * @param int $timeout Seconds before the call gives up.
	 * @return array{code: int, body: string|null, cached: bool} code is the HTTP status (0 when no answer came); body is cvrapi.dk's JSON.
	 */
	function cvrLookupFetch($type, $param, $country = 'dk', $timeout = 10) {
		$cacheFile = cvrLookupCacheFile($type, $param, $country);
		if ($cacheFile && is_file($cacheFile)) {
			$cached = json_decode((string)file_get_contents($cacheFile), true);
			$maxAge = (isset($cached['code']) && (int)$cached['code'] === 200) ? 30 * 86400 : 86400;
			if (is_array($cached) && isset($cached['time'], $cached['code']) && time() - (int)$cached['time'] < $maxAge) {
				return array('code' => (int)$cached['code'], 'body' => $cached['body'], 'cached' => true);
			}
		}

		$url = "https://cvrapi.dk/api?" . $type . "=" . urlencode($param) . "&country=" . urlencode($country);
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => min(5, (int)$timeout),
			CURLOPT_TIMEOUT        => (int)$timeout,
			CURLOPT_USERAGENT      => 'saldi.dk - kundeopslag (support@saldi.dk)',
			CURLOPT_HTTPHEADER     => array('Accept: application/json'),
		));
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err  = curl_error($ch);
		curl_close($ch);

		if ($body === false) {
			// curl_error() can reveal internal network details, so it is logged and not returned
			if ($err) error_log("cvrLookup: call to cvrapi.dk failed (status $code): $err");
			return array('code' => $code, 'body' => null, 'cached' => false);
		}
		if ($cacheFile && ($code === 200 || ($code === 404 && strpos($body, 'NOT_FOUND') !== false))) {
			$dir = dirname($cacheFile);
			if (is_dir($dir) || @mkdir($dir, 0775, true)) {
				file_put_contents($cacheFile, json_encode(array('time' => time(), 'code' => $code, 'body' => $body)), LOCK_EX);
			}
		}
		return array('code' => $code, 'body' => $body, 'cached' => false);
	}
}

if (!function_exists('cvrLookupCacheFile')) {
	/**
	 * The cache file for a CVR number, or null for what is not cached (phone numbers).
	 *
	 * @param string $type
	 * @param string $param
	 * @param string $country
	 * @return string|null
	 */
	function cvrLookupCacheFile($type, $param, $country) {
		if ($type !== 'vat' || !preg_match('/^\d{8}$/', (string)$param) || !preg_match('/^[a-z]{2}$/', (string)$country)) return null;
		return __DIR__ . '/../temp/cvrcache/' . $country . '_' . $param . '.json';
	}
}

if (!function_exists('cvrLookupCompany')) {
	/**
	 * A Danish company by CVR number, in the fields of a kreditor.
	 *
	 * @param string $cvr 8 digits.
	 * @param int $timeout Seconds.
	 * @return array{ok: bool, error: string|null, dissolved: bool, company: array<string, string>|null}
	 *   error: 'INVALID', 'NOT_FOUND', 'QUOTA_EXCEEDED' or 'UNAVAILABLE' when ok is false.
	 */
	function cvrLookupCompany($cvr, $timeout = 5) {
		$fail = function ($error) {
			return array('ok' => false, 'error' => $error, 'dissolved' => false, 'company' => null);
		};
		if (!preg_match('/^\d{8}$/', (string)$cvr)) return $fail('INVALID');
		$answer = cvrLookupFetch('vat', $cvr, 'dk', $timeout);
		$data = $answer['body'] !== null ? json_decode($answer['body'], true) : null;
		if (!is_array($data)) return $fail('UNAVAILABLE');
		if (!empty($data['error'])) {
			$error = (string)$data['error'];
			return $fail(in_array($error, array('NOT_FOUND', 'QUOTA_EXCEEDED', 'INVALID_VAT'), true) ? ($error === 'INVALID_VAT' ? 'INVALID' : $error) : 'UNAVAILABLE');
		}
		if ($answer['code'] >= 400 || empty($data['name'])) return $fail('UNAVAILABLE');
		return array('ok' => true, 'error' => null, 'dissolved' => cvrLookupDissolved($data), 'company' => cvrLookupCompanyFields($data));
	}
}

if (!function_exists('cvrLookupDissolved')) {
	/**
	 * Whether cvrapi.dk reports the company as ended (an end date, or a credit status such as "OPLØST").
	 *
	 * @param array<string, mixed> $data cvrapi.dk's answer.
	 * @return bool
	 */
	function cvrLookupDissolved(array $data) {
		if (!empty($data['enddate'])) return true;
		$status = mb_strtoupper(trim((string)($data['creditstatus'] ?? '')), 'UTF-8');
		return $status !== '' && preg_match('/OPLØST|OPHØRT|KONKURS|TVANGSOPL|DISSOLVED/u', $status) === 1;
	}
}

if (!function_exists('cvrLookupCompanyFields')) {
	/**
	 * cvrapi.dk's answer in adresser's field names, as javascript/cvrapiopslag.js's normaliseApiData() fills the card.
	 *
	 * @param array<string, mixed> $data
	 * @return array{cvrnr: string, firmanavn: string, addr1: string, addr2: string, postnr: string, bynavn: string, tlf: string, email: string}
	 */
	function cvrLookupCompanyFields(array $data) {
		$text = function ($key) use ($data) {
			return isset($data[$key]) && $data[$key] !== null ? trim((string)$data[$key]) : '';
		};
		$co = $text('addressco');
		return array(
			'cvrnr' => $text('vat'),
			'firmanavn' => $text('name'),
			'addr1' => $co !== '' ? 'c/o ' . $co : $text('address'),
			'addr2' => $co !== '' ? $text('address') : '',
			'postnr' => $text('zipcode'),
			'bynavn' => $text('city'),
			'tlf' => $text('phone'),
			'email' => $text('email'),
		);
	}
}
?>
