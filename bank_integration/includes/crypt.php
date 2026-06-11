<?php
// Provides oauthEncrypt() and oauthDecrypt() for storing OAuth session data at rest.
//
// Algorithm : AES-256-GCM (authenticated encryption — detects tampering)
// Key source : bank_integration/.ht_oauth_key.bin (32 raw bytes, web-inaccessible)
// Wire format: base64( nonce[12] || tag[16] || ciphertext )
//
// Generate the key once on the server:
//   php -r "file_put_contents(__DIR__ . '/../.ht_oauth_key.bin', random_bytes(32));"
//
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/crypt.php --- patch 0.0.1 --- 2026-05-26 ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20260526 NTR - Initial version.

if (!function_exists('oauthEncrypt')) {
    function _oauthLoadKey(): string {
        $path = __DIR__ . '/../.ht_oauth_key.bin';
        if (!is_readable($path)) {
            throw new RuntimeException('OAuth key file not found. Generate it with: php -r "file_put_contents(\'bank_integration/.ht_oauth_key.bin\', random_bytes(32));"');
        }
        $key = file_get_contents($path);
        if (strlen($key) !== 32) {
            throw new RuntimeException('OAuth key must be exactly 32 bytes.');
        }
        return $key;
    }

    /**
     * Encrypts a string using AES-256-GCM.
     *
     * @param  string $plaintext  The data to encrypt (e.g. json_encode($_SESSION['OAuth'])).
     * @return string             base64-encoded payload safe to store in the database.
     */
    function oauthEncrypt(string $plaintext): string {
        $key  = _oauthLoadKey();
        $iv   = random_bytes(12); // 96-bit nonce — unique per encryption
        $tag  = '';

        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

        if ($cipher === false) {
            throw new RuntimeException('oauthEncrypt: encryption failed.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    /**
     * Decrypts a value produced by oauthEncrypt().
     * Returns null if the ciphertext is invalid or has been tampered with.
     *
     * @param  string      $encoded  The base64 value from the database.
     * @return string|null           The original plaintext, or null on any failure.
     */
    function oauthDecrypt(string $encoded): ?string {
        try {
            $key = _oauthLoadKey();
        } catch (RuntimeException $e) {
            return null;
        }

        $raw = base64_decode($encoded, true);

        // Must have at least 12 (nonce) + 16 (tag) bytes before the ciphertext.
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }

        $iv     = substr($raw,  0, 12);
        $tag    = substr($raw, 12, 16);
        $cipher = substr($raw, 28);

        $plaintext = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        // openssl_decrypt returns false if the tag doesn't match (tampered/corrupt).
        return $plaintext === false ? null : $plaintext;
    }
}
