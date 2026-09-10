cat > config/database.php <<'EOF'
<?php

$host = getenv('DB_HOST') ?: 'mariadb';
$dbname = getenv('DB_NAME') ?: 'credential_manager';
$username = getenv('DB_USER') ?: 'credential_app';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
EOF
