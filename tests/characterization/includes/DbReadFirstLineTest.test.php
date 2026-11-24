<?php
// 20261124 CL/SZ Cover db_read_first_line() in includes/db_query.php: a missing, empty or unreadable file gives the default instead of a TypeError from fgets() (MB-142).
// 20261124 CL/SZ Cover a directory at the file's path; unlike the chmod cases it also runs as root, so CI runs it.
// 20261124 CL/SZ Cover db_error_mail_last_sent(): no stamp mails, a readable stamp gives its time, an unreadable stamp or a directory counts as mailed now.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/db_query.php';

final class DbReadFirstLineTest extends TestCase
{
    private string $dir;

    /** Prepare a private folder for the stamp files. */
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/saldi_mailstamp_' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0700);
    }

    /** Remove only this test's files. */
    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            if (is_dir($file)) {
                rmdir($file);
                continue;
            }
            chmod($file, 0600);
            unlink($file);
        }
        rmdir($this->dir);
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

    /** MB-142 review: a stamp the web user can't read can't limit the mail, so it counts as mailed now. */
    public function testMailStampUnreadableCountsAsMailedNow(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root reads any file, so the unreadable case cannot be set up');
        }
        $path = $this->dir . '/selectfejl.txt';
        file_put_contents($path, "1700000000\n");
        chmod($path, 0000);
        $before = (int) date("U");
        $result = (int) db_error_mail_last_sent($path);
        $this->assertGreaterThanOrEqual($before, $result);
        $this->assertLessThanOrEqual((int) date("U"), $result);
    }

    /** Same rule for a directory at the stamp path; this case also runs as root, so CI runs it. */
    public function testMailStampDirectoryCountsAsMailedNow(): void
    {
        $path = $this->dir . '/opdatfejl.txt';
        mkdir($path);
        $before = (int) date("U");
        $result = (int) db_error_mail_last_sent($path);
        $this->assertGreaterThanOrEqual($before, $result);
        $this->assertLessThanOrEqual((int) date("U"), $result);
    }
}
