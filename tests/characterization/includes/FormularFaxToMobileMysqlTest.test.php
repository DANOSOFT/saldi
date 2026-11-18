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
        $socket = (string)getenv('SALDI_CHAR_MYSQL_SOCKET');
        if ($socket === '' || !extension_loaded('mysqli')) {
            self::markTestSkipped('SALDI_CHAR_MYSQL_SOCKET and the mysqli extension are required');
        }
        $database = 'sst898_' . bin2hex(random_bytes(4));
        $res = CharacterizationEnv::runChild(__DIR__ . '/support/run_formular_fax_to_mobile_mysql.php', [$socket, $database, $columnCharset, $connectionCharset, $dbType]);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        self::assertIsArray($json, "runner must emit JSON (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        self::assertArrayNotHasKey('error', $json, (string)ifset($json, 'error', ''));

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
}
