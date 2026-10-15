<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/OcrKeys.php --- ver 5.0.0 --- 2026.10.01
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

require_once __DIR__ . '/Subject.php';
require_once __DIR__ . '/../../includes/std_func.php';

/** Installation-only Ed25519 keys, independent of the REST JWT signing secret. */
final class OcrKeys
{
    public const TTL = 600;
    public const AUDIENCE = 'ai.saldi.dk';
    private string $path;

    /** An explicit path isolates unit tests from installation key material. */
    public function __construct(?string $path = null)
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('OCR requires sodium');
        }
        $this->path = $path ?? __DIR__ . '/../.ht_ocr_keys.json';
    }

    /** @return string Unpadded base64url, as required for JWT and JWK. */
    private static function base64url(string $bytes): string
    {
        return sodium_bin2base64($bytes, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /** @return array{kid:string, secret:string, retain_until:int} */
    private static function generate(): array
    {
        $pair = sodium_crypto_sign_keypair();
        return [
            'kid' => bin2hex(random_bytes(16)),
            'secret' => self::base64url(sodium_crypto_sign_secretkey($pair)),
            'retain_until' => 0,
        ];
    }

    /**
     * Serialize first use and rotation; publish only complete 0600 same-directory files.
     * Readers use the same lock, so concurrent workers cannot replace each other's keys.
     *
     * @return mixed The operation's result.
     */
    private function locked(callable $operation)
    {
        if (!is_dir(dirname($this->path)) || is_link($this->path) || is_link($this->path . '.lock')) {
            throw new RuntimeException('Invalid OCR key path');
        }
        $mask = umask(0077);
        try {
            $lock = fopen($this->path . '.lock', 'c');
        } finally {
            umask($mask);
        }
        if ($lock === false) {
            throw new RuntimeException('OCR key lock unavailable');
        }
        try {
            if (!chmod($this->path . '.lock', 0600) || !flock($lock, LOCK_EX)) {
                throw new RuntimeException('OCR key lock unavailable');
            }
            if (!file_exists($this->path)) {
                $this->publish(['version' => 1, 'keys' => [self::generate(), self::generate()]]);
            }
            if (!is_readable($this->path) || (fileperms($this->path) & 0777) !== 0600) {
                throw new RuntimeException('OCR keys must be readable and owner-only');
            }
            $ring = json_decode(file_get_contents($this->path), true, 512, JSON_THROW_ON_ERROR);
            $this->validate($ring);
            return $operation($ring);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Reject corrupted key files rather than regenerating keys and invalidating live tokens. */
    private function validate(array $ring): void
    {
        if (ifset($ring, 'version') !== 1 || !is_array(ifset($ring, 'keys')) || count($ring['keys']) !== 2) {
            throw new RuntimeException('Invalid OCR key ring');
        }
        $kids = [];
        foreach ($ring['keys'] as $key) {
            if (!is_array($key) || !is_string(ifset($key, 'kid'))
                || !preg_match('/^[a-f0-9]{32}$/D', $key['kid'])
                || !is_int(ifset($key, 'retain_until')) || $key['retain_until'] < 0
                || !is_string(ifset($key, 'secret'))) {
                throw new RuntimeException('Invalid OCR key');
            }
            $secret = sodium_base642bin($key['secret'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            if (strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
                || !hash_equals($secret, sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(substr($secret, 0, SODIUM_CRYPTO_SIGN_SEEDBYTES))))
                || in_array($key['kid'], $kids, true)) {
                throw new RuntimeException('Invalid OCR key material');
            }
            $kids[] = $key['kid'];
        }
    }

    /** Atomic replacement under the installation lock; temp never has public permissions. */
    private function publish(array $ring): void
    {
        $json = json_encode($ring, JSON_THROW_ON_ERROR);
        $tmp = dirname($this->path) . '/.ht_ocr_keys_' . bin2hex(random_bytes(8)) . '.tmp';
        $mask = umask(0077);
        try {
            $file = fopen($tmp, 'xb');
        } finally {
            umask($mask);
        }
        if ($file === false) {
            throw new RuntimeException('Cannot provision OCR keys');
        }
        try {
            if (!chmod($tmp, 0600) || fwrite($file, $json) !== strlen($json) || !fflush($file) || !fsync($file)) {
                throw new RuntimeException('Cannot write OCR keys');
            }
            fclose($file);
            $file = null;
            if (!rename($tmp, $this->path)) {
                throw new RuntimeException('Cannot publish OCR keys');
            }
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
            if (file_exists($tmp)) {
                unlink($tmp);
            }
        }
    }

    /**
     * Mint only server-selected tenant and identity claims. $now is a unit-test clock.
     *
     * @return array{token:string, expires_at:int, audience:string}
     */
    public function mint(string $installation, string $subject, int $tenantId, ?int $now = null): array
    {
        if (Subject::parse($subject)['installation'] !== $installation || $tenantId < 1) {
            throw new InvalidArgumentException('Invalid OCR identity');
        }
        $now = $now ?? time();
        return $this->locked(function (array $ring) use ($installation, $subject, $tenantId, $now): array {
            $key = $ring['keys'][0];
            $header = self::base64url(json_encode(['typ' => 'JWT', 'alg' => 'EdDSA', 'kid' => $key['kid']], JSON_THROW_ON_ERROR));
            $payload = self::base64url(json_encode([
                'iss' => $installation,
                'aud' => self::AUDIENCE,
                'sub' => $subject,
                'tenant_id' => $tenantId,
                'iat' => $now,
                'exp' => $now + self::TTL,
                'jti' => bin2hex(random_bytes(16)),
            ], JSON_THROW_ON_ERROR));
            $input = "$header.$payload";
            $secret = sodium_base642bin($key['secret'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $signature = sodium_crypto_sign_detached($input, $secret);
            sodium_memzero($secret);
            return ['token' => $input . '.' . self::base64url($signature), 'expires_at' => $now + self::TTL, 'audience' => self::AUDIENCE];
        });
    }

    /** @return array{issuer:string, keys:list<array{kty:string, crv:string, use:string, alg:string, kid:string, x:string}>} */
    public function publicKeys(string $installation): array
    {
        return $this->locked(function (array $ring) use ($installation): array {
            $keys = [];
            foreach ($ring['keys'] as $key) {
                $secret = sodium_base642bin($key['secret'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
                $keys[] = [
                    'kty' => 'OKP', 'crv' => 'Ed25519', 'use' => 'sig', 'alg' => 'EdDSA',
                    'kid' => $key['kid'], 'x' => self::base64url(sodium_crypto_sign_publickey_from_secretkey($secret)),
                ];
                sodium_memzero($secret);
            }
            return ['issuer' => $installation, 'keys' => $keys];
        });
    }

    /**
     * Ops/CLI rotation: retain the old signing key for the full lifetime of outstanding tokens.
     * A second rotation is refused until the previous key's tokens have expired.
     *
     * @return string The new signing kid; both public keys remain discoverable.
     */
    public function rotate(?int $now = null): string
    {
        $now = $now ?? time();
        return $this->locked(function (array $ring) use ($now): string {
            if ($ring['keys'][1]['retain_until'] > $now) {
                throw new RuntimeException('Previous OCR key still verifies unexpired tokens');
            }
            $previous = $ring['keys'][0];
            $previous['retain_until'] = $now + self::TTL;
            $current = self::generate();
            $this->publish(['version' => 1, 'keys' => [$current, $previous]]);
            return $current['kid'];
        });
    }
}
