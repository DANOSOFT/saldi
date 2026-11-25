<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Rental startup view dispatch (MB-111): query parameters that select no view,
 * such as ?inframe=1 from the main menu, must still render the daily overview.
 */
final class rentalInitView extends TestCase
{
    private function nodeBinary(): ?string
    {
        foreach (['/usr/local/bin/node', '/usr/bin/node'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }
        $found = trim((string) shell_exec('command -v node 2>/dev/null'));
        return $found !== '' ? $found : null;
    }

    /** Runs the real init block of rental/index.js with stubbed view functions. */
    private function dispatch(string $queryString): array
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('node is not available');
        }
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../rental/index.js'));
        $start = strpos($source, '// Init the view');
        $end = strrpos($source, '})()');
        $this->assertNotFalse($start, 'init block marker missing');
        $this->assertNotFalse($end, 'closing IIFE missing');
        $block = substr($source, $start, $end - $start);

        $script = <<<'JS'
const calls = []
const record = (name) => () => { calls.push(name) }
const createCalendar = record('createCalendar')
const productOverviewMonth = record('productOverviewMonth')
const createReservationList = record('createReservationList')
const monthlyOverview = record('monthlyOverview')
const showBooking = record('showBooking')
const showCustomer = record('showCustomer')
const singleProductOverview = record('singleProductOverview')
const queryString = process.argv[2] ?? ''
JS;
        $script .= "\n" . $block . "\nprocess.stdout.write(JSON.stringify(calls))\n";

        $command = escapeshellarg($node) . ' - ' . escapeshellarg($queryString);
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process, 'could not start node');
        fwrite($pipes[0], $script);
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $this->assertSame(0, $exitCode, 'node failed: ' . $stderr);

        $calls = json_decode($stdout, true);
        $this->assertIsArray($calls, 'unexpected node output: ' . $stdout);
        return $calls;
    }

    public function testInframeFromMainMenuShowsDailyOverview(): void
    {
        $this->assertSame(['createCalendar'], $this->dispatch('?inframe=1'));
    }

    public function testVareShowsProductOverview(): void
    {
        $this->assertSame(['productOverviewMonth'], $this->dispatch('?vare'));
    }

    public function testEmptyQueryShowsDailyOverview(): void
    {
        $this->assertSame(['createCalendar'], $this->dispatch(''));
    }
}
