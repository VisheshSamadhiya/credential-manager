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
| Variables
|--------------------------------------------------------------------------
*/

$error = '';

$fullName = '';
$employeeId = '';
$username = '';
$email = '';
$role = 'viewer';


/*
|--------------------------------------------------------------------------
| Add User
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['full_name'] ?? '');
    $employeeId = trim($_POST['employee_id'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'viewer';


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $fullName === '' ||
        $employeeId === '' ||
        $username === '' ||
        $email === '' ||
        $password === ''
    ) {

        $error = 'All fields are required.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } elseif (!in_array($role, ['admin', 'editor', 'viewer'], true)) {

        $error = 'Invalid user role selected.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Check Duplicate Employee ID
            |--------------------------------------------------------------------------
            */

            $checkStmt = $pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE employee_id = :employee_id
                 LIMIT 1"
            );

            $checkStmt->execute([
                ':employee_id' => $employeeId
            ]);

            if ($checkStmt->fetch()) {

                $error = 'This Employee ID already exists.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Check Duplicate Username
                |--------------------------------------------------------------------------
                */

                $checkStmt = $pdo->prepare(
                    "SELECT id
                     FROM users
                     WHERE username = :username
                     LIMIT 1"
                );

                $checkStmt->execute([
                    ':username' => $username
                ]);

                if ($checkStmt->fetch()) {

                    $error = 'This username is already taken.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Check Duplicate Email
                    |--------------------------------------------------------------------------
                    */

                    $checkStmt = $pdo->prepare(
                        "SELECT id
                         FROM users
                         WHERE email = :email
                         LIMIT 1"
                    );

                    $checkStmt->execute([
                        ':email' => $email
                    ]);

                    if ($checkStmt->fetch()) {

                        $error = 'This email address is already registered.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Create User
                        |--------------------------------------------------------------------------
                        */

                        $hashedPassword = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );


                        $stmt = $pdo->prepare(
                            "INSERT INTO users
                            (
                                employee_id,
                                full_name,
                                username,
                                email,
                                password,
                                role
                            )
                            VALUES
                            (
                                :employee_id,
                                :full_name,
                                :username,
                                :email,
                                :password,
                                :role
                            )"
                        );


                        $stmt->execute([

                            ':employee_id' => $employeeId,

                            ':full_name' => $fullName,

                            ':username' => $username,

                            ':email' => $email,

                            ':password' => $hashedPassword,

                            ':role' => $role

                        ]);


                        header(
                            'Location: /manage-users.php?created=1'
                        );

                        exit;

                    }

                }

            }

        } catch (PDOException $e) {

            $error = 'Unable to add user. Please check the entered information.';

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

<title>Add User - Credential Manager</title>

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
    gap: 22px;
    align-items: center;

}

.navbar a {

    color: white;
    text-decoration: none;

}

.navbar a:hover {

    text-decoration: underline;

}


/* Container */

.container {

    max-width: 700px;
    margin: 50px auto;

    background: white;

    padding: 35px;

    border-radius: 10px;

    box-shadow:
        0 5px 20px
        rgba(0, 0, 0, 0.08);

}


/* Form */

label {

    display: block;

    font-weight: bold;

    margin-top: 20px;
    margin-bottom: 8px;

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

    border-color: #555;

}


/* Buttons */

.button {

    display: inline-block;

    background: #333;

    color: white;

    padding: 12px 20px;

    border: none;

    border-radius: 5px;

    text-decoration: none;

    cursor: pointer;

    margin-top: 25px;

}

.button:hover {

    background: #555;

}

.back-button {

    background: #777;

    margin-left: 10px;

}


/* Error */

.error {

    background: #f8d7da;

    color: #721c24;

    padding: 15px;

    border-radius: 5px;

    margin-bottom: 20px;

}


/* Information Box */

.info {

    background: #f1f3f5;

    padding: 15px;

    border-radius: 5px;

    margin-bottom: 20px;

    color: #555;

}


/* Responsive */

@media (max-width: 700px) {

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

        margin: 25px 15px;

        padding: 25px;

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


<div class="container">


<a
    href="/manage-users.php"
    style="text-decoration:none;"
>
    ← Back to Manage Users
</a>


<h1>Add New User</h1>


<div class="info">

    Create a new system user and assign an Employee ID and role.

</div>


<?php if ($error): ?>

    <div class="error">

        <?php
        echo htmlspecialchars($error);
        ?>

    </div>

<?php endif; ?>


<form method="POST">


<!-- Full Name -->

<label>

    Full Name *

</label>

<input
    type="text"
    name="full_name"
    value="<?php echo htmlspecialchars($fullName); ?>"
    placeholder="Enter full name"
    required
>


<!-- Employee ID -->

<label>

    Employee ID *

</label>

<input
    type="text"
    name="employee_id"
    value="<?php echo htmlspecialchars($employeeId); ?>"
    placeholder="Example: EMP-1001"
    required
>


<!-- Username -->

<label>

    Username *

</label>

<input
    type="text"
    name="username"
    value="<?php echo htmlspecialchars($username); ?>"
    placeholder="Enter username"
    required
>


<!-- Email -->

<label>

    Email Address *

</label>

<input
    type="email"
    name="email"
    value="<?php echo htmlspecialchars($email); ?>"
    placeholder="example@company.com"
    required
>


<!-- Password -->

<label>

    Password *

</label>

<input
    type="password"
    name="password"
    placeholder="Enter password"
    required
>


<!-- Role -->

<label>

    User Role *

</label>

<select
    name="role"
    required
>

    <option
        value="viewer"
        <?php echo $role === 'viewer' ? 'selected' : ''; ?>
    >
        Viewer
    </option>

    <option
        value="editor"
        <?php echo $role === 'editor' ? 'selected' : ''; ?>
    >
        Editor
    </option>

    <option
        value="admin"
        <?php echo $role === 'admin' ? 'selected' : ''; ?>
    >
        Admin
    </option>

</select>


<button
    type="submit"
    class="button"
>

    + Add User

</button>


<a
    href="/manage-users.php"
    class="button back-button"
>

    Cancel

</a>


</form>


</div>


</body>

</html>