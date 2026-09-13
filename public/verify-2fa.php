<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/two_factor.php';
require_once __DIR__ . '/../includes/encryption.php';

$pendingUserId = (int)($_SESSION['two_factor_pending_user_id'] ?? 0);
$pendingUntil = (int)($_SESSION['two_factor_pending_until'] ?? 0);

if ($pendingUserId <= 0 || $pendingUntil <= time()) {
    clearPendingTwoFactor();
    header('Location: /login.php?expired=1');
    exit;
}

$error = '';
$stmt = $pdo->prepare('SELECT id, full_name, username, role, two_factor_enabled, two_factor_secret FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $pendingUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || (int)$user['two_factor_enabled'] !== 1 || empty($user['two_factor_secret'])) {
    clearPendingTwoFactor();
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $code = trim((string)($_POST['code'] ?? ''));
        try { $secret = decryptCredential((string)$user['two_factor_secret']); } catch (Throwable $e) { $secret = ''; error_log('2FA secret decryption failed: ' . $e->getMessage()); }

        if ($secret !== '' && verifyTotpCode($secret, $code)) {
            setAuthenticatedSession($user);
            logActivity($pdo, '2FA_SUCCESS', '2FA verification successful for user: ' . $user['username']);
            logActivity($pdo, 'LOGIN_SUCCESS', 'User logged in with 2FA: ' . $user['username']);
            header('Location: /dashboard.php');
            exit;
        }

        $error = 'Invalid or expired authentication code.';
        logActivity($pdo, '2FA_FAILED', '2FA verification failed for user: ' . $user['username']);
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Two-Factor Authentication</title>
<style>body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh}.box{background:#fff;padding:35px;width:380px;border-radius:10px;box-shadow:0 5px 20px rgba(0,0,0,.1)}h1{text-align:center;margin-top:0}.hint{color:#555;line-height:1.5}.error{background:#f8d7da;color:#721c24;padding:12px;border-radius:6px;margin:15px 0}input{width:100%;padding:13px;box-sizing:border-box;margin:10px 0;font-size:22px;letter-spacing:6px;text-align:center}button{width:100%;padding:13px;border:0;background:#222;color:#fff;cursor:pointer;border-radius:5px}</style></head>
<body><div class="box"><h1>Two-Factor Authentication</h1><p class="hint">Enter the 6-digit code from your authenticator app.</p>
<?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" autocomplete="off"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus><button type="submit">Verify &amp; Continue</button></form>
</div></body></html>
