<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/docsIncludes/poolArchive.php';

/**
 * poolArchiveAuditEntry() decides what an archive or restore leaves in audit_log, which is kept for five years.
 * SD-727: the date an archived document is deleted, when the database counts it as due, and what the deletion leaves in audit_log.
 */
final class poolArchive extends TestCase
{
    public function testArchiveRecordsWhenAndWho(): void
    {
        $entry = poolArchiveAuditEntry(['id' => '17', 'filename' => 'faktura.pdf', 'archived' => null, 'archived_by' => null], true, '2026-10-03 12:00:00', 4);
        $this->assertSame('document.archived', $entry['handling']);
        $this->assertSame('dokument', $entry['objekt_type']);
        $this->assertSame('17', $entry['objekt_id']);
        $this->assertSame(['filename' => 'faktura.pdf', 'archived' => null, 'archived_by' => null], $entry['detaljer']['before']);
        $this->assertSame(['filename' => 'faktura.pdf', 'archived' => '2026-10-03 12:00:00', 'archived_by' => 4], $entry['detaljer']['after']);
    }

    public function testRestoreKeepsTheArchiveDateInBefore(): void
    {
        $entry = poolArchiveAuditEntry(['id' => 17, 'filename' => 'faktura.pdf', 'archived' => '2026-09-01 08:30:00', 'archived_by' => '4'], false);
        $this->assertSame('document.restored', $entry['handling']);
        $this->assertSame(['filename' => 'faktura.pdf', 'archived' => '2026-09-01 08:30:00', 'archived_by' => 4], $entry['detaljer']['before']);
        $this->assertSame(['filename' => 'faktura.pdf', 'archived' => null, 'archived_by' => null], $entry['detaljer']['after']);
    }

    public function testRevisorSessionIsRecordedAsMinusOne(): void
    {
        $entry = poolArchiveAuditEntry(['id' => 3, 'filename' => 'x.pdf', 'archived' => null, 'archived_by' => null], true, '2026-10-03 12:00:00', -1);
        $this->assertSame(-1, $entry['detaljer']['after']['archived_by']);
    }

    public function testObjectIdIsAlwaysANumber(): void
    {
        $entry = poolArchiveAuditEntry(['id' => "5'; drop", 'filename' => 'x.pdf', 'archived' => null, 'archived_by' => null], true, '2026-10-03 12:00:00', 1);
        $this->assertSame('5', $entry['objekt_id']);
    }

    public function testDeleteDateIsTwelveMonthsAfterArchiving(): void
    {
        $this->assertSame('2027-10-04', poolArchiveDeleteDate('2026-10-04 13:45:12.123456'));
        $this->assertSame('2027-01-15', poolArchiveDeleteDate('2026-01-15'));
        $this->assertSame('2027-12-31', poolArchiveDeleteDate('2026-12-31 23:59:59'));
    }

    public function testDeleteDateAtTheEndOfAShortMonthAsTheDatabaseCountsIt(): void
    {
        // Postgres and MySQL: 2028-02-29 + 12 months = 2029-02-28, not 1 March
        $this->assertSame('2029-02-28', poolArchiveDeleteDate('2028-02-29 10:00:00'));
    }

    public function testNoDeleteDateWhenNotArchived(): void
    {
        $this->assertSame('', poolArchiveDeleteDate(null));
        $this->assertSame('', poolArchiveDeleteDate(''));
    }

    public function testDueConditionPerDatabase(): void
    {
        $GLOBALS['db_type'] = 'postgresql';
        $this->assertSame("archived IS NOT NULL AND archived + INTERVAL '12 months' <= CURRENT_TIMESTAMP", poolArchiveDueSql());
        $GLOBALS['db_type'] = 'mysqli';
        $this->assertSame('archived IS NOT NULL AND archived + INTERVAL 12 MONTH <= CURRENT_TIMESTAMP', poolArchiveDueSql());
        unset($GLOBALS['db_type']);
    }

    public function testPurgeRecordsFilenameArchiveDateAndDeletionDate(): void
    {
        $entry = poolArchivePurgeEntry(['id' => '17', 'filename' => 'faktura.pdf', 'archived' => '2025-09-01 08:30:00', 'archived_by' => '4'], '2026-10-04 12:00:00');
        $this->assertSame('document.purged', $entry['handling']);
        $this->assertSame('dokument', $entry['objekt_type']);
        $this->assertSame('17', $entry['objekt_id']);
        $this->assertSame(['filename' => 'faktura.pdf', 'archived' => '2025-09-01 08:30:00', 'archived_by' => 4], $entry['detaljer']['before']);
        $this->assertSame(['filename' => 'faktura.pdf', 'deleted' => '2026-10-04 12:00:00', 'reason' => 'archived 12 months'], $entry['detaljer']['after']);
    }
}
