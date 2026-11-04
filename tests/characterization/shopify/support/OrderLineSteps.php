<?php
// tests/characterization/shopify/support/OrderLineSteps.php
//
// What the order-line and set tests share (SD-789): the steps run in a child
// (support/run_order_lines.php), an empty order, a line the way the order page adds it, and the
// lines read back. The using class sets self::$debtor in setUpBeforeClass().
//
// History:
// 20261102 CL/SS SD-789: created.

trait OrderLineSteps
{
    private static array $debtor;
    /** @var list<string> */
    private array $specFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->specFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->specFiles = [];
    }

    /** @return array<string, array{string}> */
    public static function copies(): array
    {
        return ['ordrefunc.php' => ['ordrefunc'], 'mysale/api.php' => ['mysale'], 'rental/rental.php' => ['rental']];
    }

    /** An open order without lines. */
    private static function order(?array $debtor = null, array $overrides = []): int
    {
        return self::$fx->order($debtor ?? self::$debtor, [], $overrides)['id'];
    }

    /**
     * Runs the steps in a child and returns its results.
     *
     * @param list<array> $steps
     * @return list<mixed>
     */
    private function runSteps(array $steps, array $globals = []): array
    {
        $file = (string)tempnam(sys_get_temp_dir(), 'sd789');
        $this->specFiles[] = $file;
        // the order page has the order's VAT rate in $momssats
        $globals += ['momssats' => 25];
        file_put_contents($file, json_encode(['regnaar' => self::$regnaar, 'globals' => $globals, 'steps' => $steps], JSON_THROW_ON_ERROR));
        return $this->runChildJson('run_order_lines.php', [$file, CharacterizationEnv::testDb()])['results'];
    }

    /**
     * One line the way the order page adds it: art DO, no line id, not a copy.
     *
     * @param array<int, mixed> $set Argument position => value, over the defaults.
     */
    private static function line(string $copy, int $orderId, array $item, $pris, array $set = []): array
    {
        $args = [$orderId, $item['id'], $item['varenr'], 1, '', $pris, 0, 100, 'DO', '', '', '0', '', '', '', '', 0, '', '', 1, 0];
        foreach ($set as $pos => $value) {
            $args[$pos] = $value;
        }
        return array_merge(['line', $copy], $args);
    }

    private static function lines(int $orderId): array
    {
        return self::rows("SELECT id, vare_id, antal, pris, rabat, rabatart, m_rabat, kostpris, momssats, saet, samlevare, lev_varenr, lager
            FROM ordrelinjer WHERE ordre_id = $1 ORDER BY id", [$orderId]);
    }

    /** The order's lines after their discounts, excl. VAT. */
    private static function net(array $lines): float
    {
        return array_sum(array_map(static fn($l) => (float)$l['antal'] * (float)$l['pris'] * (1 - (float)$l['rabat'] / 100), $lines));
    }
}
