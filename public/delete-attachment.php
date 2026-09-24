<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/s3.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireLogin();

if (!canDeleteCredentials()) {
    http_response_code(403);
    exit('You are not authorized to delete attachments.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$attachmentId = filter_input(
    INPUT_POST,
    'attachment_id',
    FILTER_VALIDATE_INT
);

if (!$attachmentId || $attachmentId < 1) {
    http_response_code(400);
    exit('Invalid attachment ID.');
}

/*
 * Retrieve metadata and make sure the parent credential
 * still exists and is not soft-deleted.
 */
$stmt = $pdo->prepare(
    'SELECT
        ca.id,
        ca.credential_id,
        ca.original_filename,
        ca.s3_key
     FROM credential_attachments ca
     INNER JOIN credentials c
         ON c.id = ca.credential_id
     WHERE ca.id = :id
       AND c.deleted_at IS NULL
     LIMIT 1'
);

$stmt->execute([
    ':id' => $attachmentId,
]);

$attachment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found.');
}

$s3Deleted = false;

try {
    global $s3, $bucket;

    /*
     * Delete S3 first.
     */
    $s3->deleteObject([
        'Bucket' => $bucket,
        'Key'    => $attachment['s3_key'],
    ]);

    $s3Deleted = true;

    /*
     * Only remove metadata after S3 deletion succeeded.
     */
    $delete = $pdo->prepare(
        'DELETE FROM credential_attachments
         WHERE id = :id'
    );

    $delete->execute([
        ':id' => $attachmentId,
    ]);

    if ($delete->rowCount() !== 1) {
        /*
         * S3 is already deleted but DB metadata remains.
         * This is intentionally logged for reconciliation.
         */
        error_log(
            'CRITICAL: S3 object deleted but attachment DB row '
            . 'could not be deleted. Attachment ID='
            . $attachmentId
        );

        throw new RuntimeException(
            'Attachment metadata could not be deleted.'
        );
    }

    header('Content-Type: application/json');

    echo json_encode([
        'success' => true,
        'message' => 'Attachment deleted successfully.',
    ], JSON_THROW_ON_ERROR);

} catch (Throwable $e) {

    error_log(
        'Attachment deletion failed. '
        . 'Attachment ID=' . $attachmentId
        . ' S3Deleted=' . ($s3Deleted ? 'yes' : 'no')
        . ' Error=' . $e->getMessage()
    );

    http_response_code(500);

    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'message' => 'Attachment deletion failed.',
    ]);
}
