<?php
declare(strict_types=1);

/*
 * Credential Manager - Attachment Upload
 *
 * Flow:
 * 1. Authenticate user
 * 2. Require Admin/Editor
 * 3. Validate credential
 * 4. Validate uploaded file
 * 5. Generate unpredictable S3 key
 * 6. Upload file to S3
 * 7. Insert metadata into MariaDB
 * 8. If DB insert fails, delete the S3 object
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/s3.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireLogin();

if (!canEditCredentials()) {
    http_response_code(403);
    exit('You are not authorized to upload attachments.');
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

/*
 * Validate credential ID.
 */
$credentialId = filter_input(
    INPUT_POST,
    'credential_id',
    FILTER_VALIDATE_INT
);

if (!$credentialId || $credentialId < 1) {
    http_response_code(400);
    exit('Invalid credential ID.');
}

/*
 * Confirm credential exists and is not soft-deleted.
 */
$stmt = $pdo->prepare(
    'SELECT id, service_name
     FROM credentials
     WHERE id = :id
       AND deleted_at IS NULL
     LIMIT 1'
);

$stmt->execute([
    ':id' => $credentialId,
]);

$credential = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$credential) {
    http_response_code(404);
    exit('Credential not found.');
}

/*
 * Validate upload.
 */
if (!isset($_FILES['attachment'])) {
    http_response_code(400);
    exit('No file was uploaded.');
}

$file = $_FILES['attachment'];

if (!is_array($file)) {
    http_response_code(400);
    exit('Invalid upload.');
}

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file.',
        UPLOAD_ERR_EXTENSION  => 'A server extension stopped the upload.',
    ];

    http_response_code(400);

    exit(
        $messages[$uploadError]
        ?? 'Unknown file upload error.'
    );
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
$originalFilename = (string) ($file['name'] ?? '');
$fileSize = (int) ($file['size'] ?? 0);

if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    http_response_code(400);
    exit('Invalid uploaded file.');
}

if ($originalFilename === '') {
    http_response_code(400);
    exit('The uploaded file has no filename.');
}

/*
 * 10 MB application limit.
 *
 * This is intentionally enforced before S3 upload.
 */
$maxFileSize = 10 * 1024 * 1024;

if ($fileSize <= 0) {
    http_response_code(400);
    exit('The uploaded file is empty.');
}

if ($fileSize > $maxFileSize) {
    http_response_code(400);
    exit('File is too large. Maximum allowed size is 10 MB.');
}

/*
 * Never trust the browser-provided MIME type.
 */
$finfo = new finfo(FILEINFO_MIME_TYPE);

$contentType = $finfo->file($tmpPath);

if ($contentType === false || $contentType === '') {
    http_response_code(400);
    exit('Unable to determine file type.');
}

/*
 * Allowed attachment types.
 *
 * PHP files and other executable/script types are deliberately excluded.
 */
$allowedTypes = [
    'application/pdf' => 'pdf',

    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',

    'text/plain' => 'txt',
    'text/csv'  => 'csv',

    'application/zip' => 'zip',

    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',

    'application/msword' => 'doc',
    'application/vnd.ms-excel' => 'xls',
];

if (!isset($allowedTypes[$contentType])) {
    http_response_code(400);
    exit(
        'File type is not allowed. Detected MIME type: '
        . $contentType
    );
}

/*
 * Keep the original filename only as metadata.
 *
 * basename() prevents directory traversal strings from being stored.
 */
$originalFilename = basename($originalFilename);

if ($originalFilename === '.' || $originalFilename === '..') {
    http_response_code(400);
    exit('Invalid filename.');
}

/*
 * Limit filename metadata length.
 */
if (mb_strlen($originalFilename, 'UTF-8') > 255) {
    $originalFilename = mb_substr(
        $originalFilename,
        0,
        255,
        'UTF-8'
    );
}

/*
 * Generate an unpredictable object name.
 *
 * Example:
 * uploads/credentials/7/550e8400-e29b-41d4-a716-446655440000.pdf
 */
try {
    $randomId = bin2hex(random_bytes(16));
} catch (Throwable $e) {
    http_response_code(500);
    exit('Unable to generate secure attachment ID.');
}

$extension = $allowedTypes[$contentType];

$s3Filename = $randomId . '.' . $extension;

$s3Key = sprintf(
    'uploads/credentials/%d/%s',
    $credentialId,
    $s3Filename
);

$s3Uploaded = false;

try {
    /*
     * Upload directly from the temporary PHP upload.
     *
     * We deliberately do not read the entire file into a PHP string.
     */
    global $s3, $bucket;

    $s3->putObject([
        'Bucket'      => $bucket,
        'Key'         => $s3Key,
        'SourceFile'  => $tmpPath,
        'ContentType' => $contentType,
    ]);

    $s3Uploaded = true;

    /*
     * Now create the MariaDB metadata record.
     */
    $insert = $pdo->prepare(
        'INSERT INTO credential_attachments (
            credential_id,
            original_filename,
            s3_key,
            content_type,
            file_size,
            uploaded_by
        ) VALUES (
            :credential_id,
            :original_filename,
            :s3_key,
            :content_type,
            :file_size,
            :uploaded_by
        )'
    );

    $insert->execute([
        ':credential_id'    => $credentialId,
        ':original_filename'=> $originalFilename,
        ':s3_key'           => $s3Key,
        ':content_type'     => $contentType,
        ':file_size'        => $fileSize,
        ':uploaded_by'      => (int) ($_SESSION['user_id'] ?? 0),
    ]);

    $attachmentId = (int) $pdo->lastInsertId();

    /*
     * JSON response makes this endpoint easy to use
     * from both normal forms and JavaScript later.
     */
    header('Content-Type: application/json');

    echo json_encode([
        'success' => true,
        'message' => 'Attachment uploaded successfully.',
        'attachment' => [
            'id' => $attachmentId,
            'credential_id' => $credentialId,
            'filename' => $originalFilename,
            'content_type' => $contentType,
            'file_size' => $fileSize,
        ],
    ], JSON_THROW_ON_ERROR);

} catch (Throwable $e) {

    /*
     * IMPORTANT:
     *
     * If S3 succeeded but MariaDB failed, remove the S3 object.
     * This prevents an orphaned object.
     */
    if ($s3Uploaded) {
        try {
            $s3->deleteObject([
                'Bucket' => $bucket,
                'Key'    => $s3Key,
            ]);
        } catch (Throwable $cleanupError) {
            /*
             * We intentionally do not expose internal AWS details
             * to the browser.
             *
             * The orphan should be detected later through
             * reconciliation/monitoring.
             */
            error_log(
                'CRITICAL: Failed to clean up S3 object after DB failure. '
                . 'Key=' . $s3Key
                . ' Error=' . $cleanupError->getMessage()
            );
        }
    }

    error_log(
        'Attachment upload failed. '
        . 'Credential ID=' . $credentialId
        . ' Error=' . $e->getMessage()
    );

    http_response_code(500);

    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'message' => 'Attachment upload failed.',
    ]);
}
