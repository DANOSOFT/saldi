<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/InvoicePaymentPageTest.php --- 2026.10.13
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
// 20261013 CL/LH (CodeRabbit) Regress PDOException catch ordering and generic public payment errors.

use PHPUnit\Framework\TestCase;

final class InvoicePaymentPageTest extends TestCase
{
    public function testDatabaseErrorsReturnGenericMessageBeforeRuntimeErrors(): void
    {
        $page = (string)file_get_contents(__DIR__ . '/../../api/stripe/faktura.php');
        $pdoCatch = strpos($page, 'catch (\\PDOException $e)');
        $runtimeCatch = strpos($page, 'catch (RuntimeException $e)');
        $this->assertNotFalse($pdoCatch);
        $this->assertNotFalse($runtimeCatch);
        $this->assertLessThan($runtimeCatch, $pdoCatch);
        $handler = substr($page, $pdoCatch, $runtimeCatch - $pdoCatch);
        $this->assertStringContainsString('stripe_log("faktura: db error order $order_id: " . $e->getMessage());', $handler);
        $this->assertSame(1, preg_match('/fp_json\((.*?)\);/s', $handler, $response));
        $this->assertSame("500, ['error' => 'Betalingen kunne ikke startes. Prøv igen om lidt.']", $response[1]);
        $this->assertStringNotContainsString('$e->getMessage()', $response[1]);
    }

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
