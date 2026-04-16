<?php
/**
 * Database Migration Runner
 * Adds authentication fields to users table
 */

require_once 'db_config.php';

echo "Starting database migration...\n\n";

try {
    // Execute migration statements in order
    $migrations = [
        // Step 1: Add missing columns to users table
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100) UNIQUE",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255)",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS nic VARCHAR(20) UNIQUE",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS dob DATE",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS license_number VARCHAR(50) UNIQUE",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS specialty VARCHAR(100)",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'PATIENT'",
        
        // Step 2: Create auth_tokens table
        "CREATE TABLE IF NOT EXISTS auth_tokens (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token VARCHAR(255) UNIQUE NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Step 3: Create indexes
        "CREATE INDEX IF NOT EXISTS idx_auth_tokens_user_id ON auth_tokens(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_auth_tokens_token ON auth_tokens(token)",
        "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
        "CREATE INDEX IF NOT EXISTS idx_users_nic ON users(nic)",
        "CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)"
    ];
    
    foreach ($migrations as $i => $statement) {
        echo "Step " . ($i + 1) . ": ";
        echo substr($statement, 0, 70) . "...\n";
        
        $result = pg_query($conn, $statement);
        
        if ($result === false) {
            echo "  ⚠ WARNING: " . pg_last_error($conn) . "\n";
        } else {
            echo "  ✓ Success\n";
        }
    }
    
    echo "\n✓ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
