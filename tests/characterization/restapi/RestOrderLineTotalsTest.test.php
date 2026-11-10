<?php
// tests/characterization/restapi/RestOrderLineTotalsTest.test.php
//
// Regression test for the testlab finding api-bookkeeper (round 2): an order
// line added through the REST API (POST debitor/orderlines/) left the order
// header at sum 0 / moms 0. GET orders/?id, SQL and the Tilbud list showed 0
// while the order screen showed Nettosum 377,01 / Moms 94,25, and the first
// look at the order in debitor/ordre.php wrote 377.010 / 94.250 / 117.360.
// OrderLineService::createOrderLine() inserted the line and never stored the
// header's totals; only the order screens recompute and store them when they
// draw the order.
//
// Each test adds lines through the service (support/run_rest_order_line.php,
// which connects as the REST endpoints do) and checks the header. Then it
// clears the header and draws the order on its screen, and the screen must
// store the same totals again: debitor/ordre.php for a sales order
// (support/run_debitor_order_page.php), the POS screen's line list for a POS
// order (support/run_pos_order_lines.php), kreditor/ordre.php for a purchase
// order (support/run_kreditor_order_page.php). The sales cases cover what the
// screen adds beyond the lines' own amounts: a stored quantity discount
// (m_rabat), a følgevare, a set's rounding, the order's procenttillæg, and
// the VAT of a customer whose prices include VAT next to one whose prices do
// not. The POS cases cover a quantity discount, a følgevare and a
// rabatgruppe's discount on a plain line and on a line of a set, and a
// følgevare's VAT in an open book that no fiscal year covers today, where the
// API works in the newest open year as the POS screen does. A cost price
// times a fractional quantity is stored as the order screen stores it,
// rounded by the column. A last test adds a line while another request holds
// the order with its own line not yet committed: the totals must count both
// lines.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
//                Review round 2: the private customer's VAT is compared with the order screen too, and a POS rabatgruppe on a plain line and on a line of a set.
// 20261110 CL/LH Review round 3: a POS følgevare's VAT in a book with no fiscal year covering today, and the cost of a fractional quantity (5.145 x 31.031).

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class RestOrderLineTotalsTest extends CharacterizationTestCase
{
    /** @var array{id:int, kontonr:string} */
    private array $debtor;
    /** @var array{id:int, kontonr:string} */
    private array $creditor;
    /** @var array{id:int, varenr:string, salgspris:float, kostpris:float, gruppe:int} the lab's item */
    private array $item;
    /** @var array{id:int, varenr:string, salgspris:float, kostpris:float, gruppe:int} */
    private array $second;
    /** ordrer.id before the test: everything above it is the test's own */
    private int $lastOrderId = 0;
    /** @var list<int> items a test made for itself */
    private array $extraItems = [];
    /** @var list<int> debtors a test made for itself */
    private array $extraDebtors = [];
    /** @var list<array<string, mixed>> settings rows a test replaced, put back in tearDown() */
    private array $savedSettings = [];
    /** @var list<string> setting names a test set */
    private array $setSettings = [];
    /** @var list<int> grupper ids of the discount groups a test made */
    private array $discountGroups = [];
    /** @var array<int, array{box2:string, box4:string}> first and last year of the fiscal years a test moved, by grupper id */
    private array $movedFiscalYears = [];

    protected function setUp(): void
    {
        $this->debtor = self::$fx->debtor();
        $this->creditor = self::$fx->creditor();
        $this->item = self::$fx->item(['salgspris' => 125.67, 'kostpris' => 39.12]);
        $this->second = self::$fx->item(['salgspris' => 49.95, 'kostpris' => 20.00]);
        $max = self::one('SELECT COALESCE(max(id), 0) AS id FROM ordrer');
        $this->lastOrderId = (int)$max['id'];
    }

    protected function tearDown(): void
    {
        foreach ($this->movedFiscalYears as $id => $years) {
            self::rows('UPDATE grupper SET box2 = $1, box4 = $2 WHERE id = $3', [$years['box2'], $years['box4'], $id]);
        }
        self::rows('DELETE FROM ordrelinjer WHERE ordre_id > $1', [$this->lastOrderId]);
        self::rows('DELETE FROM ordrer WHERE id > $1', [$this->lastOrderId]);
        $items = array_merge([$this->item['id'], $this->second['id']], $this->extraItems);
        self::rows('DELETE FROM varer WHERE id = ANY($1::int[])', ['{' . implode(',', $items) . '}']);
        $accounts = array_merge([$this->debtor['id'], $this->creditor['id']], $this->extraDebtors);
        self::rows('DELETE FROM adresser WHERE id = ANY($1::int[])', ['{' . implode(',', $accounts) . '}']);
        if ($this->discountGroups) {
            self::rows('DELETE FROM grupper WHERE id = ANY($1::int[])', ['{' . implode(',', $this->discountGroups) . '}']);
        }
        foreach ($this->setSettings as $name) {
            self::rows("DELETE FROM settings WHERE var_name = $1 AND var_grp = 'ordre'", [$name]);
        }
        foreach ($this->savedSettings as $row) {
            self::rows('INSERT INTO settings (id, var_name, var_grp, var_value, var_description, user_id) VALUES ($1, $2, $3, $4, $5, $6)',
                [$row['id'], $row['var_name'], $row['var_grp'], $row['var_value'], $row['var_description'], $row['user_id']]);
        }
    }

    /** An item of its own for one test, removed in tearDown(). */
    private function extraItem(float $salgspris, float $kostpris): array
    {
        $item = self::$fx->item(['salgspris' => $salgspris, 'kostpris' => $kostpris]);
        $this->extraItems[] = $item['id'];
        return $item;
    }

    /**
     * A line the order screen stored before the API adds one: the item at its price, quantity 1, the order's 25 % VAT,
     * with $columns (posnr, m_rabat, folgevare, saet, samlevare, vat_price, ...) on top.
     *
     * @param array{id:int, varenr:string, salgspris:float, kostpris:float} $item
     * @param array<string, int|float|string> $columns
     */
    private function storedLine(int $orderId, array $item, array $columns): void
    {
        $line = $columns + [
            'ordre_id' => $orderId, 'vare_id' => $item['id'], 'varenr' => $item['varenr'], 'beskrivelse' => 'stored line',
            'enhed' => 'stk', 'antal' => 1, 'pris' => $item['salgspris'], 'kostpris' => $item['kostpris'], 'rabat' => 0,
            'momssats' => 25, 'procent' => 100, 'momsfri' => '', 'rabatgruppe' => 0, 'm_rabat' => 0, 'folgevare' => 0,
        ];
        $placeholders = array_map(static fn(int $n): string => '$' . $n, range(1, count($line)));
        self::rows('INSERT INTO ordrelinjer (' . implode(', ', array_keys($line)) . ') VALUES (' . implode(', ', $placeholders) . ')',
            array_values($line));
    }

    /**
     * A discount group (grupper art 'VRG', what an item's rabatgruppe points at) giving $percent % from one piece,
     * removed in tearDown().
     *
     * @return int its kodenr
     */
    private function discountGroup(float $percent): int
    {
        $max = self::one("SELECT COALESCE(max(kodenr), 0) AS kodenr FROM grupper WHERE art = 'VRG'");
        $kodenr = max(9700, (int)$max['kodenr'] + 1);
        $row = self::one("INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, fiscal_year) VALUES ('RestOrderLineTotalsTest', $1, 'VRG', 'percent', $2, '1', $3) RETURNING id",
            [$kodenr, (string)$percent, self::$fx->regnaar()]);
        $this->discountGroups[] = (int)$row['id'];
        return $kodenr;
    }

    /**
     * Moves every fiscal year (grupper art 'RA', box2 the first year, box4 the last) back so the newest ends the year
     * before today's, until tearDown(): an open book that no fiscal year covers today, as before the new year is made.
     */
    private function moveFiscalYearsBeforeToday(): void
    {
        $rows = self::rows("SELECT id, box2, box4 FROM grupper WHERE art = 'RA'");
        $years = (int)date('Y') - 1 - max(array_map(static fn(array $row): int => (int)$row['box4'], $rows));
        foreach ($rows as $row) {
            $this->movedFiscalYears[(int)$row['id']] = ['box2' => (string)$row['box2'], 'box4' => (string)$row['box4']];
            self::rows('UPDATE grupper SET box2 = $1, box4 = $2 WHERE id = $3',
                [(string)((int)$row['box2'] + $years), (string)((int)$row['box4'] + $years), $row['id']]);
        }
    }

    /** Sets the 'ordre' setting $name (vatPrivateCustomers, vatBusinessCustomers) to $value until tearDown(). */
    private function setting(string $name, string $value): void
    {
        $this->savedSettings = array_merge($this->savedSettings,
            self::rows("SELECT id, var_name, var_grp, var_value, var_description, user_id FROM settings WHERE var_name = $1 AND var_grp = 'ordre'", [$name]));
        self::rows("DELETE FROM settings WHERE var_name = $1 AND var_grp = 'ordre'", [$name]);
        self::rows("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ($1, 'ordre', $2, 'RestOrderLineTotalsTest')", [$name, $value]);
        $this->setSettings[] = $name;
    }

    /**
     * POST debitor/orderlines/ (or creditor/orderlines/ for $orderArts 'KO,KK') with $body.
     *
     * @param array<string, mixed> $body
     */
    private function addLine(array $body, string $orderArts = 'DO,DK,PO'): void
    {
        $res = $this->runChildJson('run_rest_order_line.php', [CharacterizationEnv::testDb(), json_encode($body), $orderArts]);
        $this->assertTrue($res['success'], 'the line was not created: ' . json_encode($res));
    }

    /** @return array{sum:string, moms:string, kostpris:string|null} the order header's totals as stored */
    private function totals(int $orderId): array
    {
        return self::one('SELECT sum, moms, kostpris FROM ordrer WHERE id = $1', [$orderId]);
    }

    /**
     * Clears the header's totals and opens the order on its screen, which stores them again from the lines; the screen
     * must store what the API stored.
     *
     * @param array{sum:string, moms:string, kostpris:string|null} $stored the totals the API stored
     */
    private function assertTheScreenStoresTheSameTotals(int $orderId, array $stored, bool $purchase = false): void
    {
        self::rows('UPDATE ordrer SET sum = 0, moms = 0 WHERE id = $1', [$orderId]);
        if ($purchase) {
            $this->runChildJson('run_kreditor_order_page.php', ['ordre.php', "id=$orderId", '', CharacterizationEnv::SESSION_ID]);
        } else {
            self::rows('UPDATE ordrer SET kostpris = 0 WHERE id = $1', [$orderId]);
            $this->runChildJson('run_debitor_order_page.php', ["id=$orderId", CharacterizationEnv::SESSION_ID]);
        }
        $this->assertSame($stored, $this->totals($orderId), 'the order screen stores other totals than the API');
    }

    /**
     * Clears the header's totals of POS order $orderId and draws its lines on the POS screen, which stores them again;
     * the screen must store what the API stored.
     *
     * @param array{sum:string, moms:string, kostpris:string|null} $stored the totals the API stored
     */
    private function assertThePosScreenStoresTheSameTotals(int $orderId, array $stored): void
    {
        self::rows('UPDATE ordrer SET sum = 0, moms = 0 WHERE id = $1', [$orderId]);
        $this->runChildJson('run_pos_order_lines.php', [CharacterizationEnv::testDb(), $orderId]);
        $this->assertSame($stored, $this->totals($orderId), 'the POS screen stores other totals than the API');
    }

    /** An offer (status 0) with no lines, as POST debitor/orders/ creates it. */
    private function emptyOffer(): int
    {
        return self::$fx->order($this->debtor, [], ['status' => 0, 'momssats' => 25])['id'];
    }

    public function test_a_line_added_through_the_api_stores_the_totals_the_order_screen_shows(): void
    {
        $orderId = $this->emptyOffer();

        $this->addLine(['orderId' => $orderId, 'productId' => $this->item['id'], 'quantity' => 3, 'price' => 125.67, 'description' => 'AI-API-BK-LINE']);

        $this->assertSame(['sum' => '377.010', 'moms' => '94.250', 'kostpris' => '117.360'], $this->totals($orderId),
            'the header keeps the totals from before the line');
        $this->assertTheScreenStoresTheSameTotals($orderId, $this->totals($orderId));
    }

    public function test_a_discounted_line_without_vat_is_added_to_the_totals_as_the_order_screen_adds_it(): void
    {
        $orderId = $this->emptyOffer();

        $this->addLine(['orderId' => $orderId, 'productId' => $this->item['id'], 'quantity' => 3, 'price' => 125.67]);
        $this->addLine(['orderId' => $orderId, 'productId' => $this->second['id'], 'quantity' => 2, 'price' => 49.95, 'discount' => 10, 'vatFree' => true]);

        // 377.01 + 2 x (49.95 - 10 %) = 466.92; VAT only on the first line; cost 3 x 39.12 + 2 x 20.00
        $this->assertSame(['sum' => '466.920', 'moms' => '94.250', 'kostpris' => '157.360'], $this->totals($orderId));
        $this->assertTheScreenStoresTheSameTotals($orderId, $this->totals($orderId));
    }

    public function test_the_cost_of_a_fractional_quantity_is_stored_as_the_order_screen_stores_it(): void
    {
        $orderId = $this->emptyOffer();
        $item = $this->extraItem(50, 31.031);

        $this->addLine(['orderId' => $orderId, 'productId' => $item['id'], 'quantity' => 5.145, 'price' => 50]);

        // 5.145 x 31.031 = 159.654495: the screen writes it as it is and the column stores 159.654; afrund() made it
        // 159.655. 5.145 x 50 = 257.25 with 25 % VAT, 64.3125, to øre.
        $this->assertSame(['sum' => '257.250', 'moms' => '64.310', 'kostpris' => '159.654'], $this->totals($orderId));
        $this->assertTheScreenStoresTheSameTotals($orderId, $this->totals($orderId));
    }

    public function test_a_line_added_to_a_purchase_order_stores_the_totals_the_purchase_order_screen_shows(): void
    {
        $order = self::$fx->creditorOrder($this->creditor, [], ['momssats' => 25]);
        $orderId = $order['id'];

        $this->addLine(['orderId' => $orderId, 'productId' => $this->item['id'], 'quantity' => 4, 'price' => 33.333], 'KO,KK');

        // The purchase screen rounds a line to øre (133.33) and the VAT to 3 decimals (33.333), and keeps no cost price
        $this->assertSame(['sum' => '133.330', 'moms' => '33.333', 'kostpris' => null], $this->totals($orderId));
        $this->assertTheScreenStoresTheSameTotals($orderId, $this->totals($orderId), true);
    }

    public function test_a_quantity_discount_stored_on_a_line_is_taken_off_as_the_order_screen_takes_it_off(): void
    {
        $orderId = $this->emptyOffer();
        $hundred = $this->extraItem(100, 60);
        $this->storedLine($orderId, $hundred, ['posnr' => 1, 'm_rabat' => 10]);

        $this->addLine(['orderId' => $orderId, 'productId' => $hundred['id'], 'quantity' => 1, 'price' => 100]);

        // 100 less the stored quantity discount of 10, plus the new 100, at 25 %
        $this->assertSame(['sum' => '190.000', 'moms' => '47.500', 'kostpris' => '120.000'], $this->totals($orderId));
        $this->assertTheScreenStoresTheSameTotals($orderId, $this->totals($orderId));
    }

    public function test_the_orders_procenttillaeg_is_added_as_the_order_screen_adds_it(): void
    {
        $orderId = $this->emptyOffer();
        self::rows('UPDATE ordrer SET procenttillag = 5 WHERE id = $1', [$orderId]);
        $hundred = $this->extraItem(100, 60);

        $this->addLine(['orderId' => $orderId, 'productId' => $hundred['id'], 'quantity' => 1, 'price' => 100]);

        // 100 plus 5 % procenttillæg, VAT on both
        $this->assertSame(['sum' => '105.000', 'moms' => '26.250', 'kostpris' => '60.000'], $this->totals($orderId));
        $this->assertTheScreenStoresTheSameTotals($orderId, $this->totals($orderId));
    }

    public function test_the_vat_with_and_without_prices_including_vat_is_what_the_order_screen_stores(): void
    {
        self::rows("UPDATE adresser SET kontotype = 'erhverv' WHERE id = $1", [$this->debtor['id']]);
        $this->setting('vatBusinessCustomers', 'on');
        $this->setting('vatPrivateCustomers', '');
        $business = $this->emptyOffer();
        $privateDebtor = self::$fx->debtor();
        $this->extraDebtors[] = $privateDebtor['id'];
        $private = self::$fx->order($privateDebtor, [], ['status' => 0, 'momssats' => 25])['id'];
        $item = $this->extraItem(125.01, 0);

        for ($i = 0; $i < 5; $i++) {
            $this->addLine(['orderId' => $business, 'productId' => $item['id'], 'quantity' => 1, 'price' => 125.01]);
            $this->addLine(['orderId' => $private, 'productId' => $item['id'], 'quantity' => 1, 'price' => 125.01]);
        }

        // Prices with VAT: each line's VAT to 3 decimals (31.253) and their total to øre, 156.27
        $this->assertSame(['sum' => '625.050', 'moms' => '156.270', 'kostpris' => '0.000'], $this->totals($business));
        $this->assertTheScreenStoresTheSameTotals($business, $this->totals($business));
        // Prices without VAT (vatPrivateCustomers is off): the VAT of the net, 156.2625, to øre, as the order screen
        // and the invoice (momsupdat()) compute it; opening the order without saving stored the lines' 156.27
        $this->assertSame(['sum' => '625.050', 'moms' => '156.260', 'kostpris' => '0.000'], $this->totals($private));
        $this->assertTheScreenStoresTheSameTotals($private, $this->totals($private));
    }

    public function test_a_folgevare_is_added_as_the_order_screen_adds_it(): void
    {
        $orderId = $this->emptyOffer();
        $follow = $this->extraItem(20, 5);
        $main = $this->extraItem(100, 60);
        $this->storedLine($orderId, $main, ['posnr' => 1, 'antal' => 2, 'folgevare' => $follow['id']]);

        $this->addLine(['orderId' => $orderId, 'productId' => $this->item['id'], 'quantity' => 1, 'price' => 125.67]);

        // 2 x 100, the følgevare 2 x 20 at its item price, and 125.67; the følgevare has no cost price on the screen
        $this->assertSame(['sum' => '365.670', 'moms' => '91.420', 'kostpris' => '159.120'], $this->totals($orderId));
        $this->assertTheScreenStoresTheSameTotals($orderId, $this->totals($orderId));
    }

    public function test_a_sets_rounding_is_kept_as_the_order_screen_keeps_it(): void
    {
        $orderId = $this->emptyOffer();
        $head = $this->extraItem(175, 0);
        $table = $this->extraItem(100, 60);
        $chair = $this->extraItem(39.99, 15);
        $this->storedLine($orderId, $head, ['posnr' => 1, 'pris' => 0, 'saet' => 1, 'samlevare' => 'on']);
        $this->storedLine($orderId, $table, ['posnr' => 1, 'saet' => 1]);
        $this->storedLine($orderId, $chair, ['posnr' => 2, 'saet' => 1]);

        $this->addLine(['orderId' => $orderId, 'productId' => $this->item['id'], 'quantity' => 1, 'price' => 125.67]);

        // The set (100 + 39.99 net, 174.988 with VAT) shows at 175.00, so its VAT is 35.01; the new line adds 125.67 and 31.42
        $this->assertSame(['sum' => '265.660', 'moms' => '66.430', 'kostpris' => '114.120'], $this->totals($orderId));
        $this->assertTheScreenStoresTheSameTotals($orderId, $this->totals($orderId));
    }

    public function test_a_line_added_to_a_pos_order_stores_the_totals_the_pos_screen_shows(): void
    {
        $orderId = self::$fx->order($this->debtor, [], ['art' => 'PO', 'status' => 0, 'momssats' => 25])['id'];

        $this->addLine(['orderId' => $orderId, 'productId' => $this->item['id'], 'quantity' => 4, 'price' => 33.333]);

        // The POS screen rounds the net unit price to øre (4 x 33.33) and takes the gross from vat_price (4 x 41.666),
        // and it keeps no cost price
        $this->assertSame(['sum' => '133.320', 'moms' => '33.340', 'kostpris' => null], $this->totals($orderId));
        $this->assertThePosScreenStoresTheSameTotals($orderId, $this->totals($orderId));
    }

    public function test_a_pos_orders_quantity_discount_and_folgevare_count_as_the_pos_screen_counts_them(): void
    {
        $orderId = self::$fx->order($this->debtor, [], ['art' => 'PO', 'status' => 0, 'momssats' => 25])['id'];
        $follow = $this->extraItem(20, 5);
        $main = $this->extraItem(100, 60);
        $this->storedLine($orderId, $main, ['posnr' => 1, 'antal' => 2, 'vat_price' => 125, 'm_rabat' => 10]);
        $this->storedLine($orderId, $main, ['posnr' => 2, 'vat_price' => 125, 'folgevare' => $follow['id']]);

        $this->addLine(['orderId' => $orderId, 'productId' => $this->item['id'], 'quantity' => 3, 'price' => 125.67]);

        $this->assertNotSame('0.000', $this->totals($orderId)['sum'], 'the header keeps the totals from before the line');
        $this->assertThePosScreenStoresTheSameTotals($orderId, $this->totals($orderId));
    }

    /** @return array<string, array{bool, array{sum:string, moms:string, kostpris:null}}> */
    public static function posDiscountGroupLines(): array
    {
        return [
            // 10 % of the gross 125 comes off: 250 - 12.50
            'a plain line' => [false, ['sum' => '200.000', 'moms' => '37.500', 'kostpris' => null]],
            // productLines.php draws the set's line at 0 before it takes the group's 10 %, so nothing comes off
            'a line of a set' => [true, ['sum' => '200.000', 'moms' => '50.000', 'kostpris' => null]],
        ];
    }

    /**
     * A POS order with a line of 100 (125 with VAT) in a 10 % rabatgruppe, alone or as the item of a set whose head
     * line has price 0, gets a line of 100 through the API.
     *
     * @param array{sum:string, moms:string, kostpris:null} $expected
     */
    #[DataProvider('posDiscountGroupLines')]
    public function test_a_pos_rabatgruppe_discount_counts_as_the_pos_screen_counts_it(bool $inASet, array $expected): void
    {
        $orderId = self::$fx->order($this->debtor, [], ['art' => 'PO', 'status' => 0, 'momssats' => 25])['id'];
        $group = $this->discountGroup(10);
        $hundred = $this->extraItem(100, 60);
        $line = ['posnr' => 2, 'vat_price' => 125, 'rabatgruppe' => $group];
        if ($inASet) {
            $head = $this->extraItem(0, 0);
            $this->storedLine($orderId, $head, ['posnr' => 1, 'pris' => 0, 'kostpris' => 0, 'saet' => 1, 'samlevare' => 'on', 'lev_varenr' => '125|0']);
            $line['saet'] = 1;
        }
        $this->storedLine($orderId, $hundred, $line);

        $this->addLine(['orderId' => $orderId, 'productId' => $hundred['id'], 'quantity' => 1, 'price' => 100]);

        $this->assertSame($expected, $this->totals($orderId));
        $this->assertThePosScreenStoresTheSameTotals($orderId, $this->totals($orderId));
    }

    public function test_a_pos_folgevare_has_its_vat_in_a_book_no_fiscal_year_covers_today(): void
    {
        $this->moveFiscalYearsBeforeToday();
        $orderId = self::$fx->order($this->debtor, [], ['art' => 'PO', 'status' => 0, 'momssats' => 25])['id'];
        $follow = $this->extraItem(20, 5);
        $main = $this->extraItem(100, 60);
        $this->storedLine($orderId, $main, ['posnr' => 1, 'vat_price' => 125, 'folgevare' => $follow['id']]);

        $this->addLine(['orderId' => $orderId, 'description' => 'free text', 'quantity' => 1, 'price' => 100]);

        // 100 (125 with VAT), its følgevare 20 with its group's 25 % VAT, and the free-text 100 without VAT. The API
        // took the follow item's group from no fiscal year and counted it at 20: moms 45.
        $this->assertSame(['sum' => '200.000', 'moms' => '50.000', 'kostpris' => null], $this->totals($orderId));
        $this->assertThePosScreenStoresTheSameTotals($orderId, $this->totals($orderId));
    }

    public function test_a_line_added_while_another_request_holds_the_order_counts_that_requests_line_too(): void
    {
        $orderId = $this->emptyOffer();
        $observer = CharacterizationEnv::connect(CharacterizationEnv::testDb());

        // Another request is between its own line and the totals that count it: the order is locked, its line and
        // header are written but not committed.
        pg_query(self::$tenant, 'BEGIN');
        self::rows('SELECT id FROM ordrer WHERE id = $1 FOR UPDATE', [$orderId]);
        self::rows(
            "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, posnr, antal, pris, kostpris, rabat, momssats, procent, momsfri)
             VALUES ($1, $2, $3, 'the other request', 1, 2, 49.95, 20, 0, 25, 100, '0')",
            [$orderId, $this->second['id'], $this->second['varenr']]
        );
        self::rows('UPDATE ordrer SET sum = 99.9, moms = 24.98, kostpris = 40 WHERE id = $1', [$orderId]);

        $body = json_encode(['orderId' => $orderId, 'productId' => $this->item['id'], 'quantity' => 3, 'price' => 125.67]);
        $child = proc_open([PHP_BINARY, __DIR__ . '/../support/run_rest_order_line.php', CharacterizationEnv::testDb(), $body, 'DO,DK,PO'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($child, 'could not start the line request');
        try {
            // Wait until this request waits for the other one's lock, then let the other one commit
            $waiting = false;
            for ($i = 0; $i < 150 && !$waiting && proc_get_status($child)['running']; $i++) {
                usleep(100000);
                $row = CharacterizationEnv::one($observer,
                    "SELECT count(*) AS n FROM pg_stat_activity WHERE datname = $1 AND wait_event_type = 'Lock'", [CharacterizationEnv::testDb()]);
                $waiting = (int)$row['n'] > 0;
            }
        } finally {
            pg_query(self::$tenant, 'COMMIT');
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($child);
            pg_close($observer);
        }
        $this->assertTrue($waiting, "the line request never waited for the order.\nstdout: $stdout\nstderr: $stderr");
        $this->assertStringContainsString('"success":true', $stdout, "the line was not created.\nstderr: $stderr");

        // 2 x 49.95 + 3 x 125.67 = 476.91; cost 2 x 20 + 3 x 39.12
        $this->assertSame(['sum' => '476.910', 'moms' => '119.230', 'kostpris' => '157.360'], $this->totals($orderId),
            'the totals left out the line of the request that held the order');
        $posnr = array_map('intval', array_column(self::rows('SELECT posnr FROM ordrelinjer WHERE ordre_id = $1 ORDER BY posnr', [$orderId]), 'posnr'));
        $this->assertSame([1, 2], $posnr, 'the two lines share a position');
    }
}
