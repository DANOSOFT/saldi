<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20260919 CDX/MJ Pins the transaction boundary around the delivery-address save in
//                  debitor/debitorkort.php. The save path cannot be executed here - it is inline
//                  page code behind a POST, a session and a tenant database - so this asserts the
//                  structural invariant instead: every write in the block sits between one begin
//                  and its commit, and nothing in between can skip the commit.
//
//                  That is a real invariant, not a restatement of the diff: adding a db_modify()
//                  after the commit, or an early exit inside the block, silently reintroduces the
//                  partial save this fixed, and both fail here.
// 20260920 CDX/MJ The control-flow and rollback checks were regexes and missed real cases -
//                  "return $value;", "break 2;" and a rollback guarded by some other condition all
//                  passed. Both now work on tokens. Raised by CodeRabbit on the PR.
final class DeliveryAddressSaveTransactionTest extends TestCase
{
    /** @return string The delivery-address save block, start comment to end comment. */
    private static function saveBlock(): string
    {
        $src = file_get_contents(__DIR__ . '/../../../debitor/debitorkort.php');
        $start = strpos($src, '// ---- Save delivery_addresses ----');
        self::assertNotFalse($start, 'start of the delivery-address save block not found');
        $end = strpos($src, '// ---- END Save delivery_addresses ----', $start);
        self::assertNotFalse($end, 'end of the delivery-address save block not found');
        return substr($src, $start, $end - $start);
    }

    /**
     * The block's tokens, comments and whitespace removed.
     *
     * Comments have to go because the explanatory comments in the source name db_modify() and
     * transaktion() themselves, and any scan would otherwise match those.
     *
     * @return list<array{0:int|string,1:string}> Normalised tokens: [id, text], id being a T_*
     *         constant or the literal character for single-character tokens.
     */
    private static function blockTokens(): array
    {
        $out = [];
        foreach (token_get_all('<?php ' . self::saveBlock()) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE, T_OPEN_TAG], true)) {
                    continue;
                }
                $out[] = [$token[0], $token[1]];
            } else {
                $out[] = [$token, $token];
            }
        }
        return $out;
    }

    /** @return string The block as code, comments stripped, for plain text assertions. */
    private static function saveBlockCode(): string
    {
        $code = '';
        foreach (token_get_all('<?php ' . self::saveBlock()) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }
        return $code;
    }

    /**
     * Index of the transaktion('<$action>') call in the token list.
     *
     * @param list<array{0:int|string,1:string}> $tokens
     * @param string $action begin, commit or rollback.
     * @return int Token index of the transaktion identifier itself.
     */
    private static function transaktionCall(array $tokens, string $action): int
    {
        foreach ($tokens as $i => $token) {
            if ($token[0] === T_STRING && $token[1] === 'transaktion'
                && isset($tokens[$i + 2]) && $tokens[$i + 1][1] === '('
                && trim($tokens[$i + 2][1], "'\"") === $action) {
                return $i;
            }
        }
        self::fail("transaktion('$action') not found in the save block");
    }

    /** The block must open and close exactly one transaction, with one rollback arm. */
    public function testTheBlockOpensAndClosesExactlyOneTransaction(): void
    {
        $block = self::saveBlockCode();
        self::assertSame(1, substr_count($block, "transaktion('begin')"), 'exactly one begin');
        self::assertSame(1, substr_count($block, "transaktion('commit')"), 'exactly one commit');
        self::assertSame(1, substr_count($block, "transaktion('rollback')"), 'exactly one rollback');
    }

    /** Every write must sit between the begin and the commit, or it is outside the transaction. */
    public function testEveryWriteHappensInsideTheTransaction(): void
    {
        $block = self::saveBlockCode();
        $begin = strpos($block, "transaktion('begin')");
        $commit = strpos($block, "transaktion('commit')");
        self::assertNotFalse($begin);
        self::assertNotFalse($commit);

        $writes = [];
        foreach (['db_modify(', '@db_modify(', 'pg_query('] as $needle) {
            $offset = 0;
            while (($at = strpos($block, $needle, $offset)) !== false) {
                $writes[] = $at;
                $offset = $at + 1;
            }
        }
        self::assertNotEmpty($writes, 'expected the save block to contain writes');

        foreach ($writes as $at) {
            self::assertGreaterThan($begin, $at, 'a write sits before the transaction opens');
            self::assertLessThan($commit, $at, 'a write sits after the transaction commits');
        }
    }

    /**
     * Nothing between begin and commit may jump past the commit and leave the transaction open.
     *
     * Token-based rather than a regex: the previous regex matched only `return;`/`exit;`/`exit(`
     * and so missed `return $value;`, `break 2;` and `return $x ? 1 : 2;`.
     *
     * break and continue are judged against the loop nesting inside the region, so an ordinary
     * `break` in an inner foreach is fine while `break 2` out of the region is not.
     */
    public function testNothingCanSkipTheCommit(): void
    {
        $tokens = self::blockTokens();
        $begin = self::transaktionCall($tokens, 'begin');
        $commit = self::transaktionCall($tokens, 'commit');
        self::assertLessThan($commit, $begin, 'begin should precede commit');

        $loopDepth = 0;
        $stack = [];
        $expectDoTail = false;
        $i = $begin;

        while ($i < $commit) {
            [$id, $text] = $tokens[$i];

            // The while of `do { ... } while (...);` closes a loop, it does not open one.
            if ($id === T_WHILE && $expectDoTail) {
                $expectDoTail = false;
                $i = self::skipParens($tokens, $i + 1, $commit);
                if ($i < $commit && $tokens[$i][1] === ';') {
                    $i++;
                }
                continue;
            }

            // Bind each loop keyword to its own body by stepping over the header first. Without
            // this a pending flag survives to the next unrelated '{' and inflates the depth, so a
            // break that really does escape the region passes the check below.
            if (in_array($id, [T_FOR, T_FOREACH, T_WHILE, T_SWITCH], true)) {
                $body = self::skipParens($tokens, $i + 1, $commit);
                self::assertTrue(
                    $body < $commit && $tokens[$body][1] === '{',
                    "unbraced body after '$text' is not modelled by this check - brace it, or extend the check"
                );
                $stack[] = 'loop';
                $loopDepth++;
                $i = $body + 1;
                continue;
            }
            if ($id === T_DO) {
                self::assertTrue(
                    isset($tokens[$i + 1]) && $tokens[$i + 1][1] === '{',
                    "unbraced body after 'do' is not modelled by this check - brace it, or extend the check"
                );
                $stack[] = 'do';
                $loopDepth++;
                $i += 2;
                continue;
            }

            if ($text === '{') {
                $stack[] = 'plain';
                $i++;
                continue;
            }
            if ($text === '}') {
                $kind = array_pop($stack);
                if ($kind === 'loop' || $kind === 'do') {
                    $loopDepth--;
                }
                if ($kind === 'do') {
                    $expectDoTail = true;
                }
                $i++;
                continue;
            }

            if ($id === T_RETURN || $id === T_EXIT) {
                self::fail("a '$text' between begin and commit would skip the commit");
            }
            if ($id === T_BREAK || $id === T_CONTINUE) {
                $level = (isset($tokens[$i + 1]) && $tokens[$i + 1][0] === T_LNUMBER)
                    ? (int)$tokens[$i + 1][1]
                    : 1;
                self::assertLessThanOrEqual(
                    $loopDepth,
                    $level,
                    "'$text $level' escapes the transaction region and would skip the commit"
                );
            }
            $i++;
        }

        // The alternative syntax has no braces, so the nesting count above would not see it.
        foreach ([T_ENDFOR, T_ENDFOREACH, T_ENDWHILE, T_ENDSWITCH] as $alt) {
            foreach (array_slice($tokens, $begin, $commit - $begin) as $token) {
                self::assertNotSame($alt, $token[0], 'alternative loop syntax is not handled by this check');
            }
        }
    }

    /**
     * Index just past the balanced parenthesis group starting at $i.
     *
     * @param list<array{0:int|string,1:string}> $tokens
     * @param int $i Index of the opening '(' (or of whatever precedes it, if there is none).
     * @param int $limit Index to stop at.
     * @return int Index of the first token after the group, or $limit.
     */
    private static function skipParens(array $tokens, int $i, int $limit): int
    {
        if ($i >= $limit || $tokens[$i][1] !== '(') {
            return $i;
        }
        $depth = 0;
        for (; $i < $limit; $i++) {
            if ($tokens[$i][1] === '(') {
                $depth++;
            } elseif ($tokens[$i][1] === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i + 1;
                }
            }
        }
        return $limit;
    }

    /**
     * The rollback must be guarded by the write-failure flag specifically.
     *
     * The webservice path returns from db_modify() instead of exiting, so without this guard a
     * half-written save would commit. Asserted as the exact construct: the previous regex allowed
     * any condition to sit between the flag and the rollback, so a rollback guarded by something
     * unrelated would have passed.
     */
    public function testTheWriteFailureFlagGatesTheCommit(): void
    {
        $normalised = preg_replace('/\s+/', ' ', self::saveBlockCode());
        self::assertStringContainsString(
            "if (!empty(\$db_modify_fejl)) { transaktion('rollback'); } else { transaktion('commit'); }",
            $normalised,
            'the rollback/commit choice should be made by !empty($db_modify_fejl) and nothing else'
        );
    }
}
