<?php
/**
 * Debug signup endpoint
 * Tests the exact request the frontend is sending
 */

require_once 'db_config.php';

echo "=== PATIENT SIGNUP DEBUG TEST ===\n\n";

// Simulate what the frontend sends
$test_data = [
    'name' => 'Test Patient ' . time(),
    'email' => 'test' . time() . '@example.com',
    'password' => 'TestPass123!',
    'nic' => 'NIC' . time(),
    'dob' => '1990-01-15',
    'phone' => '0777123456'
];

echo "Test Data:\n";
echo json_encode($test_data, JSON_PRETTY_PRINT) . "\n\n";

// Extract fields
$name = $test_data['name'];
$email = $test_data['email'];
$password = $test_data['password'];
$nic = $test_data['nic'];
$dob = $test_data['dob'];
$phone = $test_data['phone'];

echo "Extracted fields:\n";
echo "  Name: $name\n";
echo "  Email: $email\n";
echo "  Password: " . strlen($password) . " chars\n";
echo "  NIC: $nic\n";
echo "  DOB: $dob\n";
echo "  Phone: $phone\n\n";

try {
    // Check database connection
    if (!$conn) {
        die("ERROR: No database connection\n");
    }
    echo "✓ Database connected\n\n";
    
    // Check if tables exist
    $tablesQuery = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'";
    $tablesResult = pg_query($conn, $tablesQuery);
    echo "Available tables:\n";
    while ($row = pg_fetch_assoc($tablesResult)) {
        echo "  - " . $row['table_name'] . "\n";
    }
    echo "\n";
    
    // Check users table structure
    $columnsQuery = "SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'users' ORDER BY ordinal_position";
    $columnsResult = pg_query($conn, $columnsQuery);
    echo "Users table columns:\n";
    while ($row = pg_fetch_assoc($columnsResult)) {
        $nullable = $row['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
        echo "  - " . str_pad($row['column_name'], 20) . " | " . str_pad($row['data_type'], 15) . " | $nullable\n";
    }
    echo "\n";
    
    // Step 1: Check if email exists
    echo "Step 1: Checking if email exists...\n";
    $checkQuery = "SELECT id FROM users WHERE email = $1";
    $checkResult = pg_query_params($conn, $checkQuery, array($email));
    if (!$checkResult) {
        echo "  ERROR: " . pg_last_error($conn) . "\n";
    } else {
        if (pg_num_rows($checkResult) > 0) {
            echo "  ✓ Email already exists\n";
        } else {
            echo "  ✓ Email is unique\n";
        }
    }
    echo "\n";
    
    // Step 2: Check if NIC exists
    echo "Step 2: Checking if NIC exists...\n";
    $nicQuery = "SELECT id FROM users WHERE nic = $1";
    $nicResult = pg_query_params($conn, $nicQuery, array($nic));
    if (!$nicResult) {
        echo "  ERROR: " . pg_last_error($conn) . "\n";
    } else {
        if (pg_num_rows($nicResult) > 0) {
            echo "  ✓ NIC already exists\n";
        } else {
            echo "  ✓ NIC is unique\n";
        }
    }
    echo "\n";
    
    // Step 3: Generate user ID and hash password
    echo "Step 3: Generating user ID and hashing password...\n";
    $user_id = 'USER_' . date('Ymd') . '_' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    echo "  User ID: $user_id\n";
    echo "  Password Hash: " . substr($password_hash, 0, 40) . "...\n";
    echo "  Hash length: " . strlen($password_hash) . "\n";
    echo "\n";
    
    // Step 4: Calculate age
    echo "Step 4: Calculating age from DOB...\n";
    $age = null;
    try {
        $age = date_diff(date_create($dob), date_create('today'))->y;
        echo "  Age: $age\n";
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Step 5: Validate phone
    echo "Step 5: Validating phone...\n";
    if ($phone) {
        $original_phone = $phone;
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strpos($phone, '0') === 0) {
            $phone = '94' . substr($phone, 1);
        } elseif (strpos($phone, '+94') === 0) {
            $phone = substr($phone, 1);
        } elseif (strpos($phone, '94') !== 0) {
            $phone = '94' . $phone;
        }
        if (!preg_match('/^94\d{9,11}$/', $phone)) {
            echo "  ERROR: Invalid phone format after processing\n";
            echo "  Original: $original_phone -> Processed: $phone\n";
            $phone = null;
        } else {
            $phone = '+' . $phone;
            echo "  ✓ Phone valid: $phone\n";
        }
    } else {
        echo "  Phone not provided (optional)\n";
    }
    echo "\n";
    
    // Step 6: Test INSERT query
    echo "Step 6: Testing INSERT query...\n";
    $insertQuery = "INSERT INTO users (user_id, name, email, password_hash, nic, dob, age, phone, role, status) 
                    VALUES ($1, $2, $3, $4, $5, $6, $7, $8, 'PATIENT', 'ACTIVE')";
    
    echo "  Query: " . str_replace('$1', '\$1', $insertQuery) . "\n";
    echo "  Parameters:\n";
    echo "    [1] user_id: $user_id\n";
    echo "    [2] name: $name\n";
    echo "    [3] email: $email\n";
    echo "    [4] password_hash: " . substr($password_hash, 0, 40) . "...\n";
    echo "    [5] nic: $nic\n";
    echo "    [6] dob: $dob\n";
    echo "    [7] age: " . ($age ?? 'NULL') . "\n";
    echo "    [8] phone: " . ($phone ?? 'NULL') . "\n";
    echo "\n";
    
    $result = pg_query_params($conn, $insertQuery, 
        array($user_id, $name, $email, $password_hash, $nic, $dob, $age, $phone));
    
    if (!$result) {
        echo "  ✗ INSERT FAILED!\n";
        echo "  Error: " . pg_last_error($conn) . "\n";
    } else {
        echo "  ✓ INSERT SUCCESS!\n";
        
        // Step 7: Retrieve the user
        echo "\nStep 7: Retrieving created user...\n";
        $userQuery = "SELECT id, user_id, name, email, role FROM users WHERE user_id = $1";
        $userResult = pg_query_params($conn, $userQuery, array($user_id));
        if (!$userResult || pg_num_rows($userResult) === 0) {
            echo "  ✗ Could not retrieve user\n";
        } else {
            $userData = pg_fetch_assoc($userResult);
            echo "  ✓ User found!\n";
            echo "    ID: " . $userData['id'] . "\n";
            echo "    User ID: " . $userData['user_id'] . "\n";
            echo "    Name: " . $userData['name'] . "\n";
            echo "    Email: " . $userData['email'] . "\n";
            echo "    Role: " . $userData['role'] . "\n";
        }
    }
    
    echo "\n✓ Debug test completed successfully!\n";

} catch (Exception $e) {
    echo "\n✗ Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

?>
