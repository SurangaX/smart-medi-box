<?php
// ==================== DATABASE CONFIGURATION ====================
// Smart Medi Box System - Cloud Ready
// Supports: PostgreSQL via Neon
// Version: 1.0.1

// Suppress all error output to prevent HTML from being sent
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set custom error log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/api_debug.log');

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

// SMS API Configuration (SMSAPI.LK)
define('SMSAPI_TOKEN', '517|7L7cDWKQg1DYZXZHE4TPCQl9RTeIE8mWkXQP1QUE');
define('SMSAPI_ENDPOINT', 'https://dashboard.smsapi.lk/api/v3/sms/send');
define('SMSAPI_SENDER_ID', 'SmartMedi');

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

/**
 * Send push notification via ntfy.sh
 * Simple, reliable, and bypasses Google/FCM registration limits.
 */
function sendNtfyNotification($userId, $title, $body) {
    if (empty($userId)) return false;

    // Use a unique topic for each user
    $topic = "smart_medibox_user_" . $userId;
    $url = "https://ntfy.sh/" . $topic;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Title: " . $title,
        "Priority: high",
        "Tags: pill,bell"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200);
}
?>