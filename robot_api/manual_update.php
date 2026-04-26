<?php
require_once 'db_config.php';
$user_id = 51;
$token = null;
$query = "UPDATE users SET expo_push_token = $1 WHERE id = $2";
$res = pg_query_params($conn, $query, [$token, $user_id]);
if ($res && pg_affected_rows($res) > 0) {
    echo "SUCCESS: Token updated for user 51\n";
} else {
    echo "FAILED: No rows updated. Error: " . pg_last_error($conn) . "\n";
}
?>
