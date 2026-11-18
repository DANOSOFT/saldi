<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebClient.php --- ver 5.1.1 --- 2026-10-28 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
// 20261006 CDX/LH Created Enable Banking ebClient backend service.
// 20261028 CDX/LH Classify unsupported transaction date ranges as period errors.
// 20261028 CDX/LH Recognize stored consent expiry and preserve known authorization/configuration support codes.

require_once __DIR__ . '/ebConfig.php';

interface EbTransport
{
    /**
 * @param list<string> $headers
 * @return array{status:int, body:string, error:string}
 */
    public function send(string $method, string $url, array $headers, ?string $body): array;
}

final class EbCurlTransport implements EbTransport
{
    /**
 * @param list<string> $headers
 * @return array{status:int, body:string, error:string}
 */
    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return ['status' => $response === false ? 0 : $status, 'body' => $response === false ? '' : $response,
            'error' => $response === false ? 'Transport failure' : ''];
    }
}

final class EbApiException extends RuntimeException
{
    /** @return void Messages are controlled locally; provider body/message is never exposed. */
    public function __construct(public readonly int $httpStatus, public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    /** @return string consent|action_required|period|rate_limit|transient|continuation|permanent */
    public function kind(): string
    {
        if (in_array($this->errorCode, ['CONSENT_EXPIRED', 'EXPIRED_SESSION', 'REVOKED_SESSION', 'CLOSED_SESSION', 'SESSION_DOES_NOT_EXIST',
            'ACCOUNT_DOES_NOT_EXIST', 'ASPSP_ACCOUNT_NOT_ACCESSIBLE', 'UNAUTHORIZED_ACCESS', 'WRONG_SESSION_STATUS'], true)) {
            return 'consent';
        }
        if ($this->errorCode === 'ASPSP_PSU_ACTION_REQUIRED') {
            return 'action_required';
        }
        if (in_array($this->errorCode, ['WRONG_TRANSACTIONS_PERIOD', 'WRONG_DATE_INTERVAL'], true)) {
            return 'period';
        }
        if ($this->httpStatus === 429 || $this->errorCode === 'ASPSP_RATE_LIMIT_EXCEEDED') {
            return 'rate_limit';
        }
        if ($this->errorCode === 'WRONG_CONTINUATION_KEY') {
            return 'continuation';
        }
        if ($this->httpStatus === 0 || $this->httpStatus === 408 || $this->httpStatus >= 500
            || in_array($this->errorCode, ['ASPSP_ERROR', 'ASPSP_TIMEOUT', 'INVALID_RESPONSE'], true)) {
            return 'transient';
        }
        return 'permanent';
    }
}

final class EnableBankingClient
{
    private EbTransport $transport;
    private Closure $clock;
    private string $cachedJwt = '';
    private int $jwtExpires = 0;

    /**
     * @param array{app_id:string, key_path:string, redirect_url:string, environment?:string, configured?:bool, problem?:string} $config
     * @param callable():int|null $clock
     * @return void
     */
    public function __construct(private array $config, ?EbTransport $transport = null, ?callable $clock = null)
    {
        $this->transport = $transport ?? new EbCurlTransport();
        $this->clock = Closure::fromCallable($clock ?? 'time');
    }

    /** @return string Cached RS256 provider application JWT. */
    public function jwt(): string
    {
        $now = ($this->clock)();
        if ($this->cachedJwt !== '' && $now < $this->jwtExpires - 300) {
            return $this->cachedJwt;
        }
        $keyText = is_readable($this->config['key_path']) ? file_get_contents($this->config['key_path']) : false;
        $key = $keyText !== false ? openssl_pkey_get_private($keyText) : false;
        if (!$key || openssl_pkey_get_details($key)['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new EbApiException(0, 'CONFIGURATION', 'Enable Banking signeringsnøgle kan ikke læses.');
        }
        $encode = static fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $header = $encode(json_encode(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => $this->config['app_id']], JSON_THROW_ON_ERROR));
        $payload = $encode(json_encode(['iss' => 'enablebanking.com', 'aud' => 'api.enablebanking.com',
            'iat' => $now, 'exp' => $now + 3600], JSON_THROW_ON_ERROR));
        $input = $header . '.' . $payload;
        if (!openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new EbApiException(0, 'CONFIGURATION', 'Enable Banking signering mislykkedes.');
        }
        $this->jwtExpires = $now + 3600;
        return $this->cachedJwt = $input . '.' . $encode($signature);
    }

    /** @return array{environment:string, redirect_urls:list<string>, ...} */
    public function application(): array
    {
        $response = $this->request('GET', '/application');
        if (!is_string($response['environment'] ?? null) || !is_array($response['redirect_urls'] ?? null)) {
            $this->invalid();
        }
        return $response;
    }

    /** @return list<array{name:string, country:string, psu_types:list<string>, maximum_consent_validity?:int, required_psu_headers?:list<string>, ...}> */
    public function aspsps(string $country = 'DK', ?string $psuType = null): array
    {
        $response = $this->request('GET', '/aspsps?' . http_build_query(array_filter(['country' => $country, 'psu_type' => $psuType], static fn($v) => $v !== null)));
        if (!is_array($response['aspsps'] ?? null) || !array_is_list($response['aspsps'])) {
            $this->invalid();
        }
        foreach ($response['aspsps'] as $bank) {
            if (!is_array($bank) || !is_string($bank['name'] ?? null) || !is_string($bank['country'] ?? null) || !is_array($bank['psu_types'] ?? null)) {
                $this->invalid();
            }
        }
        return $response['aspsps'];
    }

    /** @return array{url:string, authorization_id:string, ...} */
    public function startAuth(string $aspspName, string $country, string $psuType, string $state, string $validUntilIso, string $language = 'da'): array
    {
        $response = $this->request('POST', '/auth', ['access' => ['valid_until' => $validUntilIso],
            'aspsp' => ['name' => $aspspName, 'country' => $country], 'state' => $state,
            'redirect_url' => $this->config['redirect_url'], 'psu_type' => $psuType, 'language' => $language]);
        if (!is_string($response['url'] ?? null) || !filter_var($response['url'], FILTER_VALIDATE_URL)
            || !in_array(parse_url($response['url'], PHP_URL_SCHEME), ['http', 'https'], true)
            || !is_string($response['authorization_id'] ?? null)) {
            $this->invalid();
        }
        return $response;
    }

    /** @return array{session_id:string, accounts:list<array{uid:?string, identification_hash:string, currency:string, ...}>, access:array{valid_until:string}, ...} */
    public function createSession(string $code): array
    {
        $response = $this->request('POST', '/sessions', ['code' => $code]);
        if (!is_string($response['session_id'] ?? null) || $response['session_id'] === ''
            || !is_array($response['accounts'] ?? null) || !array_is_list($response['accounts'])
            || !is_array($response['access'] ?? null) || !is_string($response['access']['valid_until'] ?? null)
            || strtotime($response['access']['valid_until']) === false) {
            $this->invalid();
        }
        foreach ($response['accounts'] as $account) {
            if (!is_array($account) || !is_string($account['identification_hash'] ?? null) || $account['identification_hash'] === ''
                || !is_string($account['currency'] ?? null) || (isset($account['uid']) && !is_string($account['uid']))) {
                $this->invalid();
            }
        }
        return $response;
    }

    /** @return array{status:string, access?:array{valid_until:string}, ...} */
    public function getSession(string $sessionId): array
    {
        $response = $this->request('GET', '/sessions/' . rawurlencode($sessionId));
        if (!is_string($response['status'] ?? null)) {
            $this->invalid();
        }
        return $response;
    }

    /** @return void Close consent at the provider. */
    public function deleteSession(string $sessionId): void
    {
        $response = $this->request('DELETE', '/sessions/' . rawurlencode($sessionId));
        if (!is_string($response['message'] ?? null)) {
            $this->invalid();
        }
    }

    /**
 * @param array<string,string> $psuHeaders
 * @return array{transactions:list<array<string,mixed>>, continuation_key:?string}
 */
    public function transactions(string $accountUid, string $dateFrom, string $dateTo, ?string $continuationKey, array $psuHeaders = []): array
    {
        $query = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'transaction_status' => 'BOOK'];
        if ($continuationKey !== null && $continuationKey !== '') {
            $query['continuation_key'] = $continuationKey;
        }
        $response = $this->request('GET', '/accounts/' . rawurlencode($accountUid) . '/transactions?' . http_build_query($query), null, $psuHeaders);
        if (!is_array($response['transactions'] ?? null) || !array_is_list($response['transactions'])
            || (isset($response['continuation_key']) && !is_string($response['continuation_key']))) {
            $this->invalid();
        }
        foreach ($response['transactions'] as $transaction) {
            if (!is_array($transaction)) {
                $this->invalid();
            }
        }
        return ['transactions' => $response['transactions'], 'continuation_key' => $response['continuation_key'] ?? null];
    }

    /** @return never */
    private function invalid(int $status = 200): never
    {
        throw new EbApiException($status, 'INVALID_RESPONSE', 'Enable Banking returnerede et ugyldigt svar.');
    }

    /**
 * @param array<string,mixed>|null $body
 * @param array<string,string> $psuHeaders
 * @return array<string,mixed>
 */
    private function request(string $method, string $path, ?array $body = null, array $psuHeaders = []): array
    {
        $headers = ['Authorization: Bearer ' . $this->jwt(), 'Accept: application/json', 'Content-Type: application/json'];
        foreach ($psuHeaders as $name => $value) {
            if (in_array(strtolower($name), array_map('strtolower', ebPsuHeaderNames()), true) && !preg_match('/[\r\n]/', $value)) {
                $headers[] = $name . ': ' . $value;
            }
        }
        $response = $this->transport->send($method, 'https://api.enablebanking.com' . $path, $headers,
            $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR));
        $status = $response['status'];
        $decoded = json_decode($response['body'], true);
        if ($status < 200 || $status >= 300) {
            // Only known error codes can leave the client; arbitrary provider text may contain identifiers.
            $known = ['ACCESS_DENIED', 'ACCOUNT_DOES_NOT_EXIST', 'ALREADY_AUTHORIZED', 'ASPSP_ACCOUNT_NOT_ACCESSIBLE', 'ASPSP_ERROR',
                'ASPSP_PSU_ACTION_REQUIRED', 'ASPSP_RATE_LIMIT_EXCEEDED', 'ASPSP_TIMEOUT', 'CLOSED_SESSION',
                'AUTHORIZATION_NOT_PROVIDED', 'DATE_FROM_IN_FUTURE', 'EXPIRED_AUTHORIZATION_CODE', 'EXPIRED_SESSION',
                'INVALID_HOST', 'NO_ACCOUNTS_ADDED', 'PSU_HEADER_INVALID', 'PSU_HEADER_NOT_PROVIDED', 'REDIRECT_URI_NOT_ALLOWED',
                'REVOKED_SESSION', 'SESSION_DOES_NOT_EXIST', 'UNAUTHORIZED_ACCESS', 'UNAUTHORIZED_IP', 'WRONG_ASPSP_PROVIDED',
                'WRONG_AUTHORIZATION_CODE', 'WRONG_CONTINUATION_KEY', 'WRONG_CREDENTIALS_PROVIDED', 'WRONG_DATE_INTERVAL',
                'WRONG_REQUEST_PARAMETERS', 'WRONG_TRANSACTIONS_PERIOD', 'WRONG_SESSION_STATUS'];
            $code = is_array($decoded) ? ($decoded['error'] ?? '') : '';
            $code = in_array($code, $known, true) ? $code : ($status === 0 ? 'TRANSPORT_ERROR' : 'HTTP_ERROR');
            throw new EbApiException($status, $code, 'Enable Banking: ' . $code . ' (HTTP ' . $status . ').');
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            $this->invalid($status);
        }
        return $decoded;
    }
}

/** @return list<string> Supported user-present headers. */
function ebPsuHeaderNames(): array
{
    return ['Psu-Ip-Address', 'Psu-User-Agent', 'Psu-Referer', 'Psu-Accept', 'Psu-Accept-Charset', 'Psu-Accept-Encoding', 'Psu-Accept-language'];
}

/** @return array<string,string> Available request metadata; background callers pass an empty array. */
function ebPsuHeadersFromRequest(): array
{
    if (PHP_SAPI === 'cli') {
        return [];
    }
    $out = [];
    foreach (ebPsuHeaderNames() as $name) {
        $source = $name === 'Psu-Ip-Address' ? 'REMOTE_ADDR' : 'HTTP_' . strtoupper(str_replace('-', '_', substr($name, 4)));
        $value = $_SERVER[$source] ?? '';
        if (is_string($value) && $value !== '' && !preg_match('/[\r\n]/', $value)) {
            $out[$name] = $value;
        }
    }
    return $out;
}

/**
 * @param array<string,string> $available
 * @param list<string> $required
 * @return array<string,string> All required PSU headers, or none.
 */
function ebFilterPsuHeaders(array $available, array $required): array
{
    $lower = array_change_key_case($available, CASE_LOWER);
    foreach ($required as $name) {
        if (empty($lower[strtolower($name)])) {
            return [];
        }
    }
    return $available;
}
