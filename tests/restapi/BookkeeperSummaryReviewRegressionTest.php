<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/BookkeeperSummaryReviewRegressionTest.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Cover mixed currencies and worker-deferred tenant migrations.

require_once __DIR__ . '/support/T3B6TestCase.php';

final class BookkeeperSummaryReviewRegressionTest extends T3B6TestCase
{
    private int $journal;

    protected function setUp(): void
    {
        parent::setUp();
        RestApiEnv::rows(self::$second, 'DELETE FROM bank_line_journal_links');
        RestApiEnv::rows(self::$second, 'DELETE FROM bank_lines');
        RestApiEnv::rows(self::$second, 'DELETE FROM bank_line_imports');
        RestApiEnv::rows(self::$second, 'DELETE FROM openpost');
        RestApiEnv::rows(self::$second, "DELETE FROM settings WHERE var_name='baseCurrency'");
        RestApiEnv::rows(self::$second, "INSERT INTO settings(var_name,var_grp,var_value,user_id)
            VALUES('baseCurrency','localization','DKK',0)");
        RestApiEnv::rows(self::$second, "DELETE FROM valuta WHERE gruppe IN (9911,9912)");
        RestApiEnv::rows(self::$second, "DELETE FROM grupper WHERE art='VK' AND (box1 IN ('DKK','EUR','USD') OR kodenr IN (9911,9912))");
        RestApiEnv::rows(self::$second, "INSERT INTO grupper(art,kodenr,box1) VALUES('VK',9911,'EUR'),('VK',9912,'USD')");
        $this->journal = (int)RestApiEnv::rows(self::$second, "INSERT INTO kladdeliste(id,bogfort)
            SELECT COALESCE(max(id),0)+1,'-' FROM kladdeliste RETURNING id")[0]['id'];
        RestApiEnv::rows(self::$registry, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2',
            [',,,' . self::$secondId, self::$user['id']]);
    }

    /** Seed one missing outgoing transaction on its own bank account and live journal row. */
    private function bank(string $amount, string $currency, string $date, int $account = 6471): void
    {
        $import = (int)RestApiEnv::rows(self::$second, "INSERT INTO bank_line_imports(source,account_kontonr,kladde_id,imported_by)
            VALUES('csv',$1,$2,$3) RETURNING id", [$account, $this->journal, self::$subject])[0]['id'];
        $bank = (int)RestApiEnv::rows(self::$second, "INSERT INTO bank_lines(account_kontonr,line_key,source,transdate,amount,currency,text,first_import_id)
            VALUES($1,$2,'csv',$3,$4,$5,'Currency regression',$6) RETURNING id",
            [$account, hash('sha256', random_bytes(16)), $date, $amount, $currency, $import])[0]['id'];
        $line = (int)RestApiEnv::rows(self::$second, 'INSERT INTO kassekladde(kladde_id,bilag) VALUES($1,1) RETURNING id',
            [$this->journal])[0]['id'];
        RestApiEnv::rows(self::$second, 'INSERT INTO bank_line_journal_links(bank_line_id,kassekladde_id,import_id) VALUES($1,$2,$3)',
            [$bank, $line, $import]);
    }

    public function test_both_endpoints_convert_mixed_currencies_at_each_transaction_date(): void
    {
        RestApiEnv::rows(self::$second, "INSERT INTO valuta(gruppe,valdate,kurs) VALUES
            (9911,'2026-01-01',745.125),(9911,'2026-02-01',746.25),(9911,'2026-03-01',900),
            (9912,'2026-01-01',666.665)");
        $this->bank('-100.00', 'DKK', '2026-01-15');
        $this->bank('-100.00', 'EUR', '2026-01-15', 6472);
        $this->bank('-100.00', 'EUR', '2026-02-01', 6472);
        $this->bank('-0.01', 'USD', '2026-02-15', 6473);
        $this->bank('-0.01', 'USD', '2026-02-15', 6473);
        // Independently calculated: 100 + 745.125 + 746.25 + 2*0.0666665 = 1591.508333.
        // Round the decimal sum once to cents: 1591.51 (rounding each USD line gives 1591.52).
        $summary = $this->service()->forTenant(self::$second);
        $this->assertSame(5, $summary['bank_lines_missing']);
        $this->assertSame('1591.51', $summary['bank_lines_missing_total']);
        $this->assertSame('DKK', $summary['overdue']['currency']);
        $token = $this->pair(self::$secondId)['access_token'];
        $tenant = RestApiEnv::http('GET', '/restapi/endpoints/v1/bookkeeper/summary.php', null,
            ['Authorization: Bearer ' . $token]);
        $this->assertSame(200, $tenant['status'], $tenant['body']);
        $this->assertSame('1591.51', $tenant['json']['data']['bank_lines_missing_total']);
        $registry = $this->registryRequest();
        $this->assertSame(200, $registry['status'], $registry['body']);
        $this->assertSame('1591.51', $registry['json']['data']['items'][0]['bank_lines_missing_total']);
        $this->assertFalse($registry['json']['data']['items'][0]['stale']);
    }

    public function test_missing_total_uses_configured_base_currency_even_without_overdue_permission(): void
    {
        RestApiEnv::rows(self::$second, "UPDATE settings SET var_value='EUR' WHERE var_name='baseCurrency'");
        RestApiEnv::rows(self::$second, "UPDATE grupper SET box1='DKK' WHERE art='VK' AND kodenr=9911");
        RestApiEnv::rows(self::$second, "INSERT INTO valuta(gruppe,valdate,kurs) VALUES(9911,'2026-01-01',13.4)");
        $this->bank('-100.00', 'DKK', '2026-01-15');
        $this->bank('-100.00', 'EUR', '2026-01-15', 6472);
        $summary = $this->service()->forTenant(self::$second, ['debitorrapport' => ['read' => false]]);
        $this->assertSame('113.40', $summary['bank_lines_missing_total']);
        $this->assertNull($summary['overdue']);
    }

    public function test_unavailable_exchange_rate_never_returns_a_partial_or_unconverted_total(): void
    {
        $this->bank('-100.00', 'DKK', '2026-01-15');
        $this->bank('-100.00', 'EUR', '2026-01-15', 6472);
        // A future rate cannot price this transaction; zero is also unusable.
        RestApiEnv::rows(self::$second, "INSERT INTO valuta(gruppe,valdate,kurs) VALUES(9911,'2026-02-01',745)");
        foreach ([null, '0'] as $rate) {
            if ($rate !== null) {
                RestApiEnv::rows(self::$second, "INSERT INTO valuta(gruppe,valdate,kurs) VALUES(9911,'2026-01-01',$1)", [$rate]);
            }
            try {
                $this->service()->forTenant(self::$second);
                $this->fail('An unpriced foreign amount must not enter the monetary total');
            } catch (RuntimeException $e) {
                $this->assertSame('Summary exchange rate unavailable', $e->getMessage());
            }
            $this->assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status(self::$second));
            $registry = $this->service()->forRegistry((int)self::$user['id'], self::$subject);
            $this->assertSame('error', $registry['items'][0]['state']);
            $this->assertNull($registry['items'][0]['bank_lines_missing_total']);
            $this->assertSame([], RestApiEnv::rows(self::$registry, 'SELECT * FROM mobile_summary_cache WHERE tenant_id=$1', [self::$secondId]));
        }
    }

    public function test_ten_seconds_of_pending_migrations_are_deferred_and_warmer_finishes_them(): void
    {
        $steps = array_keys(mobileTenantUpdateSteps());
        $current = $steps[count($steps) - 1];
        $pending = $steps[count($steps) - 3];
        $this->pair(self::$secondId);
        $cached = $this->service()->forTenant(self::$second);
        unset($cached['tenant_id'], $cached['name']);
        RestApiEnv::rows(self::$second, 'CREATE TABLE summary_migration_work(step text NOT NULL)');
        RestApiEnv::rows(self::$second, "CREATE FUNCTION summary_slow_migration() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.var_grp='mobile' AND NEW.var_name='mobile_tenant_step' THEN
                    PERFORM pg_sleep(5);
                    INSERT INTO summary_migration_work(step) VALUES(NEW.var_value);
                END IF;
                RETURN NEW;
            END $$");
        RestApiEnv::rows(self::$second, 'CREATE TRIGGER summary_slow_migration BEFORE INSERT ON settings
            FOR EACH ROW EXECUTE FUNCTION summary_slow_migration()');
        RestApiEnv::rows(self::$second, "UPDATE settings SET var_value=$1
            WHERE var_grp='mobile' AND var_name='mobile_tenant_step' AND user_id=0", [$pending]);
        try {
            $started = hrtime(true) / 1e9;
            $response = $this->registryRequest();
            $elapsed = hrtime(true) / 1e9 - $started;
            $this->assertSame(200, $response['status'], $response['body']);
            $this->assertSame('pending', $response['json']['data']['items'][0]['state']);
            $this->assertSame(1, $response['json']['data']['pending_count']);
            $this->assertLessThan(3.0, $elapsed, 'HTTP must not execute the two five-second migration steps');
            $this->assertNull($response['json']['data']['items'][0]['bank_lines_missing_total']);
            $this->assertSame([], RestApiEnv::rows(self::$second, 'SELECT * FROM summary_migration_work'));
            $this->assertSame($pending, RestApiEnv::rows(self::$second, "SELECT var_value FROM settings
                WHERE var_grp='mobile' AND var_name='mobile_tenant_step' AND user_id=0")[0]['var_value']);
            RestApiEnv::rows(self::$registry, "INSERT INTO mobile_summary_cache(tenant_id,payload,computed_at)
                VALUES($1,$2,now()-interval '3 minutes')", [self::$secondId, json_encode($cached, JSON_THROW_ON_ERROR)]);
            $stale = $this->registryRequest();
            $this->assertSame('ok', $stale['json']['data']['items'][0]['state']);
            $this->assertTrue($stale['json']['data']['items'][0]['stale']);
            $this->assertSame($cached['computed_at'], $stale['json']['data']['items'][0]['computed_at']);
            $this->assertSame([], RestApiEnv::rows(self::$second, 'SELECT * FROM summary_migration_work'));
            $started = hrtime(true) / 1e9;
            $warm = $this->service()->warm(self::$secondId);
            $this->assertGreaterThanOrEqual(10.0, hrtime(true) / 1e9 - $started);
            $this->assertSame([['tenant_id' => self::$secondId, 'state' => 'ok', 'stale' => false]], $warm);
            $this->assertSame(array_slice($steps, -2), array_column(RestApiEnv::rows(self::$second,
                'SELECT step FROM summary_migration_work ORDER BY step'), 'step'));
            $fresh = $this->registryRequest();
            $this->assertFalse($fresh['json']['data']['items'][0]['stale']);
            $this->assertSame(0, $fresh['json']['data']['pending_count']);
        } finally {
            RestApiEnv::rows(self::$second, 'DROP TRIGGER summary_slow_migration ON settings');
            RestApiEnv::rows(self::$second, 'DROP FUNCTION summary_slow_migration()');
            RestApiEnv::rows(self::$second, 'DROP TABLE summary_migration_work');
            RestApiEnv::rows(self::$second, "UPDATE settings SET var_value=$1
                WHERE var_grp='mobile' AND var_name='mobile_tenant_step' AND user_id=0", [$current]);
        }
    }
}
