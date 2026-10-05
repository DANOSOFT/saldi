<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/xml_class.php';
require_once __DIR__ . '/../../integration/SAF-T/SaftStream.php';

class SaftTestItem {
    public int $Id;
    public function __construct(int $id) {
        $this->Id = $id;
    }
}

class SaftTestStream extends SaftStream {
    public int $queries = 0;
    protected int $chunkSize = 500;

    public function __construct(SaftParameters $params, private int $total) {
        parent::__construct($params);
    }

    protected function fetchChunk(int|string|null $after, int $limit): array {
        $this->queries++;
        $rows = [];
        for ($id = ($after ?? 0) + 1; $id <= $this->total && count($rows) < $limit; $id++) {
            $rows[] = ['id' => $id];
        }
        return $rows;
    }

    protected function buildItem(array $row): object {
        return new SaftTestItem($row['id']);
    }
}

final class SaftStreamTest extends TestCase
{
    private function params(): SaftParameters
    {
        return new SaftParameters(1, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'));
    }

    public function testYieldsEveryRowInChunks(): void
    {
        $stream = new SaftTestStream($this->params(), 1200);
        $ids = [];
        foreach ($stream as $item) {
            $ids[] = $item->Id;
        }
        self::assertSame(range(1, 1200), $ids);
        self::assertSame(3, $stream->queries);
    }

    public function testExactMultipleOfChunkSizeEndsWithAnEmptyQuery(): void
    {
        $stream = new SaftTestStream($this->params(), 1000);
        self::assertCount(1000, iterator_to_array($stream, false));
        self::assertSame(3, $stream->queries);
    }

    public function testEmptyResult(): void
    {
        $stream = new SaftTestStream($this->params(), 0);
        self::assertSame([], iterator_to_array($stream, false));
        self::assertSame(1, $stream->queries);
    }

    public function testCanBeIteratedMoreThanOnce(): void
    {
        $stream = new SaftTestStream($this->params(), 600);
        self::assertCount(600, iterator_to_array($stream, false));
        self::assertCount(600, iterator_to_array($stream, false));
    }

    public function testXmlStreamWriterWritesEachItem(): void
    {
        $root = new stdClass();
        $root->Line = new SaftTestStream($this->params(), 1200);
        $file = tempnam(sys_get_temp_dir(), 'saft');
        try {
            (new xmlStreamWriter($file))->writeDocument($root, 'Root');
            $xml = simplexml_load_file($file);
            self::assertCount(1200, $xml->Line);
            self::assertSame('1200', (string) $xml->Line[1199]->Id);
        } finally {
            unlink($file);
        }
    }
}
