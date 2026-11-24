<?php
// 20261110 CDX/ASR Cover module mutation gates, safe report dispatch and document ownership.
// 20261124 CL/ASR Comments state the current rule; no code change.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/accountReportPermissions.php';
require_once __DIR__ . '/../../includes/docsIncludes/docFileFunc.php';
require_once __DIR__ . '/../../includes/docsIncludes/documentAccess.php';

final class modulePermissionRequests extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function mutationRoutes(): array
    {
        return [
            'journal posting' => ['finans/bogfor.php', 'finans.kassekladde'],
            'bank import' => ['finans/upload.php', 'finans.kassekladde'],
            'customer posting' => ['debitor/bogfor.php', 'debitor.ordre'],
            'customer delivery' => ['debitor/levering.php', 'debitor.ordre'],
            'supplier posting' => ['kreditor/bogfor.php', 'kreditor.ordre'],
            'supplier receipt' => ['kreditor/modtag.php', 'kreditor.ordre'],
            'shop unlink' => ['lager/slet_shopbinding.php', 'lager.varer'],
            'assembly cost update' => ['lager/updateSetKostprise.php', 'lager.varer'],
            'create reminder' => ['debitor/ny_rykker.php', 'debitor.konti'],
            'close reminder' => ['debitor/afslut_rykker.php', 'debitor.konti'],
            'document link API' => ['includes/docsIncludes/linkDocumentsApi.php', 'finans.kassekladde'],
            'clip upload API' => ['includes/docsIncludes/uploadToClipApi.php', 'finans.kassekladde'],
        ];
    }

    #[DataProvider('mutationRoutes')]
    public function test_get_mutations_declare_write_before_the_session_gate(string $route, string $key): void
    {
        $gate = $this->bootstrapGate($route, ['id' => '123']);
        self::assertSame($key, $gate['key']);
        self::assertSame('write', $gate['level']);
    }

    public function test_document_read_and_each_get_mutation_keep_their_owning_module(): void
    {
        foreach (['kassekladde' => 'finans.kassekladde', 'debitorOrdrer' => 'debitor.ordre', 'creditorOrder' => 'kreditor.ordre'] as $source => $key) {
            $query = ['source' => $source, 'sourceId' => '123'];
            $read = $this->bootstrapGate('includes/documents.php', $query);
            self::assertSame($key, $read['key']);
            self::assertSame('read', $read['level']);
            foreach (['deleteDoc', 'unlinkDoc', 'moveDoc', 'doLink', 'unlink', 'unlinkFile', 'cleanupOrphans'] as $action) {
                $write = $this->bootstrapGate('includes/documents.php', $query + [$action => '1']);
                self::assertSame($key, $write['key']);
                self::assertSame('write', $write['level']);
            }
        }
    }

    public function test_report_filter_forms_and_actions_are_classified_before_online(): void
    {
        foreach (['debitor', 'kreditor'] as $module) {
            $read = $this->bootstrapGate("$module/rapport.php", [], ['dato' => '2026', 'konto' => '', 'openpost' => '1']);
            self::assertSame("$module.rapporter", $read['key']);
            self::assertTrue($read['postIsRead']);
            foreach (['slet', 'bogfør', 'afslut', 'ryk alle', 'mail kontoudtog', 'inkasso'] as $action) {
                self::assertFalse($this->bootstrapGate("$module/rapport.php", [], ['rapportart' => 'openpost', 'submit' => $action])['postIsRead']);
            }
        }
    }

    public function test_customer_lookup_cannot_use_customer_access_to_fetch_suppliers(): void
    {
        foreach (['DO' => 'debitor.ordre|debitor.konti', 'KO' => 'kreditor.ordre|kreditor.konti', 'PO' => 'pos.kasse|debitor.konti'] as $orderArt => $key) {
            $gate = $this->bootstrapGate('debitor/accountLookupData.php', ['o_art' => $orderArt]);
            self::assertSame($key, $gate['key']);
            self::assertTrue($gate['postIsRead']);
        }
        $supplier = $this->bootstrapGate('kreditor/accountLookupData.php', ['o_art' => 'DO']);
        self::assertSame('kreditor.ordre|kreditor.konti', $supplier['key'], 'The supplier-only endpoint never switches to customer access.');
        self::assertTrue($supplier['postIsRead']);
    }

    public function test_stock_report_filters_remain_read_and_all_repair_links_need_item_write(): void
    {
        $filters = ['dato' => '10-11-2026', 'dateType' => 'levdate', 'varegruppe' => '0:Alle', 'varenr' => 'ABC', 'lss_antal' => '1:10'];
        $read = $this->bootstrapGate('lager/lagerstatus.php', [], $filters);
        self::assertSame('lager.rapporter', $read['key']);
        self::assertTrue($read['postIsRead']);
        self::assertFalse($this->bootstrapGate('lager/lagerstatus.php', [], $filters + ['save' => '1'])['postIsRead']);
        self::assertFalse($this->bootstrapGate('lager/lagerstatus.php', [], ['dato' => ['10-11-2026']])['postIsRead']);
        foreach ([['ret_behold' => '2'], ['ret_behold' => '02'], ['ajour' => '1'], ['opdater' => '123']] as $repair) {
            $gate = $this->bootstrapGate('lager/lagerstatus.php', $repair, $filters);
            self::assertSame('lager.varer', $gate['key']);
            self::assertSame('write', $gate['level']);
        }
    }

    public function test_unknown_fields_and_array_controls_never_receive_the_read_exception(): void
    {
        self::assertTrue(account_report_post_is_read(['rapportart' => 'kontokort', 'submit' => 'ok', 'dato_fra' => '2026-01-01']));
        self::assertFalse(account_report_post_is_read(['rapportart' => 'kontokort', 'submit' => 'ok', 'newAction' => '1']));
        self::assertFalse(account_report_post_is_read(['rapportart' => 'openpost', 'konto_id' => [42]]));
        self::assertFalse(account_report_post_is_read(['rapportart' => ['openpost']]));
        self::assertFalse(account_report_post_is_read(['submit' => ['ok']]));
    }

    public function test_untrusted_report_names_cannot_call_php_functions(): void
    {
        self::assertSame('accountChart', account_report_name('accountchart'));
        self::assertSame('openpost', account_report_name('åben post'));
        foreach (['db_modify', 'system', 'unlink', 'bogfor_rykker', 'autoudlign', ['openpost']] as $name) {
            self::assertNull(account_report_name($name));
            self::assertFalse(account_report_post_is_read(['rapportart' => $name, 'submit' => 'ok']));
        }
        // account_report_dispatch() calls only a known report renderer, so the file stays.
        $file = tempnam(sys_get_temp_dir(), 'report-dispatch-');
        try {
            self::assertNull(account_report_dispatch('unlink', $file));
            self::assertFileExists($file);
        } finally {
            unlink($file);
        }
    }

    public function test_document_paths_require_the_module_they_resolve_to(): void
    {
        $paths = [
            'finance/30/141/x.pdf' => 'finans.kassekladde',
            'bilag/kladde_30/bilag_141/x.pdf' => 'finans.kassekladde',
            'debitor/orders/42/x.pdf' => 'debitor.ordre',
            'debitor/42/x.pdf' => 'debitor.konti',
            'creditor/orders/42/x.pdf' => 'kreditor.ordre',
            'creditor/42/x.pdf' => 'kreditor.konti',
            'debitor/../finance/x.pdf' => 'finans.kassekladde',
            './debitor//orders/42/x.pdf' => 'debitor.ordre',
        ];
        foreach ($paths as $path => $expected) {
            self::assertSame($expected, docFilePermissionKey('doc', $path));
            self::assertSame($expected, $this->bootstrapGate('includes/docsIncludes/docFile.php', ['k' => 'doc', 'f' => $path])['key']);
        }
        self::assertNull(docFilePermissionKey('doc', '../other_tenant/finance/x.pdf'));
        self::assertNull(docFilePermissionKey('doc', 'unrecognised/x.pdf'));
        self::assertSame('finans.kassekladde|debitor.ordre|kreditor.ordre', docFilePermissionKey('doc', 'pulje/x.pdf'));
    }

    public function test_a_symlink_cannot_disguise_the_document_owner(): void
    {
        $root = sys_get_temp_dir() . '/module-doc-' . bin2hex(random_bytes(4));
        mkdir($root);
        mkdir("$root/finance");
        mkdir("$root/debitor");
        file_put_contents("$root/finance/x.pdf", '%PDF');
        symlink("$root/finance/x.pdf", "$root/debitor/x.pdf");
        try {
            $resolved = docFileResolve($root, 'debitor/x.pdf', ['pdf']);
            self::assertNotNull($resolved);
            self::assertSame('finans.kassekladde', docFilePermissionKey('doc', substr($resolved, strlen($root) + 1)));
        } finally {
            unlink("$root/debitor/x.pdf");
            unlink("$root/finance/x.pdf");
            rmdir("$root/debitor");
            rmdir("$root/finance");
            rmdir($root);
        }
    }

    public function test_invoice_counter_has_no_public_database_action_and_internal_update_is_atomic(): void
    {
        $root = dirname(__DIR__, 2);
        $program = '<?php ' . <<<'CODE'
$queries = [];
function db_escape_string($value) { return str_replace("'", "''", $value); }
function db_modify($sql, $trace, $master = false) { $GLOBALS['queries'][] = [$sql, $master]; return $GLOBALS['writeResult'] ?? "0\tquery accepted"; }
CODE;
        $program .= 'require ' . var_export("$root/includes/easyUblInvoiceCounter.php", true) . ';';
        $program .= '$ok = easyubl_increment_invoice_counter("tenant_42");';
        $program .= '$invalid = easyubl_increment_invoice_counter("tenant_42\' OR 1=1");';
        $program .= '$writeResult = "1\tquery failed"; $failed = easyubl_increment_invoice_counter("tenant_42");';
        $program .= 'ob_start(); $_GET = ["db" => "tenant_42"]; include ' . var_export("$root/debitor/increaseInvoiceNumber.php", true) . '; $body = ob_get_clean();';
        $program .= 'echo json_encode([$ok, $invalid, $failed, $queries, http_response_code(), json_decode($body, true)]);';
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
        [$ok, $invalid, $failed, $queries, $status, $response] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($ok);
        self::assertFalse($invalid);
        self::assertFalse($failed, 'A returned database error must not be reported as success.');
        self::assertCount(2, $queries, 'The retired endpoint and invalid tenant must not reach the database.');
        self::assertTrue($queries[0][1], 'The counter lives in the master database.');
        self::assertStringContainsString('COALESCE(invoices, 0) + 1', $queries[0][0]);
        self::assertSame(410, $status);
        self::assertFalse($response['success']);
    }

    /**
     * Evaluates only the real entrypoint's declarations up to online.php, replacing
     * bootstrap includes with no-ops. Nothing after the authorization boundary runs.
     *
     * @param string $route Path below the application root.
     * @param array<string, mixed> $get Query fields.
     * @param array<string, mixed> $post Form fields.
     * @return array{key: ?string, level: string, postIsRead: bool}
     */
    private function bootstrapGate(string $route, array $get, array $post = []): array
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $route);
        $prefix = '';
        $include = null;
        $depth = 0;
        $found = false;
        $functionDepth = null;
        $skipFunction = false;
        foreach (token_get_all($source) as $token) {
            $text = is_array($token) ? $token[1] : $token;
            if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
            if (is_array($token) && $token[0] === T_FUNCTION) $skipFunction = true;
            if ($skipFunction) {
                if ($text === '{') $functionDepth = ($functionDepth ?? 0) + 1;
                if ($text === '}' && --$functionDepth === 0) {
                    $skipFunction = false;
                    $functionDepth = null;
                }
                continue;
            }
            // The gate inside lookup APIs sits inside a top-level try/catch. Keep its scope only.
            if (is_array($token) && $token[0] === T_TRY) continue;
            if (is_array($token) && in_array($token[0], [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
                $include = '';
            }
            if ($include !== null) {
                $include .= $text;
                if ($text === ';') {
                    if (str_contains($include, '/online.php')) {
                        $found = true;
                        break;
                    }
                    $include = null;
                }
                continue;
            }
            if ($text === '{') $depth++;
            if ($text === '}') $depth--;
            $prefix .= $text;
        }
        self::assertTrue($found, "$route must authenticate through online.php");
        $prefix = preg_replace('/@?session_start\(\);/', '', $prefix);
        $prefix .= '\nreturn ["key" => $permission_key ?? null, "level" => $permission_level ?? "read", "postIsRead" => $permission_post_read ?? false];';
        $prefix = str_replace('\\nreturn', "\nreturn", $prefix) . str_repeat('}', $depth);
        $previousGet = $_GET;
        $previousPost = $_POST;
        $previousRequest = $_REQUEST;
        $_GET = $get;
        $_POST = $post;
        $_REQUEST = array_merge($get, $post);
        $buffers = ob_get_level();
        ob_start();
        try {
            return eval($prefix);
        } finally {
            while (ob_get_level() > $buffers) ob_end_clean();
            $_GET = $previousGet;
            $_POST = $previousPost;
            $_REQUEST = $previousRequest;
        }
    }
}
