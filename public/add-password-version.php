<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity_logger.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
|
| Admin and Editor can add a new password version.
|
*/

$userRole = $_SESSION['role'] ?? 'viewer';


if (
    !in_array(
        $userRole,
        ['admin', 'editor'],
        true
    )
) {

    header(
        'Location: /credentials.php?access_denied=1'
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Get Credential ID
|--------------------------------------------------------------------------
*/

$credentialId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($credentialId <= 0) {

    header(
        'Location: /credentials.php'
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Get Existing Credential
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(

    "SELECT
        credentials.*,
        employees.employee_name,
        departments.department_name

    FROM credentials

    LEFT JOIN employees
        ON credentials.employee_id = employees.id

    LEFT JOIN departments
        ON employees.department_id = departments.id

    WHERE credentials.id = :id"

);

$stmt->execute([

    ':id' => $credentialId

]);

$credential = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$credential) {

    header(
        'Location: /credentials.php'
    );

    exit;

}


$error = '';


/*
|--------------------------------------------------------------------------
| Create New Password Version
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $newPassword =
        $_POST['new_password'] ?? '';

    $changeComment =
        trim(
            $_POST['change_comment']
            ?? ''
        );


    if ($newPassword === '') {

        $error =
            'A new password is required.';

    } elseif ($changeComment === '') {

        $error =
            'Please provide a comment explaining the new password.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Create New Credential Record
            |--------------------------------------------------------------------------
            */

            $insertStmt = $pdo->prepare(

                "INSERT INTO credentials (

                    service_name,

                    service_url,

                    credential_username,

                    credential_password,

                    notes,

                    change_comment,

                    is_new_version,

                    created_by,

                    employee_id

                )

                VALUES (

                    :service_name,

                    :service_url,

                    :credential_username,

                    :credential_password,

                    :notes,

                    :change_comment,

                    1,

                    :created_by,

                    :employee_id

                )"

            );


            $encryptedNewPassword = encryptCredential($newPassword);

            $insertStmt->execute([

                ':service_name' =>
                    $credential['service_name'],

                ':service_url' =>
                    $credential['service_url'],

                ':credential_username' =>
                    $credential[
                        'credential_username'
                    ],

                ':credential_password' =>
                    $encryptedNewPassword,

                ':notes' =>
                    $credential['notes'],

                ':change_comment' =>
                    $changeComment,

                ':created_by' =>
                    $_SESSION['user_id'],

                ':employee_id' =>
                    $credential['employee_id']

            ]);


            $newCredentialId =
                (int) $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Activity Log
            |--------------------------------------------------------------------------
            */

            logActivity(

                $pdo,

                'new_password_version',

                'Created a new password version for '
                . $credential['service_name']
                . '. Comment: '
                . $changeComment,

                $credential['employee_id']
                    ? (int) $credential['employee_id']
                    : null,

                $newCredentialId

            );


            header(

                'Location: /credentials.php?new_version=1'

            );

            exit;

        } catch (PDOException $e) {

            $error =
                'Unable to save the new password version.';

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

<title>Add New Password Version</title>

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

    color: white;

    display: flex;

    justify-content: space-between;

    align-items: center;

}

.navbar-links {

    display: flex;

    gap: 22px;

}

.navbar a {

    color: white;

    text-decoration: none;

}


.container {

    max-width: 750px;

    margin: 40px auto;

    background: white;

    padding: 35px;

    border-radius: 10px;

}


.info-box {

    background: #f1f3f5;

    padding: 20px;

    border-radius: 8px;

    margin-bottom: 25px;

}


.warning {

    background: #fff3cd;

    color: #856404;

    padding: 15px;

    border-radius: 5px;

    margin-bottom: 25px;

}


label {

    display: block;

    margin-top: 20px;

    margin-bottom: 8px;

    font-weight: bold;

}


input,
textarea {

    width: 100%;

    padding: 12px;

    border: 1px solid #ccc;

    border-radius: 5px;

    font-size: 14px;

}

.password-wrapper {
    position: relative;
    width: 100%;
}

.password-wrapper input {
    padding-right: 55px;
}

.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 18px;
    padding: 5px;
    line-height: 1;
}


textarea {

    min-height: 120px;

    resize: vertical;

}


.button {

    margin-top: 25px;

    background: #333;

    color: white;

    border: none;

    padding: 13px 22px;

    border-radius: 5px;

    cursor: pointer;

}


.button:hover {

    background: #555;

}


.error {

    background: #f8d7da;

    color: #721c24;

    padding: 15px;

    border-radius: 5px;

    margin-bottom: 20px;

}


.back {

    display: inline-block;

    margin-bottom: 20px;

    text-decoration: none;

}

</style>

    <link rel="stylesheet" href="/assets/theme.css">

</head>


<body>


<div class="navbar">


<strong>
    Credential Manager
</strong>


<div class="navbar-links">

    <a href="/dashboard.php">
        Dashboard
    </a>

    <a href="/credentials.php">
        Credentials
    </a>

    <a href="/logout.php">
        Logout
    </a>

</div>


</div>


<div class="container">


<a
    href="/credentials.php"
    class="back"
>

    ← Back to Credentials

</a>


<h1>
    Add New Password
</h1>


<div class="warning">

    The existing credential will not be changed.

    A new password version will be created and the old
    credential will remain in the system.

</div>


<div class="info-box">


<strong>
    Service:
</strong>

<?php
echo htmlspecialchars(
    $credential['service_name']
);
?>


<br><br>


<strong>
    Username:
</strong>

<?php
echo htmlspecialchars(
    $credential[
        'credential_username'
    ]
    ?? 'Not available'
);
?>


<br><br>


<strong>
    Employee:
</strong>

<?php
echo htmlspecialchars(
    $credential[
        'employee_name'
    ]
    ?? 'Not assigned'
);
?>


</div>


<?php if ($error): ?>

<div class="error">

    <?php
    echo htmlspecialchars($error);
    ?>

</div>

<?php endif; ?>


<form method="POST">


<label>
    New Password *
</label>

<div class="password-wrapper">

    <input
        type="password"
        name="new_password"
        id="new_password"
        required
        autocomplete="new-password"
    >

    <button
        type="button"
        class="toggle-password"
        id="togglePassword"
        aria-label="Show password"
        title="Show password"
    >👁</button>

</div>


<label>
    Change Comment *
</label>

<textarea
    name="change_comment"
    placeholder="Example: Password was changed because the previous employee no longer has access."
    required
></textarea>


<button
    type="submit"
    class="button"
>

    Save New Password Version

</button>


</form>


</div>


<script>
const passwordInput = document.getElementById('new_password');
const togglePassword = document.getElementById('togglePassword');

togglePassword.addEventListener('click', function () {
    const isHidden = passwordInput.type === 'password';

    passwordInput.type = isHidden ? 'text' : 'password';

    this.textContent = isHidden ? '🙈' : '👁';
    this.setAttribute(
        'aria-label',
        isHidden ? 'Hide password' : 'Show password'
    );
    this.setAttribute(
        'title',
        isHidden ? 'Hide password' : 'Show password'
    );
});
</script>

    <script src="/assets/theme.js"></script>

</body>

</html>
