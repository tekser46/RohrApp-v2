<?php
/**
 * Encryption — AES-256-CBC for sensitive data (IMAP passwords etc.)
 */
class Encryption
{
    private const METHOD = 'aes-256-cbc';

    /**
     * Encrypt a plaintext value
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::getKey();
        $iv  = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::METHOD));
        $encrypted = openssl_encrypt($plaintext, self::METHOD, $key, 0, $iv);
        // Store IV + encrypted data, base64 encoded
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypt an encrypted value
     */
    public static function decrypt(string $encrypted): string
    {
        $key  = self::getKey();
        $data = base64_decode($encrypted);
        [$iv, $ciphertext] = explode('::', $data, 2);
        return openssl_decrypt($ciphertext, self::METHOD, $key, 0, $iv);
    }

    /**
     * Get encryption key from config
     */
    private static function getKey(): string
    {
        static $key = null;
        if ($key === null) {
            $config = require dirname(__DIR__) . '/config/app.php';
            $key = hex2bin($config['encryption_key']);
        }
        return $key;
    }
}
