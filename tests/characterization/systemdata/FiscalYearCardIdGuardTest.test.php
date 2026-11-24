<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The year card (systemdata/regnskabskort.php) takes its Gem/opdatér POST without an "id" warning (testlab round 1,
 * php-log, 2026-11-05).
 *
 * The card's form posts to regnskabskort.php without ?id. a1a83086 added a guard that sends a GET without an id to
 * Indstillinger » Virksomhed » Regnskabsår, and it read the id as if_isset($_GET['id'], 0): the key is read before the
 * call, so every save logged Undefined array key "id" at the guard, and once more at $id=if_isset($_GET['id']) above
 * the save. In the lab the candidate's save from regnskabskort.php?id=2138 logged both, the base's only the second.
 *
 * Pins: a save POST without ?id gets past the guard and both id reads to the save with no warning, a GET without an id
 * is redirected before the page prints anything, and a GET with one draws the card. The page runs in a child process
 * against stub includes and the real includes/std_func.php, so it never reaches a database: the first
 * db_escape_string() (the save) or db_select() (drawing the card) ends the run.
 *
 * History:
 * 20261105 CL/LH Created.
 */
final class FiscalYearCardIdGuardTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        $this->fixture = sys_get_temp_dir() . '/saldi-regnskabskort-' . bin2hex(random_bytes(8));
        mkdir($this->fixture . '/systemdata', 0700, true);
        mkdir($this->fixture . '/includes', 0700);
        $stdFunc = var_export(dirname(__DIR__, 3) . '/includes/std_func.php', true);
        // The page includes these by bare relative paths, so they resolve from the child's working directory.
        $stubs = [
            'includes/connect.php' => '<?php
                function findtekst($textId, $languageID) { return substr((string) $textId, strpos((string) $textId, "|") + 1); }
                function db_escape_string($s) { exit("[save]"); }
                function db_select($q, $where = "") { exit("[query]"); }
                function db_fetch_array($q) { return false; }',
            'includes/online.php' => '<?php $sprog_id = 1; $menu = "S";',
            'includes/std_func.php' => '<?php require_once ' . $stdFunc . ';',
            'includes/genberegn.php' => '<?php',
            'includes/topline_settings.php' => '<?php $buttonStyle = "";',
            'systemdata/top.php' => '<?php echo "[top]";',
        ];
        foreach ($stubs as $path => $code) {
            file_put_contents($this->fixture . '/' . $path, $code);
        }
    }

    protected function tearDown(): void
    {
        foreach (array_merge(glob($this->fixture . '/sess_*'), glob($this->fixture . '/includes/*.php'), glob($this->fixture . '/systemdata/*.php')) as $file) {
            unlink($file);
        }
        rmdir($this->fixture . '/includes');
        rmdir($this->fixture . '/systemdata');
        rmdir($this->fixture);
    }

    /** @return array<string, array{0: string, 1: array<string, string>, 2: array<string, string>, 3: string}> */
    public static function requests(): array
    {
        $save = ['id' => '44', 'beskrivelse' => '2026', 'kodenr' => '17', 'kode' => ''];
        return [
            'Gem/opdatér posts without ?id' => ['POST', [], $save, '[save]'],
            'Gem/opdatér posts with ?id' => ['POST', ['id' => '44'], $save, '[save]'],
            'a GET without an id is redirected' => ['GET', [], [], ''],
            'a GET with id 0 is redirected' => ['GET', ['id' => '0'], [], ''],
            'a GET with an id draws the card' => ['GET', ['id' => '44'], [], '[query]'],
        ];
    }

    /**
     * @param array<string, string> $get
     * @param array<string, string> $post
     */
    #[DataProvider('requests')]
    public function testTheCardReadsItsIdWithoutAWarning(string $method, array $get, array $post, string $endsWith): void
    {
        // Any warning the page raises, as the server log would record it (so not one silenced with @), fails the run.
        $script = 'set_error_handler(static function ($severity, $message, $file, $line) {'
            . 'if (!(error_reporting() & $severity)) { return false; }'
            . 'throw new ErrorException($message, 0, $severity, $file, $line); });'
            . '$_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ';'
            . '$_GET = ' . var_export($get, true) . ';'
            . '$_POST = ' . var_export($post, true) . ';'
            . 'require ' . var_export(dirname(__DIR__, 3) . '/systemdata/regnskabskort.php', true) . ';';
        $process = proc_open(
            [PHP_BINARY, '-d', 'session.save_path=' . $this->fixture, '-d', 'include_path=.', '-d', 'display_errors=stderr', '-r', $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->fixture . '/systemdata'
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(0, proc_close($process), $stderr . $stdout);
        self::assertSame('', $stderr);
        if ($endsWith === '') {
            self::assertSame('', $stdout, 'the redirect exits before the page prints anything');
        } else {
            self::assertStringContainsString('[top]', $stdout);
            self::assertStringEndsWith($endsWith, $stdout);
        }
    }
}
