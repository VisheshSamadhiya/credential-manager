<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| Admin Permission Check
|--------------------------------------------------------------------------
*/

if (($_SESSION['role'] ?? '') !== 'admin') {

    header('Location: /dashboard.php?access_denied=1');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get All Users
|--------------------------------------------------------------------------
|
| Sort users according to Employee ID.
|
*/

$stmt = $pdo->query(
    "SELECT
        id,
        employee_id,
        full_name,
        username,
        email,
        role,
        created_at
    FROM users
    ORDER BY employee_id ASC"
);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$created = isset($_GET['created']);
$deleted = isset($_GET['deleted']);
$accessDenied = isset($_GET['access_denied']);

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


/* Navbar */

.navbar {
    background: #2b2b2b;
    padding: 18px 40px;
    color: white;
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
    color: white;
    text-decoration: none;
}

.navbar a:hover {
    text-decoration: underline;
}


/* Main Container */

.container {
    max-width: 1400px;
    margin: auto;
    padding: 40px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}


/* Buttons */

.button {
    display: inline-block;
    background: #333;
    color: white;
    padding: 11px 18px;
    text-decoration: none;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.button:hover {
    background: #555;
}

.delete-button {
    background: #b00020;
    color: white;
    border: none;
    padding: 9px 14px;
    border-radius: 5px;
    cursor: pointer;
}

.delete-button:hover {
    background: #850018;
}


/* Messages */

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


/* Table */

.table-wrapper {
    background: white;
    border-radius: 10px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid #ddd;
    white-space: nowrap;
}

th {
    background: #eeeeee;
}

tr:hover {
    background: #f9f9f9;
}


/* Employee ID */

.employee-id {
    font-weight: bold;
}


/* Role Badge */

.role {
    display: inline-block;
    padding: 6px 12px;
    background: #eeeeee;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    text-transform: capitalize;
}


/* Actions */

.actions {
    display: flex;
    align-items: center;
    gap: 10px;
}


/* Responsive */

@media (max-width: 800px) {

    .navbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
        padding: 20px;
    }

    .navbar-links {
        flex-wrap: wrap;
        gap: 15px;
    }

    .container {
        padding: 20px;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

}

</style>

</head>


<body>


<!-- Navbar -->

<div class="navbar">

    <div class="navbar-title">
        Credential Manager
    </div>


    <div class="navbar-links">

        <a href="/dashboard.php">
            Dashboard
        </a>

        <a href="/departments.php">
            Departments
        </a>

        <a href="/credentials.php">
            Credentials
        </a>

        <a href="/manage-users.php">
            Manage Users
        </a>

        <a href="/logout.php">
            Logout
        </a>

    </div>

</div>


<!-- Main Content -->

<div class="container">


<div class="header">

    <div>

        <h1>
            Manage Users
        </h1>

        <p>
            Add, view and manage system users.
        </p>

    </div>


    <a
        href="/add-user.php"
        class="button"
    >
        + Add User
    </a>

</div>


<!-- Success Messages -->

<?php if ($created): ?>

    <div class="success">
        User created successfully.
    </div>

<?php endif; ?>


<?php if ($deleted): ?>

    <div class="success">
        User deleted successfully.
    </div>

<?php endif; ?>


<!-- Access Denied -->

<?php if ($accessDenied): ?>

    <div class="warning">
        Access denied. You do not have permission to perform that action.
    </div>

<?php endif; ?>


<!-- Error Messages -->

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
        Unable to delete the user. This user may be connected to other records.
    </div>

<?php endif; ?>


<!-- Users Table -->

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

    <th>Created</th>

    <th>Actions</th>

</tr>

</thead>


<tbody>


<?php if (empty($users)): ?>

<tr>

    <td
        colspan="8"
        style="text-align:center; padding:30px;"
    >
        No users found.
    </td>

</tr>


<?php else: ?>


<?php foreach ($users as $user): ?>


<tr>


<!-- System ID -->

<td>

    <?php
    echo (int) $user['id'];
    ?>

</td>


<!-- Employee ID -->

<td class="employee-id">

    <?php
    echo htmlspecialchars(
        $user['employee_id'] ?? 'Not Assigned'
    );
    ?>

</td>


<!-- Full Name -->

<td>

    <?php
    echo htmlspecialchars(
        $user['full_name']
    );
    ?>

</td>


<!-- Username -->

<td>

    <?php
    echo htmlspecialchars(
        $user['username']
    );
    ?>

</td>


<!-- Email -->

<td>

    <?php
    echo htmlspecialchars(
        $user['email']
    );
    ?>

</td>


<!-- Role -->

<td>

    <span class="role">

        <?php
        echo htmlspecialchars(
            ucfirst($user['role'])
        );
        ?>

    </span>

</td>


<!-- Created Date -->

<td>

    <?php
    echo htmlspecialchars(
        $user['created_at']
    );
    ?>

</td>


<!-- Actions -->

<td>

<div class="actions">


<?php if (
    (int) $user['id']
    !==
    (int) $_SESSION['user_id']
): ?>


<form
    method="POST"
    action="/delete-user.php"
    onsubmit="return confirm(
        'Are you sure you want to permanently delete this user?'
    );"
>

<input
    type="hidden"
    name="user_id"
    value="<?php echo (int) $user['id']; ?>"
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