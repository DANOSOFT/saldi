<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * MB-173: kreditor/orderIncludes/selectOrder.php was included nowhere and printed the creditor
 * order header with every value raw inside value='...', the cut-at-apostrophe bug fixed in
 * MB-139. It was deleted; this pins that it stays gone and that no PHP file outside tests/
 * wires it back in. kreditor/orderIncludes/openOrderData.php is the live, escaped header.
 */
final class selectOrderRemoved extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** Directories that are not application code. */
    private const SKIP = ['.git', 'tests', 'vendor', 'node_modules'];

    public function testTheUnescapedHeaderCopyIsDeleted(): void
    {
        $this->assertFileExists(self::ROOT . '/kreditor/orderIncludes/openOrderData.php',
            'the live creditor order header is missing, so this test is looking in the wrong tree');
        $this->assertFileDoesNotExist(self::ROOT . '/kreditor/orderIncludes/selectOrder.php',
            'selectOrder.php is back; it prints the order header unescaped (MB-139)');
    }

    public function testNoPhpFileOutsideTestsNamesIt(): void
    {
        $root = realpath(self::ROOT);
        $this->assertIsString($root);

        $dirs = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $file) use ($root): bool {
                if ($file->isDir()) {
                    return !($file->getPath() === $root && in_array($file->getFilename(), self::SKIP, true));
                }
                return strtolower($file->getExtension()) === 'php';
            }
        );

        $hits = [];
        $scanned = 0;
        foreach (new RecursiveIteratorIterator($dirs) as $file) {
            $src = file_get_contents($file->getPathname());
            $this->assertIsString($src, 'could not read ' . $file->getPathname());
            $scanned++;
            if (stripos($src, 'selectOrder.php') !== false) {
                $hits[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        $this->assertGreaterThan(0, $scanned, 'no PHP files were scanned');
        $this->assertSame([], $hits, 'these PHP files still name selectOrder.php');
    }
}
