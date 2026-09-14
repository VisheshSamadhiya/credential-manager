<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/encryption.php';

/*
 * One-time migration:
 *
 * Encrypt existing plaintext credential_password values
 * using the application's existing CREDENTIAL_ENCRYPTION_KEY.
 *
 * IMPORTANT:
 * - Do NOT change the encryption key.
 * - Run a database backup before executing this.
 * - This script does not print passwords.
 */

echo "Credential password encryption migration\n";
echo "=========================================\n\n";

try {
    /*
     * Verify that the existing encryption key works.
     */
    $key = getCredentialEncryptionKey();

    echo "Encryption key loaded successfully.\n";
    echo "Key length: " . strlen($key) . " bytes\n\n";

    /*
     * Get all credentials that have a password.
     */
    $stmt = $pdo->query("
        SELECT
            id,
            service_name,
            credential_password
        FROM credentials
        WHERE credential_password IS NOT NULL
          AND credential_password <> ''
        ORDER BY id
    ");

    $credentials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$credentials) {
        echo "No credentials found to migrate.\n";
        exit(0);
    }

    echo "Found " . count($credentials) . " credential(s).\n\n";

    $pdo->beginTransaction();

    $updateStmt = $pdo->prepare("
        UPDATE credentials
        SET credential_password = :password
        WHERE id = :id
    ");

    $encryptedCount = 0;
    $alreadyEncryptedCount = 0;

    foreach ($credentials as $credential) {

        $id = (int)$credential['id'];
        $serviceName = (string)$credential['service_name'];
        $currentValue = (string)$credential['credential_password'];

        /*
         * Determine whether the value is already compatible
         * with the current decryptCredential() implementation.
         *
         * If it successfully decrypts, leave it untouched.
         */
        $alreadyEncrypted = false;

        try {
            decryptCredential($currentValue);
            $alreadyEncrypted = true;
        } catch (Throwable $e) {
            $alreadyEncrypted = false;
        }

        if ($alreadyEncrypted) {
            echo "[SKIP] ID {$id} - already encrypted\n";
            $alreadyEncryptedCount++;
            continue;
        }

        /*
         * Treat the current database value as plaintext and
         * encrypt it using the existing application key.
         */
        $encryptedPassword = encryptCredential($currentValue);

        $updateStmt->execute([
            ':password' => $encryptedPassword,
            ':id'       => $id
        ]);

        echo "[ENCRYPTED] ID {$id} - {$serviceName}\n";

        $encryptedCount++;
    }

    $pdo->commit();

    echo "\n=========================================\n";
    echo "Migration completed successfully.\n";
    echo "Encrypted: {$encryptedCount}\n";
    echo "Already encrypted: {$alreadyEncryptedCount}\n";
    echo "=========================================\n";

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\nMIGRATION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";

    exit(1);
}
