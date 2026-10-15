<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/includes/MailCoreSafetyTest.test.php --- ver 5.0.0 --- 2026.10.02
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class MailCoreSafetyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/b5-mail-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/project/includes/formFuncIncludes', 0775, true);
        mkdir($this->root . '/project/vendor', 0775, true);
        copy(__DIR__ . '/../../../includes/formFuncIncludes/sendMail.php', $this->root . '/project/includes/formFuncIncludes/sendMail.php');
        file_put_contents($this->root . '/project/vendor/autoload.php', '<?php $GLOBALS["test_root_loader"] = true;');
    }

    protected function tearDown(): void
    {
        $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($paths as $path) {
            if ($path->isDir()) {
                rmdir($path->getPathname());
            } else {
                unlink($path->getPathname());
            }
        }
        rmdir($this->root);
    }

    public function testPhpunitOnlyRootVendorDoesNotHideParentOrIncludesMailer(): void
    {
        $realLoader = realpath(__DIR__ . '/../../../includes/vendor/autoload.php');
        self::assertNotFalse($realLoader, 'Development PHPMailer installation is required');
        foreach (['/vendor', '/project/includes/vendor'] as $candidate) {
            mkdir($this->root . $candidate, 0775, true);
            file_put_contents($this->root . $candidate . '/autoload.php', '<?php require_once ' . var_export($realLoader, true) . ';');
            $result = CharacterizationEnv::runChild(__DIR__ . '/../support/run_mail_safety.php',
                ['loader', $this->root . '/project/includes/formFuncIncludes/sendMail.php']);
            self::assertSame(0, $result['exit'], $result['stderr']);
            self::assertSame(['mailer' => true, 'smtp' => true, 'root_loaded' => false], json_decode($result['stdout'], true));
            unlink($this->root . $candidate . '/autoload.php');
            rmdir($this->root . $candidate);
        }
    }

    public function testBrowserGuardSurvivesConcurrentWritersAndRepairsOldPartialPhp(): void
    {
        $mailSource = $this->root . '/project/includes/formFuncIncludes/sendMail.php';
        mkdir($this->root . '/project/temp/mailtest', 0775, true);
        file_put_contents($this->root . '/project/temp/mailtest/mailchk.php', '<?php $chkOid=');
        $processes = [];
        foreach ([1, 2] as $writer) {
            $processes[] = proc_open([PHP_BINARY, __DIR__ . '/../support/run_mail_safety.php', 'guard', $mailSource, 'mailtest', (string)$writer],
                [0 => ['pipe','r'], 1 => ['file',$this->root . '/stdout-' . $writer,'w'], 2 => ['file',$this->root . '/stderr-' . $writer,'w']], $pipes);
        }
        foreach ($processes as $index => $process) {
            $writer = $index + 1;
            self::assertSame(0, proc_close($process), file_get_contents($this->root . '/stderr-' . $writer));
            self::assertSame('OK', file_get_contents($this->root . '/stdout-' . $writer));
            self::assertSame('', file_get_contents($this->root . '/stderr-' . $writer));
        }
        $duplicate = CharacterizationEnv::runChild(__DIR__ . '/../support/run_mail_safety.php', ['duplicate', $mailSource, 'mailtest']);
        self::assertSame(0, $duplicate['exit'], $duplicate['stderr']);
        self::assertSame([true, false], json_decode($duplicate['stdout'], true));
        $guard = $this->root . '/project/temp/mailtest/mailchk.php';
        include $guard;
        self::assertFileExists($this->root . '/project/temp/mailtest/mailchk.lock');
        self::assertIsInt($chkOid);
        self::assertIsString($chkSubj);
    }
}
