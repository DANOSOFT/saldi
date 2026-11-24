<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Budget Gem saves on a chart of more than about 40 accounts (testlab round 2, budget-cycle, 2026-11-08/09).
 *
 * finans/budget.php draws kontonr[x] plus amount[x][z] and id[x][z] for every month of every account, about 25 fields
 * per account, with kontoantal, maanedantal and the visible Gem last. On the lab's 309-account book that is 7,700
 * fields; PHP keeps the first max_input_vars (1000) and drops the rest, so the Gem never arrived, the save was skipped
 * and the page came back from the database without a word. Every Gem in the lab logged "Input variables exceeded 1000".
 *
 * The page is served by PHP's built-in server with max_input_vars=1000, so PHP cuts the post the way Apache's PHP did
 * in the lab. Stub includes stand in for the database: db_modify() records each statement, and the first db_select()
 * (the page drawing itself after the save) ends the request with the statements as JSON. Pins:
 * - the form's own fields for the lab's book, as a browser without the page's script sends them, are refused with 400
 *   instead of being ignored;
 * - the same grid packed into one budget_grid field, as the page's script sends it, is saved cell by cell;
 * - a small book posted without the script still saves, and the insert escapes the account number.
 * e2e/budget-many-accounts.spec.mjs covers the script itself in a browser.
 *
 * History:
 * 20261105 CL/LH Created.
 */
final class BudgetManyAccountsSaveTest extends TestCase
{
    /** Open accounts on the lab's book (test_34). */
    private const ACCOUNTS = 309;
    private const MONTHS = 12;

    private static string $fixture = '';
    private static string $base = '';
    /** @var resource|null */
    private static $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$fixture = sys_get_temp_dir() . '/saldi-budget-post-' . bin2hex(random_bytes(8));
        mkdir(self::$fixture . '/finans', 0700, true);
        mkdir(self::$fixture . '/includes/oldDesign', 0700, true);
        $stdFunc = var_export(dirname(__DIR__, 3) . '/includes/std_func.php', true);
        $budget = var_export(dirname(__DIR__, 3) . '/finans/budget.php', true);
        // The page includes these by bare relative paths, so they resolve from the working directory the router sets.
        $stubs = [
            'includes/connect.php' => '<?php
                function db_escape_string($s) { return str_replace("\'", "\'\'", (string) $s); }
                function db_modify($q, $where = "") { $GLOBALS["budgetTestWrites"][] = $q; }
                function db_select($q, $where = "") { exit(json_encode(["writes" => $GLOBALS["budgetTestWrites"] ?? []])); }
                function db_fetch_array($q) { return false; }',
            'includes/online.php' => '<?php $regnaar = 17; $db = "test_34"; $bruger_id = 1; $sprog_id = 1; $menu = "";',
            'includes/std_func.php' => '<?php require_once ' . $stdFunc . ';',
            'includes/finansfunk.php' => '<?php',
            'includes/topline_settings.php' => '<?php',
            'includes/grid.php' => '<?php',
            'includes/oldDesign/header.php' => '<?php',
            'router.php' => '<?php chdir(__DIR__ . "/finans"); require ' . $budget . ';',
        ];
        foreach ($stubs as $path => $code) {
            file_put_contents(self::$fixture . '/' . $path, $code);
        }

        $probe = stream_socket_server('tcp://127.0.0.1:0');
        if (!$probe) {
            return;
        }
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        self::$server = proc_open(
            [PHP_BINARY, '-d', 'max_input_vars=1000', '-d', 'session.save_path=' . self::$fixture,
                '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' . self::$fixture . '/php.log',
                '-S', '127.0.0.1:' . $port, self::$fixture . '/router.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            self::$fixture
        ) ?: null;
        self::$base = 'http://127.0.0.1:' . $port;
        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($socket) {
                fclose($socket);
                return;
            }
            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
        if (self::$fixture === '' || !is_dir(self::$fixture)) {
            return;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::$fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir(self::$fixture);
    }

    protected function setUp(): void
    {
        self::assertNotNull(self::$server, 'php -S could not be started');
        file_put_contents(self::$fixture . '/php.log', '');
    }

    public function testTheLabsGridPostedFieldByFieldIsRefusedInsteadOfIgnored(): void
    {
        [$status, $body] = $this->post($this->formFields(self::ACCOUNTS, $this->labCells()));

        self::assertStringContainsString('Input variables exceeded 1000', $this->log(), 'PHP cut the post, as in the lab');
        self::assertSame(400, $status, 'the cut post is refused, not drawn as if saved: ' . $body);
        self::assertStringContainsString('Budget submission is incomplete', $body);
    }

    public function testTheLabsGridPackedIntoOneFieldIsSaved(): void
    {
        [$status, $body] = $this->post($this->packedFields(self::ACCOUNTS, $this->labCells()));

        self::assertSame('', $this->log(), 'no input-vars cut and no warning');
        self::assertSame(200, $status, $body);
        $expected = [];
        for ($z = 1; $z <= self::MONTHS; $z++) {
            // -1.234,56* in January fills every month of 1010 with -1235.
            $expected[] = "insert into budget(regnaar,kontonr,md,amount) values (17,'1010','$z','-1235')";
        }
        for ($z = 1; $z <= self::MONTHS; $z++) {
            // Account 200's stored months keep their rows; March, emptied, is set to 0 and deleted below.
            $expected[] = "update budget set amount='" . ($z === 3 ? '0' : '100') . "' where id='" . (9000 + $z) . "'";
        }
        $expected[] = "insert into budget(regnaar,kontonr,md,amount) values (17,'4090','12','877')";
        $expected[] = 'delete from budget where amount = 0';
        self::assertSame($expected, $this->writes($body));
    }

    public function testASmallBookPostedWithoutTheScriptStillSaves(): void
    {
        $cells = [
            1 => [1 => ['5*', 0]],
            2 => [2 => ['7', 0]],
            3 => [1 => ['', 77]],
        ];
        $fields = $this->formFields(3, $cells);
        // A crafted account number reaches the insert only as a quoted string.
        $fields[array_search(['kontonr[2]', '1020'], $fields, true)] = ['kontonr[2]', "1020' or '1'='1"];

        [$status, $body] = $this->post($fields);

        self::assertSame('', $this->log());
        self::assertSame(200, $status, $body);
        $expected = [];
        for ($z = 1; $z <= self::MONTHS; $z++) {
            $expected[] = "insert into budget(regnaar,kontonr,md,amount) values (17,'1010','$z','5')";
        }
        $expected[] = "insert into budget(regnaar,kontonr,md,amount) values (17,'1020'' or ''1''=''1','2','7')";
        $expected[] = "update budget set amount='0' where id='77'";
        $expected[] = 'delete from budget where amount = 0';
        self::assertSame($expected, $this->writes($body));
    }

    /**
     * The lab's entries (findings DEVY-1) on the first and last account, plus stored months on a middle account.
     *
     * @return array<int, array<int, array{0: string, 1: int}>> account => month => [typed value, stored budget id]
     */
    private function labCells(): array
    {
        $cells = [1 => [1 => ['-1.234,56*', 0]], self::ACCOUNTS => [12 => ['876,54', 0]]];
        for ($z = 1; $z <= self::MONTHS; $z++) {
            $cells[200][$z] = [$z === 3 ? '' : '100', 9000 + $z];
        }
        return $cells;
    }

    /**
     * The form's fields in the order a browser sends them for the visible Gem: the fill controls at the top, then per
     * account kontonr[x] and each month's amount[x][z] and id[x][z], then the counts and the Gem at the end.
     *
     * @param array<int, array<int, array{0: string, 1: int}>> $cells
     * @return list<array{0: string, 1: string}>
     */
    private function formFields(int $accounts, array $cells): array
    {
        $fields = [['plusminus', '+'], ['procent', '0']];
        for ($x = 1; $x <= $accounts; $x++) {
            $fields[] = ["kontonr[$x]", (string) (1000 + 10 * $x)];
            for ($z = 1; $z <= self::MONTHS; $z++) {
                $fields[] = ["amount[$x][$z]", $cells[$x][$z][0] ?? ''];
                $fields[] = ["id[$x][$z]", (string) ($cells[$x][$z][1] ?? 0)];
            }
        }
        array_push($fields, ['kontoantal', (string) $accounts], ['maanedantal', (string) self::MONTHS], ['gem', 'Gem']);
        return $fields;
    }

    /**
     * What the page's script makes of formFields(): the grid's fields leave the post and go as one JSON field at its end.
     *
     * @param array<int, array<int, array{0: string, 1: int}>> $cells
     * @return list<array{0: string, 1: string}>
     */
    private function packedFields(int $accounts, array $cells): array
    {
        $grid = ['kontonr' => [], 'amount' => [], 'id' => []];
        $fields = [];
        foreach ($this->formFields($accounts, $cells) as [$name, $value]) {
            if (preg_match('/^(kontonr|amount|id)\[(\d+)\](?:\[(\d+)\])?$/', $name, $m)) {
                if (isset($m[3])) {
                    $grid[$m[1]][$m[2]][$m[3]] = $value;
                } else {
                    $grid[$m[1]][$m[2]] = $value;
                }
            } else {
                $fields[] = [$name, $value];
            }
        }
        $fields[] = ['budget_grid', (string) json_encode($grid, JSON_FORCE_OBJECT)];
        return $fields;
    }

    /**
     * @param list<array{0: string, 1: string}> $fields
     * @return array{0: int, 1: string}
     */
    private function post(array $fields): array
    {
        $body = implode('&', array_map(static fn (array $f): string => rawurlencode($f[0]) . '=' . rawurlencode($f[1]), $fields));
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 60,
        ]]);
        $response = file_get_contents(self::$base . '/finans/budget.php?regnaar=17', false, $context);
        $statusLine = $http_response_header[0] ?? '';
        self::assertMatchesRegularExpression('/^HTTP\/\S+ \d{3}/', $statusLine, 'the server answered');
        return [(int) explode(' ', $statusLine)[1], (string) $response];
    }

    /** @return list<string> */
    private function writes(string $body): array
    {
        $decoded = json_decode($body, true);
        self::assertIsArray($decoded, 'the page went on to draw itself after the save: ' . $body);
        return $decoded['writes'];
    }

    /** What PHP logged during the request (the built-in server's access lines go to its stderr, not here). */
    private function log(): string
    {
        return trim((string) file_get_contents(self::$fixture . '/php.log'));
    }
}
