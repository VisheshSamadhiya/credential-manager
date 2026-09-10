<?php

/*
|--------------------------------------------------------------------------
| Activity Logger
|--------------------------------------------------------------------------
|
| Records important actions performed by users.
|
*/

function logActivity(
    PDO $pdo,
    string $action,
    string $description,
    ?int $employeeId = null,
    ?int $credentialId = null
): void {

    /*
    |--------------------------------------------------------------------------
    | Get Current User
    |--------------------------------------------------------------------------
    */

    $userId = isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;


    try {

        $stmt = $pdo->prepare(

            "INSERT INTO activity_logs (

                user_id,
                action,
                description,
                employee_id,
                credential_id

            )

            VALUES (

                :user_id,
                :action,
                :description,
                :employee_id,
                :credential_id

            )"

        );


        $stmt->execute([

            ':user_id' => $userId,

            ':action' => $action,

            ':description' => $description,

            ':employee_id' => $employeeId,

            ':credential_id' => $credentialId

        ]);

    } catch (PDOException $e) {

        /*
        |--------------------------------------------------------------------------
        | Logging should not break the main application
        |--------------------------------------------------------------------------
        */

        error_log(
            'Activity logging failed: '
            . $e->getMessage()
        );

    }

}