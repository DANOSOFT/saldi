<?php
// tests/characterization/systemdata/SettingsCityFromZipTest.test.php
//
// Stamdata and the employee card fill an empty By from Postnr. when they are
// saved (testlab round 2, company-masterdata, 2026-11-05).
//
// The old Stamdata (systemdata/stamkort.php) and employee card
// (systemdata/ansatte_save.php) ran `if ($postnr && !$bynavn)
// $bynavn = bynavn($postnr);` before writing the row, so a By left empty got
// the town of the postal code from importfiler/postnr.csv. Both pages moved
// to systemdata/settingsSection.php (company.data, organisation.employees),
// which stored Postnr. and By as two separate fields: 5970 and 8000 were
// saved with no town, and the company's By is printed in the invoice header
// ($eget_bynavn).
//
// Driven through systemdata/settingsSection.php the way a browser does: the
// section is drawn, its form is filled in and posted, and the row is read
// back.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class SettingsCityFromZipTest extends CharacterizationTestCase
{
    /** @var array<string, string|null> the test tenant's company row before the suite */
    private static array $company = [];

    /** @var list<int> the employees this suite added */
    private static array $employees = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company = self::one("SELECT id, postnr, bynavn FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1") ?? [];
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            foreach (self::$employees as $id) {
                self::rows('DELETE FROM ansatte WHERE id = $1', [$id]);
            }
            if (self::$company !== []) {
                self::rows('UPDATE adresser SET postnr = $1, bynavn = $2 WHERE id = $3', [self::$company['postnr'], self::$company['bynavn'], self::$company['id']]);
            }
        }
        self::$employees = [];
        parent::tearDownAfterClass();
    }

    /** @return array{status:int|bool, html:string} */
    private function page(string $query, string $body = ''): array
    {
        return $this->runChildJson('run_settings_section_page.php', [$query, $body, CharacterizationEnv::SESSION_ID]);
    }

    /**
     * The form of a drawn section that holds $field, as a browser would post it: the query of its action, and every
     * enabled field with a name and its value (a checkbox or radio only when ticked, a select's chosen option).
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function drawnForm(string $query, string $field): array
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $this->page($query)['html']);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $x = new DOMXPath($doc);
        $form = $x->query('//form[translate(@method, "POST", "post") = "post"][.//*[@name="' . $field . '"]]')->item(0);
        $this->assertNotNull($form, "$query is drawn with a form that has $field");
        $action = (string) parse_url($form->getAttribute('action'), PHP_URL_QUERY);
        $fields = [];
        foreach ($x->query('.//input[@name] | .//textarea[@name] | .//select[@name]', $form) as $el) {
            if ($el->hasAttribute('disabled')) {
                continue;
            }
            $type = strtolower($el->getAttribute('type'));
            if ($el->nodeName === 'input' && in_array($type, ['submit', 'button', 'reset'], true)) {
                continue;
            }
            if ($el->nodeName === 'input' && in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) {
                continue;
            }
            if ($el->nodeName === 'textarea') {
                $fields[$el->getAttribute('name')] = $el->textContent;
            } elseif ($el->nodeName === 'select') {
                $chosen = $x->query('.//option[@selected]', $el)->item(0) ?? $x->query('.//option', $el)->item(0);
                $fields[$el->getAttribute('name')] = $chosen === null ? '' : ($chosen->hasAttribute('value') ? $chosen->getAttribute('value') : $chosen->textContent);
            } else {
                $fields[$el->getAttribute('name')] = $el->getAttribute('value');
            }
        }
        return [$action, $fields];
    }

    /** @param array<string, string> $form */
    private function save(string $query, array $form): void
    {
        $res = $this->page($query, http_build_query($form));
        $this->assertSame(302, $res['status'], 'the save is accepted and goes back to the section (it is not drawn again with an error)');
    }

    /** An employee of the company, as Tilføj person adds one, with this Postnr. and no By. */
    private static function employee(string $name, ?string $postnr): int
    {
        $companyId = (int) self::$company['id'];
        $id = CharacterizationEnv::nextId(self::$tenant, 'ansatte');
        $nummer = (int) self::one('SELECT COALESCE(MAX(nummer), 0) + 1 AS n FROM ansatte WHERE konto_id = $1', [$companyId])['n'];
        self::rows(
            "INSERT INTO ansatte (id, konto_id, navn, nummer, postnr, bynavn, lukket, startdate, slutdate) VALUES ($1, $2, $3, $4, $5, NULL, '', '2026-11-01', '9999-12-31')",
            [$id, $companyId, $name, $nummer, $postnr]
        );
        self::$employees[] = $id;
        return $id;
    }

    /** @return array<string, array{string, string, string}> Postnr. and By as typed, and the By that is stored */
    public static function companyCases(): array
    {
        return [
            'By emptied and Postnr. changed (the lab: 5970)' => ['5970', '', 'Ærøskøbing'],
            'By emptied and Postnr. kept' => ['9370', '', 'Hals'],
            'a By that is typed is kept' => ['5970', 'Marstal', 'Marstal'],
            'a Postnr. that is not in the list leaves By empty' => ['X970', '', ''],
        ];
    }

    #[DataProvider('companyCases')]
    public function test_stamdata_fills_an_empty_by_from_postnr(string $postnr, string $by, string $stored): void
    {
        self::rows("UPDATE adresser SET postnr = '9370', bynavn = 'Hals' WHERE id = $1", [(int) self::$company['id']]);
        [$action, $form] = $this->drawnForm('s=company.data', 'f[company.data.city]');
        $this->assertArrayHasKey('f[company.data.zip]', $form, 'Stamdata has Postnr. next to By');

        // The order the lab's agents typed in: By emptied, then Postnr.
        $form['f[company.data.city]'] = $by;
        $form['f[company.data.zip]'] = $postnr;
        $this->save($action, $form);

        $row = self::one('SELECT postnr, bynavn FROM adresser WHERE id = $1', [(int) self::$company['id']]);
        $this->assertSame($postnr, $row['postnr'], 'Postnr. is stored as typed');
        $this->assertSame($stored, (string) $row['bynavn'], 'By is stored');
    }

    /** @return array<string, array{string|null, string, string, string}> the employee's Postnr. before, Postnr. and By as typed, the By that is stored */
    public static function employeeCases(): array
    {
        return [
            'added with no address, then given Postnr. 8000 (the lab\'s E1)' => [null, '8000', '', 'Århus C'],
            'a Postnr. but no By, saved unchanged' => ['5970', '5970', '', 'Ærøskøbing'],
            'a By that is typed is kept' => [null, '8000', 'Aarhus', 'Aarhus'],
        ];
    }

    #[DataProvider('employeeCases')]
    public function test_the_employee_card_fills_an_empty_by_from_postnr(?string $before, string $postnr, string $by, string $stored): void
    {
        $id = self::employee('Chartest By ' . count(self::$employees), $before);
        // The card as Brugere & ansatte opens it (includes/settings/people.php, stamUrl).
        [$action, $form] = $this->drawnForm('s=organisation.employees&item=emp_' . $id . '&embed=person&popup=1', "f[organisation.employees.bynavn.$id]");
        $this->assertArrayHasKey("f[organisation.employees.postnr.$id]", $form, 'the card has Postnr. next to By');

        $form["f[organisation.employees.bynavn.$id]"] = $by;
        $form["f[organisation.employees.postnr.$id]"] = $postnr;
        $this->save($action, $form);

        $row = self::one('SELECT postnr, bynavn FROM ansatte WHERE id = $1', [$id]);
        $this->assertSame($postnr, $row['postnr'], 'Postnr. is stored as typed');
        $this->assertSame($stored, (string) $row['bynavn'], 'By is stored');
    }
}
