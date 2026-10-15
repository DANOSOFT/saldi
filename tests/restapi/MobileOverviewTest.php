<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileOverviewTest.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Cover unavailable fiscal years and current payment/credit offsets in both dashboards.
// 20261005 CDX/LH Keep fixture cleanup compatible with retained T3 provenance and current migration markers.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/services/OverviewService.php';

final class MobileOverviewTest extends TestCase
{
    private const V1 = '/restapi/endpoints/v1/dashboard/';
    private const YEAR = 8802;
    private const PRIOR = 8801;
    private static $connection;
    private static string $today;
    private static string $start;
    private static string $priorStart;
    private static string $postingDate;
    private static string $username;
    private static ?array $login = null;

    /** Provision isolated accounting fixtures using the shared environment and random credentials. */
    public static function setUpBeforeClass(): void
    {
        if (RestApiEnv::unavailableReason() !== null) {
            return;
        }
        RestApiEnv::bootstrapTenant();
        self::$connection = RestApiEnv::connect(RestApiEnv::testDb());
        self::$username = 'overview_' . bin2hex(random_bytes(4));
        self::$today = OverviewService::today();
        // Always a non-calendar year, even if this fixture runs in January or July.
        $startYear = (int)substr(self::$today, 0, 4) - ((int)substr(self::$today, 5, 2) < 7 ? 1 : 0);
        self::$start = "$startYear-07-01";
        self::$priorStart = ($startYear - 1) . '-07-01';
        self::$postingDate = (new DateTimeImmutable(self::$today))->modify('-1 day')->format('Y-m-d');
        self::sql('UPDATE brugere SET brugernavn=$1 WHERE brugernavn=$2', [self::$username, RestApiEnv::user()]);
        foreach (['transaktioner', 'openpost', 'kontoplan', 'valuta'] as $table) {
            self::sql("DELETE FROM $table");
        }
        self::sql("DELETE FROM grupper WHERE art IN ('RA','KASKL','POS','VK')");
        self::sql("DELETE FROM settings WHERE var_name='baseCurrency' OR var_grp='dashboard_values'");
        self::sql("INSERT INTO settings (var_name,var_grp,var_value,user_id) VALUES ('baseCurrency','localization','DKK',0)");
        foreach ([self::PRIOR => $startYear - 1, self::YEAR => $startYear] as $id => $year) {
            self::sql("INSERT INTO grupper (art,kodenr,box1,box2,box3,box4,box5)
                VALUES ('RA',$1,'7',$2,'6',$3,'on')", [$id, (string)$year, (string)($year + 1)]);
            foreach ([[1100,'D',0,''], [1800,'D',0,'on'], [2500,'D',0,''], [3100,'S',1000,''],
                [3200,'S',500,'on'], [3300,'S',100,''], [3400,'S',9999,'']] as [$account,$type,$primo,$closed]) {
                self::sql('INSERT INTO kontoplan (kontonr,kontotype,primo,regnskabsaar,lukket,saldo)
                    VALUES ($1,$2,$3,$4,$5,999999)', [$account, $type, $primo, $id, $closed]);
            }
        }
        foreach ([[101,'3100'], [102,'3200'], [103,'3100'], [104,'3300'], [105,'1100']] as [$user,$account]) {
            self::sql("INSERT INTO grupper (art,kode,kodenr,box1) VALUES ('KASKL','3',$1,$2)", [$user,$account]);
        }
        self::sql("INSERT INTO grupper (art,kodenr,box2,fiscal_year) VALUES ('POS',1,$1,$2)", ["3300\t3300",self::YEAR]);
        self::sql("INSERT INTO grupper (art,kodenr,box1) VALUES ('VK',8810,'EUR')");
        self::sql('INSERT INTO valuta (gruppe,valdate,kurs) VALUES (8810,$1,745),(8810,$2,900)',
            [self::$postingDate, (new DateTimeImmutable(self::$today))->modify('+1 day')->format('Y-m-d')]);
        // Net turnover 1000 - 200 + 100 = 900. Exclude expenses, balance postings and other dates.
        self::posting(1100, self::$start, 0, 1000);
        self::posting(1100, self::$today, 200, 0);
        self::posting(1800, self::$today, 0, 100);
        self::posting(2500, self::$today, 0, 3000);
        self::posting(3400, self::$today, 0, 6000);
        self::posting(1100, (new DateTimeImmutable(self::$start))->modify('-1 day')->format('Y-m-d'), 0, 7777);
        self::posting(1100, (new DateTimeImmutable(self::$today))->modify('+1 day')->format('Y-m-d'), 0, 8888);
        self::posting(1100, self::$priorStart, 0, 600);
        self::posting(3100, self::$today, 300, 0);
        self::posting(3200, self::$today, 0, 100);
        self::posting(3300, self::$today, 50, 0);
        self::posting(3100, (new DateTimeImmutable(self::$today))->modify('+1 day')->format('Y-m-d'), 999, 0);
        self::posting(3100, (new DateTimeImmutable(self::$start))->modify('-1 day')->format('Y-m-d'), 999, 0);
        // Native partial payment leaves 600. Foreign 100 EUR at 745 per 100 is 745 DKK.
        $native = self::debtor('overview-native', 'D');
        self::post($native, 1000);
        self::post($native, -400);
        $foreign = self::debtor('overview-foreign', 'D');
        self::post($foreign, 100, 'EUR', 100);
        $credit = self::debtor('overview-credit', 'D');
        self::post($credit, -100);
        $settled = self::debtor('overview-settled', 'D');
        self::post($settled, 700, 'DKK', 100, '1', self::$postingDate);
        $notDue = self::debtor('overview-due-today', 'D');
        self::post($notDue, 800, 'DKK', 100, '0', null, self::$today);
        $creditor = self::debtor('overview-creditor', 'K');
        self::post($creditor, 99999);
        self::sql('CREATE TABLE IF NOT EXISTS moms_periode_luk (kalender_aar integer,kalender_maaned integer,status text)');
        self::sql('DELETE FROM moms_periode_luk');
        self::sql("INSERT INTO moms_periode_luk (kalender_aar,kalender_maaned,status) VALUES ($1,$2,'closed')",
            [(int)substr(self::$today,0,4),(int)substr(self::$today,5,2)]);
    }

    protected function setUp(): void
    {
        if (!self::$connection) {
            $this->markTestSkipped(RestApiEnv::unavailableReason() ?: 'Tenant unavailable');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$connection) {
            pg_close(self::$connection);
            self::$connection = null;
        }
        RestApiEnv::teardownTenant();
    }

    /** @return list<array<string,mixed>> */
    private static function sql(string $sql, array $parameters = []): array
    {
        return RestApiEnv::rows(self::$connection, $sql, $parameters);
    }

    /** Seed a posted movement, never a simulated/draft invoice total. */
    private static function posting(int $account, string $date, float $debit, float $credit): void
    {
        self::sql('INSERT INTO transaktioner (kontonr,transdate,debet,kredit) VALUES ($1,$2,$3,$4)', [$account,$date,$debit,$credit]);
    }

    /** @return int Newly owned fixture account id. */
    private static function debtor(string $name, string $art): int
    {
        $rows = self::sql('INSERT INTO adresser (firmanavn,art) VALUES ($1,$2) RETURNING id', [$name,$art]);
        return (int)$rows[0]['id'];
    }

    /** Seed the retained settlement/remainder representation used by Saldi reports. */
    private static function post(int $account, float $amount, string $currency = 'DKK', float $rate = 100,
        string $settled = '0', ?string $settledAt = null, ?string $due = null): void
    {
        self::sql('INSERT INTO openpost (konto_id,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet,udlign_date)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8)', [$account,$amount,self::$postingDate,$due ?: self::$postingDate,$currency,$rate,$settled,$settledAt]);
    }

    /** @return list<string> */
    private function headers(): array
    {
        if (self::$login === null) {
            $response = RestApiEnv::login(self::$username, RestApiEnv::password(), RestApiEnv::accountOpen());
            $this->assertSame(200, $response['status'], $response['body']);
            self::$login = $response['json']['data'];
        }
        return ['Authorization: Bearer ' . self::$login['access_token']];
    }

    /** @return array{status:int,body:string,json:array|null} */
    private function get(string $path = 'overview.php'): array
    {
        return RestApiEnv::http('GET', self::V1 . $path, null, $this->headers());
    }

    public function test_exact_non_calendar_revenue_credit_note_and_prior_period(): void
    {
        $money = (new OverviewService(self::$connection))->money(self::$today);
        $this->assertSame(['id'=>self::YEAR,'start'=>self::$start,'end'=>(substr(self::$start,0,4)+1).'-06-30',
            'locked'=>false,'period_locked'=>true], $money['fiscal_year']);
        $this->assertSame(900.0, $money['revenue']['amount']);
        $this->assertSame(600.0, $money['revenue']['previous_amount']);
        $this->assertSame(self::$priorStart, $money['revenue']['previous_period']['start']);
        $this->assertSame((substr(self::$today,0,4)-1).substr(self::$today,4), $money['revenue']['previous_period']['end']);
        $this->assertSame(self::PRIOR, $money['revenue']['previous_period']['fiscal_year']);
    }

    public function test_partial_settlement_and_foreign_currency_exact_remainder(): void
    {
        $this->assertSame(['count'=>2,'amount'=>1345.0], (new OverviewService(self::$connection))->overdue(self::$today));
    }

    public function test_two_banks_primo_and_cash_are_summed_once(): void
    {
        $service = new OverviewService(self::$connection);
        $this->assertSame(['bank'=>1700.0,'cash'=>150.0,'total'=>1850.0,'bank_accounts'=>2,'cash_accounts'=>1,'configured'=>true],
            $service->liquidity($service->fiscalYear(self::$today), self::$today));
    }

    public function test_overview_and_deprecated_stats_share_exact_figures(): void
    {
        $overview = $this->get();
        $this->assertSame(200, $overview['status'], $overview['body']);
        $data = $overview['json']['data'];
        $this->assertTrue($data['money_cards_available']);
        $this->assertSame(900, $data['revenue']['amount']);
        $this->assertSame(600, $data['revenue']['previous_amount']);
        $this->assertSame(['count'=>2,'amount'=>1345], $data['overdue']);
        $this->assertSame(1850, $data['liquidity']['total']);
        $this->assertTrue($data['fiscal_year']['period_locked']);
        $this->assertSame('tenant', $data['queue']['scope']);
        $stats = $this->get('stats.php');
        $this->assertSame(200, $stats['status'], $stats['body']);
        $this->assertSame(['revenue_ytd'=>900,'overdue_count'=>2,'overdue_amount'=>1345], $stats['json']['data']);
    }

    public function test_receipt_only_queue_omits_money_and_rechecks_database_rights(): void
    {
        $this->headers();
        $user = self::sql('SELECT id FROM brugere WHERE brugernavn=$1', [self::$username])[0];
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        $installation = RestApiEnv::rows($master, "SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name='installation_id'")[0]['var_value'];
        pg_close($master);
        $subject = $installation . '/account/' . $user['id'];
        foreach ([['own','ubehandlet',$subject,null], ['other','ubehandlet',$installation.'/account/999999',null],
            ['staged','staged',$subject,null], ['attached','attached',$subject,null], ['deleted','deleted',$subject,self::$today],
            ['legacy','ubehandlet',null,null], ['tombstone','ubehandlet',$subject,self::$today]] as [$name,$status,$owner,$deleted]) {
            self::sql('INSERT INTO pool_files (filename,status,uploaded_by,deleted_at) VALUES ($1,$2,$3,$4)', [$name.'.pdf',$status,$owner,$deleted]);
        }
        self::sql('UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2', [str_repeat('0',16),self::$username]);
        try {
            $response = $this->get();
            $this->assertSame(200, $response['status'], $response['body']);
            $this->assertSame(['as_of'=>self::$today,'money_cards_available'=>false,
                'queue'=>['unhandled_count'=>1,'scope'=>'own']], $response['json']['data']);
            $stats = $this->get('stats.php');
            $this->assertSame(403, $stats['status']);
            $this->assertSame('permission_denied', $stats['json']['data']['error']);
            // Receipt-only accounts retain the queue even without an accounting year/chart.
            self::sql("UPDATE grupper SET art='overview-test-hidden' WHERE art='RA'");
            $withoutYear = $this->get();
            $this->assertSame(200, $withoutYear['status'], $withoutYear['body']);
            $this->assertSame($response['json']['data'], $withoutYear['json']['data']);
            self::sql("UPDATE grupper SET art='RA' WHERE art='overview-test-hidden'");
            $readOnly = str_repeat('0',16);
            $readOnly[4] = '2';
            self::sql('UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2', [$readOnly,self::$username]);
            $reader = $this->get();
            $this->assertSame(200, $reader['status'], $reader['body']);
            $this->assertTrue($reader['json']['data']['money_cards_available']);
            $this->assertSame(['unhandled_count'=>3,'scope'=>'tenant'], $reader['json']['data']['queue']);
        } finally {
            self::sql("UPDATE grupper SET art='RA' WHERE art='overview-test-hidden'");
            self::sql('UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2', [str_repeat('1',16),self::$username]);
            self::sql("UPDATE pool_files SET status='deleted',deleted_at=now() WHERE status<>'deleted'");
        }
    }

    public function test_finans_queue_and_legacy_stats_survive_unavailable_fiscal_years(): void
    {
        $this->headers();
        $saved = self::sql("SELECT id,box1,box2,box3,box4 FROM grupper WHERE art='RA'");
        $pool = self::sql("INSERT INTO pool_files (filename,status) VALUES ('overview-year-fallback.pdf','ubehandlet') RETURNING id")[0]['id'];
        $ended = (new DateTimeImmutable(self::$today))->modify('first day of this month')->modify('-1 month');
        $start = $ended->modify('-11 months');
        try {
            self::sql("UPDATE grupper SET box1=$1,box2=$2,box3=$3,box4=$4 WHERE art='RA'",
                [$start->format('n'), $start->format('Y'), $ended->format('n'), $ended->format('Y')]);
            foreach (['overview.php', 'overview.php?year=' . self::YEAR] as $path) {
                $response = $this->get($path);
                $this->assertSame(200, $response['status'], $response['body']);
                $this->assertSame(['as_of'=>self::$today, 'money_cards_available'=>false,
                    'queue'=>['unhandled_count'=>1, 'scope'=>'tenant'],
                    'money_cards_unavailable_reason'=>'no_fiscal_year'], $response['json']['data']);
            }
            $unknown = $this->get('overview.php?year=999999');
            $this->assertSame(422, $unknown['status'], $unknown['body']);
            $stats = $this->get('stats.php');
            $this->assertSame(200, $stats['status'], $stats['body']);
            $this->assertSame(['revenue_ytd'=>null, 'overdue_count'=>2, 'overdue_amount'=>1345,
                'money_cards_available'=>false, 'money_cards_unavailable_reason'=>'no_fiscal_year'], $stats['json']['data']);
            self::sql("UPDATE grupper SET art='overview-test-hidden' WHERE art='RA'");
            $missing = $this->get();
            $this->assertSame(200, $missing['status'], $missing['body']);
            $this->assertSame('no_fiscal_year', $missing['json']['data']['money_cards_unavailable_reason']);
            $this->assertSame(1, $missing['json']['data']['queue']['unhandled_count']);
            self::sql("UPDATE grupper SET art='RA',box1='13' WHERE art='overview-test-hidden'");
            $invalid = $this->get();
            $this->assertSame(200, $invalid['status'], $invalid['body']);
            $this->assertSame('invalid_fiscal_year', $invalid['json']['data']['money_cards_unavailable_reason']);
            $this->assertSame(1, $invalid['json']['data']['queue']['unhandled_count']);
        } finally {
            foreach ($saved as $row) {
                self::sql("UPDATE grupper SET art='RA',box1=$1,box2=$2,box3=$3,box4=$4 WHERE id=$5",
                    [$row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['id']]);
            }
            self::sql("UPDATE pool_files SET status='deleted',deleted_at=now() WHERE id=$1", [$pool]);
        }
    }

    public function test_overlapping_years_hide_money_but_preserve_the_queue_and_stats_response(): void
    {
        $this->headers();
        $overlap = self::sql("INSERT INTO grupper (art,kodenr,box1,box2,box3,box4,box5)
            SELECT art,8899,box1,box2,box3,box4,box5 FROM grupper WHERE art='RA' AND kodenr=$1 RETURNING id", [self::YEAR])[0]['id'];
        try {
            $response = $this->get();
            $this->assertSame(200, $response['status'], $response['body']);
            $this->assertFalse($response['json']['data']['money_cards_available']);
            $this->assertSame('ambiguous_fiscal_year', $response['json']['data']['money_cards_unavailable_reason']);
            $this->assertSame(['unhandled_count'=>0,'scope'=>'tenant'], $response['json']['data']['queue']);
            $this->assertArrayNotHasKey('revenue', $response['json']['data']);
            $stats = $this->get('stats.php');
            $this->assertSame(200, $stats['status'], $stats['body']);
            $this->assertNull($stats['json']['data']['revenue_ytd']);
            $this->assertSame(1345, $stats['json']['data']['overdue_amount']);
            $this->assertSame('ambiguous_fiscal_year', $stats['json']['data']['money_cards_unavailable_reason']);
            $explicit = $this->get('overview.php?year=' . self::YEAR);
            $this->assertSame(200, $explicit['status'], $explicit['body']);
            $this->assertTrue($explicit['json']['data']['money_cards_available']);
        } finally {
            self::sql('DELETE FROM grupper WHERE id=$1', [$overlap]);
        }
    }

    public function test_today_payments_and_future_due_credits_reduce_overdue_in_both_dashboards(): void
    {
        $this->headers();
        $accounts = [];
        $future = (new DateTimeImmutable(self::$today))->modify('+1 month')->format('Y-m-d');
        try {
            foreach (['payment', 'credit', 'fully-credited'] as $kind) {
                $account = self::debtor('overview-offset-' . $kind, 'D');
                $accounts[] = $account;
                self::post($account, 1000);
                self::post($account, $kind === 'fully-credited' ? -1000 : -400, 'DKK', 100, '0', null, $future);
                if ($kind === 'payment') {
                    self::sql('UPDATE openpost SET transdate=$1,forfaldsdate=NULL WHERE konto_id=$2 AND amount<0', [self::$today, $account]);
                }
                // Not-yet-due claims and future-posted offsets must stay outside the cutoff.
                self::post($account, 9999, 'DKK', 100, '0', null, $future);
                self::post($account, -9999);
                self::sql('UPDATE openpost SET transdate=$1 WHERE konto_id=$2 AND amount=-9999', [$future, $account]);
            }
            $service = new OverviewService(self::$connection);
            $this->assertSame(['count'=>4,'amount'=>2545.0], $service->overdue(self::$today));
            $overview = $this->get();
            $this->assertSame(200, $overview['status'], $overview['body']);
            $this->assertSame(['count'=>4,'amount'=>2545], $overview['json']['data']['overdue']);
            $stats = $this->get('stats.php');
            $this->assertSame(200, $stats['status'], $stats['body']);
            $this->assertSame(4, $stats['json']['data']['overdue_count']);
            $this->assertSame(2545, $stats['json']['data']['overdue_amount']);
        } finally {
            foreach ($accounts as $account) {
                self::sql('DELETE FROM openpost WHERE konto_id=$1', [$account]);
                self::sql('DELETE FROM adresser WHERE id=$1', [$account]);
            }
        }
    }

    public function test_cutoff_uses_settlement_date_and_excludes_future_posts(): void
    {
        self::sql('BEGIN');
        try {
            self::sql('DELETE FROM openpost');
            $account = self::debtor('overview-history','D');
            self::post($account, 400, 'DKK', 100, '1', self::$today);
            self::post($account, 200, 'DKK', 100, '1', null);
            self::post($account, 100, 'DKK', 100, '1', (new DateTimeImmutable(self::$postingDate))->modify('-1 day')->format('Y-m-d'));
            self::post($account, 9999);
            self::sql('UPDATE openpost SET transdate=$1 WHERE amount=9999', [self::$today]);
            $historical = (new DateTimeImmutable(self::$today))->modify('-1 day')->format('Y-m-d');
            self::sql('UPDATE openpost SET forfaldsdate=$1', [(new DateTimeImmutable($historical))->modify('-1 day')->format('Y-m-d')]);
            $service = new OverviewService(self::$connection);
            $this->assertSame(['count'=>1,'amount'=>600.0], $service->overdue($historical));
            $this->assertSame(['count'=>1,'amount'=>9999.0], $service->overdue(self::$today));
        } finally {
            self::sql('ROLLBACK');
        }
    }

    public function test_locked_year_and_absent_or_open_month_flags(): void
    {
        self::sql('BEGIN');
        try {
            $service = new OverviewService(self::$connection);
            self::sql("UPDATE moms_periode_luk SET status='open'");
            $this->assertFalse($service->money(self::$today)['fiscal_year']['period_locked']);
            self::sql('DROP TABLE moms_periode_luk');
            $this->assertFalse($service->money(self::$today)['fiscal_year']['period_locked']);
            self::sql("UPDATE grupper SET box5='' WHERE art='RA' AND kodenr=$1", [self::YEAR]);
            $locked = $service->money(self::$today)['fiscal_year'];
            $this->assertTrue($locked['locked']);
            $this->assertTrue($locked['period_locked']);
        } finally {
            self::sql('ROLLBACK');
        }
    }

    public function test_missing_liquidity_setup_and_prior_year_are_null(): void
    {
        self::sql('BEGIN');
        try {
            self::sql("DELETE FROM grupper WHERE art='KASKL' OR (art='RA' AND kodenr=$1)", [self::PRIOR]);
            $money = (new OverviewService(self::$connection))->money(self::$today);
            $this->assertSame(['bank'=>null,'cash'=>150.0,'total'=>null,'bank_accounts'=>0,'cash_accounts'=>1,'configured'=>false], $money['liquidity']);
            $this->assertNull($money['revenue']['previous_amount']);
            $this->assertNull($money['revenue']['previous_period']);
        } finally {
            self::sql('ROLLBACK');
        }
    }

    public function test_invalid_parameters_authentication_and_methods(): void
    {
        foreach (['as_of=2026-02-30','as_of[]=2026-01-01','as_of=9999-01-01','year=0',
            'year=2026','year[]=1','year=1%20OR%201=1','year=99999999999999999999999999999'] as $query) {
            $response = $this->get('overview.php?'.$query);
            $this->assertSame(422, $response['status'], $response['body']);
            $this->assertSame('validation_failed', $response['json']['data']['error']);
        }
        $this->assertSame(200, $this->get('overview.php?year='.self::YEAR)['status']);
        $anonymous = RestApiEnv::http('GET',self::V1.'overview.php');
        $this->assertSame(401,$anonymous['status']);
        $method = RestApiEnv::http('POST',self::V1.'overview.php',[], $this->headers());
        $this->assertSame(405,$method['status']);
    }

    public function test_foreign_rows_round_after_conversion_and_keep_stored_rates(): void
    {
        self::sql('BEGIN');
        $hadCurrency = array_key_exists('baseCurrency',$GLOBALS);
        $previousCurrency = ifset($GLOBALS,'baseCurrency');
        $GLOBALS['baseCurrency'] = 'overview-outer-currency';
        try {
            self::sql('DELETE FROM openpost');
            $account = self::debtor('overview-rounding','D');
            self::post($account, 0.01, 'EUR', 745);
            self::post($account, 0.02, 'EUR', 745);
            self::post($account, -0.01, 'EUR', 745);
            self::post($account, 1, 'EUR', 0);
            self::post($account, 2, 'EUR', 350);
            self::post($account, 1.235, 'USD', 100);
            self::post($account, 1.235, 'NO_RATE', 100);
            self::post($account, 3, '-', 200);
            self::post($account, 4, '', 100);
            $service = new OverviewService(self::$connection);
            $this->assertSame(['count'=>1,'amount'=>24.08], $service->overdue(self::$today));
            $this->assertSame('overview-outer-currency', $GLOBALS['baseCurrency']);
            // Simulate a second tenant's different rate in the same PHP process: no static leakage.
            self::sql('UPDATE valuta SET kurs=800 WHERE valdate=$1', [self::$postingDate]);
            $this->assertSame(['count'=>1,'amount'=>24.63], $service->overdue(self::$today));
        } finally {
            self::sql('ROLLBACK');
            if ($hadCurrency) {
                $GLOBALS['baseCurrency'] = $previousCurrency;
            } else {
                unset($GLOBALS['baseCurrency']);
            }
        }
    }

    public function test_foreign_bank_primo_and_postings_are_already_in_base_currency(): void
    {
        self::sql('BEGIN');
        try {
            self::sql('UPDATE kontoplan SET primo=100,valuta=8810 WHERE kontonr=3200 AND regnskabsaar=$1',[self::YEAR]);
            $service = new OverviewService(self::$connection);
            $this->assertSame(['bank'=>1300.0,'cash'=>150.0,'total'=>1450.0,
                'bank_accounts'=>2,'cash_accounts'=>1,'configured'=>true],
                $service->liquidity($service->fiscalYear(self::$today),self::$today));
        } finally {
            self::sql('ROLLBACK');
        }
    }

    public function test_leap_day_comparison_clamps_cutoff_and_uses_actual_month_end(): void
    {
        self::sql('BEGIN');
        try {
            self::sql("UPDATE grupper SET box1='3',box2='2023',box3='2',box4='2024' WHERE art='RA' AND kodenr=$1",[self::YEAR]);
            self::sql("UPDATE grupper SET box1='3',box2='2022',box3='2',box4='2023' WHERE art='RA' AND kodenr=$1",[self::PRIOR]);
            self::posting(1100,'2024-02-29',0,900);
            self::posting(1100,'2023-02-28',0,600);
            self::posting(1100,'2023-03-01',0,100);
            $money = (new OverviewService(self::$connection))->money('2024-02-29');
            $this->assertSame('2024-02-29',$money['fiscal_year']['end']);
            $this->assertSame(1000.0,$money['revenue']['amount']);
            $this->assertSame(600.0,$money['revenue']['previous_amount']);
            $this->assertSame(['start'=>'2022-03-01','end'=>'2023-02-28','fiscal_year'=>self::PRIOR],
                $money['revenue']['previous_period']);
        } finally {
            self::sql('ROLLBACK');
        }
    }

    public function test_overlapping_years_fail_instead_of_picking_arbitrary_figures(): void
    {
        self::sql('BEGIN');
        try {
            self::sql("INSERT INTO grupper (art,kodenr,box1,box2,box3,box4,box5)
                SELECT art,8899,box1,box2,box3,box4,box5 FROM grupper WHERE art='RA' AND kodenr=$1",[self::YEAR]);
            $service = new OverviewService(self::$connection);
            $this->assertSame(900.0,$service->revenue($service->fiscalYear(self::$today,self::YEAR),self::$today));
            $this->expectException(InvalidArgumentException::class);
            $service->fiscalYear(self::$today);
        } finally {
            self::sql('ROLLBACK');
        }
    }
}
