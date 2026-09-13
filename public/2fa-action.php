<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity_logger.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid request.');
}


$userId = (int) ($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($userId <= 0 || !in_array($action, ['disable', 'reset'], true)) {
    header('Location: /manage-users.php?error=invalid_2fa_action');
    exit;
}

$stmt = $pdo->prepare('SELECT id, full_name, username, two_factor_enabled FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /manage-users.php?error=not_found');
    exit;
}

$update = $pdo->prepare(
    'UPDATE users
     SET two_factor_enabled = 0,
         two_factor_secret = NULL,
         two_factor_confirmed_at = NULL
     WHERE id = :id'
);
$update->execute([':id' => $userId]);

$logAction = $action === 'disable' ? '2FA_DISABLED' : '2FA_RESET';
$description = ($action === 'disable' ? 'Admin disabled 2FA for user: ' : 'Admin reset 2FA for user: ') . $user['username'];
logActivity($pdo, $logAction, $description);

header('Location: /manage-users.php?' . ($action === 'disable' ? '2fa_disabled=1' : '2fa_reset=1'));
exit;
