<?php
declare(strict_types=1);

namespace ClaudePulse;

class Crypto
{
    private static ?string $encryptionKey = null;

    /**
     * Get or initialize the master encryption key
     */
    public static function getMasterKey(): string
    {
        if (self::$encryptionKey !== null) {
            return self::$encryptionKey;
        }

        // Check environment variable first
        $envKey = getenv('CLAUDEPULSE_SECRET_KEY');
        if (!empty($envKey)) {
            self::$encryptionKey = hash('sha256', $envKey, true);
            return self::$encryptionKey;
        }

        // Check secret.key file in database directory
        $keyFile = __DIR__ . '/../database/secret.key';
        if (file_exists($keyFile)) {
            $keyData = @file_get_contents($keyFile);
            if ($keyData !== false && strlen($keyData) >= 32) {
                self::$encryptionKey = substr($keyData, 0, 32);
                return self::$encryptionKey;
            }
        }

        // Generate a new 32-byte master encryption key
        $newKey = random_bytes(32);
        @file_put_contents($keyFile, $newKey);
        @chmod($keyFile, 0600);
        self::$encryptionKey = $newKey;

        return self::$encryptionKey;
    }

    /**
     * Alias for getMasterKey
     */
    public static function getSecretKey(): string
    {
        return self::getMasterKey();
    }

    /**
     * Authenticated AES-256-CBC Encryption (Encrypt-then-MAC)
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key = self::getMasterKey();
        $encKey = hash_hkdf('sha256', $key, 32, 'encryption');
        $macKey = hash_hkdf('sha256', $key, 32, 'authentication');

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            $plaintext,
            'AES-256-CBC',
            $encKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        $hmac = hash_hmac('sha256', $iv . $ciphertext, $macKey, true);
        return base64_encode($iv . $hmac . $ciphertext);
    }

    /**
     * Authenticated AES-256-CBC Decryption
     */
    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) < 48) {
            throw new \RuntimeException('Invalid ciphertext format.');
        }

        $iv = substr($decoded, 0, 16);
        $hmac = substr($decoded, 16, 32);
        $ciphertext = substr($decoded, 48);

        $key = self::getMasterKey();
        $encKey = hash_hkdf('sha256', $key, 32, 'encryption');
        $macKey = hash_hkdf('sha256', $key, 32, 'authentication');

        $computedHmac = hash_hmac('sha256', $iv . $ciphertext, $macKey, true);
        if (!hash_equals($hmac, $computedHmac)) {
            throw new \RuntimeException('Ciphertext MAC verification failed (corrupted or tampered).');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $encKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed.');
        }

        return $plaintext;
    }

    /**
     * Generate a new Gateway API Key (sk-cp-...)
     */
    public static function generateGatewayKey(): array
    {
        $rawBytes = random_bytes(32);
        $key = 'sk-cp-' . bin2hex($rawBytes);
        $hash = hash('sha256', $key);
        $prefix = substr($key, 0, 14) . '...' . substr($key, -4);

        return [
            'key' => $key,
            'hash' => $hash,
            'prefix' => $prefix
        ];
    }

    /**
     * Mask an API key for safe UI display (e.g. sk-ant-api03-••••••8d4a)
     */
    public static function maskKey(string $key): string
    {
        $length = strlen($key);
        if ($length <= 12) {
            return '••••••••';
        }

        $prefix = substr($key, 0, min(10, (int)($length * 0.25)));
        $suffix = substr($key, -4);
        return $prefix . '••••••••' . $suffix;
    }

    /**
     * Secure password hashing
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify password
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate a secure random token (e.g. for CSRF)
     */
    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}

