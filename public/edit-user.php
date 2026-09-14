<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity_logger.php';

requireAdmin();

$userId = isset($_GET['user_id'])
    ? (int) $_GET['user_id']
    : (int) ($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: /manage-users.php?error=invalid_user');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load User
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        id,
        employee_id,
        full_name,
        username,
        email,
        role,
        two_factor_enabled
     FROM users
     WHERE id = :id
     LIMIT 1"
);

$stmt->execute([
    ':id' => $userId
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /manage-users.php?error=not_found');
    exit;
}


/*
|--------------------------------------------------------------------------
| Form Values
|--------------------------------------------------------------------------
*/

$employeeId = (string) ($user['employee_id'] ?? '');
$fullName = (string) $user['full_name'];
$username = (string) $user['username'];
$email = (string) $user['email'];
$role = (string) $user['role'];

$error = '';


/*
|--------------------------------------------------------------------------
| Update User
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $error = 'Invalid security token. Please refresh the page and try again.';

    } else {

        $employeeId = trim($_POST['employee_id'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $newPassword = $_POST['password'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if (
            $employeeId === '' ||
            $fullName === '' ||
            $username === '' ||
            $email === '' ||
            $role === ''
        ) {

            $error = 'All fields except the password are required.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Please enter a valid email address.';

        } elseif (!in_array(
            $role,
            ['admin', 'editor', 'viewer'],
            true
        )) {

            $error = 'Invalid user role selected.';

        } elseif (
            $newPassword !== '' &&
            strlen($newPassword) < 8
        ) {

            $error = 'New password must be at least 8 characters long.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Duplicate Employee ID
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM users
                     WHERE employee_id = :employee_id
                     AND id != :id
                     LIMIT 1"
                );

                $stmt->execute([
                    ':employee_id' => $employeeId,
                    ':id' => $userId
                ]);

                if ($stmt->fetch()) {

                    $error = 'This Employee ID already exists.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Duplicate Username
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare(
                        "SELECT id
                         FROM users
                         WHERE username = :username
                         AND id != :id
                         LIMIT 1"
                    );

                    $stmt->execute([
                        ':username' => $username,
                        ':id' => $userId
                    ]);

                    if ($stmt->fetch()) {

                        $error = 'This username is already taken.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Duplicate Email
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $pdo->prepare(
                            "SELECT id
                             FROM users
                             WHERE email = :email
                             AND id != :id
                             LIMIT 1"
                        );

                        $stmt->execute([
                            ':email' => $email,
                            ':id' => $userId
                        ]);

                        if ($stmt->fetch()) {

                            $error = 'This email address is already registered.';

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | Update User
                            |--------------------------------------------------------------------------
                            */

                            if ($newPassword !== '') {

                                $hashedPassword = password_hash(
                                    $newPassword,
                                    PASSWORD_DEFAULT
                                );

                                $stmt = $pdo->prepare(
                                    "UPDATE users
                                     SET
                                        employee_id = :employee_id,
                                        full_name = :full_name,
                                        username = :username,
                                        email = :email,
                                        password = :password,
                                        role = :role
                                     WHERE id = :id"
                                );

                                $stmt->execute([
                                    ':employee_id' => $employeeId,
                                    ':full_name' => $fullName,
                                    ':username' => $username,
                                    ':email' => $email,
                                    ':password' => $hashedPassword,
                                    ':role' => $role,
                                    ':id' => $userId
                                ]);

                            } else {

                                $stmt = $pdo->prepare(
                                    "UPDATE users
                                     SET
                                        employee_id = :employee_id,
                                        full_name = :full_name,
                                        username = :username,
                                        email = :email,
                                        role = :role
                                     WHERE id = :id"
                                );

                                $stmt->execute([
                                    ':employee_id' => $employeeId,
                                    ':full_name' => $fullName,
                                    ':username' => $username,
                                    ':email' => $email,
                                    ':role' => $role,
                                    ':id' => $userId
                                ]);
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Activity Log
                            |--------------------------------------------------------------------------
                            */

                            logActivity(
                                $pdo,
                                'USER_UPDATED',
                                'Updated user: ' .
                                $fullName .
                                ' (' .
                                $username .
                                ')'
                            );


                            /*
                            |--------------------------------------------------------------------------
                            | Redirect
                            |--------------------------------------------------------------------------
                            */

                            header(
                                'Location: /manage-users.php?updated=1'
                            );

                            exit;
                        }
                    }
                }

            } catch (PDOException $e) {

                error_log(
                    'User update failed: ' .
                    $e->getMessage()
                );

                $error = 'Unable to update the user. Please try again.';
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

<title>Edit User - Credential Manager</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #333;
}

.navbar {
    background: #2b2b2b;
    padding: 18px 40px;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.navbar-title {
    font-size: 18px;
    font-weight: bold;
}

.navbar-links {
    display: flex;
    gap: 22px;
}

.navbar a {
    color: #fff;
    text-decoration: none;
}

.navbar a:hover {
    text-decoration: underline;
}

.container {
    max-width: 850px;
    margin: 0 auto;
    padding: 40px 20px;
}

.card {
    background: #fff;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
}

h1 {
    margin-top: 0;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

input,
select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 15px;
}

input:focus,
select:focus {
    outline: none;
    border-color: #2563eb;
}

.help {
    margin-top: 6px;
    color: #666;
    font-size: 13px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.buttons {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.button {
    display: inline-block;
    padding: 12px 20px;
    border: 0;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    font-size: 15px;
}

.save-button {
    background: #2563eb;
    color: #fff;
}

.save-button:hover {
    background: #1d4ed8;
}

.cancel-button {
    background: #e5e7eb;
    color: #333;
}

.cancel-button:hover {
    background: #d1d5db;
}

.user-status {
    background: #f1f5f9;
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 25px;
    font-size: 14px;
}

.twofa-enabled {
    color: #155724;
    font-weight: bold;
}

.twofa-disabled {
    color: #666;
    font-weight: bold;
}

@media (max-width: 700px) {

    .navbar {
        flex-direction: column;
        gap: 12px;
        padding: 18px;
    }

    .card {
        padding: 20px;
    }

    .buttons {
        flex-direction: column;
    }

}

</style>

</head>

<body>


<div class="navbar">

    <div class="navbar-title">
        Credential Manager
    </div>

    <div class="navbar-links">

        <a href="/dashboard.php">
            Dashboard
        </a>

        <a href="/manage-users.php">
            Manage Users
        </a>

        <a href="/logout.php">
            Logout
        </a>

    </div>

</div>


<div class="container">

    <div class="card">

        <h1>
            Edit User
        </h1>


        <div class="user-status">

            Editing:

            <strong>
                <?= htmlspecialchars($user['full_name']) ?>
            </strong>

            &nbsp; | &nbsp;

            User ID:
            <strong>
                <?= (int) $user['id'] ?>
            </strong>

            &nbsp; | &nbsp;

            2FA:

            <?php if (
                (int) $user['two_factor_enabled'] === 1
            ): ?>

                <span class="twofa-enabled">
                    Enabled
                </span>

            <?php else: ?>

                <span class="twofa-disabled">
                    Disabled
                </span>

            <?php endif; ?>

        </div>


        <?php if ($error !== ''): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="/edit-user.php"
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


            <input
                type="hidden"
                name="user_id"
                value="<?= (int) $user['id'] ?>"
            >


            <div class="form-group">

                <label for="employee_id">
                    Employee ID
                </label>

                <input
                    type="text"
                    id="employee_id"
                    name="employee_id"
                    value="<?= htmlspecialchars(
                        $employeeId
                    ) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="full_name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?= htmlspecialchars(
                        $fullName
                    ) ?>"
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
                        $username
                    ) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars(
                        $email
                    ) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="role">
                    Role
                </label>

                <select
                    id="role"
                    name="role"
                    required
                >

                    <option
                        value="admin"
                        <?= $role === 'admin'
                            ? 'selected'
                            : '' ?>
                    >
                        Admin
                    </option>

                    <option
                        value="editor"
                        <?= $role === 'editor'
                            ? 'selected'
                            : '' ?>
                    >
                        Editor
                    </option>

                    <option
                        value="viewer"
                        <?= $role === 'viewer'
                            ? 'selected'
                            : '' ?>
                    >
                        Viewer
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label for="password">
                    New Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    placeholder="Leave blank to keep current password"
                >

                <div class="help">
                    Leave this field empty if you do not want to change
                    the user's current password.
                </div>

            </div>


            <div class="buttons">

                <button
                    type="submit"
                    class="button save-button"
                >
                    Save Changes
                </button>

                <a
                    href="/manage-users.php"
                    class="button cancel-button"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>
