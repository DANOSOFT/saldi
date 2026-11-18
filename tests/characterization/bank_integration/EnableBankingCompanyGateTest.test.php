<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/bank_integration/EnableBankingCompanyGateTest.test.php --- ver 5.1.1 --- 2026-11-18 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
//
//
// 20261118 CL/LH Created: the pilot company allowlist opens the feature for the listed company databases only.
// 20261118 CL/LH CodeRabbit: an allowlist of only separators is a configuration problem, a trailing comma keeps the list; the per-company enabled.php check runs in a child process.

use PHPUnit\Framework\TestCase;

// Only the config reader at load time: enabled.php pulls in crypt.php, which EnableBankingBackendTest loads from its own key tree.
require_once __DIR__ . '/../../../bank_integration/includes/ebConfig.php';
require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class EnableBankingCompanyGateTest extends TestCase
{
    private const VARS = ['APP_ID', 'KEY_PATH', 'REDIRECT_URL', 'ENV', 'COMPANIES'];
    /** @var array<string, string|false> */
    private array $previousEnv = [];
    /** @var array<string, mixed> */
    private array $previousGlobals = [];
    private string $keyDir = '';

    protected function setUp(): void
    {
        foreach (self::VARS as $var) {
            $this->previousEnv[$var] = getenv('SALDI_ENABLEBANKING_' . $var);
        }
        $this->previousGlobals = ['db' => $GLOBALS['db'] ?? null, 'enableBankingCompanies' => $GLOBALS['enableBankingCompanies'] ?? null];
        // Any readable file outside the checkout passes the key check; the gate never signs anything.
        $this->keyDir = sys_get_temp_dir() . '/saldi_eb_gate_' . bin2hex(random_bytes(6));
        mkdir($this->keyDir, 0700);
        file_put_contents($this->keyDir . '/key.pem', 'fixture');
        putenv('SALDI_ENABLEBANKING_APP_ID=fixture-app');
        putenv('SALDI_ENABLEBANKING_KEY_PATH=' . $this->keyDir . '/key.pem');
        putenv('SALDI_ENABLEBANKING_REDIRECT_URL=https://example.invalid/finans/bank_integration/callback.php');
        putenv('SALDI_ENABLEBANKING_ENV=PRODUCTION');
        putenv('SALDI_ENABLEBANKING_COMPANIES=');
        unset($GLOBALS['enableBankingCompanies']);
    }

    protected function tearDown(): void
    {
        @unlink($this->keyDir . '/key.pem');
        @rmdir($this->keyDir);
        foreach ($this->previousEnv as $var => $value) {
            putenv('SALDI_ENABLEBANKING_' . $var . ($value === false ? '' : '=' . $value));
        }
        foreach ($this->previousGlobals as $name => $value) {
            if ($value === null) {
                unset($GLOBALS[$name]);
            } else {
                $GLOBALS[$name] = $value;
            }
        }
    }

    public function test_without_allowlist_every_company_has_the_feature(): void
    {
        foreach (['saldi_1091', 'saldi_7'] as $db) {
            $GLOBALS['db'] = $db;
            $this->assertTrue(ebFeatureAvailable(), $db);
        }
        $this->assertSame([], ebConfig()['companies']);
    }

    public function test_allowlist_from_connect_php_opens_only_the_listed_companies(): void
    {
        $GLOBALS['enableBankingCompanies'] = 'saldi_1091, saldi_42';
        $this->assertSame(['saldi_1091', 'saldi_42'], ebConfig()['companies']);
        $this->assertTrue(ebConfig()['configured'], 'the allowlist does not make the server configuration incomplete');
        foreach (['saldi_1091' => true, 'saldi_42' => true, 'saldi_7' => false, 'saldi_10910' => false, '' => false] as $db => $expected) {
            $GLOBALS['db'] = $db;
            $this->assertSame($expected, ebFeatureAvailable(), "company '$db'");
        }
    }

    public function test_allowlist_without_company_names_is_a_configuration_problem(): void
    {
        foreach (['env', 'connect.php'] as $source) {
            if ($source === 'env') {
                putenv('SALDI_ENABLEBANKING_COMPANIES=, ,');
            } else {
                putenv('SALDI_ENABLEBANKING_COMPANIES=');
                $GLOBALS['enableBankingCompanies'] = ' , ';
            }
            $config = ebConfig();
            $this->assertFalse($config['configured'], $source);
            $this->assertNotSame('', $config['problem'], $source);
            foreach (['saldi_1091', 'saldi_7', ''] as $db) {
                $GLOBALS['db'] = $db;
                $this->assertFalse(ebFeatureAvailable(), "$source, company '$db'");
            }
        }
        // Blank stays unrestricted; a trailing comma keeps the one listed company.
        putenv('SALDI_ENABLEBANKING_COMPANIES=   ');
        $GLOBALS['enableBankingCompanies'] = '  ';
        $this->assertTrue(ebConfig()['configured']);
        $this->assertSame([], ebConfig()['companies']);
        putenv('SALDI_ENABLEBANKING_COMPANIES=saldi_42,');
        $this->assertTrue(ebConfig()['configured']);
        $this->assertSame(['saldi_42'], ebConfig()['companies']);
        foreach (['saldi_42' => true, 'saldi_7' => false] as $db => $expected) {
            $GLOBALS['db'] = $db;
            $this->assertSame($expected, ebFeatureAvailable(), "company '$db'");
        }
    }

    public function test_environment_allowlist_wins_over_connect_php(): void
    {
        $GLOBALS['enableBankingCompanies'] = 'saldi_1091';
        putenv('SALDI_ENABLEBANKING_COMPANIES=verify_eblive');
        $GLOBALS['db'] = 'saldi_1091';
        $this->assertFalse(ebFeatureAvailable());
        $GLOBALS['db'] = 'verify_eblive';
        $this->assertTrue(ebFeatureAvailable());
    }

    public function test_settings_gate_is_decided_per_company_in_one_process(): void
    {
        // tools/notification_digest.php switches companies within one PHP process; the cached answer must not leak.
        // A child process: enabled.php loads crypt.php, which must not bind to the checkout before EnableBankingBackendTest's key tree.
        $result = CharacterizationEnv::runChild(__DIR__ . '/support/run_gate.php', ['saldi_1091', 'saldi_7', 'saldi_1091', 'saldi_7']);
        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertSame("saldi_7=0\nsaldi_1091=1\nsaldi_7=0\n", $result['stdout'], $result['stderr']);
    }
}
