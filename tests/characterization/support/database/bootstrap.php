<?php
// 20261111 CDX/NTR Include class-created database UUIDs in captured per-test IDE/JUnit output.

require_once __DIR__ . '/TestDatabase.php';

final class TestDatabaseOutput implements PHPUnit\Event\Test\PreparedSubscriber
{
    /** Runs while the test output buffer is active, after its class setup. */
    public function notify(PHPUnit\Event\Test\Prepared $event): void
    {
        $test = $event->test();
        if ($test->isTestMethod()) {
            TestDatabase::reportGroup($test->className());
        }
    }
}

PHPUnit\Event\Facade::instance()->registerSubscriber(new TestDatabaseOutput());
