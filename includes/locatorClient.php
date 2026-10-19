<?php
// --- includes/locatorClient.php --- 2026-10-08 ---
// Copyright (c) 2026 Danosoft ApS
// 20261008 CDX/PHR Bound locator calls and validate responses before login uses them.

/** @return mixed|null Decoded successful JSON, or null for transport, HTTP or JSON errors. */
function loginLocatorDecode($status, $body)
{
    if ($status < 200 || $status >= 300 || !is_string($body)) {
        return null;
    }
    $result = json_decode($body, true);
    return json_last_error() === JSON_ERROR_NONE ? $result : null;
}

/** @return mixed|null Locator response; failures never prevent a local login. */
function loginLocatorRequest(array $params)
{
    $ch = curl_init('https://ssl3.saldi.dk/locator/locator.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 5, CURLOPT_HTTPHEADER => array('Accept: application/json')));
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_errno($ch);
    curl_close($ch);
    return $error ? null : loginLocatorDecode($status, $body);
}

/** @return string Valid HTTP(S) installation address, or an empty string. */
function loginLocatorLocation($location)
{
    if (!is_string($location) || $location === '' || preg_match('/[\s<>"\x27]/', $location)) {
        return '';
    }
    if (strpos($location, '://') === false) {
        $location = 'https://' . $location;
    }
    $parts = parse_url($location);
    if (!$parts || !in_array(ifset($parts, 'scheme'), array('http', 'https'), true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment']) || !filter_var($location, FILTER_VALIDATE_URL)) {
        return '';
    }
    return rtrim($location, '/');
}

/** @return string Current installation URL, derived from server configuration and the executing script. */
function loginLocatorInstallation(array $server)
{
    $host = ifset($server, 'SERVER_NAME', '');
    $script = ifset($server, 'SCRIPT_NAME', '');
    if (!$host || !is_string($script) || substr($script, -16) !== '/index/login.php') {
        return '';
    }
    $secure = !empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off';
    $scheme = $secure ? 'https' : 'http';
    $port = (int) ifset($server, 'SERVER_PORT', $secure ? 443 : 80);
    $authority = $host . (($port && $port !== ($secure ? 443 : 80)) ? ':' . $port : '');
    return loginLocatorLocation($scheme . '://' . $authority . substr($script, 0, -16));
}

/** @return int Validated global ID for this database; zero for failures or mismatched responses. */
function loginLocatorGlobalId($response, $dbName, $existingId = 0)
{
    if (!is_string($response)) {
        return 0;
    }
    $parts = explode(',', $response, 3);
    if (count($parts) !== 3 || $parts[1] !== $dbName || !preg_match('/^[1-9][0-9]*$/D', $parts[0])) {
        return 0;
    }
    $id = filter_var($parts[0], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 2147483647)));
    return $id && (!$existingId || $id === (int) $existingId) ? $id : 0;
}
