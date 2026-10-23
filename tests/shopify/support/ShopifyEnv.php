<?php
// tests/shopify/support/ShopifyEnv.php
//
// Environment for the Shopify integration suite (SD-733, suite `shopify`).
//
// DB-backed tests run against a throwaway PostgreSQL tenant cloned from a template
// tenant. The test tenant must be named saldi_shopify_test_<something>; pos_50,
// test_12 and any other name are refused outright. Credentials come from the
// environment only, with no fallback: when SALDI_SHOPIFY_TEST_DB is unset the
// suite skips, when it is set and user, password or template are missing the
// suite fails.
//
// Env: SALDI_SHOPIFY_PGHOST (default postgres), SALDI_SHOPIFY_PGUSER,
// SALDI_SHOPIFY_PGPASS, SALDI_SHOPIFY_MASTER_DB (default saldi),
// SALDI_SHOPIFY_TEMPLATE_DB, SALDI_SHOPIFY_TEST_DB (keep it short: tenants registered in
// master regnskab are limited to 25 characters including the per-test suffix).
//
// History:
// 20261021 Sawaneh SD-733: created.
// 20261022 Sawaneh SD-734: generic child runner, tenant registration in master regnskab.

final class ShopifyEnv
{
    public const TEST_DB_PATTERN = '/^saldi_shopify_test_[a-z0-9_]+$/';

    private const FORBIDDEN = ['pos_50', 'test_12'];

    /** Master `regnskab.db` is varchar(25); a tenant the bootstrap must find cannot be longer. */
    public const MAX_REGISTERED_DB_LENGTH = 25;

    /** @var array<string, bool> Databases this process created, keyed by name. */
    private static array $owned = [];

    public static function pgHost(): string
    {
        return getenv('SALDI_SHOPIFY_PGHOST') ?: 'postgres';
    }

    public static function pgUser(): string
    {
        return self::required('SALDI_SHOPIFY_PGUSER');
    }

    public static function pgPass(): string
    {
        return self::required('SALDI_SHOPIFY_PGPASS');
    }

    public static function masterDb(): string
    {
        return getenv('SALDI_SHOPIFY_MASTER_DB') ?: 'saldi';
    }

    public static function templateDb(): string
    {
        return self::assertSafeTemplate(self::required('SALDI_SHOPIFY_TEMPLATE_DB'));
    }

    public static function testDb(string $suffix = ''): string
    {
        return self::assertSafeTestDb(self::required('SALDI_SHOPIFY_TEST_DB') . $suffix);
    }

    public static function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /** Null when the environment is usable, otherwise the reason to skip. Misconfiguration throws. */
    public static function unavailableReason(): ?string
    {
        if (!extension_loaded('pgsql')) {
            return 'pgsql extension not loaded';
        }
        if ((string)getenv('SALDI_SHOPIFY_TEST_DB') === '') {
            return 'SALDI_SHOPIFY_TEST_DB is not set (see tests/shopify/support/ShopifyEnv.php)';
        }
        self::testDb();
        self::templateDb();
        self::pgUser();
        self::pgPass();
        $conn = @pg_connect(self::connString(self::masterDb()), PGSQL_CONNECT_FORCE_NEW);
        if ($conn === false) {
            return 'postgres not reachable at host "' . self::pgHost() . '"';
        }
        $r = pg_query_params($conn, 'SELECT 1 FROM pg_database WHERE datname = $1', [self::templateDb()]);
        $exists = $r !== false && pg_num_rows($r) === 1;
        pg_close($conn);
        if (!$exists) {
            return 'template tenant "' . self::templateDb() . '" does not exist';
        }
        return null;
    }

    /** A test tenant name must match TEST_DB_PATTERN and is never a live or shared tenant. */
    public static function assertSafeTestDb(string $name): string
    {
        self::assertNotForbidden($name);
        if (!preg_match(self::TEST_DB_PATTERN, $name)) {
            throw new RuntimeException("refusing test tenant \"$name\": the name must match saldi_shopify_test_*");
        }
        return $name;
    }

    public static function assertSafeTemplate(string $name): string
    {
        self::assertNotForbidden($name);
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new RuntimeException("unsafe template database name \"$name\"");
        }
        return $name;
    }

    private static function assertNotForbidden(string $name): void
    {
        if (in_array($name, self::FORBIDDEN, true)) {
            throw new RuntimeException("refusing database \"$name\": live tenants are off limits for the shopify suite");
        }
    }

    private static function required(string $var): string
    {
        $value = getenv($var);
        if (!is_string($value) || $value === '') {
            throw new RuntimeException("$var must be set; the shopify suite has no credential fallback");
        }
        return $value;
    }

    private static function quoteConnectionValue(string $value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    }

    private static function connString(string $db): string
    {
        return sprintf(
            'host=%s dbname=%s user=%s password=%s connect_timeout=3',
            self::quoteConnectionValue(self::pgHost()),
            self::quoteConnectionValue($db),
            self::quoteConnectionValue(self::pgUser()),
            self::quoteConnectionValue(self::pgPass())
        );
    }

    /** @return resource|\PgSql\Connection */
    public static function connect(string $db)
    {
        $conn = pg_connect(self::connString($db), PGSQL_CONNECT_FORCE_NEW);
        if ($conn === false) {
            throw new RuntimeException("could not connect to $db");
        }
        return $conn;
    }

    /** @return array<int, array<string, mixed>> */
    public static function rows($conn, string $sql, array $params = []): array
    {
        $r = $params === [] ? pg_query($conn, $sql) : pg_query_params($conn, $sql, $params);
        if ($r === false) {
            throw new RuntimeException('query failed: ' . pg_last_error($conn) . ' -- ' . $sql);
        }
        $out = [];
        while ($row = pg_fetch_assoc($r)) {
            $out[] = $row;
        }
        return $out;
    }

    public static function one($conn, string $sql, array $params = []): ?array
    {
        return self::rows($conn, $sql, $params)[0] ?? null;
    }

    /** Drops and re-creates $db as a clone of the template tenant. */
    public static function cloneTenant(string $db): void
    {
        self::assertSafeTestDb($db);
        $template = self::templateDb();
        $master = self::connect(self::masterDb());
        try {
            self::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()', [$db]);
            self::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $db));
            self::rows($master, 'CREATE DATABASE ' . pg_escape_identifier($master, $db) . ' TEMPLATE ' . pg_escape_identifier($master, $template));
            self::$owned[$db] = true;
        } finally {
            pg_close($master);
        }
    }

    /** @var array<string, int> Master regnskab ids this process inserted, keyed by tenant db. */
    private static array $registered = [];

    /** Registers a throwaway tenant in master regnskab, the way online.php and the bootstrap look it up. */
    public static function registerTenant(string $db, string $lukket = ''): int
    {
        self::assertSafeTestDb($db);
        if (strlen($db) > self::MAX_REGISTERED_DB_LENGTH) {
            throw new RuntimeException("tenant \"$db\" is longer than " . self::MAX_REGISTERED_DB_LENGTH . ' characters; master regnskab.db is varchar(25), so SALDI_SHOPIFY_TEST_DB must leave room for the suffix');
        }
        $master = self::connect(self::masterDb());
        try {
            self::rows($master, 'DELETE FROM regnskab WHERE db = $1', [$db]);
            $row = self::one($master, "INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
                VALUES ($1, $2, $3, $1, '5.0.0', '', 5, 1000000, $4, '') RETURNING id", [$db, self::pgHost(), self::pgUser(), $lukket]);
            self::$registered[$db] = (int)$row['id'];
            return (int)$row['id'];
        } finally {
            pg_close($master);
        }
    }

    public static function unregisterTenants(): void
    {
        if (self::$registered === []) {
            return;
        }
        $master = self::connect(self::masterDb());
        try {
            foreach (self::$registered as $db => $id) {
                self::rows($master, 'DELETE FROM regnskab WHERE id = $1 AND db = $2', [$id, $db]);
                unset(self::$registered[$db]);
            }
        } finally {
            pg_close($master);
        }
    }

    public static function dropOwnedTenants(): void
    {
        if (self::$owned === [] || getenv('SALDI_SHOPIFY_KEEP_TENANT')) {
            return;
        }
        $master = self::connect(self::masterDb());
        try {
            foreach (array_keys(self::$owned) as $db) {
                self::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()', [$db]);
                self::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $db));
                self::removeTempDir($db);
                unset(self::$owned[$db]);
            }
        } finally {
            pg_close($master);
        }
    }

    /** Removes temp/<db>, where the legacy db functions log for the throwaway tenant. */
    public static function removeTempDir(string $db): void
    {
        $dir = self::repoRoot() . '/temp/' . self::assertSafeTestDb($db);
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry !== '.' && $entry !== '..' && is_file("$dir/$entry")) {
                unlink("$dir/$entry");
            }
        }
        rmdir($dir);
    }

    /**
     * Starts a support script under tests/shopify/support/ with the connection settings in its
     * environment, without waiting for it, so several runs can overlap. Finish with waitChild().
     *
     * @param list<string> $args
     * @param array<string, string> $extraEnv
     * @return array{proc: resource, pipes: array<int, resource>}
     */
    public static function startChild(string $script, array $args, array $extraEnv = [])
    {
        $cmd = array_merge([PHP_BINARY, __DIR__ . '/' . $script], array_map('strval', $args));
        $env = array_merge($_ENV, [
            'SALDI_SHOPIFY_PGHOST' => self::pgHost(),
            'SALDI_SHOPIFY_PGUSER' => self::pgUser(),
            'SALDI_SHOPIFY_PGPASS' => self::pgPass(),
            'SALDI_SHOPIFY_MASTER_DB' => self::masterDb(),
            'PATH' => (string)getenv('PATH'),
        ], $extraEnv);
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::repoRoot(), $env);
        if (!is_resource($proc)) {
            throw new RuntimeException('could not start child php');
        }
        fclose($pipes[0]);
        return ['proc' => $proc, 'pipes' => $pipes];
    }

    /** Starts tests/shopify/support/run_schema.php against $db. */
    public static function startSchemaRun(string $db, string $mode)
    {
        return self::startChild('run_schema.php', [$mode, self::assertSafeTestDb($db)]);
    }

    /** Runs tests/shopify/support/run_bootstrap.php to completion. */
    public static function runBootstrap(string $mode, string $db, array $extraEnv = []): array
    {
        return self::waitChild(self::startChild('run_bootstrap.php', [$mode, self::assertSafeTestDb($db)], $extraEnv));
    }

    /** @return array{exit: int, stdout: string, stderr: string, result: ?array} */
    public static function waitChild(array $child): array
    {
        $stdout = stream_get_contents($child['pipes'][1]);
        $stderr = stream_get_contents($child['pipes'][2]);
        fclose($child['pipes'][1]);
        fclose($child['pipes'][2]);
        $exit = proc_close($child['proc']);
        $result = json_decode((string)$stdout, true);
        return ['exit' => $exit, 'stdout' => (string)$stdout, 'stderr' => (string)$stderr, 'result' => is_array($result) ? $result : null];
    }

    /** Runs the schema runner to completion. */
    public static function runSchema(string $db, string $mode): array
    {
        return self::waitChild(self::startSchemaRun($db, $mode));
    }

    /**
     * Columns, constraints and indexes of every manifest table, in a shape that two
     * databases can be compared on.
     *
     * @return array{columns: array<int, array<string, mixed>>, constraints: array<int, array<string, mixed>>, indexes: array<int, array<string, mixed>>}
     */
    public static function catalog($conn): array
    {
        $tables = "(table_name LIKE 'shopify\\_%' OR table_name IN ('dokument_links', 'pos_leveringsvalg'))";
        $legacy = "(table_name = 'varer' AND column_name = 'oversalg')"
            . " OR (table_name = 'ordrer' AND column_name IN ('leveringsvalg_id', 'leveringsmaade', 'oensket_levdato'))"
            . " OR (table_name = 'ordrelinjer' AND column_name IN ('retur_lager', 'kredit_art'))";
        $columns = self::rows($conn, "SELECT table_name, column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, is_nullable, column_default"
            . " FROM information_schema.columns WHERE table_schema = current_schema() AND ($tables OR $legacy) ORDER BY table_name, column_name");
        $constraints = self::rows($conn, "SELECT conrelid::regclass::text AS table_name, conname, contype, pg_get_constraintdef(oid) AS definition"
            . " FROM pg_constraint WHERE connamespace = current_schema()::regnamespace AND (conrelid::regclass::text LIKE 'shopify\\_%' OR conrelid::regclass::text IN ('dokument_links', 'pos_leveringsvalg')) ORDER BY 1, 2");
        $indexes = self::rows($conn, "SELECT tablename AS table_name, indexname, indexdef FROM pg_indexes WHERE schemaname = current_schema()"
            . " AND (tablename LIKE 'shopify\\_%' OR tablename IN ('dokument_links', 'pos_leveringsvalg')) ORDER BY 1, 2");
        return ['columns' => $columns, 'constraints' => $constraints, 'indexes' => $indexes];
    }
}
