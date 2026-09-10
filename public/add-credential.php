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


            $encryptedCredentialPassword = encryptCredential($credentialPassword);

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


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        Arial,
        sans-serif;

    background:
        #f4f6f9;

    color:
        #333;

}


/*
|--------------------------------------------------------------------------
| Navbar
|--------------------------------------------------------------------------
*/

.navbar {

    background:
        #2b2b2b;

    padding:
        18px 40px;

    color:
        white;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

}


.navbar-title {

    font-size:
        18px;

    font-weight:
        bold;

}


.navbar-links {

    display:
        flex;

    align-items:
        center;

    gap:
        22px;

}


.navbar a {

    color:
        white;

    text-decoration:
        none;

}


.navbar a:hover {

    text-decoration:
        underline;

}


/*
|--------------------------------------------------------------------------
| Container
|--------------------------------------------------------------------------
*/

.container {

    max-width:
        750px;

    margin:
        40px auto;

    padding:
        30px;

    background:
        white;

    border-radius:
        12px;

    box-shadow:
        0 5px 20px
        rgba(
            0,
            0,
            0,
            0.08
        );

}


/*
|--------------------------------------------------------------------------
| Back Button
|--------------------------------------------------------------------------
*/

.back {

    display:
        inline-block;

    margin-bottom:
        25px;

    text-decoration:
        none;

    color:
        #333;

    font-weight:
        bold;

}


/*
|--------------------------------------------------------------------------
| Information Box
|--------------------------------------------------------------------------
*/

.info-box {

    background:
        #f1f3f5;

    padding:
        20px;

    border-radius:
        8px;

    margin-bottom:
        25px;

    line-height:
        1.7;

}


/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/

label {

    display:
        block;

    margin-top:
        20px;

    margin-bottom:
        8px;

    font-weight:
        bold;

}


input,
textarea {

    width:
        100%;

    padding:
        13px;

    border:
        1px solid #ccc;

    border-radius:
        6px;

    font-size:
        14px;

}


input:focus,
textarea:focus {

    outline:
        none;

    border-color:
        #555;

}


textarea {

    min-height:
        110px;

    resize:
        vertical;

}


/*
|--------------------------------------------------------------------------
| Password Section
|--------------------------------------------------------------------------
*/

.password-wrapper {

    position:
        relative;

}


.password-wrapper input {

    padding-right:
        100px;

}


.toggle-password {

    position:
        absolute;

    right:
        8px;

    top:
        50%;

    transform:
        translateY(-50%);

    border:
        none;

    background:
        #eeeeee;

    padding:
        8px 10px;

    border-radius:
        5px;

    cursor:
        pointer;

}


.password-tools {

    display:
        flex;

    gap:
        10px;

    margin-top:
        10px;

}


.generate-button {

    background:
        #555;

    color:
        white;

    border:
        none;

    padding:
        10px 15px;

    border-radius:
        5px;

    cursor:
        pointer;

}


/*
|--------------------------------------------------------------------------
| Password Strength
|--------------------------------------------------------------------------
*/

.strength {

    margin-top:
        10px;

    font-size:
        14px;

    font-weight:
        bold;

}


/*
|--------------------------------------------------------------------------
| Save Button
|--------------------------------------------------------------------------
*/

.save-button {

    margin-top:
        30px;

    background:
        #333;

    color:
        white;

    border:
        none;

    padding:
        14px 25px;

    border-radius:
        6px;

    font-size:
        15px;

    cursor:
        pointer;

}


.save-button:hover {

    background:
        #555;

}


/*
|--------------------------------------------------------------------------
| Error
|--------------------------------------------------------------------------
*/

.error {

    background:
        #ffe5e5;

    color:
        #b00020;

    padding:
        15px;

    border-radius:
        6px;

    margin-bottom:
        20px;

}


/*
|--------------------------------------------------------------------------
| Notice
|--------------------------------------------------------------------------
*/

.notice {

    background:
        #e7f3ff;

    color:
        #004085;

    padding:
        15px;

    border-radius:
        6px;

    margin-bottom:
        25px;

}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (
    max-width: 700px
) {

    .navbar {

        flex-direction:
            column;

        align-items:
            flex-start;

        gap:
            15px;

        padding:
            20px;

    }


    .navbar-links {

        flex-wrap:
            wrap;

        gap:
            15px;

    }


    .container {

        margin:
            20px 15px;

        padding:
            20px;

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

                oninput="checkPasswordStrength()"

                required

            >


            <button

                type="button"

                class="toggle-password"

                onclick="togglePassword()"

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


<script>


/*
|--------------------------------------------------------------------------
| Show / Hide Password
|--------------------------------------------------------------------------
*/

function togglePassword() {


    const passwordInput =
        document.getElementById(
            'credentialPassword'
        );


    const button =
        document.querySelector(
            '.toggle-password'
        );


    if (
        passwordInput.type ===
        'password'
    ) {


        passwordInput.type =
            'text';


        button.textContent =
            'Hide';


    } else {


        passwordInput.type =
            'password';


        button.textContent =
            'Show';


    }


}


/*
|--------------------------------------------------------------------------
| Password Generator
|--------------------------------------------------------------------------
*/

function generatePassword() {


    const characters =

        'ABCDEFGHIJKLMNOPQRSTUVWXYZ'

        +

        'abcdefghijklmnopqrstuvwxyz'

        +

        '0123456789'

        +

        '!@#$%^&*()_+-=';


    let password = '';


    for (
        let i = 0;
        i < 16;
        i++
    ) {


        const randomIndex =

            Math.floor(

                Math.random()
                *
                characters.length

            );


        password +=

            characters.charAt(
                randomIndex
            );


    }


    document.getElementById(
        'credentialPassword'
    ).value = password;


    checkPasswordStrength();


}


/*
|--------------------------------------------------------------------------
| Password Strength Checker
|--------------------------------------------------------------------------
*/

function checkPasswordStrength() {


    const password =

        document.getElementById(
            'credentialPassword'
        ).value;


    const strengthElement =

        document.getElementById(
            'strength'
        );


    let score = 0;


    if (
        password.length >= 8
    ) {

        score++;

    }


    if (
        /[A-Z]/.test(
            password
        )
    ) {

        score++;

    }


    if (
        /[a-z]/.test(
            password
        )
    ) {

        score++;

    }


    if (
        /[0-9]/.test(
            password
        )
    ) {

        score++;

    }


    if (
        /[^A-Za-z0-9]/.test(
            password
        )
    ) {

        score++;

    }


    if (
        password.length === 0
    ) {


        strengthElement.textContent =
            '';


    }

    else if (
        score <= 2
    ) {


        strengthElement.textContent =
            'Password Strength: Weak';


    }

    else if (
        score <= 4
    ) {


        strengthElement.textContent =
            'Password Strength: Medium';


    }

    else {


        strengthElement.textContent =
            'Password Strength: Strong';


    }


}


</script>


</body>

</html>