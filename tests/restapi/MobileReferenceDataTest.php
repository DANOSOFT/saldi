<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileReferenceDataTest.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Cover ended fiscal years, fallback ordering and absent valid reference years.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class MobileReferenceDataTest extends TestCase
{
    private const V1 = '/restapi/endpoints/v1/';
    private const CURRENT = 8601;
    private const FUTURE = 8602;
    private static string $username;
    private static ?array $login = null;

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$username = 'capture_ref_' . bin2hex(random_bytes(4));
        self::$login = null;
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        RestApiEnv::rows($conn, 'UPDATE brugere SET rettigheder=$1, brugernavn=$2 WHERE brugernavn=$3', [str_repeat('0', 16), self::$username, RestApiEnv::user()]);
        RestApiEnv::rows($conn, "DELETE FROM grupper WHERE art IN ('RA','KM','SM','EM','YM','S')");
        RestApiEnv::rows($conn, 'DELETE FROM kontoplan');
        $startYear = (int)date('Y') - ((int)date('n') < 7 ? 1 : 0);
        foreach ([self::CURRENT => $startYear, self::FUTURE => $startYear + 1] as $id => $start) {
            RestApiEnv::rows($conn, "INSERT INTO grupper (art,kodenr,box1,box2,box3,box4) VALUES ('RA',$1,'7',$2,'6',$3)", [$id, (string)$start, (string)($start + 1)]);
        }
        foreach ([['KM','K',25,66200], ['SM','S',12.5,66100], ['EM','E',0,66150], ['YM','Y',5.5,66155]] as [$art,$code,$rate,$account]) {
            RestApiEnv::rows($conn, 'INSERT INTO grupper (art,kode,kodenr,beskrivelse,box1,box2,fiscal_year) VALUES ($1,$2,1,$3,$4,$5,$6)', [$art, $code, 'Receipt ' . $art, (string)$account, (string)$rate, self::CURRENT]);
        }
        RestApiEnv::rows($conn, "INSERT INTO grupper (art,kode,kodenr,beskrivelse,box1,box2,fiscal_year) VALUES ('KM','K',1,'Future rate','66200','17', $1)", [self::FUTURE]);
        RestApiEnv::rows($conn, "INSERT INTO grupper (art,kodenr,box1,box2,fiscal_year) VALUES ('S',99,'999','Wrong source', $1)", [self::CURRENT]);
        foreach ([[900001,'Office','K1','D','',self::CURRENT], [900002,'EU goods','E1','D','',self::CURRENT], [900003,'Balance','','S','',self::CURRENT], [900004,'Closed','K1','D','on',self::CURRENT], [900005,'Future','K1','D','',self::FUTURE]] as $row) {
            RestApiEnv::rows($conn, 'INSERT INTO kontoplan (kontonr,beskrivelse,moms,kontotype,lukket,regnskabsaar,saldo,primo) VALUES ($1,$2,$3,$4,$5,$6,1234,5678)', $row);
        }
        pg_close($conn);
    }

    public static function tearDownAfterClass(): void
    {
        RestApiEnv::teardownTenant();
    }

    /** @return array{status:int, body:string, json:array|null} */
    private function get(string $path): array
    {
        return RestApiEnv::http('GET', self::V1 . $path, null, $this->authHeaders());
    }

    /** Each suite owns a distinct principal, so other suites cannot exhaust its login bucket.
     * @return list<string>
     */
    private function authHeaders(): array
    {
        if (self::$login === null) {
            $res = RestApiEnv::login(self::$username, RestApiEnv::password(), RestApiEnv::accountOpen());
            $this->assertSame(200, $res['status'], $res['body']);
            self::$login = $res['json']['data'];
        }
        return ['Authorization: Bearer ' . self::$login['access_token']];
    }

    public function test_vat_codes_use_moms_rates_direction_and_today_fiscal_year(): void
    {
        $res = $this->get('vat-codes/');
        $this->assertSame(200, $res['status'], $res['body']);
        $expected = [
            ['code'=>'E1', 'description'=>'Receipt EM', 'rate'=>0, 'direction'=>'EU', 'konto'=>66150, 'fiscal_year'=>self::CURRENT],
            ['code'=>'K1', 'description'=>'Receipt KM', 'rate'=>25, 'direction'=>'purchase', 'konto'=>66200, 'fiscal_year'=>self::CURRENT],
            ['code'=>'S1', 'description'=>'Receipt SM', 'rate'=>12.5, 'direction'=>'sale', 'konto'=>66100, 'fiscal_year'=>self::CURRENT],
            ['code'=>'Y1', 'description'=>'Receipt YM', 'rate'=>5.5, 'direction'=>'import', 'konto'=>66155, 'fiscal_year'=>self::CURRENT],
        ];
        $this->assertSame($expected, $res['json']['data']);
        $res = $this->get('vat-codes/?year=' . self::FUTURE);
        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['json']['data']);
        $this->assertSame(17, $res['json']['data'][0]['rate']);
        $this->assertSame(self::FUTURE, $res['json']['data'][0]['fiscal_year']);
    }

    public function test_posting_chart_is_complete_open_current_year_and_exposes_no_balances(): void
    {
        $res = $this->get('accounts/posting-chart.php');
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame([
            ['kontonr'=>900001, 'beskrivelse'=>'Office', 'moms'=>'K1', 'kontotype'=>'D'],
            ['kontonr'=>900002, 'beskrivelse'=>'EU goods', 'moms'=>'E1', 'kontotype'=>'D'],
            ['kontonr'=>900003, 'beskrivelse'=>'Balance', 'moms'=>'', 'kontotype'=>'S'],
        ], $res['json']['data']);
        $explicit = $this->get('accounts/posting-chart.php?year=' . self::CURRENT);
        $this->assertSame($res['json']['data'], $explicit['json']['data']);
        $future = $this->get('accounts/posting-chart.php?year=' . self::FUTURE);
        $this->assertSame([900005], array_column($future['json']['data'], 'kontonr'));
        $this->assertSame('', $res['json']['message']);
        $this->assertStringNotContainsString('saldo', $res['body']);
        $this->assertStringNotContainsString('primo', $res['body']);
    }

    public function test_invalid_unknown_years_and_write_methods_are_rejected(): void
    {
        foreach (['vat-codes/', 'accounts/posting-chart.php'] as $path) {
            foreach (['0','-1','2026','999999','1%20OR%201=1','%27','1.5','01','&year[]=1','922337203685477580799'] as $value) {
                $res = $this->get($path . '?year=' . $value);
                $this->assertSame(422, $res['status'], $res['body']);
                $this->assertSame('validation_failed', $res['json']['data']['error']);
            }
            foreach (['POST','PUT','PATCH','DELETE'] as $method) {
                $res = RestApiEnv::http($method, self::V1 . $path, null, $this->authHeaders());
                $this->assertSame(405, $res['status']);
            }
            $anonymous = RestApiEnv::http('GET', self::V1 . $path);
            $this->assertSame(401, $anonymous['status']);
            $this->assertSame('invalid_token', $anonymous['json']['data']['error']);
        }
    }

    public function test_reference_catalogs_use_latest_started_year_when_current_year_is_missing(): void
    {
        $this->authHeaders();
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        $ended = (new DateTimeImmutable('first day of this month', new DateTimeZone('Europe/Copenhagen')))->modify('-1 month');
        $start = $ended->modify('-11 months');
        $older = $start->modify('-1 year');
        $saved = RestApiEnv::rows($conn, "SELECT id,box1,box2,box3,box4 FROM grupper WHERE art='RA'");
        $inserted = [];
        try {
            RestApiEnv::rows($conn, "UPDATE grupper SET box1=$1,box2=$2,box3=$3,box4=$4 WHERE art='RA' AND kodenr=$5",
                [$start->format('n'), $start->format('Y'), $ended->format('n'), $ended->format('Y'), self::CURRENT]);
            // A larger id with an older start must not displace the most recent year.
            foreach ([[8699, $older, $older->modify('+11 months')], [8700, $ended, $start]] as [$id, $from, $to]) {
                $row = RestApiEnv::rows($conn, "INSERT INTO grupper (art,kodenr,box1,box2,box3,box4)
                    VALUES ('RA',$1,$2,$3,$4,$5) RETURNING id",
                    [$id, $from->format('n'), $from->format('Y'), $to->format('n'), $to->format('Y')]);
                $inserted[] = $row[0]['id'];
            }
            foreach (['vat-codes/', 'accounts/posting-chart.php'] as $path) {
                $fallback = $this->get($path);
                $this->assertSame(200, $fallback['status'], $fallback['body']);
                $this->assertNotEmpty($fallback['json']['data']);
                $this->assertSame($this->get($path . '?year=' . self::CURRENT)['json']['data'], $fallback['json']['data']);
            }
            // Equal start dates resolve to the highest internal id, even without a current year.
            RestApiEnv::rows($conn, "UPDATE grupper SET box1=$1,box2=$2,box3=$3,box4=$4 WHERE art='RA' AND kodenr=$5",
                [$start->format('n'), $start->format('Y'), $ended->format('n'), $ended->format('Y'), self::FUTURE]);
            $vat = $this->get('vat-codes/');
            $this->assertSame(200, $vat['status'], $vat['body']);
            $this->assertSame(self::FUTURE, $vat['json']['data'][0]['fiscal_year']);
            $chart = $this->get('accounts/posting-chart.php');
            $this->assertSame(200, $chart['status'], $chart['body']);
            $this->assertSame([900005], array_column($chart['json']['data'], 'kontonr'));
            RestApiEnv::rows($conn, "UPDATE grupper SET box1='13' WHERE art='RA'");
            foreach (['vat-codes/', 'accounts/posting-chart.php'] as $path) {
                $missing = $this->get($path);
                $this->assertSame(422, $missing['status'], $missing['body']);
            }
        } finally {
            foreach ($inserted as $id) {
                RestApiEnv::rows($conn, 'DELETE FROM grupper WHERE id=$1', [$id]);
            }
            foreach ($saved as $row) {
                RestApiEnv::rows($conn, 'UPDATE grupper SET box1=$1,box2=$2,box3=$3,box4=$4 WHERE id=$5',
                    [$row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['id']]);
            }
            pg_close($conn);
        }
    }

    public function test_chart_bound_never_returns_a_truncated_success_list(): void
    {
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        try {
            RestApiEnv::rows($conn, "INSERT INTO kontoplan (kontonr,beskrivelse,moms,kontotype,lukket,regnskabsaar)
                SELECT 1000000+n,'Bound test','','D','',$1 FROM generate_series(1,4997) AS n", [self::CURRENT]);
            $full = $this->get('accounts/posting-chart.php');
            $this->assertSame(200, $full['status'], $full['body']);
            $this->assertCount(5000, $full['json']['data']);
            RestApiEnv::rows($conn, "INSERT INTO kontoplan (kontonr,beskrivelse,moms,kontotype,lukket,regnskabsaar) VALUES (1100000,'Overflow','','D','',$1)", [self::CURRENT]);
            $overflow = $this->get('accounts/posting-chart.php');
            $this->assertSame(413, $overflow['status']);
            $this->assertSame(['error'=>'payload_too_large'], $overflow['json']['data']);
        } finally {
            RestApiEnv::rows($conn, 'DELETE FROM kontoplan WHERE kontonr>=1000000');
            pg_close($conn);
        }
    }
}
