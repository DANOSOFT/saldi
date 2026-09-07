<?php
// 20260907 CDX/LH Scoped, short-lived access to saved assistant records.
// 20260908 CDX/LH Read authorization headers across Apache and CGI SAPIs.

final class SaldiAssistFailure extends RuntimeException
{
    public function __construct(string $reason, public int $status = 403)
    {
        parent::__construct($reason);
    }
}

final class SaldiAssistRecordAuth
{
    public const TTL = 300;

    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** @param array<string,mixed> $claims */
    public static function sign(array $claims, string $kid, string $secret): string
    {
        if (strlen($secret) < 32 || !preg_match('/^[A-Za-z0-9_-]{1,32}$/D', $kid)) {
            throw new SaldiAssistFailure('not_configured', 503);
        }
        ksort($claims);
        $input = 'r1.' . $kid . '.' . self::encode(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $input . '.' . self::encode(hash_hmac('sha256', $input, $secret, true));
    }

    /** @return array<string,mixed> */
    public static function verify(string $token, string $kid, string $secret, int $now): array
    {
        $parts = explode('.', $token);
        if (strlen($token) > 2048 || count($parts) !== 4 || $parts[0] !== 'r1' || $parts[1] !== $kid) {
            throw new SaldiAssistFailure('invalid_grant');
        }
        $input = implode('.', array_slice($parts, 0, 3));
        if (strlen($secret) < 32 || !hash_equals(self::encode(hash_hmac('sha256', $input, $secret, true)), $parts[3])) {
            throw new SaldiAssistFailure('invalid_grant');
        }
        $json = base64_decode(strtr($parts[2], '-_', '+/'), true);
        $c = $json === false ? null : json_decode($json, true);
        if (!is_array($c) || ($c['aud'] ?? '') !== 'saldi-assist-record' || ($c['iss'] ?? '') !== 'saldi'
            || !is_int($c['iat'] ?? null) || !is_int($c['exp'] ?? null)
            || $c['iat'] > $now + 30 || $c['exp'] <= $now || $c['exp'] <= $c['iat']
            || $c['exp'] - $c['iat'] > self::TTL
            || !in_array($c['kind'] ?? '', ['journal', 'invoice'], true)
            || !is_int($c['record_id'] ?? null) || $c['record_id'] < 1) {
            throw new SaldiAssistFailure('invalid_grant');
        }
        foreach (['tenant_hash', 'user_hash', 'embed_session', 'session_locator'] as $field) {
            if (!is_string($c[$field] ?? null) || !preg_match('/^[0-9a-f]{32}$/D', $c[$field])) {
                throw new SaldiAssistFailure('invalid_grant');
            }
        }
        if (!is_string($c['session_tag'] ?? null) || !preg_match('/^[0-9a-f]{64}$/D', $c['session_tag'])) {
            throw new SaldiAssistFailure('invalid_grant');
        }
        return $c;
    }

    /** @param array<string,mixed> $online
     *  @return array<string,mixed> */
    public static function claims(array $online, string $kind, int $id, string $embed, string $secret, int $now): array
    {
        return [
            'iss' => 'saldi', 'aud' => 'saldi-assist-record', 'iat' => $now, 'exp' => $now + self::TTL,
            'kind' => $kind, 'record_id' => $id,
            // Lookup only; the HMAC below authenticates the live session. SALDI online has no primary key.
            'session_locator' => md5($online['session_id']),
            'tenant_hash' => substr(hash('sha256', 'saldi-tenant:' . $online['db']), 0, 32),
            'user_hash' => substr(hash('sha256', 'saldi-user:' . $online['db'] . ':' . $online['brugernavn']), 0, 32),
            'embed_session' => $embed,
            'session_tag' => hash_hmac('sha256', 'saldi-record-session:' . $online['session_id'], $secret),
        ];
    }

    /** Match the live login, including company switches and session revocation.
     *  @param array<string,mixed> $claims
     *  @param array<string,mixed> $online */
    public static function assertSession(array $claims, array $online, string $secret): void
    {
        $current = self::claims($online, $claims['kind'], $claims['record_id'], $claims['embed_session'], $secret, time());
        foreach (['tenant_hash', 'user_hash', 'session_tag'] as $field) {
            if (!hash_equals($claims[$field], $current[$field])) {
                throw new SaldiAssistFailure('session_changed');
            }
        }
    }
}

/**
 * Apache can expose Authorization only through getallheaders(), unlike CGI.
 * Conflicting values fail closed; forwarded authorization headers are ignored.
 *
 * @param array<string,mixed> $server
 * @param array<string,mixed> $headers
 * @return string The request's authorization value, or empty when absent/ambiguous.
 */
function saldi_assist_authorization(array $server, array $headers): string
{
    $values = [];
    if (isset($server['HTTP_AUTHORIZATION']) && is_string($server['HTTP_AUTHORIZATION'])) {
        $values[] = $server['HTTP_AUTHORIZATION'];
    }
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0 && is_string($value)) {
            $values[] = $value;
        }
    }
    $values = array_values(array_unique($values));
    return count($values) === 1 ? $values[0] : '';
}

/** An independent PostgreSQL connection: no online.php, migrations or logging writes. */
function saldi_assist_read_connection(string $database): PDO
{
    if (!preg_match('/^[A-Za-z0-9_-]{1,63}$/D', $database)) {
        throw new SaldiAssistFailure('not_configured', 503);
    }
    $host = getenv('SALDI_ASSIST_DB_HOST');
    $user = getenv('SALDI_ASSIST_DB_USER');
    $password = getenv('SALDI_ASSIST_DB_PASSWORD');
    $port = (int)(getenv('SALDI_ASSIST_DB_PORT') ?: 5432);
    if (!$host || !$user || $password === false) {
        throw new SaldiAssistFailure('not_configured', 503);
    }
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database;connect_timeout=3", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET statement_timeout = '3000ms'");
    $pdo->exec("SET client_encoding = 'UTF8'");
    $pdo->exec('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    return $pdo;
}

/** @param array<int,mixed> $parameters
 *  @return array<string,mixed>|null */
function saldi_assist_one(PDO $pdo, string $sql, array $parameters = []): ?array
{
    $query = $pdo->prepare($sql);
    $query->execute($parameters);
    return $query->fetch() ?: null;
}

/** @param array<string,mixed> $online */
function saldi_assist_authorize(PDO $master, PDO $tenant, array $online, string $kind): void
{
    $zone = 'Europe/Copenhagen';
    if (saldi_assist_one($master, "SELECT to_regclass('settings') AS name")['name']) {
        $setting = saldi_assist_one($master, "SELECT var_value FROM settings WHERE var_name = 'timezone' LIMIT 1");
        $zone = (string)($setting['var_value'] ?? '') ?: $zone;
    }
    $today = (new DateTimeImmutable('now', new DateTimeZone($zone)))->format('Y-m-d');
    $company = saldi_assist_one($master, 'SELECT lukket, lukkes FROM regnskab WHERE db = ?', [$online['db']]);
    if (!$company || $company['lukket'] === 'on'
        || (!empty($company['lukkes']) && $company['lukkes'] <= $today)) {
        throw new SaldiAssistFailure('company_unavailable');
    }
    $user = saldi_assist_one($tenant, 'SELECT rettigheder FROM brugere WHERE brugernavn = ?', [$online['brugernavn']]);
    // The same module positions used by kassekladde.php and debitor/ordre.php.
    $position = $kind === 'journal' ? 2 : 5;
    $rights = (string)($user['rettigheder'] ?? '');
    if (!preg_match('/^[0-9]+$/D', $rights) || (int)substr($rights, $position, 1) < 1) {
        throw new SaldiAssistFailure('record_forbidden');
    }
}
