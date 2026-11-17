<?php
// 20261110 CDX/NTR Exercise employee login saves against temporary tables without changing installed accounts.
// 20261111 CDX/NTR Use the owned SQL fixture instead of connecting to the master.
// 20261111 CDX/NTR Attach the shared SQL database identity to this class's test results.
// 20261113 CDX/NTR Cover permission refusals for password changes, renames and employee links after merging roles.

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
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $this->connection = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        $GLOBALS['employeeLoginTestConnection'] = $this->connection;
        $GLOBALS['employeeLoginMayManage'] = true;
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
            function user_may_manage(array $row): bool {
                return $GLOBALS['employeeLoginMayManage'];
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
            'text_5548_2' => 'Permission denied',
        ];
    }

    protected function tearDown(): void
    {
        if ($this->connection) {
            pg_close($this->connection);
        }
        unset($GLOBALS['employeeLoginTestConnection'], $GLOBALS['employeeLoginMayManage']);
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

    public function testARestrictedEditorCannotChangeAnExistingPasswordOrUsername(): void
    {
        $original = $this->seedLogin();
        $GLOBALS['employeeLoginMayManage'] = false;
        $password = TestCredentials::password('employee_login_new');
        foreach ([['brugere_navn' => 'renamed'], ['kode1' => $password, 'kode2' => $password]] as $input) {
            self::assertSame('Permission denied', saveEmployeeLogin(581, 3, $input, 2));
            self::assertSame([$original], $this->logins());
        }
        self::assertNull(saveEmployeeLogin(581, 3, ['brugere_navn' => 'employee'], 2));
        self::assertSame([$original], $this->logins());
    }

    public function testARestrictedEditorCannotLinkAnUnmanageableLogin(): void
    {
        $original = $this->seedLogin(null);
        $GLOBALS['employeeLoginMayManage'] = false;
        self::assertSame('Permission denied', saveEmployeeLogin(581, 3, ['brugere_navn' => 'employee'], 2));
        self::assertSame([$original], $this->logins());
    }
}
