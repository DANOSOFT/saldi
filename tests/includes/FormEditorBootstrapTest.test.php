<?php
// 20261117 CDX/ASR Exercise the actual editor bootstrap, translated titles, expired sessions and CSRF rejection.

final class FormEditorBootstrapTest extends \PHPUnit\Framework\TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $root = $this->root = sys_get_temp_dir() . '/saldi-editor-start-' . bin2hex(random_bytes(8));
        mkdir($root . '/systemdata', 0700, true);
        mkdir($root . '/includes', 0700);
        copy(__DIR__ . '/../../systemdata/formeditor.php', $root . '/systemdata/formeditor.php');
        file_put_contents($root . '/includes/connect.php', '<?php /* Isolated bootstrap: no live database. */');
        file_put_contents($root . '/includes/online.php', <<<'PHP'
        <?php
        if (getenv('FE_TEST_CASE') === 'expired') {
            http_response_code(401);
            echo 'session-expired';
            exit;
        }
        $sprog_id = (int) getenv('FE_TEST_LANGUAGE');
        $_SESSION['text_7012_' . $sprog_id] = [1 => 'Formulareditor', 2 => 'Form editor', 3 => 'Skjemaeditor'][$sprog_id];
        PHP
        );
        // Load the real helpers only when the production entry point includes them.
        file_put_contents($root . '/includes/std_func.php', '<?php require ' . var_export(realpath(__DIR__ . '/../../includes/std_func.php'), true) . ';');
        file_put_contents($root . '/includes/topline_settings.php', <<<'PHP'
        <?php
        echo json_encode(['title' => $title, 'language' => $sprog_id, 'permission' => $permission_key]);
        exit;
        PHP
        );
        file_put_contents($root . '/run.php', <<<'PHP'
        <?php
        error_reporting(E_ALL);
        set_error_handler(static function ($severity, $message, $file, $line) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        session_save_path(__DIR__);
        if (getenv('FE_TEST_CASE') === 'csrf') $_GET['fe_action'] = 'save';
        require __DIR__ . '/systemdata/formeditor.php';
        PHP
        );
    }

    /** Run the actual editor in a fresh PHP process, with isolated session/database boundaries. */
    private function runEditor(string $case, int $language): string
    {
        $root = $this->root;
        $env = getenv();
        $env['FE_TEST_CASE'] = $case;
        $env['FE_TEST_LANGUAGE'] = (string) $language;
        $process = proc_open([PHP_BINARY, $root . '/run.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root . '/systemdata', $env);
        if (!is_resource($process)) throw new RuntimeException('Cannot start PHP bootstrap test.');
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || $errors !== '') {
            throw new RuntimeException("Editor bootstrap failed ($case/$language): $output $errors");
        }
        return $output;
    }

    public function testEditorStartsWithTheAuthenticatedSessionLanguage(): void
    {
        foreach ([1 => 'Formulareditor', 2 => 'Form editor', 3 => 'Skjemaeditor'] as $language => $title) {
            $result = json_decode($this->runEditor('page', $language), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(['title' => $title, 'language' => $language, 'permission' => 'system.indstillinger'], $result);
        }
    }

    public function testExpiredSessionReachesTheLoginGate(): void
    {
        $this->assertSame('session-expired', $this->runEditor('expired', 1));
    }

    public function testSaveWithoutCsrfIsRejectedBeforeBootstrap(): void
    {
        $this->assertSame(['ok' => false, 'error' => 'csrf'], json_decode($this->runEditor('csrf', 1), true));
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
    }
}
