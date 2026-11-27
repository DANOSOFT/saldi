<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * MB-173: kreditor/orderIncludes/selectOrder.php was included nowhere and printed the creditor
 * order header with every value raw inside value='...', the cut-at-apostrophe bug fixed in
 * MB-139. It was deleted; this pins that it stays gone. kreditor/orderIncludes/openOrderData.php
 * is the live, escaped header.
 *
 * The guard is deliberately broad: any string literal in a PHP file outside tests/ whose last
 * path segment is selectOrder.php fails the test, whatever expression it sits in. Comments do
 * not count. A path assembled at runtime (from variables, or from separately quoted pieces such
 * as 'selectOrder' . '.php') is not caught.
 */
final class selectOrderRemoved extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** Top-level directories that are not application code. */
    private const SKIP = ['.git', 'tests', 'vendor', 'node_modules'];

    /** The deleted file as the last path segment, and nothing longer such as preselectOrder.php. */
    private const TARGET = '#(^|[/\\\\])selectOrder\.php$#';

    /** True when a string literal in the PHP source ends on selectOrder.php. */
    private static function namesSelectOrder(string $src): bool
    {
        foreach (token_get_all($src) as $token) {
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $value = substr($token[1], 1, -1);
            } elseif ($token[0] === T_ENCAPSED_AND_WHITESPACE) {
                $value = rtrim($token[1], "\r\n");
            } else {
                continue;
            }
            if (preg_match(self::TARGET, $value) === 1) {
                return true;
            }
        }
        return false;
    }

    public function testTheUnescapedHeaderCopyIsDeleted(): void
    {
        $this->assertFileExists(self::ROOT . '/kreditor/orderIncludes/openOrderData.php',
            'the live creditor order header is missing, so this test is looking in the wrong tree');
        $this->assertFileDoesNotExist(self::ROOT . '/kreditor/orderIncludes/selectOrder.php',
            'selectOrder.php is back; it prints the order header unescaped (MB-139)');
    }

    /** The scan below must catch the name in any string, in any branch, and skip comments. */
    public function testTheCheckFindsTheNameInAnyString(): void
    {
        $this->assertTrue(self::namesSelectOrder("<?php\ninclude('orderIncludes/selectOrder.php');\n"));
        $this->assertTrue(self::namesSelectOrder("<?php\ninclude \"\$dir/selectOrder.php\";\n"));
        $this->assertTrue(self::namesSelectOrder("<?php\n\$file = 'selectOrder.php';\n"));
        $this->assertTrue(self::namesSelectOrder("<?php\n\$r = \$c ? include('orderIncludes/selectOrder.php') : 'fallback';\n"));
        $this->assertTrue(self::namesSelectOrder("<?php\nrequire (\$legacy ? __DIR__ . '/orderIncludes/selectOrder.php' : __DIR__ . '/orderIncludes/openOrderData.php');\n"));
        $this->assertTrue(self::namesSelectOrder("<?php\nrequire (\$legacy ? __DIR__ . '/orderIncludes/openOrderData.php' : __DIR__ . '/orderIncludes/selectOrder.php');\n"));

        $this->assertFalse(self::namesSelectOrder("<?php\n// selectOrder.php used to print this header\ninclude('orderIncludes/openOrderData.php');\n"));
        $this->assertFalse(self::namesSelectOrder("<?php\n/* include('orderIncludes/selectOrder.php'); */\n"));
        $this->assertFalse(self::namesSelectOrder("<?php\ninclude('orderIncludes/preselectOrder.php');\n"));
        $this->assertFalse(self::namesSelectOrder("<?php\ninclude('orderIncludes/selectOrder.php.bak');\n"));
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
            // Symlinked directories come through as leaves; only real PHP files are read.
            if (!$file->isFile() || $file->isLink() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            $this->assertIsString($src, 'could not read ' . $file->getPathname());
            $scanned++;
            if (self::namesSelectOrder($src)) {
                $hits[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        $this->assertGreaterThan(0, $scanned, 'no PHP files were scanned');
        $this->assertSame([], $hits, 'these PHP files name selectOrder.php in a string again');
    }
}
