<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../includes/std_func.php';

// 20261118 CL/MMR SST-898 (review 10) The SST-898 login step on MySQL/MariaDB with the real includes/db_query.php wrappers, for utf8mb4 and
//                 latin1 columns and connections and both $db_type names ('mysqli' and the legacy 'mysql'): accented and Spanish forms are stored
//                 exactly, the old default texts go and the customer's ones stay, the compare is exact, and a second run changes nothing.
//                 Needs a MySQL/MariaDB server: SALDI_CHAR_MYSQL_SOCKET (and SALDI_CHAR_MYSQL_USER / SALDI_CHAR_MYSQL_PASS, default root without a
//                 password); skipped without it. Each case is a child process (support/run_formular_fax_to_mobile_mysql.php).
// 20261118 CL/MMR SST-898 (review 11) The runner refuses an existing database and leaves it intact, and removes its own database after a failed setup.
// 20261118 CL/MMR SST-898 (review 12) A valid name with a trailing newline is refused by the runner's own check.
// 20261118 CL/MMR SST-898 (CI) admin() asks socket() first, so the test skips instead of failing on a PHP without mysqli; its four tests
//                 are on the tools/ci/check-skips.php allowlist because the CI container has no MySQL/MariaDB server.
final class FormularFaxToMobileMysqlTest extends TestCase
{
    /** @return array<string, array{string, string, string}> column charset, connection charset, db_type */
    public static function setups(): array
    {
        $out = [];
        foreach (['utf8mb4', 'latin1'] as $column) {
            foreach (['utf8mb4', 'latin1'] as $connection) {
                foreach (['mysqli', 'mysql'] as $dbType) {
                    $out["$column column, $connection connection, $dbType"] = [$column, $connection, $dbType];
                }
            }
        }
        return $out;
    }

    #[DataProvider('setups')]
    public function testTheLoginStep(string $columnCharset, string $connectionCharset, string $dbType): void
    {
        $database = 'sst898_' . bin2hex(random_bytes(4));
        $json = $this->runStep($database, $columnCharset, $connectionCharset, $dbType);
        self::assertArrayNotHasKey('error', $json, (string)ifset($json, 'error', ''));
        self::assertFalse($this->databaseExists($database), 'the runner removes its database');

        self::assertSame([5, 3], $json['first'], 'five forms; the old texts of 378/1, 50/2 and 5230/3');
        self::assertSame([0, 0], $json['second']);
        self::assertSame([1, 0, 0], $json['exact'], 'exact text matches; other case and a trailing space do not');
        self::assertSame([
            'Mobilnr.: $egen_mobile; Ærø',
            'Mobilnr.: $egen_mobile',
            'Móvil: $egen_mobile; Señor',
            'Mobile: $egen_mobile; Customers like this',
            'Mobilnr.: $egen_mobile * Åbent',
        ], $json['forms']);
        // only exact old defaults go: "telefax" (other case) and "Mobilnummer " (trailing space) stay
        self::assertSame([['378', '1', 'telefax'], ['378', '1', 'Mobiltlf.'], ['50', '2', 'Mobile'], ['5230', '1', 'Mobilnummer ']], $json['texts']);
    }

    public function testAnExistingDatabaseIsLeftIntact(): void
    {
        $database = 'sst898_' . bin2hex(random_bytes(4));
        $admin = $this->admin();
        mysqli_query($admin, "CREATE DATABASE `$database`");
        try {
            mysqli_query($admin, "CREATE TABLE `$database`.keep_me (v INT)");
            mysqli_query($admin, "INSERT INTO `$database`.keep_me VALUES (42)");
            $json = $this->runStep($database, 'utf8mb4', 'utf8mb4', 'mysqli');
            self::assertStringContainsString('exists', (string)ifset($json, 'error', ''));
            self::assertSame([['42']], mysqli_fetch_all(mysqli_query($admin, "SELECT v FROM `$database`.keep_me"), MYSQLI_NUM));
        } finally {
            mysqli_query($admin, "DROP DATABASE IF EXISTS `$database`");
            mysqli_close($admin);
        }
    }

    public function testAFailedSetupRemovesItsDatabase(): void
    {
        $database = 'sst898_' . bin2hex(random_bytes(4));
        $json = $this->runStep($database, 'nosuchcharset', 'utf8mb4', 'mysqli');
        self::assertStringContainsString('nosuchcharset', (string)ifset($json, 'error', ''));
        self::assertFalse($this->databaseExists($database));
    }

    public function testANameOutsideTheTestFormatIsRefused(): void
    {
        foreach (['saldi_1', "sst898_12345678\n"] as $name) {
            $json = $this->runStep($name, 'utf8mb4', 'utf8mb4', 'mysqli');
            self::assertStringStartsWith('InvalidArgumentException: database name must be sst898_<8 hex digits>', (string)ifset($json, 'error', ''), json_encode($name));
        }
    }

    /** @return array<string, mixed> the runner's JSON line */
    private function runStep(string $database, string $columnCharset, string $connectionCharset, string $dbType): array
    {
        $res = CharacterizationEnv::runChild(__DIR__ . '/support/run_formular_fax_to_mobile_mysql.php', [$this->socket(), $database, $columnCharset, $connectionCharset, $dbType]);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        self::assertIsArray($json, "runner must emit JSON (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        return $json;
    }

    private function socket(): string
    {
        $socket = (string)getenv('SALDI_CHAR_MYSQL_SOCKET');
        if ($socket === '' || !extension_loaded('mysqli')) {
            self::markTestSkipped('SALDI_CHAR_MYSQL_SOCKET and the mysqli extension are required');
        }
        return $socket;
    }

    /** @return \mysqli */
    private function admin()
    {
        // socket() first: it skips the test before any mysqli function is called on a PHP without mysqli (the CI image).
        $socket = $this->socket();
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        return mysqli_connect('localhost', getenv('SALDI_CHAR_MYSQL_USER') ?: 'root', getenv('SALDI_CHAR_MYSQL_PASS') ?: '', '', 0, $socket);
    }

    private function databaseExists(string $database): bool
    {
        $admin = $this->admin();
        $found = mysqli_fetch_all(mysqli_execute_query($admin, 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$database]));
        mysqli_close($admin);
        return $found !== [];
    }
}
