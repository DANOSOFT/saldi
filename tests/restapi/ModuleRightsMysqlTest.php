<?php
// tests/restapi/ModuleRightsMysqlTest.php
//
// BaseEndpoint::checkModuleRight() on MySQL/MariaDB. The user's role and the company's enforcement mode come from the
// account database, found through tbl_exists(), which on MySQL looks in the schema global $db names. The REST API
// connected to the account database without selecting it and never set global $db, so there tbl_exists() found no role
// tables and no settings table: a read-only user wrote under 'deny', and a role was read as the rights string it is
// rendered into (review of the module-rights fix). ModuleRightsTest covers the same over HTTP on PostgreSQL.
//
// CI has no MySQL and no mysqli, so support/mysql_account_database_stand_in.php answers as a MySQL account database
// would; tbl_exists() and the permission functions are the real ones. Each case logs in with a signed token through
// BaseEndpoint::checkAuthorization(), on $db_type 'mysqli' (what index/install.php writes) and 'mysql' (older
// connect.php files).
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ModuleRightsMysqlTest extends TestCase
{
    /** Kontoplan read-only ('2' at position 0), as a role with Kun visning on Kontoplan renders it. */
    private const KONTOPLAN_READ = '2000000000000000';

    /** The rights string perm_sync_user() writes for a role with Kassesystem and no Debitorordre (position 5 read). */
    private const TILL_ROLE_STRING = '0000020000000000';

    /** @var string|null The account database of this case, whose log write_log() keeps under temp/. */
    private $account = null;

    /**
     * @return array<string, array{string}>
     */
    public static function mysqlTypes(): array
    {
        return [
            "'mysqli' (what index/install.php writes)" => ['mysqli'],
            "'mysql' (older connect.php files)" => ['mysql'],
        ];
    }

    protected function tearDown(): void
    {
        if ($this->account === null) {
            return;
        }
        $dir = dirname(__DIR__, 2) . '/temp/' . $this->account;
        foreach (is_dir($dir) ? scandir($dir) : [] as $name) {
            if (is_file("$dir/$name")) {
                unlink("$dir/$name");
            }
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    /** Once enforcement is 'deny', read-only opens Kontoplan's reads but not its writes, as perm_enforce_request() does. */
    #[DataProvider('mysqlTypes')]
    public function test_a_read_only_user_is_refused_writes_once_enforcement_is_deny(string $dbType): void
    {
        $endpoint = $this->login($dbType, 'system.kontoplan', self::KONTOPLAN_READ, 'deny', []);

        self::assertTrue($endpoint->allows('GET'), 'GET accounts/ for Kontoplan read-only');
        self::assertFalse($endpoint->allows('POST'), 'POST accounts/ for Kontoplan read-only under deny');
        self::assertFalse($endpoint->allows('PUT'), 'PUT accounts/ for Kontoplan read-only under deny');
    }

    /** Until enforcement is 'deny' the web lets a read-only user save and only logs it; the API does the same. */
    #[DataProvider('mysqlTypes')]
    public function test_a_read_only_user_may_write_while_enforcement_only_logs(string $dbType): void
    {
        $endpoint = $this->login($dbType, 'system.kontoplan', self::KONTOPLAN_READ, 'log', []);

        self::assertTrue($endpoint->allows('POST'), 'POST accounts/ for Kontoplan read-only under log');
    }

    /** A role with Kassesystem and no Debitorordre is refused Debitorordre under 'deny'; the same string without a role reads. */
    #[DataProvider('mysqlTypes')]
    public function test_an_assigned_role_decides_the_level_not_the_rights_string_it_renders(string $dbType): void
    {
        $endpoint = $this->login($dbType, 'debitor.ordre', self::TILL_ROLE_STRING, 'deny', ['pos.kasse' => 'write']);

        self::assertFalse($endpoint->allows('GET'), 'GET debitor/orders/ for the till role under deny');
        self::assertFalse($endpoint->allows('POST'), 'POST debitor/orders/ for the till role under deny');

        $GLOBALS['mysqlStandIn']['roleLevels'] = [];
        self::assertTrue($endpoint->allows('GET'), 'GET debitor/orders/ for the same string without a role');
    }

    /** The checks run on the account database the token names, and tbl_exists() looks in its schema. */
    #[DataProvider('mysqlTypes')]
    public function test_the_checks_ask_the_account_database(string $dbType): void
    {
        $endpoint = $this->login($dbType, 'system.kontoplan', self::KONTOPLAN_READ, 'deny', []);
        $endpoint->allows('POST');

        self::assertSame($this->account, $GLOBALS['db'] ?? null, 'global $db is not the account database');
        $schemaLookups = [];
        foreach ($GLOBALS['mysqlStandInQueries'] as $query) {
            if (preg_match("/information_schema\.tables WHERE table_schema = '([^']*)' AND table_name = '([^']*)'/", $query['sql'], $m)) {
                $schemaLookups[$m[2]] = $m[1];
            } elseif (strpos($query['sql'], 'from regnskab') === false) {
                self::assertSame($this->account, $query['database'], 'run without the account database selected: ' . $query['sql']);
            }
        }
        self::assertSame(
            ['roles' => $this->account, 'role_permissions' => $this->account, 'settings' => $this->account],
            $schemaLookups,
            'tbl_exists() did not look for the role tables and the settings table in the account database'
        );
    }

    /**
     * Loads BaseEndpoint on the stand-in, signs an access token for user 7 of account 5, and logs in with it on an
     * endpoint that names $permissionKey.
     *
     * @param array<string, string> $roleLevels Levels of the role assigned to the user, [] for none.
     * @return object The endpoint; allows($method) is checkModuleRight() for a request with that method.
     */
    private function login(string $dbType, string $permissionKey, string $rettigheder, ?string $enforce, array $roleLevels): object
    {
        $this->account = 'apimysql_' . bin2hex(random_bytes(4));
        $GLOBALS['mysqlStandInJwtSecret'] = random_bytes(32);
        // restapi/core/cors.php reads the method when it is loaded.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        require_once __DIR__ . '/support/mysql_account_database_stand_in.php';
        require_once dirname(__DIR__, 2) . '/restapi/core/BaseEndpoint.php';

        // includes/connect.php, where the stack has one, sets the engine and the server; these are MySQL's.
        $GLOBALS['db_type'] = $dbType;
        $GLOBALS['sqhost'] = 'mysql-stand-in';
        $GLOBALS['squser'] = 'saldi';
        $GLOBALS['sqpass'] = '';
        $GLOBALS['sqdb'] = 'saldi_master';
        unset($GLOBALS['db']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/restapi/endpoints/v1/accounts/';
        mysqlStandInAccount($this->account, $rettigheder, $enforce, $roleLevels);
        $GLOBALS['mysqlStandInHeaders'] = [
            'Authorization' => 'Bearer ' . JWT::encode(['user_id' => 7, 'username' => 'e2etest3', 'tenant_id' => 5, 'type' => 'access']),
        ];

        $endpoint = new class ($permissionKey) extends BaseEndpoint {
            public function __construct(string $permissionKey)
            {
                $this->permissionKey = $permissionKey;
            }

            public function authorize(): bool
            {
                return $this->checkAuthorization();
            }

            public function allows(string $method): bool
            {
                $_SERVER['REQUEST_METHOD'] = $method;
                return $this->checkModuleRight();
            }
        };
        self::assertTrue($endpoint->authorize(), 'the token was not accepted');
        return $endpoint;
    }
}
