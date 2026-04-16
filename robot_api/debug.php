<?php
require_once 'db_config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Return exactly what was received
echo json_encode([
    'status' => 'DEBUG',
    'received_data' => $input,
    'received_keys' => array_keys($input),
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
    'timestamp' => date('Y-m-d H:i:s')
]);

error_log("DEBUG ENDPOINT - Received: " . json_encode($input));
?>
