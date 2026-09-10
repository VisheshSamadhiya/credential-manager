<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();


if (!canDeleteCredentials()) {

    header(
        'Location: /credentials.php?access_denied=1'
    );

    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /credentials.php');
    exit;
}

$userId = $_SESSION['user_id'];

$credentialId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$credentialId) {
    header('Location: /credentials.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Delete Only User's Own Credential
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "DELETE FROM credentials
     WHERE id = :id
     AND created_by = :user_id"
);

$stmt->execute([
    ':id' => $credentialId,
    ':user_id' => $userId
]);


header(
    'Location: /credentials.php?deleted=1'
);

exit;
