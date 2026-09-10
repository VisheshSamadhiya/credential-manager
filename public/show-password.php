<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/encryption.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

/*
 * View/Copy plaintext only for users allowed to manage credentials.
 * Viewers can still see credential metadata, but never receive the secret.
 */
if (!canAddCredentials()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not authorized to view passwords.']);
    exit;
}

$credentialId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$credentialId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid credential ID.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, service_name, credential_password, employee_id
        FROM credentials
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $credentialId]);
    $credential = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$credential) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Credential not found.']);
        exit;
    }

    $password = decryptCredential($credential['credential_password']);

    $action = isset($_GET['action']) && $_GET['action'] === 'copy'
        ? 'credential_copied'
        : 'credential_viewed';

    logActivity(
        $pdo,
        $action,
        'Password secret accessed for credential: ' . $credential['service_name'],
        (int)$credential['employee_id'],
        $credentialId
    );

    echo json_encode([
        'success' => true,
        'password' => $password
    ]);
} catch (Throwable $e) {
    error_log('Credential secret access failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to access password.']);
}
?>
