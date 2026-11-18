<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

// 20261118 CL/MMR SST-898 (review 4) The SST-898 login step on a real PostgreSQL database: an account without the formularer or
//                 tekster table is skipped without an error and migrated at a later login, each table on its own; a populated
//                 account gets "Mobilnr." on its forms and loses only the old default texts; a second login writes nothing.
//                 Each login is a child process (support/run_formular_fax_to_mobile.php) that runs the lines from includes/betweenUpdates.php.
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

    /** One login: a child process with its own database session. @return list<string> the SQL it wrote */
    private function login(): array
    {
        $res = CharacterizationEnv::runChild(__DIR__ . '/support/run_formular_fax_to_mobile.php', [$this->database->name()]);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        self::assertIsArray($json, "runner must emit JSON (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        self::assertArrayNotHasKey('error', $json, (string)($json['error'] ?? ''));
        return $json['writes'];
    }
}
