<?php
declare(strict_types=1);

/**
 * Reversible encryption for stored credential secrets.
 * Application login passwords remain one-way password hashes.
 */

function getCredentialEncryptionKey(): string
{
    $configFile = __DIR__ . '/../config/client.php';

    if (!is_file($configFile)) {
        throw new RuntimeException('Client configuration is not installed.');
    }

    $config = require $configFile;
    $key = trim((string)($config['credential_encryption_key'] ?? ''));

    if ($key === '') {
        throw new RuntimeException(
            'credential_encryption_key is not configured.'
        );
    }

    $decoded = base64_decode($key, true);

    if (
        $decoded !== false &&
        strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES
    ) {
        return $decoded;
    }

    if (strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        return $key;
    }

    throw new RuntimeException(
        'credential_encryption_key must be a base64-encoded 32-byte key or a raw 32-byte key.'
    );
}

function encryptCredential(string $plaintext): string
{
    $key = getCredentialEncryptionKey();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    $ciphertext = sodium_crypto_secretbox(
        $plaintext,
        $nonce,
        $key
    );

    return base64_encode($nonce . $ciphertext);
}

function decryptCredential(string $encoded): string
{
    $key = getCredentialEncryptionKey();
    $data = base64_decode($encoded, true);

    if (
        $data === false ||
        strlen($data) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
    ) {
        throw new RuntimeException('Invalid encrypted credential.');
    }

    $nonce = substr(
        $data,
        0,
        SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
    );

    $ciphertext = substr(
        $data,
        SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
    );

    $plaintext = sodium_crypto_secretbox_open(
        $ciphertext,
        $nonce,
        $key
    );

    if ($plaintext === false) {
        throw new RuntimeException('Unable to decrypt credential.');
    }

    return $plaintext;
}
