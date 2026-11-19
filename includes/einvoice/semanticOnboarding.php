<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/semanticOnboarding.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261109 CL/MMR SD-815 Created (draft). Lazy sender onboarding at Semantic on the first digital send (semantic_ensure_sender()) and the regnskab's Semantic ids in settings var_grp 'semantic'.
//                 The copy guard: the ids are stored with the database name, and a database with another name (a restored backup) never sends as the customer.
// 20261109 CL/MMR SD-815 A failed company lookup stops the onboarding instead of creating a second company, the proposed company id is reused, and the CVR/GLN the endpoints were made for is stored.

include_once(__DIR__ . '/semanticClient.php');
include_once(__DIR__ . '/semanticStatus.php');
include_once(__DIR__ . '/../stdFunc/einvoiceCvr.php');

/*
 * settings, var_grp 'semantic' (tenant database):
 *   companyId           Semantic's company id (uuid)
 *   companyIdCandidate  the id we proposed in POST /api/Company, kept until the company is confirmed
 *   endpointId          the 0184 (CVR) endpoint id (uuid), used as senderEndpointId
 *   endpointIdGln       the 0088 (GLN) endpoint id, when Stamdata has an EAN
 *   endpointIdentifier  the CVR (8 digits) the 0184 endpoint was made for, so a later change in Stamdata is noticed
 *   endpointIdentifierGln the GLN the 0088 endpoint was made for
 *   defaultEndpointSet  '1' when the CVR endpoint is the company's default
 *   peppolRegistered    '1' when registerInPeppol succeeded
 *   dbName              the database these ids were made in (copy guard)
 * The API key is never stored here (semantic_credentials()).
 */

if (!function_exists('semantic_settings_names')) {
	/**
	 * @return list<string>
	 */
	function semantic_settings_names() {
		return array('companyId', 'companyIdCandidate', 'endpointId', 'endpointIdGln', 'endpointIdentifier', 'endpointIdentifierGln', 'defaultEndpointSet',
			'peppolRegistered', 'dbName');
	}
}

if (!function_exists('semantic_settings_read')) {
	/**
	 * The regnskab's Semantic settings (var_grp 'semantic'); a missing one is ''.
	 *
	 * @return array{companyId: string, companyIdCandidate: string, endpointId: string, endpointIdGln: string, endpointIdentifier: string, endpointIdentifierGln: string, defaultEndpointSet: string, peppolRegistered: string, dbName: string}
	 */
	function semantic_settings_read() {
		$settings = array_fill_keys(semantic_settings_names(), '');
		$query = db_select("SELECT var_name, var_value FROM settings WHERE var_grp = 'semantic'", __FILE__ . " linje " . __LINE__);
		while ($query && ($r = db_fetch_array($query))) {
			$name = (string)$r['var_name'];
			if (array_key_exists($name, $settings)) {
				$settings[$name] = trim((string)$r['var_value']);
			}
		}
		return $settings;
	}
}

if (!function_exists('semantic_settings_write')) {
	/**
	 * Store one Semantic setting (var_grp 'semantic').
	 *
	 * @param string $name  One of semantic_settings_names().
	 * @param string $value
	 * @return void
	 */
	function semantic_settings_write($name, $value) {
		if (!in_array($name, semantic_settings_names(), true)) {
			return;
		}
		$name = db_escape_string($name);
		$value = db_escape_string((string)$value);
		$r = db_fetch_array(db_select("SELECT id FROM settings WHERE var_name = '$name' AND var_grp = 'semantic'", __FILE__ . " linje " . __LINE__));
		if ($r) {
			db_modify("UPDATE settings SET var_value = '$value' WHERE id = " . (int)$r['id'], __FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('$name', 'semantic', '$value', 'SD-815 Semantic e-invoice registration')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}
}

if (!function_exists('semantic_copy_guard')) {
	/**
	 * Whether this database may act as the Semantic company stored in its settings. The ids are stored together with
	 * the database name; a database with another name holding them is a copy (a restored backup on a test server) and
	 * must never send, register or poll as the customer.
	 *
	 * @param array{companyId?: string, dbName?: string} $settings  From semantic_settings_read().
	 * @param string                                      $currentDb The database this request runs in.
	 * @return array{allowed: bool, reason: string, message: string} reason 'not_registered' (allowed, onboarding will
	 *   run), 'same_db' (allowed), 'copy' or 'db_unknown'.
	 */
	function semantic_copy_guard(array $settings, $currentDb) {
		$companyId = trim((string)($settings['companyId'] ?? ''));
		$stored = trim((string)($settings['dbName'] ?? ''));
		$currentDb = trim((string)$currentDb);
		if ($companyId === '' && $stored === '') {
			return array('allowed' => true, 'reason' => 'not_registered', 'message' => '');
		}
		if ($stored === '') {
			return array('allowed' => false, 'reason' => 'db_unknown',
				'message' => 'E-faktura-registreringen i dette regnskab mangler oplysning om, hvilket regnskab den hører til. Kontakt saldi.dk support.');
		}
		if ($currentDb === '' || $stored !== $currentDb) {
			return array('allowed' => false, 'reason' => 'copy',
				'message' => 'Dette regnskab er en kopi af et andet regnskab. Fra en kopi sendes der ikke e-fakturaer i kundens navn.');
		}
		return array('allowed' => true, 'reason' => 'same_db', 'message' => '');
	}
}

if (!function_exists('semantic_onboarding_decision')) {
	/**
	 * What semantic_ensure_sender() has to do.
	 *
	 * A regnskab registered through the current provider has a companyID under var_grp 'easyUBL'. Registering its
	 * CVR again from here could take the registration (and the customer's incoming invoices) away from where it is,
	 * so that move is left to support (the planned migration, task 14).
	 * SD-815 OPEN: (Q1) how are existing registrations moved to our tenant without leaving Peppol/Nemhandel?
	 *
	 * @param array<string, string> $settings          From semantic_settings_read().
	 * @param string                $easyUblCompanyId  settings companyID/easyUBL ('' when none).
	 * @param string                $currentDb
	 * @return array{action: string, reason: string, message: string} action 'ready', 'continue' or 'refuse'.
	 */
	function semantic_onboarding_decision(array $settings, $easyUblCompanyId, $currentDb) {
		$guard = semantic_copy_guard($settings, $currentDb);
		if (!$guard['allowed']) {
			return array('action' => 'refuse', 'reason' => $guard['reason'], 'message' => $guard['message']);
		}
		$companyId = trim((string)($settings['companyId'] ?? ''));
		if ($companyId === '' && trim((string)($settings['companyIdCandidate'] ?? '')) === '' && trim((string)$easyUblCompanyId) !== '') {
			return array('action' => 'refuse', 'reason' => 'registered_elsewhere',
				'message' => 'Regnskabet er allerede registreret til e-faktura. Registreringen skal flyttes af saldi.dk support, før fakturaer kan sendes på den nye måde.');
		}
		$ready = $companyId !== ''
			&& trim((string)($settings['endpointId'] ?? '')) !== ''
			&& ($settings['defaultEndpointSet'] ?? '') === '1'
			&& ($settings['peppolRegistered'] ?? '') === '1';
		return $ready
			? array('action' => 'ready', 'reason' => 'ready', 'message' => '')
			: array('action' => 'continue', 'reason' => ($companyId === '') ? 'new' : 'partial', 'message' => '');
	}
}

if (!function_exists('semantic_uuid4')) {
	/**
	 * A random uuid (version 4).
	 *
	 * @return string
	 */
	function semantic_uuid4() {
		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
	}
}

if (!function_exists('semantic_utf8')) {
	/**
	 * A database text as UTF-8 for JSON: a tenant database may be ISO-8859-1.
	 *
	 * @param mixed $text
	 * @return string
	 */
	function semantic_utf8($text) {
		$text = trim((string)$text);
		if ($text !== '' && function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
			$text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
		}
		return $text;
	}
}

if (!function_exists('semantic_id_from_response')) {
	/**
	 * An id in a create call's answer, should Semantic return one: a bare uuid, or an object with $key.
	 *
	 * @param mixed  $data
	 * @param string $key 'companyId' or 'endpointId'.
	 * @return string|null
	 */
	function semantic_id_from_response($data, $key) {
		$candidate = null;
		if (is_string($data)) {
			$candidate = trim($data, " \t\n\r\0\x0B\"");
		} elseif (is_array($data) && isset($data[$key]) && is_string($data[$key])) {
			$candidate = trim($data[$key]);
		}
		if ($candidate === null || !SemanticClient::isUuid($candidate) || $candidate === '00000000-0000-0000-0000-000000000000') {
			return null;
		}
		return strtolower($candidate);
	}
}

if (!function_exists('semantic_own_company')) {
	/**
	 * The regnskab's own company from Stamdata (adresser, art 'S').
	 *
	 * @return array{name: string, addr1: string, addr2: string, postnr: string, bynavn: string, cvr: string, gln: string, email: string, tlf: string}|null
	 */
	function semantic_own_company() {
		$r = db_fetch_array(db_select("SELECT firmanavn, addr1, addr2, postnr, bynavn, cvrnr, ean, email, tlf FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1", __FILE__ . " linje " . __LINE__));
		if (!$r) {
			return null;
		}
		$ean = preg_replace('/\D/', '', (string)$r['ean']);
		return array(
			'name' => semantic_utf8($r['firmanavn']),
			'addr1' => semantic_utf8($r['addr1']),
			'addr2' => semantic_utf8($r['addr2']),
			'postnr' => semantic_utf8($r['postnr']),
			'bynavn' => semantic_utf8($r['bynavn']),
			'cvr' => einvoice_normalize_cvr($r['cvrnr']),
			'gln' => (strlen($ean) === 13) ? $ean : '',
			'email' => semantic_utf8($r['email']),
			'tlf' => semantic_utf8($r['tlf']),
		);
	}
}

if (!function_exists('semantic_company_payload')) {
	/**
	 * The CompanyDto for POST /api/Company. Bank details are not part of it; they go into the invoice's PaymentMeans.
	 * SD-815 OPEN: (Q6) does POST /api/Company create the nested endpoints/addresses/contacts? Endpoints are created
	 *              separately below, so none are sent here. Contact.roleCode values are undocumented and left out.
	 *
	 * @param array{name: string, addr1: string, addr2: string, postnr: string, bynavn: string, cvr: string, email: string, tlf: string} $own
	 * @param string      $companyId  The uuid we propose.
	 * @param string|null $webhookUrl semantic_webhook_url().
	 * @return array<string, mixed>
	 */
	function semantic_company_payload(array $own, $companyId, $webhookUrl) {
		$street = array('streetName' => $own['addr1'], 'buildingNumber' => '', 'additionalStreetName' => $own['addr2']);
		if (function_exists('splitStreetAddress')) {
			// debitor/api.php's splitter, when the caller has it loaded (debitor/peppol.php does).
			$street = splitStreetAddress($own['addr1'], $own['addr2']);
		}
		$payload = array(
			'companyId' => $companyId,
			'name' => $own['name'],
			'cvr' => $own['cvr'],
			'countryCode' => 'DK',
			'webHookUrl' => $webhookUrl,
			'addresses' => array(array(
				'name' => $own['name'],
				'streetName' => $street['streetName'] !== '' ? $street['streetName'] : null,
				'buildingNumber' => $street['buildingNumber'] !== '' ? $street['buildingNumber'] : null,
				'additionalStreetName' => $street['additionalStreetName'] !== '' ? $street['additionalStreetName'] : null,
				'cityName' => $own['bynavn'] !== '' ? $own['bynavn'] : null,
				'postalCode' => $own['postnr'] !== '' ? $own['postnr'] : null,
				'countryCode' => 'DK',
			)),
			'contacts' => array(array(
				'name' => $own['name'],
				'email' => $own['email'] !== '' ? $own['email'] : null,
				'sms' => $own['tlf'] !== '' ? $own['tlf'] : null,
			)),
		);
		return $payload;
	}
}

if (!function_exists('semantic_find_company_id')) {
	/**
	 * Confirm a company exists at Semantic: by the id we proposed, else by CVR.
	 *
	 * @param SemanticClient $client
	 * @param string         $candidate The uuid we proposed ('' to skip).
	 * @param string         $cvr       8 digits.
	 * @return array{companyId: string|null, ambiguous: bool, message: string} message is set when a lookup failed, so
	 *   "not found" is not known (semantic_company_lookup_action()).
	 */
	function semantic_find_company_id(SemanticClient $client, $candidate, $cvr) {
		if (SemanticClient::isUuid($candidate)) {
			$found = $client->getCompanies(array('companyId' => $candidate));
			if (!$found['ok']) {
				// Unknown whether the proposed company exists: the caller must not create another one
				return array('companyId' => null, 'ambiguous' => false, 'message' => $found['message']);
			}
			if (is_array($found['data'])) {
				foreach ($found['data'] as $company) {
					if (is_array($company) && strcasecmp((string)($company['companyId'] ?? ''), $candidate) === 0) {
						return array('companyId' => strtolower($candidate), 'ambiguous' => false, 'message' => '');
					}
				}
			}
		}
		// SD-815 OPEN: does vatIdentifier match "12345678" or "DK12345678"?
		$found = $client->getCompanies(array('vatIdentifier' => $cvr));
		if (!$found['ok']) {
			return array('companyId' => null, 'ambiguous' => false, 'message' => $found['message']);
		}
		$ids = array();
		foreach ((array)$found['data'] as $company) {
			if (is_array($company) && SemanticClient::isUuid($company['companyId'] ?? null)) {
				$ids[strtolower($company['companyId'])] = true;
			}
		}
		if (count($ids) === 1) {
			return array('companyId' => (string)key($ids), 'ambiguous' => false, 'message' => '');
		}
		return array('companyId' => null, 'ambiguous' => count($ids) > 1, 'message' => '');
	}
}

if (!function_exists('semantic_company_lookup_action')) {
	/**
	 * What the onboarding does after semantic_find_company_id(): only a lookup that worked and found nothing allows a new
	 * POST /api/Company, so a lost answer or a Semantic outage never leaves two companies for one CVR.
	 *
	 * @param array{companyId: string|null, ambiguous: bool, message: string} $found        semantic_find_company_id().
	 * @param bool                                                             $hasCandidate An earlier run proposed a company id.
	 * @return string 'use' (the company found is ours), 'create', 'retry' (a lookup failed) or 'refuse' (the CVR is taken,
	 *                or several companies have it).
	 */
	function semantic_company_lookup_action(array $found, $hasCandidate) {
		if (trim((string)($found['message'] ?? '')) !== '') {
			return 'retry';
		}
		if (!empty($found['ambiguous'])) {
			return 'refuse';
		}
		if (($found['companyId'] ?? null) !== null && (string)$found['companyId'] !== '') {
			// After our own proposal it is the company we created; without one, the CVR was registered by someone else
			return $hasCandidate ? 'use' : 'refuse';
		}
		return 'create';
	}
}

if (!function_exists('semantic_ensure_endpoint')) {
	/**
	 * Create one endpoint for the company and read its id back.
	 *
	 * @param SemanticClient $client
	 * @param string         $companyId
	 * @param string         $scheme     "0184" or "0088".
	 * @param string         $identifier CVR digits or GLN.
	 * @return array{ok: bool, endpointId: string|null, message: string}
	 */
	function semantic_ensure_endpoint(SemanticClient $client, $companyId, $scheme, $identifier) {
		$existing = $client->getEndpointByIdentifier($identifier, $scheme);
		if ($existing['ok'] && is_array($existing['data']) && SemanticClient::isUuid($existing['data']['endpointId'] ?? null)) {
			if (strcasecmp((string)($existing['data']['companyId'] ?? ''), $companyId) !== 0) {
				return array('ok' => false, 'endpointId' => null,
					'message' => "Endpoint $scheme:$identifier er allerede oprettet hos Semantic på en anden virksomhed. Kontakt saldi.dk support.");
			}
			return array('ok' => true, 'endpointId' => strtolower($existing['data']['endpointId']), 'message' => '');
		}
		$candidate = semantic_uuid4();
		$created = $client->createEndpoint(array(
			'endpointId' => $candidate,
			'companyId' => $companyId,
			'endpointType' => $scheme,
			'endpointIdentifier' => $identifier,
		));
		if (!$created['ok']) {
			return array('ok' => false, 'endpointId' => null, 'message' => 'Endpoint ' . $scheme . ' kunne ikke oprettes hos Semantic: ' . $created['message']);
		}
		$endpointId = semantic_id_from_response($created['data'], 'endpointId');
		if ($endpointId === null) {
			$read = $client->getEndpointByIdentifier($identifier, $scheme);
			if ($read['ok'] && is_array($read['data']) && SemanticClient::isUuid($read['data']['endpointId'] ?? null)
				&& strcasecmp((string)($read['data']['companyId'] ?? ''), $companyId) === 0) {
				$endpointId = strtolower($read['data']['endpointId']);
			}
		}
		if ($endpointId === null) {
			return array('ok' => false, 'endpointId' => null, 'message' => 'Semantic bekræftede ikke endpoint ' . $scheme . '. Prøv igen senere.');
		}
		return array('ok' => true, 'endpointId' => $endpointId, 'message' => '');
	}
}

if (!function_exists('semantic_ensure_sender')) {
	/**
	 * Make sure this regnskab exists at Semantic as a sender, on the first digital send (like getCompanyID() in
	 * debitor/api.php today): POST /api/Company with our webhook URL, POST /api/Endpoint 0184:<CVR> (and 0088:<GLN>
	 * when Stamdata has an EAN), the CVR endpoint as default, registerInPeppol, and the ids stored with the database
	 * name. Each step is stored as soon as it succeeds, so a failed run continues where it stopped.
	 *
	 * SD-815 OPEN: (Q6) registerInPeppol most likely also registers the company as a Peppol receiver. Incoming documents
	 *              are not handled yet (task 19), so a new regnskab registered here does not get incoming invoices into
	 *              Bilagspuljen. registerInNemHandel is not called; which registration Danish companies need is open.
	 * SD-815 OPEN: (Q11) does Semantic need consent or know-your-customer data per company before registration?
	 * SD-815 OPEN: only Danish senders (CVR, scheme 0184) are handled; a Norwegian regnskab needs 0192.
	 *
	 * @param SemanticClient|null $client  Null = semantic_client().
	 * @param array{db?: string, webhookBaseUrl?: string} $context db = the current database ($GLOBALS['db'] when absent).
	 * @return array{
	 *   ok: bool,
	 *   status: string,                 'ready', 'refused', 'config', 'stamdata' or 'failed'.
	 *   message: string,                Danish, for the user.
	 *   companyId: string|null,
	 *   endpointId: string|null,        The CVR endpoint (senderEndpointId).
	 *   endpointIdGln: string|null,
	 *   registeredCvr: string,          The CVR the CVR endpoint was made for ('' when not stored: registered before 20261109).
	 *   registeredGln: string,          The GLN the GLN endpoint was made for ('' when none or not stored).
	 * }
	 */
	function semantic_ensure_sender($client = null, array $context = array()) {
		$db = (string)($context['db'] ?? ($GLOBALS['db'] ?? ''));
		$settings = semantic_settings_read();
		$out = array('ok' => false, 'status' => 'failed', 'message' => '', 'companyId' => null, 'endpointId' => null, 'endpointIdGln' => null,
			'registeredCvr' => '', 'registeredGln' => '');
		$easyUblCompanyId = (string)get_settings_value('companyID', 'easyUBL', '');
		$decision = semantic_onboarding_decision($settings, $easyUblCompanyId, $db);
		if ($decision['action'] === 'refuse') {
			$out['status'] = 'refused';
			$out['message'] = $decision['message'];
			return $out;
		}
		if ($decision['action'] === 'ready') {
			return array('ok' => true, 'status' => 'ready', 'message' => '', 'companyId' => $settings['companyId'],
				'endpointId' => $settings['endpointId'], 'endpointIdGln' => ($settings['endpointIdGln'] !== '') ? $settings['endpointIdGln'] : null,
				'registeredCvr' => $settings['endpointIdentifier'], 'registeredGln' => $settings['endpointIdentifierGln']);
		}
		$client = $client ?? semantic_client();
		if (!$client instanceof SemanticClient) {
			$out['status'] = 'config';
			$out['message'] = 'E-faktura via Semantic er ikke sat op på denne server. Kontakt saldi.dk support.';
			return $out;
		}
		$own = semantic_own_company();
		if ($own === null || !preg_match('/^[0-9]{8}$/', $own['cvr']) || !einvoice_cvr_mod11_ok($own['cvr'])) {
			$out['status'] = 'stamdata';
			$out['message'] = 'Dit eget CVR-nummer mangler eller er ugyldigt i Stamdata. Ret det under Indstillinger, og send igen.';
			return $out;
		}
		if ($own['name'] === '') {
			$out['status'] = 'stamdata';
			$out['message'] = 'Firmanavnet mangler i Stamdata. Ret det under Indstillinger, og send igen.';
			return $out;
		}

		// 1. The company. The database name is stored first, so the copy guard covers a half-finished registration.
		if ($settings['dbName'] === '') {
			semantic_settings_write('dbName', $db);
			$settings['dbName'] = $db;
		}
		$companyId = $settings['companyId'];
		$hasCandidate = ($settings['companyIdCandidate'] !== '');
		if ($companyId === '') {
			// With a candidate, an earlier run proposed that id, and the call may have succeeded before the answer was lost.
			$found = semantic_find_company_id($client, $settings['companyIdCandidate'], $own['cvr']);
			$action = semantic_company_lookup_action($found, $hasCandidate);
			if ($action === 'retry') {
				$out['message'] = 'Semantic kunne ikke svare på, om virksomheden allerede er oprettet til e-faktura. Prøv igen senere.' . "\n" . $found['message'];
				return $out;
			}
			if ($action === 'refuse') {
				// SD-815 OPEN: two regnskaber with one CVR (one CVR routes to one company and one webhook).
				$out['status'] = 'refused';
				$out['message'] = 'CVR-nummeret er allerede oprettet til e-faktura, måske fra et andet regnskab. Kontakt saldi.dk support.';
				return $out;
			}
			if ($action === 'use') {
				$companyId = (string)$found['companyId'];
			}
		}
		if ($companyId === '') {
			$secret = semantic_webhook_secret();
			$base = (string)($context['webhookBaseUrl'] ?? '');
			$base = ($base !== '') ? $base : (string)semantic_webhook_base_url();
			if ($secret === null || $base === '') {
				$out['status'] = 'config';
				$out['message'] = 'Webhook til e-faktura er ikke sat op på denne server. Kontakt saldi.dk support.';
				return $out;
			}
			// The lookups proved the proposed company absent: propose the same id again, so a lost answer can still be found
			$candidate = $hasCandidate ? $settings['companyIdCandidate'] : semantic_uuid4();
			if (!$hasCandidate) {
				semantic_settings_write('companyIdCandidate', $candidate);
			}
			$created = $client->createCompany(semantic_company_payload($own, $candidate, semantic_webhook_url($base, $db, $secret)));
			if (!$created['ok']) {
				$out['message'] = 'Virksomheden kunne ikke oprettes til e-faktura hos Semantic: ' . $created['message'];
				return $out;
			}
			$companyId = (string)semantic_id_from_response($created['data'], 'companyId');
			if ($companyId === '') {
				$found = semantic_find_company_id($client, $candidate, $own['cvr']);
				$companyId = (string)$found['companyId'];
			}
		}
		if ($companyId === '') {
			$out['message'] = 'Semantic bekræftede ikke oprettelsen af virksomheden. Prøv igen senere, eller kontakt saldi.dk support.';
			return $out;
		}
		if ($settings['companyId'] !== $companyId) {
			semantic_settings_write('companyId', $companyId);
			$settings['companyId'] = $companyId;
		}
		$out['companyId'] = $companyId;

		// 2. The CVR endpoint (0184, digits without "DK"), the sender id of every document.
		if ($settings['endpointId'] === '') {
			$endpoint = semantic_ensure_endpoint($client, $companyId, '0184', $own['cvr']);
			if (!$endpoint['ok']) {
				$out['message'] = $endpoint['message'];
				return $out;
			}
			semantic_settings_write('endpointId', $endpoint['endpointId']);
			semantic_settings_write('endpointIdentifier', $own['cvr']);
			$settings['endpointId'] = $endpoint['endpointId'];
			$settings['endpointIdentifier'] = $own['cvr'];
		}
		$out['endpointId'] = $settings['endpointId'];
		$out['registeredCvr'] = $settings['endpointIdentifier'];

		// 3. The GLN endpoint (0088) when Stamdata has an EAN. Not needed to send, so a failure does not stop the send.
		$warnings = array();
		if ($own['gln'] !== '' && $settings['endpointIdGln'] === '') {
			$gln = semantic_ensure_endpoint($client, $companyId, '0088', $own['gln']);
			if ($gln['ok']) {
				semantic_settings_write('endpointIdGln', $gln['endpointId']);
				semantic_settings_write('endpointIdentifierGln', $own['gln']);
				$settings['endpointIdGln'] = $gln['endpointId'];
				$settings['endpointIdentifierGln'] = $own['gln'];
			} else {
				$warnings[] = $gln['message'];
			}
		}
		$out['endpointIdGln'] = ($settings['endpointIdGln'] !== '') ? $settings['endpointIdGln'] : null;
		$out['registeredGln'] = $settings['endpointIdentifierGln'];

		// 4. The CVR endpoint as the company's default billing endpoint.
		if ($settings['defaultEndpointSet'] !== '1') {
			$default = $client->setDefaultEndpoint($companyId, $settings['endpointId']);
			if ($default['ok']) {
				semantic_settings_write('defaultEndpointSet', '1');
			} else {
				$warnings[] = 'Standard-endpoint blev ikke sat: ' . $default['message'];
			}
		}

		// 5. Peppol registration. Sending does not wait for it; it is tried again on the next send until it succeeds.
		// SD-815 OPEN: (Q6) does Semantic require the sender to be registered before CreateOutgoingDocument?
		if ($settings['peppolRegistered'] !== '1') {
			$registered = $client->registerInPeppol($settings['endpointId']);
			if ($registered['ok']) {
				semantic_settings_write('peppolRegistered', '1');
			} else {
				$warnings[] = 'Registreringen i Peppol lykkedes ikke: ' . $registered['message'];
			}
		}

		$out['ok'] = true;
		$out['status'] = 'ready';
		$out['message'] = implode("\n", $warnings);
		return $out;
	}
}
