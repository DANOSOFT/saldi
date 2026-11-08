<?php
// tests/characterization/systemdata/KontoplanRightTest.test.php
//
// The chart of accounts (systemdata/kontoplan.php) and the account card
// (systemdata/kontokort.php) need the Kontoplan right, position 0 of the
// user's rights string (testlab round 1, users-and-rights, DEVY-1).
//
// The old module gate in includes/online.php checks the position a page names
// in $modulnr, and $modulnr 0 means "no module check". So a sales user with
// rights 0000010000000000 opened kontoplan.php by URL and saw every account
// and its balance (1100 Varesalg DK -527.279,38 in the lab), and kontokort.php
// showed an account and deleted it on a posted Slet. The pages' $permission_key
// (system.kontoplan) is only logged until the company switches enforcement to
// deny.
//
// The gate also skipped every check for a user without a rights string, such
// as the login sager/ansatte.php creates for an employee (rettigheder NULL,
// which online.php reads as '').
//
// Pins: without the right both pages answer 403 with the refusal text and
// show no account, and a posted Slet deletes nothing; that holds for a rights
// string with 0 at position 0 and for none at all (NULL, '' or '0'). With the
// right, write ('1', or any character from '1' up, as the old gate reads it)
// or read ('2', from a role), both pages show the account.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class KontoplanRightTest extends CharacterizationTestCase
{
    /** The test accounts' numbers share this prefix, so the grid's Kontonr search lists exactly them. */
    private const PREFIX = '97100';

    /** The seeded session's user name (CharacterizationEnv); online.php reads that user's rights from brugere. */
    private const USER = 'chartest';

    private const REFUSAL = 'Du har ikke adgang til denne funktion. Kontakt din administrator.';

    private static int $seq = 0;

    /** The brugere row this test gives the session's user; tearDown() deletes it. */
    private ?int $userId = null;

    /** @var array{revisor:?string, rettigheder:?string}|null the seeded online row before user(); tearDown() puts it back */
    private ?array $session = null;

    /** @var list<int> kontoplan rows this test created; tearDown() deletes them. */
    private array $accounts = [];

    protected function tearDown(): void
    {
        if ($this->session !== null) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            try {
                CharacterizationEnv::rows(
                    $master,
                    'UPDATE online SET revisor = $1, rettigheder = $2 WHERE session_id = $3',
                    [$this->session['revisor'], $this->session['rettigheder'], CharacterizationEnv::SESSION_ID]
                );
            } finally {
                pg_close($master);
            }
            $this->session = null;
        }
        if ($this->userId !== null) {
            self::rows('DELETE FROM brugere WHERE id = $1', [$this->userId]);
            // online.php gives a user without one a Usersettings row (grupper art USET) on the first page.
            self::rows("DELETE FROM grupper WHERE art = 'USET' AND kodenr = $1", [$this->userId]);
            $this->userId = null;
        }
        foreach ($this->accounts as $id) {
            self::rows('DELETE FROM kontoplan WHERE id = $1', [$id]);
        }
        $this->accounts = [];
    }

    /**
     * The session becomes an ordinary login of a user with $rettigheder (NULL: none, as a Sager employee's login).
     * CharacterizationEnv seeds an auditor session (online.revisor false comes back as 't'/'f', which PHP reads as true),
     * whose rights are the online row's nines; an ordinary login leaves revisor NULL, and online.php reads the rights from
     * the user's brugere row. index/login.php copies them to the online row, a missing string as ''.
     */
    private function user(?string $rettigheder): void
    {
        $this->assertNull(self::one('SELECT id FROM brugere WHERE brugernavn = $1', [self::USER]), 'no brugere row for the session user yet');
        $this->userId = CharacterizationEnv::nextId(self::$tenant, 'brugere');
        self::rows('INSERT INTO brugere (id, brugernavn, rettigheder) VALUES ($1, $2, $3)', [$this->userId, self::USER, $rettigheder]);
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            $row = CharacterizationEnv::one($master, 'SELECT revisor, rettigheder FROM online WHERE session_id = $1', [CharacterizationEnv::SESSION_ID]);
            $this->assertNotNull($row, 'the seeded online session row');
            $this->session = ['revisor' => $row['revisor'], 'rettigheder' => $row['rettigheder']];
            CharacterizationEnv::rows(
                $master,
                'UPDATE online SET revisor = NULL, rettigheder = $1 WHERE session_id = $2',
                [$rettigheder ?? '', CharacterizationEnv::SESSION_ID]
            );
        } finally {
            pg_close($master);
        }
    }

    /** A finance account in the current fiscal year; returns its id. */
    private function account(): int
    {
        $row = self::one(
            "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, fra_kto, til_kto, lukket, primo, saldo,
                                    regnskabsaar, genvej, overfor_til, anvendelse, modkonto, valuta, valutakurs, map_to)
             VALUES ($1, $2, 'D', '', 0, 0, '', 0, 4321, $3, '', 0, '', 0, '0', 100, 0)
             RETURNING id",
            [self::PREFIX . ++self::$seq, Fixtures::TAG . ' kontoplan right', self::$regnaar]
        );
        $id = (int)$row['id'];
        $this->accounts[] = $id;
        return $id;
    }

    /** @return array{status:int, html:string} */
    private function page(string $page, string $query, string $body = ''): array
    {
        return $this->runChildJson('run_chart_of_accounts_page.php', [$page, $query, $body, CharacterizationEnv::SESSION_ID]);
    }

    private function grid(): array
    {
        return $this->page('kontoplan.php', 'search[kontoplan][kontonr]=' . self::PREFIX);
    }

    /** @return array<string, array{?string}> */
    public static function withoutTheRight(): array
    {
        return [
            'Debitorordre only, the lab\'s e2etest3' => ['0000010000000000'],
            'no rights string, as sager/ansatte.php creates an employee\'s login' => [null],
            'an empty rights string' => [''],
            'a lone 0, which PHP reads as false' => ['0'],
        ];
    }

    #[DataProvider('withoutTheRight')]
    public function test_a_user_without_the_kontoplan_right_is_refused_the_chart_and_the_account_card(?string $rettigheder): void
    {
        $this->user($rettigheder);
        $id = $this->account();

        $grid = $this->grid();
        $this->assertSame(403, $grid['status'], 'kontoplan.php refuses');
        $this->assertStringContainsString(self::REFUSAL, $grid['html']);
        $this->assertStringNotContainsString('kontokort.php?id=' . $id, $grid['html'], 'the grid lists no account');
        $this->assertStringNotContainsString('4.321,00', $grid['html'], 'and shows no balance');

        $card = $this->page('kontokort.php', 'id=' . $id);
        $this->assertSame(403, $card['status'], 'kontokort.php refuses');
        $this->assertStringContainsString(self::REFUSAL, $card['html']);
        $this->assertStringNotContainsString('kontoplan right', $card['html'], 'the card shows no account');

        $slet = $this->page('kontokort.php', '', http_build_query(['slet' => 'Slet', 'id' => $id, 'kontonr' => self::PREFIX . self::$seq]));
        $this->assertSame(403, $slet['status'], 'a posted Slet is refused');
        $this->assertNotNull(self::one('SELECT id FROM kontoplan WHERE id = $1', [$id]), 'and the account is still there');
    }

    /** @return array<string, array{string}> */
    public static function withTheRight(): array
    {
        return [
            'write' => ['1000010000000000'],
            'read, from a role' => ['2000010000000000'],
            'a nine, as the old gate reads it' => ['9000010000000000'],
        ];
    }

    #[DataProvider('withTheRight')]
    public function test_a_user_with_the_kontoplan_right_opens_the_chart_and_the_account_card(string $rettigheder): void
    {
        $this->user($rettigheder);
        $id = $this->account();

        $grid = $this->grid();
        $this->assertSame(200, $grid['status']);
        $this->assertStringNotContainsString(self::REFUSAL, $grid['html']);
        $this->assertStringContainsString('kontokort.php?id=' . $id, $grid['html'], 'the grid lists the account');

        $card = $this->page('kontokort.php', 'id=' . $id);
        $this->assertSame(200, $card['status']);
        $this->assertStringNotContainsString(self::REFUSAL, $card['html']);
        $this->assertStringContainsString(Fixtures::TAG . ' kontoplan right', $card['html'], 'the card shows the account');
    }
}
