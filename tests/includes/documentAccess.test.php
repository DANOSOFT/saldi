<?php
// 20261110 CDX/ASR Exercise document source/path ownership; delete and move need an exact tenant/source/document match.
// 20261124 CL/ASR Comments state the current rule; no code change.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/docsIncludes/documentAccess.php';

final class documentAccess extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/document-access-' . bin2hex(random_bytes(5));
        foreach (['tenant_a/finance/1/2', 'tenant_a/debitor/orders/3', 'tenant_b/finance/1/2'] as $directory) {
            mkdir("$this->root/$directory", 0755, true);
            file_put_contents("$this->root/$directory/invoice.pdf", '%PDF fixture');
        }
        symlink("$this->root/tenant_b/finance/1/2/invoice.pdf", "$this->root/tenant_a/finance/1/2/outside.pdf");
        symlink("$this->root/tenant_a/finance/1/2/invoice.pdf", "$this->root/tenant_a/debitor/orders/3/finance.pdf");
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function test_authoritative_reference_matches_file_source_and_id(): void
    {
        $root = "$this->root/tenant_a";
        $file = "$root/finance/1/2/invoice.pdf";
        $row = ['id' => '42', 'source' => 'kassekladde', 'source_id' => '2', 'filepath' => '/finance/1/2', 'filename' => 'invoice.pdf'];
        $match = docAccessMatchingRow($root, $file, 'kassekladde', 2, [$row]);
        self::assertSame(42, $match['id']);
        self::assertSame($file, $match['canonicalPath']);
        self::assertNull(docAccessMatchingRow($root, $file, 'kassekladde', 3, [$row]));
        self::assertNull(docAccessMatchingRow($root, $file, 'kassekladde', 2, []));
        self::assertNull(docAccessMatchingRow($root, $file, 'debitorOrdrer', 2, [$row]));
        self::assertNull(docAccessMatchingRow($root, "$root/debitor/orders/3/invoice.pdf", 'kassekladde', 2, [$row]));
    }

    public function test_linked_finance_file_is_allowed_but_a_false_module_row_is_not(): void
    {
        $root = "$this->root/tenant_a";
        $file = "$root/finance/1/2/invoice.pdf";
        $linked = ['id' => 43, 'source' => 'kassekladde', 'source_id' => 99, 'filepath' => '/finance/1/2', 'filename' => 'invoice.pdf'];
        self::assertSame(43, docAccessMatchingRow($root, $file, 'kassekladde', 99, [$linked])['id']);
        $forged = array_replace($linked, ['source' => 'debitorOrdrer']);
        self::assertNull(docAccessMatchingRow($root, $file, 'debitorOrdrer', 99, [$forged]));
        $forged['filepath'] = '/debitor/orders/3';
        $forged['filename'] = 'finance.pdf';
        self::assertNull(docAccessMatchingRow($root, "$root/debitor/orders/3/finance.pdf", 'debitorOrdrer', 99, [$forged]));
    }

    public function test_paths_cannot_leave_the_tenant_by_absolute_path_traversal_or_symlink(): void
    {
        $root = "$this->root/tenant_a";
        self::assertNull(docAccessCanonicalPath($root, "$this->root/tenant_b/finance/1/2/invoice.pdf"));
        self::assertNull(docAccessCanonicalPath($root, "$root/../tenant_b/finance/1/2/invoice.pdf"));
        self::assertNull(docAccessCanonicalPath($root, "$root/finance/1/2/outside.pdf"));
        self::assertNull(docAccessCanonicalPath($root, "$root/finance/1/2/missing.pdf"));
        self::assertSame("$root/finance/1/2/invoice.pdf", docAccessCanonicalPath($root, "$root/debitor/orders/3/finance.pdf"));
    }

    public function test_actual_delete_and_move_handlers_refuse_files_without_the_claimed_reference(): void
    {
        foreach (['deleteDoc', 'moveDoc'] as $action) {
            $program = '<?php ' . <<<'CODE'
$writes = 0;
function db_select($sql, $trace) { return []; }
function db_fetch_array($result) { return false; }
function db_escape_string($value) { return str_replace("'", "''", $value); }
function db_modify($sql, $trace) { $GLOBALS['writes']++; return "0\taccepted"; }
register_shutdown_function(function () { echo "\n__RESULT__" . json_encode([http_response_code(), $GLOBALS['writes']]); });
CODE;
            $program .= '$docFolder = ' . var_export($this->root, true) . '; $db = "tenant_a"; $source = "debitorOrdrer"; $sourceId = 3; $kladde_id = 1;';
            $program .= '$' . $action . ' = ' . var_export("$this->root/tenant_a/finance/1/2/invoice.pdf", true) . ';';
            $program .= 'include ' . var_export(dirname(__DIR__, 2) . "/includes/docsIncludes/$action.php", true) . ';';
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
            self::assertStringContainsString('__RESULT__', $output);
            $result = json_decode(explode('__RESULT__', $output)[1], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame([403, 0], $result, $action . ' must refuse before DB or file mutation');
            self::assertSame('%PDF fixture', file_get_contents("$this->root/tenant_a/finance/1/2/invoice.pdf"));
        }
    }
}
