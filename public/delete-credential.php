<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity_logger.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /credentials.php');
    exit;
}

$credentialId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$credentialId) {
    header('Location: /credentials.php?error=invalid_id');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Credential Before Deleting
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        id,
        service_name,
        employee_id
     FROM credentials
     WHERE id = :id
     LIMIT 1"
);

$stmt->execute([
    ':id' => $credentialId
]);

$credential = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$credential) {
    header('Location: /credentials.php?error=not_found');
    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Credential
|--------------------------------------------------------------------------
|
| Admin is allowed to delete ANY credential.
| Do NOT restrict this by created_by.
|
*/

try {

    $deleteStmt = $pdo->prepare(
        "DELETE FROM credentials
         WHERE id = :id"
    );

    $deleteStmt->execute([
        ':id' => $credentialId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    */

    logActivity(
        $pdo,
        'credential_deleted',
        'Administrator deleted credential: '
        . $credential['service_name'],
        $credential['employee_id']
            ? (int) $credential['employee_id']
            : null,
        $credentialId
    );

    header(
        'Location: /credentials.php?deleted=1'
    );

    exit;

} catch (PDOException $e) {

    error_log(
        'Credential deletion failed: '
        . $e->getMessage()
    );

    header(
        'Location: /credentials.php?error=delete_failed'
    );

    exit;
}