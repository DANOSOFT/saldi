<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/signupIncludes/signupFunc.php';
require_once __DIR__ . '/../../includes/signupIncludes/signupMail.php';

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
 * The signup flow's mailbox rules and new-customer mail (doc/signup.md): one account per e-mail across the
 * packages, three calendar months before a new trial after an account closed, the messages the customer sees,
 * and the internal "Ny kunde i Saldi" mail. Pure functions only; the queries behind signup_mailbox_history()
 * need a database.
 */
final class signupMailboxRules extends TestCase
{
    private const TODAY = '2026-11-09';

    /**
     * A signup_mailbox_history() row: an activated signup with its account, open and in its trial.
     *
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private static function account(array $changes = []): array
    {
        return array_replace([
            'signup_id' => 7, 'status' => 'activated', 'email' => 'mette@nordlys.dk', 'activated' => '2026-11-01 10:00:00',
            'r_id' => 42, 'lukket' => '', 'lukkes' => '2026-12-15', 'sidst' => (string)mktime(9, 0, 0, 11, 5, 2026), 'trial_end' => '2026-12-01',
        ], $changes);
    }

    /**
     * @param array<int, array<string, mixed>> $history
     * @return array{decision: string, date: string, regnskab_id: int, signup_id: int}
     */
    private static function decide(array $history, string $today = self::TODAY, int $cooldownMonths = 3): array
    {
        return signup_mailbox_decision($history, $today, 30, 14, $cooldownMonths);
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function monthSums(): array
    {
        return [
            'an ordinary day' => ['2026-11-09', 3, '2027-02-09'],
            '30 November ends in February (not 2 March)' => ['2026-11-30', 3, '2027-02-28'],
            'a leap year February has 29 days' => ['2027-11-30', 3, '2028-02-29'],
            '31 August becomes 30 November (not 1 December)' => ['2026-08-31', 3, '2026-11-30'],
            '31 October has a 31 January' => ['2026-10-31', 3, '2027-01-31'],
            'over the new year' => ['2026-12-15', 3, '2027-03-15'],
            'a timestamp counts by its date' => ['2026-11-30 13:45:00', 3, '2027-02-28'],
            'no months' => ['2026-11-30', 0, '2026-11-30'],
            'twelve months' => ['2028-02-29', 12, '2029-02-28'],
            'not a date' => ['nonsense', 3, ''],
            'an impossible date' => ['2026-02-30', 3, ''],
        ];
    }

    #[DataProvider('monthSums')]
    public function testCalendarMonthsClampToTheEndOfTheMonth(string $from, int $months, string $expected): void
    {
        self::assertSame($expected, signup_add_months($from, $months));
    }

    public function testPhpsOwnMonthArithmeticWouldOverflow(): void
    {
        // Why signup_add_months() exists: strtotime() runs 30 November + 3 months over into March.
        self::assertSame('2027-03-02', date('Y-m-d', strtotime('2026-11-30 +3 months')));
        self::assertSame('2027-02-28', signup_add_months('2026-11-30', 3));
    }

    public function testDanishLongDate(): void
    {
        self::assertSame('9. februar 2027', signup_danish_date('2027-02-09'));
        self::assertSame('31. december 2026', signup_danish_date('2026-12-31'));
        self::assertSame('1. maj 2026', signup_danish_date('2026-05-01 08:00:00'));
        self::assertSame('', signup_danish_date(''));
        self::assertSame('', signup_danish_date('2026-13-01'));
    }

    public function testAMailboxWithoutAccountsMaySignUp(): void
    {
        self::assertSame(['decision' => 'allowed', 'date' => '', 'regnskab_id' => 0, 'signup_id' => 0], self::decide([]));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function openAccounts(): array
    {
        return [
            'in its trial' => [[]],
            'a subscription: sales cleared the closing date' => [['lukkes' => null]],
            'never closes (2099-12-31)' => [['lukkes' => '2099-12-31']],
            "lukket '0' is not closed (index/login.php reads it as false)" => [['lukket' => '0']],
            'being created right now' => [['status' => 'activating', 'r_id' => null, 'lukket' => null, 'lukkes' => null, 'sidst' => null, 'trial_end' => null]],
        ];
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('openAccounts')]
    public function testAnOpenAccountBlocksEveryPackage(array $changes): void
    {
        // The history carries no package: a Finans account blocks a Business signup and the other way round.
        $d = self::decide([self::account($changes)]);
        self::assertSame('already_has_account', $d['decision']);
        self::assertSame('', $d['date']);
        self::assertSame(7, $d['signup_id']);
    }

    public function testAnAccountWhoseSignupRowIsGoneStillCounts(): void
    {
        $row = ['r_id' => 99, 'email' => 'mette@nordlys.dk', 'lukket' => '', 'lukkes' => '2026-12-01', 'sidst' => null, 'trial_end' => '2026-11-17'];
        self::assertSame(['decision' => 'already_has_account', 'date' => '', 'regnskab_id' => 99, 'signup_id' => 0], self::decide([$row]));
    }

    public function testAClosedTrialWaitsThreeCalendarMonthsFromItsClosingDate(): void
    {
        // closed by itself: lukkes has passed (includes/online.php sets lukket at the next login, which may never come)
        $d = self::decide([self::account(['lukkes' => '2026-11-09'])]);
        self::assertSame(['decision' => 'cooldown', 'date' => '2027-02-09', 'regnskab_id' => 42, 'signup_id' => 7], $d);
        $closed = self::account(['lukket' => 'on', 'lukkes' => '2026-08-31']);
        self::assertSame('2026-11-30', self::decide([$closed], '2026-11-29')['date']);
        self::assertSame('allowed', self::decide([$closed], '2026-11-30')['decision'], 'the customer may sign up again on the day itself');
    }

    public function testTheWaitEndsOnTheLastDayOfAShortMonth(): void
    {
        $closed = self::account(['lukket' => 'on', 'lukkes' => '2026-11-30']);
        self::assertSame(['cooldown', '2027-02-28'], array_values(array_slice(self::decide([$closed], '2027-02-27'), 0, 2)));
        self::assertSame('allowed', self::decide([$closed], '2027-02-28')['decision']);
    }

    public function testClosedEarlyByStaffCountsFromThePlannedClosingDate(): void
    {
        // "Lukket" ticked during the trial: Saldi records no date, so the trial's own closing date is used (a longer
        // wait, never a shorter one)
        $d = self::decide([self::account(['lukket' => 'on', 'lukkes' => '2026-12-15'])]);
        self::assertSame(['cooldown', '2027-03-15'], [$d['decision'], $d['date']]);
    }

    public function testClosedWithoutADateCountsFromTheLastKnownDay(): void
    {
        $lastLogin = (string)mktime(9, 0, 0, 10, 20, 2026);
        foreach ([null, '', '2099-12-31'] as $lukkes) {
            // the later of the last login (20 October) and the end of the trial plus 14 days (15 August)
            $d = self::decide([self::account(['lukket' => 'on', 'lukkes' => $lukkes, 'sidst' => $lastLogin, 'trial_end' => '2026-08-01'])]);
            self::assertSame(['cooldown', '2027-01-20'], [$d['decision'], $d['date']], var_export($lukkes, true));
        }
        // no login recorded: the end of the trial plus its grace days
        $d = self::decide([self::account(['lukket' => 'on', 'lukkes' => null, 'sidst' => '', 'trial_end' => '2026-11-01'])]);
        self::assertSame('2027-02-15', $d['date']);
    }

    public function testADeletedAccountCountsFromTheDayItsTrialWouldHaveClosed(): void
    {
        // admin/slet_regnskab.php removed the regnskab row; activated 1 July -> trial to 31 July -> closes 14 August
        $deleted = self::account(['r_id' => null, 'lukket' => null, 'lukkes' => null, 'sidst' => null, 'trial_end' => null, 'activated' => '2026-07-01 09:00:00']);
        self::assertSame(['decision' => 'cooldown', 'date' => '2026-11-14', 'regnskab_id' => 0, 'signup_id' => 7], self::decide([$deleted]));
        self::assertSame('allowed', self::decide([$deleted], '2026-11-14')['decision']);
    }

    public function testTheLatestClosedAccountDecidesAndAnOpenOneWins(): void
    {
        $old = self::account(['signup_id' => 3, 'r_id' => 30, 'lukket' => 'on', 'lukkes' => '2025-01-15']);
        $recent = self::account(['signup_id' => 5, 'r_id' => 50, 'lukket' => 'on', 'lukkes' => '2026-10-01']);
        $older = self::account(['signup_id' => 4, 'r_id' => 40, 'lukket' => 'on', 'lukkes' => '2026-09-01']);
        self::assertSame(['decision' => 'cooldown', 'date' => '2027-01-01', 'regnskab_id' => 50, 'signup_id' => 5], self::decide([$old, $recent, $older]));
        self::assertSame('allowed', self::decide([$old])['decision']);
        self::assertSame('already_has_account', self::decide([$recent, self::account()])['decision']);
    }

    public function testNoWaitWhenCooldownMonthsIsZero(): void
    {
        self::assertSame('allowed', self::decide([self::account(['lukket' => 'on', 'lukkes' => '2026-11-08'])], self::TODAY, 0)['decision']);
    }

    public function testTheCustomerIsToldWhyInDanishWithTheSupportPhoneSetting(): void
    {
        self::assertSame(
            'Der er allerede en Saldi-konto med denne e-mail. Log ind på den, eller ring på 46 90 22 08, hvis du vil skifte pakke.',
            signup_mailbox_message(['decision' => 'already_has_account', 'date' => ''], '46 90 22 08')
        );
        self::assertSame(
            'Denne e-mail har haft en gratis prøveperiode. Du kan prøve Saldi gratis igen fra den 9. februar 2027. Ring på 46 90 22 08, hvis du vil i gang nu.',
            signup_mailbox_message(['decision' => 'cooldown', 'date' => '2027-02-09'], '46 90 22 08')
        );
        self::assertStringContainsString('ring på 70 70 70 70,', signup_mailbox_message(['decision' => 'already_has_account', 'date' => ''], '70 70 70 70'));
        self::assertSame('', signup_mailbox_message(['decision' => 'allowed', 'date' => ''], '46 90 22 08'));
    }

    public function testTheLogTextNamesTheAccountButNotTheAddress(): void
    {
        self::assertSame('cooldown until 2027-02-09 (regnskab 42, signup 7)', signup_mailbox_log_text(['decision' => 'cooldown', 'date' => '2027-02-09', 'regnskab_id' => 42, 'signup_id' => 7]));
        self::assertSame('already_has_account (regnskab 0, signup 3)', signup_mailbox_log_text(['decision' => 'already_has_account', 'date' => '', 'regnskab_id' => 0, 'signup_id' => 3]));
    }

    /**
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private static function newCustomer(): array
    {
        $row = ['firmanavn' => 'Nordlys Interiør ApS', 'cvrnr' => '12345678', 'navn' => 'Mette Hansen', 'email' => 'mette@nordlys.dk',
            'tlf' => '+45 12 34 56 78', 'pakke' => 'business', 'source' => 'prov'];
        $account = ['regnskab' => 'Nordlys Interiør ApS', 'regnskab_id' => 42, 'trial_end' => '2026-12-09', 'lukkes' => '2026-12-23',
            'activated' => '2026-11-09 14:33:05'];
        return [$row, $account];
    }

    public function testNewCustomerMailSaysWhoWhatAndWhen(): void
    {
        [$row, $account] = self::newCustomer();
        $m = signup_notify_message($row, $account, 'https://ssl3.saldi.dk/finans');
        self::assertSame('Ny kunde i Saldi: Nordlys Interiør ApS (Business)', $m['subject']);
        self::assertSame('mette@nordlys.dk', $m['reply_to'], 'answering the mail writes to the customer');
        self::assertSame('Mette Hansen', $m['reply_to_name']);
        foreach ([
            'Firma:             Nordlys Interiør ApS',
            'CVR-nummer:        12345678',
            'Kontaktperson:     Mette Hansen',
            'E-mail:            mette@nordlys.dk',
            'Telefon:           +45 12 34 56 78',
            'Pakke:             Business',
            'Regnskab:          Nordlys Interiør ApS (id 42)',
            'Oprettet:          9. november 2026 kl. 14.33',
            'Prøveperiode til:  9. december 2026',
            'Lukkes:            23. december 2026',
            'Kilde:             prov',
            'Installation:      https://ssl3.saldi.dk/finans',
            'Regnskabet lukker af sig selv den 23. december 2026',
        ] as $line) {
            self::assertStringContainsString($line, $m['text']);
        }
        foreach (['Nordlys Interiør ApS (id 42)', '9. december 2026', '23. december 2026', 'https://ssl3.saldi.dk/finans', 'href="mailto:mette@nordlys.dk"'] as $part) {
            self::assertStringContainsString($part, $m['html']);
        }
    }

    public function testNewCustomerMailEscapesWhatTheFormSent(): void
    {
        [$row, $account] = self::newCustomer();
        $row['firmanavn'] = "<script>alert(1)</script> & \"Co\"\r\nBcc: x@evil.example";
        $row['navn'] = "O'Neil <b>";
        $m = signup_notify_message($row, $account, 'https://ssl3.saldi.dk/finans');
        self::assertStringNotContainsString('<script>', $m['html']);
        self::assertStringNotContainsString('<b>', $m['html']);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;Co&quot; Bcc: x@evil.example', $m['html']);
        self::assertStringContainsString('O&#039;Neil &lt;b&gt;', $m['html']);
        self::assertDoesNotMatchRegularExpression('/[\r\n]/', $m['subject'], 'no header lines from the form');
        self::assertStringNotContainsString("\r", $m['text']);
    }

    public function testNewCustomerMailShowsMissingValuesAsADash(): void
    {
        [$row, $account] = self::newCustomer();
        $row['cvrnr'] = '';
        $row['tlf'] = '';
        $row['source'] = '';
        $row['pakke'] = 'finans';
        $m = signup_notify_message($row, $account, 'https://ssl3.saldi.dk/finans');
        self::assertStringContainsString('CVR-nummer:        –', $m['text']);
        self::assertStringContainsString('Telefon:           –', $m['text']);
        self::assertStringContainsString('Kilde:             –', $m['text']);
        self::assertSame('Ny kunde i Saldi: Nordlys Interiør ApS (Finans)', $m['subject']);
    }

    public function testNoReplyToTheCustomerWithoutAUsableAddress(): void
    {
        [$row, $account] = self::newCustomer();
        $row['email'] = 'not an address';
        $m = signup_notify_message($row, $account, 'https://ssl3.saldi.dk/finans');
        self::assertSame('', $m['reply_to']);
        self::assertStringNotContainsString('mailto:', $m['html']);
        self::assertStringNotContainsString('Svar på denne mail', $m['text']);
    }

    public function testNotifyMailIsOffWhenTheSettingSaysSo(): void
    {
        putenv('SALDI_SIGNUP_NOTIFY_EMAIL=off');
        try {
            [$row, $account] = self::newCustomer();
            self::assertFalse(signup_send_notify_mail($row, $account), "'off' sends nothing");
        } finally {
            putenv('SALDI_SIGNUP_NOTIFY_EMAIL');
        }
    }
}
