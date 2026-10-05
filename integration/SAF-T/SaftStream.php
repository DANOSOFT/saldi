<?php
include_once __DIR__ . '/SaftParameters.php';
include_once __DIR__ . '/../../includes/xml_class.php';
/**
 * Base class for the large SAF-T collections (ledger entries, invoices, ...) that must not be loaded into
 * memory at once.
 *
 * Put an instance in a property of the object tree. xmlStreamWriter iterates it and writes each item as it is
 * produced. Each foreach starts a fresh run (new queries), unlike a bare generator which can only be used once.
 *
 * As an xmlBufferedCollection it is iterated before its owner's other properties are written, so a property
 * declared above it can be a Closure that reads itemsWritten() or a subclass total, and gets the real value
 * for exactly the items that end up in the file:
 *
 *   public Closure|int $NumberOfEntries;   // = fn() => $this->Journal->itemsWritten()
 *   public SaftStream $Journal;
 *
 * A subclass supplies the query (fetchChunk) and the row-to-object mapping (buildItem).
 */
abstract class SaftStream implements IteratorAggregate, xmlBufferedCollection {
    /** Rows fetched per query. */
    protected int $chunkSize = 500;
    private int $itemsWritten = 0;

    public function __construct(protected readonly SaftParameters $params) {
    }

    /**
     * Fetches the next chunk, ordered by the cursor column, e.g. WHERE id > $after ORDER BY id LIMIT $limit.
     * Return fewer than $limit rows only when there are no more.
     *
     * @param int|string|null $after cursorOf() of the last row already handled, null for the first chunk
     * @param int $limit maximum number of rows to return
     * @return array<int,array<string,mixed>> database rows
     */
    abstract protected function fetchChunk(int|string|null $after, int $limit): array;

    /**
     * @param array<string,mixed> $row one row from fetchChunk()
     * @return object the SAF-T structure written for that row
     */
    abstract protected function buildItem(array $row): object;

    /**
     * @param array<string,mixed> $row one row from fetchChunk()
     * @return int|string value fetchChunk() pages by; the "id" column unless overridden
     */
    protected function cursorOf(array $row): int|string {
        return $row['id'];
    }

    /**
     * Called for every item just before it is yielded, e.g. to add to a debit/credit total. Totals should be
     * reset at the start of a run, see startRun().
     */
    protected function tally(object $item): void {
    }

    /**
     * Called at the start of every run, e.g. to reset the totals kept by tally().
     */
    protected function startRun(): void {
    }

    /**
     * @return int number of items yielded by the current or most recent run
     */
    public function itemsWritten(): int {
        return $this->itemsWritten;
    }

    public function getIterator(): Generator {
        $this->itemsWritten = 0;
        $this->startRun();
        $after = null;
        do {
            $rows = $this->fetchChunk($after, $this->chunkSize);
            foreach ($rows as $row) {
                $after = $this->cursorOf($row);
                $item = $this->buildItem($row);
                $this->tally($item);
                $this->itemsWritten++;
                yield $item;
            }
        } while (count($rows) === $this->chunkSize);
    }
}
?>
