<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stdFunc/userIpAllowed.php';

/**
 * user_ip_allowed() (includes/stdFunc/userIpAllowed.php) reads a user's IP limit (brugere.ip_address) for the web
 * login and the REST login and token refresh. No database: tests/restapi/AuthLoginEndpointTest.php and
 * AuthRefreshEndpointTest.php send the REST requests for a user with a limit.
 */
final class userIpAllowed extends TestCase
{
    /** @return array<string, array{0: ?string}> */
    public static function noLimit(): array
    {
        return [
            'NULL' => [null],
            'empty' => [''],
            'spaces only' => ['  '],
            'separators only' => [', ;'],
        ];
    }

    #[DataProvider('noLimit')]
    public function testALimitWithoutAnAddressLetsEveryAddressIn(?string $limit): void
    {
        self::assertTrue(user_ip_allowed($limit, '203.0.113.7'));
        self::assertTrue(user_ip_allowed($limit, ''));
    }

    /** @return array<string, array{0: string}> */
    public static function listsTheClient(): array
    {
        return [
            'one address' => ['203.0.113.7'],
            'first of two' => ['203.0.113.7,192.0.2.1'],
            'last of two' => ['192.0.2.1,203.0.113.7'],
            'space after the comma' => ['192.0.2.1, 203.0.113.7'],
            'line breaks' => ["192.0.2.1\n203.0.113.7"],
            'semicolon' => ['192.0.2.1;203.0.113.7'],
            'surrounding spaces' => [' 203.0.113.7 '],
        ];
    }

    #[DataProvider('listsTheClient')]
    public function testAListedAddressMayLogIn(string $limit): void
    {
        self::assertTrue(user_ip_allowed($limit, '203.0.113.7'));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function doesNotListTheClient(): array
    {
        return [
            'another address' => ['10.0.0.1', '203.0.113.7'],
            'two other addresses' => ['10.0.0.1,10.0.0.2', '203.0.113.7'],
            'a longer address that starts with it' => ['203.0.113.70', '203.0.113.7'],
            'a shorter address it starts with' => ['203.0.113.7', '203.0.113.70'],
            'no client address' => ['203.0.113.7', ''],
            'IPv6 loopback for IPv4 loopback' => ['::1', '127.0.0.1'],
        ];
    }

    #[DataProvider('doesNotListTheClient')]
    public function testAnAddressTheLimitDoesNotListIsRefused(string $limit, string $ip): void
    {
        self::assertFalse(user_ip_allowed($limit, $ip));
    }
}
