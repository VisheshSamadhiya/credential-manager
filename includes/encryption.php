<?php
/**
 * Credential password encryption/decryption.
 *
 * The encryption key MUST be supplied through the CREDENTIAL_ENCRYPTION_KEY
 * environment variable. It is never stored in the database.
 *
 * Generate a key once with:
 *   openssl rand -base64 32
 */
function getCredentialEncryptionKey(): string
{
    $key = getenv('CREDENTIAL_ENCRYPTION_KEY');

    if ($key === false || trim($key) === '') {
        throw new RuntimeException('CREDENTIAL_ENCRYPTION_KEY is not configured.');
    }

    $decoded = base64_decode($key, true);

    if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('CREDENTIAL_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
    }

    return $decoded;
}

function encryptCredential(string $plaintext): string
{
    $key = getCredentialEncryptionKey();

    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

    // Store nonce + ciphertext as one base64 string.
    return base64_encode($nonce . $ciphertext);
}

function decryptCredential(string $encoded): string
{
    $key = getCredentialEncryptionKey();

    $data = base64_decode($encoded, true);

    if ($data === false || strlen($data) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        throw new RuntimeException('Invalid encrypted credential.');
    }

    $nonce = substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

    if ($plaintext === false) {
        throw new RuntimeException('Unable to decrypt credential.');
    }

    return $plaintext;
}
?>
