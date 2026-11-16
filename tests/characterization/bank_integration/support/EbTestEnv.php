<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/bank_integration/support/EbTestEnv.php --- ver 5.1.1 --- 2026-11-16 ---
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
// 20261006 CDX/LH Created isolated Enable Banking fixture support.
// 20261116 CL/LH Child processes find the test group database through SALDI_EB_TEST_DB (UUID-owned test databases).

require_once __DIR__ . '/../../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../../includes/db_query.php';
require_once __DIR__ . '/../../../../includes/std_func.php';

/** @return string The test group's database, also in child processes (SALDI_EB_TEST_DB, set by the test class). */
function ebTestDb(): string
{
    return getenv('SALDI_EB_TEST_DB') ?: CharacterizationEnv::testDb();
}

/** @return void Establish the real tenant DB globals, without browser authentication. */
function ebTestConnect(): void
{
    $GLOBALS['db_type'] = 'pgsql';
    $GLOBALS['db_encode'] = 'UTF8';
    $GLOBALS['sqdb'] = CharacterizationEnv::masterDb();
    $GLOBALS['db'] = ebTestDb();
    $GLOBALS['db_skriv_id'] = 0;
    $GLOBALS['brugernavn'] = 'chartest';
    $GLOBALS['s_id'] = '';
    $GLOBALS['webservice'] = true;
    $GLOBALS['db_transaktion_depth'] = 0;
    $GLOBALS['db_modify_fejl'] = false;
    $_SERVER['REQUEST_URI'] = '/saldi/bank_integration/worker.php';
    $_SERVER['REMOTE_ADDR'] = 'cli';
    $GLOBALS['connection'] = CharacterizationEnv::connect(ebTestDb());
    chdir(CharacterizationEnv::repoRoot() . '/bank_integration');
}

final class EbFixtureTransport implements EbTransport
{
    /** @var list<array{method:string, path:string, status:int, body:array<string,mixed>|string, delay?:int, effect?:callable():void}> */
    public array $script;
    /** @var list<array{method:string, url:string, headers:list<string>, body:?string}> */
    public array $requests = [];

    /**
 * @param list<array{method:string, path:string, status:int, body:array<string,mixed>|string, delay?:int, effect?:callable():void}> $script
 * @return void
 */
    public function __construct(array $script)
    {
        $this->script = $script;
    }

    /**
 * @param list<string> $headers
 * @return array{status:int, body:string, error:string}
 */
    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        if (($GLOBALS['db_transaktion_depth'] ?? 0) !== 0) {
            throw new LogicException('HTTP while transaction open');
        }
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $step = array_shift($this->script);
        if (!$step || $step['method'] !== $method || !str_contains($url, $step['path'])) {
            throw new LogicException('Unexpected fixture request: ' . $method . ' ' . parse_url($url, PHP_URL_PATH));
        }
        if (isset($step['delay'])) {
            usleep($step['delay']);
        }
        if (isset($step['effect'])) {
            ($step['effect'])();
        }
        return ['status' => $step['status'], 'body' => is_string($step['body']) ? $step['body'] : json_encode($step['body'], JSON_THROW_ON_ERROR), 'error' => ''];
    }
}

final class EbTestKeys
{
    public static string $directory;
    public static string $privatePath;
    public static string $publicKey;

    /** @return void Generate ephemeral RSA and AES material entirely outside the checkout. */
    public static function create(): void
    {
        self::$directory = sys_get_temp_dir() . '/saldi_eb_' . bin2hex(random_bytes(8));
        mkdir(self::$directory . '/includes', 0700, true);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        self::$privatePath = self::$directory . '/rsa.pem';
        file_put_contents(self::$privatePath, $private);
        chmod(self::$privatePath, 0600);
        self::$publicKey = openssl_pkey_get_details($key)['key'];
        // Execute the production primitive unchanged, using its anchored key path in a temporary tree.
        copy(CharacterizationEnv::repoRoot() . '/bank_integration/includes/crypt.php', self::$directory . '/includes/crypt.php');
        file_put_contents(self::$directory . '/.ht_oauth_key.bin', random_bytes(32));
        require_once self::$directory . '/includes/crypt.php';
    }

    /** @return array{app_id:string, key_path:string, redirect_url:string, environment:string, configured:bool, problem:string} */
    public static function config(): array
    {
        return ['app_id' => 'fixture-application', 'key_path' => self::$privatePath,
            'redirect_url' => 'https://example.invalid/bank/callback', 'environment' => 'SANDBOX', 'configured' => true, 'problem' => ''];
    }

    /** @return void Remove ephemeral key files. */
    public static function cleanup(): void
    {
        foreach ([self::$privatePath, self::$directory . '/.ht_oauth_key.bin', self::$directory . '/includes/crypt.php'] as $file) {
            unlink($file);
        }
        rmdir(self::$directory . '/includes');
        rmdir(self::$directory);
    }
}
