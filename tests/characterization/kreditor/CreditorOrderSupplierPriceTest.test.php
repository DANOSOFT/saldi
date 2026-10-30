<?php

declare(strict_types=1);

namespace Saldi\Tests\CreditorOrderSupplierPrice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261019 CL/MJ SST-872 Pins that a creditor order line takes the supplier's purchase price.
//
//                  MEDSHOP's order 16845 to sound-wize.dk carried item 112140 at 1.390,00 -
//                  varer.kostpris - while sound-wize's own price in the supplier list is
//                  900,00. The supplier lookup sat inside if (!$lev_varenr[0]), and the block
//                  above it sets lev_varenr whenever the typed number is also the supplier's
//                  number. sound-wize uses our item numbers, so lev_varenr was always already
//                  set by then, the lookup never ran, and the line fell through to the item's
//                  general cost price. Seven orders between 2026-09-25 and 2026-10-14 show it.
//
//                  Two paths were already right and must stay right: picking the item through
//                  the lookup (GET vare_id), where lev_varenr is still empty, and the purchase
//                  proposal's Bestil in lager/orderapi.php.
//
//                  kreditor/ordre.php is a page script that opens a session and prints on
//                  include, so the decisions are lifted out of the source and run against stub
//                  database functions - in this file's own namespace, so they cannot collide
//                  with another test's, the way WarehouseDropdownTest does it.
// 20261030 CL/MJ SST-872 A zero price as the database returns it ("0.000") falls back to the item's cost price.

/** @var array<string, mixed> Rows the stubs answer with, keyed by what is being asked. */
$GLOBALS['sst872Db'] = [];
/** @var array<int, string> Every statement the lifted code asked for. */
$GLOBALS['sst872Queries'] = [];

function db_select($sql, $location = '')
{
    $GLOBALS['sst872Queries'][] = $sql;
    $db = $GLOBALS['sst872Db'];

    // The supplier's own row for this item.
    if (preg_match("/from vare_lev where vare_id = '(\d+)' and lev_id = '(\d+)'/", $sql, $m)) {
        $row = $db['vare_lev'][$m[1] . ':' . $m[2]] ?? null;
        return new \ArrayIterator($row === null ? [] : [$row]);
    }
    // Resolving the supplier from the account number typed on the order.
    if (preg_match("/from adresser where kontonr = '([^']*)' and art = 'K'/", $sql, $m)) {
        $id = $db['adresser'][$m[1]] ?? null;
        return new \ArrayIterator($id === null ? [] : [['id' => $id]]);
    }
    // The order's supplier, for the component lines.
    if (preg_match("/select konto_id from ordrer where id = '(\d+)'/", $sql, $m)) {
        $id = $db['ordrer'][$m[1]] ?? null;
        return new \ArrayIterator($id === null ? [] : [['konto_id' => $id]]);
    }
    return new \ArrayIterator([]);
}

function db_fetch_array($result)
{
    if (!$result instanceof \ArrayIterator || !$result->valid()) {
        return false;
    }
    $row = $result->current();
    $result->next();
    return $row;
}

function db_escape_string($value)
{
    return str_replace("'", "''", (string)$value);
}

final class CreditorOrderSupplierPriceTest extends TestCase
{
    private const ORDRE = __DIR__ . '/../../../kreditor/ordre.php';

    private static ?string $src = null;

    private static function source(): string
    {
        if (self::$src === null) {
            $src = file_get_contents(self::ORDRE);
            self::assertNotFalse($src, 'could not read kreditor/ordre.php');
            self::$src = $src;
        }
        return self::$src;
    }

    /**
     * Slices the source from $from up to and including $toEndOf, or up to $before.
     *
     * The database calls are rewritten to this namespace. Code passed to eval() does not
     * inherit the caller's namespace, so an unqualified db_escape_string() in the lifted
     * block reaches the real one in includes/db_query.php - which another test in the same
     * PHPUnit process has already loaded - and dies without a connection. Naming them
     * explicitly makes the lift behave the same whatever else the suite happens to load.
     */
    private static function slice(string $from, ?string $toEndOf, ?string $before = null): string
    {
        $src = self::source();
        $at = strpos($src, $from);
        self::assertNotFalse($at, "the block starting \"$from\" is gone");

        if ($before !== null) {
            $end = strpos($src, $before, $at);
            self::assertNotFalse($end, "the block no longer reaches \"$before\"");
            $block = substr($src, $at, $end - $at);
        } else {
            $end = strpos($src, $toEndOf, $at);
            self::assertNotFalse($end, "the block no longer reaches \"$toEndOf\"");
            $block = substr($src, $at, $end - $at + strlen($toEndOf));
        }

        foreach (['db_select', 'db_fetch_array', 'db_escape_string'] as $fn) {
            $block = str_replace($fn . '(', '\\' . __NAMESPACE__ . '\\' . $fn . '(', $block);
        }
        return $block;
    }

    /** The price resolution for a line typed into the order. */
    private static function typedLineBlock(): string
    {
        return self::slice(
            '// 20261019 CL/MJ SST-872 Read whatever lev_varenr already holds',
            "if (!\$pris[0]) \$pris[0]=\$r0['kostpris'];");
    }

    /**
     * samlevare()'s two real pieces: reading the order's supplier, then choosing a price.
     *
     * Both are lifted because they are both the fix. They are separated in the source by the
     * loop over fuld_stykliste(), which includes a file and queries varer, so the loop's own
     * read of the component row is stood in for between them - the one line this harness
     * supplies rather than lifts. Without the first piece, dropping the supplier lookup would
     * be invisible here, which is exactly what the mutation sweep caught.
     */
    private static function componentBlock(float $itemCost): string
    {
        $supplier = self::slice('$konto_id = 0;', null, 'for ($x=1; $x<=$antal; $x++) {');
        $price = self::slice("\$pris = \$r['kostpris'];", null, 'db_modify("insert into ordrelinjer');
        return $supplier . "\n\$r = array('kostpris' => " . var_export($itemCost, true) . ");\n" . $price;
    }

    /**
     * Runs the real typed-line resolution for one order line.
     *
     * @param array<string, mixed> $db     vare_lev/adresser rows the lookup will find
     * @param string               $levNr  what lev_varenr already holds on the line
     * @param string|int           $typed  a price already typed on the line, or '' for none
     * @return array{pris: mixed, lev_varenr: mixed, konto_id: mixed}
     */
    private function resolveTypedLine(array $db, string $levNr = '', $typed = '',
                                      int $kontoId = 2026, string $kontonr = '', $itemCost = 1390.00): array
    {
        $GLOBALS['sst872Db'] = $db;
        $GLOBALS['sst872Queries'] = [];

        $vare_id = [0 => 500];
        $pris = [0 => $typed];
        $lev_varenr = [0 => $levNr];
        $konto_id = $kontoId;
        $r0 = ['kostpris' => $itemCost];

        eval(self::typedLineBlock());

        return ['pris' => $pris[0], 'lev_varenr' => $lev_varenr[0], 'konto_id' => $konto_id];
    }

    /** The supplier list as the ticket describes it: item 500 at two suppliers. */
    private static function supplierList(): array
    {
        return ['vare_lev' => [
            // sound-wize.dk, posnr 1, uses our own item number
            '500:2026' => ['kostpris' => 900.00, 'lev_varenr' => '112140'],
            // Diatec A/S, posnr 2
            '500:2027' => ['kostpris' => 1311.00, 'lev_varenr' => 'DIA-112140'],
        ]];
    }

    // ------------------------------------------------------------- the reported bug

    /**
     * The reported case: the supplier uses our item number, so lev_varenr is already set.
     *
     * This is the whole bug. Before the fix the block below was skipped for exactly this
     * line and the price became the item's 1.390,00.
     */
    public function testTheSupplierPriceIsUsedEvenWhenLevVarenrIsAlreadyKnown(): void
    {
        $got = $this->resolveTypedLine(self::supplierList(), '112140');
        self::assertSame(900.00, $got['pris'],
            "item 112140 went in at the item's cost price instead of sound-wize's 900,00");
    }

    /** Typing the supplier's own number resolves to the same supplier price. */
    public function testTypingTheSuppliersNumberAlsoGivesTheSupplierPrice(): void
    {
        $got = $this->resolveTypedLine(self::supplierList(), 'DIA-112140', '', 2027);
        self::assertSame(1311.00, $got['pris'], "Diatec's own price was not used");
    }

    /** With lev_varenr empty - the lookup path - nothing regresses. */
    public function testTheLookupPathStillGetsTheSupplierPrice(): void
    {
        $got = $this->resolveTypedLine(self::supplierList(), '');
        self::assertSame(900.00, $got['pris']);
        self::assertSame('112140', $got['lev_varenr'],
            'the supplier number is no longer filled in when the line has none');
    }

    // ----------------------------------------------------------------- the fallbacks

    /** A supplier that is not in the item's supplier list keeps the item's cost price. */
    public function testASupplierOutsideTheListFallsBackToTheItemCost(): void
    {
        $got = $this->resolveTypedLine(self::supplierList(), '', '', 9999);
        self::assertSame(1390.00, $got['pris'],
            'an unknown supplier should still use varer.kostpris');
    }

    /** @return array<string, array{mixed}> */
    public static function pricelessSupplierRows(): array
    {
        return [
            'no price at all' => [0],
            'empty string'    => [''],
            'null'            => [null],
            // vare_lev.kostpris is numeric(15,3): Postgres returns a zero as this string, which PHP treats as true
            'zero from the database' => ['0.000'],
        ];
    }

    /** In the list but with no price of its own: still the item's cost price. */
    #[DataProvider('pricelessSupplierRows')]
    public function testASupplierWithoutAPriceFallsBackToTheItemCost($kostpris): void
    {
        $db = ['vare_lev' => ['500:2026' => ['kostpris' => $kostpris, 'lev_varenr' => '112140']]];
        self::assertSame(1390.00, $this->resolveTypedLine($db, '112140')['pris'],
            'a supplier row without a price should not blank the line');
    }

    /** A price the user typed is never replaced. */
    public function testAPriceTypedOnTheLineIsNotOverwritten(): void
    {
        $got = $this->resolveTypedLine(self::supplierList(), '112140', 750.00);
        self::assertSame(750.00, $got['pris'], "the user's own price was overwritten");
    }

    /** Nor is a supplier number the user typed. */
    public function testASupplierNumberTypedOnTheLineIsNotOverwritten(): void
    {
        $got = $this->resolveTypedLine(self::supplierList(), 'TYPED-BY-HAND');
        self::assertSame('TYPED-BY-HAND', $got['lev_varenr'],
            'the supplier number entered on the line was replaced by the stored one');
    }

    /** The supplier is resolved from the account number when the id is not known yet. */
    public function testTheSupplierIsResolvedFromTheAccountNumber(): void
    {
        $db = self::supplierList();
        $db['adresser'] = ['50100' => 2026];
        $got = $this->resolveTypedLine($db, '112140', '', 0, '50100');
        self::assertSame(2026, $got['konto_id'], 'the supplier was not resolved from kontonr');
        self::assertSame(900.00, $got['pris']);
    }

    /** No supplier at all: the item's cost price, and no lookup attempted. */
    public function testNoSupplierMeansNoLookup(): void
    {
        $got = $this->resolveTypedLine(self::supplierList(), '112140', '', 0, '');
        self::assertSame(1390.00, $got['pris']);
        foreach ($GLOBALS['sst872Queries'] as $sql) {
            self::assertStringNotContainsString('from vare_lev', $sql,
                'the supplier list was queried without a supplier');
        }
    }

    // ------------------------------------------------------------ the component lines

    /**
     * Runs the real component price choice from samlevare().
     *
     * @return mixed The price the component line would be inserted with.
     */
    private function resolveComponent(array $db, int $kontoId, $itemCost)
    {
        // The supplier is reached through the order, the way samlevare() has to: it is never
        // told who it is. Order 16845 belongs to account 2026.
        $db['ordrer'] = [16845 => $kontoId];
        $GLOBALS['sst872Db'] = $db;
        $GLOBALS['sst872Queries'] = [];

        $x = 1;
        $vare_id = [1 => 500];
        $id = 16845;

        eval(self::componentBlock((float)$itemCost));

        return $pris;
    }

    /** A component the supplier also sells goes in at the supplier's price. */
    public function testAComponentUsesTheSupplierPrice(): void
    {
        self::assertSame(900.00, $this->resolveComponent(self::supplierList(), 2026, 1390.00),
            "a component went in at the item's cost price while the supplier has its own");
    }

    /** A component the supplier does not sell keeps the item's cost price. */
    public function testAComponentOutsideTheSupplierListKeepsTheItemCost(): void
    {
        self::assertSame(1390.00, $this->resolveComponent(self::supplierList(), 9999, 1390.00));
    }

    /** No supplier on the order: unchanged from before. */
    public function testAComponentWithoutASupplierKeepsTheItemCost(): void
    {
        self::assertSame(1390.00, $this->resolveComponent(self::supplierList(), 0, 1390.00));
    }

    /**
     * A supplier row with no price of its own must not blank the component line.
     *
     * The same rule the typed line follows, and the reason the choice is guarded on the value
     * rather than on the row existing.
     */
    #[DataProvider('pricelessSupplierRows')]
    public function testAComponentWhoseSupplierRowHasNoPriceKeepsTheItemCost($kostpris): void
    {
        $db = ['vare_lev' => ['500:2026' => ['kostpris' => $kostpris, 'lev_varenr' => '112140']]];
        self::assertSame(1390.00, $this->resolveComponent($db, 2026, 1390.00),
            'a supplier row without a price should not become the component price');
    }

    /** The inserted line has to carry the chosen price, not the item's row. */
    public function testTheComponentInsertUsesTheChosenPrice(): void
    {
        $src = self::source();
        $at = strpos($src, 'insert into ordrelinjer (ordre_id,varenr,vare_id,beskrivelse,antal,leveres,pris,posnr,lager)');
        self::assertNotFalse($at, 'the component insert has moved');
        $insert = substr($src, $at, 300);
        self::assertStringContainsString("'\$pris'", $insert,
            'the component insert does not use the resolved price');
        self::assertStringNotContainsString("'\$r[kostpris]'", $insert,
            "the component insert went back to the item's cost price");
    }

    // ------------------------------------------------------- what must not have moved

    /** The lookup may not be gated on the supplier number being unknown again. */
    public function testTheSupplierLookupIsNotGatedOnLevVarenr(): void
    {
        $block = self::typedLineBlock();
        self::assertStringNotContainsString('if (!$lev_varenr[0]) {', $block,
            'the supplier lookup is back inside the gate that caused this bug');
        self::assertStringContainsString('from vare_lev', $block,
            'the supplier list is no longer consulted at all');
    }

    /** Currency conversion still happens, and still after the price is settled. */
    public function testTheCurrencyConversionStillFollowsThePrice(): void
    {
        $src = self::source();
        $price = strpos($src, "if (!\$pris[0]) \$pris[0]=\$r0['kostpris'];");
        self::assertNotFalse($price);
        $valuta = strpos($src, "if (\$valuta && \$valuta!='DKK') {", $price);
        self::assertNotFalse($valuta, 'the currency conversion no longer follows the price');
        self::assertStringContainsString('$pris[0]=$pris[0]*100/$r1[\'kurs\'];',
            substr($src, $valuta, 500),
            'the converted price is no longer written back to the line');
    }

    /** Every statement this ticket wrote casts or escapes what it interpolates. */
    public function testTheStatementsThisTicketWroteAreEscaped(): void
    {
        foreach ([self::typedLineBlock(), self::componentBlock(1390.00)] as $block) {
            if (!preg_match_all('/\$qtxt = "[^"]*(?:"[^;]*)*;/', $block, $m)) {
                continue;
            }
            foreach ($m[0] as $statement) {
                self::assertDoesNotMatchRegularExpression(
                    '/\'\$[A-Za-z_]/', $statement,
                    "a value is interpolated raw into SQL: $statement");
            }
        }
    }
}
