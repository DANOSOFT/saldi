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
//                  partial-save this fixed, and both would fail here.
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
     * The block with comments removed. Needed because the explanatory comments in the source name
     * db_modify() and transaktion() themselves, and a text scan would otherwise match those.
     */
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

    public function testTheBlockOpensAndClosesExactlyOneTransaction(): void
    {
        $block = self::saveBlockCode();
        self::assertSame(1, substr_count($block, "transaktion('begin')"), 'exactly one begin');
        self::assertSame(1, substr_count($block, "transaktion('commit')"), 'exactly one commit');
        self::assertSame(1, substr_count($block, "transaktion('rollback')"), 'exactly one rollback');
    }

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
     * An exit, return or break between begin and commit would leave the transaction open and the
     * save half-applied for the rest of the request.
     */
    public function testNothingCanSkipTheCommit(): void
    {
        $block = self::saveBlockCode();
        $begin = strpos($block, "transaktion('begin')");
        $commit = strpos($block, "transaktion('commit')");
        $between = substr($block, $begin, $commit - $begin);

        // 'continue' is fine: the only loop here is inside the block, so it cannot escape it.
        foreach (['exit', 'die', 'return', 'break'] as $keyword) {
            self::assertSame(
                0,
                preg_match('/\b' . $keyword . '\b\s*[;(]/', $between),
                "found a '$keyword' between begin and commit, which would skip it"
            );
        }
    }

    /** The webservice path returns instead of exiting, so the failure flag must be consulted. */
    public function testTheWriteFailureFlagGatesTheCommit(): void
    {
        $block = self::saveBlockCode();
        self::assertMatchesRegularExpression(
            '/db_modify_fejl.*\)\s*\{\s*(\/\/[^\n]*\n\s*)*transaktion\(\'rollback\'\)/s',
            $block,
            'the rollback should be guarded by $db_modify_fejl'
        );
    }
}
