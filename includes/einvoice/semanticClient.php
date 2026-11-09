<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/semanticClient.php --- ver 5.1.0 --- 2026-11-09 ---
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
// 20261109 CL/MMR SD-815 Created (draft). SemanticClient talks to Semantic's REST API ("Semantic API 3.0.3", the Integrator definition) for sending e-documents.
//                 semantic_credentials() and the other semantic_*() config readers take the server's Semantic settings from the environment or the gitignored key file, never from a database.
// 20261109 CL/MMR SD-815 The configuration comment says which values are required (the webhook base URL is, for the onboarding); INSTALLATION.md documents them.

/*
 * Configuration (per server, never in a database and never in git; INSTALLATION.md, "E-invoice through Semantic"):
 *
 *   Environment variables, read first:
 *     SALDI_SEMANTIC_BASE_URL, SALDI_SEMANTIC_TENANT_ID, SALDI_SEMANTIC_API_KEY   (required; all three, or none)
 *     SALDI_SEMANTIC_WEBHOOK_SECRET        our own HMAC secret for the webhook URL token, at least 32 characters (required
 *                                          for the onboarding and the webhook)
 *     SALDI_SEMANTIC_WEBHOOK_BASE_URL      e.g. https://<server>/<installation>, https only (required for the onboarding:
 *                                          semantic_ensure_sender() refuses without it; it is never taken from the request)
 *     SALDI_SEMANTIC_SIGNING_SECRETS       Semantic's webhook signing secret(s), comma separated (optional)
 *
 *   Otherwise the key file one level above the installation (the same .ht_keys.txt that
 *   includes/opdat_5.0.php reads as ../../.ht_keys.txt), with the PHP variables
 *     $semanticBaseUrl, $semanticTenantId, $semanticApiKey,
 *     $semanticWebhookSecret, $semanticSigningSecrets (string or array), $semanticWebhookBaseUrl
 *
 * A test server must point at Semantic's dev tenant, so a restored customer backup can never act
 * as the customer in production (see also the copy guard in semanticOnboarding.php).
 */

if (!class_exists('SemanticClient', false)) {
	/**
	 * HTTP client for Semantic's REST API.
	 *
	 * Every call returns the same classified result instead of throwing, modelled on
	 * includes/stdFunc/easyUblResponse.php. A message is a short text for the user or a log line:
	 * it never holds the API key, the tenant id or the document XML.
	 *
	 * The transport is injectable so tests need no network:
	 *   fn(string $method, string $url, array $headers, ?string $body): array{status: int, body: string, error: ?string}
	 * $headers is a list of "Name: value" lines. status is 0 when no HTTP answer arrived, error is then set.
	 */
	class SemanticClient {
		/** DocumentHeader.networkType for Peppol ("Use networktype 3 for Peppol and 4 for Nemhandel", ValidatePeppolDocument). */
		const NETWORK_PEPPOL = 3;
		/** DocumentHeader.networkType for Nemhandel. */
		const NETWORK_NEMHANDEL = 4;
		/** documentType of the isEndpointIn* lookups: "100 for Order, 110 of Invoice, 120 for CreditNote". */
		const DOCUMENT_TYPE_INVOICE = 110;
		const DOCUMENT_TYPE_CREDITNOTE = 120;
		/** Longest message a result carries. */
		const MESSAGE_MAX = 200;

		/** @var string */
		private $baseUrl;
		/** @var string */
		private $tenantId;
		/** @var string */
		private $apiKey;
		/** @var callable */
		private $transport;

		/**
		 * @param string        $baseUrl   e.g. https://<semantic host>, without a trailing /api. https only (http only for localhost).
		 * @param string        $tenantId  The tenant id (uuid).
		 * @param string        $apiKey    The tenant's API key (uuid).
		 * @param callable|null $transport See the class comment; null = curl with the timeouts in $options.
		 * @param array{connectTimeout?: int, timeout?: int} $options Seconds for the default curl transport (10 and 30).
		 * @throws InvalidArgumentException When the base URL is not https or a credential is empty.
		 */
		public function __construct(
			string $baseUrl,
			string $tenantId,
			#[\SensitiveParameter]
			string $apiKey,
			?callable $transport = null,
			array $options = array()
		) {
			$baseUrl = rtrim(trim($baseUrl), '/');
			if (!preg_match('#^https://[^/\s]+#i', $baseUrl) && !preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?(/|$)#i', $baseUrl)) {
				throw new InvalidArgumentException('Semantic base URL must be https');
			}
			if (trim($tenantId) === '' || trim($apiKey) === '') {
				throw new InvalidArgumentException('Semantic tenant id and API key are required');
			}
			$this->baseUrl = $baseUrl;
			$this->tenantId = trim($tenantId);
			$this->apiKey = trim($apiKey);
			if ($transport === null) {
				$transport = self::curlTransport((int)($options['connectTimeout'] ?? 10), (int)($options['timeout'] ?? 30));
			}
			$this->transport = $transport;
		}

		/**
		 * Keeps the key and tenant id out of var_dump()/print_r() output.
		 *
		 * @return array{baseUrl: string, tenantId: string, apiKey: string}
		 */
		public function __debugInfo() {
			return array('baseUrl' => $this->baseUrl, 'tenantId' => '***', 'apiKey' => '***');
		}

		/**
		 * The value of the Authorization header. This is the only place that knows its format.
		 *
		 * The swagger's securityScheme (apiKey in header "Authorization") says "For tenant access copy the key
		 * <GUID>&<GUID>", without a "Bearer " prefix; the Tenant schema has id and apiKey.
		 * SD-815 OPEN: (Semantic Q9) is it "<tenantId>&<apiKey>" or "<apiKey>&<tenantId>", and really without "Bearer "?
		 *
		 * @param string $tenantId
		 * @param string $apiKey
		 * @return string
		 */
		public static function authorizationValue(
			string $tenantId,
			#[\SensitiveParameter]
			string $apiKey
		) {
			return $tenantId . '&' . $apiKey;
		}

		/**
		 * The default transport: curl with a connect and a total timeout, no redirects.
		 *
		 * @param int $connectTimeout Seconds.
		 * @param int $timeout        Seconds for the whole call.
		 * @return callable(string, string, array, ?string): array{status: int, body: string, error: ?string}
		 */
		public static function curlTransport($connectTimeout = 10, $timeout = 30) {
			$connectTimeout = max(1, (int)$connectTimeout);
			$timeout = max($connectTimeout, (int)$timeout);
			return function ($method, $url, array $headers, $body) use ($connectTimeout, $timeout) {
				if (!function_exists('curl_init')) {
					return array('status' => 0, 'body' => '', 'error' => 'PHP-udvidelsen curl mangler');
				}
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
				curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
				if ($body !== null) {
					// A GET with a body (GetOutgoingDocuments) keeps its verb through CURLOPT_CUSTOMREQUEST.
					curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
				} elseif ($method !== 'GET' && $method !== 'DELETE') {
					// A PUT/POST/PATCH without content still sends Content-Length: 0.
					curl_setopt($ch, CURLOPT_POSTFIELDS, '');
				}
				$responseBody = curl_exec($ch);
				$errno = curl_errno($ch);
				$error = $errno ? 'curl ' . $errno . ': ' . curl_error($ch) : null;
				$status = $errno ? 0 : (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
				unset($ch);
				return array('status' => $status, 'body' => is_string($responseBody) ? $responseBody : '', 'error' => $error);
			};
		}

		/**
		 * POST /api/OutgoingDocument/CreateOutgoingDocument?senderEndpointId=<uuid>, body DocumentHeader.
		 *
		 * On success data is the int64 outgoing document id (see documentIdFrom()).
		 * SD-815 OPEN: (Q8) is senderEndpointId required and checked against AccountingSupplierParty/EndpointID?
		 * SD-815 OPEN: (Q5) is a validation error a 4xx here or a stored document with status 5101? Is a duplicate rejected,
		 *              and what is the safe retry after a timeout?
		 * SD-815 OPEN: what does DocumentHeader.status mean? It is only sent when the caller passes it.
		 *
		 * @param string   $senderEndpointId Semantic's endpoint id (uuid) of the sender; '' leaves the parameter out.
		 * @param string   $xml              The UBL document, UTF-8.
		 * @param int      $networkType      NETWORK_PEPPOL or NETWORK_NEMHANDEL.
		 * @param int|null $status           DocumentHeader.status, normally null.
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function createOutgoingDocument($senderEndpointId, $xml, $networkType, $status = null) {
			$senderEndpointId = trim((string)$senderEndpointId);
			if ($senderEndpointId !== '' && !self::isUuid($senderEndpointId)) {
				return self::localError('Afsenderens endpoint-id er ugyldigt');
			}
			$header = self::documentHeader($xml, $networkType);
			if (!is_array($header)) {
				return self::localError($header);
			}
			if ($status !== null) {
				$header['status'] = (int)$status;
			}
			$query = ($senderEndpointId !== '') ? array('senderEndpointId' => $senderEndpointId) : array();
			return $this->request('POST', '/api/OutgoingDocument/CreateOutgoingDocument', $query, $header, true);
		}

		/**
		 * POST /api/OutgoingDocument/ValidatePeppolDocument, body DocumentHeader. data is the validation report (a string).
		 *
		 * The swagger says "Use this for test only, as the validation is also done when creating".
		 * SD-815 OPEN: (validation) may it be called before every production send, and what is the report's format?
		 *
		 * @param string $xml         The UBL document, UTF-8.
		 * @param int    $networkType NETWORK_PEPPOL or NETWORK_NEMHANDEL.
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function validatePeppolDocument($xml, $networkType) {
			$header = self::documentHeader($xml, $networkType);
			if (!is_array($header)) {
				return self::localError($header);
			}
			return $this->request('POST', '/api/OutgoingDocument/ValidatePeppolDocument', array(), $header, true);
		}

		/**
		 * GET /api/OutgoingDocument/status/{documentId}. data is the OutgoingDocumentStatus code (int).
		 *
		 * @param int|string $documentId The int64 from createOutgoingDocument().
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function getOutgoingDocumentStatus($documentId) {
			$documentId = self::documentIdFrom($documentId);
			if ($documentId === null) {
				return self::localError('Ugyldigt dokument-id');
			}
			return $this->request('GET', '/api/OutgoingDocument/status/' . $documentId);
		}

		/**
		 * GET /api/OutgoingDocument/{documentId}. data is the OutgoingDocument (documentStatus, errorText, ublDocumentId ...).
		 *
		 * @param int|string $documentId
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function getOutgoingDocument($documentId) {
			$documentId = self::documentIdFrom($documentId);
			if ($documentId === null) {
				return self::localError('Ugyldigt dokument-id');
			}
			return $this->request('GET', '/api/OutgoingDocument/' . $documentId);
		}

		/**
		 * GET /api/OutgoingDocument/GetOutgoingDocuments with a DocumentFilter as JSON body. data is a list of OutgoingDocument.
		 *
		 * SD-815 OPEN: a GET with a body is fragile through proxies; ask Semantic for a POST or query-string version.
		 *
		 * @param array<string, mixed> $filter DocumentFilter fields, e.g. documentNumber, senderEndpointIds, pageNumber, pageSize.
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function getOutgoingDocuments(array $filter) {
			return $this->request('GET', '/api/OutgoingDocument/GetOutgoingDocuments', array(), (object)$filter, true);
		}

		/**
		 * POST /api/Company with a CompanyDto (companyId, name, cvr, countryCode, webHookUrl, endpoints, addresses, contacts).
		 *
		 * SD-815 OPEN: (Q6) the swagger documents no response body; is the company id returned, or is the companyId we send used?
		 *
		 * @param array<string, mixed> $company
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function createCompany(array $company) {
			return $this->request('POST', '/api/Company', array(), (object)$company, true);
		}

		/**
		 * GET /api/Company?companyId=&vatIdentifier=&... data is a list of CompanyDto.
		 *
		 * SD-815 OPEN: does vatIdentifier match with or without the "DK" prefix?
		 *
		 * @param array<string, scalar> $query pageNumber, pageSize, companyId, countryCode, vatIdentifier, searchText, showDeleted.
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function getCompanies(array $query) {
			$allowed = array('pageNumber', 'pageSize', 'companyId', 'countryCode', 'vatIdentifier', 'searchText', 'showDeleted');
			$clean = array();
			foreach ($allowed as $name) {
				if (isset($query[$name]) && is_scalar($query[$name])) {
					$clean[$name] = is_bool($query[$name]) ? ($query[$name] ? 'true' : 'false') : (string)$query[$name];
				}
			}
			return $this->request('GET', '/api/Company', $clean);
		}

		/**
		 * POST /api/Endpoint with an EndpointDetailsDto (endpointId, companyId, endpointType, endpointIdentifier).
		 *
		 * The endpoint type is the ISO 6523 scheme as a string ("0184" CVR without DK, "0088" GLN).
		 * SD-815 OPEN: (Q6) no response body is documented; the id is looked up with getEndpointByIdentifier() afterwards.
		 *
		 * @param array<string, mixed> $endpoint
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function createEndpoint(array $endpoint) {
			return $this->request('POST', '/api/Endpoint', array(), (object)$endpoint, true);
		}

		/**
		 * GET /api/Endpoint/getEndpointId/{endpointIdentifier}/{endpointType}. data is the EndpointModel.
		 *
		 * @param string $identifier e.g. the 8 CVR digits.
		 * @param string $scheme     e.g. "0184".
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function getEndpointByIdentifier($identifier, $scheme) {
			$identifier = trim((string)$identifier);
			$scheme = trim((string)$scheme);
			if ($identifier === '' || $scheme === '') {
				return self::localError('Endpoint mangler');
			}
			return $this->request('GET', '/api/Endpoint/getEndpointId/' . rawurlencode($identifier) . '/' . rawurlencode($scheme));
		}

		/**
		 * PATCH /api/Company/{companyId}/defaultEndpoint/{endpointId}: "Sets the default billing endpoint for a company".
		 *
		 * @param string $companyId  uuid
		 * @param string $endpointId uuid
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function setDefaultEndpoint($companyId, $endpointId) {
			if (!self::isUuid($companyId) || !self::isUuid($endpointId)) {
				return self::localError('Ugyldigt company- eller endpoint-id');
			}
			return $this->request('PATCH', '/api/Company/' . $companyId . '/defaultEndpoint/' . $endpointId);
		}

		/**
		 * PUT /api/Endpoint/registerInPeppol/{endpointId}, no body.
		 *
		 * SD-815 OPEN: (Q6) synchronous? How long until the endpoint can receive? Which error when the identifier is held
		 *              by another access point? registerInPeppol or registerInPeppolViaNemHandel for Danish companies?
		 *
		 * @param string $endpointId uuid
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function registerInPeppol($endpointId) {
			if (!self::isUuid($endpointId)) {
				return self::localError('Ugyldigt endpoint-id');
			}
			return $this->request('PUT', '/api/Endpoint/registerInPeppol/' . $endpointId);
		}

		/**
		 * PUT /api/Endpoint/registerInNemHandel/{endpointId}, no body.
		 *
		 * @param string $endpointId uuid
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function registerInNemHandel($endpointId) {
			if (!self::isUuid($endpointId)) {
				return self::localError('Ugyldigt endpoint-id');
			}
			return $this->request('PUT', '/api/Endpoint/registerInNemHandel/' . $endpointId);
		}

		/**
		 * GET /api/Endpoint/isEndpointInPeppol/{endpointIdentifier}/{endpointType}/{documentType}: identifier first, then scheme.
		 * On success data is true/false (null when the answer could not be read).
		 *
		 * SD-815 OPEN: the documentType codes (110/120) are documented only on the Nemhandel lookup.
		 *
		 * @param string $scheme       ISO 6523 scheme, e.g. "0184" or "0088".
		 * @param string $identifier   The receiver's identifier in that scheme.
		 * @param int    $documentType DOCUMENT_TYPE_INVOICE or DOCUMENT_TYPE_CREDITNOTE.
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function isEndpointInPeppol($scheme, $identifier, $documentType = self::DOCUMENT_TYPE_INVOICE) {
			$scheme = trim((string)$scheme);
			$identifier = trim((string)$identifier);
			if ($scheme === '' || $identifier === '') {
				return self::localError('Modtagerens endpoint mangler');
			}
			$result = $this->request('GET', '/api/Endpoint/isEndpointInPeppol/' . rawurlencode($identifier) . '/' . rawurlencode($scheme) . '/' . (int)$documentType);
			return self::withBoolData($result);
		}

		/**
		 * GET /api/Endpoint/isEndpointInNemHandel/{endpointType}/{endpointIdentifier}/{documentType}: scheme first, then
		 * identifier (the opposite order of isEndpointInPeppol). On success data is true/false (null when unreadable).
		 *
		 * @param string $scheme
		 * @param string $identifier
		 * @param int    $documentType
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function isEndpointInNemHandel($scheme, $identifier, $documentType = self::DOCUMENT_TYPE_INVOICE) {
			$scheme = trim((string)$scheme);
			$identifier = trim((string)$identifier);
			if ($scheme === '' || $identifier === '') {
				return self::localError('Modtagerens endpoint mangler');
			}
			$result = $this->request('GET', '/api/Endpoint/isEndpointInNemHandel/' . rawurlencode($scheme) . '/' . rawurlencode($identifier) . '/' . (int)$documentType);
			return self::withBoolData($result);
		}

		/**
		 * GET /api/UblDocument/pdf/{ublDocumentId}. The swagger types the answer as a string with format byte (base64);
		 * on success data is the decoded PDF.
		 *
		 * SD-815 OPEN: (PDF) is the rendering Danish, and does it work for outgoing documents?
		 *
		 * @param string $ublDocumentId uuid (OutgoingDocument.ublDocumentId).
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		public function getUblDocumentPdf($ublDocumentId) {
			if (!self::isUuid($ublDocumentId)) {
				return self::localError('Ugyldigt UBL-dokument-id');
			}
			$result = $this->request('GET', '/api/UblDocument/pdf/' . $ublDocumentId);
			if (!$result['ok']) {
				return $result;
			}
			$pdf = is_string($result['data']) ? base64_decode(preg_replace('/\s+/', '', $result['data']), true) : false;
			if ($pdf === false || $pdf === '') {
				$result['ok'] = false;
				$result['kind'] = 'unknown';
				$result['data'] = null;
				$result['message'] = 'Semantic sendte ikke en læsbar PDF';
				return $result;
			}
			$result['data'] = $pdf;
			return $result;
		}

		/**
		 * The outgoing document id in an answer: a positive integer, as a string of digits. Accepts the int64 that
		 * CreateOutgoingDocument returns, a numeric string, or null.
		 *
		 * @param mixed $data
		 * @return string|null
		 */
		public static function documentIdFrom($data) {
			if (is_int($data) && $data > 0) {
				return (string)$data;
			}
			if (is_string($data) && preg_match('/^\s*"?([0-9]{1,19})"?\s*$/', $data, $m) && ltrim($m[1], '0') !== '') {
				return ltrim($m[1], '0');
			}
			return null;
		}

		/**
		 * Whether a value is a uuid in the usual 8-4-4-4-12 hex form.
		 *
		 * @param mixed $value
		 * @return bool
		 */
		public static function isUuid($value) {
			return is_string($value) && (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
		}

		/**
		 * Send one request through the transport and classify the answer.
		 *
		 * @param string               $method
		 * @param string               $path     Starts with /api/.
		 * @param array<string,string> $query
		 * @param mixed                $jsonBody Encoded as JSON when $hasBody.
		 * @param bool                 $hasBody
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		private function request($method, $path, array $query = array(), $jsonBody = null, $hasBody = false) {
			$url = $this->baseUrl . $path;
			if ($query) {
				$url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
			}
			$headers = array(
				'Authorization: ' . self::authorizationValue($this->tenantId, $this->apiKey),
				'Accept: application/json',
			);
			$body = null;
			if ($hasBody) {
				$body = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				if ($body === false) {
					return self::localError('Kunne ikke lave JSON til Semantic: ' . json_last_error_msg());
				}
				$headers[] = 'Content-Type: application/json';
			}
			try {
				$raw = call_user_func($this->transport, $method, $url, $headers, $body);
			} catch (Throwable $e) {
				$raw = array('status' => 0, 'body' => '', 'error' => get_class($e) . ': ' . $e->getMessage());
			}
			return $this->classify(is_array($raw) ? $raw : array('status' => 0, 'body' => '', 'error' => 'Ugyldigt svar fra transport'));
		}

		/**
		 * Classify a transport answer.
		 *
		 * kind: 'ok' (2xx), 'validation' (400, 409, 413, 422: the document or request was refused and a retry will not help),
		 * 'auth' (401, 403), 'transient' (no answer, 408, 425, 429, 5xx: try again later; for a create the outcome is unknown),
		 * 'unknown' (anything else).
		 * SD-815 OPEN: the swagger documents only 200; the error codes and bodies are assumptions (ASP.NET ProblemDetails).
		 *
		 * @param array{status?: int, body?: string, error?: string|null} $raw
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		private function classify(array $raw) {
			$status = (int)($raw['status'] ?? 0);
			$body = isset($raw['body']) && is_string($raw['body']) ? $raw['body'] : '';
			$error = isset($raw['error']) && $raw['error'] !== null ? trim((string)$raw['error']) : '';
			if ($error !== '' || $status <= 0) {
				return self::result(false, 'transient', $status, null,
					$this->shortText('Ingen forbindelse til Semantic' . ($error !== '' ? ': ' . $error : '')));
			}
			$data = self::decodeBody($body);
			if ($status >= 200 && $status < 300) {
				return self::result(true, 'ok', $status, $data, '');
			}
			if (in_array($status, array(400, 409, 413, 422), true)) {
				$kind = 'validation';
			} elseif ($status === 401 || $status === 403) {
				$kind = 'auth';
			} elseif (in_array($status, array(408, 425, 429), true) || $status >= 500) {
				$kind = 'transient';
			} else {
				$kind = 'unknown';
			}
			$text = self::problemText($data);
			if ($text === '') {
				$text = $body;
			}
			return self::result(false, $kind, $status, null, $this->shortText('HTTP ' . $status . ($text !== '' ? ': ' . $text : '')));
		}

		/**
		 * @param bool   $ok
		 * @param string $kind
		 * @param int    $httpStatus
		 * @param mixed  $data
		 * @param string $message
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		private static function result($ok, $kind, $httpStatus, $data, $message) {
			return array('ok' => (bool)$ok, 'kind' => $kind, 'httpStatus' => (int)$httpStatus, 'data' => $data, 'message' => $message);
		}

		/**
		 * A request refused before anything was sent.
		 *
		 * @param string $message
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		private static function localError($message) {
			return self::result(false, 'validation', 0, null, (string)$message);
		}

		/**
		 * The DocumentHeader body, or an error text.
		 *
		 * @param string $xml
		 * @param int    $networkType
		 * @return array{base64EncodedUtf8DocumentXml: string, networkType: int}|string
		 */
		private static function documentHeader($xml, $networkType) {
			$xml = (string)$xml;
			if (trim($xml) === '') {
				return 'Dokumentet er tomt';
			}
			if (function_exists('mb_check_encoding') && !mb_check_encoding($xml, 'UTF-8')) {
				return 'Dokumentet er ikke UTF-8';
			}
			$networkType = (int)$networkType;
			if ($networkType !== self::NETWORK_PEPPOL && $networkType !== self::NETWORK_NEMHANDEL) {
				return 'Ukendt netværk ' . $networkType;
			}
			return array('base64EncodedUtf8DocumentXml' => base64_encode($xml), 'networkType' => $networkType);
		}

		/**
		 * JSON when the body is JSON (scalars too: "true", 12345), else the trimmed text; null when empty.
		 *
		 * @param string $body
		 * @return mixed
		 */
		private static function decodeBody($body) {
			$body = trim($body);
			if ($body === '') {
				return null;
			}
			$decoded = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
			if (json_last_error() === JSON_ERROR_NONE) {
				return $decoded;
			}
			return $body;
		}

		/**
		 * The readable part of an error body: ProblemDetails title/detail/errors, or a message field, or a plain string.
		 *
		 * @param mixed $data
		 * @return string
		 */
		private static function problemText($data) {
			if (is_string($data)) {
				return $data;
			}
			if (!is_array($data)) {
				return '';
			}
			$parts = array();
			foreach (array('title', 'detail', 'message', 'errorText', 'error') as $key) {
				if (isset($data[$key]) && is_scalar($data[$key]) && trim((string)$data[$key]) !== '') {
					$parts[] = trim((string)$data[$key]);
				}
			}
			if (isset($data['errors']) && is_array($data['errors'])) {
				foreach ($data['errors'] as $field => $messages) {
					foreach ((array)$messages as $message) {
						if (is_scalar($message) && count($parts) < 6) {
							$parts[] = (is_string($field) ? $field . ': ' : '') . $message;
						}
					}
				}
			}
			return implode(' - ', array_values(array_unique($parts)));
		}

		/**
		 * One line of at most MESSAGE_MAX characters, without tags, the key or the tenant id.
		 *
		 * @param string $text
		 * @return string
		 */
		private function shortText($text) {
			$text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$text)) ?? '');
			$secrets = array(self::authorizationValue($this->tenantId, $this->apiKey), $this->apiKey, $this->tenantId);
			foreach ($secrets as $secret) {
				if ($secret !== '') {
					$text = str_ireplace(array($secret, rawurlencode($secret)), '***', $text);
				}
			}
			if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > self::MESSAGE_MAX) {
				$text = mb_substr($text, 0, self::MESSAGE_MAX - 3, 'UTF-8') . '...';
			} elseif (!function_exists('mb_strlen') && strlen($text) > self::MESSAGE_MAX) {
				$text = substr($text, 0, self::MESSAGE_MAX - 3) . '...';
			}
			return $text;
		}

		/**
		 * Turn a lookup's data into true/false/null. The Nemhandel lookups have no schema in the swagger
		 * ("Returns true is the endpoint is found ... and false if not").
		 *
		 * @param array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string} $result
		 * @return array{ok: bool, kind: string, httpStatus: int, data: mixed, message: string}
		 */
		private static function withBoolData(array $result) {
			if (!$result['ok']) {
				return $result;
			}
			$data = $result['data'];
			if (is_string($data)) {
				$data = strtolower(trim($data, " \t\n\r\0\x0B\""));
				$data = ($data === 'true') ? true : (($data === 'false') ? false : null);
			} elseif (is_int($data) && ($data === 0 || $data === 1)) {
				$data = (bool)$data;
			} elseif (!is_bool($data)) {
				$data = null;
			}
			$result['data'] = $data;
			if ($data === null) {
				$result['ok'] = false;
				$result['kind'] = 'unknown';
				$result['message'] = 'Semantic svarede hverken ja eller nej på opslaget';
			}
			return $result;
		}
	}
}

if (!function_exists('semantic_key_file_path')) {
	/**
	 * The gitignored key file one level above the installation: the file pages reach as ../../.ht_keys.txt
	 * (includes/opdat_5.0.php), resolved from this file instead of the working directory.
	 *
	 * @return string
	 */
	function semantic_key_file_path() {
		return dirname(__DIR__, 3) . '/.ht_keys.txt';
	}
}

if (!function_exists('semantic_key_file_values')) {
	/**
	 * The Semantic variables from the key file. The file is included inside a function, so its other
	 * variables (the other providers' keys) stay local, and anything it prints is discarded.
	 *
	 * @param string|null $keyFile Another file, for tests; null = semantic_key_file_path().
	 * @return array{semanticBaseUrl?: mixed, semanticTenantId?: mixed, semanticApiKey?: mixed, semanticWebhookSecret?: mixed, semanticSigningSecrets?: mixed, semanticWebhookBaseUrl?: mixed}
	 */
	function semantic_key_file_values($keyFile = null) {
		static $cache = array();
		$file = ($keyFile !== null) ? (string)$keyFile : semantic_key_file_path();
		if (isset($cache[$file])) {
			return $cache[$file];
		}
		$values = array();
		if (is_file($file) && is_readable($file)) {
			$read = static function ($semanticKeyFile) {
				ob_start();
				try {
					include $semanticKeyFile;
				} catch (Throwable $e) {
					// A broken key file means "not configured"; the reason is never shown, it could quote the file.
				} finally {
					ob_end_clean();
				}
				return get_defined_vars();
			};
			$all = $read($file);
			$names = array('semanticBaseUrl', 'semanticTenantId', 'semanticApiKey', 'semanticWebhookSecret', 'semanticSigningSecrets', 'semanticWebhookBaseUrl');
			foreach ($names as $name) {
				if (isset($all[$name])) {
					$values[$name] = $all[$name];
				}
			}
		}
		$cache[$file] = $values;
		return $values;
	}
}

if (!function_exists('semantic_env')) {
	/**
	 * An environment variable, trimmed; null when unset or empty.
	 *
	 * @param string $name
	 * @return string|null
	 */
	function semantic_env($name) {
		$value = getenv((string)$name);
		if ($value === false) {
			return null;
		}
		$value = trim($value);
		return ($value === '') ? null : $value;
	}
}

if (!function_exists('semantic_credentials')) {
	/**
	 * The server's Semantic connection: the three SALDI_SEMANTIC_* environment variables when all are set, else
	 * $semanticBaseUrl/$semanticTenantId/$semanticApiKey from the key file. The two sources are never mixed, so a dev
	 * URL can not be combined with a production key. Never read from or stored in a database.
	 *
	 * @param string|null $keyFile Another key file, for tests.
	 * @return array{baseUrl: string, tenantId: string, apiKey: string}|null Null when Semantic is not set up on this server.
	 */
	function semantic_credentials($keyFile = null) {
		$base = semantic_env('SALDI_SEMANTIC_BASE_URL');
		$tenant = semantic_env('SALDI_SEMANTIC_TENANT_ID');
		$key = semantic_env('SALDI_SEMANTIC_API_KEY');
		if ($base !== null && $tenant !== null && $key !== null) {
			return array('baseUrl' => rtrim($base, '/'), 'tenantId' => $tenant, 'apiKey' => $key);
		}
		$values = semantic_key_file_values($keyFile);
		$base = isset($values['semanticBaseUrl']) && is_string($values['semanticBaseUrl']) ? trim($values['semanticBaseUrl']) : '';
		$tenant = isset($values['semanticTenantId']) && is_string($values['semanticTenantId']) ? trim($values['semanticTenantId']) : '';
		$key = isset($values['semanticApiKey']) && is_string($values['semanticApiKey']) ? trim($values['semanticApiKey']) : '';
		if ($base !== '' && $tenant !== '' && $key !== '') {
			return array('baseUrl' => rtrim($base, '/'), 'tenantId' => $tenant, 'apiKey' => $key);
		}
		return null;
	}
}

if (!function_exists('semantic_webhook_secret')) {
	/**
	 * Our own secret for the webhook URL's HMAC token (SALDI_SEMANTIC_WEBHOOK_SECRET or $semanticWebhookSecret).
	 * A secret shorter than 32 characters counts as missing.
	 *
	 * @param string|null $keyFile
	 * @return string|null
	 */
	function semantic_webhook_secret($keyFile = null) {
		$secret = semantic_env('SALDI_SEMANTIC_WEBHOOK_SECRET');
		if ($secret === null) {
			$values = semantic_key_file_values($keyFile);
			$secret = isset($values['semanticWebhookSecret']) && is_string($values['semanticWebhookSecret']) ? trim($values['semanticWebhookSecret']) : '';
		}
		return (strlen((string)$secret) >= 32) ? (string)$secret : null;
	}
}

if (!function_exists('semantic_signing_secrets')) {
	/**
	 * Semantic's own webhook signing secret(s): one while signing is on, two for a week after a rotation
	 * (portal "webhook-signing/rotate": "every call carries a signature for each").
	 *
	 * @param string|null $keyFile
	 * @return list<string>
	 */
	function semantic_signing_secrets($keyFile = null) {
		$raw = semantic_env('SALDI_SEMANTIC_SIGNING_SECRETS');
		if ($raw !== null) {
			$list = explode(',', $raw);
		} else {
			$values = semantic_key_file_values($keyFile);
			$list = isset($values['semanticSigningSecrets']) ? (array)$values['semanticSigningSecrets'] : array();
		}
		$secrets = array();
		foreach ($list as $secret) {
			if (is_string($secret) && trim($secret) !== '') {
				$secrets[] = trim($secret);
			}
		}
		return $secrets;
	}
}

if (!function_exists('semantic_webhook_base_url')) {
	/**
	 * The installation's public base URL for the webhook (SALDI_SEMANTIC_WEBHOOK_BASE_URL or $semanticWebhookBaseUrl),
	 * e.g. https://<server>/<installation>. Null when not configured or not https.
	 *
	 * @param string|null $keyFile
	 * @return string|null
	 */
	function semantic_webhook_base_url($keyFile = null) {
		$url = semantic_env('SALDI_SEMANTIC_WEBHOOK_BASE_URL');
		if ($url === null) {
			$values = semantic_key_file_values($keyFile);
			$url = isset($values['semanticWebhookBaseUrl']) && is_string($values['semanticWebhookBaseUrl']) ? trim($values['semanticWebhookBaseUrl']) : '';
		}
		$url = rtrim((string)$url, '/');
		return preg_match('#^https://[^/\s]+#i', $url) ? $url : null;
	}
}

if (!function_exists('semantic_client')) {
	/**
	 * A client for this server's Semantic tenant, or null when Semantic is not set up here.
	 *
	 * @param array{baseUrl: string, tenantId: string, apiKey: string}|null $credentials Null = semantic_credentials().
	 * @param array{connectTimeout?: int, timeout?: int} $options
	 * @return SemanticClient|null
	 */
	function semantic_client($credentials = null, array $options = array()) {
		$credentials = $credentials ?? semantic_credentials();
		if (!is_array($credentials)) {
			return null;
		}
		try {
			return new SemanticClient($credentials['baseUrl'], $credentials['tenantId'], $credentials['apiKey'], null, $options);
		} catch (InvalidArgumentException $e) {
			return null;
		}
	}
}
