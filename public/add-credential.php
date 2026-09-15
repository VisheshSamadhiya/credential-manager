<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity_logger.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

requireLogin();


/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
|
| Only Admin and Editor can add credentials.
|
*/

$userRole = $_SESSION['role'] ?? 'viewer';


if (!in_array($userRole, ['admin', 'editor'], true)) {

    header(
        'Location: /dashboard.php?access_denied=1'
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Get Employee ID
|--------------------------------------------------------------------------
*/

$employeeId = isset($_GET['employee_id'])
    ? (int) $_GET['employee_id']
    : 0;


if ($employeeId <= 0) {

    header(
        'Location: /departments.php'
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Get Employee and Department Information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(

    "SELECT

        employees.id,
        employees.employee_name,
        employees.department_id,

        departments.department_name

    FROM employees

    INNER JOIN departments

        ON employees.department_id = departments.id

    WHERE employees.id = :employee_id"

);


$stmt->execute([

    ':employee_id' => $employeeId

]);


$employee = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$employee) {

    header(
        'Location: /departments.php'
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Error Message
|--------------------------------------------------------------------------
*/

$error = '';


/*
|--------------------------------------------------------------------------
| Add Credential
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $serviceName = trim(
        $_POST['service_name'] ?? ''
    );


    $serviceUrl = trim(
        $_POST['service_url'] ?? ''
    );


    $credentialUsername = trim(
        $_POST['credential_username'] ?? ''
    );


    $credentialPassword =
        $_POST['credential_password'] ?? '';


    $notes = trim(
        $_POST['notes'] ?? ''
    );


    $changeComment = trim(
        $_POST['change_comment'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $serviceName === ''
        ||
        $credentialPassword === ''
        ||
        $changeComment === ''
    ) {

        $error =
            'Service name, password and change comment are required.';

    } else {


        try {


            /*
            |--------------------------------------------------------------------------
            | Insert New Credential
            |--------------------------------------------------------------------------
            |
            | Existing credentials are never modified here.
            | A completely new credential record is created.
            |
            */

            $stmt = $pdo->prepare(

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
                    :is_new_version,
                    :created_by,
                    :employee_id

                )"

            );


            $encryptedCredentialPassword =
                encryptCredential(
                    $credentialPassword
                );


            $stmt->execute([

                ':service_name' =>
                    $serviceName,


                ':service_url' =>
                    $serviceUrl !== ''
                        ? $serviceUrl
                        : null,


                ':credential_username' =>
                    $credentialUsername !== ''
                        ? $credentialUsername
                        : null,


                ':credential_password' =>
                    $encryptedCredentialPassword,


                ':notes' =>
                    $notes !== ''
                        ? $notes
                        : null,


                ':change_comment' =>
                    $changeComment,


                ':is_new_version' =>
                    1,


                ':created_by' =>
                    $_SESSION['user_id'],


                ':employee_id' =>
                    $employeeId

            ]);


            /*
            |--------------------------------------------------------------------------
            | Get New Credential ID
            |--------------------------------------------------------------------------
            */

            $newCredentialId =
                (int) $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Activity Log
            |--------------------------------------------------------------------------
            */

            logActivity(

                $pdo,

                'NEW_CREDENTIAL_ADDED',

                'New credential added for service: '
                . $serviceName
                . '. Comment: '
                . $changeComment,

                $employeeId,

                $newCredentialId

            );


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            header(

                'Location: /credentials.php?employee_id='
                . $employeeId
                . '&added=1'

            );


            exit;


        } catch (PDOException $e) {


            $error =
                'Unable to save the credential. Please check the database configuration.';

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

<title>Add Credential - Credential Manager</title>

<link
    rel="stylesheet"
    href="/assets/css/style.css"
>

    <link rel="stylesheet" href="/assets/theme.css">

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


        <?php if ($userRole === 'admin'): ?>


            <a href="/manage-users.php">

                Manage Users

            </a>


        <?php endif; ?>


        <a href="/logout.php">

            Logout

        </a>


    </div>


</div>


<!-- Main Container -->

<div class="container">


    <a
        class="back"
        href="/credentials.php?employee_id=<?php echo (int) $employeeId; ?>"
    >

        ← Back to Credentials

    </a>


    <h1>

        Add New Credential

    </h1>


    <div class="info-box">


        <strong>

            Department:

        </strong>


        <?php
        echo htmlspecialchars(
            $employee['department_name']
        );
        ?>


        <br><br>


        <strong>

            Employee:

        </strong>


        <?php
        echo htmlspecialchars(
            $employee['employee_name']
        );
        ?>


    </div>


    <div class="notice">


        <strong>

            Credential History:

        </strong>

        This creates a new credential record and does not modify
        existing credentials. Please provide a comment explaining
        why this password or credential is being added.


    </div>


    <?php if ($error): ?>


        <div class="error">


            <?php
            echo htmlspecialchars(
                $error
            );
            ?>


        </div>


    <?php endif; ?>


    <form method="POST">


        <!-- Service Name -->

        <label>

            Service Name *

        </label>


        <input

            type="text"

            name="service_name"

            placeholder="Example: Gmail"

            value="<?php echo htmlspecialchars(
                $_POST['service_name'] ?? ''
            ); ?>"

            required

        >


        <!-- Service URL -->

        <label>

            Service URL

        </label>


        <input

            type="url"

            name="service_url"

            placeholder="https://example.com"

            value="<?php echo htmlspecialchars(
                $_POST['service_url'] ?? ''
            ); ?>"

        >


        <!-- Username -->

        <label>

            Username / Email

        </label>


        <input

            type="text"

            name="credential_username"

            placeholder="username@example.com"

            value="<?php echo htmlspecialchars(
                $_POST['credential_username'] ?? ''
            ); ?>"

        >


        <!-- Password -->

        <label>

            Password *

        </label>


        <div class="password-wrapper">


            <input

                type="password"

                id="credentialPassword"

                name="credential_password"

                placeholder="Enter password"

                required

            >


            <button

                type="button"

                class="toggle-password"

                onclick="togglePassword()"

                aria-label="Show password"

                title="Show password"

            >

                Show

            </button>


        </div>


        <div class="password-tools">


            <button

                type="button"

                class="generate-button"

                onclick="generatePassword()"

            >

                Generate Password

            </button>


        </div>


        <div
            id="strength"
            class="strength"
        >

        </div>


        <!-- Change Comment -->

        <label>

            Comment / Reason for New Credential *

        </label>


        <textarea

            name="change_comment"

            placeholder="Example: New password added because the previous password expired."

            required

        ><?php
echo htmlspecialchars(
    $_POST['change_comment'] ?? ''
);
?></textarea>


        <!-- Notes -->

        <label>

            Additional Notes

        </label>


        <textarea

            name="notes"

            placeholder="Additional information..."

        ><?php
echo htmlspecialchars(
    $_POST['notes'] ?? ''
);
?></textarea>


        <!-- Submit -->

        <button

            type="submit"

            class="save-button"

        >

            Save New Credential

        </button>


    </form>


</div>


<script src="/assets/js/app.js"></script>


    <script src="/assets/theme.js"></script>

</body>

</html>