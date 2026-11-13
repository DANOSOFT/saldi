<?php
// 20260815 CL/SZ SD-646: created.
// 20261111 CDX/NTR Use the common UUID-owned fresh tenant lifecycle.

require_once __DIR__ . '/../../support/database/TestDatabase.php';

final class MomsPeriodeLukEnv
{
    private static ?TestDatabase $database = null;

    public static function unavailableReason(): ?string
    {
        return TestDatabase::unavailableReason();
    }

    public static function bootstrapTenant(): void
    {
        self::$database = TestDatabase::create('MomsPeriodeLukMigrationHealthCheckTest');
    }

    public static function testDb(): string
    {
        return self::$database->name();
    }

    public static function sessionId(): string
    {
        return self::$database->sessionId();
    }

    public static function connect(string $db)
    {
        return TestDatabase::connect($db);
    }

    public static function rows($connection, string $sql): array
    {
        return pg_fetch_all(TestDatabase::query($connection, $sql));
    }

    public static function teardownTenant(): void
    {
        if (self::$database !== null) {
            self::$database->release();
            self::$database = null;
        }
    }
}
