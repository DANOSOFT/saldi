<?php
// tests/characterization/soapklient/PhpinfoExposureTest.test.php
//
// No PHP file in the tree calls phpinfo(). The whole tree is served, and
// phpinfo() prints the server's environment, paths and module configuration to
// whoever opens the page.
//
// soapklient/phptjek.php did exactly that, with no include and no login check,
// to anonymous and logged-in visitors alike. On the testlab its page carried
// the SMTP password from the environment (testlab round 1, legacy-endpoints).
// The file is removed, and a new phpinfo() call now fails here.
//
// Reads the source only; runs everywhere.
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\TestCase;

final class PhpinfoExposureTest extends TestCase
{
    /** @return list<string> "path:line" of each phpinfo reference in the tree's PHP code (not its comments or strings) */
    private static function phpinfoCalls(): array
    {
        $root = dirname(__DIR__, 3);
        $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static fn(SplFileInfo $f): bool => !$f->isDir() || !in_array($f->getFilename(), ['.git', 'vendor', 'node_modules'], true)
        ));
        $calls = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string)file_get_contents($file->getPathname());
            if (stripos($source, 'phpinfo') === false) {
                continue;
            }
            foreach (token_get_all($source) as $token) {
                if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)
                    && strtolower(ltrim($token[1], '\\')) === 'phpinfo') {
                    $calls[] = substr($file->getPathname(), strlen($root) + 1) . ':' . $token[2];
                }
            }
        }
        sort($calls);
        return $calls;
    }

    public function test_no_file_calls_phpinfo(): void
    {
        $this->assertSame([], self::phpinfoCalls(),
            'These lines call phpinfo(), which shows the server environment to anyone who opens the page; remove the call.');
    }
}
