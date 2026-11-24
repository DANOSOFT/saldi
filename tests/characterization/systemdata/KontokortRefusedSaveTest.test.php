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
// Once the form was drawn again it warned Undefined variable $valutakurs at
// the Saldo row and $slet at the Slet button (testlab round 2,
// chart-vat-group-setup), and in the Finans > Kontoplan layout $previousPage
// and $nextPage at the arrows: the account load (id > 0) is the only place
// that sets them.
//
// Pins: a refused save of a Drift or a Status account, opened from Finans >
// Kontoplan or from Indstillinger, shows the alert, the whole form and Map til
// with the standard accounts of that type, reads no undefined variable and
// offers no Slet; a saved Drift account's card still lists the standard
// accounts and offers Slet.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261105 CL/LH A refused save reads no undefined variable and offers no Slet, in both layouts.
// 20261115 CL/ASR Integration: master's per-class test database; the session id comes from CharacterizationEnv::sessionId().

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
        return $this->runChildJson('run_chart_of_accounts_page.php', ['kontokort.php', $query, $body, CharacterizationEnv::sessionId()]);
    }

    /**
     * Gem on the new-account card (no id), as the form posts it: the Kontotype dropdown sends the type's letter, and a
     * card opened from Indstillinger carries the settings marker.
     */
    private function save(string $kontonr, string $kontotype, bool $fromSettings): array
    {
        $form = [
            'id' => '', 'valuta' => '', 'saldo' => '', 'kontonr' => $kontonr, 'map_to' => '0',
            'beskrivelse' => Fixtures::TAG . ' kontokort new', 'kontotype' => $kontotype, 'moms' => '', 'genvej' => '',
            'gem' => 'Gem',
        ];
        if ($fromSettings) {
            $form['settings'] = '1';
        }
        return $this->page('', http_build_query($form));
    }

    /** @return list<string> the page's own "Undefined variable" warnings */
    private static function undefinedVariables(array $out): array
    {
        return array_values(array_filter(
            $out['warnings'],
            static fn(string $w): bool => str_starts_with($w, 'systemdata/kontokort.php:') && str_contains($w, 'Undefined variable')
        ));
    }

    /** @return array<string, array{string, string, string, bool}> */
    public static function refusedSaves(): array
    {
        $cases = [];
        foreach (['Finans > Kontoplan' => false, 'Indstillinger' => true] as $where => $fromSettings) {
            $cases["a Drift account with a number that is taken, from $where"] = ['taken', 'D', self::STD_DRIFT, $fromSettings];
            $cases["a Status account with a number that is taken, from $where"] = ['taken', 'S', self::STD_STATUS, $fromSettings];
            $cases["a Drift account with number 0, from $where"] = ['0', 'D', self::STD_DRIFT, $fromSettings];
        }
        return $cases;
    }

    #[DataProvider('refusedSaves')]
    public function test_a_refused_save_draws_the_form_with_the_standard_accounts(string $kontonr, string $kontotype, string $standard, bool $fromSettings): void
    {
        if ($kontonr === 'taken') {
            [, $kontonr] = $this->account();
        }

        $out = $this->save($kontonr, $kontotype, $fromSettings);

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
        $this->assertSame([], self::undefinedVariables($out), 'the form reads no variable that only a saved account sets');
        $this->assertStringNotContainsString('name="slet"', $out['html'], 'an account that was not saved offers no Slet');
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
        $this->assertStringContainsString('name="slet"', $out['html'], 'an account without postings still offers Slet');
    }
}
