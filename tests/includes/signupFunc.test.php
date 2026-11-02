<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/signupIncludes/signupFunc.php';

if (!function_exists('ifset')) {
    /** Minimal stand-in for includes/std_func.php's ifset() so the pure functions can run without the app. */
    function ifset($arrayOrVar, $key = null, $default = null)
    {
        if (is_array($arrayOrVar) && $key !== null) {
            return array_key_exists($key, $arrayOrVar) && $arrayOrVar[$key] !== null ? $arrayOrVar[$key] : $default;
        }
        return $arrayOrVar ?? $default;
    }
}

/**
 * The pure parts of the signup flow (includes/signupIncludes/signupFunc.php): form validation,
 * account-name normalisation and the password rules that need no network.
 */
final class signupFunc extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function accountNames(): array
    {
        return [
            'kept as is' => ['Nordlys Interiør ApS', 'Nordlys Interiør ApS'],
            'ampersand becomes og' => ['Hansen & Søn', 'Hansen og Søn'],
            'quotes dropped' => ["Bob's \"Burgers\"", 'Bobs Burgers'],
            'odd characters become spaces' => ['Café/Bar_42!', 'Café Bar 42'],
            'whitespace collapsed and trimmed' => ["  Alfa   Beta \t Gamma  ", 'Alfa Beta Gamma'],
            'dot and hyphen allowed' => ['A.P. Møller-Mærsk', 'A.P. Møller-Mærsk'],
            'leading punctuation trimmed' => ['- .Firma', 'Firma'],
            'cut at 60 characters' => [str_repeat('a', 70), str_repeat('a', 60)],
            'a lone ampersand' => ['&', 'og'],
        ];
    }

    #[DataProvider('accountNames')]
    public function testNormalizeName(string $in, string $expected): void
    {
        self::assertSame($expected, signup_normalize_name($in));
    }

    public function testNameVariantStaysWithinSixtyCharacters(): void
    {
        $long = str_repeat('x', 60);
        $v = signup_name_variant($long, 12);
        self::assertSame(60, mb_strlen($v));
        self::assertStringEndsWith(' 12', $v);
        self::assertSame('Nordlys 2', signup_name_variant('Nordlys', 2));
    }

    public function testValidFormPassesAndIsCleaned(): void
    {
        $r = signup_validate_form([
            'name' => "  Mette \n Hansen ", 'cvr' => '12 34 56 78', 'company' => '', 'email' => 'mette@nordlys-interior.dk',
            'phone' => '+45 12 34 56 78', 'pakke' => 'Business', 'source' => 'prov',
        ]);
        self::assertTrue($r['ok'], print_r($r['errors'], true));
        self::assertSame('Mette Hansen', $r['data']['navn']);
        self::assertSame('12345678', $r['data']['cvr']);
        self::assertSame('business', $r['data']['pakke']);
        self::assertSame('prov', $r['data']['source']);
    }

    public function testFormRequiresNameEmailAndCvrOrCompany(): void
    {
        $r = signup_validate_form(['name' => 'M', 'cvr' => '', 'company' => '', 'email' => 'not-an-address', 'pakke' => 'gold']);
        self::assertFalse($r['ok']);
        self::assertArrayHasKey('name', $r['errors']);
        self::assertArrayHasKey('email', $r['errors']);
        self::assertArrayHasKey('cvr', $r['errors']);
        self::assertArrayHasKey('pakke', $r['errors']);
    }

    public function testCompanyNameIsEnoughWithoutCvr(): void
    {
        $r = signup_validate_form(['name' => 'Mette Hansen', 'cvr' => '', 'company' => 'Nordlys', 'email' => 'm@nordlys.dk', 'pakke' => 'finans']);
        self::assertTrue($r['ok']);
        $r = signup_validate_form(['name' => 'Mette Hansen', 'cvr' => '1234', 'company' => 'Nordlys', 'email' => 'm@nordlys.dk', 'pakke' => 'finans']);
        self::assertSame('Et dansk CVR-nummer har 8 cifre.', $r['errors']['cvr']);
    }

    public function testEmailNeedsADottedDomain(): void
    {
        self::assertTrue(signup_valid_email('mette@nordlys.dk'));
        self::assertFalse(signup_valid_email('mette@localhost'));
        self::assertFalse(signup_valid_email('mette'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function passwords(): array
    {
        return [
            'too short' => ['kort1234!', 'short'],
            'ten characters is enough' => ['kort12345!', ''],
            'spaces and symbols allowed' => ['tre små grise i 2026', ''],
            'the e-mail itself' => ['mette@nordlys.dk', 'personal'],
            'built around the company name' => ['Nordlys2026!', 'personal'],
            'built around the local part' => ['xx-mette-xx!', 'personal'],
            'saldi in a short password' => ['saldi12345', 'personal'],
            'too long' => [str_repeat('a', 201), 'long'],
        ];
    }

    #[DataProvider('passwords')]
    public function testPasswordRules(string $pw, string $reason): void
    {
        $r = signup_validate_password($pw, ['email' => 'mette@nordlys.dk', 'firmanavn' => 'Nordlys', 'regnskab' => 'Nordlys'], false);
        self::assertSame($reason, $r['reason']);
        self::assertSame($reason === '', $r['ok']);
    }

    public function testTokensAreUrlSafeAndOnlyHashesAreComparable(): void
    {
        $t = signup_new_token();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $t);
        self::assertNotSame($t, signup_new_token());
        self::assertSame(64, strlen(signup_token_hash($t)));
    }

    public function testPendingState(): void
    {
        $h = signup_token_hash('abc');
        $row = ['status' => 'pending', 'token_hash' => $h, 'old_token_hash' => null, 'token_expires' => date('Y-m-d H:i:s', time() + 3600)];
        self::assertSame('valid', signup_pending_state($row, $h));
        self::assertSame('invalid', signup_pending_state($row, signup_token_hash('other')));
        $row['token_expires'] = date('Y-m-d H:i:s', time() - 60);
        self::assertSame('expired', signup_pending_state($row, $h));
        $row['status'] = 'activated';
        self::assertSame('activated', signup_pending_state($row, $h));
        $row = ['status' => 'pending', 'token_hash' => signup_token_hash('new'), 'old_token_hash' => $h, 'token_expires' => date('Y-m-d H:i:s', time() + 3600)];
        self::assertSame('replaced', signup_pending_state($row, $h));
    }

    public function testNameFromCvrUsesTheStoredFlagWithAFallbackForOldRows(): void
    {
        self::assertTrue(signup_name_from_cvr(['navn_fra_cvr' => '1', 'cvrnr' => '12345678']));
        self::assertFalse(signup_name_from_cvr(['navn_fra_cvr' => '0', 'cvrnr' => '12345678']), 'CVR given, but the lookup found nothing');
        self::assertFalse(signup_name_from_cvr(['navn_fra_cvr' => '0', 'cvrnr' => '']));
        self::assertTrue(signup_name_from_cvr(['navn_fra_cvr' => null, 'cvrnr' => '12345678']), 'rows from before the column existed');
        self::assertFalse(signup_name_from_cvr(['cvrnr' => '']));
    }

    public function testMaskEmail(): void
    {
        self::assertSame('me•••@nordlys-interior.dk', signup_mask_email('mette@nordlys-interior.dk'));
        self::assertSame('•••', signup_mask_email('nonsense'));
    }

    protected function tearDown(): void
    {
        foreach (['POSTERINGER_FINANS', 'BRUGERE_FINANS', 'BASE_URL', 'LOGIN_URL', 'LOGIN_URL_FINANS', 'ALLOWED_ORIGINS'] as $key) {
            putenv('SALDI_SIGNUP_' . $key);
        }
    }

    public function testEveryPackageGetsItsOwnLimitsNeverUnlimited(): void
    {
        self::assertSame(['brugerantal' => 1, 'posteringer' => 500], signup_package_limits('finans'));
        self::assertSame(['brugerantal' => 1, 'posteringer' => 5000], signup_package_limits('professionel'));
        self::assertSame(['brugerantal' => 1, 'posteringer' => 25000], signup_package_limits('business'));
        self::assertSame(signup_package_limits('business'), signup_package_limits('ukendt'), 'unknown package falls back to Business');
        foreach (array_keys(signup_packages()) as $key) {
            // 0 in regnskab.posteringer means unlimited (admin/admin_panel.php shows the infinity sign)
            self::assertGreaterThan(0, signup_package_limits($key)['posteringer'], "$key would get unlimited postings");
            self::assertGreaterThan(0, signup_package_limits($key)['brugerantal'], "$key would get unlimited users");
        }
    }

    public function testPackageLimitsCanBeOverriddenButNeverToUnlimited(): void
    {
        putenv('SALDI_SIGNUP_POSTERINGER_FINANS=750');
        putenv('SALDI_SIGNUP_BRUGERE_FINANS=2');
        self::assertSame(['brugerantal' => 2, 'posteringer' => 750], signup_package_limits('finans'));
        putenv('SALDI_SIGNUP_POSTERINGER_FINANS=0');
        self::assertSame(500, signup_package_limits('finans')['posteringer'], 'a setting of 0 must not make the account unlimited');
    }

    public function testTrialEndsAndTheAccountClosesAfterTheGracePeriod(): void
    {
        $today = mktime(12, 0, 0, 11, 2, 2026);
        self::assertSame(['start' => '2026-11-02', 'end' => '2026-12-02', 'lukkes' => '2026-12-16'], signup_trial_dates(30, 14, $today));
        // across the end of daylight saving time (25 October 2026) the dates stay whole days
        self::assertSame(['start' => '2026-10-20', 'end' => '2026-11-19', 'lukkes' => '2026-12-03'], signup_trial_dates(30, 14, mktime(0, 30, 0, 10, 20, 2026)));
        self::assertSame(['start' => '2026-11-02', 'end' => '2026-11-03', 'lukkes' => '2026-11-03'], signup_trial_dates(0, -5, $today), 'at least one trial day, no negative grace');
    }

    public function testFinansLogsInOnThisInstallationNotSaldiDkMini(): void
    {
        putenv('SALDI_SIGNUP_BASE_URL=https://ssl3.saldi.dk/finans');
        foreach (array_keys(signup_packages()) as $key) {
            self::assertSame('https://ssl3.saldi.dk/finans/index/index.php', signup_urls($key)['login'], $key);
        }
        putenv('SALDI_SIGNUP_LOGIN_URL_FINANS=https://ssl3.saldi.dk/mini/index/index.php');
        self::assertSame('https://ssl3.saldi.dk/mini/index/index.php', signup_urls('finans')['login'], 'an explicit Finans login is still honoured');
        self::assertSame('https://ssl3.saldi.dk/finans/index/index.php', signup_urls('business')['login']);
    }

    public function testBillingOrderUsesTheApiFieldNamesAndNeedsAPhone(): void
    {
        $row = ['firmanavn' => 'Nordlys Interiør ApS', 'navn' => 'Mette Lund', 'email' => 'mette@nordlys-interior.dk', 'tlf' => '+45 12 34 56 78',
            'cvrnr' => '12345678', 'addr1' => 'Havnegade 4', 'addr2' => '', 'postnr' => '3000', 'bynavn' => 'Helsingør'];
        $order = signup_billing_order_payload($row, 'Nordlys Interiør ApS', '2026-12-02', 'Professionel');
        // restapi/services/OrderService.php maps postalCode -> postnr; zipCode is not one of its fields
        self::assertSame('3000', $order['postalCode']);
        self::assertArrayNotHasKey('zipCode', $order);
        self::assertSame('+45 12 34 56 78', $order['phone']);
        self::assertSame('2026-12-02', $order['invoiceDate']);
        self::assertStringContainsString('Saldi Professionel', $order['notes']);
        self::assertNull(signup_billing_order_payload(array_replace($row, ['tlf' => '  ']), 'Nordlys Interiør ApS', '2026-12-02', 'Professionel'),
            'no phone: no order, since the api uses the phone as the customer key');
    }

    public function testEmailsMustFitTheAccountAndBeUsableAsALoginName(): void
    {
        self::assertTrue(signup_valid_email('mette@nordlys-interior.dk'));
        self::assertTrue(signup_valid_email(str_repeat('a', 54) . '@ab.dk'), '60 characters fit regnskab.email');
        self::assertFalse(signup_valid_email(str_repeat('a', 55) . '@ab.dk'), '61 characters do not fit varchar(60)');
        foreach (["o'neil@ab.dk", 'a&b@ab.dk', 'a"b"@ab.dk', 'a;b@ab.dk'] as $email) {
            // index/login.php strips these from the username, so the account could never be logged in to
            self::assertFalse(signup_valid_email($email), $email);
        }
    }

    public function testNamesFitTheAccountAndCarryNoLinks(): void
    {
        $base = ['email' => 'mette@nordlys-interior.dk', 'pakke' => 'finans', 'company' => 'Nordlys Interiør ApS'];
        $v = signup_validate_form(['name' => str_repeat('æ', 70)] + $base);
        self::assertSame(60, mb_strlen($v['data']['navn']), 'tenant adresser.kontakt is varchar(60)');
        $v = signup_validate_form(['name' => 'Mette\\Lund'] + $base);
        self::assertSame('MetteLund', $v['data']['navn'], 'backslashes trip the legacy injection check during the account build');
        foreach (['Klik www.example.com', 'Se https://example.com', 'x://y'] as $text) {
            self::assertArrayHasKey('name', signup_validate_form(['name' => $text] + $base)['errors'], $text);
            self::assertArrayHasKey('company', signup_validate_form(['name' => 'Mette', 'company' => $text, 'email' => 'm@ab.dk', 'pakke' => 'finans'])['errors'], $text);
        }
    }

    public function testMailboxKeyGroupsAliasesOfOneInbox(): void
    {
        self::assertSame('victimname@gmail.com', signup_email_key('Victim.Name+trial7@GoogleMail.com'));
        self::assertSame('mette@nordlys.dk', signup_email_key('Mette+x@Nordlys.dk'));
        self::assertSame('m.e@nordlys.dk', signup_email_key('m.e@nordlys.dk'), 'dots only matter for Gmail');
    }

    public function testLimitsCountIpv6PerSlash64(): void
    {
        $_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:aaaa:bbbb:cccc:dddd';
        $a = signup_client_ip();
        $_SERVER['REMOTE_ADDR'] = '2001:db8:1:2::9';
        self::assertSame($a, signup_client_ip());
        self::assertSame('2001:db8:1:2::/64', $a);
        $_SERVER['REMOTE_ADDR'] = '192.0.2.7';
        self::assertSame('192.0.2.7', signup_client_ip());
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testLinksNeverComeFromTheHostHeader(): void
    {
        $_SERVER['HTTP_HOST'] = 'evil.example';
        $_SERVER['SCRIPT_NAME'] = '/finans/signup/nytlink.php';
        self::assertSame('', signup_base_url(), 'without base_url the flow refuses to run instead of trusting Host');
        putenv('SALDI_SIGNUP_BASE_URL=https://ssl3.saldi.dk/finans/');
        self::assertSame('https://ssl3.saldi.dk/finans', signup_base_url());
        unset($_SERVER['HTTP_HOST'], $_SERVER['SCRIPT_NAME']);
    }

    public function testOnlyTheWebsiteAndThisInstallationMayPost(): void
    {
        putenv('SALDI_SIGNUP_BASE_URL=https://ssl3.saldi.dk/finans');
        putenv('SALDI_SIGNUP_ALLOWED_ORIGINS=https://saldi.dk');
        self::assertSame(['https://saldi.dk', 'https://ssl3.saldi.dk'], signup_allowed_origins());
        foreach (['https://saldi.dk' => true, 'https://ssl3.saldi.dk' => true, 'https://evil.example' => false, 'null' => false] as $origin => $ok) {
            $_SERVER['HTTP_ORIGIN'] = $origin;
            self::assertSame($ok, signup_origin_allowed(), $origin);
        }
        unset($_SERVER['HTTP_ORIGIN']);
        self::assertTrue(signup_origin_allowed(), 'no Origin: not a browser form on another site');
        $_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
        self::assertFalse(signup_origin_allowed());
        unset($_SERVER['HTTP_SEC_FETCH_SITE']);
    }

    public function testBillingPayloadCannotBreakTheApisSql(): void
    {
        $row = ['firmanavn' => "Hotel'; DROP TABLE ordrer;--", 'navn' => 'Mette "M" Lund\\', 'email' => 'm@ab.dk', 'tlf' => '12345678',
            'cvrnr' => '', 'addr1' => '', 'addr2' => '', 'postnr' => '3000', 'bynavn' => 'Helsingør'];
        $order = signup_billing_order_payload($row, "Hotel og Co", '2026-12-02', 'Finans');
        foreach ($order as $key => $value) {
            self::assertDoesNotMatchRegularExpression('/[\'"\\\;]/', (string)$value, $key);
        }
    }
}
