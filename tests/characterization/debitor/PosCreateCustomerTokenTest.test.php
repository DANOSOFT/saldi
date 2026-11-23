<?php
// SD-844 K31: the till's create-customer form carries a token recorded in the session that was
// shown the form (debitor/pos_ordre_includes/createCustomer.php), and debitor/posCreateCustomer.php
// creates nothing for a token that session was not given, such as one made up by a form on
// another site.
//
// History:
// 20261123 CL/MJ SD-844 K31: created.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/debitor/pos_ordre_includes/createCustomer.php';

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
}
