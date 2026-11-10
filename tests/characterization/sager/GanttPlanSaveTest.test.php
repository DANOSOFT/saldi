<?php
// 20261106 CDX/NTR Exercise all Gantt AJAX endpoints for authorization, injection and valid date saves.
// 20261110 CDX/NTR Cover interval ordering, ISO storage and rollback for failed combined saves.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GanttPlanSaveTest extends TestCase
{
    private string $fixture;

    /** Build an isolated copy of the endpoints with controllable database failures. */
    protected function setUp(): void
    {
        $this->fixture = sys_get_temp_dir() . '/saldi-gantt-' . bin2hex(random_bytes(8));
        mkdir($this->fixture . '/sager/sagerIncludes', 0700, true);
        mkdir($this->fixture . '/includes', 0700);
        $root = dirname(__DIR__, 3);
        foreach (self::endpoints() as $endpoint) {
            copy($root . '/sager/' . $endpoint, $this->fixture . '/sager/' . $endpoint);
        }
        copy($root . '/sager/sagerIncludes/saveGanttPlanDates.php', $this->fixture . '/sager/sagerIncludes/saveGanttPlanDates.php');
        // Replace authentication and database I/O only; execute the real endpoints and handler.
        file_put_contents($this->fixture . '/includes/connect.php', <<<'PHP'
<?php
function db_modify($sql, $context) {
    $GLOBALS['queries'][] = $sql;
    if (count($GLOBALS['queries']) === ($GLOBALS['failure']['on'] ?? 0)) {
        if ($GLOBALS['failure']['mode'] === 'exit') {
            exit;
        }
        if ($GLOBALS['failure']['mode'] === 'throw') {
            throw new RuntimeException('Simulated Gantt write failure');
        }
        return "1\tquery failed";
    }
    if ($GLOBALS['inTransaction']) {
        $GLOBALS['pending'][] = $sql;
    } else {
        $GLOBALS['committed'][] = $sql;
    }
    return "0\tquery accepted";
}
function transaktion($action) {
    $GLOBALS['transactions'][] = $action;
    if ($action === 'begin') {
        $GLOBALS['inTransaction'] = true;
    } else {
        if ($action === 'commit') {
            $GLOBALS['committed'] = array_merge($GLOBALS['committed'], $GLOBALS['pending']);
        }
        $GLOBALS['pending'] = [];
        $GLOBALS['inTransaction'] = false;
    }
}
function db_escape_string($value) {
    $GLOBALS['escaped'][] = $value;
    return str_replace("'", "''", $value);
}
PHP);
        file_put_contents($this->fixture . '/includes/online.php', '<?php $sag_rettigheder = $testRights;');
        file_put_contents($this->fixture . '/includes/std_func.php', '<?php require_once ' . var_export($root . '/includes/std_func.php', true) . ';');
    }

    /** Remove the temporary endpoint tree, including session and diagnostic files. */
    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->fixture, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->fixture);
    }

    /** @return list<string> */
    private static function endpoints(): array
    {
        return ['ajax_planlaeg.php', 'ajax_planlaeg_sag.php', 'ajax_planlaeg_sager.php'];
    }

    /** @return array<string, array{string, string|null}> */
    public static function deniedRequests(): array
    {
        $cases = [];
        foreach (self::endpoints() as $endpoint) {
            foreach ([null, '', '0000000', '1101111', '00', '00x0000'] as $index => $rights) {
                $cases[$endpoint . ' rights ' . $index] = [$endpoint, $rights];
            }
        }
        return $cases;
    }

    #[DataProvider('deniedRequests')]
    public function testMissingPlanningRightsPreventEveryWrite(string $endpoint, ?string $rights): void
    {
        $result = $this->request($endpoint, $rights, self::validPost());
        self::assertSame(403, $result['status']);
        self::assertSame([], $result['queries']);
        self::assertSame([], $result['escaped']);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function invalidRequests(): array
    {
        $cases = [];
        $invalidFields = [
            'case SQL' => ['id' => "23' OR '1'='1"],
            'task SQL' => ['opg_id' => "34' OR '1'='1"],
            'case array' => ['id' => ['23']],
            'task array' => ['opg_id' => ['34']],
            'negative case' => ['id' => '-23'],
            'negative task' => ['opg_id' => '-34'],
            'undefined case' => ['id' => 'undefined'],
            'undefined task' => ['opg_id' => 'undefined'],
            'no target' => ['id' => '0', 'opg_id' => '0'],
            'start SQL' => ['planfra' => "06-11-2026', status='Afsluttet"],
            'end SQL' => ['plantil' => "07-11-2026' WHERE '1'='1"],
            'start array' => ['planfra' => ['06-11-2026']],
            'end array' => ['plantil' => ['07-11-2026']],
            'invalid start' => ['planfra' => '31-02-2026'],
            'invalid end' => ['plantil' => '31-11-2026'],
            'empty start' => ['planfra' => ''],
            'missing end' => ['plantil' => null],
            'reversed dates' => ['planfra' => '08-11-2026'],
            'reversed across year' => ['planfra' => '01-01-2027', 'plantil' => '31-12-2026'],
        ];
        foreach (self::endpoints() as $endpoint) {
            foreach ($invalidFields as $label => $fields) {
                $cases[$endpoint . ' ' . $label] = [$endpoint, array_replace(self::validPost(), $fields)];
            }
        }
        foreach ([null, '', '2', "0 OR 1=1", ['1']] as $index => $op) {
            $cases['invalid montage selector ' . $index] = ['ajax_planlaeg.php', array_replace(self::validPost(), ['op' => $op])];
        }
        $cases['reversed montage ISO'] = ['ajax_planlaeg.php', array_replace(self::validPost(), ['planfra' => '2026-12-01', 'plantil' => '2026-11-30'])];
        $cases['reversed montage mixed formats'] = ['ajax_planlaeg.php', array_replace(self::validPost(), ['planfra' => '01-12-2026', 'plantil' => '2026-11-30'])];
        return $cases;
    }

    /** @param array<string, mixed> $post */
    #[DataProvider('invalidRequests')]
    public function testInvalidRequestsCannotPartiallyUpdateDates(string $endpoint, array $post): void
    {
        $result = $this->request($endpoint, '0010000', $post);
        self::assertSame(400, $result['status']);
        self::assertSame([], $result['queries']);
        self::assertSame([], $result['transactions']);
    }

    /** @return array<string, array{string, array<string, mixed>, list<string>, list<string>}> */
    public static function validRequests(): array
    {
        $cases = [];
        foreach (['ajax_planlaeg_sag.php', 'ajax_planlaeg_sager.php'] as $endpoint) {
            $caseSql = "update sager set planfraop='2026-11-06', plantilop='2026-11-07' where id = '23'";
            $taskSql = "update opgaver set opg_planfra='2026-11-06', opg_plantil='2026-11-07' where id = '34'";
            $dates = ['2026-11-06', '2026-11-07'];
            $cases[$endpoint . ' case'] = [$endpoint, self::validPost(), [$caseSql], $dates];
            $cases[$endpoint . ' task'] = [$endpoint, array_replace(self::validPost(), ['id' => '0', 'opg_id' => '34']), [$taskSql], $dates];
            $cases[$endpoint . ' both'] = [$endpoint, array_replace(self::validPost(), ['opg_id' => '34']), [$caseSql, $taskSql], $dates];
        }
        $cases['montage'] = ['ajax_planlaeg.php', self::validPost(), [
            "update sager set planfraop='2026-11-06', plantilop='2026-11-07' where id = '23'",
        ], ['2026-11-06', '2026-11-07']];
        $cases['demontage'] = ['ajax_planlaeg.php', array_replace(self::validPost(), ['op' => '0']), [
            "update sager set planfraned='2026-11-06', plantilned='2026-11-07' where id = '23'",
        ], ['2026-11-06', '2026-11-07']];
        $cases['legacy ISO'] = ['ajax_planlaeg.php', array_replace(self::validPost(), ['planfra' => '2026-11-06', 'plantil' => '2026-11-07']), [
            "update sager set planfraop='2026-11-06', plantilop='2026-11-07' where id = '23'",
        ], ['2026-11-06', '2026-11-07']];
        $cases['montage mixed formats across year'] = ['ajax_planlaeg.php', array_replace(self::validPost(), ['planfra' => '31-12-2026', 'plantil' => '2027-01-01']), [
            "update sager set planfraop='2026-12-31', plantilop='2027-01-01' where id = '23'",
        ], ['2026-12-31', '2027-01-01']];
        foreach (self::endpoints() as $endpoint) {
            $cases[$endpoint . ' same day'] = [$endpoint, array_replace(self::validPost(), ['plantil' => '06-11-2026']), [
                "update sager set planfraop='2026-11-06', plantilop='2026-11-06' where id = '23'",
            ], ['2026-11-06', '2026-11-06']];
        }
        return $cases;
    }

    /**
     * @param array<string, mixed> $post
     * @param list<string> $queries
     * @param list<string> $dates
     */
    #[DataProvider('validRequests')]
    public function testAuthorizedSavesUpdateOnlyTheirIntendedColumns(string $endpoint, array $post, array $queries, array $dates): void
    {
        $result = $this->request($endpoint, '0010000', $post);
        self::assertSame(200, $result['status']);
        self::assertSame($queries, $result['queries']);
        self::assertSame($dates, $result['escaped']);
        self::assertSame($queries, $result['committed']);
        self::assertSame(count($queries) > 1 ? ['begin', 'commit'] : [], $result['transactions']);
    }

    /** @return array<string, array{string, int, string}> */
    public static function combinedFailures(): array
    {
        $cases = [];
        foreach (['ajax_planlaeg_sag.php', 'ajax_planlaeg_sager.php'] as $endpoint) {
            foreach ([1, 2] as $write) {
                foreach (['return', 'exit', 'throw'] as $mode) {
                    $cases["$endpoint write $write $mode"] = [$endpoint, $write, $mode];
                }
            }
        }
        return $cases;
    }

    /** A failed first or second update must leave neither target rescheduled. */
    #[DataProvider('combinedFailures')]
    public function testCombinedWriteFailuresCannotCommitEitherTarget(string $endpoint, int $write, string $mode): void
    {
        $post = array_replace(self::validPost(), ['opg_id' => '34']);
        $result = $this->request($endpoint, '0010000', $post, ['on' => $write, 'mode' => $mode]);
        self::assertSame(500, $result['status']);
        self::assertSame([], $result['committed']);
        self::assertCount($write, $result['queries']);
        // A terminating db_modify() closes the connection without committing its open transaction.
        self::assertSame($mode === 'exit' ? ['begin'] : ['begin', 'rollback'], $result['transactions']);
        if ($mode === 'throw') {
            self::assertStringContainsString('Simulated Gantt write failure', file_get_contents($this->fixture . '/error.log'));
        }
    }

    /** @return array<string, array{string, string}> */
    public static function singleFailures(): array
    {
        $cases = [];
        foreach (self::endpoints() as $endpoint) {
            foreach (['return', 'exit', 'throw'] as $mode) {
                $cases["$endpoint $mode"] = [$endpoint, $mode];
            }
        }
        return $cases;
    }

    /** Do not report success for any endpoint when its database write fails. */
    #[DataProvider('singleFailures')]
    public function testSingleWriteFailureReturnsServerError(string $endpoint, string $mode): void
    {
        $result = $this->request($endpoint, '0010000', self::validPost(), ['on' => 1, 'mode' => $mode]);
        self::assertSame(500, $result['status']);
        self::assertSame([], $result['committed']);
        self::assertSame([], $result['transactions']);
    }

    /** @return array<string, string> */
    private static function validPost(): array
    {
        return ['id' => '23', 'opg_id' => '0', 'planfra' => '06-11-2026', 'plantil' => '07-11-2026', 'op' => '1'];
    }

    /**
     * @param array<string, mixed> $post
     * @param array{on?: int, mode?: string} $failure
     * @return array{status: int, queries: list<string>, escaped: list<string>, committed: list<string>, transactions: list<string>}
     */
    private function request(string $endpoint, ?string $rights, array $post, array $failure = []): array
    {
        $script = 'http_response_code(200); $queries = []; $escaped = [];'
            . '$committed = []; $pending = []; $transactions = []; $inTransaction = false;'
            . 'set_error_handler(static function ($severity, $message, $file, $line) {'
            . 'throw new ErrorException($message, 0, $severity, $file, $line); });'
            . 'register_shutdown_function(static function () {'
            . 'echo json_encode(["status" => http_response_code(), "queries" => $GLOBALS["queries"], "escaped" => $GLOBALS["escaped"], '
            . '"committed" => $GLOBALS["committed"], "transactions" => $GLOBALS["transactions"]]); });'
            . '$failure = ' . var_export($failure, true) . ';'
            . '$testRights = ' . var_export($rights, true) . ';'
            . '$_POST = ' . var_export($post, true) . ';'
            . 'require ' . var_export($this->fixture . '/sager/' . $endpoint, true) . ';';
        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-d', 'error_log=' . $this->fixture . '/error.log', '-d', 'session.save_path=' . $this->fixture, '-r', $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->fixture . '/sager'
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stdout . $stderr);
        self::assertSame('', $stderr);
        return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
