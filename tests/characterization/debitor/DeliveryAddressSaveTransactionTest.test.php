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
// 20261105 CL/LH The block's transaction is now the card's: account_card_begin_save() opens it with a lock on
//                  the account before the card is compared, and account_card_end_save() commits it, or rolls it
//                  back on $db_modify_fejl, once the contact persons are written too (testlab concurrent-editing
//                  DEVY-2). The same invariant is pinned at that boundary: the block opens and ends no
//                  transaction of its own, which would let the lock go halfway through the save, and nothing
//                  from the block to the card's commit can leave without ending the save.
final class DeliveryAddressSaveTransactionTest extends TestCase
{
    private const PAGE = __DIR__ . '/../../../debitor/debitorkort.php';
    private const HELPER = __DIR__ . '/../../../includes/stdFunc/accountCardVersion.php';

    /**
     * The page's tokens without comments and whitespace, and where the delivery-address save block starts and ends.
     *
     * Comments have to go because the explanatory comments in the source name db_modify() and
     * transaktion() themselves, and any scan would otherwise match those. The block is marked by
     * its start and end comments, so their positions are kept.
     *
     * @return array{tokens: list<array{0:int|string,1:string}>, start:int, end:int}
     */
    private static function page(): array
    {
        $tokens = [];
        $start = $end = null;
        foreach (token_get_all((string)file_get_contents(self::PAGE)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                if (str_contains($token[1], '---- Save delivery_addresses ----')) {
                    $start = count($tokens);
                } elseif (str_contains($token[1], '---- END Save delivery_addresses ----')) {
                    $end = count($tokens);
                }
                continue;
            }
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }
            $tokens[] = is_array($token) ? [$token[0], $token[1]] : [$token, $token];
        }
        self::assertNotNull($start, 'start of the delivery-address save block not found');
        self::assertNotNull($end, 'end of the delivery-address save block not found');
        self::assertLessThan($end, $start);
        return ['tokens' => $tokens, 'start' => $start, 'end' => $end];
    }

    /**
     * Indexes of the calls of a function in the token list.
     *
     * @param list<array{0:int|string,1:string}> $tokens
     * @return list<int> Token index of each call's function name.
     */
    private static function calls(array $tokens, string $function): array
    {
        $found = [];
        foreach ($tokens as $i => $token) {
            if ($token[0] === T_STRING && $token[1] === $function && isset($tokens[$i + 1]) && $tokens[$i + 1][1] === '(') {
                $found[] = $i;
            }
        }
        return $found;
    }

    /**
     * The card's commit after the block: the last account_card_end_save() without arguments, on the way out of a
     * save that wrote the card.
     */
    private static function cardCommit(array $tokens, int $after): int
    {
        $commits = array_values(array_filter(
            self::calls($tokens, 'account_card_end_save'),
            static fn(int $i): bool => $i > $after && $tokens[$i + 2][1] === ')'
        ));
        self::assertNotEmpty($commits, 'no account_card_end_save() after the delivery-address save block');
        return end($commits);
    }

    /** The block opens, commits and rolls back no transaction of its own: it runs in the card's. */
    public function testTheBlockOpensAndEndsNoTransactionOfItsOwn(): void
    {
        ['tokens' => $tokens, 'start' => $start, 'end' => $end] = self::page();
        $commit = self::cardCommit($tokens, $end);
        foreach (self::calls($tokens, 'transaktion') as $i) {
            $this->assertFalse($i >= $start && $i <= $commit, "transaktion({$tokens[$i + 2][1]}) between the delivery addresses and the card's commit would end the card's transaction and let the account's lock go");
        }
    }

    /** Every write in the block must sit between the card's begin and its commit, or it is outside the transaction. */
    public function testEveryWriteHappensInsideTheTransaction(): void
    {
        ['tokens' => $tokens, 'start' => $start, 'end' => $end] = self::page();
        $begins = self::calls($tokens, 'account_card_begin_save');
        $this->assertCount(1, $begins, 'the card opens its transaction in one place');
        $commit = self::cardCommit($tokens, $end);

        $writes = array_merge(self::calls($tokens, 'db_modify'), self::calls($tokens, 'pg_query'));
        $inBlock = array_filter($writes, static fn(int $i): bool => $i > $start && $i < $end);
        $this->assertNotEmpty($inBlock, 'expected the save block to contain writes');
        foreach ($inBlock as $i) {
            $this->assertGreaterThan($begins[0], $i, 'a write sits before the transaction opens');
            $this->assertLessThan($commit, $i, 'a write sits after the transaction commits');
        }
    }

    /**
     * Nothing from the block to the card's commit may leave without ending the save, which would leave the
     * transaction open until the connection closes and the server discards the save.
     *
     * Token-based rather than a regex: the previous regex matched only `return;`/`exit;`/`exit(`
     * and so missed `return $value;`, `break 2;` and `return $x ? 1 : 2;`.
     *
     * An exit or return is allowed once account_card_end_save() has been called in its own block (the refused
     * Pos. numbers answer and leave that way). break and continue are judged against the loop nesting inside the
     * region, so an ordinary `break` in an inner foreach is fine while `break 2` out of the region is not.
     */
    public function testNothingCanSkipTheCommit(): void
    {
        ['tokens' => $tokens, 'start' => $start, 'end' => $end] = self::page();
        $commit = self::cardCommit($tokens, $end);

        $loopDepth = 0;
        $stack = [['kind' => 'region', 'ended' => false]];
        $expectDoTail = false;
        $i = $start;

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
                $this->assertTrue(
                    $body < $commit && $tokens[$body][1] === '{',
                    "unbraced body after '$text' is not modelled by this check - brace it, or extend the check"
                );
                $stack[] = ['kind' => 'loop', 'ended' => end($stack)['ended']];
                $loopDepth++;
                $i = $body + 1;
                continue;
            }
            if ($id === T_DO) {
                $this->assertTrue(
                    isset($tokens[$i + 1]) && $tokens[$i + 1][1] === '{',
                    "unbraced body after 'do' is not modelled by this check - brace it, or extend the check"
                );
                $stack[] = ['kind' => 'do', 'ended' => end($stack)['ended']];
                $loopDepth++;
                $i += 2;
                continue;
            }

            if ($text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $stack[] = ['kind' => 'plain', 'ended' => end($stack)['ended']];
                $i++;
                continue;
            }
            if ($text === '}') {
                $this->assertGreaterThan(1, count($stack), "the block and the card's commit are not in the same block of the page");
                $kind = array_pop($stack)['kind'];
                if ($kind === 'loop' || $kind === 'do') {
                    $loopDepth--;
                }
                if ($kind === 'do') {
                    $expectDoTail = true;
                }
                $i++;
                continue;
            }

            if ($id === T_STRING && $text === 'account_card_end_save' && $tokens[$i + 1][1] === '(') {
                $stack[count($stack) - 1]['ended'] = true;
            }
            if ($id === T_RETURN || $id === T_EXIT) {
                $this->assertTrue(end($stack)['ended'], "a '$text' between the delivery addresses and the card's commit would skip the commit");
            }
            if ($id === T_BREAK || $id === T_CONTINUE) {
                $level = (isset($tokens[$i + 1]) && $tokens[$i + 1][0] === T_LNUMBER)
                    ? (int)$tokens[$i + 1][1]
                    : 1;
                $this->assertLessThanOrEqual(
                    $loopDepth,
                    $level,
                    "'$text $level' escapes the transaction region and would skip the commit"
                );
            }
            $i++;
        }

        // The alternative syntax has no braces, so the nesting count above would not see it.
        foreach (array_slice($tokens, $start, $commit - $start) as $token) {
            $this->assertNotContains($token[0], [T_ENDFOR, T_ENDFOREACH, T_ENDWHILE, T_ENDSWITCH, T_ENDIF], 'alternative syntax is not handled by this check');
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
     * half-written save would commit. Asserted as the exact construct in account_card_end_save(),
     * which ends the card's transaction: a rollback guarded by something unrelated must not pass.
     */
    public function testTheWriteFailureFlagGatesTheCommit(): void
    {
        $source = (string)file_get_contents(self::HELPER);
        $this->assertSame(1, preg_match('/\nfunction account_card_end_save\(.*?\n}\n/s', $source, $function), 'account_card_end_save() not found');
        $normalised = preg_replace('/\s+/', ' ', $function[0]);
        $gate = "if (!empty(\$db_modify_fejl)) { transaktion('rollback'); return; }";
        $this->assertStringContainsString($gate, $normalised, 'the rollback/commit choice should be made by !empty($db_modify_fejl) and nothing else');
        $this->assertSame(1, substr_count($normalised, "transaktion('commit')"), 'one commit');
        $this->assertLessThan(strpos($normalised, "transaktion('commit')"), strpos($normalised, $gate), 'the flag is checked before the commit');
    }
}
