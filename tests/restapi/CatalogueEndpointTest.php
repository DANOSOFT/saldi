<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/CatalogueEndpointTest.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Cover inclusive pricing and export actual UTC catalogue responses for the mobile schema contract.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/fixtures/t2/ProductVatFixture.php';
require_once __DIR__ . '/../characterization/support/CharacterizationEnv.php';
require_once __DIR__ . '/../../includes/db_query.php';
require_once __DIR__ . '/../../includes/lager/productCatalog.php';

final class CatalogueEndpointTest extends TestCase
{
    private const ROOT = '/restapi/endpoints/v1/';
    private $tenant;
    private array $saved;
    private int $year;
    private ?string $originalUri;
    private string $originalCwd;

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        RestApiEnv::loginData();
    }

    public static function tearDownAfterClass(): void
    {
        RestApiEnv::teardownTenant();
    }

    protected function setUp(): void
    {
        $this->saved = [];
        $this->originalCwd = getcwd();
        chdir(dirname(__DIR__, 2));
        $this->originalUri = ifset($_SERVER, 'REQUEST_URI');
        $_SERVER['REQUEST_URI'] = '/saldi/index.php';
        foreach (['connection', 'db_type', 'db', 'webservice', 'db_modify_fejl', 'db_transaktion_depth'] as $key) {
            $this->saved[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
        }
        $this->tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $GLOBALS['connection'] = $this->tenant;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db'] = RestApiEnv::testDb();
        $GLOBALS['webservice'] = 1;
        $directory = dirname(__DIR__, 2) . '/temp/' . RestApiEnv::testDb();
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        mobileTenantUpdates($this->tenant);
        $this->year = (int)VatModel::resolveReferenceYear();
        RestApiEnv::rows($this->tenant, 'DELETE FROM varer');
        RestApiEnv::rows($this->tenant, 'DELETE FROM mobile_catalogue_changes');
        RestApiEnv::rows($this->tenant, "DELETE FROM settings WHERE var_grp='mobile' AND var_name='catalogue_retention_floor'");
    }

    protected function tearDown(): void
    {
        if (pg_transaction_status($this->tenant) !== PGSQL_TRANSACTION_IDLE) {
            RestApiEnv::rows($this->tenant, 'ROLLBACK');
        }
        pg_close($this->tenant);
        chdir($this->originalCwd);
        if ($this->originalUri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $this->originalUri;
        }
        foreach ($this->saved as $key => [$exists, $value]) {
            if ($exists) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }

    /** @return array Fixture product and order ids, year and expected engine flags. */
    private function fixture(string $scenario = 'taxable'): array
    {
        return ProductVatFixture::create($this->tenant, [RestApiEnv::class, 'rows'], $this->year, $scenario);
    }

    /** @return array{status:int,json:?array,body:string} */
    private function get(string $file, array $query = []): array
    {
        return RestApiEnv::http('GET', self::ROOT . $file . '?' . http_build_query($query), null, RestApiEnv::authHeaders());
    }

    /** @return array Full successful delta/snapshot response. */
    private function changes(?string $cursor = null, int $limit = 500, bool $waitForCommittedTransactions = true): array
    {
        if ($waitForCommittedTransactions) {
            $this->waitForCommittedTransactions();
        }
        $query = ['limit' => $limit];
        if ($cursor !== null) {
            $query['cursor'] = $cursor;
        }
        $response = $this->get('products/changes.php', $query);
        $this->assertSame(200, $response['status'], $response['body']);
        return $response['json']['data'];
    }

    /** Global PostgreSQL xmin can temporarily be pinned by another concurrently running slice. */
    private function waitForCommittedTransactions(): void
    {
        $target = (int)RestApiEnv::rows($this->tenant, 'SELECT COALESCE(max(txid),0) AS txid FROM mobile_catalogue_changes')[0]['txid'];
        $deadline = microtime(true) + 30;
        do {
            $watermark = (int)RestApiEnv::rows($this->tenant, 'SELECT txid_snapshot_xmin(txid_current_snapshot()) AS watermark')[0]['watermark'];
            if ($watermark > $target) {
                return;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        $this->fail('Concurrent transaction pinned catalogue xmin below ' . $target);
    }

    public function testSearchBarcodeAliasDuplicateBarcodeAndSafeQueries(): void
    {
        $fixture = $this->fixture();
        $alias = RestApiEnv::rows($this->tenant, 'SELECT varenr_alias FROM varer WHERE id=$1', [$fixture['product_id']])[0]['varenr_alias'];
        $barcode = $this->get('products/barcode.php', ['code' => $alias]);
        $this->assertSame(200, $barcode['status'], $barcode['body']);
        $this->assertSame($fixture['product_id'], $barcode['json']['data']['items'][0]['id']);
        $this->assertSame('25.00', $barcode['json']['data']['items'][0]['vat_rate']);
        $this->assertSame('DKK', $barcode['json']['data']['items'][0]['currency']);
        RestApiEnv::rows($this->tenant, "INSERT INTO varer(varenr,stregkode,beskrivelse,gruppe,salgspris,kostpris)
            SELECT 'CAT-duplicate',stregkode,'Duplicate',gruppe,20,10 FROM varer WHERE id=$1", [$fixture['product_id']]);
        $matches = $this->get('products/barcode.php', ['code' => 'BAR-7800']);
        $this->assertCount(2, $matches['json']['data']['items']);
        $search = $this->get('products/search.php', ['q' => 'VAT-*', 'limit' => 1]);
        $this->assertSame(200, $search['status'], $search['body']);
        $this->assertSame(1, $search['json']['data']['total']);
        $this->assertSame(1, $search['json']['data']['limit']);
        foreach (["' OR 1=1 --", '%', '_'] as $query) {
            $search = $this->get('products/search.php', ['q' => $query]);
            $this->assertSame(200, $search['status'], $search['body']);
            $this->assertSame([], $search['json']['data']['items']);
        }
        foreach ([['limit' => '0'], ['offset' => '-1'], ['sort' => 'price DESC;--'], ['q' => ['bad']], ['year' => '99999999']] as $query) {
            $bad = $this->get('products/search.php', $query);
            $this->assertSame(422, $bad['status'], $bad['body']);
            $this->assertSame('validation_failed', $bad['json']['data']['error']);
        }
        $missing = $this->get('products/detail.php', ['id' => 2147483647]);
        $this->assertSame(404, $missing['status']);
        $this->assertSame('not_found', $missing['json']['data']['error']);
    }

    public function testDetailStockUsesWarehousesAndWebReservationIndexes(): void
    {
        $fixture = $this->fixture();
        $id = $fixture['product_id'];
        RestApiEnv::rows($this->tenant, "DELETE FROM grupper WHERE art='LG' AND kodenr IN (7811,7812)");
        RestApiEnv::rows($this->tenant, "INSERT INTO grupper(art,kodenr,fiscal_year,beskrivelse)
            VALUES ('LG',7811,$1,'Main'),('LG',7812,$1,'Van'),('LG',7811,0,'Old name')", [$this->year]);
        RestApiEnv::rows($this->tenant, 'INSERT INTO lagerstatus(vare_id,lager,beholdning) VALUES ($1,7811,12),($1,7812,3.5)', [$id]);
        RestApiEnv::rows($this->tenant, 'UPDATE ordrelinjer SET vare_id=0 WHERE vare_id=$1', [$id]);
        foreach ([['DO', 0, 2, 0], ['DO', 1, 5, 1], ['KO', 1, 7, 2]] as [$art, $status, $quantity, $delivered]) {
            $order = RestApiEnv::rows($this->tenant, 'INSERT INTO ordrer(art,status,ordrenr) VALUES ($1,$2,7800) RETURNING id', [$art, $status])[0];
            RestApiEnv::rows($this->tenant, 'INSERT INTO ordrelinjer(ordre_id,vare_id,antal,leveret) VALUES ($1,$2,$3,$4)', [$order['id'], $id, $quantity, $delivered]);
        }
        $detail = $this->get('products/detail.php', ['id' => $id]);
        $this->assertSame(200, $detail['status'], $detail['body']);
        $item = $detail['json']['data'];
        $this->assertSame('15.500', $item['stock_total']);
        $this->assertSame([['warehouse_no' => 7811, 'name' => 'Main', 'quantity' => '12.000'],
            ['warehouse_no' => 7812, 'name' => 'Van', 'quantity' => '3.500']], $item['stock']);
        $this->assertSame(['in_quotes' => '2.000', 'in_orders' => '4.000', 'on_purchase_orders' => '5.000'], $item['reserved']);
    }

    public function testCatalogueQuoteAndPersistedLineShareVatResolver(): void
    {
        foreach (ProductVatFixture::scenarios() as $scenario) {
            $fixture = $this->fixture($scenario);
            $catalogue = $this->get('products/detail.php', ['id' => $fixture['product_id'], 'afd' => $fixture['department']]);
            $this->assertSame(200, $catalogue['status'], $catalogue['body']);
            $item = $catalogue['json']['data'];
            $this->assertSame($scenario === 'caller_exempt' ? '25.00' : number_format($fixture['expected_rate'], 2, '.', ''), $item['vat_rate'], $scenario);
            $quote = $this->runLine($fixture, true);
            $count = RestApiEnv::rows($this->tenant, 'SELECT count(*) AS count FROM ordrelinjer WHERE ordre_id=$1', [$fixture['order_id']])[0];
            $this->assertSame('0', $count['count'], 'Quote rolls back the real engine');
            $persisted = $this->runLine($fixture, false);
            $this->assertSame($quote['line'], $persisted['line'], $scenario);
            if ($scenario === 'missing_account') {
                $this->assertNull($persisted['line'], 'Preserved legacy unconfigured-SM numeric insert failure');
                $this->assertTrue($item['missing_vat_account']);
            } else {
                $this->assertSame($fixture['expected_rate'], (float)$persisted['line']['momssats'], $scenario);
                $resolved = product_vat_rate(7800 + array_search($scenario, ProductVatFixture::scenarios(), true), $this->year,
                    $fixture['department'], 25, $fixture['caller_exemption']);
                $this->assertSame($resolved['rate'], (float)$quote['line']['momssats'], $scenario);
            }
        }
    }

    /** @return array Engine result and selected persisted/rolled-back line fields. */
    private function runLine(array $fixture, bool $quote): array
    {
        $child = CharacterizationEnv::runChild(__DIR__ . '/../characterization/support/run_product_vat.php',
            [json_encode($fixture), RestApiEnv::testDb(), $quote ? 'quote' : 'persist']);
        $lines = array_values(array_filter(explode("\n", trim($child['stdout']))));
        $out = json_decode(end($lines), true);
        $this->assertIsArray($out, $child['stdout'] . $child['stderr']);
        return $out;
    }

    public function testMissingSmRatesAgreeWithQuoteAndPersistedInclusivePrices(): void
    {
        foreach ([false, true] as $department) {
            foreach (['', '0', '0.0', null] as $rate) {
                $fixture = ProductVatFixture::missingRate($this->tenant, [RestApiEnv::class, 'rows'], $this->year, $rate, $department);
                $item = $this->get('products/detail.php', ['id' => $fixture['product_id'], 'afd' => $fixture['department']]);
                $this->assertSame(200, $item['status'], $item['body']);
                $this->assertSame(number_format($fixture['expected_rate'], 2, '.', ''), $item['json']['data']['vat_rate']);
                $quote = $this->runLine($fixture, true);
                $persisted = $this->runLine($fixture, false);
                $this->assertSame($quote['line'], $persisted['line']);
                if ($rate === null) {
                    $this->assertNull($persisted['line']);
                    $this->assertTrue($item['json']['data']['missing_vat_account']);
                } else {
                    $this->assertSame($fixture['expected_rate'], (float)$persisted['line']['momssats']);
                    $this->assertSame(125.0, (float)$persisted['line']['pris']);
                    $this->assertSame($rate === '0.0' ? 125.0 : 156.25, (float)$persisted['line']['vat_price']);
                }
            }
        }
    }

    public function testCatalogueTimestampsUseConfiguredTimezoneAndPreserveNull(): void
    {
        $fixture = $this->fixture();
        $id = $fixture['product_id'];
        $database = pg_escape_identifier($this->tenant, RestApiEnv::testDb());
        $originalTimezone = RestApiEnv::rows($this->tenant, 'SHOW TimeZone')[0]['TimeZone'];
        $triggers = RestApiEnv::rows($this->tenant, "SELECT tgname FROM pg_trigger WHERE tgrelid='varer'::regclass AND NOT tgisinternal AND tgname<>'mobile_varer_change'");
        $contracts = [];
        try {
            foreach ($triggers as $trigger) {
                RestApiEnv::rows($this->tenant, 'ALTER TABLE varer DISABLE TRIGGER ' . pg_escape_identifier($this->tenant, $trigger['tgname']));
            }
            foreach (['UTC', 'Europe/Copenhagen'] as $timezone) {
                $literal = pg_escape_literal($this->tenant, $timezone);
                RestApiEnv::rows($this->tenant, "ALTER DATABASE $database SET TimeZone TO $literal");
                RestApiEnv::rows($this->tenant, "SET TimeZone TO $literal");
                foreach ([
                    ['2026-10-02 08:00:00.123456', '2026-10-02T06:00:00.123456Z'],
                    ['2026-12-02 08:00:00', '2026-12-02T07:00:00.000000Z'],
                    ['2026-10-25 01:30:00.654321', '2026-10-24T23:30:00.654321Z'],
                    ['2026-10-25 03:30:00.654321', '2026-10-25T02:30:00.654321Z'],
                    [null, null],
                ] as [$wallTime, $copenhagenInstant]) {
                    RestApiEnv::rows($this->tenant, 'UPDATE varer SET modtime=$1::timestamp WHERE id=$2', [$wallTime, $id]);
                    $expected = $wallTime === null ? null : ($timezone === 'UTC'
                        ? str_replace(' ', 'T', $wallTime) . (str_contains($wallTime, '.') ? 'Z' : '.000000Z') : $copenhagenInstant);
                    $requests = [
                        ['products-search', 'products/search.php', []],
                        ['products-barcode', 'products/barcode.php', ['code' => 'ALIAS-7800']],
                        ['products-detail', 'products/detail.php', ['id' => $id]],
                        ['products-changes', 'products/changes.php', []],
                        ['sales-products', 'debitor/sales/products.php', ['mode' => 'search']],
                        ['sales-products-barcode', 'debitor/sales/products.php', ['mode' => 'barcode', 'code' => 'ALIAS-7800']],
                        ['sales-products-changes', 'debitor/sales/products.php', ['mode' => 'changes']],
                    ];
                    $cursors = [];
                    foreach ($requests as [$schema, $file, $query]) {
                        $response = $this->get($file, $query);
                        $this->assertSame(200, $response['status'], $response['body']);
                        $data = $response['json']['data'];
                        $product = $schema === 'products-detail' ? $data : $data['items'][0];
                        $this->assertSame($expected, $product['modtime'], "$timezone $schema");
                        $contracts[] = ['schema' => $schema, 'response' => $response['json'], 'expected_modtime' => $expected];
                        if (isset($data['next_cursor'])) {
                            $cursors[] = [$schema, $file, $query, $data['next_cursor']];
                        }
                    }
                    RestApiEnv::rows($this->tenant, 'UPDATE varer SET salgspris=salgspris+1 WHERE id=$1', [$id]);
                    $this->waitForCommittedTransactions();
                    foreach ($cursors as [$schema, $file, $query, $cursor]) {
                        $response = $this->get($file, array_merge($query, ['cursor' => $cursor]));
                        $this->assertSame(200, $response['status'], $response['body']);
                        $this->assertCount(1, $response['json']['data']['items']);
                        $this->assertSame($expected, $response['json']['data']['items'][0]['modtime']);
                        $contracts[] = ['schema' => $schema, 'response' => $response['json'], 'expected_modtime' => $expected];
                    }
                }
            }
            $output = getenv('SALDI_CATALOGUE_CONTRACT_OUTPUT');
            if ($output) {
                $this->assertNotFalse(file_put_contents($output, json_encode($contracts, JSON_THROW_ON_ERROR)));
            }
        } finally {
            foreach ($triggers as $trigger) {
                RestApiEnv::rows($this->tenant, 'ALTER TABLE varer ENABLE TRIGGER ' . pg_escape_identifier($this->tenant, $trigger['tgname']));
            }
            $literal = pg_escape_literal($this->tenant, $originalTimezone);
            RestApiEnv::rows($this->tenant, "ALTER DATABASE $database SET TimeZone TO $literal");
            RestApiEnv::rows($this->tenant, "SET TimeZone TO $literal");
        }
    }

    public function testLowerTransactionThatCommitsLastIsDelivered(): void
    {
        $fixture = $this->fixture();
        $start = $this->changes()['next_cursor'];
        $older = RestApiEnv::connect(RestApiEnv::testDb());
        $newer = RestApiEnv::connect(RestApiEnv::testDb());
        try {
            RestApiEnv::rows($older, 'BEGIN');
            RestApiEnv::rows($older, "UPDATE varer SET beskrivelse='Late old transaction' WHERE id=$1", [$fixture['product_id']]);
            $oldTx = RestApiEnv::rows($older, 'SELECT txid_current() AS txid')[0]['txid'];
            RestApiEnv::rows($newer, 'BEGIN');
            $product = RestApiEnv::rows($newer, "INSERT INTO varer(varenr,beskrivelse,gruppe,salgspris,kostpris)
                VALUES ('CAT-newer','Newer commit',7800,50,10) RETURNING id")[0];
            $newTx = RestApiEnv::rows($newer, 'SELECT txid_current() AS txid')[0]['txid'];
            $this->assertLessThan((int)$newTx, (int)$oldTx);
            RestApiEnv::rows($newer, 'COMMIT');
            $blocked = $this->changes($start, 500, false);
            $this->assertSame([], $blocked['items'], 'No unsafe cursor advance over unfinished older tx');
            RestApiEnv::rows($older, 'COMMIT');
            $delivered = $this->changes($blocked['next_cursor']);
            $ids = array_column($delivered['items'], 'id');
            $this->assertContains($fixture['product_id'], $ids);
            $this->assertContains((int)$product['id'], $ids);
        } finally {
            if (pg_transaction_status($older) !== PGSQL_TRANSACTION_IDLE) {
                RestApiEnv::rows($older, 'ROLLBACK');
            }
            if (pg_transaction_status($newer) !== PGSQL_TRANSACTION_IDLE) {
                RestApiEnv::rows($newer, 'ROLLBACK');
            }
            pg_close($older);
            pg_close($newer);
        }
    }

    public function testSnapshotDeleteAndInsertBetweenPagesAreDelivered(): void
    {
        $first = $this->fixture('taxable');
        $second = $this->fixture('group_exempt');
        $snapshot = $this->changes(null, 1);
        $this->assertTrue($snapshot['has_more']);
        $this->assertSame($first['product_id'], $snapshot['items'][0]['id']);
        RestApiEnv::rows($this->tenant, 'DELETE FROM varer WHERE id=$1', [$second['product_id']]);
        $third = $this->fixture('reverse_normal');
        $next = $this->changes($snapshot['next_cursor'], 1);
        $this->assertSame($third['product_id'], $next['items'][0]['id']);
        $this->assertFalse($next['has_more']);
        $delta = $this->changes($next['next_cursor']);
        $this->assertContains($second['product_id'], $delta['deleted']);
        $this->assertContains($third['product_id'], array_column($delta['items'], 'id'));
    }

    public function testEqualTransactionPagingDeduplicatesAndResumes(): void
    {
        $fixture = $this->fixture();
        $cursor = $this->changes()['next_cursor'];
        RestApiEnv::rows($this->tenant, 'BEGIN');
        for ($index = 0; $index < 3; $index++) {
            RestApiEnv::rows($this->tenant, 'UPDATE varer SET salgspris=salgspris+1 WHERE id=$1', [$fixture['product_id']]);
        }
        RestApiEnv::rows($this->tenant, 'COMMIT');
        $first = $this->changes($cursor, 2);
        $this->assertTrue($first['has_more'], json_encode($first) . json_encode(RestApiEnv::rows($this->tenant, 'SELECT txid,id FROM mobile_catalogue_changes ORDER BY txid,id')));
        $this->assertCount(1, $first['items']);
        $this->assertSame('103.00', $first['items'][0]['price']);
        $second = $this->changes($first['next_cursor'], 2);
        $this->assertFalse($second['has_more']);
        $this->assertCount(1, $second['items']);
        $empty = $this->changes($second['next_cursor']);
        $this->assertSame([], $empty['items']);
    }

    public function testFeedDoesNotDependOnLegacyModtimeTrigger(): void
    {
        $fixture = $this->fixture();
        $cursor = $this->changes()['next_cursor'];
        $triggers = RestApiEnv::rows($this->tenant, "SELECT tgname FROM pg_trigger WHERE tgrelid='varer'::regclass AND NOT tgisinternal AND tgname<>'mobile_varer_change'");
        foreach ($triggers as $trigger) {
            RestApiEnv::rows($this->tenant, 'DROP TRIGGER ' . pg_escape_identifier($this->tenant, $trigger['tgname']) . ' ON varer');
        }
        $before = RestApiEnv::rows($this->tenant, 'SELECT modtime FROM varer WHERE id=$1', [$fixture['product_id']])[0]['modtime'];
        RestApiEnv::rows($this->tenant, "UPDATE varer SET beskrivelse='Without legacy trigger' WHERE id=$1", [$fixture['product_id']]);
        $after = RestApiEnv::rows($this->tenant, 'SELECT modtime FROM varer WHERE id=$1', [$fixture['product_id']])[0]['modtime'];
        $this->assertSame($before, $after);
        $delta = $this->changes($cursor);
        $this->assertSame('Without legacy trigger', $delta['items'][0]['name']);
    }

    public function testOldAndPrunedCursorsReturnGoneAndTamperingIsRejected(): void
    {
        $fixture = $this->fixture();
        $cursor = $this->changes()['next_cursor'];
        $old = $this->cursorWith($cursor, ['issued_at' => (string)(time() - 91 * 86400)]);
        $response = $this->get('products/changes.php', ['cursor' => $old]);
        $this->assertSame(410, $response['status'], $response['body']);
        $this->assertSame('gone', $response['json']['data']['error']);
        $tampered = $this->get('products/changes.php', ['cursor' => $cursor . '0']);
        $this->assertSame(422, $tampered['status']);
        RestApiEnv::rows($this->tenant, 'UPDATE varer SET salgspris=salgspris+1 WHERE id=$1', [$fixture['product_id']]);
        RestApiEnv::rows($this->tenant, "UPDATE mobile_catalogue_changes SET changed_at=now()-interval '91 days'");
        $this->waitForCommittedTransactions();
        $pruned = $this->get('products/changes.php', ['cursor' => $cursor]);
        $this->assertSame(410, $pruned['status'], $pruned['body']);
        $remaining = RestApiEnv::rows($this->tenant, 'SELECT count(*) AS count FROM mobile_catalogue_changes')[0]['count'];
        $this->assertSame('0', $remaining);
        $snapshot = $this->changes();
        $this->assertSame($fixture['product_id'], $snapshot['items'][0]['id']);
    }

    private function cursorWith(string $cursor, array $changes): string
    {
        $payload = explode('.', $cursor)[0];
        $state = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $payload = rtrim(strtr(base64_encode(json_encode(array_replace($state, $changes))), '+/', '-_'), '=');
        return $payload . '.' . hash_hmac('sha256', 'catalogue:' . $payload, _jwtLoadSecret());
    }

    public function testSellerRightsAndProjectionAreRestrictedOnEveryMode(): void
    {
        $fixture = $this->fixture();
        $rights = str_repeat('0', 16);
        $rights[5] = '2';
        $password = TestCredentials::password('catalogue_seller');
        $user = RestApiEnv::rows($this->tenant, "INSERT INTO brugere(brugernavn,kode,rettigheder,status)
            VALUES ('catalogue_seller',$1,$2,true) RETURNING id", [md5($password), $rights])[0];
        try {
            $login = RestApiEnv::login('catalogue_seller', $password, RestApiEnv::accountOpen());
            $this->assertSame(200, $login['status'], $login['body']);
            $headers = ['Authorization: Bearer ' . $login['json']['data']['access_token']];
            foreach (['search', 'barcode', 'changes'] as $mode) {
                $response = RestApiEnv::http('GET', self::ROOT . 'debitor/sales/products.php?' . http_build_query(['mode' => $mode, 'code' => 'ALIAS-7800']), null, $headers);
                $this->assertSame(200, $response['status'], $response['body']);
                $this->assertNotEmpty($response['json']['data']['items']);
                foreach ($response['json']['data']['items'] as $item) {
                    foreach (['cost_price', 'kostpris', 'productCost', 'campaign_cost', 'tier_price'] as $field) {
                        $this->assertArrayNotHasKey($field, $item);
                    }
                    $this->assertSame($fixture['product_id'], $item['id']);
                }
            }
            foreach (['search', 'barcode', 'changes', 'detail'] as $mode) {
                $denied = RestApiEnv::http('GET', self::ROOT . "products/$mode.php", null, $headers);
                $this->assertSame(403, $denied['status'], $denied['body']);
                $this->assertSame('permission_denied', $denied['json']['data']['error']);
            }
            $fullCursor = $this->changes()['next_cursor'];
            $mismatch = RestApiEnv::http('GET', self::ROOT . 'debitor/sales/products.php?' . http_build_query(['mode' => 'changes', 'cursor' => $fullCursor]), null, $headers);
            $this->assertSame(422, $mismatch['status']);
        } finally {
            RestApiEnv::rows($this->tenant, 'DELETE FROM brugere WHERE id=$1', [$user['id']]);
        }
        foreach (['products/search.php', 'products/barcode.php', 'products/changes.php', 'products/detail.php', 'debitor/sales/products.php'] as $file) {
            $unauthorized = RestApiEnv::http('GET', self::ROOT . $file);
            $this->assertSame(401, $unauthorized['status'], $unauthorized['body']);
            $method = RestApiEnv::http('POST', self::ROOT . $file, [], RestApiEnv::authHeaders());
            $this->assertSame(405, $method['status'], $method['body']);
            $this->assertSame('validation_failed', $method['json']['data']['error']);
        }
    }
}
