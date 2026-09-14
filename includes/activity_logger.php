<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Activity Logger
|--------------------------------------------------------------------------
|
| Records important actions performed by users.
|
| The activity_logs table contains:
|   user_id
|   action
|   description
|   created_at
|
| Credential-specific access logging is handled separately by the
| credential access/activity log tables.
|
*/

function logActivity(
    PDO $pdo,
    string $action,
    string $description,
    ?int $employeeId = null,
    ?int $credentialId = null
): void {

    $userId = isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;

    /*
     * employeeId and credentialId are intentionally accepted for
     * compatibility with existing callers, but they are not inserted
     * into activity_logs because those columns do not exist there.
     */

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (
                user_id,
                action,
                description
            )
            VALUES (
                :user_id,
                :action,
                :description
            )"
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':description' => $description
        ]);

    } catch (PDOException $e) {

        /*
         * Logging failure must never break the main application.
         */
        error_log(
            'Activity logging failed: ' . $e->getMessage()
        );
    }
}
