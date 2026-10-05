<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SaftStreamTest.test.php';

class SaftBufferedStream extends SaftTestStream {
    public int $sum = 0;
    protected function startRun(): void {
        $this->sum = 0;
    }
    protected function tally(object $item): void {
        $this->sum += $item->Id;
    }
}

class SaftLedger {
    public string $Before = 'b';
    public Closure|int $NumberOfEntries;
    public Closure|int $Total;
    /** @var iterable<SaftTestItem> */
    public iterable $Entry;
    public ?string $After = null;
}

final class SaftBufferedWritingTest extends TestCase
{
    private function params(): SaftParameters
    {
        return new SaftParameters(1, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'));
    }

    /**
     * @return array{string,SaftBufferedStream}
     */
    private function writeBuffered(int $items, ?string $after = null): array
    {
        $stream = new SaftBufferedStream($this->params(), $items);
        $ledger = new SaftLedger();
        $ledger->NumberOfEntries = fn() => $stream->itemsWritten();
        $ledger->Total = fn() => $stream->sum;
        $ledger->Entry = $stream;
        $ledger->After = $after;
        $root = new stdClass();
        $root->Header = 'h';
        $root->Ledger = $ledger;
        $root->Footer = 'f';
        return [$this->write($root), $stream];
    }

    private function write(object $root): string
    {
        $file = tempnam(sys_get_temp_dir(), 'saft');
        try {
            (new xmlStreamWriter($file))->writeDocument($root, 'Root');
            return file_get_contents($file);
        } finally {
            unlink($file);
        }
    }

    /**
     * What the buffered output must look like: the same tree with the real numbers and a plain array.
     */
    private function writeUnbuffered(int $items, ?string $after = null): string
    {
        $entries = [];
        for ($id = 1; $id <= $items; $id++) {
            $entries[] = new SaftTestItem($id);
        }
        $ledger = new SaftLedger();
        $ledger->NumberOfEntries = $items;
        $ledger->Total = $items * ($items + 1) / 2;
        $ledger->Entry = $entries;
        $ledger->After = $after;
        $root = new stdClass();
        $root->Header = 'h';
        $root->Ledger = $ledger;
        $root->Footer = 'f';
        return $this->write($root);
    }

    public function testOutputIsIdenticalToUnbufferedOutputWithTheRealTotals(): void
    {
        foreach ([1, 3, 1200] as $items) {
            [$buffered] = $this->writeBuffered($items);
            self::assertSame($this->writeUnbuffered($items), $buffered, "$items items");
        }
    }

    public function testIdenticalWhenAnElementFollowsTheCollection(): void
    {
        [$buffered] = $this->writeBuffered(3, 'after');
        self::assertSame($this->writeUnbuffered(3, 'after'), $buffered);
    }

    public function testEmptyCollection(): void
    {
        [$buffered] = $this->writeBuffered(0);
        self::assertSame($this->writeUnbuffered(0), $buffered);
        self::assertSame('0', (string) simplexml_load_string($buffered)->Ledger->NumberOfEntries);
    }

    public function testTotalsComeFromWhatWasWritten(): void
    {
        [$xml, $stream] = $this->writeBuffered(1200);
        $ledger = simplexml_load_string($xml)->Ledger;
        self::assertSame('1200', (string) $ledger->NumberOfEntries);
        self::assertSame((string) (1200 * 1201 / 2), (string) $ledger->Total);
        self::assertCount(1200, $ledger->Entry);
        self::assertSame(1200, $stream->itemsWritten());
    }

    public function testTemporaryFileIsRemoved(): void
    {
        $dir = sys_get_temp_dir() . '/saftbuf' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            $stream = new SaftBufferedStream($this->params(), 600);
            $root = new stdClass();
            $root->Entry = $stream;
            (new xmlStreamWriter("$dir/out.xml"))->writeDocument($root, 'Root');
            self::assertSame(['out.xml'], array_values(array_diff(scandir($dir), ['.', '..'])));
        } finally {
            array_map('unlink', glob("$dir/*"));
            rmdir($dir);
        }
    }

    public function testTemporaryFileIsRemovedWhenTheStreamFails(): void
    {
        $dir = sys_get_temp_dir() . '/saftbuf' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            $failing = new class($this->params(), 10) extends SaftTestStream {
                protected function buildItem(array $row): object {
                    if ($row['id'] === 5) {
                        throw new RuntimeException('boom');
                    }
                    return parent::buildItem($row);
                }
            };
            $root = new stdClass();
            $root->Entry = $failing;
            try {
                (new xmlStreamWriter("$dir/out.xml"))->writeDocument($root, 'Root');
                self::fail('Expected exception');
            } catch (RuntimeException $e) {
                self::assertSame('boom', $e->getMessage());
            }
            self::assertSame(['out.xml'], array_values(array_diff(scandir($dir), ['.', '..'])));
        } finally {
            array_map('unlink', glob("$dir/*"));
            rmdir($dir);
        }
    }
}
