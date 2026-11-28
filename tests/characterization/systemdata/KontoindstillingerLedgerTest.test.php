<?php
// tests/characterization/systemdata/KontoindstillingerLedgerTest.test.php
//
// Kontoindstillinger keeps the regnskab's name and delete where
// Abonnement & konto (G1.4) does not exist (testlab round 1,
// old-year-purge-reset, 2026-11-05).
//
// The redesign moved Skift navn, Nulstil regnskab and Slet regnskab from
// diverse.php?sektion=kontoindstillinger to settingsSection.php?s=company.account,
// a section that only exists with the hosted module (settings system/hosted_licence,
// or ../.ht_keys.txt on Saldi's own servers). The old page dropped them on every
// installation and linked to the section instead, which redirected to
// settings.php?err=module wherever the module is off: no rename or delete
// at all there, while the hub row "Abonnement & konto" opens this page.
//
// Nulstil regnskab is not offered here (owner, 14 Nov 2026): a ledger is reset
// only from the admin panel (admin/admin_panel.php), on either kind of installation.
//
// Pins, driven through systemdata/diverse.php as a browser does: without the
// hosted module the page shows Skift navn and Slet regnskab, no Nulstil and no
// link to the section, and its posts rename and close the regnskab as before the
// redesign, while a posted nulstil changes nothing. With the module the page
// links to the section and the same posts change nothing (the section's password
// stays the only way there).
//
// The two forms carry the regnskab they were drawn in (thisDb), and a form
// drawn in one regnskab changes neither that one nor a second one the session
// has moved to since (admin/aaben_regnskab.php keeps the session and its CSRF
// token): includes/online.php refuses it, as it refuses Stamdata's.
//
// On a LATIN9 installation the regnskab's name reaches the page in LATIN9, and
// the page shows it, offers it for renaming and names it at Slet, as it does on
// UTF8 (run_diverse_page.php serves includes/connect.php with LATIN9). Escaped
// as UTF-8, a name such as 'Jørgens ApS' came out empty.
//
// Every request is checked for PHP warnings: only the legacy floor
// (LEGACY_WARNINGS, statements this change leaves alone) may appear
// (doc/ai/convention_testing_error_visibility.md).
//
// Every test works in a fresh regnskab of its own, registered in the master
// database under a database name shaped as a regnskab's (cloneLedger()), with
// the session moved to it, as SettingsSectionThisDbTest does: a delete never
// touches the class's own test tenant.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261114 CL/ASR Nulstil regnskab left the page (owner, 14 Nov 2026, reset only from the admin panel): the tests pin rename and delete, and that a posted nulstil changes nothing.
// 20261115 CL/ASR Integration: master's test environment (2bd490c6) has no SESSION_ID and no template; the session comes from sessionId() and each regnskab is a fresh TestDatabase installation copied under a <master>_<n> name, as online.php's thisDb check needs.
// 20261124 CL/ASR Integration: TestDatabase names its installations <master>_<n> itself since master's MB-154, so each regnskab is used as installed, renamed in the registry, and no longer copied.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class KontoindstillingerLedgerTest extends CharacterizationTestCase
{
    private const LEDGER_NAME = 'chartest_konto';
    private const OTHER_NAME = 'chartest_konto_b';

    /**
     * The legacy floor: warnings from statements this change leaves alone, each as the file, the statement that raises
     * it and the message. The same requests raise the first five on the parent commit; the last two come from the second
     * include of online.php that Slet does once the sessions are gone, as the page did before the redesign. A warning
     * from any other statement fails the test.
     */
    private const LEGACY_WARNINGS = [
        // table_exists() reads the row of a table that is not there
        ['includes/db_query.php', '($r[\'tablename\'])?$tbl_exists=1:$tbl_exists=0;', 'Trying to access array offset on false'],
        // the page reads its query string before it knows the section
        ['systemdata/diverse.php', '$skiftnavn  = if_isset($_GET[\'skiftnavn\']);', 'Undefined array key "skiftnavn"'],
        ['systemdata/diverse.php', '$valg    = if_isset($_GET[\'valg\']);', 'Undefined array key "valg"'],
        ['systemdata/top.php', 'if ($popup) $returside="../includes/luk.php";', 'Undefined variable $popup'],
        ['includes/online.php', 'if (!strpos($css, \'mysale\') && $bruger_id) {', 'strpos(): Passing null to parameter #1 ($haystack) of type string is deprecated'],
        // Slet includes online.php again once the regnskab's sessions are gone, as the page did before the redesign
        ['includes/online.php', 'if ($r[\'var_value\']) {', 'Trying to access array offset on false'],
        ['includes/online.php', 'if ($r[\'id\']) $qtxt = "update settings set var_value=\'$timezone\' where id=\'$r[id]\'";', 'Trying to access array offset on false'],
    ];

    /** The regnskab the current test draws its forms in ('' when none). */
    private string $ledger = '';

    /** The installation's charset the pages run with ('' for the stack's own, UTF8). */
    private string $encode = '';

    /** @var list<TestDatabase> every regnskab the current test made (cloneLedger()), released by tearDown() */
    private array $clones = [];

    /** @var array<string, string|null> the seeded session row, restored after a delete removed it */
    private static array $session = [];

    /** @var array<string, list<string>> repository files by path, for the legacy-floor check */
    private static array $sources = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            self::$session = CharacterizationEnv::one($master, 'SELECT * FROM online WHERE session_id = $1', [CharacterizationEnv::sessionId()]) ?? [];
        } finally {
            pg_close($master);
        }
        if (self::$session === []) {
            self::fail('the seeded online session row is missing');
        }
    }

    protected function setUp(): void
    {
        $this->ledger = $this->cloneLedger(self::LEDGER_NAME);
        $this->moveSession($this->ledger);
    }

    protected function tearDown(): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            // A delete removes the session row with the regnskab's other sessions; every test starts from the seeded one.
            CharacterizationEnv::rows($master, 'DELETE FROM online WHERE session_id = $1', [CharacterizationEnv::sessionId()]);
            $columns = array_keys(self::$session);
            $placeholders = array_map(static fn(int $i): string => '$' . ($i + 1), array_keys($columns));
            CharacterizationEnv::rows(
                $master,
                'INSERT INTO online (' . implode(', ', array_map(static fn(string $c): string => pg_escape_identifier($master, $c), $columns)) . ') VALUES (' . implode(', ', $placeholders) . ')',
                array_values(self::$session)
            );
            foreach ($this->clones as $clone) {
                CharacterizationEnv::rows($master, 'DELETE FROM online WHERE db = $1', [$clone->name()]);
                $clone->release();
            }
            $this->clones = [];
            $this->ledger = '';
            $this->encode = '';
        } finally {
            pg_close($master);
        }
    }

    /**
     * A fresh regnskab of its own, registered in the master registry under $name, with its database named as a
     * regnskab's is (<master>_<n>).
     *
     * includes/online.php refuses a form drawn in another regnskab only when the session's database name has an
     * underscore, as every regnskab's has (the master's has none). TestDatabase names its installations so (MB-154);
     * the registry row it made is renamed to $name, and tearDown() releases the installation.
     */
    private function cloneLedger(string $name): string
    {
        $fresh = TestDatabase::create(static::class . " (regnskab $name)");
        $this->clones[] = $fresh; // from here on tearDown() releases it, whatever fails below
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'UPDATE regnskab SET regnskab = $1 WHERE db = $2', [$name, $fresh->name()]);
        } finally {
            pg_close($master);
        }
        return $fresh->name();
    }

    /** The class's own regnskab's name in the master registry (TestDatabase::registerAccount()), a name another regnskab has. */
    private static function tenantName(): string
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            return (string) (CharacterizationEnv::one($master, 'SELECT regnskab FROM regnskab WHERE db = $1', [CharacterizationEnv::testDb()])['regnskab'] ?? '');
        } finally {
            pg_close($master);
        }
    }

    /** What admin/aaben_regnskab.php does when the user opens another regnskab: the same session, another db. */
    private function moveSession(string $db): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'UPDATE online SET db = $1 WHERE session_id = $2', [$db, CharacterizationEnv::sessionId()]);
        } finally {
            pg_close($master);
        }
    }

    /**
     * Runs a statement in a clone, by default the one the forms are drawn in.
     *
     * @param array<int, mixed> $params
     * @return array<int, array<string, string|null>>
     */
    private function inLedger(string $sql, array $params = [], ?string $db = null): array
    {
        $conn = CharacterizationEnv::connect($db ?? $this->ledger);
        try {
            return CharacterizationEnv::rows($conn, $sql, $params);
        } finally {
            pg_close($conn);
        }
    }

    /** A clone's row in the master registry. */
    private function registry(?string $db = null): array
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            return CharacterizationEnv::one($master, 'SELECT regnskab, lukket, logintekst FROM regnskab WHERE db = $1', [$db ?? $this->ledger]) ?? [];
        } finally {
            pg_close($master);
        }
    }

    /** Names the regnskab in the master registry, where includes/online.php reads $regnskab. */
    private function nameLedger(string $name): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'UPDATE regnskab SET regnskab = $1 WHERE db = $2', [$name, $this->ledger]);
        } finally {
            pg_close($master);
        }
    }

    /** The licence flag SettingsService::hasModule('hosted') reads before it looks for ../.ht_keys.txt. */
    private function hosted(bool $on, ?string $db = null): void
    {
        $this->inLedger("DELETE FROM settings WHERE var_grp = 'system' AND var_name = 'hosted_licence'", [], $db);
        $this->inLedger("INSERT INTO settings (var_grp, var_name, var_value, var_description, user_id) VALUES ('system', 'hosted_licence', $1, '', 0)", [$on ? 'on' : ''], $db);
    }

    /**
     * One request to diverse.php, which may raise no warning beyond the legacy floor.
     *
     * @return array{status:int|bool, html:string, warnings:list<string>}
     */
    private function page(string $query, string $body = ''): array
    {
        $res = $this->runChildJson('run_diverse_page.php', [$query, $body, CharacterizationEnv::sessionId(), $this->encode]);
        $new = array_values(array_filter($res['warnings'], static fn(string $warning): bool => !self::isLegacyWarning($warning)));
        $this->assertSame([], $new, ($body === '' ? 'GET' : 'POST') . " diverse.php?$query raised warnings beyond the legacy floor");
        return $res;
    }

    /** Whether a "path:line: message" warning from the runner is one of LEGACY_WARNINGS. */
    private static function isLegacyWarning(string $warning): bool
    {
        if (!preg_match('/^(.+?):(\d+): (.*)$/s', $warning, $m)) {
            return false;
        }
        [, $file, $line, $message] = $m;
        if (!isset(self::$sources[$file])) {
            $path = CharacterizationEnv::repoRoot() . '/' . $file;
            self::$sources[$file] = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
        }
        $statement = trim(self::$sources[$file][(int) $line - 1] ?? '');
        foreach (self::LEGACY_WARNINGS as [$legacyFile, $legacyStatement, $legacyMessage]) {
            if ($file === $legacyFile && $statement === $legacyStatement && $message === $legacyMessage) {
                return true;
            }
        }
        return false;
    }

    /**
     * The hidden fields a form was drawn with (the CSRF token, thisDb), read from the page as the browser got it.
     *
     * @return array<string, string>
     */
    private function drawn(string $form, string $query = 'sektion=kontoindstillinger'): array
    {
        $html = $this->page($query)['html'];
        $this->assertSame(1, preg_match("~<form name='" . preg_quote($form, '~') . "'[^>]*method='post'>(.*?)</form>~s", $html, $m), "the page draws the form $form");
        preg_match_all("~<input type='hidden' name='([^']+)' value='([^']*)'>~", $m[1], $hidden, PREG_SET_ORDER);
        $fields = [];
        foreach ($hidden as [, $name, $value]) {
            $fields[$name] = html_entity_decode($value, ENT_QUOTES);
        }
        $this->assertArrayHasKey('csrf_token', $fields, "$form carries the CSRF token");
        return $fields;
    }

    /**
     * Submits a form of Kontoindstillinger as drawn on $query, with the fields a user fills in or presses.
     *
     * @param array<string, string> $fields
     * @return array{status:int|bool, html:string, warnings:list<string>}
     */
    private function post(string $form, array $fields, string $query = 'sektion=kontoindstillinger'): array
    {
        return $this->page('sektion=kontoindstillinger', http_build_query($this->drawn($form, $query) + $fields));
    }

    /** A debtor, an item and an order, as the lab's regnskab has them. */
    private function seedLedger(?string $db = null): void
    {
        $this->inLedger("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('90001', 'Chartest kunde', 'D')", [], $db);
        $this->inLedger("INSERT INTO varer (varenr, beskrivelse, beholdning) VALUES ('CHK-1', 'Chartest vare', 5)", [], $db);
        $this->inLedger("INSERT INTO ordrer (art, status, firmanavn, ordredate) VALUES ('DO', 1, 'Chartest kunde', CURRENT_DATE)", [], $db);
    }

    private function rowCount(string $sql, ?string $db = null): int
    {
        return (int) $this->inLedger($sql, [], $db)[0]['n'];
    }

    public function test_without_the_hosted_module_the_page_offers_rename_and_delete(): void
    {
        $this->hosted(false);

        $res = $this->page('sektion=kontoindstillinger');

        $this->assertNotSame(403, $res['status']);
        $html = $res['html'];
        $this->assertStringNotContainsString('settingsSection.php?s=company.account', $html, 'no link to a section that redirects to settings.php?err=module');
        $this->assertStringContainsString("href='settingsSection.php?s=company.localisation'", $html, 'the time zone still moved to Lokalisering');
        $this->assertStringContainsString(self::LEDGER_NAME, $html, 'the regnskab\'s name');
        $this->assertStringContainsString('diverse.php?sektion=kontoindstillinger&amp;skiftnavn=ja', $html, 'the way to rename it');
        // Nulstil regnskab is the admin panel's only (owner, 14 Nov 2026), so the page draws no reset form.
        $this->assertStringNotContainsString("name='nulstil_regnskab'", $html, 'no Nulstil regnskab form');
        $this->assertStringNotContainsString("name='nulstil'", $html, 'no Nulstil button');
        $this->assertMatchesRegularExpression("/<form name='slet_regnskab'[^>]*method='post'>.*name='slet_regnskab'.*name='slet'.*<\/form>/s", $html);

        $rename = $this->page('sektion=kontoindstillinger&skiftnavn=ja')['html'];
        $this->assertStringContainsString("name='newName' value='" . self::LEDGER_NAME . "'", $rename);
    }

    public function test_the_rename_and_delete_forms_carry_the_regnskab_they_were_drawn_in(): void
    {
        $this->hosted(false);

        // The Nulstil form is gone (owner, 14 Nov 2026: reset only from the admin panel); the two forms left still post thisDb.
        $this->assertSame($this->ledger, $this->drawn('slet_regnskab')['thisDb'] ?? null, 'Slet posts thisDb');
        $this->assertSame($this->ledger, $this->drawn('diverse', 'sektion=kontoindstillinger&skiftnavn=ja')['thisDb'] ?? null, 'Skift navn posts thisDb');
    }

    public function test_with_the_hosted_module_the_page_links_to_abonnement_og_konto(): void
    {
        $this->hosted(true);

        $html = $this->page('sektion=kontoindstillinger')['html'];

        $this->assertStringContainsString("href='settingsSection.php?s=company.account'", $html);
        $this->assertStringNotContainsString("name='nulstil'", $html);
        $this->assertStringNotContainsString("name='slet'", $html);
        $this->assertStringNotContainsString('skiftnavn=ja', $html);
    }

    public function test_without_the_hosted_module_a_posted_nulstil_changes_nothing(): void
    {
        // Was: Nulstil empties the regnskab here. The owner moved the reset to the admin panel (14 Nov 2026), so the post must change nothing.
        $this->hosted(false);
        $this->seedLedger();

        // The page draws no Nulstil form any more; the post carries the token and thisDb of the Slet form, as a stale tab would.
        $res = $this->post('slet_regnskab', ['behold_debkred' => 'on', 'behold_varer' => 'on', 'nulstil' => 'Nulstil']);

        $this->assertStringNotContainsString('regnskab nulstillet', $res['html']);
        $this->assertSame(1, $this->rowCount('SELECT count(*) AS n FROM ordrer'), 'the order stays');
        $this->assertSame(1, $this->rowCount("SELECT count(*) AS n FROM varer WHERE varenr = 'CHK-1' AND beholdning = 5"), 'the item keeps its stock');
        $after = $this->registry();
        $this->assertSame(self::LEDGER_NAME, $after['regnskab']);
        $this->assertSame('', (string) $after['lukket'], 'the regnskab stays open');
    }

    public function test_without_the_hosted_module_skift_navn_renames_the_regnskab_unless_the_name_is_taken(): void
    {
        $this->hosted(false);

        $this->post('diverse', ['newName' => 'Chartest Omdøbt ApS', 'changeAccountName' => 'Skift navn'], 'sektion=kontoindstillinger&skiftnavn=ja');
        $this->assertSame('Chartest Omdøbt ApS', $this->registry()['regnskab']);

        $taken = self::tenantName();
        $this->assertNotSame('', $taken, 'the class\'s own regnskab is registered');
        $res = $this->post('diverse', ['newName' => $taken, 'changeAccountName' => 'Skift navn'], 'sektion=kontoindstillinger&skiftnavn=ja'); // the class's own regnskab's name
        $this->assertSame('Chartest Omdøbt ApS', $this->registry()['regnskab'], 'a name another regnskab has is refused');
        $this->assertStringContainsString('alert(', $res['html']);
    }

    public function test_without_the_hosted_module_slet_closes_the_regnskab_only_with_the_box_ticked(): void
    {
        $this->hosted(false);

        $this->post('slet_regnskab', ['slet' => 'Slet']);
        $this->assertSame('', (string) $this->registry()['lukket'], 'without the tick nothing is closed');

        $this->post('slet_regnskab', ['slet_regnskab' => 'on', 'slet' => 'Slet']);
        $closed = $this->registry();
        $this->assertSame('on', $closed['lukket']);
        $this->assertStringStartsWith('slettet af chartest den ', (string) $closed['logintekst']);
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            $this->assertNull(CharacterizationEnv::one($master, 'SELECT session_id FROM online WHERE db = $1', [$this->ledger]), 'every session of the regnskab ends');
        } finally {
            pg_close($master);
        }
    }

    public function test_on_a_latin9_installation_the_page_keeps_the_regnskab_s_name(): void
    {
        $this->hosted(false);
        $this->nameLedger('Jørgen & Søn ApS');
        $this->encode = 'LATIN9';

        $html = $this->page('sektion=kontoindstillinger')['html'];
        $this->assertStringContainsString('charset=ISO-8859-1', $html, 'the page runs as on a LATIN9 installation');
        $this->assertStringContainsString("<span style='font-weight:bold'>Jørgen &amp; Søn ApS</span>", $html, 'Dit regnskab hedder');
        $this->assertStringContainsString('<b>Slet regnskab: Jørgen &amp; Søn ApS</b>', $html);

        $rename = $this->page('sektion=kontoindstillinger&skiftnavn=ja')['html'];
        $this->assertStringContainsString("name='newName' value='Jørgen &amp; Søn ApS'", $rename);
    }

    public function test_with_the_hosted_module_the_old_posts_change_nothing(): void
    {
        $this->hosted(true);
        $this->seedLedger();

        // The page draws none of the three forms here; the posts carry the token of the form it does draw.
        $this->post('maxusers', ['nulstil' => 'Nulstil']);
        $this->post('maxusers', ['newName' => 'Chartest Omdøbt ApS', 'changeAccountName' => 'Skift navn']);
        $this->post('maxusers', ['slet_regnskab' => 'on', 'slet' => 'Slet']);

        $this->assertSame(1, $this->rowCount('SELECT count(*) AS n FROM ordrer'), 'no reset from the ledger\'s own settings');
        $after = $this->registry();
        $this->assertSame(self::LEDGER_NAME, $after['regnskab']);
        $this->assertSame('', (string) $after['lukket']);
    }

    /** @return array<string, array{string, string, array<string, string>}> form, the page it is drawn on, what the user fills in */
    public static function forms(): array
    {
        // No 'Nulstil' case: the page no longer draws that form (owner, 14 Nov 2026: reset only from the admin panel).
        return [
            'Slet' => ['slet_regnskab', 'sektion=kontoindstillinger', ['slet_regnskab' => 'on', 'slet' => 'Slet']],
            'Skift navn' => ['diverse', 'sektion=kontoindstillinger&skiftnavn=ja', ['newName' => 'Chartest Omdøbt ApS', 'changeAccountName' => 'Skift navn']],
        ];
    }

    /** @param array<string, string> $fields */
    #[DataProvider('forms')]
    public function test_a_form_drawn_before_the_session_moved_to_another_regnskab_changes_neither(string $form, string $query, array $fields): void
    {
        $this->hosted(false);
        $this->seedLedger();
        $other = $this->cloneLedger(self::OTHER_NAME);
        $this->hosted(false, $other);
        $this->seedLedger($other);
        $drawn = $this->drawn($form, $query);

        $this->moveSession($other);
        $res = $this->page('sektion=kontoindstillinger', http_build_query($drawn + $fields));

        $this->assertStringContainsString("Du har skiftet regnskab fra {$this->ledger} til $other, handling afbrudt", $res['html'], 'the user is told why nothing happened');
        $this->assertStringNotContainsString('regnskab nulstillet', $res['html']);
        foreach ([$this->ledger => self::LEDGER_NAME, $other => self::OTHER_NAME] as $db => $name) {
            $this->assertSame(1, $this->rowCount('SELECT count(*) AS n FROM ordrer', $db), "$db keeps its order");
            $this->assertSame(1, $this->rowCount("SELECT count(*) AS n FROM varer WHERE varenr = 'CHK-1' AND beholdning = 5", $db), "$db keeps its stock");
            $registry = $this->registry($db);
            $this->assertSame($name, $registry['regnskab'], "$db keeps its name");
            $this->assertSame('', (string) $registry['lukket'], "$db stays open");
        }
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            $this->assertNotNull(CharacterizationEnv::one($master, 'SELECT session_id FROM online WHERE session_id = $1 AND db = $2', [CharacterizationEnv::sessionId(), $other]), 'the session stays in the regnskab opened since');
        } finally {
            pg_close($master);
        }
    }
}
