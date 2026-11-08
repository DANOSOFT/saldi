<?php
// tests/characterization/systemdata/KontokortRefusedSaveTest.test.php
//
// The account card (systemdata/kontokort.php) draws its form again after it
// refuses a save (testlab round 1, php-log).
//
// Gem on a new Drift account with a number that is already taken alerted
// "Der findes allerede en konto med nr: ..." and then died with "count():
// Argument #1 ($value) must be of type Countable|array, null given" at the
// Map til dropdown, on both legs. The standard accounts that dropdown lists
// (importfiler/kontoplan.txt) were only read for a saved account (id > 0),
// and a refused save draws the form with id 0. A Status account and a number
// below 1 took the same path.
//
// Pins: a refused save of a Drift or a Status account shows the alert, the
// whole form and Map til with the standard accounts of that type; a saved
// Drift account's card still lists them.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class KontokortRefusedSaveTest extends CharacterizationTestCase
{
    /** The test accounts' numbers share this prefix. */
    private const PREFIX = '97200';

    /** A Drift and a Status account of importfiler/kontoplan.txt, as Map til lists them. */
    private const STD_DRIFT = "<OPTION value = '1010'>1010 : Salg af varer og ydelser </OPTION>";
    private const STD_STATUS = "<OPTION value = '5010'>5010 : Goodwill, bogført værdi primo</OPTION>";

    private static int $seq = 0;

    /** @var list<int> kontoplan rows this test created; tearDown() deletes them. */
    private array $accounts = [];

    protected function tearDown(): void
    {
        foreach ($this->accounts as $id) {
            self::rows('DELETE FROM kontoplan WHERE id = $1', [$id]);
        }
        $this->accounts = [];
    }

    /** A Drift account in the current fiscal year; returns [id, kontonr]. */
    private function account(): array
    {
        $kontonr = self::PREFIX . ++self::$seq;
        $row = self::one(
            "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, fra_kto, til_kto, lukket, primo, saldo,
                                    regnskabsaar, genvej, overfor_til, anvendelse, modkonto, valuta, valutakurs, map_to)
             VALUES ($1, $2, 'D', '', 0, 0, '', 0, 0, $3, '', 0, '', 0, '0', 100, 0)
             RETURNING id",
            [$kontonr, Fixtures::TAG . ' kontokort refused', self::$regnaar]
        );
        $id = (int)$row['id'];
        $this->accounts[] = $id;
        return [$id, $kontonr];
    }

    /** @return array{status:int, html:string} */
    private function page(string $query, string $body = ''): array
    {
        return $this->runChildJson('run_chart_of_accounts_page.php', ['kontokort.php', $query, $body, CharacterizationEnv::SESSION_ID]);
    }

    /** Gem on the new-account card (no id), as the form posts it: the Kontotype dropdown sends the type's letter. */
    private function save(string $kontonr, string $kontotype): array
    {
        return $this->page('', http_build_query([
            'id' => '', 'valuta' => '', 'saldo' => '', 'kontonr' => $kontonr, 'map_to' => '0',
            'beskrivelse' => Fixtures::TAG . ' kontokort new', 'kontotype' => $kontotype, 'moms' => '', 'genvej' => '',
            'gem' => 'Gem',
        ]));
    }

    /** @return array<string, array{string, string, string}> */
    public static function refusedSaves(): array
    {
        return [
            'a Drift account with a number that is taken' => ['taken', 'D', self::STD_DRIFT],
            'a Status account with a number that is taken' => ['taken', 'S', self::STD_STATUS],
            'a Drift account with number 0' => ['0', 'D', self::STD_DRIFT],
        ];
    }

    #[DataProvider('refusedSaves')]
    public function test_a_refused_save_draws_the_form_with_the_standard_accounts(string $kontonr, string $kontotype, string $standard): void
    {
        if ($kontonr === 'taken') {
            [, $kontonr] = $this->account();
        }

        $out = $this->save($kontonr, $kontotype);

        $this->assertSame(200, $out['status']);
        $this->assertStringContainsString(
            $kontonr === '0' ? 'Kontonummer skal v&aelig;re et positivt heltal' : 'Der findes allerede en konto med nr: ' . $kontonr,
            $out['html'],
            'the refusal'
        );
        $this->assertStringContainsString("<SELECT name='map_to'", $out['html'], 'Map til is drawn');
        $this->assertStringContainsString($standard, $out['html'], 'with the standard accounts of that type');
        $this->assertStringContainsString('name=beskrivelse value="' . Fixtures::TAG . ' kontokort new"', $out['html'], 'the form keeps what was typed');
        $this->assertStringContainsString('name="gem"', $out['html'], 'and is drawn to the Gem button');
        $this->assertSame(
            0,
            (int)self::one("SELECT count(*) AS n FROM kontoplan WHERE beskrivelse = $1", [Fixtures::TAG . ' kontokort new'])['n'],
            'nothing is created'
        );
    }

    public function test_a_saved_drift_account_still_lists_the_standard_accounts(): void
    {
        [$id] = $this->account();

        $out = $this->page('id=' . $id);

        $this->assertSame(200, $out['status']);
        $this->assertStringContainsString(Fixtures::TAG . ' kontokort refused', $out['html']);
        $this->assertStringContainsString(self::STD_DRIFT, $out['html']);
        $this->assertStringNotContainsString(self::STD_STATUS, $out['html'], 'a Drift account is offered Drift accounts only');
    }
}
