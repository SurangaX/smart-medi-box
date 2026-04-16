<?php
/**
 * Database Initialization Script
 * Runs setup SQL to create required tables
 * 
 * Usage: Visit https://yourdomain.com/api/init-database in browser
 * Or run: php init_database.php from command line
 */

require_once 'db_config.php';

// Check if already initialized
$check_query = "SELECT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_name = 'users')";
$check_result = pg_query($GLOBALS['conn'], $check_query);
$row = pg_fetch_array($check_result);
$tables_exist = $row[0];

if ($tables_exist) {
    echo "✅ Database tables already exist. No action needed.\n";
    echo "If you need to reset, manually run the SQL in create_auth_tables.sql\n";
    exit(0);
}

echo "Setting up database tables...\n";

// Read and execute the SQL file
$sql_file = __DIR__ . '/create_auth_tables.sql';

if (!file_exists($sql_file)) {
    echo "❌ Error: create_auth_tables.sql not found at $sql_file\n";
    exit(1);
}

$sql = file_get_contents($sql_file);

// Split by semicolons and execute each statement
$statements = array_filter(array_map('trim', preg_split('/;/', $sql)));

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue; // Skip empty lines and comments
    }

    $result = pg_query($GLOBALS['conn'], $statement);
    
    if ($result) {
        $success_count++;
    } else {
        $error_count++;
        $error = pg_last_error($GLOBALS['conn']);
        // If table already exists, it's not really an error
        if (strpos($error, 'already exists') !== false) {
            echo "⚠️  Table already exists (this is OK)\n";
            $error_count--; // Don't count as error
        } else {
            echo "❌ Error: " . $error . "\n";
        }
    }
}

echo "\n";
echo "========================================\n";
echo "Database Initialization Complete!\n";
echo "========================================\n";
echo "✅ Successful statements: $success_count\n";

if ($error_count > 0) {
    echo "⚠️  Errors: $error_count (some may be expected)\n";
}

echo "\nYou can now:\n";
echo "1. Create a new user account via /api/auth/signup\n";
echo "2. Login via /api/auth/login\n";
echo "3. Pair devices via the dashboard\n";
echo "\n";
echo "For detailed database info:\n";
echo "SELECT * FROM users;\n";
echo "SELECT * FROM auth_tokens;\n";
echo "SELECT * FROM patients;\n";
echo "SELECT * FROM device_registry;\n";

exit(0);
?>
