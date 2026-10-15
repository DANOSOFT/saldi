<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileOcrTokenTest.php --- ver 5.0.0 --- 2026.10.01
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

require_once dirname(__DIR__, 2) . '/restapi/core/OcrKeys.php';

final class MobileOcrTokenTest extends TestCase
{
    private string $directory;
    private string $path;
    private OcrKeys $keys;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/ocr_keys_' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->path = $this->directory . '/.ht_ocr_keys.json';
        $this->keys = new OcrKeys($this->path);
    }

    protected function tearDown(): void
    {
        foreach (array_diff(scandir($this->directory), ['.', '..']) as $child) {
            unlink($this->directory . '/' . $child);
        }
        rmdir($this->directory);
    }

    /** @return array{header:array, claims:array, signature:string, input:string} */
    private function decode(string $token): array
    {
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
        return [
            'header' => json_decode(sodium_base642bin($parts[0], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), true, 512, JSON_THROW_ON_ERROR),
            'claims' => json_decode(sodium_base642bin($parts[1], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), true, 512, JSON_THROW_ON_ERROR),
            'signature' => sodium_base642bin($parts[2], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
            'input' => $parts[0] . '.' . $parts[1],
        ];
    }

    /** Verify using only published JWK material, like the external OCR service. */
    private function assertVerifies(array $decoded, array $published): void
    {
        $matching = array_values(array_filter($published['keys'], fn(array $key): bool => $key['kid'] === $decoded['header']['kid']));
        $this->assertCount(1, $matching);
        $public = sodium_base642bin($matching[0]['x'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $this->assertTrue(sodium_crypto_sign_verify_detached($decoded['signature'], $decoded['input'], $public));
    }

    public function test_claims_expiry_kid_and_public_key_only_verification(): void
    {
        $result = $this->keys->mint('installation-a', Subject::build('account', 7, 'installation-a'), 12, 2000000000);
        $decoded = $this->decode($result['token']);
        $this->assertSame('ai.saldi.dk', $result['audience']);
        $this->assertSame(2000000600, $result['expires_at']);
        $this->assertSame(['iss', 'aud', 'sub', 'tenant_id', 'iat', 'exp', 'jti'], array_keys($decoded['claims']));
        $this->assertSame('installation-a', $decoded['claims']['iss']);
        $this->assertSame('ai.saldi.dk', $decoded['claims']['aud']);
        $this->assertSame('installation-a/account/7', $decoded['claims']['sub']);
        $this->assertSame(12, $decoded['claims']['tenant_id']);
        $this->assertSame(600, $decoded['claims']['exp'] - $decoded['claims']['iat']);
        $this->assertSame($result['expires_at'], $decoded['claims']['exp']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $decoded['claims']['jti']);
        $this->assertSame('EdDSA', $decoded['header']['alg']);
        $this->assertSame('JWT', $decoded['header']['typ']);
        $published = $this->keys->publicKeys('installation-a');
        $this->assertCount(2, $published['keys']);
        foreach ($published['keys'] as $key) {
            $this->assertSame(['kty', 'crv', 'use', 'alg', 'kid', 'x'], array_keys($key));
            $this->assertSame('OKP', $key['kty']);
            $this->assertSame('Ed25519', $key['crv']);
            $this->assertSame('sig', $key['use']);
        }
        $this->assertVerifies($decoded, $published);
        $this->assertFalse(sodium_crypto_sign_verify_detached($decoded['signature'], $decoded['input'] . 'changed', sodium_base642bin($published['keys'][0]['x'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)));
    }

    public function test_provisioning_is_stable_owner_only_and_leaves_no_scratch(): void
    {
        $first = $this->keys->publicKeys('installation-a');
        $contents = file_get_contents($this->path);
        $this->assertSame($first, (new OcrKeys($this->path))->publicKeys('installation-a'));
        $this->assertSame($contents, file_get_contents($this->path));
        $this->assertSame(0600, fileperms($this->path) & 0777);
        $this->assertSame(0600, fileperms($this->path . '.lock') & 0777);
        $this->assertCount(2, array_diff(scandir($this->directory), ['.', '..']));
    }

    public function test_rotation_keeps_old_tokens_verifiable_until_expiry_and_uses_new_kid(): void
    {
        $old = $this->decode($this->keys->mint('installation-a', 'installation-a/account/7', 12, 2000000000)['token']);
        $newKid = $this->keys->rotate(2000000001);
        $new = $this->decode($this->keys->mint('installation-a', 'installation-a/account/7', 12, 2000000002)['token']);
        $this->assertSame($newKid, $new['header']['kid']);
        $this->assertNotSame($old['header']['kid'], $newKid);
        $published = $this->keys->publicKeys('installation-a');
        $this->assertVerifies($old, $published);
        $this->assertVerifies($new, $published);
        try {
            $this->keys->rotate(2000000599);
            $this->fail('A live previous key must not be discarded');
        } catch (RuntimeException $e) {
            $this->assertSame('Previous OCR key still verifies unexpired tokens', $e->getMessage());
        }
        $this->assertVerifies($old, $this->keys->publicKeys('installation-a'));
        $this->keys->rotate(2000000601);
        $this->assertNotContains($old['header']['kid'], array_column($this->keys->publicKeys('installation-a')['keys'], 'kid'));
        $this->assertVerifies($new, $this->keys->publicKeys('installation-a'));
    }

    public function test_installation_id_changes_issuer_and_jtis_are_unique(): void
    {
        $a = $this->decode($this->keys->mint('installation-a', Subject::build('registry', 7, 'installation-a'), 12)['token']);
        $other = new OcrKeys($this->directory . '/.ht_other_installation.json');
        $b = $this->decode($other->mint('installation-b', Subject::build('registry', 7, 'installation-b'), 12)['token']);
        $this->assertNotSame($a['claims']['iss'], $b['claims']['iss']);
        $this->assertNotSame($a['claims']['sub'], $b['claims']['sub']);
        $this->assertNotSame($a['claims']['jti'], $b['claims']['jti']);
        $this->assertNotSame($a['header']['kid'], $b['header']['kid']);
        $again = $this->decode($this->keys->mint('installation-a', 'installation-a/registry/7', 12)['token']);
        $this->assertNotSame($a['claims']['jti'], $again['claims']['jti']);
    }

    public function test_mismatched_subject_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->keys->mint('installation-a', 'installation-b/account/7', 12);
    }

    public function test_missing_tenant_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->keys->mint('installation-a', 'installation-a/account/7', 0);
    }

    public function test_corrupt_key_file_is_never_overwritten(): void
    {
        $this->keys->publicKeys('installation-a');
        file_put_contents($this->path, '{}');
        try {
            $this->keys->publicKeys('installation-a');
            $this->fail('Corrupted material must fail closed');
        } catch (RuntimeException $e) {
            $this->assertSame('{}', file_get_contents($this->path));
        }
    }

    public function test_concurrent_first_use_publishes_one_complete_stable_key_ring(): void
    {
        $script = 'require $argv[1]; echo json_encode((new OcrKeys($argv[2]))->publicKeys("installation-a"));';
        $workers = [];
        for ($i = 0; $i < 6; $i++) {
            $process = proc_open([PHP_BINARY, '-r', $script, dirname(__DIR__, 2) . '/restapi/core/OcrKeys.php', $this->path],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        $expected = null;
        foreach ($workers as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $errors);
            $this->assertSame('', $errors);
            $expected = $expected ?? $output;
            $this->assertSame($expected, $output);
        }
        $this->assertSame(json_decode($expected, true), $this->keys->publicKeys('installation-a'));
        $this->assertSame(0600, fileperms($this->path) & 0777);
        $this->assertCount(2, array_diff(scandir($this->directory), ['.', '..']));
    }

    public function test_non_private_key_file_fails_closed(): void
    {
        $this->keys->publicKeys('installation-a');
        chmod($this->path, 0644);
        clearstatcache(true, $this->path);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OCR keys must be readable and owner-only');
        $this->keys->publicKeys('installation-a');
    }
}
