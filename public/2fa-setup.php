<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/two_factor.php';
require_once __DIR__ . '/../includes/encryption.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid request.');
}

$userId = (int) ($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: /manage-users.php?error=invalid_user');
    exit;
}

$stmt = $pdo->prepare('SELECT id, full_name, username, role, two_factor_enabled, two_factor_secret FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /manage-users.php?error=not_found');
    exit;
}

if ((int) $user['two_factor_enabled'] === 1) {
    header('Location: /manage-users.php?error=2fa_already_enabled');
    exit;
}

$pendingKey = 'two_factor_setup_' . $userId;

if (isset($_SESSION[$pendingKey]['created_at']) && (time() - (int)$_SESSION[$pendingKey]['created_at']) >= TOTP_SETUP_TTL) {
    unset($_SESSION[$pendingKey]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $secret = $_SESSION[$pendingKey]['secret'] ?? '';

    if ($secret === '' || !verifyTotpCode($secret, $code)) {
        $error = 'Invalid authenticator code. Enter the current 6-digit code and try again.';
    } else {
        $update = $pdo->prepare(
            'UPDATE users
             SET two_factor_enabled = 1,
                 two_factor_secret = :secret,
                 two_factor_confirmed_at = NOW()
             WHERE id = :id'
        );
        $update->execute([':secret' => encryptCredential($secret), ':id' => $userId]);

        unset($_SESSION[$pendingKey]);
        logActivity($pdo, '2FA_ENABLED', 'Admin enabled 2FA for user: ' . $user['username']);
        header('Location: /manage-users.php?2fa_enabled=1');
        exit;
    }
} else {
    $error = '';
}

if (!isset($_SESSION[$pendingKey]['secret'])) {
    $_SESSION[$pendingKey] = [
        'secret' => generateTotpSecret(),
        'created_at' => time()
    ];
}

$secret = $_SESSION[$pendingKey]['secret'];
$otpAuthUri = buildOtpAuthUri($secret, $user['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set Up 2FA</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f9;margin:0;color:#333}.container{max-width:760px;margin:50px auto;padding:30px;background:#fff;border-radius:10px;box-shadow:0 5px 20px rgba(0,0,0,.08)}.secret{font:700 24px monospace;letter-spacing:2px;background:#f1f5f9;padding:15px;border-radius:8px;word-break:break-all}.uri{font:13px monospace;word-break:break-all;background:#f8fafc;padding:12px;border-radius:8px}.error{background:#f8d7da;color:#721c24;padding:12px;border-radius:6px;margin:15px 0}input{padding:13px;width:220px;font-size:20px;letter-spacing:5px;text-align:center}button,.back{display:inline-block;padding:12px 18px;background:#222;color:#fff;border:0;border-radius:5px;text-decoration:none;cursor:pointer}.steps{line-height:1.7}
</style>
    <link rel="stylesheet" href="/assets/theme.css">

</head>
<body>
<div class="container">
<h1>Set Up 2FA</h1>
<p>Admin setup for <strong><?= htmlspecialchars($user['full_name']) ?></strong> (<?= htmlspecialchars($user['username']) ?>).</p>
<ol class="steps">
<li>Open Google Authenticator, Microsoft Authenticator, Authy, 1Password, or another TOTP authenticator.</li>
<li>Add a new account and choose <strong>enter setup key manually</strong>.</li>
<li>Use the secret below. The authenticator must use SHA-1, 6 digits, and a 30-second period.</li>
</ol>
<div class="secret"><?= htmlspecialchars($secret) ?></div>
<p><strong>Setup URI:</strong></p>
<div class="uri"><?= htmlspecialchars($otpAuthUri) ?></div>
<p>After adding it, enter the current 6-digit code below to confirm and enable 2FA.</p>
<?php if (!empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="user_id" value="<?= (int) $userId ?>">
<input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required>
<button type="submit">Confirm &amp; Enable 2FA</button>
<a class="back" href="/manage-users.php">Cancel</a>
</form>
</div>
    <script src="/assets/theme.js"></script>

</body>
</html>
