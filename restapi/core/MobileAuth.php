<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/MobileAuth.php --- ver 5.0.0 --- 2026.10.08
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Preserve nonrotating refresh for omitted-device legacy sessions without bypassing revocation.
// 20261001 CDX/LH Enforce refresh IP rules, tenant closure, bounded rotation locks and auth housekeeping.
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.

require_once __DIR__ . '/MobileSchema.php';
require_once __DIR__ . '/Subject.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/JwtSecretProvisioning.php';
require_once __DIR__ . '/../../includes/mobileRegistryUpdates.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';

/** A client-visible authentication failure, without internal diagnostics. */
class MobileAuthError extends RuntimeException
{
    public $error;
    public $status;

    public function __construct(string $error, int $status = 401)
    {
        parent::__construct($error);
        $this->error = $error;
        $this->status = $status;
    }
}

/** Registry-backed session families. PostgreSQL is the production auth store. */
final class MobileAuth
{
    private $db;
    private $connection;

    public function __construct()
    {
        $this->connection = self::connectRegistry();
        mobileRegistryUpdates($this->connection);
        $this->db = new MobileSchema($this->connection);
    }

    /** Open the registry without changing the caller's selected tenant link. */
    public static function connectRegistry()
    {
        global $sqhost, $squser, $sqpass, $sqdb, $db_encode;
        static $links = [];
        $key = hash('sha256', $sqhost . "\0" . $sqdb . "\0" . $squser . "\0" . $sqpass);
        if (!isset($links[$key])) {
            $quote = static function (string $value): string {
                return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
            };
            $dsn = 'host=' . $quote($sqhost) . ' dbname=' . $quote($sqdb) . ' user=' . $quote($squser) . ' password=' . $quote($sqpass);
            $links[$key] = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
            if (!$links[$key]) {
                throw new RuntimeException('Auth registry unavailable');
            }
            pg_set_client_encoding($links[$key], $db_encode === 'UTF8' ? 'UTF8' : 'LATIN9');
        }
        return $links[$key];
    }

    /** @return MobileSchema Explicit registry connection, never the selected tenant. */
    public function store(): MobileSchema
    {
        return $this->db;
    }

    /** @return string Canonical UUID v4 for a family or a challenge. */
    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
        $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        $h = bin2hex($bytes);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20);
    }

    /** Missing device ids preserve legacy login; an explicit id must be nonempty. */
    public static function device($data): string
    {
        $device = ifset($data, 'device_id', 'legacy');
        if (!is_string($device) || strlen($device) > 128 || !preg_match('/^[A-Za-z0-9._:-]+$/D', $device)) {
            throw new MobileAuthError('validation_failed', 400);
        }
        return $device;
    }

    /** Parse PostgreSQL booleans without treating the string 'f' as true. */
    public static function enabled($value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true', 'on'], true);
    }

    /** Account and username normalization matches Saldi's Scandinavian case folding. */
    public static function normalize(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }

    /** Exact comma-separated IP equality, as web login; proxy headers never select the IP. */
    public static function enforceIp(array $user, string $ip): void
    {
        $allow = (string)ifset($user, 'ip_address', '');
        if (!self::enabled(ifset($user, 'mobil_undtaget', false)) && $allow !== '' && !in_array($ip, explode(',', $allow), true)) {
            throw new MobileAuthError('ip_rejected', 403);
        }
    }

    /** Foundation's shared account bucket key, including the principal namespace. */
    public static function bucketKey(string $principal, string $account, string $username): string
    {
        return hash('sha256', json_encode([$principal, self::normalize($account), self::normalize($username)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** Consume both buckets atomically; B3 supplies principal=registry and an empty account. */
    public function rateLimit(string $ip, string $account, string $username, string $principal = 'account'): void
    {
        $packed = inet_pton($ip);
        if ($packed === false || !in_array($principal, ['account', 'registry'], true)) {
            throw new RuntimeException('Invalid server login identity');
        }
        $ip = inet_ntop($packed);
        $this->db->execute('BEGIN');
        try {
            $allowed = true;
            foreach ([['ip', $ip, 120, 2], ['account', self::bucketKey($principal, $account, $username), 10, 1 / 60]] as [$type, $key, $capacity, $rate]) {
                $key = $this->db->literal($key);
                $this->db->execute("INSERT INTO auth_rate_limits(bucket_type,bucket_key,tokens,updated_at) VALUES ('$type',$key,$capacity,now()) ON CONFLICT DO NOTHING");
                $row = $this->db->rows("SELECT tokens, EXTRACT(EPOCH FROM (clock_timestamp()-updated_at)) AS elapsed FROM auth_rate_limits WHERE bucket_type='$type' AND bucket_key=$key FOR UPDATE")[0];
                $tokens = min($capacity, (float)$row['tokens'] + max(0, (float)$row['elapsed']) * $rate);
                if ($tokens < 1) {
                    $allowed = false;
                } else {
                    $tokens -= 1;
                }
                $this->db->execute("UPDATE auth_rate_limits SET tokens=" . (float)$tokens . ",updated_at=clock_timestamp() WHERE bucket_type='$type' AND bucket_key=$key");
            }
            $this->db->execute('COMMIT');
        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
        if (!$allowed) {
            throw new MobileAuthError('rate_limited', 429);
        }
    }

    /** Consume a per-family Assist bucket: 30 sessions per minute across tenants. */
    public function assistRateLimit(string $familyId): void
    {
        $key = $this->db->literal(hash('sha256', 'assistant-session:' . $familyId));
        $this->db->execute('BEGIN');
        try {
            $this->db->execute("INSERT INTO auth_rate_limits(bucket_type,bucket_key,tokens,updated_at) VALUES ('account',$key,30,now()) ON CONFLICT DO NOTHING");
            $row = $this->db->rows("SELECT tokens,EXTRACT(EPOCH FROM (clock_timestamp()-updated_at)) AS elapsed FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$key FOR UPDATE")[0];
            $tokens = min(30, (float)$row['tokens'] + max(0, (float)$row['elapsed']) * 0.5);
            $allowed = $tokens >= 1;
            $this->db->execute("UPDATE auth_rate_limits SET tokens=" . max(0, $tokens - ($allowed ? 1 : 0)) . ",updated_at=clock_timestamp() WHERE bucket_type='account' AND bucket_key=$key");
            $this->db->execute('COMMIT');
        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
        if (!$allowed) {
            throw new MobileAuthError('rate_limited', 429);
        }
    }

    /** Refund a verified attempt without resetting failures or the shared IP budget. */
    public function verifiedLogin(string $account, string $username, string $principal = 'account'): void
    {
        $key = $this->db->literal(self::bucketKey($principal, $account, $username));
        $this->db->execute("UPDATE auth_rate_limits SET tokens=LEAST(10,tokens+1) WHERE bucket_type='account' AND bucket_key=$key");
    }

    /**
     * Opportunistic authenticated-session housekeeping, at most 500 rows per table per pass.
     * Keep expired rotations for a day and revoked rows for 30 days; a missing old jti
     * still revokes its surviving family, so cleanup cannot turn reuse into a login.
     * @return void
     */
    public function prune(): void
    {
        $this->db->execute("DELETE FROM refresh_sessions WHERE id IN (
            SELECT id FROM refresh_sessions WHERE expires_at<now()-INTERVAL '1 day'
                OR revoked_at<now()-INTERVAL '30 days' LIMIT 500 FOR UPDATE SKIP LOCKED)");
        $this->db->execute("DELETE FROM auth_challenges WHERE id IN (
            SELECT id FROM auth_challenges WHERE expires_at<now()-INTERVAL '1 day'
                OR consumed_at<now()-INTERVAL '1 day' LIMIT 500 FOR UPDATE SKIP LOCKED)");
        // Even an empty bucket is full after one day at either configured refill rate.
        $this->db->execute("DELETE FROM auth_rate_limits WHERE (bucket_type,bucket_key) IN (
            SELECT bucket_type,bucket_key FROM auth_rate_limits WHERE updated_at<now()-INTERVAL '1 day'
                AND (blocked_until IS NULL OR blocked_until<now()) LIMIT 500 FOR UPDATE SKIP LOCKED)");
    }

    /** @return array{tenant:?array, user:array, connection:mixed} Current principal record and tenant. */
    public function context(string $principal, int $userId, ?int $tenantId): array
    {
        global $sqhost, $squser, $sqpass, $connection;
        $tenant = null;
        $link = $this->connection;
        if ($tenantId !== null) {
            $rows = $this->db->rows("SELECT *, (lukkes IS NOT NULL AND lukkes<>'2099-12-31' AND lukkes::date<=CURRENT_DATE) AS scheduled_closed FROM regnskab WHERE id=" . (int)$tenantId);
            $tenant = ifset($rows, 0);
            if (!$tenant || $tenant['lukket'] === 'on' || self::enabled($tenant['scheduled_closed'])) {
                throw new MobileAuthError('account_closed', 403);
            }
            if ($principal === 'account') {
                $previous = $connection;
                try {
                    $link = db_connect($sqhost, $squser, $sqpass, $tenant['db'], __FILE__ . ' linje ' . __LINE__);
                    mobileTenantUpdates($link);
                } finally {
                    $connection = $previous;
                }
            }
        } elseif ($principal !== 'registry') {
            throw new MobileAuthError('invalid_token');
        }
        $users = (new MobileSchema($link))->rows('SELECT * FROM brugere WHERE id=' . (int)$userId);
        $user = ifset($users, 0);
        if (!$user || (array_key_exists('status', $user) && $user['status'] !== null && !self::enabled($user['status']))) {
            throw new MobileAuthError('user_disabled', 403);
        }
        if ($principal === 'registry' && $tenantId !== null) {
            require_once __DIR__ . '/Rights.php';
            $rights = Rights::registry((string)$user['rettigheder']);
            if (!$rights['admin'] && !in_array($tenantId, $rights['tenant_ids'], true)) {
                throw new MobileAuthError('permission_denied', 403);
            }
        }
        return ['tenant' => $tenant, 'user' => $user, 'connection' => $link];
    }

    /** Current epoch; lock=true serializes session issuance against revocation. */
    public function version(string $subject, ?int $tenantId, bool $lock = false): int
    {
        $subject = $this->db->literal($subject);
        $tenant = (int)$tenantId;
        $this->db->execute("INSERT INTO auth_versions(subject,tenant_id) VALUES ($subject,$tenant) ON CONFLICT DO NOTHING");
        $row = $this->db->rows("SELECT auth_version FROM auth_versions WHERE subject=$subject AND tenant_id=$tenant" . ($lock ? ' FOR UPDATE' : ''))[0];
        return (int)$row['auth_version'];
    }

    /** @return array Session token data; challenge epoch must still match under lock. */
    public function create(array $user, string $principal, ?int $tenantId, string $device, ?int $expectedVersion = null, ?string $familyId = null): array
    {
        $subject = Subject::build($principal, (int)$user['id']);
        $family = $familyId ?? self::uuid();
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $family)) {
            throw new MobileAuthError('invalid_token');
        }
        $this->db->execute('BEGIN');
        try {
            $version = $this->version($subject, $principal === 'registry' ? null : $tenantId, true);
            if ($expectedVersion !== null && $version !== $expectedVersion) {
                throw new MobileAuthError('session_revoked');
            }
            if ($familyId !== null) {
                $f = $this->db->literal($familyId);
                $existing = ifset($this->db->rows("SELECT *,expires_at>now() AS unexpired FROM refresh_sessions WHERE family_id=$f ORDER BY id DESC LIMIT 1 FOR UPDATE"), 0);
                if (!$existing || $existing['revoked_at'] !== null || !self::enabled($existing['unexpired'])
                    || $existing['subject'] !== $subject || $existing['principal'] !== $principal
                    || $existing['device_id'] !== $device || (int)$existing['auth_version'] !== $version
                    || ($principal === 'account' && (int)$existing['tenant_id'] !== (int)$tenantId)) {
                    throw new MobileAuthError('session_revoked');
                }
                $scope = $tenantId === null ? 'tenant_id IS NULL' : 'tenant_id=' . (int)$tenantId;
                $previous = ifset($this->db->rows("SELECT id FROM refresh_sessions WHERE family_id=$f AND $scope ORDER BY id DESC LIMIT 1 FOR UPDATE"), 0);
                if ($previous) {
                    $this->db->execute('UPDATE refresh_sessions SET last_used_at=clock_timestamp() WHERE id=' . (int)$previous['id']);
                }
            }
            $row = $this->insert($family, $subject, $principal, $tenantId, $device, $version);
            $this->db->execute('COMMIT');
        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
        return $this->tokens($row, $user);
    }

    /** @return array Persist a new rotation; the raw jti is never stored. */
    private function insert(string $family, string $subject, string $principal, ?int $tenantId, string $device, int $version): array
    {
        $f = $this->db->literal($family);
        $s = $this->db->literal($subject);
        $p = $this->db->literal($principal);
        $d = $this->db->literal($device);
        $t = $tenantId === null ? 'NULL' : (int)$tenantId;
        $jti = bin2hex(random_bytes(32));
        $hash = $this->db->literal(hash('sha256', $jti));
        $nonce = random_bytes(12);
        $tag = '';
        $key = hash_hkdf('sha256', _jwtLoadSecret(), 32, 'mobile-refresh-replay');
        $cipher = openssl_encrypt($jti, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $family);
        if ($cipher === false) {
            throw new RuntimeException('Refresh replay encryption failed');
        }
        $encrypted = $this->db->literal(base64_encode($nonce . $tag . $cipher));
        return $this->db->rows("INSERT INTO refresh_sessions(jti_hash,jti_ciphertext,family_id,subject,principal,tenant_id,device_id,expires_at,auth_version) VALUES ($hash,$encrypted,$f,$s,$p,$t,$d,now()+INTERVAL '30 days',$version) RETURNING *")[0];
    }

    /** Recover the current random jti for lost-response retries; the registry holds only its hash and authenticated ciphertext. */
    private function jti(array $row): string
    {
        $bytes = base64_decode((string)ifset($row, 'jti_ciphertext', ''), true);
        if ($bytes === false || strlen($bytes) < 29) {
            throw new RuntimeException('Refresh replay ciphertext unavailable');
        }
        $key = hash_hkdf('sha256', _jwtLoadSecret(), 32, 'mobile-refresh-replay');
        $jti = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), $row['family_id']);
        if ($jti === false || !hash_equals($row['jti_hash'], hash('sha256', $jti))) {
            throw new RuntimeException('Refresh replay decryption failed');
        }
        return $jti;
    }

    /** @return array{access_token:string,refresh_token:string,token_type:string,expires_in:int} */
    private function tokens(array $row, array $user): array
    {
        if (random_int(1, 100) === 1) {
            $this->prune();
        }
        $claims = ['user_id' => (int)$user['id'], 'username' => $user['brugernavn'], 'principal' => $row['principal'], 'sub' => $row['subject'], 'tenant_id' => $row['tenant_id'] === null ? null : (int)$row['tenant_id'], 'sid' => $row['family_id'], 'auth_version' => (int)$row['auth_version'], 'device_id' => $row['device_id']];
        return ['access_token' => JWT::encode($claims + ['type' => 'access'], 3600), 'refresh_token' => JWT::encode($claims + ['type' => 'refresh', 'jti' => $this->jti($row)], max(1, strtotime($row['expires_at']) - time())), 'token_type' => 'Bearer', 'expires_in' => 3600];
    }

    /**
     * Rotate or replay a session; omitted-device legacy clients keep their original refresh token.
     *
     * @param bool $rotate False permits access-only refresh exclusively for the legacy device.
     * @return array{access_token:string,refresh_token?:string,token_type:string,expires_in:int}
     */
    public function refresh(array $payload, string $device, bool $rotate = true): array
    {
        $this->identity($payload);
        if (empty($payload['sid']) || empty($payload['jti'])) {
            throw new MobileAuthError('session_revoked');
        }
        $family = $this->db->literal((string)$payload['sid']);
        $this->db->execute('BEGIN');
        try {
            // The auth epoch lock is always acquired before family rows, matching revoke/create.
            $this->version($payload['sub'], $payload['principal'] === 'registry' ? null : $payload['tenant_id'], true);
            $scope = ifset($payload, 'tenant_id') === null ? 'tenant_id IS NULL' : 'tenant_id=' . (int)$payload['tenant_id'];
            $hash = $this->db->literal(hash('sha256', (string)$payload['jti']));
            // Only the newest two rotations and the presented jti are needed, even in a long-lived family.
            $rows = $this->db->rows("SELECT *, expires_at>now() AS unexpired, EXTRACT(EPOCH FROM (clock_timestamp()-last_used_at)) AS since_use FROM refresh_sessions WHERE family_id=$family AND $scope AND id IN ((SELECT id FROM refresh_sessions WHERE family_id=$family AND $scope ORDER BY id DESC LIMIT 2) UNION (SELECT id FROM refresh_sessions WHERE jti_hash=$hash)) ORDER BY id DESC FOR UPDATE");
            $current = ifset($rows, 0);
            $matched = null;
            foreach ($rows as $i => $row) {
                if (hash_equals($row['jti_hash'], hash('sha256', (string)$payload['jti']))) {
                    $matched = $i;
                }
            }
            $valid = $current && $matched !== null && $current['revoked_at'] === null && self::enabled($current['unexpired'])
                && $current['subject'] === $payload['sub'] && $current['principal'] === $payload['principal']
                && (($current['tenant_id'] === null && ifset($payload, 'tenant_id') === null) || ($current['tenant_id'] !== null && (int)$current['tenant_id'] === (int)ifset($payload, 'tenant_id'))) && (int)$current['auth_version'] === (int)ifset($payload, 'auth_version', 0)
                && $current['device_id'] === $device && $current['device_id'] === ifset($payload, 'device_id');
            if ($valid) {
                $context = $this->context($payload['principal'], (int)$payload['user_id'], ifset($payload, 'tenant_id') === null ? null : (int)$payload['tenant_id']);
                self::enforceIp($context['user'], (string)ifset($_SERVER, 'REMOTE_ADDR', ''));
            }
            if ($valid && $matched === 0) {
                if ($rotate || $current['device_id'] !== 'legacy') {
                    $this->db->execute('UPDATE refresh_sessions SET last_used_at=clock_timestamp() WHERE id=' . (int)$current['id']);
                    $current = $this->insert($current['family_id'], $current['subject'], $current['principal'], $current['tenant_id'] === null ? null : (int)$current['tenant_id'], $current['device_id'], (int)$current['auth_version']);
                }
            } elseif (!$valid || $matched !== 1 || $rows[1]['last_used_at'] === null || (float)$rows[1]['since_use'] > 60) {
                $this->db->execute("UPDATE refresh_sessions SET revoked_at=COALESCE(revoked_at,now()) WHERE family_id=$family");
                $valid = false;
            }
            $this->db->execute('COMMIT');
        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
        if (!$valid) {
            require_once __DIR__ . '/MobileAuthRevocation.php';
            mobileRevokeAssistGrants($this->db->rows("SELECT DISTINCT family_id,tenant_id FROM refresh_sessions WHERE family_id=$family AND tenant_id IS NOT NULL"));
            throw new MobileAuthError('session_revoked');
        }
        $tokens = $this->tokens($current, $context['user']);
        if (!$rotate && $current['device_id'] === 'legacy') {
            unset($tokens['refresh_token']);
        }
        return $tokens;
    }

    /** Check the server-controlled identity independently of request headers. */
    public function identity(array $payload): void
    {
        $principal = ifset($payload, 'principal');
        $id = (int)ifset($payload, 'user_id', 0);
        if (!in_array($principal, ['account', 'registry'], true) || $id < 1
            || ifset($payload, 'sub') !== Subject::build($principal, $id)
            || ($principal === 'account' && (int)ifset($payload, 'tenant_id', 0) < 1)) {
            throw new MobileAuthError('invalid_token');
        }
    }

    /** Revoke a subject's sessions and pending challenges, and bump its persistent epoch. */
    public function revoke(string $subject, string $reason, ?int $tenantId = null): void
    {
        $identity = Subject::parse($subject);
        if ($identity['principal'] === 'account' && $tenantId === null) {
            throw new InvalidArgumentException('Account revocation requires tenant scope');
        }
        if ($identity['principal'] === 'registry') {
            $tenantId = null;
        }
        $s = $this->db->literal($subject);
        $scope = $identity['principal'] === 'registry' ? '' : ' AND tenant_id=' . (int)$tenantId;
        $this->db->execute('BEGIN');
        try {
            $this->version($subject, $tenantId, true);
            $this->db->execute("UPDATE auth_versions SET auth_version=auth_version+1,updated_at=now() WHERE subject=$s AND tenant_id=" . (int)$tenantId);
            $families = $this->db->rows("SELECT DISTINCT family_id,tenant_id FROM refresh_sessions WHERE subject=$s$scope AND tenant_id IS NOT NULL");
            $this->db->execute("UPDATE refresh_sessions SET revoked_at=COALESCE(revoked_at,now()),auth_version=auth_version+1 WHERE subject=$s$scope");
            $this->db->execute("UPDATE auth_challenges SET consumed_at=COALESCE(consumed_at,now()) WHERE subject=$s$scope");
            $this->db->execute('COMMIT');
        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
        require_once __DIR__ . '/MobileAuthRevocation.php';
        mobileRevokeAssistGrants($families);
    }

    /** @return array The one indexed registry SELECT used by every access-token request. */
    public static function checkSession(array $payload): array
    {
        if (empty($payload['sid']) || !isset($payload['auth_version'])) {
            throw new MobileAuthError('session_revoked');
        }
        // No schema runner here: authenticated resource requests pay one session SELECT.
        $db = new MobileSchema(self::connectRegistry());
        $f = $db->literal((string)$payload['sid']);
        $scope = ifset($payload, 'tenant_id') === null ? 's.tenant_id IS NULL' : 's.tenant_id=' . (int)$payload['tenant_id'];
        $rows = $db->rows("SELECT s.*,s.expires_at>now() AS unexpired,
            (s.tenant_id IS NOT NULL AND (r.id IS NULL OR r.lukket='on' OR (r.lukkes IS NOT NULL AND r.lukkes<>'2099-12-31' AND r.lukkes::date<=CURRENT_DATE))) AS account_closed
            FROM refresh_sessions s LEFT JOIN regnskab r ON r.id=s.tenant_id
            WHERE s.family_id=$f AND $scope ORDER BY s.id DESC LIMIT 1");
        $row = ifset($rows, 0);
        if (!$row || $row['revoked_at'] !== null || !self::enabled($row['unexpired'])
            || (int)$row['auth_version'] !== (int)$payload['auth_version'] || $row['subject'] !== ifset($payload, 'sub')
            || $row['principal'] !== ifset($payload, 'principal')
            || (($row['tenant_id'] === null) !== (ifset($payload, 'tenant_id') === null))
            || (int)$row['tenant_id'] !== (int)ifset($payload, 'tenant_id')
            || $row['device_id'] !== ifset($payload, 'device_id')) {
            throw new MobileAuthError('session_revoked');
        }
        if (self::enabled($row['account_closed'])) {
            throw new MobileAuthError('account_closed', 403);
        }
        return $row;
    }
}
