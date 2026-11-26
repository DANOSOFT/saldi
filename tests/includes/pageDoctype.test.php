<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stdFunc/pageDoctype.php';

/**
 * saldi_page_doctype() from includes/stdFunc/pageDoctype.php (MB-143).
 *
 * The legacy doctype must stay byte for byte the same, because every page that does not opt in
 * depends on the quirks mode it triggers. A system identifier would switch those pages to
 * limited-quirks mode, where their height="100%" tables collapse.
 */
final class pageDoctype extends TestCase
{
    public function testPagesThatDoNotOptInKeepTheQuirksModeDoctype(): void
    {
        self::assertSame(
            '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">',
            saldi_page_doctype(false)
        );
    }

    public function testPagesThatOptInGetTheStandardsModeDoctype(): void
    {
        self::assertSame('<!DOCTYPE html>', saldi_page_doctype(true));
    }
}
