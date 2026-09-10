<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity_logger.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| Admin Permission
|--------------------------------------------------------------------------
*/

if (!canEditCredentials()) {

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

    header('Location: /credentials.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Credential
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

    header('Location: /credentials.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Update Credential
|--------------------------------------------------------------------------
*/

$error = '';


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

    $credentialPassword = $_POST[
        'credential_password'
    ] ?? '';

    $notes = trim(
        $_POST['notes'] ?? ''
    );


    if (
        $serviceName === ''
        ||
        $credentialPassword === ''
    ) {

        $error =
            'Service name and password are required.';

    } else {

        try {

            $updateStmt = $pdo->prepare(

                "UPDATE credentials

                SET

                    service_name =
                        :service_name,

                    service_url =
                        :service_url,

                    credential_username =
                        :credential_username,

                    credential_password =
                        :credential_password,

                    notes =
                        :notes,

                    change_comment =
                        :change_comment,

                    is_new_version = 0

                WHERE id = :id"

            );


            $encryptedCredentialPassword = encryptCredential($credentialPassword);

            $updateStmt->execute([

                ':service_name' =>
                    $serviceName,

                ':service_url' =>
                    $serviceUrl ?: null,

                ':credential_username' =>
                    $credentialUsername ?: null,

                ':credential_password' =>
                    $encryptedCredentialPassword,

                ':notes' =>
                    $notes ?: null,

                ':change_comment' =>
                    'Credential updated by administrator.',

                ':id' =>
                    $credentialId

            ]);


            /*
            |--------------------------------------------------------------------------
            | Activity Log
            |--------------------------------------------------------------------------
            */

            logActivity(

                $pdo,

                'credential_updated',

                'Administrator updated credential: '
                . $serviceName,

                $credential['employee_id']
                    ? (int) $credential['employee_id']
                    : null,

                $credentialId

            );


            header(
                'Location: /credentials.php?updated=1'
            );

            exit;

        } catch (PDOException $e) {

            $error =
                'Unable to update the credential.';

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

<title>Edit Credential</title>

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

textarea {

    min-height: 100px;

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

.info-box {

    background: #f1f3f5;

    padding: 18px;

    border-radius: 6px;

    margin-bottom: 25px;

}

.back {

    display: inline-block;

    margin-bottom: 20px;

    text-decoration: none;

}

</style>

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
    Edit Credential
</h1>


<div class="info-box">

    <strong>Employee:</strong>

    <?php
    echo htmlspecialchars(
        $credential['employee_name']
        ?? 'Not assigned'
    );
    ?>

    <br><br>


    <strong>Department:</strong>

    <?php
    echo htmlspecialchars(
        $credential['department_name']
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
    Service Name *
</label>

<input
    type="text"
    name="service_name"
    value="<?php
        echo htmlspecialchars(
            $credential['service_name']
        );
    ?>"
    required
>


<label>
    Service URL
</label>

<input
    type="url"
    name="service_url"
    value="<?php
        echo htmlspecialchars(
            $credential['service_url']
            ?? ''
        );
    ?>"
>


<label>
    Username / Email
</label>

<input
    type="text"
    name="credential_username"
    value="<?php
        echo htmlspecialchars(
            $credential['credential_username']
            ?? ''
        );
    ?>"
>


<label>
    Password *
</label>

<input
    type="text"
    name="credential_password"
    value="<?php
        echo htmlspecialchars(
            $credential['credential_password']
            ?? ''
        );
    ?>"
    required
>


<label>
    Notes
</label>

<textarea
    name="notes"
><?php
    echo htmlspecialchars(
        $credential['notes']
        ?? ''
    );
?></textarea>


<button
    type="submit"
    class="button"
>

    Update Credential

</button>


</form>


</div>

</body>

</html>