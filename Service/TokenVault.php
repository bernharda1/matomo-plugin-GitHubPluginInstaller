<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Service;

use Piwik\Plugins\GitHubPluginInstaller\Exception\SecurityException;

/**
 * Encrypts/decrypts GitHub personal access tokens at rest.
 *
 * Mirrors the AES-256-CBC + HMAC-SHA256 scheme used by
 * VisitorFlowIntelligence\Service\CacheKeyEncryption, kept local to this
 * plugin to avoid a cross-plugin runtime dependency.
 */
class TokenVault
{
    private const ALGORITHM = 'AES-256-CBC';
    private const HASH_ALGORITHM = 'sha256';

    private static ?string $encryptionKey = null;

    public static function setEncryptionKey(string $key): void
    {
        if (strlen($key) < 32) {
            throw new SecurityException('Encryption key must be at least 32 characters');
        }

        self::$encryptionKey = $key;
    }

    private static function getEncryptionKey(): string
    {
        if (self::$encryptionKey !== null) {
            return self::$encryptionKey;
        }

        try {
            $salt = \Piwik\Config::getInstance()->General['salt'] ?? null;
            if (is_string($salt) && strlen($salt) >= 32) {
                return $salt;
            }
        } catch (\Throwable $e) {
            // fall through to hard failure below
        }

        throw new SecurityException(
            'No usable encryption key available (General[salt] missing or too short). ' .
            'Refusing to store GitHub tokens without a strong key.'
        );
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::getEncryptionKey();
        $iv = openssl_random_pseudo_bytes((int) openssl_cipher_iv_length(self::ALGORITHM));

        if ($iv === false) {
            throw new SecurityException('Failed to generate IV for encryption');
        }

        $encrypted = openssl_encrypt($plaintext, self::ALGORITHM, $key, 0, $iv);

        if ($encrypted === false) {
            throw new SecurityException('Failed to encrypt token');
        }

        $combined = base64_encode($iv . $encrypted);
        $hash = hash_hmac(self::HASH_ALGORITHM, $combined, $key);

        return $hash . '::' . $combined;
    }

    public static function decrypt(string $ciphertext): string
    {
        if (strpos($ciphertext, '::') === false) {
            throw new SecurityException('Invalid encrypted token format');
        }

        [$hash, $combined] = explode('::', $ciphertext, 2);

        $key = self::getEncryptionKey();
        $expectedHash = hash_hmac(self::HASH_ALGORITHM, $combined, $key);

        if (!hash_equals($expectedHash, $hash)) {
            throw new SecurityException('Stored token has been tampered with');
        }

        $decoded = base64_decode($combined, true);
        if ($decoded === false) {
            throw new SecurityException('Failed to decode stored token');
        }

        $ivLength = (int) openssl_cipher_iv_length(self::ALGORITHM);
        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt($encrypted, self::ALGORITHM, $key, 0, $iv);
        if ($decrypted === false) {
            throw new SecurityException('Failed to decrypt stored token');
        }

        return $decrypted;
    }
}
