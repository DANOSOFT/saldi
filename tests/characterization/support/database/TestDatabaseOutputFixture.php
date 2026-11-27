<?php
// 20261120 CDX/NTR Exercise installed fixture identity output without loading an application page.
// 20261123 CDX/NTR Port the reviewed test fixture repairs to hml/shopify.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestDatabase.php';

/** Child-process fixture, explicitly selected by TestDatabaseLifecycleTest. */
final class TestDatabaseOutputFixture extends TestCase
{
    private static TestDatabase $database;

    public static function setUpBeforeClass(): void
    {
        self::$database = TestDatabase::create(static::class);
    }

    public static function tearDownAfterClass(): void
    {
        self::$database->release();
    }

    public function testConnectionUsesOwnedDatabase(): void
    {
        self::assertSame(self::$database->name(), self::$database->pdo()->query('SELECT current_database()')->fetchColumn());
    }
}
