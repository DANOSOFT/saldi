<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261113 CL/MMR SST-898 The visual form editor names the own mobile field "Egen mobilnr." (Morten 2026-11-13: «Bare "Mobilnr."»), like the classic form card (text 50).
//                 fe_var_map() and fe_var_label() are lifted from systemdata/formeditor.php (the page itself needs a login and a database) and evaluated in their own
//                 namespace; the field list, the chips and the JavaScript VAR_MAP all read fe_var_map(), which the source tests below pin.
final class FormeditorMobileLabelTest extends TestCase
{
    private const EDITOR = __DIR__ . '/../../../systemdata/formeditor.php';
    private const NS = 'FormeditorMobileLabelFixture';

    public static function setUpBeforeClass(): void
    {
        if (function_exists(self::NS . '\fe_var_label')) {
            return;
        }
        eval('namespace ' . self::NS . ';' . self::functionSource('fe_var_map') . "\n" . self::functionSource('fe_var_label'));
    }

    private static function label(string $token, int $sprogId): string
    {
        $GLOBALS['sprog_id'] = $sprogId;
        return (string)call_user_func(self::NS . '\fe_var_label', $token);
    }

    public function testTheOwnMobileFieldIsCalledEgenMobilnrInDanish(): void
    {
        self::assertSame('Egen mobilnr.', self::label('egen_mobile', 1));
    }

    public function testEnglishKeepsOwnMobile(): void
    {
        self::assertSame('Own mobile', self::label('egen_mobile', 2));
    }

    public function testTheVariableTokenIsUnchanged(): void
    {
        $map = call_user_func(self::NS . '\fe_var_map');
        self::assertSame(['Egen mobilnr.', 'Own mobile'], $map['egen_mobile']);
        self::assertArrayNotHasKey('egen_fax', $map);
        // the JavaScript VAR_MAP is this map as JSON
        self::assertStringContainsString('"egen_mobile":["Egen mobilnr.","Own mobile"]', (string)json_encode($map, JSON_UNESCAPED_UNICODE));
    }

    public function testFieldListChipsAndScriptReadTheSameMap(): void
    {
        $code = self::code();
        self::assertStringContainsString('foreach (fe_var_map() as $tok => $pair) {', $code, 'the field list');
        self::assertStringContainsString('var VAR_MAP  = <?php echo json_encode(fe_var_map()); ?> || {};', $code, 'the chips in the browser');
        self::assertStringNotContainsString("'Egen mobil'", $code);
    }

    public function testAnEnglishCaptionTranslatesMobilnr(): void
    {
        // TR_PAIRS: the first pair for a key wins, so "Mobile" back to Danish is "Mobilnr."
        $code = self::code();
        $a = strpos($code, "['Mobilnr.','Mobile']");
        $b = strpos($code, "['Mobil','Mobile']");
        self::assertNotFalse($a);
        self::assertNotFalse($b);
        self::assertLessThan($b, $a);
    }

    private static function code(): string
    {
        return (string)file_get_contents(self::EDITOR);
    }

    private static function functionSource(string $fn): string
    {
        $code = self::code();
        $a = strpos($code, "\nfunction $fn(");
        self::assertNotFalse($a, "$fn() is gone from systemdata/formeditor.php");
        $depth = 0;
        for ($i = strpos($code, '{', $a); $i < strlen($code); $i++) {
            if ($code[$i] === '{') $depth++;
            if ($code[$i] === '}' && --$depth === 0) {
                return substr($code, $a, $i - $a + 1);
            }
        }
        self::fail("$fn() has no end");
    }
}
