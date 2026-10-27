<?php
// 20260910 CL/NTR Cover FileReservation: suffixing, sibling extensions, discard, overwrite-in-place,
//                  and a real multi-process race on the same base name (SST-776 follow-up).

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/docsIncludes/FileReservation.php';

final class FileReservationTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/filereservation_' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->dir) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                unlink($this->dir . '/' . $f);
            }
        }
        rmdir($this->dir);
    }

    public function testReservesTheBaseNameWhenFree(): void
    {
        $res = FileReservation::reserve($this->dir, 'scan', 'pdf');

        self::assertNotNull($res);
        self::assertSame('scan', $res->baseName());
        self::assertSame('pdf', $res->ext());
        self::assertSame($this->dir . '/scan.pdf', $res->path());
        self::assertFileExists($res->path());
        self::assertSame(0, filesize($res->path()));
    }

    public function testSuffixesWhenTheBaseNameIsTaken(): void
    {
        touch($this->dir . '/scan.pdf');
        touch($this->dir . '/scan_1.pdf');

        $res = FileReservation::reserve($this->dir, 'scan', 'pdf');

        self::assertSame('scan_2', $res->baseName());
        self::assertFileExists($this->dir . '/scan_2.pdf');
    }

    public function testSiblingExtensionsCountAsTaken(): void
    {
        touch($this->dir . '/scan.jpg');
        touch($this->dir . '/scan_1.info');

        $res = FileReservation::reserve($this->dir, 'scan', 'pdf', ['jpg', '.info']);

        self::assertSame('scan_2', $res->baseName());
        self::assertSame($this->dir . '/scan_2.jpg', $res->siblingPath('jpg'));
        self::assertSame($this->dir . '/scan_2.info', $res->siblingPath('.info'));
    }

    public function testSuccessiveReservationsNeverCollide(): void
    {
        $names = [];
        for ($i = 0; $i < 5; $i++) {
            $names[] = FileReservation::reserve($this->dir, 'scan', 'pdf')->baseName();
        }

        self::assertSame(['scan', 'scan_1', 'scan_2', 'scan_3', 'scan_4'], $names);
    }

    public function testNormalisesDotsAndTrailingSlash(): void
    {
        $res = FileReservation::reserve($this->dir . '/', 'scan', '.pdf');

        self::assertSame($this->dir . '/scan.pdf', $res->path());
        self::assertSame($this->dir, $res->dir());
    }

    public function testPlaceholderCanBeOverwrittenInPlace(): void
    {
        $res = FileReservation::reserve($this->dir, 'scan', 'pdf');
        $src = $this->dir . '/incoming.tmp';
        file_put_contents($src, 'payload');

        self::assertTrue(rename($src, $res->path()));
        self::assertSame('payload', file_get_contents($res->path()));
    }

    public function testDiscardRemovesThePlaceholder(): void
    {
        $res = FileReservation::reserve($this->dir, 'scan', 'pdf');

        self::assertTrue($res->discard());
        self::assertFileDoesNotExist($res->path());
        self::assertFalse($res->discard());
    }

    public function testReturnsNullForUnwritableDirectory(): void
    {
        if (posix_geteuid() === 0) {
            self::markTestSkipped('root ignores directory permissions');
        }
        chmod($this->dir, 0555);
        try {
            self::assertNull(FileReservation::reserve($this->dir, 'scan', 'pdf'));
        } finally {
            chmod($this->dir, 0755);
        }
    }

    public function testReturnsNullForMissingDirectory(): void
    {
        self::assertNull(FileReservation::reserve($this->dir . '/nope', 'scan', 'pdf'));
    }

    public function testReturnsNullWhenAttemptsAreExhausted(): void
    {
        touch($this->dir . '/scan.pdf');
        touch($this->dir . '/scan_1.pdf');

        self::assertNull(FileReservation::reserve($this->dir, 'scan', 'pdf', [], '_', 2));
    }

    public function testExistingMarkerCountsAsTaken(): void
    {
        touch($this->dir . '/.scan.reserving');

        $res = FileReservation::reserve($this->dir, 'scan', 'pdf');

        self::assertSame('scan_1', $res->baseName());
        self::assertFileExists($this->dir . '/.scan.reserving', 'a foreign marker must not be removed');
        self::assertFileDoesNotExist($this->dir . '/.scan_1.reserving', 'own marker is removed after reserving');
    }

    /**
     * Spawns several PHP processes that all reserve "scan.pdf" in the same directory at the
     * same moment. Every process must come away with a distinct name.
     */
    public function testConcurrentProcessesGetDistinctNames(): void
    {
        $names = $this->reserveConcurrently(array_fill(0, 12, 'pdf'));

        self::assertCount(12, array_unique($names), 'two workers reserved the same name: ' . implode(', ', $names));
        self::assertCount(12, glob($this->dir . '/scan*.pdf'));
    }

    /**
     * Same race, but the workers ask for different extensions of the same base name. The
     * sibling rule alone is check-then-act, so without the marker two workers could end up
     * with "scan.pdf" and "scan.jpg". Every base name must still be unique.
     */
    public function testConcurrentProcessesWithDifferentExtensionsGetDistinctBaseNames(): void
    {
        $exts = ['pdf', 'jpg', 'png', 'pdf', 'jpg', 'png', 'pdf', 'jpg', 'png', 'pdf', 'jpg', 'png'];

        $names = $this->reserveConcurrently($exts);

        self::assertCount(count($exts), array_unique($names), 'two workers reserved the same base name: ' . implode(', ', $names));
        self::assertSame([], glob($this->dir . '/.*.reserving'), 'no markers left behind');
    }

    /**
     * Runs one PHP process per entry in $exts, each reserving base name "scan" with that
     * extension, released simultaneously. Returns the base names they were given.
     *
     * @param string[] $exts
     * @return string[]
     */
    private function reserveConcurrently(array $exts): array
    {
        $go = $this->dir . '/go';
        $scripts = [];
        foreach ($exts as $i => $ext) {
            $scripts[$i] = $this->dir . "/worker$i.php";
            file_put_contents($scripts[$i], '<?php
                require ' . var_export(dirname(__DIR__, 3) . '/includes/docsIncludes/FileReservation.php', true) . ';
                while (!file_exists(' . var_export($go, true) . ')) { usleep(200); }
                $r = FileReservation::reserve(' . var_export($this->dir, true) . ', "scan", ' . var_export($ext, true) . ', ["pdf", "jpg", "png", "info"]);
                echo $r === null ? "NULL" : $r->baseName();
            ');
        }

        $procs = [];
        $pipes = [];
        foreach ($scripts as $i => $script) {
            $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $procs[$i] = proc_open(PHP_BINARY . ' ' . escapeshellarg($script), $spec, $pipes[$i]);
            self::assertIsResource($procs[$i]);
        }
        usleep(200000); // let every worker reach the spin-wait before releasing them together
        touch($go);

        $names = [];
        foreach ($procs as $i => $proc) {
            $names[] = trim(stream_get_contents($pipes[$i][1]));
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            proc_close($proc);
            unlink($scripts[$i]);
        }
        unlink($go);

        self::assertNotContains('NULL', $names);
        return $names;
    }
}
