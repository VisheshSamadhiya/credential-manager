<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

$stmt = $pdo->query(
    "SELECT
        id,
        employee_id,
        full_name,
        username,
        email,
        role,
        created_at,
        two_factor_enabled
     FROM users
     ORDER BY employee_id ASC"
);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);

$twoFactorEnabled = isset($_GET['2fa_enabled']);
$twoFactorDisabled = isset($_GET['2fa_disabled']);
$twoFactorReset = isset($_GET['2fa_reset']);

$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Manage Users - Credential Manager</title>

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
    align-items: center;
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
    max-width: 1500px;
    margin: auto;
    padding: 40px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 20px;
}

.button {
    display: inline-block;
    background: #333;
    color: #fff;
    padding: 11px 18px;
    text-decoration: none;
    border: 0;
    border-radius: 5px;
    cursor: pointer;
}

.button:hover {
    background: #555;
}

.success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.warning {
    background: #fff3cd;
    color: #856404;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.table-wrapper {
    background: #fff;
    border-radius: 10px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
    white-space: nowrap;
}

th {
    background: #eee;
}

tr:hover {
    background: #f9f9f9;
}

.employee-id {
    font-weight: bold;
}

.role {
    display: inline-block;
    padding: 6px 12px;
    background: #eee;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    text-transform: capitalize;
}

.actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.small-button {
    display: inline-block;
    padding: 8px 11px;
    border: 0;
    border-radius: 5px;
    background: #334155;
    color: #fff;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
}

.small-button:hover {
    background: #475569;
}

.edit-button {
    background: #2563eb;
}

.edit-button:hover {
    background: #1d4ed8;
}

.reset-button {
    background: #7c3aed;
}

.disable-button {
    background: #b45309;
}

.delete-button {
    background: #b00020;
    color: #fff;
    border: 0;
    padding: 9px 14px;
    border-radius: 5px;
    cursor: pointer;
}

.delete-button:hover {
    background: #850018;
}

.twofa-enabled {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    background: #d4edda;
    color: #155724;
    font-weight: bold;
    font-size: 12px;
}

.twofa-disabled {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    background: #eee;
    color: #555;
    font-weight: bold;
    font-size: 12px;
}

@media (max-width: 800px) {

    .navbar {
        flex-direction: column;
        gap: 12px;
        padding: 18px;
    }

    .container {
        padding: 20px;
    }

    .header {
        align-items: flex-start;
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
        <a href="/dashboard.php">Dashboard</a>
        <a href="/manage-users.php">Manage Users</a>
        <a href="/logout.php">Logout</a>
    </div>

</div>


<div class="container">

    <div class="header">

        <h1>Manage Users</h1>

        <a
            class="button"
            href="/add-user.php"
        >
            + Add User
        </a>

    </div>


    <?php if ($created): ?>

        <div class="success">
            User created successfully.
        </div>

    <?php endif; ?>


    <?php if ($updated): ?>

        <div class="success">
            User updated successfully.
        </div>

    <?php endif; ?>


    <?php if ($deleted): ?>

        <div class="success">
            User deleted successfully.
        </div>

    <?php endif; ?>


    <?php if ($twoFactorEnabled): ?>

        <div class="success">
            2FA has been enabled successfully.
        </div>

    <?php endif; ?>


    <?php if ($twoFactorDisabled): ?>

        <div class="success">
            2FA has been disabled successfully.
        </div>

    <?php endif; ?>


    <?php if ($twoFactorReset): ?>

        <div class="success">
            2FA has been reset.
            The user must be set up again before 2FA is active.
        </div>

    <?php endif; ?>


    <?php if ($error === 'self_delete'): ?>

        <div class="error">
            You cannot delete your own account.
        </div>

    <?php endif; ?>


    <?php if ($error === 'not_found'): ?>

        <div class="error">
            User not found.
        </div>

    <?php endif; ?>


    <?php if ($error === 'delete_failed'): ?>

        <div class="error">
            Unable to delete the user.
            This user may be connected to other records.
        </div>

    <?php endif; ?>


    <?php if ($error === 'invalid_2fa_action'): ?>

        <div class="error">
            Invalid 2FA action.
        </div>

    <?php endif; ?>


    <?php if ($error === 'invalid_user'): ?>

        <div class="error">
            Invalid user selected.
        </div>

    <?php endif; ?>


    <?php if ($error === '2fa_already_enabled'): ?>

        <div class="warning">
            2FA is already enabled for this user.
        </div>

    <?php endif; ?>


    <?php if ($error === 'duplicate_employee_id'): ?>

        <div class="error">
            This Employee ID already exists.
        </div>

    <?php endif; ?>


    <?php if ($error === 'duplicate_username'): ?>

        <div class="error">
            This username is already taken.
        </div>

    <?php endif; ?>


    <?php if ($error === 'duplicate_email'): ?>

        <div class="error">
            This email address is already registered.
        </div>

    <?php endif; ?>


    <?php if ($error === 'update_failed'): ?>

        <div class="error">
            Unable to update the user.
            Please check the entered information.
        </div>

    <?php endif; ?>


    <div class="table-wrapper">

        <table>

            <thead>

                <tr>
                    <th>System ID</th>
                    <th>Employee ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>2FA</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>

            </thead>


            <tbody>

            <?php if (empty($users)): ?>

                <tr>

                    <td
                        colspan="9"
                        style="text-align:center;padding:30px;"
                    >
                        No users found.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($users as $user): ?>

                    <tr>

                        <td>
                            <?= (int) $user['id'] ?>
                        </td>


                        <td class="employee-id">
                            <?= htmlspecialchars(
                                $user['employee_id'] ?? 'Not Assigned'
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $user['full_name']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $user['username']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $user['email']
                            ) ?>
                        </td>


                        <td>

                            <span class="role">

                                <?= htmlspecialchars(
                                    ucfirst($user['role'])
                                ) ?>

                            </span>

                        </td>


                        <td>

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

                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $user['created_at']
                            ) ?>
                        </td>


                        <td>

                            <div class="actions">

                                <!-- EDIT USER -->

                                <a
                                    class="small-button edit-button"
                                    href="/edit-user.php?user_id=<?= (int) $user['id'] ?>"
                                >
                                    Edit
                                </a>


                                <!-- 2FA -->

                                <?php if (
                                    (int) $user['two_factor_enabled'] === 1
                                ): ?>

                                    <form
                                        method="POST"
                                        action="/2fa-action.php"
                                        onsubmit="return confirm('Disable 2FA for this user?');"
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

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="disable"
                                        >

                                        <button
                                            class="small-button disable-button"
                                            type="submit"
                                        >
                                            Disable 2FA
                                        </button>

                                    </form>


                                    <form
                                        method="POST"
                                        action="/2fa-action.php"
                                        onsubmit="return confirm('Reset 2FA for this user? The current authenticator will stop working.');"
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

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="reset"
                                        >

                                        <button
                                            class="small-button reset-button"
                                            type="submit"
                                        >
                                            Reset 2FA
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <a
                                        class="small-button"
                                        href="/2fa-setup.php?user_id=<?= (int) $user['id'] ?>"
                                    >
                                        Set Up 2FA
                                    </a>

                                <?php endif; ?>


                                <!-- DELETE -->

                                <?php if (
                                    (int) $user['id'] !==
                                    (int) $_SESSION['user_id']
                                ): ?>

                                    <form
                                        method="POST"
                                        action="/delete-user.php"
                                        onsubmit="return confirm('Are you sure you want to permanently delete this user?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $user['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="delete-button"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span>
                                        Current User
                                    </span>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>