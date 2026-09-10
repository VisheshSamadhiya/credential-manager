<?php
/**
 * ONE-TIME migration:
 * Encrypts existing plaintext credential passwords in the database.
 *
 * Run from the application container after CREDENTIAL_ENCRYPTION_KEY is set:
 *   php /var/www/migrate-encrypt-passwords.php
 *
 * Delete this file after a successful migration.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/encryption.php';

$stmt = $pdo->query("
    SELECT id, credential_password
    FROM credentials
    WHERE credential_password IS NOT NULL
      AND credential_password <> ''
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$update = $pdo->prepare("
    UPDATE credentials
    SET credential_password = :password
    WHERE id = :id
");

$count = 0;

foreach ($rows as $row) {
    $value = (string)$row['credential_password'];

    // Sodium secretbox output used by this project is base64 encoded and
    // contains a 24-byte nonce plus ciphertext. If it can already be
    // decrypted with the configured key, leave it alone.
    $alreadyEncrypted = false;
    try {
        decryptCredential($value);
        $alreadyEncrypted = true;
    } catch (Throwable $e) {
        $alreadyEncrypted = false;
    }

    if ($alreadyEncrypted) {
        continue;
    }

    $encrypted = encryptCredential($value);

    $update->execute([
        ':password' => $encrypted,
        ':id' => (int)$row['id']
    ]);

    $count++;
}

echo "Encrypted {$count} credential password(s).\n";
?>
