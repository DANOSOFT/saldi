<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/std_func.php';

// 20261027 CL/LH PR #681: a plukliste is a whole-order document, so udskriftsvalg.php sends
//                formularprint.php no lev_nr (and usually no locat). The page read them with
//                if_isset($_GET['lev_nr']), which evaluates the missing offset before the call
//                and warned on every plukliste print. formularprint.php is a page script that
//                opens a session, so its four optional reads are lifted from the source and run
//                here with warnings turned into exceptions.
final class FormularprintOptionalParamsTest extends TestCase
{
    private const PAGE = __DIR__ . '/../../../debitor/formularprint.php';

    /**
     * The four optional GET reads at the top of the id branch, verbatim from the page.
     *
     * @return string PHP statements, one per line.
     */
    private static function optionalReads(): string
    {
        $src = file_get_contents(self::PAGE);
        self::assertNotFalse($src, 'could not read debitor/formularprint.php');

        $start = strpos($src, "if (isset(\$_GET['id']) && \$_GET['id']){");
        self::assertNotFalse($start, 'the id branch of formularprint.php is gone');
        $end = strpos($src, '// Get order', $start);
        self::assertNotFalse($end, 'the end of the GET reads in formularprint.php moved');

        preg_match_all('/^\s*\$(formular|lev_nr|udskriv_til|locat) = [^\n]*;$/m',
            substr($src, $start, $end - $start), $m);
        self::assertSame(array('formular', 'lev_nr', 'udskriv_til', 'locat'), $m[1],
            'expected the four optional GET reads in formularprint.php');

        return implode("\n", array_map('trim', $m[0]));
    }

    /**
     * Runs the lifted reads against $get with every PHP warning or notice thrown.
     *
     * @param array<string, string> $get The request's GET parameters.
     * @return array{
     *   formular: mixed,     Value read for formular.
     *   lev_nr: mixed,       Value read for lev_nr.
     *   udskriv_til: mixed,  Value read for udskriv_til.
     *   locat: mixed,        Value read for locat.
     * }
     */
    private static function read(array $get): array
    {
        $code = self::optionalReads();
        $savedGet = $_GET;
        $_GET = $get;
        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            throw new ErrorException($str, 0, $no, $file, $line);
        });
        try {
            $formular = $lev_nr = $udskriv_til = $locat = 'unset';
            eval($code);
            return array(
                'formular' => $formular,
                'lev_nr' => $lev_nr,
                'udskriv_til' => $udskriv_til,
                'locat' => $locat,
            );
        } finally {
            restore_error_handler();
            $_GET = $savedGet;
        }
    }

    /** The plukliste request: no lev_nr, no locat, no udskriv_til. Nothing may warn. */
    public function testAPluklisteRequestWithoutLevNrDoesNotWarn(): void
    {
        $read = self::read(array('id' => '4711', 'formular' => '9'));

        self::assertSame('9', $read['formular']);
        self::assertNull($read['lev_nr']);
        self::assertNull($read['udskriv_til']);
        self::assertNull($read['locat']);
    }

    /** A delivery note sends all four; they arrive unchanged. */
    public function testParametersThatArePresentAreReadUnchanged(): void
    {
        $read = self::read(array(
            'id' => '4711',
            'formular' => '3',
            'lev_nr' => '2',
            'udskriv_til' => 'PDF',
            'locat' => 'A1',
        ));

        self::assertSame(array('formular' => '3', 'lev_nr' => '2', 'udskriv_til' => 'PDF', 'locat' => 'A1'), $read);
    }
}
