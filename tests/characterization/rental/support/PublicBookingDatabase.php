<?php
// tests/characterization/rental/support/PublicBookingDatabase.php
//
// A regnskab the login-free booking endpoints accept, for the rental suites.
//
// remoteBooking/ and mysale/ only take account database names that end in
// _<number> (public_tenant_db(), includes/publicTenantDb.php), as the
// application names them (<master>_<id>). The class's own database from
// TestDatabase is named t<24 hex digits>, which they refuse. So a suite
// installs a fresh regnskab with TestDatabase, copies it under such a name,
// and releases the installation. The copy is not a TestDatabase: the suite
// drops it in tearDownAfterClass(), and a shutdown function drops what an
// interrupted run left.
//
// History:
// 20261115 CL/ASR Integration: Created. Master's test environment (2bd490c6) has no template tenant to clone, so the copies come from a fresh installation.

require_once __DIR__ . '/../../support/CharacterizationEnv.php';

final class PublicBookingDatabase
{
    /** The master's mysale.db is varchar(20), the narrowest column such a name goes in (regnskab.db 25, online.db 30). */
    private const MAX_LENGTH = 20;

    /** @var array<string, true> the copies made and not dropped yet */
    private static array $live = [];

    private static bool $shutdown = false;

    /**
     * A name the public endpoints accept for a copy: the start of the class's database name, then $suffix.
     *
     * @param string $suffix '_<letters>_<number>', e.g. '_cd_1'
     * @return string a name no other class uses, within the master's columns
     */
    public static function name(string $suffix): string
    {
        $name = substr(CharacterizationEnv::testDb(), 0, self::MAX_LENGTH - strlen($suffix)) . $suffix;
        if (strlen($name) > self::MAX_LENGTH || !preg_match('/^[a-z][a-z0-9_]*_[0-9]+$/', $name)
            || in_array($name, [CharacterizationEnv::testDb(), CharacterizationEnv::masterDb()], true)) {
            throw new RuntimeException('unsafe database name ' . $name);
        }
        return $name;
    }

    /**
     * Installs a fresh regnskab and copies it under each name; the installation itself is released.
     * On a failure the copies made so far are dropped again.
     *
     * @param string $group the test class, which labels the installation in the output
     * @param string ...$names names from name(), not in use
     * @return void
     */
    public static function create(string $group, string ...$names): void
    {
        if (!self::$shutdown) {
            self::$shutdown = true;
            register_shutdown_function(static function (): void {
                foreach (array_keys(self::$live) as $name) {
                    try {
                        self::drop($name);
                    } catch (Throwable $error) {
                        fwrite(STDERR, '[test database] cleanup failed for ' . $name . ': ' . $error->getMessage() . "\n");
                    }
                }
            });
        }
        $source = TestDatabase::create($group . ' (copied for the public booking endpoints)');
        $made = [];
        try {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            try {
                foreach ($names as $name) {
                    CharacterizationEnv::rows($master, 'CREATE DATABASE ' . pg_escape_identifier($master, $name) . ' TEMPLATE ' . pg_escape_identifier($master, $source->name()));
                    $made[] = $name;
                    self::$live[$name] = true;
                }
            } finally {
                pg_close($master);
            }
            foreach ($names as $name) {
                // The installation's ownership record (support/database/RetainedTestDatabases.php) is not the copy's.
                $copy = CharacterizationEnv::connect($name);
                try {
                    CharacterizationEnv::rows($copy, 'DROP TABLE IF EXISTS public.saldi_phpunit_identity');
                } finally {
                    pg_close($copy);
                }
            }
        } catch (Throwable $error) {
            foreach ($made as $name) {
                self::drop($name);
            }
            throw $error;
        } finally {
            $source->release();
        }
    }

    /**
     * Drops a copy with the routing rows that name it: a regnskab row a suite registered, a session it moved there.
     *
     * @param string $name a name create() made
     * @return void
     */
    public static function drop(string $name): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()', [$name]);
            CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $name));
            CharacterizationEnv::rows($master, 'DELETE FROM regnskab WHERE db = $1', [$name]);
            CharacterizationEnv::rows($master, 'DELETE FROM online WHERE db = $1', [$name]);
            unset(self::$live[$name]);
        } finally {
            pg_close($master);
        }
    }
}
