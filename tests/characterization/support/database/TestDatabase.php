<?php
// 20261111 CDX/NTR Own UUID-named test databases, fresh installer fixtures and optional retention.
// 20261111 CDX/NTR Attach class-owned database identities to individual test output for IDE runners.
// 20261111 CDX/NTR Persist test ownership and retention markers for verified bulk cleanup.
// 20261111 CDX/NTR Associate each SQL fixture consumer with its captured database identity.
// 20261111 CDX/NTR Put wrapped helper-call arguments on separate lines.

require_once __DIR__ . '/RetainedTestDatabases.php';

/**
 * Owns one test group's database. Names are generated, never supplied by callers.
 * Create a separate instance per mutating group; explicitly share an instance only
 * when the participating groups cannot interfere. The last owner releases it.
 */
final class TestDatabase
{
    private static ?self $sqlFixture = null;
    /** @var array<string, self> Databases still owned by their named test groups. */
    private static array $groups = [];
    private string $group;
    private string $uuid;
    private string $name;
    private bool $created = false;
    private bool $released = false;
    private array $registryIds = [];

    private function __construct(string $group)
    {
        $this->group = $group;
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
        $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        $hex = bin2hex($bytes);
        $this->uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
        // regnskab.db is varchar(25). Keep 96 identifier bits within that limit.
        $this->name = 't' . substr($hex, 0, 24);
        self::$groups[$group] = $this;
        echo "\n" . $this->description();
    }

    /** Create an empty database, or install a clean tenant with the production installer. */
    public static function create(string $group, bool $install = true): self
    {
        $database = new self($group);
        register_shutdown_function(static function () use ($database): void {
            try {
                $database->release();
            } catch (Throwable $error) {
                fwrite(STDERR, '[test database] cleanup failed for ' . $database->name() . ': ' . $error->getMessage() . "\n");
            }
        });
        $admin = self::connect(self::masterDb());
        try {
            self::query($admin, 'CREATE DATABASE ' . pg_escape_identifier($admin, $database->name) . " TEMPLATE template0 ENCODING 'UTF8'");
            $database->created = true;
        } finally {
            pg_close($admin);
        }
        try {
            RetainedTestDatabases::markCreated($database, $group);
            if ($install) {
                $database->install();
                $database->registerAccount('test_' . $database->name);
                $database->seedSession();
            }
            return $database;
        } catch (Throwable $error) {
            $database->release();
            throw $error;
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function sessionId(): string
    {
        return str_replace('-', '', $this->uuid);
    }

    /** Repeat the class's identity inside PHPUnit's per-test output capture. */
    public static function reportGroup(string $group): void
    {
        if (isset(self::$groups[$group])) {
            echo self::$groups[$group]->description();
        }
    }

    /** Non-secret identity shared by the early creation message and IDE results. */
    private function description(): string
    {
        return "[test database] {$this->group} UUID={$this->uuid} database={$this->name} keep="
            . (self::keep() ? 'yes' : 'no') . "\n";
    }

    /** Remove this database's aliases without discarding newer owners of reused labels. */
    private function forgetGroup(): void
    {
        foreach (self::$groups as $group => $database) {
            if ($database === $this) {
                unset(self::$groups[$group]);
            }
        }
    }

    public static function masterDb(): string
    {
        return getenv('SALDI_CHAR_MASTER_DB') ?: 'saldi';
    }

    /** Explicitly shared only by SQL fixtures using temporary tables or their own schemas. */
    public static function sqlFixture(string $group): self
    {
        if (self::$sqlFixture === null) {
            self::$sqlFixture = self::create('SQL fixtures (private schemas and temporary tables)', false);
        }
        self::$groups[$group] = self::$sqlFixture;
        return self::$sqlFixture;
    }

    public function pdo(): PDO
    {
        $host = getenv('SALDI_CHAR_PGHOST') ?: 'postgres';
        return new PDO(
            'pgsql:host=' . $host . ';dbname=' . $this->name,
            getenv('SALDI_CHAR_PGUSER') ?: 'user',
            getenv('SALDI_CHAR_PGPASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    /** Keep every group, including its registry/session rows, for manual inspection. */
    public static function keep(): bool
    {
        foreach (['SALDI_TEST_KEEP_DATABASES', 'SALDI_REST_KEEP_TENANT'] as $key) {
            if (in_array(strtolower((string)getenv($key)), ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
        }
        return false;
    }

    /** No tenant/template is required; only the installed application's master and CREATEDB access. */
    public static function unavailableReason(): ?string
    {
        if (!extension_loaded('pgsql')) {
            return 'pgsql extension not loaded';
        }
        if (!getenv('SALDI_CHAR_PGPASS')) {
            return 'SALDI_CHAR_PGPASS is required for PostgreSQL tests';
        }
        // A failed connection must not print a credential-bearing connection string.
        set_error_handler(static function (): never {
            throw new RuntimeException('Configured test PostgreSQL server is unavailable');
        });
        try {
            $admin = self::connect(self::masterDb());
        } catch (RuntimeException $error) {
            return 'Configured test PostgreSQL server is unavailable';
        } finally {
            restore_error_handler();
        }
        pg_close($admin);
        return null;
    }

    public static function connect(string $database): PgSql\Connection
    {
        $values = [
            'host' => getenv('SALDI_CHAR_PGHOST') ?: 'postgres',
            'user' => getenv('SALDI_CHAR_PGUSER') ?: 'user',
            'password' => getenv('SALDI_CHAR_PGPASS') ?: '',
            'dbname' => $database,
            'connect_timeout' => '3',
        ];
        $parts = [];
        foreach ($values as $key => $value) {
            $parts[] = $key . "='" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        }
        $connection = pg_connect(implode(' ', $parts), PGSQL_CONNECT_FORCE_NEW);
        if ($connection === false) {
            throw new RuntimeException('Could not connect to test database');
        }
        return $connection;
    }

    public static function query(PgSql\Connection $connection, string $sql, array $params = []): PgSql\Result
    {
        $result = $params === [] ? pg_query($connection, $sql) : pg_query_params($connection, $sql, $params);
        if ($result === false) {
            throw new RuntimeException('Test database query failed: ' . pg_last_error($connection));
        }
        return $result;
    }

    /** Register a generated tenant for HTTP routing; remember the exact row for cleanup. */
    public function registerAccount(string $account, bool $closed = false): int
    {
        if (!$this->created || $this->released) {
            throw new RuntimeException('Cannot register a released test database');
        }
        $master = self::connect(self::masterDb());
        try {
            $version = '';
            require __DIR__ . '/../../../../includes/version.php';
            $result = self::query(
                $master,
                "INSERT INTO regnskab
                (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
                VALUES ($1,$2,$3,$4,$5,$6,100,1000000,$7,'') RETURNING id",
                [
                    $account, getenv('SALDI_CHAR_PGHOST') ?: 'postgres', getenv('SALDI_CHAR_PGUSER') ?: 'user',
                    $this->name, $version, date('Y-m-d'), $closed ? 'on' : '',
                ]
            );
            $id = (int)pg_fetch_result($result, 0, 0);
            $this->registryIds[] = $id;
            RetainedTestDatabases::recordAccount($this, $id);
            return $id;
        } finally {
            pg_close($master);
        }
    }

    /** Idempotent; only a successful CREATE by this instance grants deletion authority. */
    public function release(): void
    {
        if (!$this->created || $this->released) {
            $this->forgetGroup();
            return;
        }
        if (self::keep()) {
            RetainedTestDatabases::markRetained($this);
            $this->released = true;
            $this->forgetGroup();
            return;
        }
        $master = self::connect(self::masterDb());
        try {
            self::query($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()', [$this->name]);
            self::query($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $this->name));
            self::query($master, 'DELETE FROM online WHERE db=$1 AND session_id=$2', [$this->name, $this->sessionId()]);
            foreach ($this->registryIds as $id) {
                self::query($master, 'DELETE FROM regnskab WHERE id=$1 AND db=$2', [$id, $this->name]);
            }
            $this->released = true;
            $this->forgetGroup();
        } finally {
            pg_close($master);
        }
    }

    private function seedSession(): void
    {
        $master = self::connect(self::masterDb());
        try {
            $width = pg_fetch_result(
                self::query(
                    $master,
                    "SELECT character_maximum_length FROM information_schema.columns
                WHERE table_schema=current_schema() AND table_name='online' AND column_name='rettigheder'"
                ),
                0,
                0
            );
            self::query(
                $master,
                'INSERT INTO online (session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
                VALUES ($1,$2,$3,$4,$5,1,$6,false,0)',
                [
                    $this->sessionId(), 'chartest', $this->name, getenv('SALDI_CHAR_PGUSER') ?: 'user',
                    str_repeat('9', max(1, (int)($width ?: 30))), (string)time(),
                ]
            );
        } finally {
            pg_close($master);
        }
    }

    private function install(): void
    {
        $output = tmpfile();
        $errors = tmpfile();
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/installTenant.php', $this->name],
            [0 => ['file', '/dev/null', 'r'], 1 => $output, 2 => $errors],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the test tenant installer');
        }
        $exit = proc_close($process);
        rewind($errors);
        $diagnostics = stream_get_contents($errors);
        fclose($errors);
        fclose($output);
        if ($diagnostics !== '') {
            fwrite(STDERR, $diagnostics);
        }
        if ($exit !== 0) {
            throw new RuntimeException("Test tenant installer failed (exit $exit), database={$this->name}");
        }
    }
}
