<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/InvoicePaymentPageTest.php --- 2026.10.12
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
// 20261012 CL/LH Confirm current amounts and committed outcomes through the actual browser controller.

use PHPUnit\Framework\TestCase;

final class InvoicePaymentPageTest extends TestCase
{
    private function browser(string $scenario): void
    {
        $node = trim((string)shell_exec('command -v node'));
        if ($node === '') $this->markTestSkipped('Browser controller fixture requires Node.js');
        $process = proc_open([$node, __DIR__ . '/support/InvoicePaymentPageFixture.js', $scenario],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $output . $errors);
        $this->assertSame('', $errors);
    }

    public function testChangedAmountRequiresNewConfirmation(): void { $this->browser('changed'); }
    public function testProcessingStopsPaymentButton(): void { $this->browser('processing'); }
    public function testAlreadySucceededRedirectsWithoutConfirmation(): void { $this->browser('succeeded'); }
    public function testUnchangedAmountConfirmsIntent(): void { $this->browser('unchanged'); }
}
