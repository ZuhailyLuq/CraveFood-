<?php

// Database connection using PDO for PostgreSQL (Supabase)
// Environment variables are set in Vercel dashboard (or .env for local)

$db_host     = getenv('DB_HOST')     ?: 'db.zuojozzmfokpfemqcxcg.supabase.co';
$db_port     = getenv('DB_PORT')     ?: '5432';
$db_name     = getenv('DB_NAME')     ?: 'postgres';
$db_user     = getenv('DB_USER')     ?: 'postgres';
$db_password = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "pgsql:host={$db_host};port={$db_port};dbname={$db_name}",
        $db_user,
        $db_password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    // Temporarily expose details for debugging connection failure
    die(json_encode([
        'error' => 'Database connection failed: ' . $e->getMessage(),
        'debug' => [
            'host' => $db_host,
            'port' => $db_port,
            'name' => $db_name,
            'user' => $db_user,
            'has_password' => !empty($db_password)
        ]
    ]));
}

// Backwards-compatibility shim: keep $conn alias pointing to $pdo
// so any legacy code using $conn still works during migration
$conn = $pdo;
