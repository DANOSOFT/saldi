<?php
/**
 * Simple JWT implementation for Saldi API
 * Supports encoding and decoding of JWT tokens
 *
 * setSecret() must be called with an install-specific, >=256-bit random
 * secret before encode()/decode() are used — there is no built-in default.
 * See restapi/core/BaseEndpoint.php for where that secret is loaded and set
 * (SD-587).
 */

class JWT {
    private static $secret;

    public static function secretPath(): string {
        return __DIR__ . '/../.ht_jwt_secret.bin';
    }

    public static function setSecret($secret) {
        self::$secret = $secret;
    }
    
    public static function encode($payload, $expiration = 3600) {
        if (empty(self::$secret)) {
            throw new \RuntimeException('JWT signing secret is not configured; JWT::setSecret() must be called before encoding.');
        }

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        
        // Add expiration time
        $payload['exp'] = time() + $expiration;
        $payload['iat'] = time();
        
        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", self::$secret, true);
        $base64Signature = self::base64UrlEncode($signature);
        
        return "$base64Header.$base64Payload.$base64Signature";
    }
    
    public static function decode($token) {
        if (empty(self::$secret)) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        list($base64Header, $base64Payload, $base64Signature) = $parts;
        
        // Verify signature
        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", self::$secret, true);
        $expectedSignature = self::base64UrlEncode($signature);
        
        if ($base64Signature !== $expectedSignature) {
            return null;
        }
        
        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

