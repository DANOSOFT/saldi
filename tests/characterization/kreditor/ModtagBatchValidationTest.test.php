<?php
// 20260917 SZ MB-36: Cover the "Batchoplysninger ikke udfyldt" receipt hard-stop in
// kreditor/modtag.php so a future edit can't silently loosen or drop it. Also covers the
// split gating added the same day: batch_due_date is required only when item_has_due_date()
// is true, batch_no is required when that OR the item's group has box9='on'.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// Runs in a separate process per test: other suites in the same run may already have
// loaded the real item_has_due_date()/db_select() (includes/stdFunc/fefo.php,
// includes/db_query.php), which would make the function_exists() guards below skip the
// stubs and hit a real (absent) database connection.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ModtagBatchValidationTest extends TestCase
{
    private static string $source;

    public static function setUpBeforeClass(): void
    {
        self::$source = file_get_contents(dirname(__DIR__, 3) . '/kreditor/modtag.php');
    }

    protected function setUp(): void
    {
        if (!function_exists('item_has_due_date')) {
            // Real function lives in includes/stdFunc/fefo.php and queries varer.has_due_date;
            // stubbed here so the extracted block can be exercised without a database.
            function item_has_due_date($vare_id) {
                return (bool) ($GLOBALS['modtagHasDueDate'] ?? false);
            }
        }
        if (!function_exists('db_select')) {
            // Stubs the box9 lookup the extracted block performs directly; the query text
            // itself isn't parsed, only the fixture's box9 value is returned.
            function db_select($qtxt, $spor) {
                return ['box9' => $GLOBALS['modtagBox9'] ?? null];
            }
            function db_fetch_array($q) {
                return $q;
            }
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['modtagHasDueDate'], $GLOBALS['modtagBox9']);
    }

    // Execute the production condition, not a second implementation of it.
    private function isBlocked(float $leveres, string $art, ?string $dueDate, ?string $batchNo): bool
    {
        $start = strpos(self::$source, '$dueDateTracked = item_has_due_date(');
        self::assertNotFalse($start, 'Batch-tracking checks must still exist in kreditor/modtag.php.');
        $end = strpos(self::$source, '){', $start);
        self::assertNotFalse($end, 'Validation if-block must still exist in kreditor/modtag.php.');
        $block = substr(self::$source, $start, $end - $start);

        $x = 1;
        $regnaar = '2026';
        $leveres = [1 => $leveres];
        $art = $art;
        $vare_id = [1 => 1];
        $batch_due_date = [1 => $dueDate];
        $batch_batch_no = [1 => $batchNo];

        eval($block . ') { $blocked = true; } else { $blocked = false; }');
        return $blocked;
    }

    public static function cases(): array
    {
        return [
            'has_due_date: both empty' => [1.0, 'KO', true, false, null, null, true],
            'has_due_date: due date missing only' => [1.0, 'KO', true, false, '', '123', true],
            'has_due_date: batch no missing only' => [1.0, 'KO', true, false, '2026-12-01', '', true],
            'has_due_date: both filled' => [1.0, 'KO', true, false, '2026-12-01', '123', false],
            'neither has_due_date nor box9' => [1.0, 'KO', false, false, null, null, false],
            'credit note line (KK) is exempt' => [1.0, 'KK', true, false, null, null, false],
            'nothing delivered yet' => [0.0, 'KO', true, false, null, null, false],
            'negative delivery (return) is exempt' => [-1.0, 'KO', true, false, null, null, false],
            'box9 only: batch no filled, due date empty is fine' => [1.0, 'KO', false, true, null, '123', false],
            'box9 only: batch no empty is still blocked' => [1.0, 'KO', false, true, null, null, true],
            'box9 only: due date filled does not matter' => [1.0, 'KO', false, true, '2026-12-01', '123', false],
            'has_due_date and box9 both on: due date still required' => [1.0, 'KO', true, true, '', '123', true],
        ];
    }

    #[DataProvider('cases')]
    public function testBatchInfoRequiredExactlyWhenTrackedAndDelivered(
        float $leveres,
        string $art,
        bool $hasDueDate,
        bool $box9,
        ?string $dueDate,
        ?string $batchNo,
        bool $expectedBlocked
    ): void {
        $GLOBALS['modtagHasDueDate'] = $hasDueDate;
        $GLOBALS['modtagBox9'] = $box9 ? 'on' : '';
        self::assertSame($expectedBlocked, $this->isBlocked($leveres, $art, $dueDate, $batchNo));
    }
}
