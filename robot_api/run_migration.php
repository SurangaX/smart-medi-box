<?php
/**
 * Database Migration Runner
 * Adds authentication fields to users table
 */

require_once 'db_config.php';

echo "Starting database migration...\n\n";

try {
    // Execute all SQL files in the migrations directory in alphabetical order
    $migrationsDir = __DIR__ . '/migrations';
    if (!is_dir($migrationsDir)) {
        echo "No migrations directory found at {$migrationsDir}\n";
        exit(0);
    }

    $files = glob($migrationsDir . '/*.sql');
    sort($files, SORT_STRING);

    if (empty($files)) {
        echo "No .sql migration files to run in {$migrationsDir}\n";
        exit(0);
    }

    foreach ($files as $i => $file) {
        echo "Running migration file: " . basename($file) . "\n";
        $sql = file_get_contents($file);
        if ($sql === false) {
            echo "  ✖ Failed to read file: " . basename($file) . "\n";
            continue;
        }

        $result = pg_query($conn, $sql);
        if ($result === false) {
            echo "  ⚠ WARNING executing " . basename($file) . ": " . pg_last_error($conn) . "\n";
        } else {
            echo "  ✓ Applied " . basename($file) . "\n";
        }
    }

    echo "\n✓ Migration run completed.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
