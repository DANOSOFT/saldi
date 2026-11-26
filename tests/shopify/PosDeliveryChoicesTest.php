<?php
// tests/shopify/PosDeliveryChoicesTest.php
//
// K33/K34 in the POS: the delivery choices maintained from the till settings
// (pos_leveringsvalg), and the delivery choice and wished delivery date the cashier sets on a sale
// (ordrer.leveringsvalg_id, ordrer.leveringsmaade, ordrer.oensket_levdato), which the store-order
// export (SD-804) reads. Runs the shipped functions in a child (support/run_pos_delivery.php) on a
// fresh UUID-owned tenant with the Shopify schema, once with a UTF-8 and once with a LATIN9 client,
// and checks that a MySQL installation never reaches the new columns. Skips without TestDatabase.
//
// History:
// 20261120 CL/LH K33/K34: created.
// 20261126 CDX/NTR Use a UUID-owned installed tenant and cover review regressions in delivery saves.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../characterization/support/database/TestDatabase.php';

final class PosDeliveryChoicesTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';
    private static ?TestDatabase $fixture = null;

    /** @var resource|\PgSql\Connection|null */
    private $conn = null;

    public static function setUpBeforeClass(): void
    {
        self::$skip = TestDatabase::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$fixture = TestDatabase::create(self::class);
            self::$db = self::$fixture->name();
            self::steps([['ensure_schema']]);
        } catch (Throwable $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$fixture?->release();
        self::$fixture = null;
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $this->conn = TestDatabase::connect(self::$db);
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
        return pg_fetch_all(TestDatabase::query($this->conn, $q, $p));
    }

    /** @return array<int, array{step: string, result: mixed, alerts: array}> */
    private static function steps(array $steps, string $encode = 'UTF8', string $dbType = 'postgresql'): array
    {
        $output = tmpfile();
        $errors = tmpfile();
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/support/run_pos_delivery.php', self::$db, self::$fixture->uuid(), $encode, $dbType, json_encode($steps)],
            [0 => ['file', '/dev/null', 'r'], 1 => $output, 2 => $errors],
            $pipes
        );
        self::assertIsResource($process);
        $exit = proc_close($process);
        rewind($output);
        rewind($errors);
        $stdout = stream_get_contents($output);
        $stderr = stream_get_contents($errors);
        fclose($output);
        fclose($errors);
        self::assertSame(0, $exit, "runner failed: $stderr\n$stdout");
        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        return $result;
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
        self::assertFalse($r[2]['result']);
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

    public function testARowChangedBySomeoneElseSinceThePageWasOpenedIsNotSaved(): void
    {
        $id = $this->choice('Levering', 'levering', 'EasyRoutes');
        $this->sale(3, $id);
        $html = $this->steps([['render_settings']])[0]['result'];
        self::assertSame(1, preg_match("/name='leveringsvalg\\[$id\\]\\[rev\\]' value='([0-9a-f]{32})'/", $html, $m));
        $form = ['leveringsvalg' => [$id => ['rev' => $m[1], 'navn' => 'Levering', 'type' => 'levering',
            'shopify_vaerdi' => 'Danske Fragtmænd', 'sort' => '0', 'aktiv' => 'on']]];
        $this->steps([['save_choices', $form]]);
        // The same stale form again (a second admin, or a resubmit): the row is now the deactivated old version.
        $r = $this->steps([['save_choices', $form]]);
        self::assertCount(1, $r[0]['alerts']);
        self::assertStringContainsString('ændret af en anden', $r[0]['alerts'][0]);
        self::assertSame([['shopify_vaerdi' => 'EasyRoutes', 'aktiv' => 'f'], ['shopify_vaerdi' => 'Danske Fragtmænd', 'aktiv' => 't']],
            $this->sql('SELECT shopify_vaerdi, aktiv FROM pos_leveringsvalg ORDER BY id'), 'no second replacement, and the old version stays inactive');
    }

    public function testADatabaseFailureDoesNotClaimThatAnotherAdministratorChangedTheChoice(): void
    {
        $id = $this->choice('Afhentning', 'afhentning');
        $this->sql("ALTER TABLE pos_leveringsvalg ADD CONSTRAINT review_refuse_name CHECK (navn <> 'Fejl')");
        try {
            $r = $this->steps([['save_choices', ['leveringsvalg' => [$id => [
                'navn' => 'Fejl', 'type' => 'afhentning', 'aktiv' => 'on',
            ]]]]]);
            self::assertCount(1, $r[0]['alerts']);
            self::assertStringContainsString('kunne ikke gemmes', $r[0]['alerts'][0]);
            self::assertStringNotContainsString('ændret af en anden', $r[0]['alerts'][0]);
            self::assertSame('Afhentning', $this->sql('SELECT navn FROM pos_leveringsvalg WHERE id = $1', [$id])[0]['navn']);
        } finally {
            $this->sql('ALTER TABLE pos_leveringsvalg DROP CONSTRAINT review_refuse_name');
        }
    }

    public function testAnUnchangedChoiceIsNotRewritten(): void
    {
        $id = $this->choice('Afhentning', 'afhentning');
        $before = $this->sql('SELECT xmin::text FROM pos_leveringsvalg WHERE id = $1', [$id])[0]['xmin'];
        $r = $this->steps([['save_choices', ['leveringsvalg' => [$id => [
            'navn' => 'Afhentning', 'type' => 'afhentning', 'aktiv' => 'on',
        ]]]]]);
        self::assertSame([], $r[0]['alerts']);
        self::assertSame($before, $this->sql('SELECT xmin::text FROM pos_leveringsvalg WHERE id = $1', [$id])[0]['xmin']);
    }

    public function testUnchangedAndSortOnlyChoicesDoNotReadTheOrdersTable(): void
    {
        $id = $this->choice('Afhentning', 'afhentning');
        // A missing orders relation makes an accidental usage scan fail, without mocking SQL.
        $this->sql('ALTER TABLE ordrer RENAME TO review_orders');
        try {
            $r = $this->steps([
                ['save_choices', ['leveringsvalg' => [$id => ['navn' => 'Afhentning', 'type' => 'afhentning', 'aktiv' => 'on']]]],
                ['save_choices', ['leveringsvalg' => [$id => ['navn' => 'Afhentning', 'type' => 'afhentning', 'sort' => '9']]]],
            ]);
            self::assertSame([[], []], array_column($r, 'alerts'));
            self::assertSame(['sort' => '9', 'aktiv' => 'f'], $this->sql('SELECT sort, aktiv FROM pos_leveringsvalg WHERE id = $1', [$id])[0]);
        } finally {
            $this->sql('ALTER TABLE review_orders RENAME TO ordrer');
        }
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

    public function testTheEndpointResponseReportsOnlyApplicableSuccessfulSavesAsSuccessful(): void
    {
        $valg = $this->choice('Levering', 'levering');
        $order = $this->sale(0, $valg, $this->future());
        $posted = $this->sale(3, $valg, $this->future());
        $invoice = $this->sale(0, $valg, $this->future());
        $this->sql("UPDATE ordrer SET art = 'DO' WHERE id = $1", [$invoice]);
        try {
            $requests = [
                [],
                ['id' => $order],
                ['leveringsvalg_id' => (string)$valg],
                ['id' => [$order], 'leveringsvalg_id' => (string)$valg],
                ['id' => $order, 'leveringsvalg_id' => [$valg]],
                ['id' => $order, 'leveringsvalg_id' => (string)$valg, 'oensket_levdato' => ['2027-01-01']],
                ['id' => $order . 'junk', 'leveringsvalg_id' => (string)$valg],
                ['id' => 2147483647, 'leveringsvalg_id' => (string)$valg],
                ['id' => $posted, 'leveringsvalg_id' => ''],
                ['id' => $invoice, 'leveringsvalg_id' => ''],
            ];
            $r = $this->steps(array_map(static fn(array $post): array => ['sale_response', $post], $requests));
            self::assertSame(array_fill(0, count($requests), ['ok' => false]), array_column($r, 'result'));
            $r = $this->steps([['sale_response', ['id' => $order, 'leveringsvalg_id' => (string)$valg, 'oensket_levdato' => '']]]);
            self::assertSame(['ok' => true], $r[0]['result']);
            self::assertNull($this->order($order)['oensket_levdato']);
            $r = $this->steps([['sale_response', ['id' => $order, 'leveringsvalg_id' => (string)$valg, 'oensket_levdato' => '2000-01-01']]]);
            self::assertFalse($r[0]['result']['ok']);
            self::assertStringContainsString('før i dag', $r[0]['result']['error']);
        } finally {
            $this->sql('DELETE FROM ordrer WHERE id = $1', [$invoice]);
        }
    }

    public function testTheEndpointDoesNotClaimSuccessWhenTheOrderUpdateWritesNoRow(): void
    {
        $valg = $this->choice('Levering', 'levering');
        $order = $this->sale(0, $valg, $this->future());
        $this->sql('CREATE FUNCTION review_skip_delivery_update() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RETURN NULL; END $$');
        $this->sql('CREATE TRIGGER review_skip_delivery_update BEFORE UPDATE ON ordrer FOR EACH ROW EXECUTE FUNCTION review_skip_delivery_update()');
        try {
            $r = $this->steps([['sale_response', ['id' => $order, 'leveringsvalg_id' => (string)$valg, 'oensket_levdato' => '']]]);
            self::assertSame(['ok' => false], $r[0]['result']);
            self::assertNotNull($this->order($order)['oensket_levdato']);
        } finally {
            $this->sql('DROP TRIGGER review_skip_delivery_update ON ordrer');
            $this->sql('DROP FUNCTION review_skip_delivery_update()');
        }
    }

    public function testTakenAlongNeverKeepsADate(): void
    {
        $levering = $this->choice('Levering', 'levering');
        $taget = $this->choice('Taget med', 'taget_med');
        $order = $this->sale(0, $levering, $this->future());
        $this->steps([['sale_save', $order, (string)$taget, $this->future()]]);
        self::assertSame(['leveringsvalg_id' => null, 'leveringsmaade' => null, 'oensket_levdato' => null], $this->order($order),
            'a taget med choice is stored as no choice, which the store-order export reads as taken along');
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

    public function testARefusedDateKeepsTheDeliveryThatWasPicked(): void
    {
        $valg = $this->choice('Levering', 'levering');
        $order = $this->sale();
        self::assertIsString($this->steps([['sale_save', $order, (string)$valg, '2020-01-01']])[0]['result']);
        self::assertSame(['leveringsvalg_id' => (string)$valg, 'leveringsmaade' => 'Levering', 'oensket_levdato' => null], $this->order($order),
            'the cashier corrects the date without picking the delivery again');
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
        self::assertSame(1, substr_count($html, 'Taget med</option>'), 'with a taken-along choice set up, there is no separate default option');
        self::assertStringContainsString("<option value='' data-type='taget_med'>Taget med</option>", $html,
            'a taken-along choice posts the empty value: it is stored as no choice');
        self::assertStringNotContainsString(' min=', $html, 'a sale reopened after its wished date must still submit');
    }

    public function testATakenAlongSaleHidesTheDateField(): void
    {
        $this->choice('Levering', 'levering');
        $html = $this->steps([['render_sale', $this->sale(), 0]])[0]['result'];
        self::assertStringContainsString("<option value='' data-type='taget_med' selected>Taget med</option>", $html,
            'without a taken-along choice the default is offered as "Taget med"');
        self::assertStringContainsString("<span id='pos-levdato' class='pos-levdato' style='display:none'>", $html,
            'the date is there for when the cashier picks a delivery, hidden while the sale is taken along');
        self::assertMatchesRegularExpression("/name='oensket_levdato' value='' disabled/", $html, 'and not posted while hidden');
    }

    public function testTheFieldsNeverSubmitTheSaleFormOnTheirOwn(): void
    {
        // The sale form also carries an item waiting for its quantity; submitting it on a change of
        // delivery would add that item. The fields are posted with the cashier's next action instead.
        $valg = $this->choice('Levering', 'levering');
        $html = $this->steps([['render_sale', $this->sale(0, $valg, $this->future()), 0]])[0]['result'];
        self::assertStringNotContainsString('submit(', $html);
    }

    public function testATakenAlongPickNeitherAttachesNorCreatesASale(): void
    {
        $taget = $this->choice('Taget med', 'taget_med');
        $levering = $this->choice('Levering', 'levering');
        $inactive = $this->choice('Gammel levering', 'levering', null, 0, false);
        $r = $this->steps([['attaches', (string)$taget], ['attaches', ''], ['attaches', (string)$levering], ['attaches', 'x'],
            ['attaches', (string)$inactive], ['attaches', '99999999999999999999']]);
        self::assertSame([false, false, true, false, false, false], array_column($r, 'result'));
        $order = $this->sale();
        self::assertIsString($this->steps([['sale_save', $order, '99999999999999999999', '']])[0]['result'],
            'an id outside the integer column is refused, not an error');
    }

    public function testASaleKeepsItsFieldAfterTheLastChoiceIsDeactivated(): void
    {
        $valg = $this->choice('Levering', 'levering');
        $order = $this->sale(0, $valg);
        $this->sql('UPDATE pos_leveringsvalg SET aktiv = false');
        $date = $this->future(5);
        $r = $this->steps([['enabled'], ['render_sale', $order, 0], ['sale_save', $order, (string)$valg, $date]]);
        self::assertFalse($r[0]['result']);
        self::assertStringContainsString("value='$valg' data-type='levering' selected>Levering</option>", $r[1]['result']);
        self::assertNull($r[2]['result']);
        self::assertSame($date, $this->order($order)['oensket_levdato']);
        $this->steps([['sale_save', $order, '', '']]);
        self::assertNull($this->order($order)['leveringsvalg_id'], 'and can set it back to taget med');
    }

    public function testValuesAreStoredExactlyAsTyped(): void
    {
        // db_modify() rewrites " like " to " ilike " anywhere in a statement, also inside a value.
        $this->steps([['save_choices', ['leveringsvalg_ny' => ['navn' => "Send it like O'Brien", 'type' => 'levering',
            'shopify_vaerdi' => 'ship like usual', 'tags' => 'like, "x"']]]]);
        self::assertSame([['navn' => "Send it like O'Brien", 'shopify_vaerdi' => 'ship like usual', 'tags' => '["like", "\\"x\\""]']],
            $this->sql('SELECT navn, shopify_vaerdi, tags::text AS tags FROM pos_leveringsvalg'));
    }

    public function testAnOutOfRangeSortNeverLeavesAUsedChoiceDeactivatedWithoutItsNewVersion(): void
    {
        $id = $this->choice('Levering', 'levering', 'EasyRoutes');
        $this->sale(3, $id);
        $this->steps([['save_choices', ['leveringsvalg' => [$id => ['navn' => 'Levering', 'type' => 'levering',
            'shopify_vaerdi' => 'Danske Fragtmænd', 'sort' => '99999999999', 'aktiv' => 'on']]]]]);
        self::assertSame([['shopify_vaerdi' => 'EasyRoutes', 'aktiv' => 'f'], ['shopify_vaerdi' => 'Danske Fragtmænd', 'aktiv' => 't']],
            $this->sql('SELECT shopify_vaerdi, aktiv FROM pos_leveringsvalg ORDER BY id'));
    }

    public function testAPostedSaleShowsItsDeliveryAsText(): void
    {
        $valg = $this->choice('Levering', 'levering');
        $html = $this->steps([['render_sale', $this->sale(3, $valg, '2027-01-12'), 3]])[0]['result'];
        self::assertStringNotContainsString('<select', $html);
        self::assertStringContainsString('Levering', $html);
        self::assertStringContainsString('12-01-2027', $html);
    }

    public function testAWishedDateIsShownAsAnIsoDateWhateverTheDateStyle(): void
    {
        // The date input only takes YYYY-MM-DD; a DMY DateStyle would blank it and the next submit would clear the date.
        $valg = $this->choice('Levering', 'levering');
        $html = $this->steps([['datestyle', 'SQL, DMY'], ['render_sale', $this->sale(0, $valg, '2027-01-12'), 0]])[1]['result'];
        self::assertStringContainsString('2027-01-12', $html);
        self::assertStringNotContainsString('12/01/2027', $html);
    }

    public function testMovingASaleOntoAnotherTablesSaleTakesItsDeliveryAlong(): void
    {
        $valg = $this->choice('Levering', 'levering');
        $from = $this->sale(0, $valg, '2027-01-12');
        $to = $this->sale();
        $this->steps([['move_delivery', $from, $to]]);
        self::assertSame(['leveringsvalg_id' => (string)$valg, 'leveringsmaade' => null, 'oensket_levdato' => '2027-01-12'], $this->order($to));
    }

    public function testMovingASaleOntoASaleWithItsOwnDeliveryKeepsThatOne(): void
    {
        $levering = $this->choice('Levering', 'levering');
        $afhentning = $this->choice('Afhentning', 'afhentning');
        $from = $this->sale(0, $levering, '2027-01-12');
        $to = $this->sale(0, $afhentning, '2027-02-01');
        $this->steps([['move_delivery', $from, $to]]);
        self::assertSame((string)$afhentning, $this->order($to)['leveringsvalg_id']);
        self::assertSame('2027-02-01', $this->order($to)['oensket_levdato']);
    }

    public function testANewSaleIsResetToTakenAlong(): void
    {
        self::assertSame(',leveringsvalg_id=NULL,leveringsmaade=NULL,oensket_levdato=NULL', $this->steps([['reset_sql']])[0]['result']);
    }

    // ------------------------------------------------------------------ LATIN9

    public function testALatin9InstallationTakesAUtf8PostFromATopMenuPage(): void
    {
        // The top-menu pages declare UTF-8 also on a LATIN9 installation, so the form arrives as UTF-8.
        $this->steps([['save_choices_utf8', ['leveringsvalg_ny' => ['navn' => 'Afhentning i Hørsholm', 'type' => 'afhentning',
            'shopify_vaerdi' => 'Mårum', 'tags' => 'særlig']]]], 'LATIN9');
        self::assertSame([['navn' => 'Afhentning i Hørsholm', 'shopify_vaerdi' => 'Mårum', 'tags' => '["særlig"]']],
            $this->sql('SELECT navn, shopify_vaerdi, tags::text AS tags FROM pos_leveringsvalg'));
    }

    public function testALatin9InstallationShowsDanishNamesOnATopMenuPage(): void
    {
        $this->choice('Afhentning i Hørsholm', 'afhentning', 'Mårum');
        $r = $this->steps([['menu', 'T'], ['render_settings']], 'LATIN9');
        self::assertStringContainsString("value='Afhentning i Hørsholm'", $r[1]['result']);
        self::assertStringContainsString("value='Mårum'", $r[1]['result']);
    }

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
