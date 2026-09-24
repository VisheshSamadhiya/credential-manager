<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';

if (isLoggedIn()) {
    logActivity($pdo, isset($_GET['expired']) ? 'SESSION_EXPIRED' : 'LOGOUT', isset($_GET['expired']) ? 'Session expired after 5 minutes.' : 'User logged out.');
}

destroyUserSession();
header('Location: /login.php' . (isset($_GET['expired']) ? '?session_expired=1' : '?logged_out=1'));
exit;
