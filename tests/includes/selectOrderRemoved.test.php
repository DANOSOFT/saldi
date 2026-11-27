<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * MB-173: kreditor/orderIncludes/selectOrder.php was included nowhere and printed the creditor
 * order header with every value raw inside value='...', the cut-at-apostrophe bug fixed in
 * MB-139. It was deleted; this pins that it stays gone and that no PHP file outside tests/
 * includes or requires it again. kreditor/orderIncludes/openOrderData.php is the live, escaped
 * header. Mentions in comments are allowed; only include/require targets count.
 */
final class selectOrderRemoved extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** Top-level directories that are not application code. */
    private const SKIP = ['.git', 'tests', 'vendor', 'node_modules'];

    private const INCLUDE_TOKENS = [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE];

    /**
     * The target expression of every include/include_once/require/require_once in a PHP source,
     * with comments and whitespace dropped.
     *
     * @return array<int, string>
     */
    private static function includeTargets(string $src): array
    {
        $targets = [];
        $current = null;
        foreach (token_get_all($src) as $token) {
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;
            if ($id !== null && in_array($id, self::INCLUDE_TOKENS, true)) {
                if ($current !== null) {
                    $targets[] = $current;
                }
                $current = '';
                continue;
            }
            if ($current === null) {
                continue;
            }
            if ($text === ';' || $id === T_CLOSE_TAG) {
                $targets[] = $current;
                $current = null;
                continue;
            }
            if ($id === T_COMMENT || $id === T_DOC_COMMENT || $id === T_WHITESPACE) {
                continue;
            }
            $current .= $text;
        }
        if ($current !== null) {
            $targets[] = $current;
        }
        return $targets;
    }

    private static function includesSelectOrder(string $src): bool
    {
        foreach (self::includeTargets($src) as $target) {
            if (stripos($target, 'selectOrder.php') !== false) {
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

    /** The scan below must catch a real include and must not trip on a comment. */
    public function testTheCheckTellsAnIncludeFromAComment(): void
    {
        $this->assertTrue(self::includesSelectOrder("<?php\ninclude('orderIncludes/selectOrder.php');\n"));
        $this->assertTrue(self::includesSelectOrder("<?php\nif (\$x) include_once __DIR__ . \"/orderIncludes/selectOrder.php\";\n"));
        $this->assertTrue(self::includesSelectOrder("<?php\nrequire(__DIR__ . /* header */ '/orderIncludes/' . 'selectOrder.php');\n"));
        $this->assertTrue(self::includesSelectOrder("<?php require_once 'kreditor/orderIncludes/selectOrder.php' ?>"));

        $this->assertFalse(self::includesSelectOrder("<?php\n// selectOrder.php used to print this header\ninclude('orderIncludes/openOrderData.php');\n"));
        $this->assertFalse(self::includesSelectOrder("<?php\n/* include('orderIncludes/selectOrder.php'); */\n"));
        $this->assertFalse(self::includesSelectOrder("<?php\n\$note = 'selectOrder.php';\n"));
    }

    public function testNoPhpFileOutsideTestsIncludesIt(): void
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
            if (self::includesSelectOrder($src)) {
                $hits[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        $this->assertGreaterThan(0, $scanned, 'no PHP files were scanned');
        $this->assertSame([], $hits, 'these PHP files include or require selectOrder.php again');
    }
}
