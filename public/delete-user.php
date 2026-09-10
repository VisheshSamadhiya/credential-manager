<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity_logger.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| Only Admin Can Delete Users
|--------------------------------------------------------------------------
*/

if (($_SESSION['role'] ?? '') !== 'admin') {

    header(
        'Location: /manage-users.php?access_denied=1'
    );

    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /manage-users.php'
    );

    exit;
}


$userId = isset($_POST['user_id'])
    ? (int) $_POST['user_id']
    : 0;


if ($userId <= 0) {

    header(
        'Location: /manage-users.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Prevent Admin From Deleting Himself
|--------------------------------------------------------------------------
*/

if ($userId === (int) $_SESSION['user_id']) {

    header(
        'Location: /manage-users.php?error=self_delete'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get User Information Before Deleting
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        id,
        full_name,
        username,
        role
    FROM users
    WHERE id = :id"
);

$stmt->execute([
    ':id' => $userId
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$user) {

    header(
        'Location: /manage-users.php?error=not_found'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Delete User
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare(
        "DELETE FROM users
        WHERE id = :id"
    );

    $stmt->execute([
        ':id' => $userId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    */

    logActivity(
        $pdo,
        'USER_DELETED',
        'Deleted user: ' .
        $user['full_name'] .
        ' (' . $user['username'] . ')'
    );


    header(
        'Location: /manage-users.php?deleted=1'
    );

    exit;

} catch (PDOException $e) {

    header(
        'Location: /manage-users.php?error=delete_failed'
    );

    exit;
}
