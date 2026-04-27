<?php
require_once 'db_config.php';

header('Content-Type: application/json');

$phone = $_GET['phone'] ?? '';
$message = $_GET['message'] ?? 'Test message from Smart Medi Box Diagnostic';

if (empty($phone)) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Please provide a phone number in the URL: ?phone=07XXXXXXXX'
    ]);
    exit();
}

echo json_encode([
    'diagnostic' => 'Attempting to send SMS...',
    'recipient' => $phone,
    'message' => $message,
    'result' => sendSMSNotification($phone, $message)
]);
?>