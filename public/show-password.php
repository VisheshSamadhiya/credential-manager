<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/encryption.php';


/*
|--------------------------------------------------------------------------
| Security Headers
|--------------------------------------------------------------------------
|
| The password response must never be cached by the browser,
| proxy, or intermediary.
|
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Referrer-Policy: no-referrer'
);

header(
    'X-Frame-Options: DENY'
);


requireLogin();


/*
|--------------------------------------------------------------------------
| Password Viewing Permission
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Viewers ARE allowed to view passwords.
|
| The purpose of the Viewer role in this Credential Manager
| is to help employees log into their services.
|
| Therefore:
|
| Root   -> allowed
| Admin  -> allowed
| Editor -> allowed
| Viewer -> allowed
|
| canViewCredentials() represents all four roles.
|
|--------------------------------------------------------------------------
*/

if (!canViewCredentials()) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' =>
            'You are not authorized to view passwords.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Reject Clipboard / Copy Requests
|--------------------------------------------------------------------------
|
| There is deliberately NO password-copy functionality.
|
| Even if somebody manually calls:
|
| /show-password.php?id=5&action=copy
|
| the server will reject it.
|
|--------------------------------------------------------------------------
*/

$action = isset($_GET['action'])
    ? strtolower(
        trim(
            (string) $_GET['action']
        )
    )
    : 'view';


if ($action !== 'view') {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' =>
            'Clipboard access is disabled. Passwords cannot be copied.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Credential ID
|--------------------------------------------------------------------------
*/

$credentialId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


if (
    !$credentialId ||
    $credentialId < 1
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            'Invalid credential ID.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Retrieve Credential
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare(
        "
        SELECT
            id,
            service_name,
            credential_password,
            employee_id
        FROM credentials
        WHERE id = :id
        LIMIT 1
        "
    );


    $stmt->execute([
        ':id' => $credentialId
    ]);


    $credential =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$credential) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' =>
                'Credential not found.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Decrypt Password
    |--------------------------------------------------------------------------
    |
    | The password is decrypted only now, after the user explicitly
    | requested to see it.
    |
    |--------------------------------------------------------------------------
    */

    $password =
        decryptCredential(
            (string)
            $credential[
                'credential_password'
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | The actual password is NEVER written to the log.
    |
    |--------------------------------------------------------------------------
    */

    logActivity(
        $pdo,
        'credential_viewed',
        'Password secret viewed for credential: ' .
        $credential['service_name'],
        (int)
        $credential['employee_id'],
        $credentialId
    );


    /*
    |--------------------------------------------------------------------------
    | Return Password
    |--------------------------------------------------------------------------
    |
    | The browser receives the password only after an explicit
    | Show action.
    |
    | The frontend automatically hides it after 30 seconds.
    |
    |--------------------------------------------------------------------------
    */

    echo json_encode(
        [
            'success' => true,
            'password' => $password
        ],
        JSON_UNESCAPED_SLASHES
    );

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Server-Side Error Logging
    |--------------------------------------------------------------------------
    |
    | Never return the actual exception or password to the browser.
    |
    |--------------------------------------------------------------------------
    */

    error_log(
        'Credential secret access failed: ' .
        $e->getMessage()
    );


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'message' =>
            'Unable to access password.'
    ]);

}
