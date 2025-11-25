<?php

namespace App\Service;

class TokenCipher
{
    public function __construct(private readonly string $encryptionKey)
    {
    }

    public function encrypt(string $plain): string
    {
        $key = $this->getKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $key);

        return base64_encode($nonce . $cipher);
    }

    public function decrypt(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        if (false === $decoded || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted payload');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return sodium_crypto_secretbox_open($cipher, $nonce, $this->getKey());
    }

    private function getKey(): string
    {
        $key = base64_decode($this->encryptionKey, true);
        if (!$key || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('APP_TOKEN_KEY must be a base64-encoded 32-byte key.');
        }

        return $key;
    }
}
