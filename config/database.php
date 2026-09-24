<?php
declare(strict_types=1);

$configFile = __DIR__ . '/client.php';

if (!is_file($configFile)) {
    http_response_code(500);
    exit('Client configuration is not installed.');
}

$config = require $configFile;

$host = (string)($config['db_host'] ?? 'localhost');
$port = (int)($config['db_port'] ?? 3306);
$dbname = (string)($config['db_name'] ?? '');
$username = (string)($config['db_user'] ?? '');
$password = (string)($config['db_password'] ?? '');

if ($dbname === '' || $username === '' || $password === '') {
    http_response_code(500);
    exit('Database configuration is incomplete.');
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed.');
}
