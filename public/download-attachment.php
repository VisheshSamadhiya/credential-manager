<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/s3.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireLogin();

if (!canViewCredentials()) {
    http_response_code(403);
    exit('You are not authorized to download attachments.');
}

$attachmentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$attachmentId || $attachmentId < 1) {
    http_response_code(400);
    exit('Invalid attachment ID.');
}

/*
 * Verify both the attachment and its parent credential.
 *
 * deleted_at IS NULL is important because credentials use
 * soft deletion.
 */
$stmt = $pdo->prepare(
    'SELECT
        ca.id,
        ca.credential_id,
        ca.original_filename,
        ca.s3_key,
        ca.content_type,
        ca.file_size
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

try {
    global $s3, $bucket;

    $result = $s3->getObject([
        'Bucket' => $bucket,
        'Key'    => $attachment['s3_key'],
    ]);

    $body = $result['Body'];

    header(
        'Content-Type: '
        . ($attachment['content_type'] ?: 'application/octet-stream')
    );

    header(
        'Content-Length: '
        . (string) $attachment['file_size']
    );

    /*
     * Content-Disposition attachment forces download
     * instead of browser rendering.
     */
    $filename = $attachment['original_filename'];

    header(
        'Content-Disposition: attachment; filename="'
        . addcslashes($filename, "\"\\")
        . '"'
    );

    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');

    echo $body;

} catch (Throwable $e) {

    error_log(
        'Attachment download failed. '
        . 'Attachment ID=' . $attachmentId
        . ' Error=' . $e->getMessage()
    );

    http_response_code(404);
    exit('Attachment could not be retrieved.');
}
