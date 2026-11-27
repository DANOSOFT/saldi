<?php
// 20261127 ASR Created. The line check of a restored dump (includes/restoreDumpCheck.php) and how admin/restore.php uses it.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * admin/restore.php restores a dump through psql (PostgreSQL) or mysql. Each line is checked first
 * (restore_dump_check_line()); one refused line refuses the restore before anything is changed. A word counts wherever
 * on the line it stands, also at its very start. In a PostgreSQL dump every psql command is refused except pg_dump's own
 * \restrict and \unrestrict lines, while quoted strings, dollar-quoted bodies and COPY data are read as psql reads them.
 */
final class restoreDumpCheck extends TestCase
{
	protected function setUp(): void
	{
		require_once __DIR__ . '/../../includes/restoreDumpCheck.php';
	}

	/** @return string '' when every line and the end are accepted, else the first reason with its line number */
	private static function check(string $dump, bool $psql = true): string
	{
		$state = restore_dump_check_start($psql);
		foreach (preg_split('/(?<=\n)/', $dump, -1, PREG_SPLIT_NO_EMPTY) as $line) {
			$why = restore_dump_check_line($line, $state);
			if ($why !== '') {
				return $state['line'] . ': ' . $why;
			}
		}
		return restore_dump_check_end($state);
	}

	private static function pgDump(string $body): string
	{
		return "--\n-- PostgreSQL database dump\n--\n\n\\restrict k3Y9aZ\n\nSET standard_conforming_strings = on;\n" . $body . "\n\\unrestrict k3Y9aZ\n";
	}

	public function test_a_dump_as_pg_dump_writes_it_is_accepted(): void
	{
		$dump = self::pgDump(<<<'SQL'
-- Name: f(); Type: FUNCTION; Schema: public; Owner: user
CREATE FUNCTION public.f(t text) RETURNS text
    LANGUAGE plpgsql
    AS $_$ BEGIN RETURN regexp_replace(t, '\s+\\', ' '); END; $_$;
CREATE TABLE public.varer (id integer NOT NULL, beskrivelse text DEFAULT E'a\'b\\c'::text, "na\me" text);
COMMENT ON TABLE public.varer IS 'Varer med \ i teksten';
/* a block comment /* nested */ still a comment */
COPY public.varer (id, beskrivelse, "na\me") FROM stdin;
1	Kaffe\tfilter	\N
2	\\connect er tekst	\N
3	linje\nto	x\r\b\f\v\0122\x41
\.
SELECT pg_catalog.setval('public.varer_id_seq', 3, true);
SQL);
		self::assertSame('', self::check($dump));
	}

	public function test_a_real_pg_dump_of_a_ledger_is_accepted(): void
	{
		$db = getenv('SALDI_CHAR_TEMPLATE_DB');
		$pgDump = is_executable('/usr/bin/pg_dump') ? '/usr/bin/pg_dump' : '';
		if (!$db || $pgDump === '' || !getenv('SALDI_CHAR_PGHOST')) {
			self::markTestSkipped('needs pg_dump and the characterization database (SALDI_CHAR_TEMPLATE_DB, SALDI_CHAR_PGHOST)');
		}
		$cmd = escapeshellarg($pgDump) . ' -h ' . escapeshellarg((string) getenv('SALDI_CHAR_PGHOST')) . ' -U ' . escapeshellarg((string) getenv('SALDI_CHAR_PGUSER')) . ' ' . escapeshellarg($db);
		$proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, array('PGPASSWORD' => (string) getenv('SALDI_CHAR_PGPASS')) + getenv());
		$state = restore_dump_check_start(true);
		$refused = '';
		$lines = 0;
		while (($line = fgets($pipes[1])) !== false) {
			$lines++;
			$why = restore_dump_check_line($line, $state);
			if ($why !== '' && $refused === '') {
				$refused = $state['line'] . ': ' . $why . ': ' . substr($line, 0, 120);
			}
		}
		fclose($pipes[1]);
		$err = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		self::assertSame(0, proc_close($proc), (string) $err);
		self::assertGreaterThan(100, $lines);
		self::assertSame('', $refused);
		self::assertSame('', restore_dump_check_end($state));
	}

	/** @return array<string, array{0: string}> */
	public static function psqlCommands(): array
	{
		return array(
			'\\connect at the start of a line' => array("\\connect saldi_other\nDELETE FROM brugere;\n"),
			'\\c at the start of a line' => array("\\c saldi_other\n"),
			'after blanks' => array("   \\connect saldi_other\n"),
			'after a statement on the line' => array("SELECT 1; \\connect saldi_other\n"),
			'after a closed string' => array("SELECT 'a\\'; \\connect saldi_other\n"),
			'a shell command' => array("\\! id\n"),
			'output to a file' => array("\\o /tmp/x\n"),
			'include a file' => array("\\i /tmp/x.sql\n"),
			'gexec' => array("SELECT 'x' \\gexec\n"),
			'the separator command' => array("SELECT 1 \\\\ SELECT 2;\n"),
			'in a line comment' => array("-- see \\connect saldi_other\n"),
			'in a block comment' => array("/* \\connect saldi_other */\n"),
			'after an identifier with a dollar sign' => array("SELECT a\$b\$ \\connect saldi_other\n"),
			'a restrict line with more' => array("\\restrict k3Y9aZ \\connect saldi_other\n"),
			'COPY data after a COPY that fails' => array("COPY no_such_table FROM stdin;\n\\connect saldi_other\n\\.\n"),
			'a command inside COPY data' => array("COPY public.t (a) FROM stdin;\nx\\c y\n\\.\n"),
			'COPY data ends with more than \\.' => array("COPY public.t (a) FROM stdin;\n1\n\\. x\n"),
			'standard_conforming_strings off' => array("SET standard_conforming_strings = off;\nSELECT 'a\\'' \\connect saldi_other --';\n"),
			'standard_conforming_strings through set_config' => array("SELECT set_config('standard_conforming_strings', 'off', false);\n"),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('psqlCommands')]
	public function test_a_psql_command_is_refused(string $body): void
	{
		self::assertNotSame('', self::check(self::pgDump($body)));
	}

	public function test_quoted_text_that_looks_like_a_command_is_data(): void
	{
		self::assertSame('', self::check(self::pgDump("SELECT E'a\\' \\connect saldi_other';\nSELECT 'x \\connect y';\nSELECT \$q\$ \\connect\n\\! id \$q\$;\nSELECT \"col\\c\";\n")));
	}

	public function test_a_dump_that_ends_inside_a_string_or_copy_data_is_refused(): void
	{
		self::assertNotSame('', self::check("SELECT 'not closed\n"));
		self::assertNotSame('', self::check("COPY public.t (a) FROM stdin;\n1\n"));
		self::assertNotSame('', self::check("/* not closed\n"));
	}

	public function test_create_and_drop_database_are_refused_also_at_the_start_of_a_line(): void
	{
		foreach (array("DROP DATABASE saldi_other;\n", "drop database saldi_other;\n", "CREATE DATABASE saldi_other;\n", "SELECT 1; create database x;\n") as $line) {
			self::assertNotSame('', self::check(self::pgDump($line)), $line);
			self::assertNotSame('', self::check("-- MySQL dump\n" . $line, false), "MySQL: $line");
		}
	}

	public function test_a_mysql_dump_keeps_its_own_backslashes_and_refuses_connect(): void
	{
		self::assertSame('', self::check("-- MySQL dump 10.13\nINSERT INTO `varer` VALUES (1,'it\\'s','a\\\\b');\n", false));
		self::assertNotSame('', self::check("-- MySQL dump 10.13\n\\connect other\n", false), 'at the start of a line too');
	}

	public function test_the_restore_page_checks_every_line_and_follows_allowed_ledgers(): void
	{
		$page = (string) file_get_contents(__DIR__ . '/../../admin/restore.php');
		self::assertStringContainsString('$why = restore_dump_check_line($linje, $dumpCheck);', $page);
		self::assertStringContainsString('$why = restore_dump_check_end($dumpCheck);', $page);
		self::assertStringNotContainsString('if (strpos(strtolower($linje)', $page, 'no check misses a word at offset 0');
		self::assertStringContainsString('strpos($linje, "PostgreSQL database dump") !== false', $page);
		$sql = strpos($page, "if (\$extension === 'sql' && restore_sql_refused()) {");
		self::assertNotFalse($sql, 'an SQL file is refused while $allowedLedgers is set');
		self::assertLessThan(strpos($page, 'mkdir($workDir, 0700)'), $sql, 'before the upload is kept');
		self::assertStringContainsString("if (!\$res['info'] && restore_sql_refused()) {", $page, 'an archive without backup.info is read as an SQL file');
		self::assertStringContainsString("if (restore_sql_refused() && empty(\$p['info'])) {", $page, 'the confirmation checks it again');
		self::assertStringContainsString("&& !restore_sql_refused() && restore_ledger_empty();", $page, 'no move from MySQL');
		self::assertStringContainsString('return saldi_allowed_ledgers() !== null;', $page);
		$restore = strpos($page, 'function restore($filnavn');
		self::assertLessThan(strpos($page, 'db_modify("DROP DATABASE $db"', $restore), strpos($page, '$why = restore_dump_check_end($dumpCheck);', $restore), 'every line is checked before the ledger is replaced');
	}
}
