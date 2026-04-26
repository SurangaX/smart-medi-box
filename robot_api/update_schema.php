<?php
require_once 'db_config.php';
try {
    // Add real-time state columns to devices table
    pg_query($conn, "ALTER TABLE devices ADD COLUMN IF NOT EXISTS door_state VARCHAR(20) DEFAULT 'CLOSED'");
    pg_query($conn, "ALTER TABLE devices ADD COLUMN IF NOT EXISTS lock_state VARCHAR(20) DEFAULT 'LOCKED'");
    pg_query($conn, "ALTER TABLE devices ADD COLUMN IF NOT EXISTS alarm_state VARCHAR(20) DEFAULT 'INACTIVE'");
    echo "Database schema updated successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
