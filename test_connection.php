<?php
require 'robot_api/db_config.php';

if ($conn) {
    echo "✓ SUCCESS: Connected to PostgreSQL smart_medi_box database\n";
    
    // Test a simple query
    $result = pg_query($conn, "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'public'");
    $row = pg_fetch_assoc($result);
    echo "✓ Tables found: " . $row['table_count'] . "\n";
    
    pg_close($conn);
} else {
    die("✗ FAILED: " . pg_last_error() . "\n");
}
?>
