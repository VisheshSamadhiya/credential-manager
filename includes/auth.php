<?php
declare(strict_types=1);

const USER_SESSION_TTL = 300;
const PENDING_2FA_TTL = 300;
const TOTP_SETUP_TTL = 600;

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['login_authenticated_at']);
}

function isAdmin(): bool
{
    return isLoggedIn() && (($_SESSION['role'] ?? '') === 'admin');
}

function setAuthenticatedSession(array $user): void
{
    // This is called only after password and, when required, TOTP verification succeed.
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['full_name'] = (string)($user['full_name'] ?? '');
    $_SESSION['username'] = (string)($user['username'] ?? '');
    $_SESSION['role'] = (string)$user['role'];
    $_SESSION['login_authenticated_at'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function clearPendingTwoFactor(): void
{
    unset(
        $_SESSION['two_factor_pending_user_id'],
        $_SESSION['two_factor_pending_until'],
        $_SESSION['two_factor_pending_username']
    );
}

function destroyUserSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            (bool)$params['secure'], (bool)$params['httponly']
        );
    }

    session_destroy();
}

function enforceSessionExpiry(): void
{
    if (!isLoggedIn() || isAdmin()) {
        return;
    }

    $loginAt = (int)($_SESSION['login_authenticated_at'] ?? 0);
    if ($loginAt <= 0 || (time() - $loginAt) >= USER_SESSION_TTL) {
        destroyUserSession();
        header('Location: /login.php?session_expired=1');
        exit;
    }
}

function sessionSecondsRemaining(): ?int
{
    if (!isLoggedIn() || isAdmin()) {
        return null;
    }
    return max(0, USER_SESSION_TTL - (time() - (int)$_SESSION['login_authenticated_at']));
}

function installSessionTimer(): void
{
    if (!isLoggedIn() || isAdmin()) {
        return;
    }

    $remaining = sessionSecondsRemaining();
    if ($remaining === null) {
        return;
    }

    ob_start(static function (string $html) use ($remaining): string {
        if (stripos($html, '</body>') === false || stripos($html, 'id="credential-session-timer"') !== false) {
            return $html;
        }

        $timer = '<div id="credential-session-timer" style="position:fixed;right:18px;bottom:18px;z-index:99999;background:#222;color:#fff;padding:10px 14px;border-radius:8px;font:600 14px Arial,sans-serif;box-shadow:0 3px 12px rgba(0,0,0,.25);">Session expires in <span id="credential-session-countdown">05:00</span></div>'
            . '<script>(function(){var remaining=' . (int)$remaining . ';var el=document.getElementById("credential-session-countdown");function tick(){if(remaining<=0){el.textContent="00:00";window.location.replace("/logout.php?expired=1");return;}var m=Math.floor(remaining/60),s=remaining%60;el.textContent=String(m).padStart(2,"0")+":"+String(s).padStart(2,"0");remaining--;}tick();setInterval(tick,1000);})();</script>';

        return str_ireplace('</body>', $timer . '</body>', $html);
    });
}

function requireLogin(): void
{
    enforceSessionExpiry();
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
    installSessionTimer();
}

function hasRole(string $role): bool
{
    enforceSessionExpiry();
    return isLoggedIn() && (($_SESSION['role'] ?? '') === $role);
}

function hasAnyRole(array $roles): bool
{
    enforceSessionExpiry();
    return isLoggedIn() && in_array($_SESSION['role'] ?? '', $roles, true);
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        header('Location: /dashboard.php?access_denied=admin');
        exit;
    }
}

function canManageUsers(): bool { return hasRole('admin'); }
function canManageDepartments(): bool { return hasRole('admin'); }
function canAddEmployees(): bool { return hasRole('admin'); }
function canAddCredentials(): bool { return hasAnyRole(['admin','editor']); }
function canEditCredentials(): bool { return hasAnyRole(['admin','editor']); }
function canDeleteCredentials(): bool { return hasRole('admin'); }
function canViewCredentials(): bool { return hasAnyRole(['admin','editor','viewer']); }

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
