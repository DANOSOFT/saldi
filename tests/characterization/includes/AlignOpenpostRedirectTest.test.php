<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20260921 CDX/MJ MB-57 Pins the redirect doAlign.php sends after a successful udligning, the
//                  missing URL= on the fiscal-year path, and the absence of debug output. The file
//                  is an include that runs inside udlign_openpost.php's request, so the redirect
//                  block is lifted out and evaluated with the variables it reads.
final class AlignOpenpostRedirectTest extends TestCase
{
    private const DO_ALIGN = __DIR__ . '/../../../includes/alignOpenpostIncludes/doAlign.php';

    /**
     * Builds the success redirect for a given $retur, running the real block from doAlign.php.
     *
     * @param string $retur The caller's return target, as generalLedger.php sets it.
     * @return string The emitted meta refresh.
     */
    private static function redirectFor(string $retur): string
    {
        $src = file_get_contents(self::DO_ALIGN);
        $start = strpos($src, "if (strpos((string) \$retur, 'debitorkort.php') !== false) {");
        self::assertNotFalse($start, 'the redirect branch was not found');
        $end = strpos($src, 'print "<meta', $start);
        self::assertNotFalse($end, 'the meta refresh was not found');
        // To the end of that line, not the next ';' - the first semicolon is inside
        // content="0;URL=..." and cutting there would leave an unterminated string.
        $end = strpos($src, "\n", $end);
        self::assertNotFalse($end);
        $block = substr($src, $start, $end - $start);

        $dato_fra = '01-01-2026';
        $dato_til = '31-12-2026';
        $konto_fra = '165320';
        $konto_til = '165320';
        $layout = 'grid';
        $returside = '../debitor/generalLedger.php?id=42&konto=165320';

        ob_start();
        try {
            eval($block);
        } finally {
            $out = ob_get_clean();
        }
        return (string) $out;
    }

    /** The bug: coming from the debtor card must not redirect back to a card with no id. */
    public function testDebtorCardCallerGoesToTheKontokortReport(): void
    {
        $html = self::redirectFor('../debitor/debitorkort.php');

        self::assertStringContainsString('rapportart=kontokort', $html);
        self::assertStringNotContainsString('debitorkort.php', $html, 'the blank-card target must be gone');
        self::assertStringContainsString('konto_fra=165320', $html);
        self::assertStringContainsString('konto_til=165320', $html);
        self::assertStringContainsString('submit=ok', $html);
        self::assertStringContainsString('URL=', $html, 'a meta refresh without URL= goes nowhere');
    }

    /** Every other caller keeps today's target - the report flow must be unchanged. */
    public function testReportCallerIsUnchanged(): void
    {
        $html = self::redirectFor('../debitor/rapport.php');

        self::assertStringContainsString('../debitor/rapport.php?rapportart=accountChart', $html);
        self::assertStringNotContainsString('rapportart=kontokort', $html);
        self::assertStringContainsString('dato_fra=01-01-2026', $html);
        self::assertStringContainsString('layout=grid', $html);
    }

    /** The return path is a path, not a parameter: urlencoding it would escape the slashes. */
    public function testTheReturnPathIsNotUrlEncoded(): void
    {
        $html = self::redirectFor('../debitor/rapport.php');
        self::assertStringNotContainsString('%2F', $html, 'the target path must keep its slashes');
        self::assertStringNotContainsString('..%2F', $html);
    }

    /** The returside is a parameter and must be encoded, since it carries its own query string. */
    public function testTheReturnsideParameterIsEncoded(): void
    {
        $html = self::redirectFor('../debitor/debitorkort.php');
        self::assertStringContainsString('returside=' . urlencode('../debitor/generalLedger.php?id=42&konto=165320'), $html);
    }

    /** Both meta refreshes in the file must carry URL=, or the browser goes nowhere. */
    public function testEveryMetaRefreshHasAUrl(): void
    {
        $src = file_get_contents(self::DO_ALIGN);
        $refreshes = [];
        preg_match_all('/content=\\\\"0;([^"]*)/', $src, $refreshes);
        self::assertNotEmpty($refreshes[1], 'no meta refresh found');
        foreach ($refreshes[1] as $target) {
            self::assertStringStartsWith('URL=', $target, "a meta refresh is missing URL=: $target");
        }
    }

    /** No debug output: one echo printed the server's absolute path, four printed raw SQL. */
    public function testNoDebugOutputRemains(): void
    {
        $src = file_get_contents(self::DO_ALIGN);
        self::assertStringNotContainsString('echo __file__', $src, 'this leaked the server path to the browser');
        self::assertStringNotContainsString('echo "$qtxt', $src, 'this leaked raw SQL to the browser');
        self::assertSame(0, preg_match('/^\s*echo /m', $src), 'no bare echo should remain');
    }

    /** The openpost id reaches SQL, so it must be cast. */
    public function testTheOpenpostIdIsIntCast(): void
    {
        $src = file_get_contents(self::DO_ALIGN);
        self::assertStringContainsString('(int) $post_id[$x]', $src);
        self::assertStringNotContainsString('where id = $post_id[$x]', $src);
    }

    /** The dead duplicates of this file are gone, so nobody fixes the wrong copy next time. */
    #[DataProvider('deadFiles')]
    public function testDeadDuplicatesAreRemoved(string $path): void
    {
        self::assertFileDoesNotExist(__DIR__ . '/../../../' . $path);
    }

    public static function deadFiles(): array
    {
        return [
            'legacy debtor page'      => ['debitor/udlign_openpost.php'],
            'copy of doAlign'         => ['includes/alignOpenpostIncludes/findAlignDate.php'],
            'unreachable doAlign'     => ['alignOpenpostIncludes/doAlign.php'],
            'unreachable findAlign'   => ['alignOpenpostIncludes/findAlignDate.php'],
            'unreachable findMatch'   => ['alignOpenpostIncludes/findMatch.php'],
        ];
    }
}
