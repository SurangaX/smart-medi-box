<?php
require_once 'db_config.php';

// Simulate the input that would come from the frontend
$_SERVER['REQUEST_METHOD'] = 'POST';
$input = [
    'user_id' => 55, // sachini
    'expo_push_token' => 'ExponentPushToken[TEST_TOKEN_12345]'
];

// Mock file_get_contents('php://input')
// We can't easily mock php://input, so we'll just call the function directly with custom logic
function testHandleUpdatePushToken($user_id, $push_token) {
    global $conn;
    try {
        $query = "UPDATE users SET expo_push_token = $1, updated_at = NOW() WHERE id = $2";
        $result = pg_query_params($conn, $query, array($push_token, $user_id));
        
        if ($result && pg_affected_rows($result) > 0) {
            echo "SUCCESS: Token updated for user $user_id\n";
        } else {
            echo "FAILED: No rows affected for user $user_id. Does user exist?\n";
            $check = pg_query_params($conn, "SELECT id FROM users WHERE id = $1", [$user_id]);
            if (pg_num_rows($check) > 0) {
                echo "  User exists, but UPDATE failed. Check column name.\n";
            } else {
                echo "  User DOES NOT exist.\n";
            }
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

testHandleUpdatePushToken($input['user_id'], $input['expo_push_token']);
?>
