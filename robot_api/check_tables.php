<?php
require_once 'db_config.php';
header('Content-Type: application/json');
try {
    $query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'";
    $result = pg_query($conn, $query);
    $tables = [];
    while ($row = pg_fetch_assoc($result)) {
        $tables[] = $row['table_name'];
    }
    echo json_encode(['status' => 'SUCCESS', 'tables' => $tables], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
}
?>
