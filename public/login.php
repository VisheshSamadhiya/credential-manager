<?php
declare(strict_types=1);

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
            $stmt = $pdo->prepare(
                'SELECT
                    id,
                    full_name,
                    username,
                    password,
                    role,
                    two_factor_enabled,
                    two_factor_secret
                 FROM users
                 WHERE username = ?
                 LIMIT 1'
            );

            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, (string)$user['password'])) {

                /*
                 * Regenerate the session before establishing either
                 * the authenticated session or the pending 2FA session.
                 */
                session_regenerate_id(true);

                $_SESSION = [];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                /*
                 * 2FA-enabled users do not receive a full authenticated
                 * session until their TOTP code has been verified.
                 */
                if ((int)$user['two_factor_enabled'] === 1) {

                    if (empty($user['two_factor_secret'])) {
                        $error =
                            'Two-factor authentication is enabled but not configured correctly. Contact an administrator.';
                    } else {

                        $_SESSION['two_factor_pending_user_id'] =
                            (int)$user['id'];

                        $_SESSION['two_factor_pending_until'] =
                            time() + PENDING_2FA_TTL;

                        $_SESSION['two_factor_pending_username'] =
                            (string)$user['username'];

                        logActivity(
                            $pdo,
                            'LOGIN_2FA_REQUIRED',
                            '2FA verification required for user: ' .
                            $user['username']
                        );

                        header('Location: /verify-2fa.php');
                        exit;
                    }

                } else {

                    /*
                     * Normal login for users without 2FA.
                     */
                    setAuthenticatedSession($user);

                    logActivity(
                        $pdo,
                        'LOGIN_SUCCESS',
                        'User logged in: ' . $user['username']
                    );

                    header('Location: /dashboard.php');
                    exit;
                }

            } else {

                $error = 'Invalid username or password.';

                try {
                    logActivity(
                        $pdo,
                        'LOGIN_FAILED',
                        'Failed login attempt for username: ' . $username
                    );
                } catch (Throwable $ignored) {
                    // Never expose logging failures to the user.
                }
            }

        } catch (PDOException $e) {

            error_log('Login failed: ' . $e->getMessage());

            $error =
                'Unable to process login. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Credential Manager - Login</title>

    <link
        rel="stylesheet"
        href="/assets/theme.css"
    >
</head>

<body class="auth-page login-page">

    <main class="auth-container">

        <section class="auth-card login-card">

            <!-- Brand -->
            <div class="auth-brand">

                <div class="auth-brand-icon" aria-hidden="true">
                    <svg
                        viewBox="0 0 24 24"
                        width="30"
                        height="30"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <rect
                            x="4"
                            y="10"
                            width="16"
                            height="10"
                            rx="2"
                        />
                        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        <circle
                            cx="12"
                            cy="15"
                            r="1"
                        />
                        <path d="M12 16v2" />
                    </svg>
                </div>

                <div class="auth-brand-text">
                    <h1>Credential Manager</h1>
                    <p>Secure credential management</p>
                </div>

            </div>

            <!-- Heading -->
            <div class="auth-heading">
                <h2>Sign in</h2>
                <p>
                    Enter your credentials to access the management portal.
                </p>
            </div>

            <!-- Session expired -->
            <?php if ($expired): ?>
                <div
                    class="alert alert-success auth-alert"
                    role="status"
                >
                    <span class="alert-icon" aria-hidden="true">
                        <svg
                            viewBox="0 0 24 24"
                            width="18"
                            height="18"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 8v4l2.5 1.5" />
                        </svg>
                    </span>

                    <span>
                        Your session expired. Please sign in again.
                    </span>
                </div>
            <?php endif; ?>

            <!-- 2FA required -->
            <?php if ($twoFactorRequired): ?>
                <div
                    class="alert alert-info auth-alert"
                    role="status"
                >
                    <span class="alert-icon" aria-hidden="true">
                        <svg
                            viewBox="0 0 24 24"
                            width="18"
                            height="18"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect
                                x="5"
                                y="11"
                                width="14"
                                height="9"
                                rx="2"
                            />
                            <path d="M8 11V8a4 4 0 0 1 8 0v3" />
                        </svg>
                    </span>

                    <span>
                        Two-factor authentication is required.
                    </span>
                </div>
            <?php endif; ?>

            <!-- Login error -->
            <?php if ($error): ?>
                <div
                    class="alert alert-error auth-alert"
                    role="alert"
                >
                    <span class="alert-icon" aria-hidden="true">
                        <svg
                            viewBox="0 0 24 24"
                            width="18"
                            height="18"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 8v5" />
                            <path d="M12 16h.01" />
                        </svg>
                    </span>

                    <span>
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Login form -->
            <form
                method="POST"
                class="auth-form login-form"
                autocomplete="off"
                novalidate
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        csrfToken(),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <!-- Username -->
                <div class="form-group">

                    <label for="username">
                        <span class="form-label-icon" aria-hidden="true">
                            <svg
                                viewBox="0 0 24 24"
                                width="17"
                                height="17"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="12" cy="8" r="3.5" />
                                <path d="M5 20c.8-3.2 3.1-5 7-5s6.2 1.8 7 5" />
                            </svg>
                        </span>

                        Username
                    </label>

                    <div class="input-wrapper">

                        <span
                            class="input-icon"
                            aria-hidden="true"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                width="19"
                                height="19"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="12" cy="8" r="3.5" />
                                <path d="M5 20c.8-3.2 3.1-5 7-5s6.2 1.8 7 5" />
                            </svg>
                        </span>

                        <input
                            id="username"
                            type="text"
                            name="username"
                            placeholder="Enter your username"
                            autocomplete="username"
                            required
                            autofocus
                            spellcheck="false"
                        >

                    </div>

                </div>

                <!-- Password -->
                <div class="form-group">

                    <label for="password">
                        <span class="form-label-icon" aria-hidden="true">
                            <svg
                                viewBox="0 0 24 24"
                                width="17"
                                height="17"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect
                                    x="5"
                                    y="10"
                                    width="14"
                                    height="10"
                                    rx="2"
                                />
                                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                            </svg>
                        </span>

                        Password
                    </label>

                    <div class="input-wrapper">

                        <span
                            class="input-icon"
                            aria-hidden="true"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                width="19"
                                height="19"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect
                                    x="5"
                                    y="10"
                                    width="14"
                                    height="10"
                                    rx="2"
                                />
                                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                            </svg>
                        </span>

                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    class="auth-submit login-submit"
                >
                    <span class="button-icon" aria-hidden="true">
                        <svg
                            viewBox="0 0 24 24"
                            width="19"
                            height="19"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M10 17l5-5-5-5" />
                            <path d="M15 12H3" />
                            <path d="M21 4v16" />
                        </svg>
                    </span>

                    <span>Sign In</span>
                </button>

            </form>

            <!-- Security information -->
            <div class="auth-security">

                <span
                    class="security-icon"
                    aria-hidden="true"
                >
                    <svg
                        viewBox="0 0 24 24"
                        width="17"
                        height="17"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M12 3l8 4v5c0 4.8-3.2 7.7-8 9-4.8-1.3-8-4.2-8-9V7l8-4z" />
                        <path d="M9 12l2 2 4-4" />
                    </svg>
                </span>

                <span>
                    Protected access with encrypted credentials
                    and optional two-factor authentication.
                </span>

            </div>

        </section>

    </main>

    <script src="/assets/theme.js"></script>

</body>
</html>
