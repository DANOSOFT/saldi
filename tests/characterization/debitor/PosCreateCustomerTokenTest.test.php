<?php
// SD-844 K31: the till's create-customer form carries a token recorded in the session that was
// shown the form (debitor/pos_ordre_includes/createCustomer.php), and debitor/posCreateCustomer.php
// creates nothing for a token that session was not given, such as one made up by a form on
// another site.
//
// History:
// 20261123 CL/MJ SD-844 K31: created.
// 20261126 CL/MJ SD-844 K31: a database error in the Shopify mode check falls back to the old form.
// 20261126 CL/MJ SD-844 K31: the cashier's alert keeps æøå on a LATIN9 tenant.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/debitor/pos_ordre_includes/createCustomer.php';
require_once dirname(__DIR__, 3) . '/includes/stdFunc/jsString.php';

final class PosCreateCustomerTokenTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testATokenShownInTheSessionIsAcceptedAgain(): void
    {
        $token = pos_customer_create_token();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
        self::assertTrue(pos_customer_create_token_issued($token));
        self::assertTrue(pos_customer_create_token_issued($token), 'the same form sent again still matches');
    }

    public function testAMadeUpTokenOrNoneIsRefused(): void
    {
        pos_customer_create_token();

        self::assertFalse(pos_customer_create_token_issued(str_repeat('a', 32)), 'a well-formed token this session was not given');
        self::assertFalse(pos_customer_create_token_issued(null));
        self::assertFalse(pos_customer_create_token_issued(['x']));
    }

    public function testASessionKeepsOnlyItsNewestTokens(): void
    {
        $first = pos_customer_create_token();
        for ($i = 0; $i < POS_CUSTOMER_CREATE_SESSION_TOKENS; $i++) {
            $last = pos_customer_create_token();
        }

        self::assertCount(POS_CUSTOMER_CREATE_SESSION_TOKENS, $_SESSION['pos_create_tokens']);
        self::assertFalse(pos_customer_create_token_issued($first));
        self::assertTrue(pos_customer_create_token_issued($last));
    }

    public function testADatabaseErrorInTheShopifyModeCheckFallsBackToTheOldForm(): void
    {
        // Run apart, so this process keeps its own db functions: the settings query throws, as a
        // failed connection does, and the customer lookup must still open (with the old form).
        $script = '$GLOBALS["db_type"] = "postgres";'
            . 'function db_select($q, $w) { throw new RuntimeException("db down"); }'
            . 'function db_fetch_array($r) { return false; }'
            . 'if (!function_exists("pg_transaction_status")) { function pg_transaction_status($c) { return 0; } }'
            . 'require ' . var_export(dirname(__DIR__, 3) . '/debitor/pos_ordre_includes/createCustomer.php', true) . ';'
            . 'var_export(pos_customer_create_shopify_mode());';
        $out = [];
        exec(escapeshellarg(PHP_BINARY) . ' -d error_log=/dev/null -r ' . escapeshellarg($script) . ' 2>&1', $out, $exit);

        self::assertSame(0, $exit, implode("\n", $out));
        self::assertSame('false', trim(implode("\n", $out)));
    }

    public function testTheAlertKeepsTheDanishLettersOnALatin9Tenant(): void
    {
        // findtekst() gives a LATIN9 tenant its text in ISO-8859-15; json_encode() of that is false.
        $saved = $GLOBALS['db_encode'] ?? null;
        $GLOBALS['db_encode'] = 'LATIN9';
        try {
            $script = pos_customer_create_alert_script(mb_convert_encoding('Åbn kundeopslaget igen', 'ISO-8859-15', 'UTF-8'), 'pos_ordre.php?id=7');
        } finally {
            $GLOBALS['db_encode'] = $saved;
        }

        self::assertStringContainsString('alert("\\u00c5bn kundeopslaget igen")', $script);
        self::assertStringContainsString('window.location.href = "pos_ordre.php?id=7"', $script);
    }
}
