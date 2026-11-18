<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../includes/std_func.php';

// 20261118 CL/MMR SST-898 (review 4) The SST-898 login step on a real PostgreSQL database: an account without the formularer or
//                 tekster table is skipped without an error and migrated at a later login, each table on its own; a populated
//                 account gets "Mobilnr." on its forms and loses only the old default texts; a second login writes nothing.
//                 Each login is a child process (support/run_formular_fax_to_mobile.php) that runs the lines from includes/betweenUpdates.php.
// 20261118 CL/MMR SST-898 (review 6) The runner's error message is read with ifset().
// 20261118 CL/MMR SST-898 (review 7) A form saved by another session between the migration's read and its write is kept; the next login migrates it if it still holds a fax variable.
// 20261118 CL/MMR SST-898 (review 8) With the real includes/db_query.php wrappers: a form with " like " in its prose is migrated with the prose intact, and a form
//                 saved by another session while the migration waits for its row lock is kept, also when the saved text differs from the read one only by "like"/"ilike".
// 20261118 CL/MMR SST-898 (review 9) On a LATIN9 connection accented forms (Danish, English, Spanish with the new "Móvil") are stored exactly; a second run changes nothing.
final class FormularFaxToMobileLoginTest extends TestCase
{
    private ?TestDatabase $database = null;
    /** @var resource|\PgSql\Connection|null */
    private $db = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('pgsql extension not loaded');
        }
        if (CharacterizationEnv::pgPass() === '') {
            self::markTestSkipped('SALDI_CHAR_PGPASS is required for database characterization tests');
        }
        $this->database = TestDatabase::create(static::class, false);
        $this->db = CharacterizationEnv::connect($this->database->name());
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            pg_close($this->db);
            $this->db = null;
        }
        if ($this->database !== null) {
            $this->database->release();
            $this->database = null;
        }
    }

    public function testAnAccountWithoutTheTablesIsSkippedAndMigratedOnceTheyExist(): void
    {
        self::assertSame([], $this->login(), 'no formularer and no tekster: nothing to do and no error');
        self::assertSame([], $this->login(), 'and no flag that would stop a later login');

        $this->createForms();
        $this->createTexts();
        self::assertNotSame([], $this->login());
        self::assertSame([['Dansk', 'Mobilnr.: $egen_mobile']], $this->forms());
        self::assertSame([['378', '1', 'Mobiltlf.']], $this->texts());
    }

    public function testTextsAreCleanedWhenTheFormTableIsMissing(): void
    {
        $this->createTexts();
        $this->login();
        self::assertSame([['378', '1', 'Mobiltlf.']], $this->texts());
        self::assertNull(CharacterizationEnv::one($this->db, "SELECT 1 AS found FROM information_schema.tables WHERE table_name = 'formularer'"));
    }

    public function testFormsAreRewrittenWhenTheTextTableIsMissing(): void
    {
        $this->createForms();
        $this->login();
        self::assertSame([['Dansk', 'Mobilnr.: $egen_mobile']], $this->forms());
        self::assertNull(CharacterizationEnv::one($this->db, "SELECT 1 AS found FROM information_schema.tables WHERE table_name = 'tekster'"));
    }

    public function testAPopulatedAccountIsMigratedOnceAndASecondLoginWritesNothing(): void
    {
        $this->createForms([
            ['Dansk', 'Cvr.: $eget_cvrnr; * Tlf: $egen_tlf; * Fax: $egen_fax; * $egen_bank_navn'],
            ['English', 'Fax: $egen_fax'],
            ['Norsk', 'Faks: $eget_fax'],
            ['Dansk', 'Support Fax: $egen_fax'],
            ['Dansk', '$adresser_fax'],
            ['Dansk', 'Faxnummer findes ikke her'],
            ['Dansk', 'Tlf: $egen_tlf'],
        ]);
        $this->createTexts([
            [378, 1, 'Telefax'], [378, 2, 'Fax'], [378, 3, 'Mobil'],
            [50, 1, 'Mobiltlf.'], [50, 2, 'Mobile'], [50, 3, 'Faks'],
            [5230, 1, 'Mobilnummer'], [5230, 2, 'Mobile number'], [5230, 3, 'Mobilnr.'],
            [100, 1, 'Fax'],
        ]);

        $writes = $this->login();
        self::assertCount(5 + 5, $writes, 'five forms, and the old texts of five text/language pairs');
        self::assertSame([
            ['Dansk', 'Cvr.: $eget_cvrnr; * Tlf: $egen_tlf; * Mobilnr.: $egen_mobile; * $egen_bank_navn'],
            ['English', 'Mobile: $egen_mobile'],
            ['Norsk', 'Mobilnr.: $egen_mobile'],
            ['Dansk', 'Support Fax: $egen_mobile'],
            ['Dansk', '$adresser_mobile'],
            ['Dansk', 'Faxnummer findes ikke her'],
            ['Dansk', 'Tlf: $egen_tlf'],
        ], $this->forms());
        // the customer's "Mobiltlf.", the English "Mobile"/"Mobile number", the new "Mobilnr." and other ids are kept
        self::assertSame([
            ['50', '1', 'Mobiltlf.'], ['50', '2', 'Mobile'],
            ['100', '1', 'Fax'],
            ['5230', '2', 'Mobile number'], ['5230', '3', 'Mobilnr.'],
        ], $this->texts());

        $before = CharacterizationEnv::rows($this->db, 'SELECT id, xmin::text AS xmin FROM formularer ORDER BY id');
        self::assertSame([], $this->login(), 'a second login writes nothing');
        self::assertSame($before, CharacterizationEnv::rows($this->db, 'SELECT id, xmin::text AS xmin FROM formularer ORDER BY id'));
    }

    public function testAFormSavedBetweenTheReadAndTheWriteIsKept(): void
    {
        $this->createForms([['Dansk', 'Fax: $egen_fax'], ['English', 'Fax: $egen_fax']]);
        self::assertSame(1, $this->loginWithSave('Updated contact: $egen_mobile')['forms'], 'only the row nobody else saved counts');
        self::assertSame([['Dansk', 'Updated contact: $egen_mobile'], ['English', 'Mobile: $egen_mobile']], $this->forms());
        self::assertSame([], $this->login(), 'the saved text has no fax variable: nothing left to do');
    }

    public function testAFormSavedInBetweenWithAFaxVariableIsMigratedAtTheNextLogin(): void
    {
        $this->createForms([['Dansk', 'Fax: $egen_fax'], ['Dansk', '$adresser_fax']]);
        self::assertSame(1, $this->loginWithSave('Tlf: $egen_tlf * Fax: $egen_fax')['forms']);
        self::assertSame([['Dansk', 'Tlf: $egen_tlf * Fax: $egen_fax'], ['Dansk', '$adresser_mobile']], $this->forms());
        self::assertCount(1, $this->login());
        self::assertSame([['Dansk', 'Tlf: $egen_tlf * Mobilnr.: $egen_mobile'], ['Dansk', '$adresser_mobile']], $this->forms());
    }

    public function testAFormWithoutALanguageIsRewritten(): void
    {
        CharacterizationEnv::rows($this->db, 'CREATE TABLE formularer (id serial PRIMARY KEY, formular integer, art integer, beskrivelse text, sprog varchar(25))');
        CharacterizationEnv::rows($this->db, "INSERT INTO formularer (formular, art, beskrivelse, sprog) VALUES (1, 2, 'Fax: \$egen_fax', NULL)");
        self::assertCount(1, $this->login());
        self::assertSame('Mobilnr.: $egen_mobile', CharacterizationEnv::one($this->db, 'SELECT beskrivelse FROM formularer')['beskrivelse']);
    }

    public function testTheRealWrapperKeepsProseWithLikeInIt(): void
    {
        $this->createForms([['English', 'Fax: $egen_fax; Customers like this wording'], ['Dansk', 'Fax: $egen_fax']]);
        self::assertSame(['forms' => 2, 'texts' => 0], $this->productionLogin());
        self::assertSame([['English', 'Mobile: $egen_mobile; Customers like this wording'], ['Dansk', 'Mobilnr.: $egen_mobile']], $this->forms());
        self::assertSame(['forms' => 0, 'texts' => 0], $this->productionLogin());
    }

    public function testTheRealWrapperKeepsAFormSavedWhileTheMigrationWaits(): void
    {
        $this->createForms([['English', 'Fax: $egen_fax; Customers like this wording'], ['Dansk', 'Fax: $egen_fax']]);
        $first = (int)CharacterizationEnv::one($this->db, 'SELECT MIN(id) AS id FROM formularer')['id'];
        // the saved text differs from the read one only by "ilike", which the wrapper's rewrite would produce
        $result = $this->productionLoginWhileAnotherSessionSaves($first, 'Fax: $egen_fax; Customers ilike this wording');
        self::assertSame(['forms' => 1, 'texts' => 0], $result, 'only the row nobody else saved counts');
        self::assertSame([['English', 'Fax: $egen_fax; Customers ilike this wording'], ['Dansk', 'Mobilnr.: $egen_mobile']], $this->forms());
        // the next login migrates the saved form, which still holds a fax variable
        self::assertSame(['forms' => 1, 'texts' => 0], $this->productionLogin());
        self::assertSame([['English', 'Mobile: $egen_mobile; Customers ilike this wording'], ['Dansk', 'Mobilnr.: $egen_mobile']], $this->forms());
    }

    public function testTheRealWrapperOnALatin9ConnectionKeepsAccentedText(): void
    {
        $this->createForms([
            ['English', 'Fax: $egen_fax; Customers like Ærø'],
            ['Spansk', 'Fax: $egen_fax; Señor García'],
            ['Dansk', 'Telefax: $egen_fax * Åbent 8-16 €'],
            ['Dansk', 'Fax: $egen_fax'],
        ]);
        self::assertSame(['forms' => 4, 'texts' => 0], $this->productionLogin('LATIN9'));
        self::assertSame([
            ['English', 'Mobile: $egen_mobile; Customers like Ærø'],
            ['Spansk', 'Móvil: $egen_mobile; Señor García'],
            ['Dansk', 'Mobilnr.: $egen_mobile * Åbent 8-16 €'],
            ['Dansk', 'Mobilnr.: $egen_mobile'],
        ], $this->forms());
        $before = CharacterizationEnv::rows($this->db, 'SELECT id, xmin::text AS xmin FROM formularer ORDER BY id');
        self::assertSame(['forms' => 0, 'texts' => 0], $this->productionLogin('LATIN9'));
        self::assertSame(['forms' => 0, 'texts' => 0], $this->productionLogin());
        self::assertSame($before, CharacterizationEnv::rows($this->db, 'SELECT id, xmin::text AS xmin FROM formularer ORDER BY id'));
    }

    /** @param list<array{string, string}> $rows sprog, beskrivelse */
    private function createForms(array $rows = [['Dansk', 'Fax: $egen_fax']]): void
    {
        CharacterizationEnv::rows($this->db, 'CREATE TABLE formularer (id serial PRIMARY KEY, formular integer, art integer, beskrivelse text, sprog varchar(25))');
        foreach ($rows as [$sprog, $text]) {
            CharacterizationEnv::rows($this->db, 'INSERT INTO formularer (formular, art, beskrivelse, sprog) VALUES (1, 2, $1, $2)', [$text, $sprog]);
        }
    }

    /** @param list<array{int, int, string}> $rows tekst_id, sprog_id, tekst */
    private function createTexts(array $rows = [[378, 1, 'Telefax'], [378, 1, 'Mobiltlf.']]): void
    {
        CharacterizationEnv::rows($this->db, 'CREATE TABLE tekster (id serial PRIMARY KEY, sprog_id integer, tekst_id integer, tekst text, tekst_key varchar(255))');
        foreach ($rows as [$id, $sprog, $text]) {
            CharacterizationEnv::rows($this->db, 'INSERT INTO tekster (tekst_id, sprog_id, tekst) VALUES ($1, $2, $3)', [$id, $sprog, $text]);
        }
    }

    /** @return list<array{string, string}> sprog, beskrivelse */
    private function forms(): array
    {
        return array_map(fn ($r) => [$r['sprog'], $r['beskrivelse']], CharacterizationEnv::rows($this->db, 'SELECT sprog, beskrivelse FROM formularer ORDER BY id'));
    }

    /** @return list<array{string, string, string}> tekst_id, sprog_id, tekst */
    private function texts(): array
    {
        return array_map(fn ($r) => [$r['tekst_id'], $r['sprog_id'], $r['tekst']], CharacterizationEnv::rows($this->db, 'SELECT tekst_id, sprog_id, tekst FROM tekster ORDER BY tekst_id, sprog_id, id'));
    }

    /**
     * One login during which another session saves $text into the first form the migration rewrites, just before
     * the migration's UPDATE. @return array{forms: int, writes: list<string>}
     */
    private function loginWithSave(string $text): array
    {
        $res = CharacterizationEnv::runChild(__DIR__ . '/support/run_formular_fax_to_mobile.php', [$this->database->name(), 'stub', $text]);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        self::assertIsArray($json, "runner must emit JSON (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        self::assertArrayNotHasKey('error', $json, (string)ifset($json, 'error', ''));
        return $json;
    }

    /** One login with the real includes/db_query.php wrappers, optionally with a client encoding. @return array{forms: int, texts: int} */
    private function productionLogin(string $clientEncoding = ''): array
    {
        $args = [$this->database->name(), 'production'];
        if ($clientEncoding !== '') {
            $args[] = $clientEncoding;
        }
        $res = CharacterizationEnv::runChild(__DIR__ . '/support/run_formular_fax_to_mobile.php', $args);
        return $this->runnerResult($res['stdout'], $res['stderr']);
    }

    /**
     * One login with the real wrappers while another session saves $text into form $id: that session updates the row
     * in an open transaction, the login reads the old text and its UPDATE waits for the row lock, and the other
     * session commits once the UPDATE is waiting. @return array{forms: int, texts: int}
     */
    private function productionLoginWhileAnotherSessionSaves(int $id, string $text): array
    {
        $other = CharacterizationEnv::connect($this->database->name());
        CharacterizationEnv::rows($other, 'BEGIN');
        CharacterizationEnv::rows($other, 'UPDATE formularer SET beskrivelse = $1 WHERE id = $2', [$text, $id]);
        $errFile = tmpfile();
        $proc = proc_open([PHP_BINARY, __DIR__ . '/support/run_formular_fax_to_mobile.php', $this->database->name(), 'production'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $errFile], $pipes);
        self::assertIsResource($proc, 'could not start the login');
        fclose($pipes[0]);
        $waiting = null;
        for ($deadline = microtime(true) + 20; $waiting === null && microtime(true) < $deadline; usleep(50000)) {
            $waiting = CharacterizationEnv::one($this->db, "SELECT pid FROM pg_stat_activity WHERE datname = current_database() AND wait_event_type = 'Lock' AND query LIKE 'UPDATE formularer%'");
        }
        CharacterizationEnv::rows($other, 'COMMIT');
        pg_close($other);
        $stdout = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($proc);
        rewind($errFile);
        $stderr = (string)stream_get_contents($errFile);
        fclose($errFile);
        self::assertNotNull($waiting, "the login's UPDATE never waited for the other session (stdout: $stdout stderr: $stderr)");
        return $this->runnerResult($stdout, $stderr);
    }

    /** @return array<string, mixed> the runner's JSON line */
    private function runnerResult(string $stdout, string $stderr): array
    {
        $lines = array_values(array_filter(explode("\n", trim($stdout))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        self::assertIsArray($json, "runner must emit JSON (stdout: $stdout stderr: $stderr)");
        self::assertArrayNotHasKey('error', $json, (string)ifset($json, 'error', ''));
        return $json;
    }

    /** One login: a child process with its own database session. @return list<string> the SQL it wrote */
    private function login(): array
    {
        $res = CharacterizationEnv::runChild(__DIR__ . '/support/run_formular_fax_to_mobile.php', [$this->database->name(), 'stub']);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        self::assertIsArray($json, "runner must emit JSON (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        self::assertArrayNotHasKey('error', $json, (string)ifset($json, 'error', ''));
        return $json['writes'];
    }
}
