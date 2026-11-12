<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261112 CL/SS MB-82 Pins how debitor/debitorkort.php uses debitorkortIncludes/staleSave.php.
//                The save is inline page code behind a POST, a session and a tenant database, so this checks the structure on tokens:
//                the card form carries recordHash, and a stale save is refused before the save writes anything.
//                It needs no database, so it also runs in CI.
final class DebitorkortStaleSaveWiringTest extends TestCase
{
    private const PAGE = __DIR__ . '/../../../debitor/debitorkort.php';

    /** @return list<array{0:int|string,1:string}> The page's tokens without comments and whitespace. */
    private static function tokens(): array
    {
        $out = [];
        foreach (token_get_all((string)file_get_contents(self::PAGE)) as $token) {
            if (is_array($token)) {
                if (!in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true)) {
                    $out[] = [$token[0], $token[1]];
                }
            } else {
                $out[] = [$token, $token];
            }
        }
        return $out;
    }

    /**
     * Index of the first token at or after $from whose text is $text.
     *
     * @param list<array{0:int|string,1:string}> $tokens
     */
    private static function find(array $tokens, string $text, int $from = 0): int
    {
        for ($i = $from; $i < count($tokens); $i++) {
            if ($tokens[$i][1] === $text) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * Index of the brace that closes the one at $open.
     *
     * @param list<array{0:int|string,1:string}> $tokens
     */
    private static function closingBrace(array $tokens, int $open): int
    {
        $depth = 0;
        for ($i = $open; $i < count($tokens); $i++) {
            if ($tokens[$i][1] === '{' || $tokens[$i][0] === T_CURLY_OPEN || $tokens[$i][0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } elseif ($tokens[$i][1] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return -1;
    }

    /**
     * The card form prints the hash of the stored customer next to its id.
     */
    public function testTheCardFormCarriesTheRecordHash(): void
    {
        $src = (string)file_get_contents(self::PAGE);
        self::assertStringContainsString("include_once(__DIR__ . '/debitorkortIncludes/staleSave.php');", $src);
        $taken = strpos($src, "\$recordHash = (\$id > 0) ? debitorkortRecordHash(\$id) : '';");
        $read = strpos($src, "select * from adresser where id = '\$id'");
        $form = strpos($src, 'print "<form name=debitorkort');
        $hash = strpos($src, "name=recordHash value='\$recordHash'");
        $end = strpos($src, 'print "</form>\n";');
        self::assertNotFalse($taken, 'recordHash is not taken');
        self::assertNotFalse($read, 'the card read of the customer not found');
        self::assertTrue($taken < $read, 'the hash must be taken before the customer is read, so a save in between is caught');
        self::assertNotFalse($form, 'card form not found');
        self::assertNotFalse($hash, 'recordHash field not found');
        self::assertTrue($form < $hash && $hash < (int)$end, 'recordHash must be printed inside the card form');
    }

    /**
     * The stale check is the first branch of the save, and that branch only shows the message.
     */
    public function testAStaleSaveIsRefusedBeforeAnyWrite(): void
    {
        $tokens = self::tokens();
        $start = self::find($tokens, '$is_grid_submission', self::find($tokens, '$is_grid_submission') + 1);
        self::assertGreaterThan(0, $start, 'start of the save not found');
        $guard = self::find($tokens, 'debitorkortSaveIsStale', $start);
        self::assertGreaterThan($start, $guard, 'the save does not call debitorkortSaveIsStale()');
        for ($i = $start; $i < $guard; $i++) {
            self::assertNotSame('db_modify', $tokens[$i][1], 'a write runs before the stale check');
        }
        $open = self::find($tokens, '{', $guard);
        $close = self::closingBrace($tokens, $open);
        $body = array_column(array_slice($tokens, $open + 1, $close - $open - 1), 1);
        self::assertContains('alert', $body, 'a refused save must show the message');
        self::assertNotContains('db_modify', $body, 'a refused save must not write');
        self::assertNotContains('include', $body, 'a refused save must not run other code');
        self::assertSame(T_ELSEIF, $tokens[$close + 1][0], 'the save must be the else-branch of the stale check');
    }
}
