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

    /** Single-character tokens that end an include operand when not nested inside it. */
    private const OPERAND_END = [':', '?', ',', ')', ';', ']', '}'];

    /** The deleted file as the last path segment, and nothing longer such as preselectOrder.php. */
    private const TARGET = '#(^|[/\\\\])selectOrder\.php$#';

    /** The value of a quoted string literal token. */
    private static function literal(string $text): string
    {
        $body = substr($text, 1, -1);
        if ($text[0] === "'") {
            return str_replace(["\\'", '\\\\'], ["'", '\\'], $body);
        }
        return stripcslashes($body);
    }

    /**
     * The static path of every include/include_once/require/require_once in a PHP source.
     *
     * Only the operand is read: from the keyword up to the first : ? , ) ; ] } ?? or ?> that is
     * not nested inside the operand's own brackets. Its string literals are joined in order, so
     * 'selectOrder' . '.php' reads as selectOrder.php, while in
     * $c ? include('a.php') : 'fallback' the fallback is not part of the path. Comments,
     * constants such as __DIR__ and variables add nothing.
     *
     * @return array<int, string>
     */
    private static function includeTargets(string $src): array
    {
        $targets = [];
        $current = null;
        $depth = 0;
        foreach (token_get_all($src) as $token) {
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;
            if ($id !== null && in_array($id, self::INCLUDE_TOKENS, true)) {
                // A nested include (include include 'x') starts a new operand.
                if ($current !== null) {
                    $targets[] = $current;
                }
                $current = '';
                $depth = 0;
                continue;
            }
            if ($current === null) {
                continue;
            }
            if ($id === null && in_array($text, ['(', '['], true)
                || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES
                || ($id === null && $text === '{')) {
                $depth++;
                continue;
            }
            $ends = $id === T_CLOSE_TAG || $id === T_COALESCE
                || ($id === null && in_array($text, self::OPERAND_END, true));
            if ($ends && $depth === 0) {
                $targets[] = $current;
                $current = null;
                continue;
            }
            if ($id === null && in_array($text, [')', ']', '}'], true)) {
                $depth--;
            } elseif ($id === T_CONSTANT_ENCAPSED_STRING) {
                $current .= self::literal($text);
            } elseif ($id === T_ENCAPSED_AND_WHITESPACE) {
                $current .= stripcslashes($text);
            }
        }
        if ($current !== null) {
            $targets[] = $current;
        }
        return $targets;
    }

    private static function includesSelectOrder(string $src): bool
    {
        foreach (self::includeTargets($src) as $target) {
            if (preg_match(self::TARGET, $target) === 1) {
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

    /** The scan below must catch a real include, however the path is spelled, and nothing else. */
    public function testTheCheckTellsAnIncludeFromAnythingElse(): void
    {
        $this->assertTrue(self::includesSelectOrder("<?php\ninclude('orderIncludes/selectOrder.php');\n"));
        $this->assertTrue(self::includesSelectOrder("<?php\nif (\$x) include_once __DIR__ . \"/orderIncludes/selectOrder.php\";\n"));
        $this->assertTrue(self::includesSelectOrder("<?php\nrequire(__DIR__ . /* header */ '/orderIncludes/' . 'selectOrder.php');\n"));
        $this->assertTrue(self::includesSelectOrder("<?php require_once 'kreditor/orderIncludes/selectOrder.php' ?>"));
        $this->assertTrue(self::includesSelectOrder("<?php\ninclude 'orderIncludes/' . 'selectOrder' . '.php';\n"));
        $this->assertTrue(self::includesSelectOrder("<?php\ninclude \"\$dir/selectOrder.php\";\n"));
        $this->assertTrue(self::includesSelectOrder("<?php\ninclude('selectOrder.php');\n"));
        $this->assertTrue(self::includesSelectOrder("<?php\ninclude(dirname(__FILE__) . '/orderIncludes/selectOrder.php');\n"));

        // The include is one operand of a larger expression; what follows it is not its path.
        $this->assertTrue(self::includesSelectOrder("<?php\n\$result = \$useHeader ? include('orderIncludes/selectOrder.php') : 'fallback';\n"));
        $this->assertTrue(self::includesSelectOrder("<?php\n\$result = \$useHeader ? 'fallback' : include 'orderIncludes/selectOrder.php';\n"));
        $this->assertTrue(self::includesSelectOrder("<?php\nrender(include 'orderIncludes/selectOrder.php', 'other');\n"));
        $this->assertTrue(self::includesSelectOrder("<?php\n\$a = [include 'orderIncludes/selectOrder.php', 'other'];\n"));
        $this->assertTrue(self::includesSelectOrder("<?php\n\$a = (include 'orderIncludes/selectOrder.php') ?? 'other';\n"));

        $this->assertFalse(self::includesSelectOrder("<?php\n// selectOrder.php used to print this header\ninclude('orderIncludes/openOrderData.php');\n"));
        $this->assertFalse(self::includesSelectOrder("<?php\n/* include('orderIncludes/selectOrder.php'); */\n"));
        $this->assertFalse(self::includesSelectOrder("<?php\n\$note = 'selectOrder.php';\n"));
        $this->assertFalse(self::includesSelectOrder("<?php\ninclude('orderIncludes/preselectOrder.php');\n"));
        $this->assertFalse(self::includesSelectOrder("<?php\ninclude('orderIncludes/selectOrder.php.bak');\n"));
        $this->assertFalse(self::includesSelectOrder("<?php\n\$result = \$useHeader ? include('orderIncludes/openOrderData.php') : 'selectOrder.php';\n"));
        $this->assertFalse(self::includesSelectOrder("<?php\nrender(include 'orderIncludes/openOrderData.php', 'selectOrder.php');\n"));
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
