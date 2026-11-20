<?php
// 20261120 CDX/NTR Exercise the print page's A4 preparation with real PDF conversion and merging.

use PHPUnit\Framework\TestCase;

final class PrintBackgroundNormalizationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        foreach (array('gs', 'pdfinfo', 'pdftk', 'pdftotext') as $tool) {
            exec('command -v ' . escapeshellarg($tool), $output, $status);
            if ($status !== 0) {
                self::markTestSkipped("PDF integration test needs $tool");
            }
        }
        $this->directory = sys_get_temp_dir() . '/print background ' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        if (!isset($this->directory)) {
            return;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
    }

    /** @param array<string> $arguments */
    private function command(array $arguments): string
    {
        exec(implode(' ', array_map('escapeshellarg', $arguments)) . ' 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
        return implode("\n", $output);
    }

    /**
     * Create a synthetic PDF; the non-A4 size matches an image printed to PDF.
     * @param array<string> $labels One marker per page.
     */
    private function pdf(string $name, int $width, int $height, array $labels = array('BACKGROUND MARKER')): string
    {
        $source = $this->directory . '/' . $name . '.ps';
        $fontSize = $width > 1000 ? 72 : 20;
        $postscript = "%!PS\n<< /PageSize [$width $height] >> setpagedevice\n/Helvetica findfont $fontSize scalefont setfont\n";
        foreach ($labels as $label) {
            $postscript .= "72 200 moveto ($label) show\nshowpage\n";
        }
        file_put_contents($source, $postscript);
        $pdf = $this->directory . '/' . $name . '.pdf';
        $this->command(array('gs', '-q', '-dBATCH', '-dNOPAUSE', '-sDEVICE=pdfwrite', '-sOutputFile=' . $pdf, $source));
        return $pdf;
    }

    /**
     * Run the actual preparation block, without the page's session/DB/exit plumbing.
     * Legacy page tests use this seam because including the page exits the test process.
     */
    private function prepare(string $background): string
    {
        $source = file_get_contents(__DIR__ . '/../../../includes/udskriv.php');
        $start = strpos($source, '// Self-heal non-A4 letterheads.');
        self::assertNotFalse($start);
        $end = strpos($source, '$out = "../temp/"', $start);
        self::assertNotFalse($end);
        $bg_fil = $background;
        clearstatcache();
        set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            eval(substr($source, $start, $end - $start));
        } finally {
            restore_error_handler();
        }
        return $bg_fil;
    }

    public function testOversizedBackgroundBecomesAMergeableA4PdfWithoutChangingTheUpload(): void
    {
        $background = $this->pdf('uploaded background', 2550, 3300);
        $original = hash_file('sha256', $background);
        $prepared = $this->prepare($background);

        self::assertSame('%PDF-', file_get_contents($prepared, false, null, 0, 5));
        self::assertStringContainsString('(A4)', $this->command(array('pdfinfo', $prepared)));
        self::assertSame($original, hash_file('sha256', $background));
        $invoice = $this->pdf('invoice', 595, 842, array('INVOICE MARKER'));
        $merged = $this->directory . '/merged invoice.pdf';
        $this->command(array('pdftk', $invoice, 'background', $prepared, 'output', $merged));
        $text = $this->command(array('pdftotext', $merged, '-'));
        self::assertStringContainsString('BACKGROUND MARKER', $text);
        self::assertStringContainsString('INVOICE MARKER', $text);
    }

    public function testFreshPngCacheLeftByTheBugIsRebuilt(): void
    {
        $background = $this->pdf('background', 2550, 3300);
        $cache = $this->directory . '/background.a4.pdf';
        file_put_contents($cache, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jxRkAAAAASUVORK5CYII='));
        touch($background, time() - 10);

        $prepared = $this->prepare($background);

        self::assertSame($cache, $prepared);
        self::assertSame('%PDF-', file_get_contents($prepared, false, null, 0, 5));
        self::assertStringContainsString('(A4)', $this->command(array('pdfinfo', $prepared)));
        self::assertStringContainsString('BACKGROUND MARKER', $this->command(array('pdftotext', $prepared, '-')));
    }

    public function testA4UploadIsUsedWithoutCreatingACache(): void
    {
        $background = $this->pdf('background', 595, 842);
        $hash = hash_file('sha256', $background);

        self::assertSame($background, $this->prepare($background));
        self::assertSame($hash, hash_file('sha256', $background));
        self::assertFileDoesNotExist($this->directory . '/background.a4.pdf');
    }

    public function testValidCurrentCacheIsReused(): void
    {
        $background = $this->pdf('background', 2550, 3300);
        $cache = $this->pdf('background.a4', 595, 842);
        touch($background, time() - 10);
        $hash = hash_file('sha256', $cache);

        self::assertSame($cache, $this->prepare($background));
        self::assertSame($hash, hash_file('sha256', $cache));
    }

    public function testOnlyTheFirstBackgroundPageIsPrepared(): void
    {
        $background = $this->pdf('background', 2550, 3300, array('FIRST BACKGROUND PAGE', 'SECOND BACKGROUND PAGE'));
        $prepared = $this->prepare($background);

        self::assertMatchesRegularExpression('/^Pages:\s+1$/m', $this->command(array('pdfinfo', $prepared)));
        $text = $this->command(array('pdftotext', $prepared, '-'));
        self::assertStringContainsString('FIRST BACKGROUND PAGE', $text);
        self::assertStringNotContainsString('SECOND BACKGROUND PAGE', $text);
    }

    public function testFailedConversionKeepsTheUploadAndDoesNotUseAnOutdatedCache(): void
    {
        $background = $this->pdf('background', 2550, 3300);
        $cache = $this->pdf('background.a4', 595, 842);
        touch($cache, time() - 20);
        touch($background, time() - 10);
        $uploadHash = hash_file('sha256', $background);
        $cacheHash = hash_file('sha256', $cache);
        mkdir($this->directory . '/bin');
        $ghostscript = $this->directory . '/bin/gs';
        file_put_contents($ghostscript, "#!/bin/sh\nfor argument do\ncase \"\$argument\" in -sOutputFile=*) printf 'partial conversion' > \"\${argument#-sOutputFile=}\";; esac\ndone\necho 'simulated converter failure' >&2\nexit 9\n");
        chmod($ghostscript, 0700);
        $oldPath = getenv('PATH');
        $oldErrorLog = ini_get('error_log');
        $errorLog = $this->directory . '/conversion-errors.log';
        putenv('PATH=' . $this->directory . '/bin:' . $oldPath);
        ini_set('error_log', $errorLog);
        try {
            self::assertSame($background, $this->prepare($background));
        } finally {
            putenv('PATH=' . $oldPath);
            ini_set('error_log', $oldErrorLog);
        }
        self::assertSame($uploadHash, hash_file('sha256', $background));
        self::assertSame($cacheHash, hash_file('sha256', $cache));
        self::assertSame(array(), glob($cache . '.*.new'));
        self::assertStringContainsString('simulated converter failure', file_get_contents($errorLog));
    }
}
