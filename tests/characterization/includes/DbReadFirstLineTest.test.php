<?php
// 20261124 CL/SZ Cover db_read_first_line() in includes/db_query.php: a missing, empty or unreadable file gives the default instead of a TypeError from fgets() (MB-142).

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
}
