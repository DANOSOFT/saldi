<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileRestLoginSchemaTest.php --- ver 5.0.0 --- 2026.10.05
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
// 20261005 CDX/LH Created. Cover legacy schema provisioning through app login.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/fixtures/t2/SalesFixture.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../restapi/core/RequestId.php';

final class MobileRestLoginSchemaTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
    }

    public static function tearDownAfterClass(): void
    {
        RestApiEnv::teardownTenant();
    }

    public function testAppLoginRepairsLegacySchemaBeforeQuoteAndCreate(): void
    {
        $connection = RestApiEnv::connect(RestApiEnv::testDb());
        try {
            $fixture = SalesFixture::create($connection, [RestApiEnv::class, 'rows']);
            // The fixture itself adds performed_by; remove it AFTER seeding so it cannot mask the bug.
            RestApiEnv::rows($connection, 'ALTER TABLE ordrer DROP COLUMN performed_by');
            RestApiEnv::rows($connection, 'DROP TABLE IF EXISTS moms_periode_luk CASCADE');
            RestApiEnv::rows($connection, 'DROP FUNCTION IF EXISTS check_moms_periode_luk()');
            RestApiEnv::rows($connection, "DELETE FROM settings WHERE var_grp='mobile' AND var_name='mobile_tenant_step'");
            self::assertSame([], RestApiEnv::rows($connection, "SELECT 1 FROM information_schema.columns
                WHERE table_schema=current_schema() AND table_name='ordrer' AND column_name='performed_by'"));

            $login = RestApiEnv::http('POST', '/restapi/endpoints/v1/auth/login.php', [
                'account_name' => RestApiEnv::accountOpen(), 'username' => RestApiEnv::user(),
                'password' => RestApiEnv::password(), 'device_id' => 'legacy-schema-app',
            ]);
            self::assertSame(200, $login['status'], $login['body']);
            $headers = ['Authorization: Bearer ' . $login['json']['data']['access_token']];
            $root = '/restapi/endpoints/v1/debitor/sales/';
            $body = ['client_id' => RequestId::resolve(null), 'konto_id' => $fixture['konto_id'], 'kind' => 'tilbud',
                'lines' => [['vare_id' => $fixture['product_id'], 'quantity' => '2', 'unit_price' => '100', 'discount_pct' => '10']]];
            $before = RestApiEnv::rows($connection, 'SELECT count(*) AS n FROM ordrer')[0]['n'];
            $quote = RestApiEnv::http('POST', $root . 'quote.php', $body, $headers);
            self::assertSame(200, $quote['status'], $quote['body']);
            self::assertSame($before, RestApiEnv::rows($connection, 'SELECT count(*) AS n FROM ordrer')[0]['n']);
            $create = RestApiEnv::http('POST', $root . 'index.php', $body, $headers);
            self::assertSame(201, $create['status'], $create['body']);
            $order = $create['json']['data'];
            self::assertSame(['net' => '180.00', 'vat' => '45.00', 'gross' => '225.00', 'currency' => 'DKK'], $order['totals']);
            self::assertSame($quote['json']['data']['totals'], $order['totals']);
            self::assertSame('', RestApiEnv::rows($connection, 'SELECT performed_by FROM ordrer WHERE id=$1', [$order['id']])[0]['performed_by']);

            $date = (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
            RestApiEnv::rows($connection, "INSERT INTO moms_periode_luk (kalender_aar,kalender_maaned,status)
                VALUES (EXTRACT(YEAR FROM $1::date),EXTRACT(MONTH FROM $1::date),'closed')", [$date]);
            $transition = RestApiEnv::http('POST', $root . 'transition.php', [
                'client_id' => RequestId::resolve(null), 'order_id' => $order['id'], 'to' => 'faktura', 'invoice_date' => $date,
            ], array_merge($headers, ['If-Match: ' . $order['etag']]));
            self::assertSame(422, $transition['status'], $transition['body']);
            self::assertSame('period_locked', $transition['json']['data']['details']['reason']);
            self::assertSame('0', RestApiEnv::rows($connection, 'SELECT status FROM ordrer WHERE id=$1', [$order['id']])[0]['status']);

            // The database guard must enforce the same lock for writes outside the friendly REST check.
            self::assertTrue(pg_send_query_params($connection, 'INSERT INTO transaktioner (transdate) VALUES ($1)', [$date]));
            $result = pg_get_result($connection);
            self::assertSame(PGSQL_FATAL_ERROR, pg_result_status($result));
            self::assertStringContainsString('er lukket for bogfoering', pg_result_error($result));
            self::assertFalse(pg_get_result($connection));

            // Replay only the appended step as an already-migrated tenant; retain employee/period data.
            RestApiEnv::rows($connection, 'UPDATE ordrer SET performed_by=$1 WHERE id=$2', ['Selected employee', $order['id']]);
            RestApiEnv::rows($connection, "UPDATE settings SET var_value='m2_11_payment_capture_money'
                WHERE var_grp='mobile' AND var_name='mobile_tenant_step'");
            mobileTenantUpdates($connection);
            mobileTenantUpdates($connection);
            self::assertSame('Selected employee', RestApiEnv::rows($connection, 'SELECT performed_by FROM ordrer WHERE id=$1', [$order['id']])[0]['performed_by']);
            self::assertSame('closed', RestApiEnv::rows($connection, 'SELECT status FROM moms_periode_luk')[0]['status']);
            self::assertSame([['var_value' => array_key_last(mobileTenantUpdateSteps())]], RestApiEnv::rows($connection,
                "SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name='mobile_tenant_step' AND user_id=0"));
        } finally {
            pg_close($connection);
        }
    }
}
