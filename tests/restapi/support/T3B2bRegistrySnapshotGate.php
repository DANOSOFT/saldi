<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T3B2bRegistrySnapshotGate.php --- ver 5.0.0 --- 2026.10.05
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

/**
 * Optional local gate bootstrap for parallel suites sharing the master database.
 * Only the installation-wide admin-directory snapshot assertion needs a stable
 * regnskab set between its HTTP read and its independent SQL read. All tests and
 * assertions still execute; fixture writes in other processes wait briefly.
 *
 * Usage: php vendor/phpunit.phar --bootstrap
 * tests/restapi/support/T3B2bRegistrySnapshotGate.php --testsuite restapi
 */
require_once __DIR__ . '/RestApiEnv.php';

final class T3B2bRegistrySnapshotGate
{
    public const TEST_ID = 'MobileRegistryTest::test_registry_admin_sees_all_open_tenants_and_can_exchange_unlisted_id';
    private static ?PgSql\Connection $connection = null;

    public static function acquire(): void
    {
        self::$connection = RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows(self::$connection, 'BEGIN');
        RestApiEnv::rows(self::$connection, "SET LOCAL lock_timeout='10s'");
        RestApiEnv::rows(self::$connection, "SET LOCAL idle_in_transaction_session_timeout='60s'");
        RestApiEnv::rows(self::$connection, 'LOCK TABLE regnskab IN SHARE MODE');
    }

    public static function release(): void
    {
        if (self::$connection !== null) {
            RestApiEnv::rows(self::$connection, 'ROLLBACK');
            pg_close(self::$connection);
            self::$connection = null;
        }
    }
}

PHPUnit\Event\Facade::instance()->registerSubscriber(new class implements PHPUnit\Event\Test\PreparedSubscriber {
    public function notify(PHPUnit\Event\Test\Prepared $event): void
    {
        if ($event->test()->id() === T3B2bRegistrySnapshotGate::TEST_ID) {
            T3B2bRegistrySnapshotGate::acquire();
        }
    }
});
PHPUnit\Event\Facade::instance()->registerSubscriber(new class implements PHPUnit\Event\Test\FinishedSubscriber {
    public function notify(PHPUnit\Event\Test\Finished $event): void
    {
        if ($event->test()->id() === T3B2bRegistrySnapshotGate::TEST_ID) {
            T3B2bRegistrySnapshotGate::release();
        }
    }
});
register_shutdown_function([T3B2bRegistrySnapshotGate::class, 'release']);
