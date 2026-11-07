<?php
// tests/characterization/systemdata/SettingsSectionThisDbTest.test.php
//
// A settings form drawn in one regnskab saves nothing after the session has
// moved to another one (testlab round 1, login-sessions-recovery, 2026-11-05).
//
// An administrator or revisor opens a second regnskab with
// admin/aaben_regnskab.php, which points the same session (online.db) at it
// and keeps the session's CSRF token. The old Stamdata page (stamkort.php)
// posted thisDb, the regnskab it was drawn in, and includes/online.php refuses
// a post whose thisDb is not the session's regnskab ("Du har skiftet regnskab
// fra ... til ..., handling afbrudt"). Stamdata moved to
// settingsSection.php?s=company.data without that field, so a tab left open on
// Stamdata saved its company data into the regnskab opened since.
//
// Driven through systemdata/settingsSection.php itself, as a browser does: the
// page is drawn in the test tenant, the session is moved to a second clone of
// the template (as aaben_regnskab.php does it), and the form drawn before is
// posted.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class SettingsSectionThisDbTest extends CharacterizationTestCase
{
    private const NEW_PHONE = '86 12 34 59';

    /** The second regnskab the session moves to. */
    private static string $other = '';

    /** @var array<string, string|null> the test tenant's company row before the suite */
    private static array $company = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company = self::one("SELECT tlf FROM adresser WHERE art = 'S'") ?? [];

        // A second regnskab: another clone of the template, registered in the master registry as the test tenant is.
        $other = CharacterizationEnv::testDb() . '_andet';
        if (!preg_match('/^[a-z0-9_]+$/', $other) || in_array($other, [CharacterizationEnv::templateDb(), CharacterizationEnv::masterDb()], true)) {
            throw new RuntimeException('unsafe database name ' . $other);
        }
        self::$other = $other; // from here on tearDownAfterClass() drops it, whatever fails below
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $other));
            CharacterizationEnv::rows($master, 'CREATE DATABASE ' . pg_escape_identifier($master, $other) . ' TEMPLATE ' . pg_escape_identifier($master, CharacterizationEnv::templateDb()));
            CharacterizationEnv::rows($master, 'DELETE FROM regnskab WHERE db = $1', [$other]);
            CharacterizationEnv::rows(
                $master,
                "INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
                 SELECT 'chartest_andet', dbhost, dbuser, $1, version, sidst, brugerantal, 1000000, '', administrator
                 FROM regnskab WHERE db = $2",
                [$other, CharacterizationEnv::testDb()]
            );
        } finally {
            pg_close($master);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$other !== '') {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            try {
                self::moveSession($master, CharacterizationEnv::testDb());
                CharacterizationEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()', [self::$other]);
                CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, self::$other));
                CharacterizationEnv::rows($master, "DELETE FROM regnskab WHERE db = $1 AND regnskab = 'chartest_andet'", [self::$other]);
            } finally {
                pg_close($master);
            }
            self::$other = '';
        }
        if (isset(self::$tenant) && self::$tenant && self::$company !== []) {
            self::rows("UPDATE adresser SET tlf = $1 WHERE art = 'S'", [self::$company['tlf']]);
        }
        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        // Every test starts with the session in the test tenant, whatever it did.
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            self::moveSession($master, CharacterizationEnv::testDb());
        } finally {
            pg_close($master);
        }
    }

    /** What admin/aaben_regnskab.php does when the user opens another regnskab: the same session, another db. */
    private static function moveSession($master, string $db): void
    {
        CharacterizationEnv::rows($master, 'UPDATE online SET db = $1 WHERE session_id = $2', [$db, CharacterizationEnv::SESSION_ID]);
    }

    /** @return array{status:int|bool, html:string} */
    private function page(string $query, string $body = ''): array
    {
        return $this->runChildJson('run_settings_section_page.php', [$query, $body, CharacterizationEnv::SESSION_ID]);
    }

    /**
     * The fields every form of the page that posts would send, by form id (or its position).
     *
     * @return array<string, array<string, string>>
     */
    private static function postForms(string $html): array
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $x = new DOMXPath($doc);
        $forms = [];
        foreach ($x->query('//form[translate(@method, "POST", "post") = "post"]') as $i => $form) {
            $fields = [];
            foreach ($x->query('.//input[@name]', $form) as $input) {
                $type = strtolower($input->getAttribute('type'));
                if (in_array($type, ['checkbox', 'radio'], true) && !$input->hasAttribute('checked')) {
                    continue;
                }
                $fields[$input->getAttribute('name')] = $input->getAttribute('value');
            }
            $forms[$form->getAttribute('id') !== '' ? $form->getAttribute('id') : 'form ' . $i] = $fields;
        }
        return $forms;
    }

    /** Stamdata as drawn in the test tenant, with the phone number changed as a user would type it. */
    private function stamdataForm(): array
    {
        $page = $this->page('s=company.data');
        $forms = self::postForms($page['html']);
        $this->assertArrayHasKey('st-form', $forms, 'Stamdata is drawn with its form');
        $form = $forms['st-form'];
        $this->assertArrayHasKey('f[company.data.phone]', $form, 'the form has the phone field');
        $form['f[company.data.phone]'] = self::NEW_PHONE;
        return $form;
    }

    private static function phone(string $db): ?string
    {
        $conn = CharacterizationEnv::connect($db);
        try {
            $row = CharacterizationEnv::one($conn, "SELECT tlf FROM adresser WHERE art = 'S'");
        } finally {
            pg_close($conn);
        }
        return $row === null ? null : (string) $row['tlf'];
    }

    /** @return array<string, array{string}> a section of each view: fields, a rows table, a list with drawers */
    public static function sections(): array
    {
        return ['Stamdata' => ['company.data'], 'Moms (rows)' => ['finance.vat'], 'Prislister (list)' => ['purchase.pricelists']];
    }

    #[DataProvider('sections')]
    public function test_every_form_that_posts_carries_the_regnskab_it_was_drawn_in(string $section): void
    {
        $forms = self::postForms($this->page('s=' . $section)['html']);

        $this->assertArrayHasKey('st-dialog-form', $forms, 'the section is drawn, with its dialog form (Gendan, actions)');
        foreach ($forms as $id => $fields) {
            $this->assertSame(CharacterizationEnv::testDb(), $fields['thisDb'] ?? null, "$id posts thisDb, the regnskab it was drawn in");
        }
    }

    public function test_stamdata_drawn_before_the_session_moved_saves_nothing_in_the_other_regnskab(): void
    {
        $form = $this->stamdataForm();
        $otherBefore = self::phone(self::$other);
        $tenantBefore = self::phone(CharacterizationEnv::testDb());

        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            self::moveSession($master, self::$other);
        } finally {
            pg_close($master);
        }
        $res = $this->page('s=company.data', http_build_query($form));

        $this->assertSame($otherBefore, self::phone(self::$other), 'the regnskab opened since keeps its company data');
        $this->assertSame($tenantBefore, self::phone(CharacterizationEnv::testDb()), 'the regnskab the form came from is not written either');
        $this->assertStringContainsString(
            'Du har skiftet regnskab fra ' . CharacterizationEnv::testDb() . ' til ' . self::$other . ', handling afbrudt',
            $res['html'],
            'the user is told why nothing was saved'
        );
    }

    public function test_stamdata_still_saves_in_the_regnskab_it_was_drawn_in(): void
    {
        $form = $this->stamdataForm();

        $res = $this->page('s=company.data', http_build_query($form));

        $this->assertSame(302, $res['status'], 'a saved form redirects back to the section');
        $this->assertSame(self::NEW_PHONE, self::phone(CharacterizationEnv::testDb()), 'the phone number is saved');
    }
}
