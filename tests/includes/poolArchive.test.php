<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/docsIncludes/poolArchive.php';

/**
 * poolArchiveAuditEntry() decides what an archive or restore leaves in audit_log, which is kept for five years.
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
}
