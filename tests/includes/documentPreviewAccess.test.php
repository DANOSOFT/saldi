<?php
// 20261110 CDX/ASR Cover canonical XML ownership, session-bound previews and production permission modes.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/docsIncludes/docFileFunc.php';

final class documentPreviewAccess extends TestCase
{
    private string $root;
    private string $source;
    private string $preview;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/document-preview-' . bin2hex(random_bytes(5));
        foreach (['bilag/tenant_a/finance/1/2', 'bilag/tenant_a/debitor/orders/3', 'bilag/tenant_b/finance/1/2', 'temp/tenant_a'] as $folder) {
            mkdir("$this->root/$folder", 0750, true);
        }
        $this->source = "$this->root/bilag/tenant_a/finance/1/2/invoice.xml";
        file_put_contents($this->source, '<Invoice>PRIVATE_FINANCE_XML</Invoice>');
        file_put_contents("$this->root/bilag/tenant_b/finance/1/2/invoice.xml", '<Invoice>OTHER_TENANT</Invoice>');
        $this->preview = "$this->root/temp/tenant_a/xml_view_" . md5($this->source) . '.html';
        file_put_contents($this->preview, '<p>PRIVATE_FINANCE_PREVIEW</p>');
        symlink($this->source, "$this->root/bilag/tenant_a/debitor/orders/3/disguised.xml");
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
        $_SESSION = [];
    }

    public function test_canonical_source_owner_survives_a_misleading_folder_and_rejects_another_tenant(): void
    {
        $root = "$this->root/bilag/tenant_a";
        $target = docFileReadTarget($root, "$root/debitor/orders/3/disguised.xml");
        self::assertSame('finans.kassekladde', $target['key']);
        self::assertSame($this->source, $target['path']);
        self::assertNull(docFileReadTarget($root, "$root/../tenant_b/finance/1/2/invoice.xml"));
        self::assertNull(docFilePermissionKey('temp', basename($this->preview)));
    }

    public function test_predictable_cached_name_is_not_an_authorization_in_any_mode(): void
    {
        foreach (['deny', 'log'] as $mode) {
            [$status, $body] = $this->viewer('temp', basename($this->preview), $mode, '0020000000000000');
            self::assertSame(403, $status);
            self::assertStringNotContainsString('PRIVATE_FINANCE_PREVIEW', $body);
        }
    }

    public function test_registered_preview_uses_current_original_permission_and_preserves_log_mode(): void
    {
        self::assertTrue(docFileRegisterPreview('tenant_a', "$this->root/bilag/tenant_a", $this->source, $this->preview));
        [$status, $body] = $this->viewer('temp', basename($this->preview), 'deny', '0020000000000000');
        self::assertSame(200, $status);
        self::assertStringContainsString('PRIVATE_FINANCE_PREVIEW', $body);

        // A role change removes finance access while the same preview remains registered.
        [$status, $body] = $this->viewer('temp', basename($this->preview), 'deny', '0000020000000000');
        self::assertSame(403, $status);
        self::assertStringNotContainsString('PRIVATE_FINANCE_PREVIEW', $body);
        [$status, $body] = $this->viewer('temp', basename($this->preview), 'log', '0000020000000000');
        self::assertSame(200, $status);
        self::assertStringContainsString('PRIVATE_FINANCE_PREVIEW', $body);
    }

    public function test_replaced_original_preview_and_another_session_or_tenant_are_rejected(): void
    {
        $root = "$this->root/bilag/tenant_a";
        $temp = "$this->root/temp/tenant_a";
        $name = basename($this->preview);
        self::assertTrue(docFileRegisterPreview('tenant_a', $root, $this->source, $this->preview));
        self::assertNull(docFilePreviewTarget('tenant_b', $root, $temp, $name));
        $session = $_SESSION;
        $_SESSION = [];
        self::assertNull(docFilePreviewTarget('tenant_a', $root, $temp, $name));
        $_SESSION = $session;
        file_put_contents($this->preview, '<p>REPLACED</p>');
        self::assertNull(docFilePreviewTarget('tenant_a', $root, $temp, $name));
        file_put_contents($this->preview, '<p>PRIVATE_FINANCE_PREVIEW</p>');
        file_put_contents($this->source, '<Invoice>REPLACED</Invoice>');
        self::assertNull(docFilePreviewTarget('tenant_a', $root, $temp, $name));
    }

    public function test_actual_file_endpoint_checks_resolved_owner_and_tenant(): void
    {
        [$status, $body] = $this->viewer('doc', 'debitor/orders/3/disguised.xml', 'deny', '0000020000000000');
        self::assertSame(403, $status);
        self::assertStringNotContainsString('PRIVATE_FINANCE_XML', $body);
        [$status, $body] = $this->viewer('doc', '../tenant_b/finance/1/2/invoice.xml', 'log', '0020000000000000');
        self::assertSame(404, $status);
        self::assertStringNotContainsString('OTHER_TENANT', $body);
    }

    public function test_xml_viewer_checks_reference_and_owner_before_reading_or_converting(): void
    {
        foreach (['deny', 'log'] as $mode) {
            [$status, $body] = $this->documentViewer('debitorOrdrer', $mode, '0000020000000000');
            self::assertSame(403, $status, 'A forged source cannot authorize a finance XML, even in log mode.');
            self::assertStringNotContainsString('PRIVATE_FINANCE_XML', $body);
            [$status, $body] = $this->documentViewer('kassekladde', $mode, '0020000000000000', false);
            self::assertSame(403, $status, 'A file with no matching reference cannot be rendered.');
        }
        [$status, $body] = $this->documentViewer('kassekladde', 'deny', '0000020000000000');
        self::assertSame(403, $status);
        self::assertStringNotContainsString('PRIVATE_FINANCE_XML', $body);
        [$status, $body] = $this->documentViewer('kassekladde', 'deny', '0020000000000000');
        self::assertSame(200, $status);
        self::assertStringContainsString('&lt;Invoice&gt;PRIVATE_FINANCE_XML&lt;/Invoice&gt;', $body);
    }

    /** @return array{int, string} HTTP status and body from the real XML renderer. */
    private function documentViewer(string $sourceName, string $mode, string $rights, bool $hasReference = true): array
    {
        $app = dirname(__DIR__, 2);
        $program = '<?php ' . <<<'PHP'
function tbl_exists($name) { return $name === 'settings'; }
function db_select($sql, $trace) {
    if (str_contains($sql, 'FROM documents')) { return new ArrayIterator($GLOBALS['testRows']); }
    if (str_contains($sql, "var_name = 'enforce'")) { return new ArrayIterator([['var_value' => $GLOBALS['testMode']]]); }
    return new ArrayIterator([]);
}
function db_fetch_array($rows) { if (!$rows->valid()) { return false; } $row = $rows->current(); $rows->next(); return $row; }
function db_escape_string($value) { return str_replace("'", "''", $value); }
function findtekst($key, $language) { return explode('|', (string)$key, 2)[1] ?? $key; }
register_shutdown_function(function () { echo "\n__STATUS__" . (http_response_code() ?: 200); });
$_SESSION = []; $_GET = []; $_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/includes/documents.php'; $_SERVER['HTTP_ACCEPT'] = 'application/json';
$bruger_id = 42; $sprog_id = 1; $db = 'tenant_a'; $sourceId = 2;
PHP;
        $rows = $hasReference ? [['id' => 1, 'source' => $sourceName, 'source_id' => 2, 'filepath' => 'finance/1/2', 'filename' => 'invoice.xml']] : [];
        $program .= '$testRows = ' . var_export($rows, true) . '; $source = ' . var_export($sourceName, true) . ';';
        $program .= '$testMode = ' . var_export($mode, true) . '; $rettigheder = ' . var_export($rights, true) . ';';
        $program .= '$docFolder = ' . var_export("$this->root/bilag", true) . '; $showDoc = ' . var_export($this->source, true) . ';';
        $program .= 'require ' . var_export("$app/includes/permissions.php", true) . ';';
        $program .= 'include ' . var_export("$app/includes/docsIncludes/showDoc.php", true) . ';';
        return $this->execute($program);
    }

    /**
     * Executes the real viewer and central permission gate, replacing only login/DB bootstrap and storage root.
     *
     * @return array{int, string} HTTP status and body.
     */
    private function viewer(string $kind, string $filename, string $mode, string $rights): array
    {
        $app = dirname(__DIR__, 2);
        $viewer = file_get_contents("$app/includes/docsIncludes/docFile.php");
        $viewer = str_replace(['<?php', 'session_start();', "include(__DIR__ . '/../connect.php');", "include_once(__DIR__ . '/../std_func.php');"], '', $viewer);
        $viewer = str_replace("include(__DIR__ . '/../online.php');", "perm_enforce_request(\$permission_key, 'read', true, null);", $viewer);
        $viewer = str_replace('$appRoot = dirname(__DIR__, 2);', '$appRoot = ' . var_export($this->root, true) . ';', $viewer);
        $viewer = str_replace('__DIR__', var_export("$app/includes/docsIncludes", true), $viewer);
        $program = '<?php ' . <<<'PHP'
function tbl_exists($name) { return $name === 'settings'; }
function db_select($sql, $trace) { return ['var_value' => $GLOBALS['testMode']]; }
function db_fetch_array($row) { return $row; }
function findtekst($key, $language) { return explode('|', (string)$key, 2)[1] ?? $key; }
register_shutdown_function(function () { echo "\n__STATUS__" . (http_response_code() ?: 200); });
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/includes/docsIncludes/docFile.php';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$bruger_id = 42; $sprog_id = 1; $db = 'tenant_a'; $sqdb = 'master';
PHP;
        $program .= '$testMode = ' . var_export($mode, true) . '; $rettigheder = ' . var_export($rights, true) . ';';
        $program .= '$_SESSION = ' . var_export($_SESSION, true) . '; $_GET = ' . var_export(['k' => $kind, 'f' => $filename], true) . ';';
        $program .= 'require ' . var_export("$app/includes/permissions.php", true) . ';' . $viewer;
        return $this->execute($program);
    }

    /** @return array{int, string} HTTP status and body from an isolated fixture process. */
    private function execute(string $program): array
    {
        $pipes = [];
        $process = proc_open([PHP_BINARY], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fwrite($pipes[0], $program);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame('', $errors);
        self::assertStringContainsString('__STATUS__', $output);
        [$body, $status] = explode('__STATUS__', $output);
        return [(int)$status, $body];
    }
}
