<?php
// 20261127 CDX/NTR Accept an unlocked journal's empty timestamp without an undefined-key warning.

declare(strict_types=1);

namespace JournalLockTimestampFixture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/std_func.php';

function db_modify($sql, $context) { $GLOBALS['journal_timestamp_writes'][] = $sql; }
function findtekst($text, $language) { return ''; }

final class KassekladdeLockTimestampTest extends TestCase
{
    /** @return array<string, array{string, bool, bool}> */
    public static function timestamps(): array
    {
        return [
            'empty unlocked timestamp' => ['', false, false],
            'recent other owner' => ['0.12345600 1795784380', false, true],
            'recent same owner' => ['0.12345600 1795784380', true, false],
            'expired other owner' => ['0.12345600 1795780000', false, false],
            'previously rewritten timestamp' => ['-1 1795784380', false, true],
        ];
    }

    #[DataProvider('timestamps')]
    public function testTimestampParsingPreservesLockDecision(string $stored, bool $sameOwner, bool $locked): void
    {
        $source = file_get_contents(__DIR__ . '/../../../finans/kassekladde.php');
        $start = strpos($source, "\tif (isset(\$row['tidspkt'])) {", strpos($source, 'if ($tjek ='));
        self::assertNotFalse($start);
        $end = strpos($source, "\n\t/*", $start);
        self::assertNotFalse($end);
        $row = ['tidspkt' => $stored, 'hvem' => $sameOwner ? 'fixture' : 'other-fixture'];
        $brugernavn = 'fixture';
        $a = '0.98765400';
        $b = '1795784387';
        $tjek = 2;
        $sprog_id = 1;
        $popup = $visipop = false;
        $page_display = true;
        $GLOBALS['journal_timestamp_writes'] = [];
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . ';' . substr($source, $start, $end - $start));
        } finally {
            ob_end_clean();
            restore_error_handler();
        }
        self::assertSame($locked, $page_display);
        self::assertCount($locked ? 0 : 1, $GLOBALS['journal_timestamp_writes']);
        unset($GLOBALS['journal_timestamp_writes']);
    }
}
