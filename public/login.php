<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$expired = isset($_GET['expired']) || isset($_GET['session_expired']);
$twoFactorRequired = isset($_GET['2fa']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, full_name, username, password, role, two_factor_enabled, two_factor_secret FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, (string)$user['password'])) {
                session_regenerate_id(true);
                $_SESSION = [];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                if ((int)$user['two_factor_enabled'] === 1) {
                    if (empty($user['two_factor_secret'])) {
                        $error = 'Two-factor authentication is enabled but not configured correctly. Contact an administrator.';
                    } else {
                        $_SESSION['two_factor_pending_user_id'] = (int)$user['id'];
                        $_SESSION['two_factor_pending_until'] = time() + PENDING_2FA_TTL;
                        $_SESSION['two_factor_pending_username'] = (string)$user['username'];
                        logActivity($pdo, 'LOGIN_2FA_REQUIRED', '2FA verification required for user: ' . $user['username']);
                        header('Location: /verify-2fa.php');
                        exit;
                    }
                } else {
                    setAuthenticatedSession($user);
                    logActivity($pdo, 'LOGIN_SUCCESS', 'User logged in: ' . $user['username']);
                    header('Location: /dashboard.php');
                    exit;
                }
            } else {
                $error = 'Invalid username or password.';
                try { logActivity($pdo, 'LOGIN_FAILED', 'Failed login attempt for username: ' . $username); } catch (Throwable $ignored) {}
            }
        } catch (PDOException $e) {
            error_log('Login failed: ' . $e->getMessage());
            $error = 'Unable to process login. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Credential Manager - Login</title>
<style>body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh}.login-box{background:#fff;padding:35px;width:350px;border-radius:10px;box-shadow:0 5px 20px rgba(0,0,0,.1)}h1{text-align:center;margin-bottom:25px}input{width:100%;padding:12px;margin:10px 0;box-sizing:border-box}button{width:100%;padding:12px;border:none;background:#222;color:#fff;cursor:pointer}.error{color:#b00020;margin-bottom:15px}.success{color:#155724;background:#d4edda;padding:12px;border-radius:6px;margin-bottom:15px}</style></head>
<body><div class="login-box"><h1>Credential Manager</h1>
<?php if ($expired): ?><div class="success">Your session expired. Please log in again.</div><?php endif; ?>
<?php if ($twoFactorRequired): ?><div class="success">Two-factor authentication is required.</div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" autocomplete="off"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="text" name="username" placeholder="Username" required autofocus><input type="password" name="password" placeholder="Password" required><button type="submit">Login</button></form>
</div></body></html>
