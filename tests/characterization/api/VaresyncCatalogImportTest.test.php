<?php
// tests/characterization/api/VaresyncCatalogImportTest.test.php
//
// The shop catalog import (api/varesync.php), run on the files a shop serves
// through support/run_varesync.php: Hent varer (varesync(1)) creates the items
// the regnskab does not have yet, Opdater fra shop (varesync(2)) updates the
// ones it has.
//
// varesync() hands three arrays to in_array() that only a loop fills: the
// regnskab's item numbers, its shop mappings, and the items the variant file
// maps. A regnskab without items or mappings, or a shop without variants, left
// one of them null, and PHP 8 ended the import with a TypeError. The variant
// one stopped every import without variants after its items were written,
// before the last stock pass and the notices about duplicate barcodes
// (testlab round 1, websims CSV-*: varesync.php:573 on both legs).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class VaresyncCatalogImportTest extends CharacterizationTestCase
{
    /** The shop's API client; nothing listens there, so every call to the shop fails at once. */
    private const API_URL = 'http://127.0.0.1:9/rest_api_client.php';

    /** The header of the variant file a shop without variants serves. */
    private const NO_VARIANTS = "\"varenr\";\"parent_id\";\"variant_id\";\"stregkode\";\"variant\";\"variant_type\";\"variant_text\"\n";

    /** @var list<array{id:string, box4:string|null}> the API rows this suite changed, put back afterwards */
    private static array $apiRows = [];
    private static bool $apiConfigured = false;

    private string $shopFiles = '';
    private ?string $clone = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$apiRows = self::rows("SELECT id, box4 FROM grupper WHERE art = 'API' ORDER BY id");
        if (self::$apiRows === []) {
            self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, box4) VALUES ('API valg', 1, 'API', $1)", [self::API_URL]);
        } else {
            self::rows("UPDATE grupper SET box4 = $1 WHERE art = 'API'", [self::API_URL]);
        }
        self::$apiConfigured = true;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$apiConfigured) {
            if (self::$apiRows === []) {
                self::rows("DELETE FROM grupper WHERE art = 'API'");
            }
            foreach (self::$apiRows as $row) {
                self::rows('UPDATE grupper SET box4 = $1 WHERE id = $2', [$row['box4'], $row['id']]);
            }
            self::$apiConfigured = false;
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        $this->shopFiles = sys_get_temp_dir() . '/chartest-shopfiles-' . bin2hex(random_bytes(4));
        mkdir($this->shopFiles, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->shopFiles . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->shopFiles);
        if ($this->clone !== null) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            try {
                CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $this->clone));
            } finally {
                pg_close($master);
            }
            $this->clone = null;
        }
    }

    /** A clone of the template with no items and no shop mappings, as a regnskab is before its first import. */
    private function emptyRegnskab(): string
    {
        $clone = CharacterizationEnv::testDb() . '_vs';
        if (!preg_match('/^[a-z0-9_]+$/', $clone) || in_array($clone, [CharacterizationEnv::templateDb(), CharacterizationEnv::masterDb()], true)) {
            throw new RuntimeException('unsafe database name ' . $clone);
        }
        $this->clone = $clone; // from here on tearDown() drops it, whatever fails below
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $clone));
            CharacterizationEnv::rows($master, 'CREATE DATABASE ' . pg_escape_identifier($master, $clone) . ' TEMPLATE ' . pg_escape_identifier($master, CharacterizationEnv::templateDb()));
        } finally {
            pg_close($master);
        }
        $this->inClone('DELETE FROM varer');
        $this->inClone('DELETE FROM shop_varer');
        $this->inClone("DELETE FROM grupper WHERE art = 'API'");
        $this->inClone("INSERT INTO grupper (beskrivelse, kodenr, art, box4) VALUES ('API valg', 1, 'API', $1)", [self::API_URL]);
        return $clone;
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, string|null>>
     */
    private function inClone(string $sql, array $params = []): array
    {
        $conn = CharacterizationEnv::connect((string)$this->clone);
        try {
            return CharacterizationEnv::rows($conn, $sql, $params);
        } finally {
            pg_close($conn);
        }
    }

    /**
     * The shop's product file in its 8-column form, and a variant file.
     *
     * @param list<array{int, string, string, string, string, int}> $products shop_id, varenr, stregkode, salgspris, beskrivelse, gruppe
     */
    private function serve(array $products, string $variants = self::NO_VARIANTS): void
    {
        $lines = ['"shop_id";"varenr";"stregkode";"salgspris";"beskrivelse";"gruppe";"tilbud";"notes"'];
        foreach ($products as [$shopId, $varenr, $stregkode, $salgspris, $beskrivelse, $gruppe]) {
            $lines[] = "\"$shopId\";\"$varenr\";\"$stregkode\";\"$salgspris\";\"$beskrivelse\";\"$gruppe\";\"\";\"\"";
        }
        file_put_contents($this->shopFiles . '/shop_products.csv', implode("\n", $lines) . "\n");
        file_put_contents($this->shopFiles . '/shop_variants.csv', $variants);
    }

    /** @return array{output:string} */
    private function import(int $valg, ?string $db = null): array
    {
        return $this->runChildJson('run_varesync.php', [$this->shopFiles, $valg, $db ?? CharacterizationEnv::testDb()]);
    }

    private function itemGroup(): int
    {
        $row = self::one("SELECT kodenr FROM grupper WHERE art = 'VG' AND fiscal_year = $1 ORDER BY kodenr LIMIT 1", [self::$regnaar]);
        $this->assertNotNull($row, 'the template tenant has an item group');
        return (int)$row['kodenr'];
    }

    private function freeShopId(): int
    {
        return (int)self::one('SELECT coalesce(max(shop_id), 990000) + 1 AS id FROM shop_varer')['id'];
    }

    public function test_hent_varer_finishes_when_the_shop_has_no_variants(): void
    {
        $tag = 'chartest-vs-' . bin2hex(random_bytes(3));
        $shopId = $this->freeShopId();
        $barcode = '5799' . random_int(100000000, 999999999);
        $group = $this->itemGroup();
        $this->serve([
            [$shopId, "$tag-1", $barcode, '100.00', 'Testvare æøå 1', $group],
            [$shopId + 1, "$tag-2", $barcode, '100.00', 'Testvare æøå 2', $group],
        ]);

        $out = $this->import(1);

        $this->assertStringContainsString("Opretter $tag-1, Testvare æøå 1<br>", $out['output']);
        $this->assertStringContainsString("Opretter $tag-2, Testvare æøå 2<br>", $out['output']);
        // The notices are the last thing varesync() prints, after the stock pass that threw.
        $this->assertStringContainsString(
            "Stregkode $barcode bruges også i $tag-1:Testvare æøå 1 -- stregkode slettet for $tag-2:Testvare æøå 2<br>",
            $out['output']
        );
        $this->assertSame(
            [
                ['varenr' => "$tag-1", 'stregkode' => $barcode, 'shop_id' => (string)$shopId],
                ['varenr' => "$tag-2", 'stregkode' => '', 'shop_id' => (string)($shopId + 1)],
            ],
            self::rows(
                'SELECT v.varenr, v.stregkode, s.shop_id FROM varer v JOIN shop_varer s ON s.saldi_id = v.id
                 WHERE v.varenr IN ($1, $2) ORDER BY v.varenr',
                ["$tag-1", "$tag-2"]
            ),
            'both items, mapped to their shop ids; the second barcode is cleared as the notice says'
        );
    }

    public function test_opdater_fra_shop_finishes_when_the_shop_has_no_variants(): void
    {
        $item = self::$fx->item(['salgspris' => 125.00]);
        $shopId = $this->freeShopId();
        $this->serve([[$shopId, $item['varenr'], '', '150.00', 'Ny tekst æøå', $item['gruppe']]]);

        $this->import(2);

        $row = self::one('SELECT salgspris, beskrivelse FROM varer WHERE id = $1', [$item['id']]);
        $this->assertEqualsWithDelta(150.0, (float)$row['salgspris'], 0.001, 'the price comes from the shop');
        $this->assertSame('Ny tekst æøå', $row['beskrivelse']);
        $this->assertSame(
            [['shop_id' => (string)$shopId]],
            self::rows('SELECT shop_id FROM shop_varer WHERE saldi_id = $1', [$item['id']]),
            'the item is mapped to its shop id'
        );
    }

    /** The first Hent varer of a regnskab has no item numbers to compare with, and the first Opdater fra shop no mappings. */
    public function test_a_regnskab_without_items_or_shop_mappings_imports_from_the_shop(): void
    {
        $db = $this->emptyRegnskab();
        $this->serve([[991001, 'chartest-vs-first', '', '100.00', 'Første vare', $this->itemGroup()]]);

        $out = $this->import(1, $db);

        $this->assertStringContainsString('Opretter chartest-vs-first, Første vare<br>', $out['output']);
        $this->assertSame(
            [['varenr' => 'chartest-vs-first', 'salgspris' => '100', 'shop_id' => '991001']],
            $this->inClone('SELECT v.varenr, round(v.salgspris)::text AS salgspris, s.shop_id FROM varer v JOIN shop_varer s ON s.saldi_id = v.id'),
            'the one item, mapped to its shop id'
        );

        $this->inClone('DELETE FROM shop_varer');
        $this->serve([[991001, 'chartest-vs-first', '', '110.00', 'Første vare', $this->itemGroup()]]);

        $this->import(2, $db);

        $this->assertSame(
            [['varenr' => 'chartest-vs-first', 'salgspris' => '110', 'shop_id' => '991001']],
            $this->inClone('SELECT v.varenr, round(v.salgspris)::text AS salgspris, s.shop_id FROM varer v JOIN shop_varer s ON s.saldi_id = v.id'),
            'the new price, and the item mapped again'
        );
    }
}
