<?php
// 20260921 CDX/LH Preserve fixture connection options and credentials when passing libpq DSNs to PDO.

/**
 * Decode a libpq keyword DSN or PostgreSQL URI for the PDO regression fixture.
 *
 * @return array<string, string>
 */
function regressionPostgresParameters(string $dsn): array
{
    if (preg_match('~^postgres(?:ql)?://~', $dsn)) {
        $uri = parse_url($dsn);
        if ($uri === false) {
            throw new InvalidArgumentException('Invalid PostgreSQL fixture URI');
        }
        $parameters = [];
        foreach (['host' => 'host', 'port' => 'port', 'user' => 'user', 'pass' => 'password'] as $part => $key) {
            if (isset($uri[$part])) {
                $parameters[$key] = rawurldecode((string)$uri[$part]);
            }
        }
        if (isset($uri['path']) && $uri['path'] !== '/') {
            $parameters['dbname'] = rawurldecode(substr($uri['path'], 1));
        }
        foreach (explode('&', $uri['query'] ?? '') as $pair) {
            if ($pair === '') continue;
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $parameters[rawurldecode($key)] = rawurldecode($value);
        }
        return $parameters;
    }
    $parameters = [];
    $length = strlen($dsn);
    $offset = 0;
    while ($offset < $length) {
        if (!preg_match('/\G\s*([a-zA-Z_][a-zA-Z_0-9]*)\s*=\s*/A', $dsn, $match, 0, $offset)) {
            if (trim(substr($dsn, $offset)) === '') break;
            throw new InvalidArgumentException('Invalid PostgreSQL fixture connection parameters');
        }
        $offset += strlen($match[0]);
        $quoted = ($dsn[$offset] ?? '') === "'";
        if ($quoted) $offset++;
        $value = '';
        $closed = !$quoted;
        while ($offset < $length) {
            $character = $dsn[$offset++];
            if ($character === '\\') {
                if ($offset === $length) {
                    throw new InvalidArgumentException('Incomplete PostgreSQL fixture escape');
                }
                $value .= $dsn[$offset++];
            } elseif ($quoted && $character === "'") {
                $closed = true;
                break;
            } elseif (!$quoted && ctype_space($character)) {
                break;
            } else {
                $value .= $character;
            }
        }
        if (!$closed || ($quoted && $offset < $length && !ctype_space($dsn[$offset]))) {
            throw new InvalidArgumentException('Invalid quoted PostgreSQL fixture parameter');
        }
        $parameters[$match[1]] = $value;
    }
    return $parameters;
}

/** Open the exact configured test fixture without discarding DSN authentication. */
function regressionPostgresPdo(string $dsn): PDO
{
    $parameters = regressionPostgresParameters($dsn);
    $user = $parameters['user'] ?? (getenv('SALDI_CHAR_PGUSER') ?: null);
    $password = $parameters['password'] ?? (getenv('SALDI_CHAR_PGPASS') ?: null);
    unset($parameters['user'], $parameters['password']);
    $options = [];
    foreach ($parameters as $key => $value) {
        // PDO replaces every semicolon in its DSN before handing it to libpq.
        // Credentials travel separately, preserving even semicolons and quotes.
        if (!preg_match('/^[a-zA-Z_][a-zA-Z_0-9]*$/D', $key) || str_contains($value, ';') || str_contains($value, "\0")) {
            throw new InvalidArgumentException('Unsupported PostgreSQL fixture option for PDO');
        }
        $options[] = $key . "='" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }
    return new PDO('pgsql:' . implode(' ', $options), $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
