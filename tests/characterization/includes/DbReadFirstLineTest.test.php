<?php
// 20261124 CL/SZ Cover db_read_first_line() in includes/db_query.php: a missing, empty or unreadable file gives the default instead of a TypeError from fgets() (MB-142).
// 20261124 CL/SZ Cover a directory at the file's path; unlike the chmod cases it also runs as root, so CI runs it.
// 20261124 CL/SZ Cover db_error_mail_last_sent(): no stamp mails, a readable stamp gives its time, an unreadable stamp or a directory counts as mailed now.
// 20261124 CL/SZ Cover a stamp that can be read but not rewritten: it counts as mailed now.
// 20261124 CL/SZ A stamp this account owns in a trusted folder is repaired and mails, as the stamp write did on master; a stamp in an untrusted folder sends no mail and logs why.
// 20261124 CL/SZ serverLog(): check the log file exists instead of hiding the warning with @ (MB-142 review).

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/db_query.php';

final class DbReadFirstLineTest extends TestCase
{
    private string $dir;
    private int $originalUmask;
    private string|false $originalGroup;
    private string $originalErrorLog;

    /** Prepare a private, trusted folder for the stamp files (as SharedFileTest does) and catch the server log. */
    protected function setUp(): void
    {
        $this->originalUmask = umask(0077);
        $this->originalGroup = getenv('SALDI_SHARED_FILE_GROUP');
        putenv('SALDI_SHARED_FILE_GROUP=' . posix_getgrgid(posix_getegid())['name']);
        $this->dir = sys_get_temp_dir() . '/saldi_mailstamp_' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0700);
        $this->originalErrorLog = ini_get('error_log');
        ini_set('error_log', $this->dir . '/diagnostics.log');
    }

    /** Restore process settings and remove only this test's files. */
    protected function tearDown(): void
    {
        umask($this->originalUmask);
        ini_set('error_log', $this->originalErrorLog);
        putenv($this->originalGroup === false ? 'SALDI_SHARED_FILE_GROUP' : 'SALDI_SHARED_FILE_GROUP=' . $this->originalGroup);
        $this->remove($this->dir);
    }

    private function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            chmod($path, 0700);
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->remove($path . '/' . $entry);
                }
            }
            rmdir($path);
            return;
        }
        chmod($path, 0600);
        unlink($path);
    }

    /** A folder others can write to, which sharedFile.php does not trust, so it repairs nothing there. */
    private function untrustedDir(): string
    {
        $dir = $this->dir . '/untrusted';
        mkdir($dir);
        chmod($dir, 0777);
        return $dir;
    }

    private function serverLog(): string
    {
        $log = $this->dir . '/diagnostics.log';
        return is_file($log) ? (string) file_get_contents($log) : '';
    }

    private function assertMailedNow(callable $call): void
    {
        $before = (int) date("U");
        $result = (int) $call();
        $this->assertGreaterThanOrEqual($before, $result);
        $this->assertLessThanOrEqual((int) date("U"), $result);
        $this->assertStringContainsString('so no error mail is sent', $this->serverLog());
    }

    public function testMissingStampGivesZero(): void
    {
        $this->assertSame(0, db_read_first_line($this->dir . '/modifyfejl.txt'));
    }

    public function testReadableStampGivesItsFirstLine(): void
    {
        file_put_contents($this->dir . '/selectfejl.txt', "1764000000\n");
        $this->assertSame('1764000000', db_read_first_line($this->dir . '/selectfejl.txt'));
    }

    public function testEmptyStampGivesZero(): void
    {
        touch($this->dir . '/opdatfejl.txt');
        $this->assertSame(0, db_read_first_line($this->dir . '/opdatfejl.txt'));
    }

    /** The MB-142 case: a stamp another account created that this user can't read. */
    public function testUnreadableStampGivesZeroInsteadOfTypeError(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root reads any file, so the unreadable case cannot be set up');
        }
        $path = $this->dir . '/modifyfejl.txt';
        file_put_contents($path, "1764000000\n");
        chmod($path, 0000);
        $this->assertSame(0, db_read_first_line($path));
    }

    /** lasterror.txt is read with '' as default; an unreadable one must not stop db_select(). */
    public function testUnreadableFileGivesTheCallersDefault(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root reads any file, so the unreadable case cannot be set up');
        }
        $path = $this->dir . '/lasterror.txt';
        file_put_contents($path, "ERROR: relation does not exist_10:15\n");
        chmod($path, 0000);
        $this->assertSame('', db_read_first_line($path, ''));
    }

    /** A directory where the stamp should be reads like a missing file, with no fgets() notice. */
    public function testDirectoryAtThePathGivesTheDefault(): void
    {
        $path = $this->dir . '/selectfejl.txt';
        mkdir($path);
        $notices = [];
        set_error_handler(function (int $no, string $msg) use (&$notices): bool {
            $notices[] = $msg;
            return true;
        });
        try {
            $result = db_read_first_line($path);
        } finally {
            restore_error_handler();
        }
        $this->assertSame(0, $result);
        $this->assertSame([], $notices);
    }

    public function testMailStampMissingGivesZeroSoTheMailGoesOut(): void
    {
        $this->assertSame(0, db_error_mail_last_sent($this->dir . '/modifyfejl.txt'));
    }

    public function testMailStampReadableGivesItsTime(): void
    {
        file_put_contents($this->dir . '/modifyfejl.txt', "1764000000\n");
        $this->assertSame('1764000000', db_error_mail_last_sent($this->dir . '/modifyfejl.txt'));
    }

    /** MB-142 review: a stamp the web user can't read and can't repair can't limit the mail, so it counts as mailed now. */
    public function testMailStampUnreadableCountsAsMailedNow(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root reads any file, so the unreadable case cannot be set up');
        }
        $path = $this->untrustedDir() . '/selectfejl.txt';
        file_put_contents($path, "1700000000\n");
        chmod($path, 0000);
        $this->assertMailedNow(fn() => db_error_mail_last_sent($path));
    }

    /** Same rule for a directory at the stamp path; this case also runs as root, so CI runs it. */
    public function testMailStampDirectoryCountsAsMailedNow(): void
    {
        $path = $this->dir . '/opdatfejl.txt';
        mkdir($path);
        $this->assertMailedNow(fn() => db_error_mail_last_sent($path));
    }

    /** A stamp that can be read but not rewritten, and can't be repaired, can't limit the mail either. */
    public function testMailStampReadOnlyCountsAsMailedNow(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root writes any file, so the read-only case cannot be set up');
        }
        $path = $this->untrustedDir() . '/modifyfejl.txt';
        file_put_contents($path, "1700000000\n");
        chmod($path, 0444);
        $this->assertMailedNow(fn() => db_error_mail_last_sent($path));
    }

    /**
     * MB-142 review: a read-only stamp this account owns in a trusted temp folder is repaired to 0660, as the
     * stamp write did on master, so the mail limit keeps working instead of turning the mail off for good.
     * As root the stamp is writable anyway, so CI checks the result and a local run also checks the repair.
     */
    public function testMailStampReadOnlyButOwnedIsRepairedAndKeepsItsTime(): void
    {
        $path = $this->dir . '/modifyfejl.txt';
        file_put_contents($path, "1700000000\n");
        chmod($path, 0444);
        $this->assertSame('1700000000', db_error_mail_last_sent($path));
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            clearstatcache(true, $path);
            $this->assertSame(0660, fileperms($path) & 0777);
        }
        $this->assertStringNotContainsString('so no error mail is sent', $this->serverLog());
    }
}
