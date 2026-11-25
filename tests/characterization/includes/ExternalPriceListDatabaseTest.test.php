<?php
// 20261125 CDX/NTR Prove concurrent external item creation reuses one PostgreSQL item and preserves exact prices.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/database/TestDatabase.php';

final class ExternalPriceListDatabaseTest extends TestCase
{
    public function testConcurrentSelectionsCreateOneItem(): void
    {
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $database = TestDatabase::sqlFixture(static::class);
        $connection = TestDatabase::connect($database->name());
        $schema = 'price_list_' . bin2hex(random_bytes(8));
        $stage = tempnam(sys_get_temp_dir(), 'price-list-race-');
        $workers = [];
        try {
            pg_query($connection, "CREATE SCHEMA $schema");
            pg_query($connection, "SET search_path TO $schema");
            pg_query($connection, "CREATE TABLE varer (id serial PRIMARY KEY, varenr text, stregkode text, trademark text, beskrivelse text, kostpris numeric(15,3), salgspris numeric(15,3), enhed text, gruppe integer, lukket text)");
            pg_query($connection, "CREATE TABLE grupper (art text, fiscal_year integer, kodenr text); INSERT INTO grupper VALUES ('VG', 2026, '10')");
            for ($i = 0; $i < 2; $i++) {
                $out = tmpfile();
                $err = tmpfile();
                $process = proc_open([PHP_BINARY, __DIR__ . '/support/external_price_list_worker.php', $database->name(), $schema, $stage], [0 => ['file', '/dev/null', 'r'], 1 => $out, 2 => $err], $pipes);
                self::assertIsResource($process);
                $workers[] = [$process, $out, $err];
            }
            touch($stage . '.go');
            $ids = [];
            foreach ($workers as [$process, $out, $err]) {
                self::assertSame(0, proc_close($process));
                rewind($out);
                rewind($err);
                self::assertSame('', stream_get_contents($err));
                $ids[] = (int) stream_get_contents($out);
            }
            self::assertGreaterThan(0, $ids[0]);
            self::assertSame($ids[0], $ids[1]);
            $rows = pg_fetch_all(pg_query($connection, 'SELECT varenr, kostpris, salgspris FROM varer'));
            self::assertSame([['varenr' => 'A100', 'kostpris' => '1250.500', 'salgspris' => '0.005']], $rows);
        } finally {
            foreach ($workers as [$process, $out, $err]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
                fclose($out);
                fclose($err);
            }
            foreach (glob($stage . '.*') as $path) {
                unlink($path);
            }
            unlink($stage);
            pg_query($connection, "DROP SCHEMA IF EXISTS $schema CASCADE");
            pg_close($connection);
        }
    }
}
