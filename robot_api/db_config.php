<?php
// ==================== DATABASE CONFIGURATION ====================
// Smart Medi Box System - Cloud Ready
// Supports: PostgreSQL via Neon
// Version: 1.0.1

// Check if DATABASE_URL is set (Render/Railway deployment)
if (!empty(getenv('DATABASE_URL'))) {
    $db_url = parse_url(getenv('DATABASE_URL'));
    define('DB_HOST', $db_url['host']);
    define('DB_PORT', $db_url['port'] ?? 5432);
    define('DB_NAME', ltrim($db_url['path'], '/'));
    define('DB_USER', $db_url['user']);
    define('DB_PASSWORD', $db_url['pass']);
    define('DB_SSLMODE', 'require');
} else {
    // Local/Development configuration (Neon)
    define('DB_HOST', getenv('DB_HOST') ?: 'ep-shy-mountain-achv0blk-pooler.sa-east-1.aws.neon.tech');
    define('DB_PORT', getenv('DB_PORT') ?: 5432);
    define('DB_NAME', getenv('DB_NAME') ?: 'neondb');
    define('DB_USER', getenv('DB_USER') ?: 'neondb_owner');
    define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'npg_oZ0eaCKj1zYO');
    define('DB_SSLMODE', 'require');
}

define('DB_TYPE', 'postgresql');

// PostgreSQL Connection with SSL for cloud databases
$connection_string = "host=" . DB_HOST . 
                     " port=" . DB_PORT . 
                     " dbname=" . DB_NAME . 
                     " user=" . DB_USER . 
                     " password=" . DB_PASSWORD .
                     " sslmode=" . DB_SSLMODE;

$conn = pg_connect($connection_string);

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'PostgreSQL connection failed: ' . pg_last_error(),
        'hint' => 'Check database credentials and connectivity'
    ]);
    exit();
}

pg_set_client_encoding($conn, 'UTF-8');

// Set global timezone to GMT+5:30 (Asia/Colombo)
date_default_timezone_set('Asia/Colombo');
pg_query($conn, "SET TIMEZONE TO 'Asia/Colombo'");

define('DB_CONNECTED', true);
?>