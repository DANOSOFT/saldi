<?php
// 20261030 CDX/NTR Reject incomplete budget saves before bootstrapping or database writes.
// 20261105 CL/LH A grid post cut short by max_input_vars, or one whose budget_grid is not JSON, is refused the same way.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BudgetSubmissionTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        $this->fixture = sys_get_temp_dir() . '/saldi-budget-' . bin2hex(random_bytes(8));
        mkdir($this->fixture . '/finans', 0700, true);
        mkdir($this->fixture . '/includes', 0700);
        // Stop at the first include so these requests can never reach a real database.
        file_put_contents($this->fixture . '/includes/connect.php', '<?php exit("bootstrap reached");');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->fixture . '/sess_*') as $session) {
            unlink($session);
        }
        unlink($this->fixture . '/includes/connect.php');
        rmdir($this->fixture . '/includes');
        rmdir($this->fixture . '/finans');
        rmdir($this->fixture);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: int}> */
    public static function submissions(): array
    {
        return [
            'both counts missing' => [['gem' => '1'], 400],
            'account count missing' => [['gem' => '1', 'maanedantal' => '12'], 400],
            'month count missing' => [['gem' => '1', 'kontoantal' => '2'], 400],
            'null account count' => [['gem' => '1', 'kontoantal' => null, 'maanedantal' => '12'], 400],
            'null month count' => [['gem' => '1', 'kontoantal' => '2', 'maanedantal' => null], 400],
            'complete save' => [['gem' => '1', 'kontoantal' => '2', 'maanedantal' => '12'], 200],
            'zero counts are present' => [['gem' => '1', 'kontoantal' => '0', 'maanedantal' => '0'], 200],
            'normal page load' => [[], 200],
            'fill takes precedence over save' => [['gem' => '1', 'udfyld' => 'OK'], 200],
            // What PHP keeps of a long grid it cut at max_input_vars: the first fields, without the Gem, kontoantal
            // and maanedantal at the end of the form (testlab round 2, budget-cycle on a 309-account book).
            'grid cut short before the Gem' => [['plusminus' => '+', 'procent' => '0', 'kontonr' => ['1' => '1010', '2' => '1020'],
                'amount' => ['1' => ['1' => '5']], 'id' => ['1' => ['1' => '0']]], 400],
            'packed grid without the Gem' => [['budget_grid' => '{"kontonr":{"1":"1010"}}'], 400],
            'packed grid that is not JSON' => [['gem' => '1', 'kontoantal' => '1', 'maanedantal' => '12', 'budget_grid' => '{"kontonr":'], 400],
            'complete packed save' => [['gem' => '1', 'kontoantal' => '1', 'maanedantal' => '12',
                'budget_grid' => '{"kontonr":{"1":"1010"},"amount":{"1":{"1":"5"}},"id":{"1":{"1":"0"}}}'], 200],
            'fill with a cut grid still fills' => [['udfyld' => 'OK', 'kontonr' => ['1' => '1010']], 200],
        ];
    }

    /** @param array<string, mixed> $post */
    #[DataProvider('submissions')]
    public function testIncompleteSavesStopBeforeAnyIncludes(array $post, int $expectedStatus): void
    {
        $script = 'http_response_code(200);'
            . 'set_error_handler(static function ($severity, $message, $file, $line) {'
            . 'throw new ErrorException($message, 0, $severity, $file, $line); });'
            . 'register_shutdown_function(static function () { fwrite(STDERR, (string) http_response_code()); });'
            . '$_POST = ' . var_export($post, true) . ';'
            . 'require ' . var_export(dirname(__DIR__, 3) . '/finans/budget.php', true) . ';';
        $process = proc_open(
            [PHP_BINARY, '-d', 'session.save_path=' . $this->fixture, '-r', $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->fixture . '/finans'
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stdout . $stderr);
        self::assertSame((string) $expectedStatus, $stderr);
        if ($expectedStatus === 400) {
            self::assertStringNotContainsString('bootstrap reached', $stdout);
            self::assertNotSame('', $stdout);
        } else {
            self::assertSame('bootstrap reached', $stdout);
        }
    }
}
