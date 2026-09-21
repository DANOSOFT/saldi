<?php
// 20260920 CDX/LH Keep finance precision, journal navigation and preservation regressions in normal PHPUnit discovery.
// 20260921 CDX/LH Exercise atomic invoice/payment split and concurrent replay in the native gate.
// 20260921 CDX/LH Surface missing fixtures as skips and reject partially skipped child regressions.
use PHPUnit\Framework\TestCase;

final class FinanceRegressionTest extends TestCase
{
    private function runFinanceScript(string $name, bool $requiresPostgres = false): void
    {
        $environment = getenv();
        $dsn = getenv('SALDI_CHAR_PG_DSN') ?: getenv('SALDI_TEST_DSN');
        if ($requiresPostgres && (!extension_loaded('pgsql') || !$dsn)) {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN and enable pgsql for the isolated PostgreSQL fixture.');
        }
        if ($dsn) {
            $environment['SALDI_CHAR_PG_DSN'] = $dsn;
            $environment['SALDI_TEST_DSN'] = $dsn;
        }
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/' . $name],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        self::assertSame(0, $status, $output . $errors);
        self::assertSame('', $errors, $errors);
        self::assertStringContainsString('PASS:', $output);
        self::assertStringNotContainsString('SKIP:', $output);
    }

    public function testOpenpostSplitAtomicity(): void
    {
        $this->runFinanceScript('test_openpost_split.php', true);
    }

    public function testSalesPostingPrecision(): void
    {
        $this->runFinanceScript('test_sales_posting_precision.php');
    }

    public function testJournalRedirectContext(): void
    {
        $this->runFinanceScript('test_journal_save_redirect.php');
    }

    public function testJournalSaveHttpLifecycle(): void
    {
        $this->runFinanceScript('test_journal_save_lifecycle.php');
    }

    public function testDraftPreservationAndSettlement(): void
    {
        $dsn = getenv('SALDI_CHAR_PG_DSN') ?: getenv('SALDI_TEST_DSN');
        if ($dsn && (!extension_loaded('pgsql') || !extension_loaded('pdo_pgsql'))) {
            self::markTestSkipped('The configured PostgreSQL fixture requires pgsql and pdo_pgsql.');
        }
        if (!$dsn) {
            $driver = explode(':', getenv('SALDI_CHAR_DSN') ?: 'sqlite::memory:', 2)[0];
            if (!class_exists(PDO::class) || !in_array($driver, PDO::getAvailableDrivers(), true)) {
                self::markTestSkipped('Enable the configured PDO driver (pdo_sqlite by default) for draft/settlement checks.');
            }
        }
        $this->runFinanceScript('test_journal_preservation_and_settlement.php');
    }
    public function testApiDebtorAndOrderIntegrity(): void
    {
        $this->runFinanceScript('test_api_record_integrity.php', true);
    }
    public function testShopOrderAtomicityAndConcurrentReplay(): void
    {
        $this->runFinanceScript('test_shop_order_transaction.php', true);
    }
}
