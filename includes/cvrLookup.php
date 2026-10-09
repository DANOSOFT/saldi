<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/cvrLookup.php --- ver 5.0.0 --- 2026-10-07 ---
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
// 20261006 CL/SZ SD-721 As cvrapi.dk's documentation asks: the User-Agent is "Firma - Projekt - Kontaktperson telefon/e-mail" ($cvrapi_contact in
//                includes/connect.php), and a token ($cvrapi_token in includes/connect.php) is sent when the server has one.
//                A refused call says why: cvrapi.dk's error code (QUOTA_EXCEEDED, BANNED, INVALID_UA ...), or BLOCKED for an empty 403, and it is logged.
// 20261006 CL/SZ SD-721 cvrapi.dk gives 50 free lookups a day per IP address: after QUOTA_EXCEEDED the server stops calling until tomorrow, after
//                BANNED, BLOCKED or INVALID_UA for an hour (temp/cvrcache/_refused.json; a new token ends the pause). An error in a 200 answer
//                counts as a refusal too and is never cached as a company. cvrLookupCompany() passes these codes on.
// 20261006 CL/SZ SD-721 cvrLookupClientConfig(): the kreditor and debitor cards' lookup (javascript/cvrapiopslag.js) goes through
//                sager/cvrLookupProxy.php too. From the browser cvrapi.dk refused it: a browser cannot send the User-Agent it requires.
// 20261007 CL/SZ SD-721 A Danish CVR number is looked up at Datafordeleren (the CVR register's own GraphQL service, flexibleCurrent) when the
//                server has an API key (setting datafordeler / apikey in the master database): free and without a daily quota, which cvrapi.dk's 50 lookups
//                a day per IP address can't give a server shared by every customer. The answer is turned into cvrapi.dk's fields, so the cache,
//                the proxy and the kreditor creation stay as they were. Without the key a CVR number is not looked up (never cvrapi.dk), and
//                $cvrapi_token is no longer used. Phone numbers and other countries, which Datafordeleren can't look up, keep cvrapi.dk.
//                A failed call gives UNAVAILABLE (fill in the kreditor by hand) and is logged, never with the key.

if (!function_exists('cvrLookupFetch')) {
	/**
	 * Looks a CVR or phone number up, through the cache: a Danish CVR number at Datafordeleren (UNAVAILABLE when the server has no
	 * API key), a phone number or another country's number at cvrapi.dk.
	 *
	 * @param string $type 'vat' or 'phone'.
	 * @param string $param 8 digits.
	 * @param string $country Two lowercase letters, e.g. 'dk'.
	 * @param int $timeout Seconds before the call gives up.
	 * @return array{code: int, body: string|null, cached: bool, error: string|null} code is the HTTP status (0 when no answer came);
	 *   body is cvrapi.dk's JSON (Datafordeleren's answer in its fields); error is the code for a refused call (cvrapi.dk's own, BLOCKED when
	 *   it gave no reason, UNAVAILABLE when Datafordeleren failed), else null.
	 */
	function cvrLookupFetch($type, $param, $country = 'dk', $timeout = 10) {
		$cacheFile = cvrLookupCacheFile($type, $param, $country);
		if ($cacheFile && is_file($cacheFile)) {
			$cached = json_decode((string)file_get_contents($cacheFile), true);
			$maxAge = (isset($cached['code']) && (int)$cached['code'] === 200) ? 30 * 86400 : 86400;
			if (is_array($cached) && isset($cached['time'], $cached['code']) && time() - (int)$cached['time'] < $maxAge) {
				return array('code' => (int)$cached['code'], 'body' => $cached['body'], 'cached' => true, 'error' => null);
			}
		}

		// A Danish CVR number only ever goes to Datafordeleren: cvrapi.dk's quota is per IP address, so the server would share it with every customer
		if ($type === 'vat' && $country === 'dk') {
			if (cvrLookupDatafordelerKey() === '') {
				error_log('cvrLookup: no Datafordeleren API key (setting datafordeler / apikey in the master database), so no CVR lookup');
				return array('code' => 0, 'body' => null, 'cached' => false, 'error' => 'UNAVAILABLE');
			}
			$answer = cvrLookupDatafordeler($param, $timeout);
			if ($cacheFile && ($answer['code'] === 200 || $answer['code'] === 404)) cvrLookupCacheStore($cacheFile, $answer['code'], $answer['body']);
			return $answer;
		}

		$token = '';
		$paused = cvrLookupPaused($token);
		if ($paused !== null) return array('code' => 0, 'body' => null, 'cached' => false, 'error' => $paused);

		$url = "https://cvrapi.dk/api?" . $type . "=" . urlencode($param) . "&country=" . urlencode($country);
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => min(5, (int)$timeout),
			CURLOPT_TIMEOUT        => (int)$timeout,
			CURLOPT_USERAGENT      => cvrLookupUserAgent(),
			CURLOPT_HTTPHEADER     => array('Accept: application/json'),
		));
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err  = curl_error($ch);
		curl_close($ch);

		if ($body === false) {
			// curl_error() can reveal internal network details, so it is logged and not returned
			if ($err) error_log("cvrLookup: call to cvrapi.dk failed (status $code): $err");
			return array('code' => $code, 'body' => null, 'cached' => false, 'error' => 'UNAVAILABLE');
		}
		$error = null;
		$data = json_decode($body, true);
		$given = is_array($data) && !empty($data['error']) ? preg_replace('/[^A-Z_]/', '', strtoupper((string)$data['error'])) : '';
		$notFound = $given === 'NOT_FOUND';
		// The documentation: errors come as codes in the answer, not always as an HTTP status
		if (!$notFound && ($code >= 400 || $given !== '')) {
			$error = $given !== '' ? $given : ($code === 403 ? 'BLOCKED' : 'UNAVAILABLE');
			// The answer, not the URL
			error_log("cvrLookup: cvrapi.dk refused the lookup (status $code, $error)");
			cvrLookupPause($error, $token);
		}
		if ($cacheFile && (($code === 200 && $error === null) || $notFound)) cvrLookupCacheStore($cacheFile, $code, $body);
		return array('code' => $code, 'body' => $body, 'cached' => false, 'error' => $error);
	}
}

if (!function_exists('cvrLookupCacheStore')) {
	/**
	 * Stores an answer for a CVR number in temp/cvrcache.
	 *
	 * @param string $cacheFile From cvrLookupCacheFile().
	 * @param int $code The HTTP status the answer is remembered with (200 a company, 404 not found).
	 * @param string|null $body cvrapi.dk's JSON.
	 * @return void
	 */
	function cvrLookupCacheStore($cacheFile, $code, $body) {
		$dir = dirname($cacheFile);
		if (is_dir($dir) || @mkdir($dir, 0775, true)) {
			file_put_contents($cacheFile, json_encode(array('time' => time(), 'code' => (int)$code, 'body' => $body)), LOCK_EX);
		}
	}
}

if (!function_exists('cvrLookupDatafordelerKey')) {
	/**
	 * The server's Datafordeleren API key: setting datafordeler / apikey in the master database (one key for every company on the server),
	 * or '' without one. Never written to git or a log.
	 *
	 * @return string
	 */
	function cvrLookupDatafordelerKey() {
		if (!function_exists('get_settings_value')) return '';
		return trim((string)get_settings_value('apikey', 'datafordeler', '', NULL, NULL, true));
	}
}

if (!function_exists('cvrLookupDatafordelerQuery')) {
	/**
	 * The GraphQL query for one company as it is now: name, addresses, phone and e-mail. An ended company is not "now", so it is not found.
	 *
	 * @param string $cvr 8 digits (checked by the caller).
	 * @param string $now The effective time, e.g. 2026-10-07T12:00:00Z.
	 * @return string
	 */
	function cvrLookupDatafordelerQuery($cvr, $now) {
		return 'query {
	CVR_Virksomhed(first: 1, virkningstid: "' . $now . '", where: { CVRNummer: { eq: ' . (int)$cvr . ' } }) {
		nodes {
			CVRNummer
			virksomhedOphoersdato
			id_CVR_Navn_CVREnhedsId_ref { vaerdi }
			id_CVR_Adressering_CVREnhedsId_ref(first: 10, where: { AdresseringAnvendelse: { in: ["beliggenhedsadresse", "postadresse"] } }) {
				nodes {
					AdresseringAnvendelse
					coNavn
					CVRAdresse_vejnavn
					CVRAdresse_husnummerFra
					CVRAdresse_husnummerTil
					CVRAdresse_etagebetegnelse
					CVRAdresse_doerbetegnelse
					CVRAdresse_postnummer
					CVRAdresse_postdistrikt
					CVRAdresse_adresseFritekst
				}
			}
			id_CVR_Telefonnummer_CVREnhedsId_ref { vaerdi }
			id_CVR_e_mailadresse_CVREnhedsId_ref { vaerdi }
		}
	}
}';
	}
}

if (!function_exists('cvrLookupDatafordeler')) {
	/**
	 * Looks a Danish CVR number up at Datafordeleren and answers as cvrLookupFetch() does, with cvrapi.dk's JSON.
	 *
	 * @param string $cvr 8 digits.
	 * @param int $timeout Seconds.
	 * @return array{code: int, body: string|null, cached: bool, error: string|null} code 200 a company, 404 not found ({"error":"NOT_FOUND"}).
	 */
	function cvrLookupDatafordeler($cvr, $timeout) {
		$fail = function ($why, $status) {
			// Never the URL: it carries the API key
			error_log("cvrLookup: Datafordeleren lookup failed (status $status): $why");
			return array('code' => (int)$status, 'body' => null, 'cached' => false, 'error' => 'UNAVAILABLE');
		};
		$payload = json_encode(array('query' => cvrLookupDatafordelerQuery($cvr, gmdate('Y-m-d\TH:i:s\Z'))));
		$ch = curl_init('https://graphql.datafordeler.dk/flexibleCurrent/v3?apiKey=' . rawurlencode(cvrLookupDatafordelerKey()));
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $payload,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => min(5, (int)$timeout),
			CURLOPT_TIMEOUT        => (int)$timeout,
			CURLOPT_USERAGENT      => cvrLookupUserAgent(),
			CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'Accept: application/json'),
		));
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err  = curl_error($ch);
		curl_close($ch);
		if ($body === false) return $fail($err !== '' ? $err : 'no answer', $code);
		if ($code === 401 || $code === 403) return $fail('the API key was refused', $code);
		if ($code !== 200) return $fail('unexpected answer', $code);
		$data = json_decode($body, true);
		if (!is_array($data)) return $fail('the answer is not JSON', $code);
		if (!empty($data['errors'])) {
			$messages = array();
			foreach ((array)$data['errors'] as $e) $messages[] = is_array($e) ? (string)($e['message'] ?? '') : (string)$e;
			return $fail('GraphQL: ' . mb_substr(implode(' | ', $messages), 0, 300), $code);
		}
		$nodes = $data['data']['CVR_Virksomhed']['nodes'] ?? null;
		if (!is_array($nodes)) return $fail('no CVR_Virksomhed in the answer', $code);
		if (!$nodes) return array('code' => 404, 'body' => json_encode(array('error' => 'NOT_FOUND')), 'cached' => false, 'error' => null);
		$company = cvrLookupDatafordelerCompany($nodes[0]);
		if ($company['vat'] !== (string)(int)$cvr || $company['name'] === '') return $fail('the answer has no company for this number', $code);
		return array('code' => 200, 'body' => json_encode($company), 'cached' => false, 'error' => null);
	}
}

if (!function_exists('cvrLookupDatafordelerFirst')) {
	/**
	 * The first record of a relation, whether Datafordeleren gives it as {nodes: [...]}, as a list or as one object.
	 *
	 * @param mixed $relation
	 * @return array<string, mixed>
	 */
	function cvrLookupDatafordelerFirst($relation) {
		if (!is_array($relation)) return array();
		if (isset($relation['nodes'])) $relation = $relation['nodes'];
		if (array_key_exists(0, $relation)) return is_array($relation[0]) ? $relation[0] : array();
		return $relation;
	}
}

if (!function_exists('cvrLookupDatafordelerCompany')) {
	/**
	 * Datafordeleren's company in cvrapi.dk's fields (vat, name, address, addressco, zipcode, city, phone, email, enddate),
	 * which cvrLookupCompanyFields() and javascript/cvrapiopslag.js read. The address is the beliggenhedsadresse, else the postadresse.
	 *
	 * @param array<string, mixed> $node One CVR_Virksomhed node.
	 * @return array<string, string|null>
	 */
	function cvrLookupDatafordelerCompany(array $node) {
		$text = function ($value) {
			return $value === null ? '' : trim((string)$value);
		};
		$addresses = $node['id_CVR_Adressering_CVREnhedsId_ref'] ?? array();
		if (isset($addresses['nodes'])) $addresses = $addresses['nodes'];
		$address = array();
		foreach (array('beliggenhedsadresse', 'postadresse') as $use) {
			foreach ((array)$addresses as $a) {
				if (is_array($a) && ($a['AdresseringAnvendelse'] ?? '') === $use) { $address = $a; break 2; }
			}
		}
		$line = $text($address['CVRAdresse_vejnavn'] ?? null);
		if ($line !== '') {
			$number = $text($address['CVRAdresse_husnummerFra'] ?? null);
			$to = $text($address['CVRAdresse_husnummerTil'] ?? null);
			if ($to !== '' && $to !== $text($address['CVRAdresse_husnummerFra'] ?? null)) $number .= '-' . $to;
			if ($number !== '') $line .= ' ' . $number;
			$floor = $text($address['CVRAdresse_etagebetegnelse'] ?? null);
			$door = $text($address['CVRAdresse_doerbetegnelse'] ?? null);
			// As on a letter: "2. tv", "st. th"
			if ($floor !== '' || $door !== '') $line .= ',' . ($floor !== '' ? ' ' . $floor . (preg_match('/^(\d+|st|kl)$/i', $floor) ? '.' : '') : '') . ($door !== '' ? ' ' . $door : '');
		} else {
			$line = $text($address['CVRAdresse_adresseFritekst'] ?? null);
		}
		$zipcode = $text($address['CVRAdresse_postnummer'] ?? null);
		// Some companies have their own address in the c/o field; it would show twice on the kreditor
		$co = $text($address['coNavn'] ?? null);
		$street = $text($address['CVRAdresse_vejnavn'] ?? null);
		if ($co !== '' && $street !== '' && mb_stripos($co, $street, 0, 'UTF-8') !== false) $co = '';
		$value = function ($key) use ($node, $text) {
			$first = cvrLookupDatafordelerFirst($node[$key] ?? null);
			return $text($first['vaerdi'] ?? null);
		};
		return array(
			'vat' => $text($node['CVRNummer'] ?? null),
			'name' => $value('id_CVR_Navn_CVREnhedsId_ref'),
			'address' => $line,
			'addressco' => $co !== '' ? $co : null,
			'zipcode' => $zipcode,
			'city' => $text($address['CVRAdresse_postdistrikt'] ?? null),
			'phone' => $value('id_CVR_Telefonnummer_CVREnhedsId_ref'),
			'email' => $value('id_CVR_e_mailadresse_CVREnhedsId_ref'),
			'enddate' => $text($node['virksomhedOphoersdato'] ?? null) !== '' ? $text($node['virksomhedOphoersdato']) : null,
		);
	}
}

if (!function_exists('cvrLookupPauseFile')) {
	/**
	 * Where a refusal from cvrapi.dk is remembered: one file for the server, as the quota is per IP address.
	 *
	 * @return string
	 */
	function cvrLookupPauseFile() {
		return __DIR__ . '/../temp/cvrcache/_refused.json';
	}
}

if (!function_exists('cvrLookupPause')) {
	/**
	 * Remembers a refusal, so the next lookups don't call cvrapi.dk again (more calls can turn a quota into a ban).
	 * QUOTA_EXCEEDED lasts until tomorrow; BANNED, BLOCKED and INVALID_UA are tried again after an hour.
	 *
	 * @param string $error cvrapi.dk's error code.
	 * @param string $token The token the call was made with ('' without one).
	 * @return void
	 */
	function cvrLookupPause($error, $token) {
		if ($error === 'QUOTA_EXCEEDED') $until = strtotime('tomorrow');
		elseif (in_array($error, array('BANNED', 'BLOCKED', 'INVALID_UA'), true)) $until = time() + 3600;
		else return;
		$file = cvrLookupPauseFile();
		$dir = dirname($file);
		if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return;
		@file_put_contents($file, json_encode(array('error' => $error, 'until' => $until, 'token' => sha1($token))), LOCK_EX);
	}
}

if (!function_exists('cvrLookupPaused')) {
	/**
	 * The refusal still in force, or null. A token other than the one refused (one added or changed) ends the pause.
	 *
	 * @param string $token The token the next call would use.
	 * @return string|null cvrapi.dk's error code.
	 */
	function cvrLookupPaused($token) {
		$file = cvrLookupPauseFile();
		if (!is_file($file)) return null;
		$pause = json_decode((string)@file_get_contents($file), true);
		if (!is_array($pause) || empty($pause['error']) || (int)($pause['until'] ?? 0) <= time() || ($pause['token'] ?? '') !== sha1($token)) return null;
		return preg_replace('/[^A-Z_]/', '', (string)$pause['error']);
	}
}

if (!function_exists('cvrLookupUserAgent')) {
	/**
	 * The User-Agent cvrapi.dk requires: "Firma - Projekt - Kontaktperson telefon/e-mail". The contact is $cvrapi_contact
	 * in includes/connect.php (name and phone or e-mail), else Saldi's support address.
	 *
	 * @return string
	 */
	function cvrLookupUserAgent() {
		$contact = isset($GLOBALS['cvrapi_contact']) ? trim(preg_replace('/[\r\n]+/', ' ', (string)$GLOBALS['cvrapi_contact'])) : '';
		if ($contact === '') $contact = 'Saldi support support@saldi.dk';
		return 'Danosoft ApS - Saldi - ' . $contact;
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
	 *   error: 'INVALID', 'NOT_FOUND', 'QUOTA_EXCEEDED', 'BANNED', 'BLOCKED', 'INVALID_UA' or 'UNAVAILABLE' when ok is false.
	 */
	function cvrLookupCompany($cvr, $timeout = 5) {
		$fail = function ($error) {
			return array('ok' => false, 'error' => $error, 'dissolved' => false, 'company' => null);
		};
		if (!preg_match('/^\d{8}$/', (string)$cvr)) return $fail('INVALID');
		$answer = cvrLookupFetch('vat', $cvr, 'dk', $timeout);
		$data = $answer['body'] !== null ? json_decode($answer['body'], true) : null;
		$error = !empty($answer['error']) ? (string)$answer['error'] : (is_array($data) && !empty($data['error']) ? (string)$data['error'] : '');
		if ($error === 'INVALID_VAT') return $fail('INVALID');
		// The refusals keep their code, so the pool can say why ("Kvoten for CVR-opslag er opbrugt")
		if (in_array($error, array('NOT_FOUND', 'QUOTA_EXCEEDED', 'BANNED', 'BLOCKED', 'INVALID_UA'), true)) return $fail($error);
		if ($error !== '' || !is_array($data)) return $fail('UNAVAILABLE');
		if ($answer['code'] >= 400 || empty($data['name'])) return $fail('UNAVAILABLE');
		return array('ok' => true, 'error' => null, 'dissolved' => cvrLookupDissolved($data), 'company' => cvrLookupCompanyFields($data));
	}
}

if (!function_exists('cvrLookupDissolved')) {
	/**
	 * Whether cvrapi.dk reports the company as ended (an end date, or a credit status such as "OPLØST").
	 * Datafordeleren doesn't return an ended company at all: it comes back as NOT_FOUND.
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
if (!function_exists('cvrLookupClientConfig')) {
	/**
	 * The script block that points javascript/cvrapiopslag.js at the server proxy, with its messages in the user's language.
	 * Printed before the script on a page one folder below the root (kreditor/, debitor/).
	 *
	 * @param int $sprog_id
	 * @return string
	 */
	function cvrLookupClientConfig($sprog_id) {
		$refused = findtekst('5409|cvrapi.dk afviser opslag fra serveren. Udfyld felterne manuelt.', $sprog_id);
		// JSON_HEX_*: a translation can't close the script block or an attribute
		$texts = json_encode(array(
			'fejl'           => findtekst('3374|CVR-opslaget kunne ikke gennemføres. Udfyld felterne manuelt.', $sprog_id),
			'QUOTA_EXCEEDED' => findtekst('3375|Kvoten for CVR-opslag er opbrugt.', $sprog_id),
			'NOT_FOUND'      => findtekst('3376|CVR-nummeret blev ikke fundet.', $sprog_id),
			'INVALID_VAT'    => findtekst('3377|CVR-nummeret er ikke gyldigt.', $sprog_id),
			'BLOCKED'        => $refused,
			'BANNED'         => $refused,
			'INVALID_UA'     => $refused,
			'soeger'         => findtekst('3378|Søger...', $sprog_id),
		), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
		return "<script type=\"text/javascript\">var cvrLookupProxy = '../sager/cvrLookupProxy.php'; var cvrTekster = $texts;</script>\n";
	}
}
?>
