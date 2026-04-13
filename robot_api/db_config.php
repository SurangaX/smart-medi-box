<?php
// ==================== DATABASE CONFIGURATION ====================
// Smart Medi Box System
// Supports: PostgreSQL (Recommended) or MySQL
// Version: 1.0.0

// ===== POSTGRESQL (Recommended) =====
define('DB_TYPE', 'postgresql');
define('DB_HOST', 'localhost');
define('DB_PORT', 5432);
define('DB_NAME', 'smart_medi_box');
define('DB_USER', 'postgres');
define('DB_PASSWORD', '123');

// PostgreSQL Connection
$connection_string = "host=" . DB_HOST . 
                     " port=" . DB_PORT . 
                     " dbname=" . DB_NAME . 
                     " user=" . DB_USER . 
                     " password=" . DB_PASSWORD;

$conn = pg_connect($connection_string);

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'PostgreSQL connection failed: ' . pg_last_error()
    ]);
    exit();
}

pg_set_client_encoding($conn, 'UTF-8');

// ===== MYSQL (Alternative - Comment out PostgreSQL above to use) =====
/*
define('DB_TYPE', 'mysql');
define('DB_HOST', 'localhost');
define('DB_USER', 'medi_user');
define('DB_PASSWORD', 'password123');
define('DB_NAME', 'smart_medi_box');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'MySQL connection failed: ' . $conn->connect_error
    ]);
    exit();
}

$conn->set_charset("utf8mb4");
*/

define('DEBUG_MODE', true);
?>