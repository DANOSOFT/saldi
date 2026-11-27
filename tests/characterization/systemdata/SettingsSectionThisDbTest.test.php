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
// page is drawn in a regnskab, the session is moved to a second one (as
// aaben_regnskab.php does it), and the form drawn before is posted. Both are
// fresh installations under database names shaped as a regnskab's
// (<master>_<n>), so online.php's check runs for the post that is refused and
// for the one that saves.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261115 CL/ASR Integration: master's test environment (2bd490c6) has no SESSION_ID and no template; the session comes from sessionId() and both regnskaber (drawn in, moved to) are fresh TestDatabase installations copied under <master>_<n> names, so online.php's thisDb check runs for the refused and the saved post alike.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class SettingsSectionThisDbTest extends CharacterizationTestCase
{
    private const NEW_PHONE = '86 12 34 59';

    /** The regnskab the forms are drawn in, and every test starts in. */
    private static string $home = '';

    /** The second regnskab the session moves to. */
    private static string $other = '';

    /** @var list<string> the regnskaber the class made (regnskab()), dropped by tearDownAfterClass() */
    private static array $copies = [];

    /** The phone number of the regnskab the forms are drawn in, as installed. */
    private static string $homePhone = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Two regnskaber with their databases named as a regnskab's is (<master>_<n>): the forms are drawn in the first,
        // and the session moves to the second. includes/online.php compares a posted thisDb with the session's regnskab
        // only when the session's database name has an underscore (the master's has none), so a form drawn and saved in
        // the class tenant (t + 24 hex digits) would get past that check without it running.
        self::$home = self::regnskab('chartest_hjem');
        self::$other = self::regnskab('chartest_andet');
        self::$homePhone = (string) self::phone(self::$home);
    }

    /**
     * A fresh installation, copied once under its name with "_1" for the last two hex digits (regnskab.db is 25
     * characters wide) and registered in the master registry under $name with the fresh one's routing fields; the
     * fresh one is released and tearDownAfterClass() drops the copy.
     */
    private static function regnskab(string $name): string
    {
        $fresh = TestDatabase::create(static::class . " (regnskab $name)");
        $copy = substr($fresh->name(), 0, 23) . '_1';
        try {
            if (!preg_match('/^t[a-f0-9]{22}_1$/', $copy) || in_array($copy, self::$copies, true)) {
                throw new RuntimeException('unsafe database name ' . $copy);
            }
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            try {
                CharacterizationEnv::rows($master, 'CREATE DATABASE ' . pg_escape_identifier($master, $copy) . ' TEMPLATE ' . pg_escape_identifier($master, $fresh->name()));
                self::$copies[] = $copy; // from here on tearDownAfterClass() drops it, whatever fails below
                CharacterizationEnv::rows(
                    $master,
                    'INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
                     SELECT $1, dbhost, dbuser, $2, version, sidst, brugerantal, posteringer, lukket, administrator
                     FROM regnskab WHERE db = $3',
                    [$name, $copy, $fresh->name()]
                );
            } finally {
                pg_close($master);
            }
        } finally {
            $fresh->release();
        }
        return $copy;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$copies !== []) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            try {
                self::moveSession($master, CharacterizationEnv::testDb());
                foreach (self::$copies as $copy) {
                    CharacterizationEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()', [$copy]);
                    CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $copy));
                    CharacterizationEnv::rows($master, 'DELETE FROM regnskab WHERE db = $1', [$copy]);
                }
            } finally {
                pg_close($master);
            }
            self::$copies = [];
            self::$home = self::$other = '';
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        // Every test starts with the session in the regnskab the forms are drawn in, its phone number as installed.
        $conn = CharacterizationEnv::connect(self::$home);
        try {
            CharacterizationEnv::rows($conn, "UPDATE adresser SET tlf = $1 WHERE art = 'S'", [self::$homePhone]);
        } finally {
            pg_close($conn);
        }
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            self::moveSession($master, self::$home);
        } finally {
            pg_close($master);
        }
    }

    protected function tearDown(): void
    {
        // The session goes back to the test tenant, whatever the test did.
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
        CharacterizationEnv::rows($master, 'UPDATE online SET db = $1 WHERE session_id = $2', [$db, CharacterizationEnv::sessionId()]);
    }

    /** @return array{status:int|bool, html:string} */
    private function page(string $query, string $body = ''): array
    {
        return $this->runChildJson('run_settings_section_page.php', [$query, $body, CharacterizationEnv::sessionId()]);
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

    /** Stamdata as drawn in the regnskab the session is in, with the phone number changed as a user would type it. */
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
            $this->assertSame(self::$home, $fields['thisDb'] ?? null, "$id posts thisDb, the regnskab it was drawn in");
        }
    }

    public function test_stamdata_drawn_before_the_session_moved_saves_nothing_in_the_other_regnskab(): void
    {
        $form = $this->stamdataForm();
        $otherBefore = self::phone(self::$other);
        $homeBefore = self::phone(self::$home);
        $this->assertNotSame(self::NEW_PHONE, $otherBefore, 'the regnskab opened since has another phone number before the post');
        $this->assertNotSame(self::NEW_PHONE, $homeBefore, 'the regnskab the form came from has another phone number before the post');

        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            self::moveSession($master, self::$other);
        } finally {
            pg_close($master);
        }
        $res = $this->page('s=company.data', http_build_query($form));

        $this->assertSame($otherBefore, self::phone(self::$other), 'the regnskab opened since keeps its company data');
        $this->assertSame($homeBefore, self::phone(self::$home), 'the regnskab the form came from is not written either');
        $this->assertStringContainsString(
            'Du har skiftet regnskab fra ' . self::$home . ' til ' . self::$other . ', handling afbrudt',
            $res['html'],
            'the user is told why nothing was saved'
        );
    }

    public function test_stamdata_still_saves_in_the_regnskab_it_was_drawn_in(): void
    {
        $form = $this->stamdataForm();
        $this->assertNotSame(self::NEW_PHONE, self::phone(self::$home), 'the regnskab has another phone number before the post');

        $res = $this->page('s=company.data', http_build_query($form));

        $this->assertSame(302, $res['status'], 'a saved form redirects back to the section');
        $this->assertSame(self::NEW_PHONE, self::phone(self::$home), 'the phone number is saved');
    }
}
