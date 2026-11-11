<?php
// 20261111 CDX/NTR Verify persistent test identities before listing or deleting retained databases.
// 20261111 CDX/NTR Put wrapped helper-call arguments on separate lines.

/** Persistent ownership proof survives the PHPUnit process that created a database. */
final class RetainedTestDatabases
{
    private const COMMENT_PREFIX = 'saldi-phpunit:v1:';

    public static function markCreated(TestDatabase $database, string $group): void
    {
        $tenant = TestDatabase::connect($database->name());
        try {
            TestDatabase::query(
                $tenant,
                'CREATE TABLE public.saldi_phpunit_identity
                (uuid text PRIMARY KEY, test_group text NOT NULL, master_db text NOT NULL, state text NOT NULL,
                 registry_ids integer[] NOT NULL DEFAULT ARRAY[]::integer[], created_at timestamptz NOT NULL DEFAULT now())'
            );
            TestDatabase::query(
                $tenant,
                "INSERT INTO public.saldi_phpunit_identity (uuid,test_group,master_db,state) VALUES ($1,$2,$3,'active')",
                [$database->uuid(), $group, TestDatabase::masterDb()]
            );
        } finally {
            pg_close($tenant);
        }
        $master = TestDatabase::connect(TestDatabase::masterDb());
        try {
            TestDatabase::query(
                $master,
                'COMMENT ON DATABASE ' . pg_escape_identifier($master, $database->name())
                . ' IS ' . pg_escape_literal($master, self::COMMENT_PREFIX . $database->uuid())
            );
        } finally {
            pg_close($master);
        }
    }

    /** Remember exact application registry IDs, rather than deleting rows by an account-name prefix. */
    public static function recordAccount(TestDatabase $database, int $id): void
    {
        $tenant = TestDatabase::connect($database->name());
        try {
            TestDatabase::query(
                $tenant,
                'UPDATE public.saldi_phpunit_identity SET registry_ids=array_append(registry_ids,$1) WHERE uuid=$2',
                [$id, $database->uuid()]
            );
        } finally {
            pg_close($tenant);
        }
    }

    /** Active databases are never eligible for the bulk cleanup command. */
    public static function markRetained(TestDatabase $database): void
    {
        $tenant = TestDatabase::connect($database->name());
        try {
            $result = TestDatabase::query($tenant, "UPDATE public.saldi_phpunit_identity SET state='retained' WHERE uuid=$1", [$database->uuid()]);
            if (pg_affected_rows($result) !== 1) {
                throw new RuntimeException('Cannot retain a database without its original test identity');
            }
        } finally {
            pg_close($tenant);
        }
    }

    /** @return list<array{database:string,uuid:string,test_group:string,registry_ids:string,created_at:string}> */
    public static function listing(?string $onlyDatabase = null): array
    {
        $master = TestDatabase::connect(TestDatabase::masterDb());
        try {
            $result = TestDatabase::query(
                $master,
                "SELECT datname FROM pg_database
                WHERE pg_get_userbyid(datdba)=current_user AND shobj_description(oid,'pg_database') LIKE $1
                AND ($2::text IS NULL OR datname=$2) ORDER BY datname",
                [self::COMMENT_PREFIX . '%', $onlyDatabase]
            );
            $identities = [];
            while ($row = pg_fetch_assoc($result)) {
                $identity = self::verify($master, $row['datname']);
                if ($identity !== null) {
                    $identities[] = $identity;
                }
            }
            return $identities;
        } finally {
            pg_close($master);
        }
    }

    /** Verify again at deletion time; a prefix match or database name is never sufficient. */
    public static function delete(string $database): void
    {
        $master = TestDatabase::connect(TestDatabase::masterDb());
        try {
            $identity = self::verify($master, $database);
            if ($identity === null) {
                throw new RuntimeException('Refusing to delete a database without matching retained PHPUnit identity: ' . $database);
            }
            TestDatabase::query($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()', [$database]);
            TestDatabase::query($master, 'DROP DATABASE ' . pg_escape_identifier($master, $database));
            TestDatabase::query($master, 'DELETE FROM online WHERE db=$1 AND session_id=$2', [$database, str_replace('-', '', $identity['uuid'])]);
            TestDatabase::query($master, 'DELETE FROM regnskab WHERE db=$1 AND id=ANY($2::integer[])', [$database, $identity['registry_ids']]);
        } finally {
            pg_close($master);
        }
    }

    /** @return array{database:string,uuid:string,test_group:string,registry_ids:string,created_at:string}|null */
    private static function verify(PgSql\Connection $master, string $database): ?array
    {
        if (!preg_match('/^t[a-f0-9]{24}$/', $database) || $database === TestDatabase::masterDb()) {
            return null;
        }
        $result = TestDatabase::query(
            $master,
            "SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database
            WHERE datname=$1 AND pg_get_userbyid(datdba)=current_user",
            [$database]
        );
        $row = pg_fetch_assoc($result);
        $marker = $row['marker'] ?? '';
        $prefix = preg_quote(self::COMMENT_PREFIX, '/');
        if (!preg_match('/^' . $prefix . '([a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12})$/', $marker, $match)) {
            return null;
        }
        $uuid = $match[1];
        if ($database !== 't' . substr(str_replace('-', '', $uuid), 0, 24)) {
            return null;
        }
        $tenant = TestDatabase::connect($database);
        try {
            if (pg_fetch_result(TestDatabase::query($tenant, "SELECT to_regclass('public.saldi_phpunit_identity')"), 0, 0) === null) {
                return null;
            }
            $rows = pg_fetch_all(TestDatabase::query($tenant, 'SELECT uuid,test_group,master_db,state,registry_ids::text,created_at::text FROM public.saldi_phpunit_identity'));
            if (count($rows) !== 1 || $rows[0]['uuid'] !== $uuid || $rows[0]['state'] !== 'retained'
                || $rows[0]['master_db'] !== TestDatabase::masterDb()) {
                return null;
            }
            return [
                'database' => $database, 'uuid' => $uuid, 'test_group' => $rows[0]['test_group'],
                'registry_ids' => $rows[0]['registry_ids'], 'created_at' => $rows[0]['created_at'],
            ];
        } finally {
            pg_close($tenant);
        }
    }
}
