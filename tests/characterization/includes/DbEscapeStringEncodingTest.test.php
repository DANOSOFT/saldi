<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * db_escape_string() (includes/db_query.php) and the connection's encoding on PostgreSQL.
 *
 * For a UTF8 connection, text that is not UTF-8 is read as Windows-1252 and sent as UTF-8. A LATIN9 connection
 * ($db_encode not UTF8; db_connect() sets the client encoding from it) expects single-byte text, and the conversion sent
 * it UTF-8 too: the database stored æ, ø and å as two characters each and € as ¤. It now gets its text as it is.
 *
 * Runs on a real Postgres with search_path set to pg_temp: the table is a TEMP table of this session, and nothing in
 * the shared SQL fixture database (TestDatabase::sqlFixture()) is read or changed.
 *
 * History:
 * 20261124 CL/ASR Created.
 */
#[RunTestsInSeparateProcesses]
final class DbEscapeStringEncodingTest extends TestCase
{
    /** @var \PgSql\Connection|null */
    private static $conn = null;

    private function connect(string $encode): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to escape on a PostgreSQL connection.');
        }
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db_encode'] = $encode;
        require_once dirname(__DIR__, 3) . '/includes/db_query.php';
        self::$conn = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        $GLOBALS['connection'] = self::$conn;
        // As db_connect() sets it for $db_encode.
        pg_set_client_encoding(self::$conn, $encode === 'UTF8' ? 'UTF8' : 'LATIN9');
        pg_query(self::$conn, "SET search_path TO pg_temp");
        pg_query(self::$conn, "CREATE TEMP TABLE tekst (t text)");
    }

    /** What the database holds after an insert of db_escape_string($text), read as UTF-8. */
    private static function stored(string $text): string
    {
        pg_query(self::$conn, "INSERT INTO tekst VALUES ('" . db_escape_string($text) . "')");
        pg_set_client_encoding(self::$conn, 'UTF8');
        return (string) pg_fetch_result(pg_query(self::$conn, "SELECT t FROM tekst"), 0, 0);
    }

    public function testALatin9ConnectionGetsItsSingleByteTextAsItIs(): void
    {
        $this->connect('LATIN9');
        $latin9 = "Bl\xe5b\xe6r \xf8l \xa4 O'Brien";   // ISO-8859-15: å, æ, ø and € (0xA4)
        self::assertSame("Bl\xe5b\xe6r \xf8l \xa4 O''Brien", db_escape_string($latin9));
        self::assertSame("Blåbær øl € O'Brien", self::stored($latin9));
    }

    public function testAUtf8ConnectionStillReadsTextThatIsNotUtf8AsWindows1252(): void
    {
        $this->connect('UTF8');
        self::assertSame("Blåbær €", self::stored("Bl\xe5b\xe6r \x80"));   // Windows-1252: € is 0x80
        self::assertSame("Blåbær", db_escape_string("Blåbær"));
    }
}
