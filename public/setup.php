<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Check Installation Status
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare(
        "SELECT setting_value
         FROM app_settings
         WHERE setting_key = 'installation_completed'
         LIMIT 1"
    );

    $stmt->execute();

    $installationCompleted =
        $stmt->fetchColumn();

} catch (PDOException $e) {

    http_response_code(500);

    exit(
        'Unable to determine installation status.'
    );
}


/*
|--------------------------------------------------------------------------
| If Already Installed
|--------------------------------------------------------------------------
*/

if ($installationCompleted === '1') {

    header(
        'Location: /login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['setup_csrf_token'])
) {

    $_SESSION['setup_csrf_token'] =
        bin2hex(random_bytes(32));

}

$csrfToken =
    $_SESSION['setup_csrf_token'];


/*
|--------------------------------------------------------------------------
| Form State
|--------------------------------------------------------------------------
*/

$error = '';

$fullName = '';

$username = '';

$email = '';


/*
|--------------------------------------------------------------------------
| Create First Administrator
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    $submittedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {

        $error =
            'Invalid security token. Please try again.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $fullName =
            trim(
                (string)
                ($_POST['full_name'] ?? '')
            );

        $username =
            trim(
                (string)
                ($_POST['username'] ?? '')
            );

        $email =
            trim(
                (string)
                ($_POST['email'] ?? '')
            );

        $password =
            (string)
            ($_POST['password'] ?? '');

        $confirmPassword =
            (string)
            ($_POST['confirm_password'] ?? '');


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if (
            $fullName === ''
            ||
            $username === ''
            ||
            $email === ''
            ||
            $password === ''
            ||
            $confirmPassword === ''
        ) {

            $error =
                'All fields are required.';

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                'Please enter a valid email address.';

        } elseif (
            strlen($username) < 3
        ) {

            $error =
                'Username must contain at least 3 characters.';

        } elseif (
            strlen($password) < 12
        ) {

            $error =
                'Password must contain at least 12 characters.';

        } elseif (
            $password !== $confirmPassword
        ) {

            $error =
                'Passwords do not match.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Transaction
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Re-check Installation Status
                |--------------------------------------------------------------------------
                |
                | Prevents two simultaneous setup requests from
                | creating multiple initial administrators.
                |--------------------------------------------------------------------------
                */

                $checkStmt =
                    $pdo->prepare(
                        "SELECT setting_value
                         FROM app_settings
                         WHERE setting_key = 'installation_completed'
                         LIMIT 1
                         FOR UPDATE"
                    );

                $checkStmt->execute();

                $currentStatus =
                    $checkStmt->fetchColumn();


                if ($currentStatus === '1') {

                    $pdo->rollBack();

                    header(
                        'Location: /login.php'
                    );

                    exit;
                }


                /*
                |--------------------------------------------------------------------------
                | Make Sure No User Already Exists
                |--------------------------------------------------------------------------
                */

                $userCount =
                    (int)
                    $pdo->query(
                        "SELECT COUNT(*) FROM users"
                    )->fetchColumn();


                if ($userCount > 0) {

                    $pdo->rollBack();

                    $error =
                        'An administrator already exists. Please use the login page.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Check Username
                    |--------------------------------------------------------------------------
                    */

                    $usernameCheck =
                        $pdo->prepare(
                            "SELECT id
                             FROM users
                             WHERE username = :username
                             LIMIT 1"
                        );

                    $usernameCheck->execute([
                        ':username' => $username
                    ]);


                    if (
                        $usernameCheck->fetch()
                    ) {

                        $pdo->rollBack();

                        $error =
                            'That username is already in use.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Password Hash
                        |--------------------------------------------------------------------------
                        */

                        $passwordHash =
                            password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );


                        if (
                            $passwordHash === false
                        ) {

                            throw new RuntimeException(
                                'Unable to securely hash password.'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Create First Administrator
                        |--------------------------------------------------------------------------
                        */

                        $insertUser =
                            $pdo->prepare(
                                "INSERT INTO users (
                                    full_name,
                                    username,
                                    email,
                                    password,
                                    role
                                )
                                VALUES (
                                    :full_name,
                                    :username,
                                    :email,
                                    :password,
                                    'admin'
                                )"
                            );


                        $insertUser->execute([
                            ':full_name' =>
                                $fullName,

                            ':username' =>
                                $username,

                            ':email' =>
                                $email,

                            ':password' =>
                                $passwordHash
                        ]);


                        /*
                        |--------------------------------------------------------------------------
                        | Lock Installation
                        |--------------------------------------------------------------------------
                        */

                        $updateSettings =
                            $pdo->prepare(
                                "UPDATE app_settings
                                 SET setting_value = '1'
                                 WHERE setting_key = 'installation_completed'"
                            );

                        $updateSettings->execute();


                        /*
                        |--------------------------------------------------------------------------
                        | Commit
                        |--------------------------------------------------------------------------
                        */

                        $pdo->commit();


                        /*
                        |--------------------------------------------------------------------------
                        | Remove Setup Session Token
                        |--------------------------------------------------------------------------
                        */

                        unset(
                            $_SESSION[
                                'setup_csrf_token'
                            ]
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | Redirect
                        |--------------------------------------------------------------------------
                        */

                        header(
                            'Location: /login.php?setup=complete'
                        );

                        exit;
                    }
                }

            } catch (
                Throwable $e
            ) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }

                error_log(
                    'Credential Manager setup error: '
                    . $e->getMessage()
                );

                $error =
                    'Installation could not be completed. Please check the database configuration.';
            }
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
    content="width=device-width, initial-scale=1"
>

<meta
    name="robots"
    content="noindex,nofollow"
>

<title>
    Initial Setup · Credential Manager
</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 30px;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #f7f1ff 0%,
            #ffffff 50%,
            #fff0f8 100%
        );

    color: #24172f;
}

.setup-card {

    width: 100%;

    max-width: 520px;

    background: #ffffff;

    border: 1px solid #eee7f7;

    border-radius: 20px;

    padding: 38px;

    box-shadow:
        0 20px 60px
        rgba(91, 33, 182, .12);
}

.logo {

    width: 58px;

    height: 58px;

    border-radius: 16px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 28px;

    color: #ffffff;

    background:
        linear-gradient(
            135deg,
            #6d28d9,
            #8b5cf6,
            #ec4899
        );

    margin-bottom: 22px;
}

h1 {

    margin: 0 0 10px;

    font-size: 28px;
}

.subtitle {

    margin: 0 0 28px;

    color: #746b82;

    line-height: 1.6;
}

.form-group {

    margin-bottom: 18px;
}

label {

    display: block;

    margin-bottom: 7px;

    font-weight: 600;

    font-size: 14px;
}

input {

    width: 100%;

    padding: 13px 14px;

    border: 1px solid #dcd4e7;

    border-radius: 10px;

    font-size: 15px;

    outline: none;
}

input:focus {

    border-color: #7c3aed;

    box-shadow:
        0 0 0 3px
        rgba(124, 58, 237, .10);
}

.error {

    margin-bottom: 20px;

    padding: 13px 15px;

    border-radius: 10px;

    background: #fff1f2;

    border: 1px solid #fecdd3;

    color: #9f1239;

    line-height: 1.5;
}

button {

    width: 100%;

    border: 0;

    border-radius: 10px;

    padding: 14px;

    font-size: 15px;

    font-weight: 700;

    color: #ffffff;

    cursor: pointer;

    background:
        linear-gradient(
            135deg,
            #6d28d9,
            #8b5cf6,
            #ec4899
        );
}

button:hover {

    opacity: .94;
}

.security-note {

    margin-top: 22px;

    padding-top: 18px;

    border-top: 1px solid #eee7f7;

    color: #746b82;

    font-size: 13px;

    line-height: 1.6;
}

</style>

</head>

<body>

<div class="setup-card">

    <div class="logo">
        🔐
    </div>

    <h1>
        Welcome to Credential Manager
    </h1>

    <p class="subtitle">
        Create the first administrator account
        for this installation.
    </p>

    <?php if ($error !== ''): ?>

        <div class="error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        autocomplete="off"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrfToken,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >


        <div class="form-group">

            <label for="full_name">
                Administrator Name
            </label>

            <input
                type="text"
                id="full_name"
                name="full_name"
                value="<?= htmlspecialchars(
                    $fullName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                autocomplete="name"
                required
            >

        </div>


        <div class="form-group">

            <label for="username">
                Username
            </label>

            <input
                type="text"
                id="username"
                name="username"
                value="<?= htmlspecialchars(
                    $username,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                autocomplete="username"
                required
            >

        </div>


        <div class="form-group">

            <label for="email">
                Email Address
            </label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars(
                    $email,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                autocomplete="email"
                required
            >

        </div>


        <div class="form-group">

            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                minlength="12"
                autocomplete="new-password"
                required
            >

        </div>


        <div class="form-group">

            <label for="confirm_password">
                Confirm Password
            </label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="12"
                autocomplete="new-password"
                required
            >

        </div>


        <button type="submit">
            Create Administrator Account
        </button>

    </form>


    <div class="security-note">

        This account will automatically receive
        <strong>Administrator</strong> privileges.

        The setup page is locked after installation
        is completed.

    </div>

</div>

</body>

</html>
