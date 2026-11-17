<?php
// 20261117 CDX/ASR Exercise real editor PDF composition without Poppler and preserve active files on failure.

require_once __DIR__ . '/../../includes/formEditorState.php';

final class FormEditorPdfTest extends \PHPUnit\Framework\TestCase
{
    private string $work;
    private string $cwd;
    private string $path;
    /** @var array{xa: int, ya: int, xb: int, yb: int} */
    private array $position = ['xa' => 20, 'ya' => 277, 'xb' => 30, 'yb' => 10];

    protected function setUp(): void
    {
        parent::setUp();
        if (!is_executable('/usr/bin/gs') || !is_executable('/usr/bin/convert')) {
            $this->markTestSkipped('Ghostscript and ImageMagick are required for the PDF integration tests.');
        }
        $this->cwd = getcwd();
        $this->path = (string) getenv('PATH');
        $this->work = sys_get_temp_dir() . '/saldi-editor-pdf-' . bin2hex(random_bytes(8));
        mkdir($this->work . '/systemdata', 0700, true);
        mkdir($this->work . '/logolib/1091', 0700, true);
        mkdir($this->work . '/bin', 0700);
        symlink('/usr/bin/gs', $this->work . '/bin/gs');
        symlink('/usr/bin/convert', $this->work . '/bin/convert');
        // Reproduce ssl3: Ghostscript and convert are installed, pdftoppm is not.
        putenv('PATH=' . $this->work . '/bin');
        chdir($this->work . '/systemdata');
        $this->command("convert -size 1654x2339 xc:white -units PixelsPerInch -density 200 ../logolib/1091/fe_logobase.pdf");
        copy('../logolib/1091/fe_logobase.pdf', '../logolib/1091/faktura_bg.pdf');
        $this->command('convert -size 30x10 xc:red ../logolib/1091/fe_logo.png');
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->work)) {
                chdir($this->cwd);
                putenv('PATH=' . $this->path);
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->work, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $file) {
                    $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }
                rmdir($this->work);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testPreviewUsesGhostscriptWithoutPopplerAndKeepsItsCacheOnFailure(): void
    {
        $this->assertTrue(fe_pdf_png('../logolib/1091/faktura_bg.pdf', $this->work . '/preview.png', 100));
        $size = getimagesize($this->work . '/preview.png');
        $this->assertEqualsWithDelta(827, $size[0], 2);
        $this->assertEqualsWithDelta(1170, $size[1], 2);
        $before = hash_file('sha256', $this->work . '/preview.png');
        file_put_contents($this->work . '/invalid.pdf', 'broken PDF');
        $this->assertFalse(fe_pdf_png($this->work . '/invalid.pdf', $this->work . '/preview.png', 100));
        $this->assertSame($before, hash_file('sha256', $this->work . '/preview.png'));
        $this->assertSame([], glob($this->work . '/.fe-preview-*'));
    }

    public function testLogoIsVisibleAndMovingItDoesNotLeaveThePreviousStamp(): void
    {
        $this->assertTrue(fe_write_logo_background('../logolib/1091', '../logolib/1091/faktura_bg.pdf', $this->position));
        $this->assertTrue(fe_pdf_png('../logolib/1091/faktura_bg.pdf', $this->work . '/first.png', 100));
        $this->assertRedAt($this->work . '/first.png', 25, 25);
        $this->position['xa'] = 80;
        $this->assertTrue(fe_write_logo_background('../logolib/1091', '../logolib/1091/faktura_bg.pdf', $this->position));
        $this->assertTrue(fe_pdf_png('../logolib/1091/faktura_bg.pdf', $this->work . '/second.png', 100));
        $this->assertRedAt($this->work . '/second.png', 85, 25);
        $pixel = $this->pixel($this->work . '/second.png', 25, 25);
        $this->assertGreaterThan(0.95, min($pixel), 'The previous logo position is white again.');
        $meta = json_decode(file_get_contents('../logolib/1091/fe_logo_meta.json'), true);
        $this->assertSame(md5_file('../logolib/1091/faktura_bg.pdf'), $meta['out_md5']);
        $this->assertSame([], glob('../logolib/1091/.fe-compose-*'));
    }

    public function testFailedFinalPdfConversionLeavesTheActiveBackgroundAndMetadataUntouched(): void
    {
        $this->assertTrue(fe_write_logo_background('../logolib/1091', '../logolib/1091/faktura_bg.pdf', $this->position));
        $before = hash_file('sha256', '../logolib/1091/faktura_bg.pdf');
        $meta = file_get_contents('../logolib/1091/fe_logo_meta.json');
        unlink($this->work . '/bin/convert');
        file_put_contents($this->work . '/bin/convert', '#!/bin/sh' . "\n" .
            'for arg do case "$arg" in */composite.pdf) exit 1;; esac; done' . "\n" .
            'exec /usr/bin/convert "$@"' . "\n");
        chmod($this->work . '/bin/convert', 0700);
        $this->assertFalse(fe_write_logo_background('../logolib/1091', '../logolib/1091/faktura_bg.pdf', $this->position));
        $this->assertSame($before, hash_file('sha256', '../logolib/1091/faktura_bg.pdf'));
        $this->assertSame($meta, file_get_contents('../logolib/1091/fe_logo_meta.json'));
        $this->assertSame([], glob('../logolib/1091/.fe-compose-*'));
    }

    private function command(string $command): string
    {
        exec($command . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        return implode("\n", $output);
    }

    /** @return float[] Red, green and blue, each in the range 0–1. */
    private function pixel(string $png, float $xMm, float $yMm): array
    {
        $x = (int) round($xMm / 25.4 * 100);
        $y = (int) round($yMm / 25.4 * 100);
        $format = "%[fx:p{{$x},{$y}}.r],%[fx:p{{$x},{$y}}.g],%[fx:p{{$x},{$y}}.b]";
        return array_map('floatval', explode(',', $this->command('convert ' . escapeshellarg($png) . ' -format ' . escapeshellarg($format) . ' info:')));
    }

    private function assertRedAt(string $png, float $x, float $y): void
    {
        $pixel = $this->pixel($png, $x, $y);
        $this->assertGreaterThan(0.95, $pixel[0]);
        $this->assertLessThan(0.05, max($pixel[1], $pixel[2]));
    }
}
