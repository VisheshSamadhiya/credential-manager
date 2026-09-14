<?php
declare(strict_types=1);

/**
 * Credential password encryption/decryption.
 *
 * Supports:
 * 1. Base64-encoded 32-byte Sodium keys.
 * 2. Legacy raw 32-character/32-byte keys.
 *
 * IMPORTANT:
 * Never change the existing encryption key unless all existing
 * encrypted credentials have first been migrated to a new key.
 */

function getCredentialEncryptionKey(): string
{
    $key = getenv('CREDENTIAL_ENCRYPTION_KEY');

    if ($key === false || trim($key) === '') {
        throw new RuntimeException(
            'CREDENTIAL_ENCRYPTION_KEY is not configured.'
        );
    }

    $key = trim($key);

    /*
     * Preferred format:
     * Base64-encoded 32-byte key.
     */
    $decoded = base64_decode($key, true);

    if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        return $decoded;
    }

    /*
     * Legacy compatibility:
     * The existing installation may use the key directly as
     * a 32-byte Sodium key.
     *
     * Do NOT convert or replace this key.
     */
    if (strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        return $key;
    }

    throw new RuntimeException(
        'CREDENTIAL_ENCRYPTION_KEY must be either a base64-encoded 32-byte key or a raw 32-byte key.'
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

    // Store nonce + ciphertext as one base64 string.
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
