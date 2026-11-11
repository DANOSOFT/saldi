<?php
// 20261103 CL/MMR SST-929 Deleting an empty journal removes only empty folders, keeps what isn't the journal's own, and raises no warning.
// 20261104 CL/MMR SST-929 A warning from unlink(), scandir() or rmdir() under the throwing handler is a failed step, not an exception (CodeRabbit on PR #1004).
// 20261104 CL/MMR SST-929 The caught warning is logged, and an error of another type still reaches the outer handler (Codex round 2).

// 20261111 CDX/NTR Leave teardown untouched when setup skipped before initializing its state.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * journalDocumentsDelete() runs inside the delete in finans/kladdeliste.php, whose error handler turns every warning
 * into an ErrorException. The tests install the same handler, so any warning fails them.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class JournalDocumentsDeleteRegressionTest extends TestCase
{
    private const DB = 'chartest_sst929';
    private const KLADDE_ID = 210;

    private PDO $db;
    private string $root;
    private string $cwd;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the isolated documents table.');
        }
        require_once __DIR__ . '/support/journal_documents_database.php';
        require_once dirname(__DIR__, 3) . '/finans/kassekladde_includes/journalDocumentsFunc.php';
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['journalDocumentsTestDb'] = $this->db;
        $this->db->exec('CREATE TABLE documents (id INTEGER, filename TEXT, filepath TEXT, source TEXT, source_id INTEGER, timestamp TEXT)');

        // The journal's folders are relative to finans/, so the test runs from a finans/ folder of its own
        $this->root = sys_get_temp_dir() . '/sst929_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/finans', 0777, true);
        $this->cwd = getcwd();
        chdir($this->root . '/finans');

        set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
    }

    protected function tearDown(): void
    {
        if (!isset($this->cwd)) {
            return;
        }
        restore_error_handler();
        chdir($this->cwd);
        $this->removeTree($this->root);
    }

    public function testFolderLeftByALineDocumentDoesNotStopTheDeletion(): void
    {
        // Slet and Flyt til pulje on a line's document leave finance/<kladde_id>/<line id> behind
        $lineFolder = $this->kladdeFolder() . '/3400';
        mkdir($lineFolder, 0777, true);

        self::assertTrue(journalDocumentsDelete(self::DB, self::KLADDE_ID));
        self::assertDirectoryExists($lineFolder);
    }

    public function testFileLeftInALineFolderIsKept(): void
    {
        $lineFolder = $this->kladdeFolder() . '/3400';
        mkdir($lineFolder, 0777, true);
        file_put_contents($lineFolder . '/rest.pdf', "%PDF-1.4\n");

        self::assertTrue(journalDocumentsDelete(self::DB, self::KLADDE_ID));
        self::assertFileExists($lineFolder . '/rest.pdf');
    }

    public function testJournalWithoutAnyFolderIsDeletable(): void
    {
        self::assertTrue(journalDocumentsDelete(self::DB, self::KLADDE_ID));
        self::assertDirectoryDoesNotExist($this->kladdeFolder());
    }

    public function testJournalDocumentsAndTheEmptyFoldersAreRemoved(): void
    {
        $journalFolder = $this->journalFolder();
        $this->addJournalDocument(1, 'kontoudtog.pdf');
        // Rows that are not this journal's own documents
        $this->db->exec("INSERT INTO documents VALUES (2, 'linje.pdf', '/finance/210/3400', 'kassekladde', 210, '0')");
        $this->db->exec("INSERT INTO documents VALUES (3, 'andet.pdf', '/finance/211/journal', 'kladdeliste', 211, '0')");

        self::assertTrue(journalDocumentsDelete(self::DB, self::KLADDE_ID));
        self::assertFileDoesNotExist($journalFolder . '/kontoudtog.pdf');
        self::assertDirectoryDoesNotExist($journalFolder);
        self::assertDirectoryDoesNotExist($this->kladdeFolder());
        self::assertSame([2, 3], $this->documentIds());
    }

    public function testJournalDocumentGoesWhileTheLeftLineFolderStays(): void
    {
        $lineFolder = $this->kladdeFolder() . '/3400';
        $this->addJournalDocument(1, 'kontoudtog.pdf');
        mkdir($lineFolder, 0777, true);

        self::assertTrue(journalDocumentsDelete(self::DB, self::KLADDE_ID));
        self::assertDirectoryDoesNotExist($this->journalFolder());
        self::assertDirectoryExists($lineFolder);
        self::assertSame([], $this->documentIds());
    }

    public function testDocumentInAFolderThatCanNotBeWrittenKeepsItsRow(): void
    {
        $journalFolder = $this->journalFolder();
        $this->addJournalDocument(1, 'kontoudtog.pdf');
        chmod($journalFolder, 0555);
        if (is_writable($journalFolder)) {
            chmod($journalFolder, 0777);
            self::markTestSkipped('The folder stays writable for this user (root), so the case can not be set up.');
        }

        try {
            self::assertFalse(journalDocumentsDelete(self::DB, self::KLADDE_ID));
        } finally {
            chmod($journalFolder, 0777);
        }
        self::assertFileExists($journalFolder . '/kontoudtog.pdf');
        self::assertSame([1], $this->documentIds());
    }

    public function testAWarningInAFileStepIsAFailureAndNotAnException(): void
    {
        // CodeRabbit (PR #1004): with the handler of kladdeliste.php a warning threw, also behind @, and stopped the deletion
        $missing = $this->root . '/no-such-folder/kontoudtog.pdf';
        // the caught warnings go to the PHP error log, here a file of the test's own
        $log = $this->root . '/php-error.log';
        ini_set('error_log', $log);
        self::assertFalse(journalDocumentsQuietly(static fn () => unlink($missing)));
        self::assertFalse(journalDocumentsQuietly(static fn () => rmdir($missing)));
        self::assertTrue(journalDocumentsQuietly(static fn () => true), 'a step without a warning keeps its result');

        // the warning is kept in the PHP error log
        self::assertStringContainsString('journalDocuments: unlink(', (string)file_get_contents($log));

        // only warnings are caught: another error type reaches the outer (throwing) handler
        try {
            journalDocumentsQuietly(static fn () => trigger_error('not a file warning', E_USER_WARNING));
            self::fail('an E_USER_WARNING was swallowed');
        } catch (ErrorException $e) {
            self::assertSame(E_USER_WARNING, $e->getSeverity());
        }

        // the test's own throwing handler is back afterwards
        $this->expectException(ErrorException::class);
        unlink($missing);
    }

    private function journalFolder(): string
    {
        return journalDocumentsFolder(self::DB, self::KLADDE_ID);
    }

    private function kladdeFolder(): string
    {
        return dirname($this->journalFolder());
    }

    private function addJournalDocument(int $id, string $filename): void
    {
        $journalFolder = $this->journalFolder();
        if (!is_dir($journalFolder)) {
            mkdir($journalFolder, 0777, true);
        }
        file_put_contents($journalFolder . '/' . $filename, "%PDF-1.4\n");
        $insert = $this->db->prepare("INSERT INTO documents VALUES (?, ?, ?, 'kladdeliste', ?, '0')");
        $insert->execute([$id, $filename, journalDocumentsFilepath(self::KLADDE_ID), self::KLADDE_ID]);
    }

    /**
     * @return list<int>
     */
    private function documentIds(): array
    {
        return array_map('intval', $this->db->query('SELECT id FROM documents ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        chmod($path, 0777);
        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . '/' . $entry;
            is_dir($child) ? $this->removeTree($child) : unlink($child);
        }
        rmdir($path);
    }
}
