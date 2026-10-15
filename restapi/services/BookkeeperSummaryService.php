<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/BookkeeperSummaryService.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Convert missing bank totals with dated decimal rates and defer migrations to the warmer.

require_once __DIR__ . '/../core/MobileAuth.php';
require_once __DIR__ . '/../core/Rights.php';
require_once __DIR__ . '/../core/Paging.php';
require_once __DIR__ . '/../../includes/finans/bankLines.php';
require_once __DIR__ . '/../../includes/openPostsService.php';

/** Internal deadline signal; no database or exception text is exposed to clients. */
final class BookkeeperSummaryBudgetExceeded extends RuntimeException
{
}

/** Counts only, with tenant-neutral registry caching and fresh membership on every request. */
class BookkeeperSummaryService
{
    public const MAX_TENANTS = 200;
    public const MAX_COMPUTATIONS = 25;
    public const BUDGET_SECONDS = 8.0;
    private $registry;
    private $clock;
    private ?float $deadline = null;
    private bool $workerMigrations = false;

    /** @param callable():float|null $clock Monotonic clock, injectable for deterministic budget tests. */
    public function __construct($registry, ?callable $clock = null)
    {
        $this->registry = $registry;
        $this->clock = $clock ?? static fn(): float => hrtime(true) / 1e9;
        mobileRegistryUpdates($registry);
    }

    /**
     * A consistent tenant snapshot. Null rights means registry revisor access; account callers
     * pass the current permissions map, never aggregate navigation flags or JWT rights.
     *
     * @param array{debitorrapport?:array{read:bool}}|null $rights
     * @param array{id:int,name:string}|null $tenant Server-resolved registry identity.
     * @return array{
     *   tenant_id:int,name:string,pending_attachments:int,oldest_pending_days:?int,rejected_open:int,
     *   bank_lines_missing:int,bank_lines_missing_total:string, Two-decimal tenant base-currency sum.
     *   overdue:array{count:int,total:string,currency:string}|null,draft_lines_open:int,
     *   last_upload_at:?string,last_reminder_at:?string,needs_attention:bool,computed_at:string
     * }
     */
    public function forTenant($link, ?array $rights = null, ?array $tenant = null): array
    {
        if (pg_transaction_status($link) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Summary requires an idle tenant connection');
        }
        if ($tenant === null) {
            $db = $this->rows($link, 'SELECT current_database() AS db')[0]['db'];
            $tenants = $this->rows($this->registry, 'SELECT id,regnskab AS name FROM regnskab WHERE db=$1 ORDER BY id LIMIT 1', [$db]);
            if (!$tenants) {
                throw new RuntimeException('Summary tenant is not registered');
            }
            $tenant = $tenants[0];
        }
        $this->rows($link, 'BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        try {
            $this->rows($link, "SET LOCAL TIME ZONE 'Europe/Copenhagen'");
            $pool = $this->rows($link, "SELECT
                count(*) FILTER (WHERE status='ubehandlet' AND review_state='pending') AS pending,
                GREATEST(0,(now() AT TIME ZONE 'Europe/Copenhagen')::date -
                    (min(uploaded_at) FILTER (WHERE status='ubehandlet' AND review_state='pending')
                    AT TIME ZONE 'Europe/Copenhagen')::date) AS oldest,
                count(*) FILTER (WHERE status='ubehandlet' AND review_state='rejected') AS rejected,
                max(uploaded_at) FILTER (WHERE status<>'staged') AS last_upload,
                now() AS computed_at FROM pool_files")[0];
            // Reuse B3a's live-state expression and missing relationship rules, summing all rows
            // in SQL so a summary never depends on a paginated bank-line page.
            $currency = $this->rows($link, "SELECT var_value FROM settings WHERE var_name='baseCurrency' ORDER BY id LIMIT 1");
            $baseCurrency = (string)(ifset($currency, [0,'var_value'], 'DKK') ?: 'DKK');
            $state = bank_line_state_sql();
            $bank = $this->rows($link, "SELECT count(*) AS count,
                round(COALESCE(sum(CASE WHEN b.currency=$1 THEN -b.amount
                    ELSE -b.amount*r.rate/100 END),0),2)::text AS total,
                count(*) FILTER (WHERE b.currency<>$1 AND (r.rate IS NULL OR r.rate<=0)) AS missing_rates
                FROM bank_lines b LEFT JOIN LATERAL (
                    SELECT v.kurs::numeric AS rate FROM valuta v JOIN grupper g ON g.kodenr=v.gruppe AND g.art='VK'
                    WHERE g.box1=b.currency AND v.valdate<=b.transdate AND b.currency<>$1
                    ORDER BY v.valdate DESC,v.id DESC LIMIT 1
                ) r ON true WHERE b.amount<0 AND ($state) IN ('open','requested')
                AND EXISTS (SELECT 1 FROM bank_line_journal_links l JOIN kassekladde k ON k.id=l.kassekladde_id
                    WHERE l.bank_line_id=b.id)
                AND NOT EXISTS (SELECT 1 FROM pool_files p WHERE (p.id=b.pool_file_id OR p.bank_line_id=b.id)
                    AND p.status IN ('ubehandlet','attached'))", [$baseCurrency])[0];
            if ((int)$bank['missing_rates'] > 0) {
                throw new RuntimeException('Summary exchange rate unavailable');
            }
            $draft = $this->rows($link, "SELECT count(DISTINCT k.id) AS count FROM mobile_journal_lines m
                JOIN kassekladde k ON k.id=m.kassekladde_id AND k.kladde_id=m.kladde_id
                JOIN kladdeliste kl ON kl.id=k.kladde_id
                WHERE kl.bogfort='-' AND m.mode<>'attach_only'")[0];
            $reminder = $this->rows($link, 'SELECT max(created_at) AS last_reminder FROM reminders')[0];
            $overdue = null;
            if ($rights === null || ifset($rights, ['debitorrapport','read'], false) === true) {
                $this->checkDeadline();
                $reader = new OpenPostsReader(fn(string $sql, array $params): array => $this->rows($link, $sql, $params),
                    $baseCurrency);
                $summary = $reader->summary();
                $this->checkDeadline();
                $overdue = ['count' => $summary['overdue_count'], 'total' => $summary['overdue_total'], 'currency' => $summary['currency']];
            }
            $data = [
                'tenant_id' => (int)$tenant['id'], 'name' => (string)$tenant['name'],
                'pending_attachments' => (int)$pool['pending'],
                'oldest_pending_days' => (int)$pool['pending'] === 0 ? null : (int)$pool['oldest'],
                'rejected_open' => (int)$pool['rejected'], 'bank_lines_missing' => (int)$bank['count'],
                'bank_lines_missing_total' => $bank['total'], 'overdue' => $overdue,
                'draft_lines_open' => (int)$draft['count'], 'last_upload_at' => self::timestamp($pool['last_upload']),
                'last_reminder_at' => self::timestamp($reminder['last_reminder']),
                'needs_attention' => (int)$pool['pending'] + (int)$bank['count'] > 0,
                'computed_at' => self::timestamp($pool['computed_at']),
            ];
            $this->rows($link, 'COMMIT');
            return $data;
        } catch (Throwable $e) {
            $this->rows($link, 'ROLLBACK');
            throw $e;
        }
    }

    /**
     * The explicit id list also bounds admins; admins without ids see live exchanges only (A15).
     * The first 200 by registry name/id are pageable. total is the accessible capped count.
     * totals and pending_count describe the returned page, including stale cached counts.
     *
     * @return array{items:list<array<string,mixed>>,total:int,limit:int,offset:int,totals:array{tenants:int,needs_attention:int,pending_attachments:int,bank_lines_missing:int},pending_count:int,truncated:bool,computed_at:string}
     */
    public function forRegistry(int $userId, string $subject, array $query = []): array
    {
        $paging = new Paging($query);
        $started = ($this->clock)();
        $user = ifset($this->rows($this->registry, 'SELECT * FROM brugere WHERE id=$1', [$userId]), 0);
        if (!$user || (ifset($user, 'status') !== null && !MobileAuth::enabled($user['status']))) {
            throw new MobileAuthError('user_disabled', 403);
        }
        if ($subject !== Subject::build('registry', $userId)) {
            throw new MobileAuthError('permission_denied', 403);
        }
        $rights = Rights::registry((string)$user['rettigheder']);
        $params = [];
        if ($rights['tenant_ids']) {
            $params[] = '{' . implode(',', array_map('intval', $rights['tenant_ids'])) . '}';
            $scope = 'r.id=ANY($1::int[])';
        } elseif ($rights['admin']) {
            $params[] = $subject;
            $scope = "EXISTS (SELECT 1 FROM refresh_sessions s
                JOIN auth_versions v ON v.subject=s.subject AND v.tenant_id=0 AND v.auth_version=s.auth_version
                WHERE s.tenant_id=r.id AND s.subject=$1 AND s.principal='registry'
                AND s.revoked_at IS NULL AND s.expires_at>now())";
        } else {
            $scope = 'false';
        }
        $tenants = $this->rows($this->registry, 'SELECT r.id,r.regnskab AS name,r.db,count(*) OVER () AS total
            FROM regnskab r WHERE ' . self::openTenantSql() . ' AND ' . $scope . '
            ORDER BY r.regnskab,r.id LIMIT 200', $params);
        $total = (int)ifset($tenants, [0,'total'], 0);
        $page = array_slice($tenants, $paging->offset, $paging->limit);
        $computations = 0;
        $items = [];
        foreach ($page as $tenant) {
            $items[] = $this->cachedTenant($tenant, $started + self::BUDGET_SECONDS, $computations);
        }
        $totals = ['tenants' => count($items), 'needs_attention' => 0, 'pending_attachments' => 0, 'bank_lines_missing' => 0];
        $pending = 0;
        foreach ($items as $item) {
            $totals['needs_attention'] += $item['needs_attention'] === true ? 1 : 0;
            $totals['pending_attachments'] += (int)$item['pending_attachments'];
            $totals['bank_lines_missing'] += (int)$item['bank_lines_missing'];
            $pending += $item['state'] === 'pending' ? 1 : 0;
        }
        return $paging->response($items, min(self::MAX_TENANTS, $total)) + [
            'totals' => $totals, 'pending_count' => $pending, 'truncated' => $total > self::MAX_TENANTS,
            'computed_at' => gmdate('c'),
        ];
    }

    /** @return string Server-owned open-tenant predicate over registry alias r. */
    private static function openTenantSql(): string
    {
        return "r.db<>current_database() AND COALESCE(r.lukket,'')<>'on'
            AND (r.lukkes IS NULL OR r.lukkes='2099-12-31' OR r.lukkes>CURRENT_DATE)";
    }

    /** @return array{payload:array,fresh:bool}|null Cached counts without user data or labels. */
    private function cache(int $id): ?array
    {
        $row = ifset($this->rows($this->registry, "SELECT payload,computed_at>now()-interval '2 minutes' AS fresh
            FROM mobile_summary_cache WHERE tenant_id=$1", [$id]), 0);
        if (!$row) {
            return null;
        }
        $payload = json_decode($row['payload'], true);
        if (!is_array($payload) || !isset($payload['pending_attachments'], $payload['computed_at'])) {
            return null;
        }
        return ['payload' => $payload, 'fresh' => $row['fresh'] === 't'];
    }

    /** @return array<string,mixed> Full projection, or nullable fields while no snapshot exists. */
    private function cachedTenant(array $tenant, float $deadline, int &$computations): array
    {
        $id = (int)$tenant['id'];
        $identity = ['tenant_id' => $id, 'name' => (string)$tenant['name']];
        $cache = $this->cache($id);
        if ($cache && $cache['fresh']) {
            return $identity + $cache['payload'] + ['state' => 'ok', 'stale' => false];
        }
        $state = 'pending';
        if ($computations < self::MAX_COMPUTATIONS && ($this->clock)() < $deadline) {
            $locked = $this->rows($this->registry, "SELECT pg_try_advisory_lock(hashtext('saldi:bookkeeper_summary'),$1) AS locked", [$id])[0]['locked'] === 't';
            if ($locked) {
                try {
                    $cache = $this->cache($id);
                    if (!$cache || !$cache['fresh']) {
                        $computations++;
                        $payload = $this->computeTenant($tenant, $deadline);
                        unset($payload['tenant_id'], $payload['name']);
                        $this->rows($this->registry, 'INSERT INTO mobile_summary_cache(tenant_id,payload,computed_at)
                            VALUES ($1,$2,$3) ON CONFLICT (tenant_id) DO UPDATE SET payload=EXCLUDED.payload,computed_at=EXCLUDED.computed_at',
                            [$id,json_encode($payload, JSON_THROW_ON_ERROR),$payload['computed_at']]);
                        $cache = ['payload' => $payload, 'fresh' => true];
                    }
                    return $identity + $cache['payload'] + ['state' => 'ok', 'stale' => false];
                } catch (BookkeeperSummaryBudgetExceeded $e) {
                    $state = 'pending';
                } catch (Throwable $e) {
                    $state = 'error';
                    error_log(json_encode(['tenant_id' => $id, 'error' => 'summary_failed', 'exception' => get_class($e)]));
                } finally {
                    $this->rows($this->registry, "SELECT pg_advisory_unlock(hashtext('saldi:bookkeeper_summary'),$1)", [$id]);
                }
            }
        }
        if ($cache) {
            return $identity + $cache['payload'] + ['state' => 'ok', 'stale' => true];
        }
        return $identity + [
            'pending_attachments' => null, 'oldest_pending_days' => null, 'rejected_open' => null,
            'bank_lines_missing' => null, 'bank_lines_missing_total' => null, 'overdue' => null,
            'draft_lines_open' => null, 'last_upload_at' => null, 'last_reminder_at' => null,
            'needs_attention' => null, 'computed_at' => null, 'state' => $state, 'stale' => true,
        ];
    }

    /** @return array<string,mixed> Bounded snapshot; pending tenant migrations run only in the warmer. */
    protected function computeTenant(array $tenant, float $deadline): array
    {
        $this->deadline = $deadline;
        $link = null;
        try {
            $this->checkDeadline();
            $available = $this->rows($this->registry, "SELECT 1 FROM pg_database WHERE datname=$1 AND has_database_privilege(datname,'CONNECT')", [$tenant['db']]);
            if (!$available) {
                throw new RuntimeException('Summary database unavailable');
            }
            $link = $this->connectTenant((string)$tenant['db']);
            if ($this->workerMigrations) {
                // The CLI warmer finishes schema work before starting a separate snapshot budget.
                $this->deadline = null;
                mobileTenantUpdates($link);
                $this->deadline = ($this->clock)() + self::BUDGET_SECONDS;
            } else {
                $steps = mobileTenantUpdateSteps();
                $marker = $this->rows($link, "SELECT var_value FROM settings WHERE var_grp='mobile'
                    AND var_name='mobile_tenant_step' AND user_id=0 ORDER BY id DESC LIMIT 1");
                if (ifset($marker, [0,'var_value'], '') !== array_key_last($steps)) {
                    throw new BookkeeperSummaryBudgetExceeded();
                }
            }
            $this->checkDeadline();
            $timeout = max(1, (int)floor(($this->deadline - ($this->clock)()) * 1000));
            $this->rows($link, "SELECT set_config('statement_timeout',$1,false),set_config('lock_timeout',$1,false)", [(string)$timeout]);
            return $this->forTenant($link, null, $tenant);
        } finally {
            $this->deadline = null;
            if ($link) {
                pg_close($link);
            }
        }
    }

    /** Connect asynchronously so an unavailable database cannot consume an unbounded request. */
    private function connectTenant(string $db)
    {
        $quote = static fn(string $value): string => "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
        $dsn = 'host=' . $quote((string)$GLOBALS['sqhost']) . ' user=' . $quote((string)$GLOBALS['squser'])
            . ' password=' . $quote((string)$GLOBALS['sqpass']) . ' dbname=' . $quote($db);
        $link = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW | PGSQL_CONNECT_ASYNC);
        if (!$link) {
            throw new RuntimeException('Summary connection failed');
        }
        try {
            do {
                $this->checkDeadline();
                $status = pg_connect_poll($link);
                if ($status === PGSQL_POLLING_FAILED) {
                    throw new RuntimeException('Summary connection failed');
                }
                if ($status !== PGSQL_POLLING_OK) {
                    usleep(1000);
                }
            } while ($status !== PGSQL_POLLING_OK);
            pg_set_client_encoding($link, ifset($GLOBALS, 'db_encode', 'UTF8') === 'UTF8' ? 'UTF8' : 'LATIN9');
            return $link;
        } catch (Throwable $e) {
            pg_close($link);
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> Parameterized queries, with deadline-aware tenant reads. */
    private function rows($link, string $sql, array $params = []): array
    {
        $cleanup = $sql === 'ROLLBACK' || $sql === 'COMMIT';
        if ($this->deadline !== null && $link !== $this->registry && !$cleanup) {
            $this->checkDeadline();
            $timeout = max(1, (int)floor(($this->deadline - ($this->clock)()) * 1000));
            $this->query($link, "SELECT set_config('statement_timeout',$1,false)", [(string)$timeout]);
        }
        return $this->query($link, $sql, $params);
    }

    /** @return list<array<string,mixed>> Checked asynchronous PostgreSQL result, without error text. */
    private function query($link, string $sql, array $params): array
    {
        if (!pg_send_query_params($link, $sql, $params)) {
            throw new RuntimeException('Summary query failed');
        }
        $result = pg_get_result($link);
        while (pg_get_result($link) !== false) {
        }
        if (!$result || !in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true)) {
            if ($result && pg_result_error_field($result, PGSQL_DIAG_SQLSTATE) === '57014') {
                throw new BookkeeperSummaryBudgetExceeded();
            }
            throw new RuntimeException('Summary query failed');
        }
        return pg_num_fields($result) ? pg_fetch_all($result) : [];
    }

    private function checkDeadline(): void
    {
        if ($this->deadline !== null && ($this->clock)() >= $this->deadline) {
            throw new BookkeeperSummaryBudgetExceeded();
        }
    }

    /** @return string|null ISO 8601 UTC instant, preserving absent activity as null. */
    private static function timestamp(?string $value): ?string
    {
        return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('c');
    }

    /**
     * Warm only open tenants with a live exchanged registry session. Per-tenant cache locks are
     * shared with HTTP requests, so overlapping workers never overwrite a newer snapshot.
     * Pending schema work precedes the eight-second snapshot budget; HTTP callers keep stale/pending data.
     *
     * @return list<array{tenant_id:int,state:string,stale:bool}> Safe operational counts, no payloads.
     */
    public function warm(?int $tenantId = null): array
    {
        $params = [Subject::installation() . '/registry/'];
        if ($tenantId !== null) {
            $params[] = $tenantId;
        }
        $filter = $tenantId === null ? '' : ' AND r.id=$2';
        $tenants = $this->rows($this->registry, 'SELECT DISTINCT r.id,r.regnskab AS name,r.db,u.rettigheder,u.status
            FROM regnskab r JOIN refresh_sessions s ON s.tenant_id=r.id
            JOIN brugere u ON s.subject=$1 || u.id::text
            JOIN auth_versions v ON v.subject=s.subject AND v.tenant_id=0 AND v.auth_version=s.auth_version
            WHERE ' . self::openTenantSql() . " AND s.principal='registry'
            AND s.revoked_at IS NULL AND s.expires_at>now() $filter ORDER BY r.id", $params);
        $results = [];
        $seen = [];
        foreach ($tenants as $tenant) {
            $rights = Rights::registry((string)$tenant['rettigheder']);
            if (isset($seen[$tenant['id']]) || ($tenant['status'] !== null && !MobileAuth::enabled($tenant['status']))
                || (!$rights['admin'] && !in_array((int)$tenant['id'], $rights['tenant_ids'], true))) {
                continue;
            }
            $seen[$tenant['id']] = true;
            $count = 0;
            $this->workerMigrations = true;
            try {
                $item = $this->cachedTenant($tenant, ($this->clock)() + self::BUDGET_SECONDS, $count);
            } finally {
                $this->workerMigrations = false;
            }
            $results[] = ['tenant_id' => (int)$tenant['id'], 'state' => $item['state'], 'stale' => $item['stale']];
        }
        return $results;
    }
}
