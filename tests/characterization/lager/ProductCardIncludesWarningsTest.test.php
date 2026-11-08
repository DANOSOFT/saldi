<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261105 CL/LH Created. Opening an item card (lager/varekort.php?id=13) and saving its Lokation logged PHP warnings from three of the card's includes (testlab round 1, warehouses-transfer-receipt, both legs).
//                showGroups.php read one debtor discount group past the last, and that read was what printed the empty option that clears an item's group.
//                showQtyDiscounts.php read the decimals of a whole number that has none.
//                showLocations.php read the location of a warehouse with no lagerstatus row.
//                Each include runs here as varekort.php runs it, in the caller's scope with the card's variables set, against a fake database (support/product_card_includes_database.php).
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ProductCardIncludesWarningsTest extends TestCase
{
    private const INCLUDES = __DIR__ . '/../../../lager/productCardIncludes/';

    protected function setUp(): void
    {
        require_once __DIR__ . '/support/product_card_includes_database.php';
        require_once __DIR__ . '/../../../includes/std_func.php';
        ProductCardIncludesDb::$plan = array();
        ProductCardIncludesDb::$writes = array();
    }

    /**
     * Includes one productCardIncludes file with $variables in its scope, as lager/varekort.php does.
     *
     * @param array<string, mixed> $variables
     * @return array{0: string, 1: array<int, string>} The output, and every warning, notice or deprecation raised.
     */
    private static function render(string $include, array $variables): array
    {
        $problems = array();
        set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$problems): bool {
            $problems[] = basename($errfile) . ':' . $errline . ' ' . $errstr;
            return true;
        });
        ob_start();
        try {
            (static function (string $productCardInclude, array $productCardVariables): void {
                extract($productCardVariables);
                include $productCardInclude;
            })(self::INCLUDES . $include, $variables);
        } finally {
            $html = (string) ob_get_clean();
            restore_error_handler();
        }
        return array($html, $problems);
    }

    /**
     * Renders showGroups.php for an item in varegruppe 1 and returns the Debitorrabatgrp. options.
     *
     * @param array<int, int> $dvrgNr Index 0 is the item's own group, 1.. the DVRG groups, as varekort.php builds it.
     * @param array<int, string> $dvrgNavn
     * @return array{0: array<int, array{0: string, 1: string}>, 1: array<int, string>} value/label pairs, and the problems.
     */
    private static function debtorDiscountOptions(array $dvrgNr, array $dvrgNavn): array
    {
        ProductCardIncludesDb::$plan = array(
            array("/^select beskrivelse,box10 from grupper where art='VG' and kodenr = '1'/", array(array('beskrivelse' => 'Varer', 'box10' => ''))),
            array('/information_schema\.columns/', array()),
            array("/from grupper where art='VG' and kodenr!='1'/", array(array('kodenr' => '2', 'beskrivelse' => 'Ydelser'))),
            array("/art='(VPG|VTG|VRG)'/", array()),
        );
        [$html, $problems] = self::render('showGroups.php', array(
            'sprog_id' => 1, 'gruppe' => 1, 'regnaar' => 1, 'operation' => 0, 'beholdning' => 0, 'batchItem' => 0,
            'samlevare' => '', 'dvrg_nr' => $dvrgNr, 'dvrg_navn' => $dvrgNavn,
            'prisgruppe' => 0, 'tilbudgruppe' => 0, 'rabatgruppe' => 0,
        ));
        self::assertSame(1, preg_match('/NAME="dvrg"[^>]*>(.*?)<\/SELECT>/s', $html, $select), 'no Debitorrabatgrp. select in: ' . $html);
        preg_match_all('/<option value="([^"]*)">([^<]*)<\/option>/', $select[1], $matches, PREG_SET_ORDER);
        $options = array_map(static fn (array $match): array => array($match[1], $match[2]), $matches);
        return array($options, $problems);
    }

    public function testAnItemWithoutADebtorDiscountGroupListsTheGroupsWithoutReadingPastTheLast(): void
    {
        // The lab's case: one DVRG group, and the item has none (dvrg 0).
        [$options, $problems] = self::debtorDiscountOptions(array(0 => 0, 1 => 5), array(1 => 'Engros'));

        self::assertSame(array(), $problems);
        self::assertSame(array(array('0', ''), array('5', '5 Engros')), $options);
    }

    public function testAnItemInADebtorDiscountGroupListsItFirstThenEachOtherGroupOnceThenAnEmptyOptionThatClearsIt(): void
    {
        [$options, $problems] = self::debtorDiscountOptions(array(0 => 7, 1 => 5, 2 => 7), array(1 => 'Engros', 2 => 'Detail'));

        self::assertSame(array(), $problems);
        // The empty option posts dvrg 0, which lager/varekort.php saves as no group.
        self::assertSame(array(array('7', '7 Detail'), array('5', '5 Engros'), array('0', '')), $options);
    }

    /**
     * @param array<int, string> $rabat
     * @param array<int, string> $antal
     * @return array{0: string, 1: array<int, string>}
     */
    private static function quantityDiscounts(array $rabat, array $antal): array
    {
        return self::render('showQtyDiscounts.php', array(
            'sprog_id' => 1, 'special_price' => 0, 'specialType' => '', 'm_type' => 'percent',
            'm_rabat_array' => $rabat, 'm_antal_array' => $antal, 'incl_moms' => 0, 'rabatgruppe' => 0,
        ));
    }

    public function testAnItemWithoutQuantityDiscountsShowsAnEmptyRowWithoutWarnings(): void
    {
        // varer.m_rabat and m_antal are '' for the lab's item; explode() makes them array('').
        [$html, $problems] = self::quantityDiscounts(array(''), array(''));

        self::assertSame(array(), $problems);
        self::assertStringContainsString("name='m_rabat_array[1]'value='0'", $html);
        self::assertStringContainsString("name='m_antal_array[1]'value='0'", $html);
    }

    public function testQuantityDiscountsInWholeAndDecimalNumbersKeepTheirDecimalsWithoutWarnings(): void
    {
        [$html, $problems] = self::quantityDiscounts(array('5', '2.25'), array('10', '25.5'));

        self::assertSame(array(), $problems);
        self::assertStringContainsString('name="m_rabat_array[0]" value=5>', $html);
        self::assertStringContainsString('name="m_antal_array[0]" value="10"', $html);
        self::assertStringContainsString('name="m_rabat_array[1]" value=2,25>', $html);
        self::assertStringContainsString('name="m_antal_array[1]" value="25,5"', $html);
        self::assertStringContainsString("name='m_rabat_array[2]'value='0'", $html);
    }

    public function testAWarehouseWithoutAStockRowShowsAnEmptyLocationWithoutWarnings(): void
    {
        // Hovedlager has stock and a location; Filial has never had a lagerstatus row for the item.
        ProductCardIncludesDb::$plan = array(
            array("/sum\(antal\) as antal from batch_kob where vare_id = '13' and lager = '1'/", array(array('antal' => '4'))),
            array("/sum\(antal\) as antal from batch_(kob|salg) where vare_id = '13'/", array(array('antal' => null))),
            array("/select lok1 from lagerstatus where vare_id = '13' and lager = '1'/", array(array('lok1' => 'A-12'))),
            array("/select lok1 from lagerstatus where vare_id = '13' and lager = '2'/", array()),
            array("/sum\(beholdning\) as beholdning from lagerstatus where vare_id = '13' and lager = '1'/", array(array('beholdning' => '4'))),
            array("/sum\(beholdning\) as beholdning from lagerstatus where vare_id = '13' and lager = '2'/", array(array('beholdning' => null))),
            array("/ordrer\.art = '(KO|DO)'/", array()),
            array('/from stocklog where item_id = 13/', array()),
        );
        [$html, $problems] = self::render('showLocations.php', array(
            'sprog_id' => 1, 'stockItem' => 'on', 'warehouseNames' => array(1 => 'Hovedlager', 2 => 'Filial'), 'id' => 13,
            'vare_varianter' => array(), 'variantVarerId' => array(), 'location' => '', 'folgevarenr' => '',
            'operation' => 0, 'montage' => 0, 'demontage' => 0, 'min_lager' => 0, 'max_lager' => 0, 'volume_lager' => 0,
            'beholdning' => 4, 'fifo' => null, 'samlevare' => '', 'bgcolor' => '#ffffff', 'bgcolor5' => '#e0e0f0',
            'provisionsfri' => '', 'shopurl' => null, 'shop_id' => null, 'publiceret' => '',
        ));

        self::assertSame(array(), $problems);
        self::assertStringContainsString('name="lagerlok[1]"value="A-12"', $html);
        self::assertStringContainsString('name="lagerlok[2]"value=""', $html);
    }
}
