<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/T4GoldenStockTest.php --- ver 5.0.0 --- 2026.10.07
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/RestApiEnv.php';

/** Execute the owning slices' real web/REST comparisons in independent PHP processes. */
final class T4GoldenStockTest extends TestCase
{
    /** @return iterable<string,array{string,string,int}> Golden comparisons, including reconciliation cases S6b, S12, L5 and E6. */
    public static function goldenGroups(): iterable
    {
        yield 'S1-S6 and S12 counts, invariants and valuation' => ['StockCountEndpointTest.php','testGolden',3];
        yield 'S6b queue guard' => ['StockCountConcurrencyTest.php','testGoldenS6b',1];
        yield 'S7-S11 receipt rows and valuation' => ['GoodsReceiptEndpointTest.php','testGoldenReceipt',5];
        yield 'L1-L5 packed payroll and browser settlement' => ['TimeEntryEndpointTest.php','testGoldenPayroll|testL4Web',7];
        yield 'E1-E6 real web postings and annual allowance' => ['ClaimApprovalTest.php','testE[1-6]',6];
    }

    #[DataProvider('goldenGroups')]
    public function testGoldenGroupInIsolatedProcess(string $file,string $filter,int $minimumTests): void
    {
        if ($reason = RestApiEnv::unavailableReason()) { self::markTestSkipped($reason); }
        $output = tempnam(sys_get_temp_dir(),'t4golden');
        $process = null;
        try {
            $environment = getenv();
            unset($environment['SALDI_T3_CONTRACT_OUTPUT'],$environment['SALDI_T4_CONTRACT_OUTPUT']);
            $environment['SALDI_CHAR_TEST_DB'] = 'mc_t4x1';
            $process = proc_open([PHP_BINARY,__DIR__.'/../../vendor/phpunit.phar','--colors=never','--filter',$filter,__DIR__.'/'.$file],
                [0=>['file','/dev/null','r'],1=>['file',$output,'w'],2=>['file',$output.'.err','w']],$pipes,dirname(__DIR__,2),$environment);
            $this->assertIsResource($process);
            $exit = proc_close($process);
            $process = null;
            $result = file_get_contents($output).file_get_contents($output.'.err');
            $this->assertSame(0,$exit,$result);
            $this->assertMatchesRegularExpression('/OK \((\d+) tests?, \d+ assertions?\)/',$result,$result);
            preg_match('/OK \((\d+) tests?,/', $result,$match);
            $this->assertGreaterThanOrEqual($minimumTests,(int)$match[1],$result);
            $this->assertStringNotContainsString('Skipped:',$result,'Golden scenarios must run against PostgreSQL');
            $this->assertStringNotContainsString('No tests executed',$result);
        } finally {
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
            unlink($output);
            if (is_file($output.'.err')) { unlink($output.'.err'); }
        }
    }
}
