<?php
// tests/characterization/debitor/SetItemLinePositionTest.test.php
//
// SST-1002: with Brug samlet pris on, ordrelinjer() in debitor/ordre.php gave no position number to a line whose
// samlevare is 'on', taking it for the head of a set. A line of a set item that is not a set (saet 0), such as a
// variant of the set item, then got an empty Pos field, and the next Gem stopped with "Unsupported operand types:
// string * int" where the position is read back. The rule is lifted from the page and run here.
//
// History:
// 20261111 CL/MJ SST-1002 Created.
// 20261112 CL/MJ SST-1002 The header goes directly below the opening tag (CodeRabbit).

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SetItemLinePositionTest extends TestCase
{
    private const ORDRE = __DIR__ . '/../../../debitor/ordre.php';

    /** @return array<string, array{string, string, int, bool}> samlevare, brugsamletpris, saet, gets a number */
    public static function lines(): array
    {
        return [
            'set item line that is not a set, Brug samlet pris on' => ['on', 'on', 0, true],
            'set head, Brug samlet pris on'                        => ['on', 'on', 3, false],
            'set item line, Brug samlet pris off'                  => ['on', '', 0, true],
            'ordinary line, Brug samlet pris on'                   => ['', 'on', 0, true],
            'component of a set, Brug samlet pris on'              => ['', 'on', 3, true],
        ];
    }

    #[DataProvider('lines')]
    public function testOnlyASetHeadGoesWithoutAPositionNumber(string $samlevare, string $brugsamletpris, int $saet, bool $numbered): void
    {
        $rule = self::line((string)file_get_contents(self::ORDRE), '$ny_pos++; #20150317');

        $ny_pos = 4;
        eval($rule);

        self::assertSame($numbered ? 5 : 4, $ny_pos);
    }

    /** The single source line that contains $needle, its trailing # comment stripped. */
    private static function line(string $src, string $needle): string
    {
        $hits = array_values(array_filter(explode("\n", $src), static fn ($l) => strpos($l, $needle) !== false));
        self::assertCount(1, $hits, "expected one line with $needle in debitor/ordre.php");
        return (string)preg_replace('/\s#.*$/', '', $hits[0]);
    }
}
