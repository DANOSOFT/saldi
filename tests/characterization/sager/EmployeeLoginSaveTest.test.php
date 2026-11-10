<?php
// 20261110 CDX/NTR Exercise employee login saves against temporary tables without changing installed accounts.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EmployeeLoginSaveTest extends TestCase
{
    private $connection;

    protected function setUp(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('pgsql is required for the temporary employee login tables.');
        }
        $settings = [];
        $config = dirname(__DIR__, 3) . '/includes/connect.php';
        $source = is_file($config) ? file_get_contents($config) : '';
        foreach (['sqhost' => 'SALDI_CHAR_PGHOST', 'squser' => 'SALDI_CHAR_PGUSER', 'sqpass' => 'SALDI_CHAR_PGPASS', 'sqdb' => 'SALDI_CHAR_MASTER_DB'] as $key => $environment) {
            preg_match('/\$' . $key . '\s*=\s*([\'"])(.*?)\1\s*;/', $source, $match);
            $settings[$key] = getenv($environment) ?: ($match[2] ?? '');
        }
        if ($settings['sqhost'] === '' || $settings['squser'] === '' || $settings['sqdb'] === '') {
            self::markTestSkipped('Set SALDI_CHAR_PGHOST/PGUSER/MASTER_DB or provide local includes/connect.php.');
        }
        $dsn = 'connect_timeout=3 ';
        foreach (['host' => 'sqhost', 'user' => 'squser', 'password' => 'sqpass', 'dbname' => 'sqdb'] as $parameter => $setting) {
            $dsn .= $parameter . "='" . str_replace(['\\', "'"], ['\\\\', "\\'"], $settings[$setting]) . "' ";
        }
        $this->connection = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        if (!$this->connection) {
            self::markTestSkipped('The configured PostgreSQL server is unavailable.');
        }
        $GLOBALS['employeeLoginTestConnection'] = $this->connection;
        pg_query($this->connection, 'SET search_path TO pg_temp');
        pg_query($this->connection, 'CREATE TEMP TABLE brugere (id SERIAL PRIMARY KEY, brugernavn TEXT, kode TEXT, ansat_id INTEGER, regnskabsaar INTEGER)');
        pg_query($this->connection, 'CREATE TEMP TABLE grupper (kodenr INTEGER, beskrivelse TEXT, art TEXT, box1 TEXT, box2 TEXT, box5 TEXT, box10 TEXT)');
        pg_query($this->connection, "INSERT INTO grupper VALUES (3, 'Current year', 'RA', '01', '2026', 'on', '')");

        if (!function_exists('db_select')) {
            function db_select($sql, $location = '') {
                return pg_query($GLOBALS['employeeLoginTestConnection'], $sql);
            }
            function db_fetch_array($result) {
                return pg_fetch_assoc($result);
            }
            function db_modify($sql, $location = '') {
                return pg_query($GLOBALS['employeeLoginTestConnection'], $sql);
            }
            function db_escape_string($value) {
                return pg_escape_string($GLOBALS['employeeLoginTestConnection'], (string) $value);
            }
        }
        require_once dirname(__DIR__, 3) . '/includes/std_func.php';
        require_once dirname(__DIR__, 3) . '/sager/sagerIncludes/saveEmployeeLogin.php';
        require_once dirname(__DIR__, 2) . '/TestCredentials.php';
        $_SESSION = [
            'text_3077_2' => 'Password required',
            'text_3078_2' => 'Passwords do not match',
            'text_1928_2' => 'Username already exists',
            'text_5149_2' => 'Username is too long',
        ];
    }

    protected function tearDown(): void
    {
        if ($this->connection) {
            pg_close($this->connection);
        }
        unset($GLOBALS['employeeLoginTestConnection']);
    }

    private function seedLogin(?int $employeeId = 581, string $username = 'employee', bool $hasPassword = true): array
    {
        $hash = $hasPassword ? saldikrypt(1, TestCredentials::password('employee_login_original')) : '';
        $result = pg_query_params($this->connection, 'INSERT INTO brugere (brugernavn, kode, ansat_id, regnskabsaar) VALUES ($1, $2, $3, 3) RETURNING *', [$username, $hash, $employeeId]);
        return pg_fetch_assoc($result);
    }

    private function logins(): array
    {
        return pg_fetch_all(pg_query($this->connection, 'SELECT * FROM brugere ORDER BY id'));
    }

    public function testBlankPasswordFieldsPreserveAnExistingPasswordWithoutAnAlert(): void
    {
        $original = $this->seedLogin();
        self::assertNull(saveEmployeeLogin(581, 3, ['brugere_navn' => 'employee', 'kode1' => '', 'kode2' => ''], 2));
        self::assertSame([$original], $this->logins());
    }

    public function testMissingPasswordFieldsAlsoPreserveTheStoredPassword(): void
    {
        $original = $this->seedLogin();
        self::assertNull(saveEmployeeLogin(581, 3, ['brugere_navn' => 'employee', 'brugere_id' => '999'], 2));
        self::assertSame([$original], $this->logins());
    }

    public function testANewLoginRequiresAPasswordAndIsNotCreatedAfterTheAlert(): void
    {
        self::assertSame('Password required!', saveEmployeeLogin(581, 3, ['brugere_navn' => 'employee'], 2));
        self::assertSame([], $this->logins());
    }

    public function testAnExistingLoginWithoutAPasswordStillRequiresOne(): void
    {
        $original = $this->seedLogin(581, 'employee', false);
        self::assertSame('Password required!', saveEmployeeLogin(581, 3, ['brugere_navn' => 'employee'], 2));
        self::assertSame([$original], $this->logins());
    }

    public function testAnIncompletePasswordChangeWarnsAndPreservesTheOldPassword(): void
    {
        $original = $this->seedLogin();
        $password = TestCredentials::password('employee_login_new');
        foreach ([['kode1' => $password], ['kode2' => $password]] as $fields) {
            self::assertSame('Password required!', saveEmployeeLogin(581, 3, ['brugere_navn' => 'employee'] + $fields, 2));
            self::assertSame([$original], $this->logins());
        }
    }

    public function testMismatchedPasswordsWarnWithoutChangingTheLogin(): void
    {
        $original = $this->seedLogin();
        self::assertSame('Passwords do not match!', saveEmployeeLogin(581, 3, [
            'brugere_navn' => 'employee',
            'kode1' => TestCredentials::password('employee_login_new'),
            'kode2' => TestCredentials::password('employee_login_other'),
        ], 2));
        self::assertSame([$original], $this->logins());
    }

    public function testMatchingPasswordsReplaceOnlyTheExistingPassword(): void
    {
        $original = $this->seedLogin();
        $password = TestCredentials::password('employee_login_new');
        self::assertNull(saveEmployeeLogin(581, 3, ['brugere_navn' => 'employee', 'kode1' => $password, 'kode2' => $password], 2));
        $original['kode'] = md5($password);
        self::assertSame([$original], $this->logins());
    }

    public function testANewLoginGetsItsPasswordEmployeeAndFallbackFiscalYear(): void
    {
        $password = TestCredentials::password('employee_login_new');
        self::assertNull(saveEmployeeLogin(581, 0, ['brugere_navn' => "O'Neil", 'kode1' => $password, 'kode2' => $password], 2));
        $login = $this->logins()[0];
        self::assertSame("O'Neil", $login['brugernavn']);
        self::assertSame(md5($password), $login['kode']);
        self::assertSame('581', $login['ansat_id']);
        self::assertSame('3', $login['regnskabsaar']);
    }

    public function testRenamingAnExistingLoginDoesNotRequireANewPassword(): void
    {
        $original = $this->seedLogin();
        self::assertNull(saveEmployeeLogin(581, 3, ['brugere_navn' => 'renamed'], 2));
        $original['brugernavn'] = 'renamed';
        self::assertSame([$original], $this->logins());
    }

    public function testAnotherEmployeesUsernameCannotBeReassigned(): void
    {
        $original = $this->seedLogin(999);
        self::assertSame('Username already exists: employee', saveEmployeeLogin(581, 3, ['brugere_navn' => 'employee'], 2));
        self::assertSame([$original], $this->logins());
    }

    public function testAnUnlinkedLoginCanKeepItsPasswordWhenLinkedToTheEmployee(): void
    {
        $original = $this->seedLogin(null);
        self::assertNull(saveEmployeeLogin(581, 3, ['brugere_navn' => 'employee'], 2));
        $original['ansat_id'] = '581';
        self::assertSame([$original], $this->logins());
    }
}
