<?php

// Database connection using PDO for PostgreSQL (Supabase)
// Environment variables are set in Vercel dashboard (or .env for local)

/**
 * Retrieve an environment variable from all possible sources.
 * vercel-php may expose vars via $_SERVER, $_ENV, or getenv().
 */
if (!function_exists('env')) {
function env(string $key, string $default = ''): string {
    // 1. getenv() &mdash; works on most PHP setups
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;

    // 2. $_ENV &mdash; populated when variables_order includes 'E'
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];

    // 3. $_SERVER &mdash; vercel-php often injects env vars here
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];

    return $default;
}
}

// Try DATABASE_URL first (single connection string), then individual vars
$database_url = env('DATABASE_URL', '');

if (!empty($database_url)) {
    // Parse the DATABASE_URL connection string
    $params = parse_url($database_url);
    $db_host = $params['host'] ?? '';
    $db_port = $params['port'] ?? '6543';
    $db_name = ltrim($params['path'] ?? '/postgres', '/');
    $db_user = $params['user'] ?? '';
    $db_password = urldecode($params['pass'] ?? '');
} else {
    // Supabase Connection Pooler (Transaction mode for Serverless speed)
    $db_host     = env('DB_HOST',     'aws-1-ap-northeast-1.pooler.supabase.com');
    $db_port     = env('DB_PORT',     '6543');
    $db_name     = env('DB_NAME',     'postgres');
    $db_user     = env('DB_USER',     'postgres.zuojozzmfokpfemqcxcg');
    $db_password = env('DB_PASSWORD', 'Masbro24andLatte26');
}

try {
    $dsn = "pgsql:host={$db_host};port={$db_port};dbname={$db_name};sslmode=require";
    $pdo = new PDO(
        $dsn,
        $db_user,
        $db_password,
        [
            PDO::ATTR_PERSISTENT         => true,
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
