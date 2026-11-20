<?php
// tests/shopify/PosDeliveryChoicesTest.php
//
// K33/K34 in the POS: the delivery choices HML maintains under Systemdata > POS-valg
// (pos_leveringsvalg), and the delivery choice and wished delivery date the cashier sets on a sale
// (ordrer.leveringsvalg_id, ordrer.leveringsmaade, ordrer.oensket_levdato), which the store-order
// export (SD-804) reads. Runs the shipped functions in a child (support/run_pos_delivery.php) on a
// real tenant with the Shopify schema, once with a UTF-8 and once with a LATIN9 client, and checks
// that a MySQL installation never reaches the new columns. Skips without ShopifyEnv.
//
// History:
// 20261120 CL/LH K33/K34: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class PosDeliveryChoicesTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';

    /** @var resource|\PgSql\Connection|null */
    private $conn = null;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_pd');
            ShopifyEnv::cloneTenant(self::$db);
            $run = ShopifyEnv::runSchema(self::$db, 'ensure');
            if ($run['exit'] !== 0) {
                throw new RuntimeException('schema ensure failed: ' . $run['stderr']);
            }
        } catch (Throwable $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        ShopifyEnv::dropOwnedTenants();
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $this->conn = ShopifyEnv::connect(self::$db);
        $this->sql("DELETE FROM ordrer WHERE art = 'PO' AND ref = 'k33test'");
        $this->sql('DELETE FROM pos_leveringsvalg');
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            pg_close($this->conn);
        }
    }

    private function sql(string $q, array $p = []): array
    {
        return ShopifyEnv::rows($this->conn, $q, $p);
    }

    /** @return array<int, array{step: string, result: mixed, alerts: array}> */
    private function steps(array $steps, string $encode = 'UTF8', string $dbType = 'postgresql'): array
    {
        $child = ShopifyEnv::startChild('run_pos_delivery.php', [self::$db, $encode, $dbType, json_encode($steps)]);
        $r = ShopifyEnv::waitChild($child);
        self::assertSame(0, $r['exit'], "runner failed: {$r['stderr']}\n{$r['stdout']}");
        self::assertIsArray($r['result'], "runner printed no JSON: {$r['stdout']}");
        return $r['result'];
    }

    private function choice(string $navn, string $type, ?string $vaerdi = null, int $sort = 0, bool $aktiv = true): int
    {
        return (int)$this->sql('INSERT INTO pos_leveringsvalg (navn, type, shopify_vaerdi, sort, aktiv) VALUES ($1, $2, $3, $4, $5) RETURNING id',
            [$navn, $type, $vaerdi, $sort, $aktiv ? 't' : 'f'])[0]['id'];
    }

    private function sale(int $status = 0, ?int $valg = null, ?string $dato = null): int
    {
        return (int)$this->sql("INSERT INTO ordrer (art, status, ref, hvem, konto_id, ordredate, leveringsvalg_id, oensket_levdato)
            VALUES ('PO', $1, 'k33test', 'k33test', 0, CURRENT_DATE, $2, $3) RETURNING id", [$status, $valg, $dato])[0]['id'];
    }

    private function order(int $id): array
    {
        return $this->sql('SELECT leveringsvalg_id, leveringsmaade, oensket_levdato FROM ordrer WHERE id = $1', [$id])[0];
    }

    private function future(int $days = 30): string
    {
        return date('Y-m-d', strtotime("+$days days"));
    }

    // ------------------------------------------------------------------ the gate

    public function testThePosShowsNothingNewUntilAChoiceIsSetUp(): void
    {
        [$enabled, $render] = $this->steps([['enabled'], ['render_sale', $this->sale(), 0]]);
        self::assertFalse($enabled['result'], 'an installation without delivery choices sells exactly as before');
        self::assertSame('', $render['result']);

        $this->choice('Afhentning i butik', 'afhentning');
        self::assertTrue($this->steps([['enabled']])[0]['result']);
    }

    public function testOnlyInactiveChoicesDoNotTurnTheFieldOn(): void
    {
        $this->choice('Gammel levering', 'levering', null, 0, false);
        self::assertFalse($this->steps([['enabled']])[0]['result']);
    }

    public function testAMysqlInstallationNeverQueriesTheShopifyColumns(): void
    {
        // The runner has no database connection in mysqli mode, and php has no mysqli extension
        // here, so any query would end the child with a fatal error.
        $r = $this->steps([['enabled'], ['render_sale', 1, 0], ['sale_save', 1, '3', $this->future()], ['reset_sql'],
            ['render_settings'], ['save_choices', ['leveringsvalg_ny' => ['navn' => 'Levering', 'type' => 'levering']]]], 'UTF8', 'mysqli');
        self::assertFalse($r[0]['result']);
        self::assertSame('', $r[1]['result']);
        self::assertNull($r[2]['result']);
        self::assertSame('', $r[3]['result']);
        self::assertSame('', $r[4]['result']);
    }

    // ------------------------------------------------------- settings (K33 admin)

    public function testANewChoiceIsCreatedFromTheSettingsForm(): void
    {
        $this->steps([['save_choices', ['leveringsvalg_ny' => ['navn' => ' Levering med fragtmand ', 'type' => 'levering',
            'shopify_vaerdi' => 'Danske Fragtmænd', 'tags' => 'fragtmand, , tung ', 'sort' => '2']]]]);
        $rows = $this->sql('SELECT navn, type, shopify_vaerdi, tags::text AS tags, aktiv, sort FROM pos_leveringsvalg');
        self::assertCount(1, $rows);
        self::assertSame(['navn' => 'Levering med fragtmand', 'type' => 'levering', 'shopify_vaerdi' => 'Danske Fragtmænd',
            'tags' => '["fragtmand", "tung"]', 'aktiv' => 't', 'sort' => '2'], $rows[0]);
    }

    public function testAnEmptyNewRowCreatesNothing(): void
    {
        $this->steps([['save_choices', ['leveringsvalg_ny' => ['navn' => '', 'type' => 'levering']]]]);
        self::assertSame([], $this->sql('SELECT id FROM pos_leveringsvalg'));
    }

    public function testAChoiceWithoutANameOrWithAnUnknownTypeIsRefused(): void
    {
        $id = $this->choice('Afhentning', 'afhentning');
        $r = $this->steps([['save_choices', [
            'leveringsvalg' => [$id => ['navn' => '', 'type' => 'afhentning', 'aktiv' => 'on']],
            'leveringsvalg_ny' => ['navn' => 'Drone', 'type' => 'drone'],
        ]]]);
        self::assertCount(1, $r[0]['alerts'], 'the administrator is told what was not saved');
        self::assertSame([['navn' => 'Afhentning', 'type' => 'afhentning']], $this->sql('SELECT navn, type FROM pos_leveringsvalg'));
    }

    public function testAnUnusedChoiceIsEditedInPlace(): void
    {
        $id = $this->choice('Levering', 'levering', 'EasyRoutes');
        $this->steps([['save_choices', ['leveringsvalg' => [$id => ['navn' => 'Levering til døren', 'type' => 'levering',
            'shopify_vaerdi' => 'EasyRoutes', 'tags' => '', 'sort' => '1', 'aktiv' => 'on']]]]]);
        self::assertSame([['id' => (string)$id, 'navn' => 'Levering til døren', 'aktiv' => 't', 'sort' => '1']],
            $this->sql('SELECT id, navn, aktiv, sort FROM pos_leveringsvalg'));
    }

    public function testAChoiceAnOrderUsesGetsANewVersionWhenItsMeaningChanges(): void
    {
        $id = $this->choice('Levering', 'levering', 'EasyRoutes');
        $order = $this->sale(3, $id, $this->future());
        $this->steps([['save_choices', ['leveringsvalg' => [$id => ['navn' => 'Levering', 'type' => 'levering',
            'shopify_vaerdi' => 'Danske Fragtmænd', 'tags' => '', 'sort' => '0', 'aktiv' => 'on']]]]]);

        $rows = $this->sql('SELECT id, navn, shopify_vaerdi, aktiv FROM pos_leveringsvalg ORDER BY id');
        self::assertCount(2, $rows);
        self::assertSame(['id' => (string)$id, 'navn' => 'Levering', 'shopify_vaerdi' => 'EasyRoutes', 'aktiv' => 'f'], $rows[0],
            'the posted order keeps the choice it was sold with');
        self::assertSame(['Levering', 'Danske Fragtmænd', 't'], [$rows[1]['navn'], $rows[1]['shopify_vaerdi'], $rows[1]['aktiv']]);
        self::assertSame((string)$id, $this->order($order)['leveringsvalg_id']);
    }

    public function testSortAndActiveChangeInPlaceEvenWhenUsed(): void
    {
        $id = $this->choice('Afhentning', 'afhentning');
        $this->sale(3, $id);
        $this->steps([['save_choices', ['leveringsvalg' => [$id => ['navn' => 'Afhentning', 'type' => 'afhentning',
            'shopify_vaerdi' => '', 'tags' => '', 'sort' => '5']]]]]);
        self::assertSame([['id' => (string)$id, 'aktiv' => 'f', 'sort' => '5']], $this->sql('SELECT id, aktiv, sort FROM pos_leveringsvalg'));
    }

    public function testDeletingAnUnusedChoiceRemovesIt(): void
    {
        $id = $this->choice('Afhentning', 'afhentning');
        $this->steps([['save_choices', ['leveringsvalg' => [$id => ['navn' => 'Afhentning', 'type' => 'afhentning', 'slet' => 'on']]]]]);
        self::assertSame([], $this->sql('SELECT id FROM pos_leveringsvalg'));
    }

    public function testDeletingAUsedChoiceOnlyDeactivatesIt(): void
    {
        $id = $this->choice('Afhentning', 'afhentning');
        $this->sale(3, $id);
        $this->steps([['save_choices', ['leveringsvalg' => [$id => ['navn' => 'Afhentning', 'type' => 'afhentning', 'slet' => 'on', 'aktiv' => 'on']]]]]);
        self::assertSame([['id' => (string)$id, 'aktiv' => 'f']], $this->sql('SELECT id, aktiv FROM pos_leveringsvalg'));
    }

    public function testAFormWithoutTheDeliveryFieldsLeavesTheChoicesAlone(): void
    {
        $this->choice('Afhentning', 'afhentning');
        $this->steps([['save_choices', ['kasseantal' => '1']]]);
        self::assertSame([['navn' => 'Afhentning', 'aktiv' => 't']], $this->sql('SELECT navn, aktiv FROM pos_leveringsvalg'));
    }

    public function testTheSettingsListShowsEveryChoiceWithItsValues(): void
    {
        $this->choice('Levering <b>', 'levering', 'Danske Fragtmænd', 1);
        $this->choice('Afhentning', 'afhentning', null, 0, false);
        $html = $this->steps([['render_settings']])[0]['result'];
        self::assertStringContainsString('Levering &lt;b&gt;', $html, 'the name is escaped');
        self::assertStringContainsString('Danske Fragtmænd', $html);
        self::assertStringContainsString("name='leveringsvalg_ny[navn]'", $html, 'there is always a row for a new choice');
        self::assertSame(2, substr_count($html, "[slet]'"), 'each stored choice can be deleted');
    }

    // --------------------------------------------------------- the sale (K33/K34)

    public function testTheCashierPicksADeliveryChoiceAndAWishedDate(): void
    {
        $valg = $this->choice('Levering', 'levering', 'EasyRoutes');
        $order = $this->sale();
        $date = $this->future(40);
        $r = $this->steps([['sale_save', $order, (string)$valg, $date]]);
        self::assertNull($r[0]['result']);
        self::assertSame(['leveringsvalg_id' => (string)$valg, 'leveringsmaade' => 'Levering', 'oensket_levdato' => $date], $this->order($order));
    }

    public function testADanishDateIsUnderstood(): void
    {
        $valg = $this->choice('Afhentning', 'afhentning');
        $order = $this->sale();
        $iso = $this->future(10);
        $this->steps([['sale_save', $order, (string)$valg, date('d-m-Y', strtotime($iso))]]);
        self::assertSame($iso, $this->order($order)['oensket_levdato']);
    }

    public function testDeliveryWithoutADateIsAllowed(): void
    {
        // K36: a delivery without a date goes to Shopify with the note "Leveringsdato aftales med kunden".
        $valg = $this->choice('Levering', 'levering');
        $order = $this->sale();
        self::assertNull($this->steps([['sale_save', $order, (string)$valg, '']])[0]['result']);
        self::assertSame(['leveringsvalg_id' => (string)$valg, 'leveringsmaade' => 'Levering', 'oensket_levdato' => null], $this->order($order));
    }

    public function testTakenAlongNeverKeepsADate(): void
    {
        $levering = $this->choice('Levering', 'levering');
        $taget = $this->choice('Taget med', 'taget_med');
        $order = $this->sale(0, $levering, $this->future());
        $this->steps([['sale_save', $order, (string)$taget, $this->future()]]);
        self::assertSame(['leveringsvalg_id' => (string)$taget, 'leveringsmaade' => 'Taget med', 'oensket_levdato' => null], $this->order($order));
    }

    public function testNoChoiceMeansTakenAlong(): void
    {
        $levering = $this->choice('Levering', 'levering');
        $order = $this->sale(0, $levering, $this->future());
        $this->steps([['sale_save', $order, '', $this->future()]]);
        self::assertSame(['leveringsvalg_id' => null, 'leveringsmaade' => null, 'oensket_levdato' => null], $this->order($order));
    }

    public function testAnInvalidOrPastDateIsRefusedAndTheSaleKeepsItsValues(): void
    {
        $valg = $this->choice('Levering', 'levering');
        $date = $this->future();
        $order = $this->sale(0, $valg, $date);
        $r = $this->steps([
            ['sale_save', $order, (string)$valg, '31-02-2027'],
            ['sale_save', $order, (string)$valg, 'i morgen'],
            ['sale_save', $order, (string)$valg, date('Y-m-d', strtotime('-1 day'))],
        ]);
        foreach ($r as $step) {
            self::assertIsString($step['result'], 'the cashier is told why');
        }
        self::assertSame($date, $this->order($order)['oensket_levdato']);
    }

    public function testTodayIsAValidWishedDate(): void
    {
        $valg = $this->choice('Afhentning', 'afhentning');
        $order = $this->sale();
        self::assertNull($this->steps([['sale_save', $order, (string)$valg, date('Y-m-d')]])[0]['result']);
    }

    public function testAnUnknownOrInactiveChoiceIsRefused(): void
    {
        $inactive = $this->choice('Gammel', 'levering', null, 0, false);
        $this->choice('Afhentning', 'afhentning');
        $order = $this->sale();
        $r = $this->steps([['sale_save', $order, (string)$inactive, ''], ['sale_save', $order, '999999', ''], ['sale_save', $order, 'x', '']]);
        foreach ($r as $step) {
            self::assertIsString($step['result']);
        }
        self::assertNull($this->order($order)['leveringsvalg_id']);
    }

    public function testASaleKeepsAChoiceThatWasDeactivatedAfterItWasPicked(): void
    {
        $valg = $this->choice('Levering', 'levering');
        $order = $this->sale(0, $valg);
        $this->sql("UPDATE pos_leveringsvalg SET aktiv = false WHERE id = $1", [$valg]);
        $this->choice('Afhentning', 'afhentning');
        $date = $this->future();
        self::assertNull($this->steps([['sale_save', $order, (string)$valg, $date]])[0]['result'],
            'the field posts the stored choice back on every scan');
        self::assertSame($date, $this->order($order)['oensket_levdato']);
    }

    public function testAPostedSaleIsNotChanged(): void
    {
        $valg = $this->choice('Levering', 'levering');
        $other = $this->choice('Afhentning', 'afhentning');
        $order = $this->sale(3, $valg);
        $this->steps([['sale_save', $order, (string)$other, $this->future()]]);
        self::assertSame((string)$valg, $this->order($order)['leveringsvalg_id']);
    }

    public function testTheSaleShowsTheActiveChoicesWithTheCurrentOneSelected(): void
    {
        $this->choice('Taget med', 'taget_med', null, 0);
        $levering = $this->choice('Levering <i>', 'levering', null, 2);
        $this->choice('Afhentning', 'afhentning', null, 1);
        $this->choice('Gammel', 'levering', null, 3, false);
        $date = $this->future();
        $html = $this->steps([['render_sale', $this->sale(0, $levering, $date), 0]])[0]['result'];
        self::assertMatchesRegularExpression('/Taget med.*Afhentning.*Levering &lt;i&gt;/s', $html, 'in the order the settings give');
        self::assertStringNotContainsString('Gammel', $html);
        self::assertStringContainsString("value='$levering' data-type='levering' selected", $html);
        self::assertStringContainsString("name='oensket_levdato'", $html);
        self::assertStringContainsString("value='$date'", $html);
        self::assertStringNotContainsString("value=''", substr($html, 0, strpos($html, 'Taget med')),
            'with a taken-along choice set up, there is no separate empty option');
    }

    public function testATakenAlongSaleHasNoDateField(): void
    {
        $this->choice('Levering', 'levering');
        $html = $this->steps([['render_sale', $this->sale(), 0]])[0]['result'];
        self::assertStringContainsString("<option value='' data-type='taget_med' selected>Taget med</option>", $html,
            'without a taken-along choice the default is offered as "Taget med"');
        self::assertStringNotContainsString("name='oensket_levdato'", $html);
    }

    public function testAPostedSaleShowsItsDeliveryAsText(): void
    {
        $valg = $this->choice('Levering', 'levering');
        $html = $this->steps([['render_sale', $this->sale(3, $valg, '2027-01-12'), 3]])[0]['result'];
        self::assertStringNotContainsString('<select', $html);
        self::assertStringContainsString('Levering', $html);
        self::assertStringContainsString('12-01-2027', $html);
    }

    public function testANewSaleIsResetToTakenAlong(): void
    {
        self::assertSame(',leveringsvalg_id=NULL,leveringsmaade=NULL,oensket_levdato=NULL', $this->steps([['reset_sql']])[0]['result']);
    }

    // ------------------------------------------------------------------ LATIN9

    public function testALatin9InstallationStoresAndShowsDanishNames(): void
    {
        $r = $this->steps([
            ['save_choices', ['leveringsvalg_ny' => ['navn' => 'Afhentning på lager Mårum', 'type' => 'afhentning',
                'shopify_vaerdi' => 'Hørsholm', 'tags' => 'mårum, særlig']]],
            ['render_settings'],
        ], 'LATIN9');
        $row = $this->sql('SELECT id, navn, shopify_vaerdi, tags::text AS tags FROM pos_leveringsvalg')[0];
        self::assertSame(['Afhentning på lager Mårum', 'Hørsholm', '["mårum", "særlig"]'], [$row['navn'], $row['shopify_vaerdi'], $row['tags']]);
        self::assertStringContainsString('Afhentning på lager Mårum', $r[1]['result']);
        self::assertStringContainsString('mårum, særlig', $r[1]['result']);

        $order = $this->sale();
        $r = $this->steps([['sale_save', $order, $row['id'], ''], ['render_sale', $order, 0]], 'LATIN9');
        self::assertNull($r[0]['result']);
        self::assertSame('Afhentning på lager Mårum', $this->order($order)['leveringsmaade']);
        self::assertStringContainsString('Afhentning på lager Mårum</option>', $r[1]['result']);
    }
}
