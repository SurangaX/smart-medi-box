<?php
require_once 'db_config.php';
header('Content-Type: application/json');
try {
    $query = "SELECT id, name, expo_push_token FROM users WHERE id = 51";
    $result = pg_query($conn, $query);
    $user = pg_fetch_assoc($result);
    echo json_encode(['status' => 'SUCCESS', 'user' => $user], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
}
?>
