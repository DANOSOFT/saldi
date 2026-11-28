<?php
// tests/characterization/includes/DbEscapeStringMysqlTest.test.php
//
// db_escape_string() on MySQL and MariaDB. db_connect() opens a mysqli connection for $db_type 'mysql' as well as for
// 'mysqli', but db_escape_string() escaped for 'mysql' with mysql_real_escape_string(), which PHP 7 removed. Every
// escaped value was a fatal error there, also a customer's link on mysale/mybooking.php once that page escaped it
// (testlab round 1, DEVY-14). Both now escape through mysqli_real_escape_string() on that connection.
//
// CI has no mysqli, so there support/mysqli_escape_stand_in.php stands in for mysqli_real_escape_string() and records
// the connection it gets. Where mysqli is loaded the test needs a server in SALDI_CHAR_MYSQL_HOST, SALDI_CHAR_MYSQL_USER
// and SALDI_CHAR_MYSQL_PASS, and reads the escaped text back from it as a string literal.
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DbEscapeStringMysqlTest extends TestCase
{
    /**
     * Both MySQL settings, each with a customer's link and the testlab's quote.
     *
     * @return array<string, array{string, string, string}> $db_type, the text, and what MySQL's escaping makes of it
     */
    public static function mysqlTexts(): array
    {
        $cases = [];
        foreach (['mysql' => 'older connect.php files', 'mysqli' => 'what index/install.php writes'] as $dbType => $who) {
            $cases["'$dbType' ($who), a customer's link"] = [$dbType, '5f0c9e2a7b31d4e8', '5f0c9e2a7b31d4e8'];
            $cases["'$dbType' ($who), a link with a quote"] = [$dbType, "x' OR '1'='1", "x\\' OR \\'1\\'=\\'1"];
        }
        return $cases;
    }

    #[DataProvider('mysqlTexts')]
    public function test_escapes_on_the_mysqli_connection_db_connect_opened(string $dbType, string $text, string $expected): void
    {
        $connection = $this->connect($dbType);

        $escaped = db_escape_string($text);

        self::assertSame($expected, $escaped);
        if (extension_loaded('mysqli')) {
            $row = db_fetch_array(mysqli_query($connection, "SELECT '$escaped' AS link"));
            self::assertSame($text, $row['link'], 'in a string literal the escaped text is not the text it was given');
        } else {
            self::assertSame([$connection], $GLOBALS['mysqliEscapeCalls'], 'not escaped on the connection db_connect() opened');
        }
    }

    /**
     * Sets $db_type and opens the connection as includes/connect.php does for it.
     *
     * @return mixed the connection db_escape_string() should escape on
     */
    private function connect(string $dbType)
    {
        $GLOBALS['db_type'] = $dbType;
        $GLOBALS['db_encode'] = 'UTF8';
        require_once dirname(__DIR__, 3) . '/includes/db_query.php';
        if (!extension_loaded('mysqli')) {
            require_once __DIR__ . '/support/mysqli_escape_stand_in.php';
            $GLOBALS['mysqliEscapeCalls'] = [];
            // db_connect() would call mysqli_connect(), which needs mysqli too.
            return $GLOBALS['connection'] = new stdClass();
        }
        $host = getenv('SALDI_CHAR_MYSQL_HOST');
        if (!$host) {
            self::markTestSkipped('mysqli is loaded, so this needs a MySQL or MariaDB server in SALDI_CHAR_MYSQL_HOST, _USER and _PASS');
        }
        return db_connect($host, (string) getenv('SALDI_CHAR_MYSQL_USER'), (string) getenv('SALDI_CHAR_MYSQL_PASS'));
    }
}
