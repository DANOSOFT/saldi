<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/OpenPostsEndpointTest.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Verify raw web reconciliation, foreign rate isolation and response fixtures.
// 20261001 CDX/LH Cover fractional cent allocation and realistic journal voucher reference collisions.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../includes/db_query.php';
require_once __DIR__ . '/../../restapi/services/OpenPostService.php';
require_once __DIR__ . '/../../restapi/services/OverviewService.php';

final class OpenPostsEndpointTest extends TestCase
{
    private const PATH = '/restapi/endpoints/v1/debitor/open-posts/index.php';
    private static $connection;
    private static array $accounts = [];
    private static array $posts = [];
    private static array $orders = [];
    private static string $today;
    private static string $transdate;
    private static array $headers;
    private static int $creditorPost;

    public static function setUpBeforeClass(): void
    {
        if (RestApiEnv::unavailableReason() !== null) {
            return;
        }
        RestApiEnv::bootstrapTenant();
        self::$connection = RestApiEnv::connect(RestApiEnv::testDb());
        self::$headers = RestApiEnv::authHeaders();
        self::$today = OpenPostsReader::today();
        self::$transdate = self::date(-150);
        foreach (['payment_attempts','payment_links','document_deliveries','openpost','ordrelinjer','ordrer','valuta'] as $table) {
            self::sql("DELETE FROM $table");
        }
        self::sql("DELETE FROM grupper WHERE art='VK'");
        self::sql("DELETE FROM settings WHERE var_name='baseCurrency' OR var_grp='mobile_betaling'");
        self::sql("INSERT INTO settings (var_name,var_grp,var_value,user_id) VALUES ('baseCurrency','localization','DKK',0)");
        self::sql("INSERT INTO grupper (art,kodenr,box1) VALUES ('VK',9921,'EUR')");
        self::sql("INSERT INTO valuta (gruppe,valdate,kurs) VALUES (9921,'2020-01-01',745)");
        $fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/t2/open-posts-accounts.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($fixture['accounts'] as $index => $account) {
            $id = (int)self::sql("INSERT INTO adresser (art,kontonr,firmanavn,tlf,email)
                VALUES ('D',$1,$2,'12345678','test@example.invalid') RETURNING id", [(string)(8100 + $index), $account['name']])[0]['id'];
            self::$accounts[$account['name']] = ['id' => $id, 'expected' => $account['expected']];
            $invoices = [];
            foreach ($account['posts'] as $post) {
                $orderId = null;
                $voucher = (float)$post['amount'] < 0 && ifset($account, 'art', 'DO') !== 'DK';
                if (!$voucher && $post['invoice'] !== '' && !str_contains($post['invoice'], ';')) {
                    if (!isset($invoices[$post['invoice']])) {
                        $invoices[$post['invoice']] = (int)self::sql("INSERT INTO ordrer (konto_id,art,status,fakturanr,fakturadate,ordredate)
                            VALUES ($1,$2,4,$3,$4,$4) RETURNING id", [$id, ifset($account,'art','DO'), $post['invoice'], self::$transdate])[0]['id'];
                    }
                    $orderId = $invoices[$post['invoice']];
                }
                $postId = (int)self::sql("INSERT INTO openpost (konto_id,konto_nr,faktnr,refnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet,udlign_date,kladde_id)
                    VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12) RETURNING id",
                    [$id, (string)(8100 + $index), $post['invoice'], $voucher ? 100 + $index : $orderId, $post['amount'], self::$transdate,
                    self::date(-(int)$post['days']), ifset($post,'currency','DKK'), ifset($post,'rate','100'),
                    ifset($post,'settled',false) ? '1' : '0', ifset($post,'settled',false) ? self::date(-(int)ifset($post,'settled_days',1)) : null,
                    $voucher ? 991 : 0])[0]['id'];
                self::$posts[$account['name']][] = $postId;
            }
            self::$orders[$account['name']] = $invoices;
        }
        $partial = self::$accounts['Two partial payments']['id'];
        self::sql("UPDATE openpost SET faktnr=' 102 ' WHERE id=$1",[self::$posts['Two partial payments'][1]]);
        foreach ([1,2,3] as $level) {
            $rykker = (int)self::sql("INSERT INTO ordrer (konto_id,art,status,ordredate) VALUES ($1,$2,2,$3) RETURNING id",
                [$partial, 'R' . $level, self::date(-4 + $level)])[0]['id'];
            self::sql('INSERT INTO ordrelinjer (ordre_id,enhed) VALUES ($1,$2)', [$rykker, (string)self::$posts['Two partial payments'][0]]);
            if ($level === 3) {
                self::sql("INSERT INTO document_deliveries (ordre_id,document,channel,recipient,state,created_by,client_id,finished_at)
                    VALUES ($1,'rykker','email','test@example.invalid','sent','fixture',$2,now())", [$rykker,self::uuid()]);
            }
        }
        foreach ([self::$orders['Two partial payments']['102']=>'faktura', self::$orders['Dunning fee']['18-1']=>'rykker'] as $orderId => $document) {
            self::sql("INSERT INTO document_deliveries (ordre_id,document,channel,state,created_by,client_id,finished_at)
                VALUES ($1,$2,'none','sent','fixture',$3,now())",[$orderId,$document,self::uuid()]);
        }
        $other = (int)self::sql("INSERT INTO adresser (art,kontonr,firmanavn) VALUES ('K','8999','Creditor') RETURNING id")[0]['id'];
        self::$creditorPost = (int)self::sql("INSERT INTO openpost (konto_id,amount,transdate,udlignet,faktnr)
            VALUES ($1,99999,$2,'0','999') RETURNING id", [$other,self::$transdate])[0]['id'];
    }

    protected function setUp(): void
    {
        if (!self::$connection) {
            $this->markTestSkipped(RestApiEnv::unavailableReason() ?: 'Tenant unavailable');
        }
        self::sql("UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2", [str_repeat('1',32),RestApiEnv::user()]);
    }

    protected function tearDown(): void
    {
        if (self::$connection) {
            self::sql("UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2", [str_repeat('1',32),RestApiEnv::user()]);
            self::sql("DELETE FROM settings WHERE var_grp='mobile_betaling'");
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
        return RestApiEnv::rows(self::$connection,$sql,$parameters);
    }

    private static function date(int $days): string
    {
        return (new DateTimeImmutable(self::$today))->modify(sprintf('%+d days',$days))->format('Y-m-d');
    }

    private static function uuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        return substr($hex,0,8) . '-' . substr($hex,8,4) . '-4' . substr($hex,13,3) . '-a' . substr($hex,17,3) . '-' . substr($hex,20);
    }

    /** @return array<string,mixed> */
    private function get(string $query = ''): array
    {
        $response = RestApiEnv::http('GET',self::PATH . $query,null,self::$headers);
        self::assertSame(200,$response['status'],$response['body']);
        self::assertTrue($response['json']['success']);
        return $response['json']['data'];
    }

    public function testEveryFixtureReconcilesToTheWebReport(): void
    {
        foreach (self::$accounts as $name => $account) {
            $data = $this->get('?konto_id=' . $account['id']);
            $rests = array_sum(array_map('floatval',array_column($data['items'],'rest')));
            self::assertSame($account['expected'],OpenPostsReader::money($rests + (float)$data['summary']['unallocated_credit_total']),$name);
            self::assertSame($account['expected'],$data['summary']['open_total']);
            self::assertSame($account['expected'],$data['accounts'][0]['saldo']);
            $reader = openposts_reader(self::$connection);
            $posts = array_values(array_filter($reader->posts(self::$today),static fn(array $row): bool => (int)$row['konto_id'] === $account['id']));
            $aging = $this->webAging($posts);
            self::assertSame($data['summary']['open_total'],OpenPostsReader::money($aging['openY']));
        }
    }

    /** @return array<string,int|float> Raw web oracle on the same tenant, without substituting ledger rates. */
    private function webAging(array $posts): array
    {
        $saved = [];
        foreach (['connection', 'db_type', 'baseCurrency'] as $name) {
            $saved[$name] = [array_key_exists($name, $GLOBALS), ifset($GLOBALS, $name)];
        }
        $server = $_SERVER;
        $GLOBALS['connection'] = self::$connection;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['baseCurrency'] = 'DKK';
        $_SERVER['REQUEST_URI'] = '/saldi/debitor/rapport.php';
        $cache = [];
        try {
            return openpost_account_aging($posts, self::$today, self::$today, 'D', $cache);
        } finally {
            $_SERVER = $server;
            foreach ($saved as $name => [$existed, $value]) {
                if ($existed) {
                    $GLOBALS[$name] = $value;
                } else {
                    unset($GLOBALS[$name]);
                }
            }
        }
    }

    public function testSummaryBucketsAndOverviewAreTheSame(): void
    {
        $data = $this->get();
        self::assertSame('2620.00',$data['summary']['open_total']);
        self::assertSame('2620.00',OpenPostsReader::money(array_sum(array_map('floatval',$data['summary']['buckets']))));
        self::assertSame('-300.00',$data['summary']['unallocated_credit_total']);
        $overdue = (new OverviewService(self::$connection))->overdue(self::$today);
        self::assertSame($overdue['count'],$data['summary']['overdue_count']);
        self::assertSame(OpenPostsReader::money($overdue['amount']),$data['summary']['overdue_total']);
        $response = RestApiEnv::http('GET','/restapi/endpoints/v1/dashboard/overview.php',null,self::$headers);
        self::assertSame(200,$response['status'],$response['body']);
        self::assertSame($overdue['count'],$response['json']['data']['overdue']['count']);
        self::assertSame(OpenPostsReader::money($overdue['amount']),OpenPostsReader::money((float)$response['json']['data']['overdue']['amount']));
    }

    public function testDunningLevelTypesAndForeignCurrencyPayability(): void
    {
        $data = $this->get('?konto_id=' . self::$accounts['Two partial payments']['id']);
        self::assertSame(3,$data['items'][0]['dunning']['level']);
        self::assertTrue($data['items'][0]['dunning']['sent']);
        self::assertSame('600.00',$data['items'][0]['rest']);
        self::assertSame('invoice',$data['items'][0]['type']);
        self::assertSame('no_account',$data['items'][0]['payable']['reason']);
        $foreign = $this->get('?konto_id=' . self::$accounts['EUR placeholder']['id'])['items'][0];
        self::assertSame('DKK',$foreign['currency']);
        self::assertSame('EUR',$foreign['original_currency']);
        self::assertSame('745.00',$foreign['rest']);
        self::assertSame('foreign_currency',$foreign['payable']['reason']);
        self::assertFalse($foreign['payable']['register']);
        self::assertSame('credit_note',$this->get('?konto_id=' . self::$accounts['Credit note']['id'])['items'][0]['type']);
        $fee = $this->get('?konto_id=' . self::$accounts['Dunning fee']['id'])['items'][0];
        self::assertSame('dunning_fee',$fee['type']);
        self::assertFalse($fee['dunning']['sent']);
    }

    public function testStablePagingFiltersAndSummaryScope(): void
    {
        $all = $this->get('?sort=days_overdue');
        $first = $this->get('?sort=days_overdue&limit=2');
        $second = $this->get('?sort=days_overdue&limit=2&offset=2');
        self::assertSame($all['total'],$first['total']);
        self::assertSame(array_slice(array_column($all['items'],'id'),0,4),array_merge(array_column($first['items'],'id'),array_column($second['items'],'id')));
        self::assertSame($all['summary'],$second['summary']);
        $bucket = $this->get('?bucket=d31_60&status=overdue');
        self::assertSame(2,$bucket['total']);
        self::assertSame($all['summary'],$bucket['summary']);
        self::assertSame(1,$this->get('?q=partial')['total']);
        self::assertSame(1,$this->get('?q=8101')['total']);
        self::assertSame(1,$this->get('?q=18-1')['total']);
        self::assertSame(0,$this->get('?q=%25')['total']);
        self::assertSame(100,$this->get('?limit=999')['limit']);
        self::assertSame([],$this->get('?offset=9999')['items']);
        self::assertSame('not_due',$this->get('?konto_id=' . self::$accounts['Due today']['id'])['items'][0]['bucket']);
    }

    public function testDetailTimelineAndUnallocatedCredit(): void
    {
        $detail = $this->get('?id=' . self::$posts['Two partial payments'][1]);
        self::assertSame(self::$posts['Two partial payments'][0],$detail['id']);
        self::assertSame('600.00',$detail['customer']['saldo']);
        self::assertSame(['payment','payment','dunning','dunning','dunning'],array_column($detail['history'],'kind'));
        self::assertSame([1,2,3],array_values(array_column($detail['history'],'level')));
        $unallocated = $this->get('?id=' . self::$posts['Unallocated credit'][0]);
        self::assertSame('-300.00',$unallocated['unallocated_credit']);
        self::assertCount(1,$unallocated['other_open_posts']);
        self::assertSame('800.00',$unallocated['customer']['saldo']);
        self::assertArrayNotHasKey('_posts',$detail);
        $ledgerReads = 0;
        $reader = new OpenPostsReader(static function (string $sql, array $parameters) use (&$ledgerReads): array {
            if (str_contains($sql,'FROM openpost o')) {
                $ledgerReads++;
            }
            return self::sql($sql,$parameters);
        },'DKK');
        self::assertSame('600.00',$reader->detail(self::$posts['Two partial payments'][0])['rest']);
        self::assertSame(1,$ledgerReads);
    }

    public function testMissingInvalidIdsAndQueryValidation(): void
    {
        foreach (['?id=2147483647','?id=' . self::$creditorPost,'?id=' . self::$posts['Unallocated credit'][2]] as $query) {
            $response = RestApiEnv::http('GET',self::PATH . $query,null,self::$headers);
            self::assertSame(404,$response['status'],$response['body']);
            self::assertSame('not_found',$response['json']['data']['error']);
        }
        foreach (['?id=0','?id=01','?id[]=1','?limit=0','?offset=-1','?bucket=anything',
            '?sort=amount%3BDROP%20TABLE%20openpost','?status=anything','?q[]=x','?konto_id=-1'] as $query) {
            $response = RestApiEnv::http('GET',self::PATH . $query,null,self::$headers);
            self::assertSame(422,$response['status'],$response['body']);
            self::assertSame('validation_failed',$response['json']['data']['error']);
        }
    }

    public function testRightsAndReadOnlyMethod(): void
    {
        $response = RestApiEnv::http('GET',self::PATH);
        self::assertSame(401,$response['status']);
        $digits = str_repeat('0',32);
        $digits[5] = '1';
        self::sql('UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[$digits,RestApiEnv::user()]);
        $response = RestApiEnv::http('GET',self::PATH,null,self::$headers);
        self::assertSame(403,$response['status']);
        self::assertSame('permission_denied',$response['json']['data']['error']);
        $digits[12] = '2';
        self::sql('UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[$digits,RestApiEnv::user()]);
        $this->get();
        $response = RestApiEnv::http('POST',self::PATH,[],self::$headers);
        self::assertSame(405,$response['status']);
        self::assertSame('validation_failed',$response['json']['data']['error']);
    }

    public function testConfiguredRegistrationAccountDoesNotEnableUnbuiltProvider(): void
    {
        self::sql("INSERT INTO kontoplan (kontonr,kontotype,regnskabsaar,lukket) VALUES (8991,'S',9921,'')");
        self::sql("INSERT INTO settings (var_name,var_grp,var_value,user_id) VALUES ('account_bank','mobile_betaling','8991',0)");
        $item = $this->get('?konto_id=' . self::$accounts['Two partial payments']['id'])['items'][0];
        self::assertTrue($item['payable']['register']);
        self::assertFalse($item['payable']['link']);
        self::assertSame('provider_not_configured',$item['payable']['reason']);
    }

    public function testLegacySaldoFunctionsUseTheSameReaderAndRestoreGlobals(): void
    {
        $previous = ifset($GLOBALS,'connection');
        $currency = ifset($GLOBALS,'baseCurrency');
        $GLOBALS['connection'] = self::$connection;
        $GLOBALS['baseCurrency'] = 'sentinel';
        try {
            $ids = array_column(self::$accounts,'id');
            self::assertSame(array_column(self::$accounts,'expected','id'),openposts_customer_saldo($ids));
            self::assertSame('sentinel',$GLOBALS['baseCurrency']);
            self::assertSame($this->get()['summary'],openposts_summary());
            self::assertSame(1,openposts_items(['konto_id'=>self::$accounts['Two partial payments']['id']],50,0)['total']);
        } finally {
            $GLOBALS['connection'] = $previous;
            $GLOBALS['baseCurrency'] = $currency;
        }
    }

    public function testMobileBucketEdgesAndMissingDueDate(): void
    {
        $id = self::$posts['Due today'][0];
        try {
            foreach ([0=>'not_due',1=>'d0_30',30=>'d0_30',31=>'d31_60',60=>'d31_60',
                61=>'d61_90',90=>'d61_90',91=>'d90_plus'] as $days => $bucket) {
                self::sql('UPDATE openpost SET forfaldsdate=$1 WHERE id=$2',[self::date(-$days),$id]);
                $item = $this->get('?id=' . $id);
                self::assertSame($days,$item['days_overdue']);
                self::assertSame($bucket,$item['bucket']);
            }
            self::sql('UPDATE openpost SET forfaldsdate=NULL WHERE id=$1',[$id]);
            $item = $this->get('?id=' . $id);
            self::assertSame(self::$transdate,$item['due_date']);
            self::assertSame(150,$item['days_overdue']);
        } finally {
            self::sql('UPDATE openpost SET forfaldsdate=$1 WHERE id=$2',[self::$today,$id]);
        }
        $zero = $this->get('?konto_id=' . self::$accounts['Zero outstanding group']['id']);
        self::assertSame(1,$zero['total']);
        self::assertSame('0.00',$zero['items'][0]['rest']);
        self::assertFalse($zero['items'][0]['payable']['register']);
    }

    public function testSettledInvoiceRemainsReadableWithoutReappearingInTheOpenList(): void
    {
        $detail = $this->get('?id=' . self::$posts['Fully paid'][0]);
        self::assertSame('1000.00',$detail['amount']);
        self::assertSame('0.00',$detail['rest']);
        self::assertSame('0.00',$detail['customer']['saldo']);
        self::assertTrue($detail['settled']);
        self::assertSame(['payment','settled'],array_column($detail['history'],'kind'));
        self::assertSame('-1000.00',$detail['history'][0]['amount']);
        self::assertSame(0,$this->get('?konto_id=' . self::$accounts['Fully paid']['id'])['total']);
    }

    public function testCurrencyRateResolutionIsNotCachedAcrossReaders(): void
    {
        $id = self::$accounts['EUR placeholder']['id'];
        self::assertSame('745.00',openposts_reader(self::$connection)->summary($id)['open_total']);
        try {
            self::sql('UPDATE valuta SET kurs=900 WHERE gruppe=9921');
            self::assertSame('900.00',openposts_reader(self::$connection)->summary($id)['open_total']);
            self::assertSame('900.00',$this->get('?konto_id=' . $id)['summary']['open_total']);
            self::sql('UPDATE valuta SET kurs=100 WHERE gruppe=9921');
            self::assertSame('100.00',openposts_reader(self::$connection)->summary($id)['open_total']);
            self::assertSame('foreign_currency',$this->get('?konto_id=' . $id)['items'][0]['payable']['reason']);
            self::sql('UPDATE valuta SET kurs=0 WHERE gruppe=9921');
            self::assertSame('100.00',openposts_reader(self::$connection)->summary($id)['open_total']);
            self::assertSame('100.00',$this->get('?konto_id=' . $id)['summary']['open_total']);
        } finally {
            self::sql('UPDATE valuta SET kurs=745 WHERE gruppe=9921');
        }
    }

    public function testFractionalForeignGroupsReconcileWithoutInventingUnallocatedCredit(): void
    {
        $account = (int)self::sql("INSERT INTO adresser (art,kontonr,firmanavn) VALUES ('D','8992','Fractional EUR') RETURNING id")[0]['id'];
        try {
            foreach (['fraction-1','fraction-2'] as $invoice) {
                self::sql("INSERT INTO openpost (konto_id,faktnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet)
                    VALUES ($1,$2,1.005,$3,$3,'EUR',100,'0')",[$account,$invoice,self::$transdate]);
            }
            $data = $this->get('?konto_id=' . $account);
            self::assertSame('14.97',$data['summary']['open_total']);
            self::assertSame('14.97',OpenPostsReader::money(array_sum(array_map('floatval',array_column($data['items'],'rest')))));
            self::assertSame('0.00',$data['summary']['unallocated_credit_total']);
            self::assertSame('other',$data['items'][0]['type']);
            self::sql('UPDATE valuta SET kurs=100 WHERE gruppe=9921');
            $parity = $this->get('?konto_id=' . $account);
            self::assertSame('2.01',$parity['summary']['open_total']);
            self::assertSame('2.01',OpenPostsReader::money(array_sum(array_map('floatval',array_column($parity['items'],'rest')))));
            self::assertSame('2.02',$parity['summary']['overdue_total']);
        } finally {
            self::sql('UPDATE valuta SET kurs=745 WHERE gruppe=9921');
            self::sql('DELETE FROM openpost WHERE konto_id=$1',[$account]);
            self::sql('DELETE FROM adresser WHERE id=$1',[$account]);
        }
    }

    public function testFractionalForeignSaldoMatchesWebAccountDetailAndSummary(): void
    {
        $account = (int)self::sql("INSERT INTO adresser (art,kontonr,firmanavn) VALUES ('D','8995','Half cent EUR') RETURNING id")[0]['id'];
        try {
            foreach (['fraction', ''] as $invoice) {
                foreach ([1, -1] as $sign) {
                    $id = (int)self::sql("INSERT INTO openpost (konto_id,faktnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet)
                        VALUES ($1,$2,$3,$4,$4,'EUR',745,'0') RETURNING id", [$account, $invoice, $sign * 0.151, self::$transdate])[0]['id'];
                    $data = $this->get('?konto_id=' . $account);
                    $posts = self::sql('SELECT * FROM openpost WHERE konto_id=$1 ORDER BY id', [$account]);
                    $expected = number_format(afrund($this->webAging($posts)['openY'], 2), 2, '.', '');
                    self::assertSame($sign > 0 ? '1.13' : '-1.13', $expected);
                    self::assertSame($expected, $data['accounts'][0]['saldo']);
                    self::assertSame($expected, $data['summary']['open_total']);
                    self::assertSame($expected, OpenPostsReader::money(array_sum(array_map('floatval', array_column($data['items'], 'rest')))
                        + (float)$data['summary']['unallocated_credit_total']));
                    self::assertSame($expected, OpenPostsReader::money(array_sum(array_map('floatval', $data['summary']['buckets']))));
                    if ($invoice !== '') {
                        $detail = $this->get('?id=' . $id);
                        self::assertSame($expected, $detail['customer']['saldo']);
                        self::assertSame($expected, $detail['rest']);
                        self::assertSame($expected, $detail['amount']);
                    } else {
                        self::assertSame($expected, $data['accounts'][0]['unallocated_credit']);
                        self::assertSame([], $data['items']);
                    }
                    self::sql('DELETE FROM openpost WHERE id=$1', [$id]);
                }
            }
        } finally {
            self::sql('DELETE FROM openpost WHERE konto_id=$1', [$account]);
            self::sql('DELETE FROM adresser WHERE id=$1', [$account]);
        }
    }

    public function testResidualCentsPreserveZeroGroupsAndSignsWithStableAllocation(): void
    {
        $account = (int)self::sql("INSERT INTO adresser (art,kontonr,firmanavn) VALUES ('D','8996','Rounding allocation') RETURNING id")[0]['id'];
        try {
            foreach ([[1, 3, 0.135, '4.02', '1.00', '1.01', 1], [-1, 3, 0.135, '-4.02', '-1.00', '-1.01', 1],
                [1, 3, 0.136, '4.04', '1.02', '1.01', 1], [-1, 3, 0.136, '-4.04', '-1.02', '-1.01', 1],
                [1, 12, 0.135, '13.06', '1.00', '1.01', 6], [-1, 12, 0.135, '-13.06', '-1.00', '-1.01', 6]]
                as [$sign, $count, $amount, $total, $adjustedRest, $roundedRest, $adjustedCount]) {
                // Fractional groups and an exact-cent group, followed by a fully offset invoice.
                foreach (range(1, $count) as $index) {
                    self::sql("INSERT INTO openpost (konto_id,faktnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet)
                        VALUES ($1,$2,$3,$4,$4,'EUR',744.5,'0')", [$account, 'fraction-' . $index, $sign * $amount, self::$transdate]);
                }
                foreach (['exact' => $sign * 1, 'zero' => 50] as $invoice => $amount) {
                    self::sql("INSERT INTO openpost (konto_id,faktnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet)
                        VALUES ($1,$2,$3,$4,$4,'DKK',100,'0')", [$account, $invoice, $amount, self::$transdate]);
                }
                self::sql("INSERT INTO openpost (konto_id,faktnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet)
                    VALUES ($1,'zero',-50,$2,$2,'DKK',100,'0')", [$account, self::$transdate]);
                $data = $this->get('?konto_id=' . $account . '&sort=id');
                $rests = array_column($data['items'], 'rest', 'faktnr');
                self::assertSame($total, $data['summary']['open_total']);
                self::assertSame('0.00', $rests['zero']);
                self::assertSame($sign > 0 ? '1.00' : '-1.00', $rests['exact']);
                foreach (range(1, $count) as $index) {
                    self::assertSame($index <= $adjustedCount ? $adjustedRest : $roundedRest, $rests['fraction-' . $index]);
                }
                self::assertSame('0.00', $data['summary']['unallocated_credit_total']);
                self::assertSame($data['accounts'][0]['saldo'], OpenPostsReader::money(array_sum(array_map('floatval', $rests))));
                self::assertSame($data['summary']['open_total'], OpenPostsReader::money(array_sum(array_map('floatval', $data['summary']['buckets']))));
                $reversed = $this->get('?konto_id=' . $account . '&sort=rest');
                self::assertEquals($rests, array_column($reversed['items'], 'rest', 'faktnr'));
                foreach ($data['items'] as $item) {
                    self::assertSame($item['rest'], $this->get('?id=' . $item['id'])['rest']);
                    if (str_starts_with($item['faktnr'], 'fraction-')) {
                        self::assertGreaterThan(0, $sign * (float)$item['rest']);
                    }
                }
                self::sql('DELETE FROM openpost WHERE konto_id=$1', [$account]);
            }
        } finally {
            self::sql('DELETE FROM openpost WHERE konto_id=$1', [$account]);
            self::sql('DELETE FROM adresser WHERE id=$1', [$account]);
        }
    }

    public function testMixedSignedGroupsAndUnallocatedFractionsKeepTheirBalances(): void
    {
        $account = (int)self::sql("INSERT INTO adresser (art,kontonr,firmanavn) VALUES ('D','8998','Mixed fractions') RETURNING id")[0]['id'];
        try {
            foreach (['fraction-1' => 0.135, 'fraction-2' => 0.135, 'fraction-3' => 0.135,
                'tiny-credit' => -0.001, '' => 0.135, 'zero' => 0.135] as $invoice => $amount) {
                self::sql("INSERT INTO openpost (konto_id,faktnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet)
                    VALUES ($1,$2,$3,$4,$4,'EUR',744.5,'0')", [$account, $invoice, $amount, self::$transdate]);
            }
            self::sql("INSERT INTO openpost (konto_id,faktnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet)
                VALUES ($1,'zero',-0.135,$2,$2,'EUR',744.5,'0')", [$account, self::$transdate]);
            $data = $this->get('?konto_id=' . $account . '&sort=id');
            self::assertSame(['fraction-1' => '1.00', 'fraction-2' => '1.00', 'fraction-3' => '1.01',
                'tiny-credit' => '-0.01', 'zero' => '0.00'], array_column($data['items'], 'rest', 'faktnr'));
            self::assertSame('1.01', $data['summary']['unallocated_credit_total']);
            self::assertSame('1.01', $data['accounts'][0]['unallocated_credit']);
            self::assertSame('4.01', $data['summary']['open_total']);
            self::assertSame('4.01', $data['accounts'][0]['saldo']);
            $posts = self::sql('SELECT * FROM openpost WHERE konto_id=$1 ORDER BY id', [$account]);
            self::assertSame('4.01', number_format(afrund($this->webAging($posts)['openY'], 2), 2, '.', ''));
            self::assertSame('4.01', OpenPostsReader::money(array_sum(array_map('floatval', $data['summary']['buckets']))));
            // The genuine unallocated balance can also receive a cent when it has the largest error.
            self::sql('DELETE FROM openpost WHERE konto_id=$1', [$account]);
            foreach (['fraction' => 0.134, '' => 0.135] as $invoice => $amount) {
                self::sql("INSERT INTO openpost (konto_id,faktnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet)
                    VALUES ($1,$2,$3,$4,$4,'EUR',744.5,'0')", [$account, $invoice, $amount, self::$transdate]);
            }
            $data = $this->get('?konto_id=' . $account);
            self::assertSame('1.00', $data['items'][0]['rest']);
            self::assertSame('1.00', $data['accounts'][0]['unallocated_credit']);
            self::assertSame('2.00', $data['summary']['open_total']);
            self::assertSame('2.00', $data['accounts'][0]['saldo']);
        } finally {
            self::sql('DELETE FROM openpost WHERE konto_id=$1', [$account]);
            self::sql('DELETE FROM adresser WHERE id=$1', [$account]);
        }
    }

    public function testVoucherCollisionsAndMismatchedInvoiceNumbersNeverBorrowOrderMetadata(): void
    {
        $account = (int)self::sql("INSERT INTO adresser (art,kontonr,firmanavn) VALUES ('D','8997','Voucher collision') RETURNING id")[0]['id'];
        try {
            self::sql("INSERT INTO kontoplan (kontonr,kontotype,regnskabsaar,lukket) VALUES (8997,'S',9921,'')");
            self::sql("INSERT INTO settings (var_name,var_grp,var_value,user_id) VALUES ('account_bank','mobile_betaling','8997',0)");
            foreach (['DO', 'DK', 'R1'] as $art) {
                foreach ([100, -100] as $amount) {
                    $invoice = $art . '-' . $amount;
                    $order = (int)self::sql("INSERT INTO ordrer (konto_id,art,status,fakturanr,fakturadate,ordredate)
                        VALUES ($1,$2,4,$3,$4,$4) RETURNING id", [$account, $art, $invoice, self::date(-180)])[0]['id'];
                    self::sql("INSERT INTO document_deliveries (ordre_id,document,channel,state,recipient,created_by,client_id,finished_at)
                        VALUES ($1,'faktura','email','sent','unrelated@example.invalid','fixture',$2,now())", [$order, self::uuid()]);
                    $id = (int)self::sql("INSERT INTO openpost (konto_id,faktnr,refnr,kladde_id,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet)
                        VALUES ($1,$2,$3,991,$4,$5,$5,'DKK',100,'0') RETURNING id", [$account, $invoice, $order, $amount, self::$transdate])[0]['id'];
                    $detail = $this->get('?id=' . $id);
                    self::assertSame('other', $detail['type']);
                    self::assertNull($detail['ordre_id']);
                    self::assertSame(self::$transdate, $detail['invoice_date']);
                    self::assertFalse($detail['payable']['register']);
                    self::assertSame('not_invoice', $detail['payable']['reason']);
                    self::assertSame([], $detail['history']);
                    self::assertSame(0, $detail['dunning']['level']);
                    // Even a document-backed refnr needs the same invoice number.
                    self::sql('UPDATE openpost SET kladde_id=0,faktnr=$1 WHERE id=$2', ['wrong-' . $invoice, $id]);
                    $mismatch = $this->get('?id=' . $id);
                    self::assertSame('other', $mismatch['type']);
                    self::assertNull($mismatch['ordre_id']);
                    self::assertSame([], $mismatch['history']);
                    self::assertSame(0, $mismatch['dunning']['level']);
                    self::sql('UPDATE openpost SET faktnr=$1 WHERE id=$2', [$invoice, $id]);
                    $matched = $this->get('?id=' . $id);
                    self::assertSame(['DO' => 'invoice', 'DK' => 'credit_note', 'R1' => 'dunning_fee'][$art], $matched['type']);
                    self::assertSame($order, $matched['ordre_id']);
                }
            }
        } finally {
            self::sql('DELETE FROM document_deliveries WHERE ordre_id IN (SELECT id FROM ordrer WHERE konto_id=$1)', [$account]);
            self::sql('DELETE FROM openpost WHERE konto_id=$1', [$account]);
            self::sql('DELETE FROM ordrer WHERE konto_id=$1', [$account]);
            self::sql('DELETE FROM adresser WHERE id=$1', [$account]);
            self::sql("DELETE FROM kontoplan WHERE kontonr=8997 AND regnskabsaar=9921");
        }
    }

    public function testForeignRateDatesAndNegativeRowsPreserveOverviewNormalization(): void
    {
        $account = (int)self::sql("INSERT INTO adresser (art,kontonr,firmanavn) VALUES ('D','8993','Foreign rates') RETURNING id")[0]['id'];
        try {
            self::sql("INSERT INTO grupper (art,kodenr,box1) VALUES ('VK',9922,'GBP')");
            self::sql("INSERT INTO valuta (gruppe,valdate,kurs) VALUES
                (9922,$1,100),(9922,$2,850),(9922,$3,999)",
                [self::date(-160), self::date(-100), self::date(1)]);
            foreach ([null, '0', '100', '700'] as $index => $rate) {
                foreach ([self::date(-150), self::date(-90)] as $date) {
                    foreach (['1.005', '-0.005'] as $amount) {
                        self::sql("INSERT INTO openpost (konto_id,faktnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet)
                            VALUES ($1,$2,$3,$4,$4,'GBP',$5,'0')", [$account, 'rate-' . $index . '-' . $date, $amount, $date, $rate]);
                    }
                }
            }
            $data = $this->get('?konto_id=' . $account);
            $posts = self::sql('SELECT * FROM openpost WHERE konto_id=$1 ORDER BY id', [$account]);
            // Pin the web decimal-zero quirk and the intentional B8 missing-rate normalization.
            $aging = $this->webAging($posts);
            self::assertSame('33.00', OpenPostsReader::money($aging['openY']));
            foreach ($posts as &$post) {
                if ((float)$post['valutakurs'] === 0.0) {
                    $post['valutakurs'] = null;
                }
            }
            unset($post);
            $aging = $this->webAging($posts);
            self::assertSame('42.50', $data['summary']['open_total']);
            self::assertSame(OpenPostsReader::money($aging['openY']), $data['summary']['open_total']);
            self::assertSame(OpenPostsReader::money($aging['openKontrol']), $data['summary']['overdue_total']);
            self::assertSame('0.00', $data['summary']['unallocated_credit_total']);
            self::assertSame($data['summary']['open_total'], OpenPostsReader::money(array_sum(array_map('floatval', array_column($data['items'], 'rest')))));
            self::assertSame($data['summary']['open_total'], OpenPostsReader::money(array_sum(array_map('floatval', $data['summary']['buckets']))));
            foreach ($data['items'] as $item) {
                self::assertSame('foreign_currency', $item['payable']['reason']);
                $detail = $this->get('?id=' . $item['id']);
                self::assertSame($item['rest'], $detail['rest']);
                self::assertSame('GBP', $detail['original_currency']);
                self::assertSame('DKK', $detail['currency']);
            }
        } finally {
            self::sql('DELETE FROM openpost WHERE konto_id=$1', [$account]);
            self::sql('DELETE FROM adresser WHERE id=$1', [$account]);
            self::sql('DELETE FROM valuta WHERE gruppe=9922');
            self::sql("DELETE FROM grupper WHERE art='VK' AND kodenr=9922");
        }
    }

    public function testParityRatesKeepForeignPrecisionWithoutAmbientTenantQueries(): void
    {
        $post = ['id' => '1', 'konto_id' => '1', 'kontonr' => '8994', 'customer_name' => 'Reader isolation',
            'tlf' => '', 'email' => '', 'ordre_id' => null, 'fakturadate' => null, 'order_art' => null,
            'faktnr' => 'parity', 'amount' => '1.005', 'transdate' => self::$transdate,
            'forfaldsdate' => self::$transdate, 'udlignet' => '0', 'udlign_date' => null,
            'valuta' => 'USD', 'valutakurs' => '100'];
        $saved = [];
        foreach (['connection', 'baseCurrency'] as $name) {
            $saved[$name] = [array_key_exists($name, $GLOBALS), ifset($GLOBALS, $name)];
            unset($GLOBALS[$name]);
        }
        try {
            foreach ([['100', '1.01'], ['650', '6.53'], ['100', '1.01'], ['0.000000', '1.01']] as [$rate, $expected]) {
                $reader = new OpenPostsReader(static function (string $sql, array $parameters) use ($post, $rate): array {
                    return str_contains($sql, 'FROM openpost o') ? [$post] : [['kurs' => $rate]];
                }, 'DKK');
                self::assertSame($expected, $reader->summary()['open_total']);
                self::assertArrayNotHasKey('baseCurrency', $GLOBALS);
                self::assertArrayNotHasKey('connection', $GLOBALS);
            }
        } finally {
            foreach ($saved as $name => [$existed, $value]) {
                if ($existed) {
                    $GLOBALS[$name] = $value;
                } else {
                    unset($GLOBALS[$name]);
                }
            }
        }
    }

    public function testPublishedResponseFixturesMatchHttpResponses(): void
    {
        $account = self::$accounts['Two partial payments']['id'];
        // Normalize only generated identities and dates; every business field stays unchanged.
        $normalize = function (array $values) use (&$normalize): array {
            foreach ($values as $key => &$value) {
                if (is_array($value)) {
                    $value = $normalize($value);
                } elseif (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                    $days = (int)(new DateTimeImmutable(self::$today))->diff(new DateTimeImmutable($value))->format('%r%a');
                    $value = (new DateTimeImmutable('2026-10-02'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
                } elseif (in_array($key, ['konto_id', 'ordre_id', 'rykker_id'], true)) {
                    $value = ['konto_id' => 12, 'ordre_id' => 812, 'rykker_id' => 901][$key];
                } elseif ($key === 'id') {
                    $value = array_key_exists('saldo', $values) ? 12 : 4711;
                }
            }
            unset($value);
            return $values;
        };
        foreach (['list' => '?konto_id=' . $account, 'detail' => '?id=' . self::$posts['Two partial payments'][0]] as $name => $query) {
            $response = RestApiEnv::http('GET', self::PATH . $query, null, self::$headers);
            self::assertSame(200, $response['status'], $response['body']);
            $fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/t2/open-posts-' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);
            self::assertEquals($fixture, $normalize($response['json']));
        }
    }

    public function testMeAdvertisesGatedOpenPostsWithIndependentReportPermissions(): void
    {
        $digits = str_repeat('0', 32);
        self::sql('UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2', [$digits, RestApiEnv::user()]);
        $response = RestApiEnv::http('GET', '/restapi/endpoints/v1/auth/me.php', null, self::$headers);
        self::assertSame(200, $response['status'], $response['body']);
        self::assertSame('v2', $response['json']['data']['capabilities']['open_posts']);
        self::assertFalse($response['json']['data']['permissions']['debitorrapport']['read']);
        $refused = RestApiEnv::http('GET', self::PATH, null, self::$headers);
        self::assertSame(403, $refused['status']);
        self::assertSame('permission_denied', $refused['json']['data']['error']);
    }
}
