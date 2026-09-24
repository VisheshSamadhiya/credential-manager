<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$region = getenv('AWS_REGION');
$bucket = getenv('AWS_S3_BUCKET');
$prefix = trim((string) getenv('AWS_S3_PREFIX'), '/');

if (!$region) {
    throw new RuntimeException('AWS_REGION is not configured.');
}

if (!$bucket) {
    throw new RuntimeException('AWS_S3_BUCKET is not configured.');
}

$s3 = new S3Client([
    'version' => 'latest',
    'region'  => $region,
]);

function s3ObjectKey(string $filename): string
{
    global $prefix;

    $filename = basename($filename);

    if ($filename === '' || $filename === '.' || $filename === '..') {
        throw new InvalidArgumentException('Invalid filename.');
    }

    return $prefix !== ''
        ? $prefix . '/' . $filename
        : $filename;
}

function s3Upload(string $filename, string $contents, string $contentType = 'application/octet-stream'): string
{
    global $s3, $bucket;

    $key = s3ObjectKey($filename);

    $s3->putObject([
        'Bucket'      => $bucket,
        'Key'         => $key,
        'Body'        => $contents,
        'ContentType' => $contentType,
    ]);

    return $key;
}

function s3Download(string $key): string
{
    global $s3, $bucket;

    $result = $s3->getObject([
        'Bucket' => $bucket,
        'Key'    => $key,
    ]);

    return (string) $result['Body'];
}

function s3Delete(string $key): void
{
    global $s3, $bucket;

    $s3->deleteObject([
        'Bucket' => $bucket,
        'Key'    => $key,
    ]);
}
