<?php
// 20260921 CDX/LH Exercise fixture DSN parsing and authenticated PDO connections without static secrets.
require_once __DIR__ . '/RegressionPostgres.php';
use PHPUnit\Framework\TestCase;

final class RegressionPostgresTest extends TestCase
{
    public function testCredentialsSurviveKeywordAndUriParsing(): void
    {
        $password = bin2hex(random_bytes(16)) . ";'\\ " . "\n";
        $quote = static fn(string $value): string => "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        $expected = ['host' => '127.0.0.1', 'dbname' => 'regression_fixture', 'user' => 'regression_owner', 'password' => $password];
        $dsn = "host=127.0.0.1 dbname = 'regression_fixture' user=regression_owner password=" . $quote($password);
        self::assertSame($expected, regressionPostgresParameters($dsn));
        $uri = 'postgresql://regression_owner:' . rawurlencode($password) . '@127.0.0.1/regression_fixture';
        self::assertEquals($expected, regressionPostgresParameters($uri));
        self::assertSame(['options' => '-c application_name=fixture'], regressionPostgresParameters('options=-c\\ application_name=fixture'));
    }

    public function testMalformedFixtureDoesNotExposeCredentials(): void
    {
        $private = bin2hex(random_bytes(16));
        foreach (["password='" . $private, 'password=' . $private . '\\', "host='localhost'garbage password=" . $private] as $dsn) {
            try {
                regressionPostgresParameters($dsn);
                self::fail('Malformed fixture was accepted');
            } catch (InvalidArgumentException $error) {
                self::assertStringNotContainsString($private, $error->getMessage());
            }
        }
    }

    public function testWrappersRejectSilentOrPartiallySkippedChildren(): void
    {
        $fixture = sys_get_temp_dir() . '/saldi-wrapper-probe-' . bin2hex(random_bytes(8));
        mkdir($fixture . '/tests/characterization/release', 0700, true);
        try {
            foreach (['FinanceRegressionTest' => 'runFinanceScript', 'ReleaseStartupRegressionTest' => 'runRegressionScript'] as $class => $method) {
                $probe = 'Probe' . $class;
                $source = file_get_contents(__DIR__ . '/../release/' . $class . '.test.php');
                $source = str_replace('final class ' . $class, 'final class ' . $probe, $source);
                $file = $fixture . '/tests/characterization/release/' . $probe . '.php';
                file_put_contents($file, $source);
                require $file;
                $test = new $probe('probe');
                $run = new ReflectionMethod($test, $method);
                foreach (["PASS: first assertion\nSKIP: remaining database checks\n", ''] as $output) {
                    file_put_contents($fixture . '/tests/child.php', '<?php echo ' . var_export($output, true) . ';');
                    $rejected = false;
                    try {
                        $run->invoke($test, 'child.php');
                    } catch (PHPUnit\Framework\AssertionFailedError $error) {
                        $rejected = true;
                    }
                    self::assertTrue($rejected, $class . ' accepted missing or skipped checks');
                }
                file_put_contents($fixture . '/tests/child.php', "<?php echo 'PASS: complete regression';");
                $run->invoke($test, 'child.php');
                unlink($file);
            }
        } finally {
            foreach (glob($fixture . '/tests/characterization/release/*') as $file) unlink($file);
            if (is_file($fixture . '/tests/child.php')) unlink($fixture . '/tests/child.php');
            rmdir($fixture . '/tests/characterization/release');
            rmdir($fixture . '/tests/characterization');
            rmdir($fixture . '/tests');
            rmdir($fixture);
        }
    }

    public function testPdoUsesTheConfiguredDatabaseAndAuthenticatedIdentity(): void
    {
        $dsn = getenv('SALDI_CHAR_PG_DSN') ?: getenv('SALDI_TEST_DSN');
        if (!$dsn || !extension_loaded('pdo_pgsql') || !extension_loaded('pgsql')) {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN and enable pgsql/pdo_pgsql for the isolated fixture.');
        }
        $native = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        self::assertNotFalse($native);
        $expected = pg_fetch_assoc(pg_query($native, 'SELECT current_database() AS database, current_user AS actor'));
        $pdo = regressionPostgresPdo($dsn);
        self::assertSame($expected, $pdo->query('SELECT current_database() AS database, current_user AS actor')->fetch(PDO::FETCH_ASSOC));
        $pdo->exec('CREATE TEMP TABLE regression_pdo_fixture(value integer)');
        $pdo->exec('INSERT INTO regression_pdo_fixture VALUES(42)');
        self::assertSame(42, (int)$pdo->query('SELECT value FROM regression_pdo_fixture')->fetchColumn());
        pg_close($native);
    }
}
