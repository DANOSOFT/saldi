<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * SD-786: the message "Kostprisen er anslået …" that deficit_cost_price() shows when a delivery
 * takes a cost price that no purchase lot (batch_kob) covers.
 *
 * Decided by Morten 2026-10-29: off by default; shown only in the relevant cases; a text that says
 * what it warns about. The cost price and the order log line are the same in every case.
 *
 * The database layer and settings are doubles; findtekst() returns the call's fallback text, which
 * the guardrails pin to the Danish column of importfiler/tekster.csv. dkdecimal() and jsString() are
 * the real ones, so the printed message is what a browser gets.
 *
 * History:
 * 20261029 CL/MMR Created (SD-786).
 */
#[RunTestsInSeparateProcesses]
final class CostEstimateNoticeTest extends TestCase
{
    /** Den-Tec's case (SST-795): bought at EUR 18.500 x 7,50, item card three years old. */
    private const PO_LINE = [
        'linje_id' => 27083, 'ordre_id' => 8469, 'pris' => '18500.000', 'rabat' => '0.000', 'antal' => '1.000',
        'modtaget' => '0.000', 'variant_id' => '', 'valutakurs' => '750.000', 'ordrenr' => '355',
    ];

    private static function boot(?string $setting, array $poRows, string $varekortpris = '88100.930', string $art = 'DO', string $varenr = '5314349'): void
    {
        $GLOBALS['__setting'] = $setting;            // NULL = never saved, as on every existing tenant
        $GLOBALS['__settingCalls'] = [];
        $GLOBALS['__po'] = $poRows;
        $GLOBALS['__vare'] = ['varenr' => $varenr, 'kostpris' => $varekortpris];
        $GLOBALS['__art'] = $art;
        $GLOBALS['db_encode'] = 'UTF8';
        eval('
            function db_select($q, $where = "") {
                if (stripos($q, "from varer") !== false) return (object) ["rows" => [$GLOBALS["__vare"]]];
                if (stripos($q, "join ordrer o on o.id = ol.ordre_id") !== false) return (object) ["rows" => [["art" => $GLOBALS["__art"]]]];
                return (object) ["rows" => $GLOBALS["__po"]];
            }
            function db_fetch_array($r) { return array_shift($r->rows) ?? false; }
            function findtekst($key, $sprog_id) { return explode("|", $key, 2)[1]; }
            function get_settings_value($name, $group, $default) {
                $GLOBALS["__settingCalls"][] = [$name, $group, $default];
                return ($name === "showCostEstimateNotice" && $group === "ordre" && $GLOBALS["__setting"] !== null) ? $GLOBALS["__setting"] : $default;
            }
            function afrund($n, $d) { return round((float) $n, (int) $d); }
        ');
        $root = dirname(__DIR__, 3);
        require $root . '/includes/stdFunc/dkDecimal.php';
        require $root . '/includes/stdFunc/jsString.php';
        require $root . '/includes/stdFunc/findOpenPurchaseCost.php';
    }

    /** @return array{pris: float, printed: string, log: string} */
    private static function deliver(int $linje_id = 44001, string $kontekst = 'salg fra negativ lagerbeholdning', $webservice = false): array
    {
        $fp = fopen('php://memory', 'w+');
        ob_start();
        $pris = deficit_cost_price(1096, $linje_id, 0, $fp, 1, $kontekst, $webservice);
        $printed = (string) ob_get_clean();
        rewind($fp);
        $log = (string) stream_get_contents($fp);
        fclose($fp);
        return ['pris' => (float) $pris, 'printed' => $printed, 'log' => $log];
    }

    /** @return string[] The texts of the printed jsString() literals, decoded. */
    private static function lines(string $printed): array
    {
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"/', $printed, $m);
        return array_map(static fn(string $lit): string => (string) json_decode($lit), $m[0]);
    }

    public function testOffByDefaultOnATenantThatNeverSavedTheSetting(): void
    {
        self::boot(null, [self::PO_LINE]);
        $r = self::deliver();
        self::assertSame('', $r['printed']);
        self::assertEqualsWithDelta(138750.0, $r['pris'], 0.0005, 'the open purchase line still prices the line');
        self::assertStringContainsString('source open purchase line 27083 on order 355', $r['log'], 'the order log is unchanged');
        self::assertContains(['showCostEstimateNotice', 'ordre', 'off'], $GLOBALS['__settingCalls'], 'read with default off');
    }

    public function testOffWhenTheBoxIsUnticked(): void
    {
        self::boot('off', [self::PO_LINE]);
        self::assertSame('', self::deliver()['printed']);
    }

    public function testOpenPurchaseLineThatDiffersFromTheItemCardIsShown(): void
    {
        // Den-Tec's shape: the item card said 88.100,93, the open purchase line 138.750,00
        self::boot('on', [self::PO_LINE]);
        $r = self::deliver();
        self::assertSame([
            'Kostprisen er anslået på disse varer, fordi der ikke var et købsparti at tage den fra:',
            '5314349: 138.750,00 fra åben indkøbsordre 355 (varekortet: 88.100,93)',
        ], self::lines($r['printed']));
        self::assertEqualsWithDelta(138750.0, $r['pris'], 0.0005);
    }

    public function testOpenPurchaseLineThatAgreesWithTheItemCardIsNotShown(): void
    {
        self::boot('on', [self::PO_LINE], '138750.000');
        $r = self::deliver();
        self::assertSame('', $r['printed'], 'nothing to look at: the price is the item card price');
        self::assertStringContainsString('source open purchase line 27083', $r['log']);
    }

    public function testItemCardPriceWithoutAnOpenPurchaseLineIsShown(): void
    {
        self::boot('on', []);
        $r = self::deliver();
        self::assertSame([
            'Kostprisen er anslået på disse varer, fordi der ikke var et købsparti at tage den fra:',
            '5314349: 88.100,93 fra varekortet. Der er ingen åben indkøbsordre, så prisen kan være forældet',
        ], self::lines($r['printed']));
        self::assertStringContainsString('source varer.kostpris', $r['log']);
    }

    public function testCreditNoteBranchShowsNothing(): void
    {
        self::boot('on', []);
        $r = self::deliver(44002, 'negativt salg/kreditnota');
        self::assertSame('', $r['printed']);
        self::assertStringContainsString('(negativt salg/kreditnota)', $r['log']);
    }

    public function testWebserviceDeliveryShowsNothing(): void
    {
        self::boot('on', []);
        self::assertSame('', self::deliver(44003, 'salg fra negativ lagerbeholdning', 'on')['printed']);
    }

    public function testPosSaleShowsNothing(): void
    {
        self::boot('on', [], '88100.930', 'PO');
        self::assertSame('', self::deliver()['printed']);
    }

    public function testOneMessagePerDeliveryListingEveryItem(): void
    {
        self::boot('on', []);
        $first = self::deliver(44001);
        $GLOBALS['__vare'] = ['varenr' => '500180', 'kostpris' => '108.670'];
        $second = self::deliver(44005);
        $printed = $first['printed'] . $second['printed'];
        self::assertSame(1, substr_count($printed, "addEventListener('load'"), 'one alert per delivery');
        self::assertSame(2, substr_count($printed, '.push('), 'one line per item');
        self::assertSame([
            'Kostprisen er anslået på disse varer, fordi der ikke var et købsparti at tage den fra:',
            '5314349: 88.100,93 fra varekortet. Der er ingen åben indkøbsordre, så prisen kan være forældet',
            '500180: 108,67 fra varekortet. Der er ingen åben indkøbsordre, så prisen kan være forældet',
        ], self::lines($printed));
        self::assertStringContainsString("alert(window.saldiCostNotice.join('\\n'))", $printed);
    }

    public function testAnItemNumberWithQuotesCannotBreakTheScript(): void
    {
        self::boot('on', [], '88100.930', 'DO', "AB'12\"</script><b>");
        $printed = self::deliver()['printed'];
        self::assertSame(2, substr_count($printed, '</script>'), 'only the two script tags this code prints');
        self::assertStringNotContainsString("AB'12", $printed);
        self::assertSame("AB'12\"</script><b>: 88.100,93 fra varekortet. Der er ingen åben indkøbsordre, så prisen kan være forældet", self::lines($printed)[1]);
    }
}
