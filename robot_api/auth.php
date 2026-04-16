<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Authentication API
 * ============================================================================
 * 
 * Handles QR-based authentication and user verification
 * Communicates with Arduino devices via GSM
 * 
 * Endpoints:
 * - POST /api/auth/verify - Verify existing user
 * - POST /api/auth/register - Register new user
 * - POST /api/auth/qr-generate - Generate QR code for device
 * - GET /api/auth/mac-lookup - Find user by MAC address
 * 
 * ============================================================================
 */

require_once 'db_config.php';

header('Content-Type: application/json');

// Debug mode - log all requests
$debug_log = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'path_info' => $_SERVER['PATH_INFO'] ?? 'NONE',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'NONE',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'NONE'
];

// Get request method
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));

error_log("AUTH REQUEST: " . json_encode($debug_log));
error_log("PARSED PATH: " . json_encode($request_uri));

// Skip the 'api' prefix if present
$start_index = (isset($request_uri[0]) && $request_uri[0] === 'api') ? 1 : 0;

// Get action and subaction from the path
// Path format: /api/auth/login or /api/auth/patient/signup
$action = $request_uri[$start_index + 1] ?? '';  // 'auth' or 'login' 
$subaction = $request_uri[$start_index + 2] ?? '';
$third_level = $request_uri[$start_index + 3] ?? '';

error_log("ACTION: $action, SUBACTION: $subaction, THIRD: $third_level");

// Determine which handler to call
// If first segment is 'auth', the actual action is in the next segment
if ($action === 'auth') {
    $actual_action = $subaction;
    $actual_subaction = $third_level;
} else {
    // Direct action path
    $actual_action = $action;
    $actual_subaction = $subaction;
}

error_log("ACTUAL_ACTION: $actual_action, ACTUAL_SUBACTION: $actual_subaction");

// Route to appropriate handler
switch ($actual_action) {
    case 'login':
        handleLogin($method);
        break;
    
    case 'patient':
        if ($actual_subaction === 'signup') {
            handlePatientSignup($method);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found']);
        }
        break;
    
    case 'doctor':
        if ($actual_subaction === 'signup') {
            handleDoctorSignup($method);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found']);
        }
        break;
    
    case 'verify':
        handleVerifyUser($method);
        break;
    
    case 'register':
        handleRegisterUser($method);
        break;
    
    case 'qr-generate':
        handleGenerateQR($method);
        break;
    
    case 'mac-lookup':
        handleMACLookup($method);
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found']);
        break;
}

// ============================================================================
// LOGIN USER (Email + Password)
// ============================================================================
function handleLogin($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    error_log("LOGIN INPUT: " . json_encode($input));
    
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;
    
    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Email and password required']);
        return;
    }
    
    try {
        // Check if user exists (PostgreSQL) - only use columns that exist
        $query = "SELECT id, email, password_hash, role FROM users WHERE email = $1";
        
        $result = pg_query_params($conn, $query, array($email));
        
        if (!$result) {
            throw new Exception("Query failed: " . pg_last_error($conn));
        }
        
        if (pg_num_rows($result) === 0) {
            http_response_code(401);
            echo json_encode(['status' => 'ERROR', 'message' => 'Invalid email or password']);
            logAuthentication(null, 'EMAIL:' . $email, 'FAILED');
            return;
        }
        
        $user = pg_fetch_assoc($result);
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['status' => 'ERROR', 'message' => 'Invalid email or password']);
            logAuthentication($user['id'], 'EMAIL:' . $email, 'FAILED');
            return;
        }
        
        // Generate token (7-day expiry)
        $token = generateAuthToken();
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Store token in database
        $tokenQuery = "INSERT INTO auth_tokens (user_id, token, expires_at) 
                     VALUES ($1, $2, $3)";
        $tokenResult = pg_query_params($conn, $tokenQuery, array($user['id'], $token, $expires_at));
        
        if (!$tokenResult) {
            throw new Exception("Failed to create token: " . pg_last_error($conn));
        }
        
        // Log successful authentication
        logAuthentication($user['id'], 'EMAIL:' . $email, 'SUCCESS');
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Login successful',
            'token' => $token,
            'user_id' => $user['id'],
            'role' => $user['role'],
            'profile' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);
        
        error_log("LOGIN SUCCESS: user_id=" . $user['id'] . ", email=$email");
    } catch (Exception $e) {
        error_log("Login Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Login failed: ' . $e->getMessage()]);
    }
}
                'role' => $user['role']
            ]
        ]);
    } catch (Exception $e) {
        error_log("Login Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Login failed']);
    }
}

// ============================================================================
// PATIENT SIGNUP
// ============================================================================
function handlePatientSignup($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    error_log("PATIENT SIGNUP INPUT: " . json_encode($input));
    
    $name = $input['name'] ?? null;
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;
    $nic = $input['nic'] ?? null;
    $dob = $input['dob'] ?? null;
    $phone = $input['phone'] ?? null;
    
    // Validate required fields
    $missing_fields = [];
    if (!$name) $missing_fields[] = 'name';
    if (!$email) $missing_fields[] = 'email';
    if (!$password) $missing_fields[] = 'password';
    if (!$nic) $missing_fields[] = 'nic';
    
    if (count($missing_fields) > 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'ERROR', 
            'message' => 'Missing required fields: ' . implode(', ', $missing_fields),
            'received_fields' => array_keys($input),
            'debug' => true
        ]);
        error_log("PATIENT SIGNUP - Missing fields: " . implode(', ', $missing_fields));
        return;
    }
    
    try {
        // Check if email exists
        $checkQuery = "SELECT id FROM users WHERE email = $1";
        $checkResult = pg_query_params($conn, $checkQuery, array($email));
        
        if (!$checkResult) {
            throw new Exception("Email check failed: " . pg_last_error($conn));
        }
        
        if (pg_num_rows($checkResult) > 0) {
            http_response_code(409);
            echo json_encode(['status' => 'ERROR', 'message' => 'Email already registered']);
            return;
        }
        
        // Check if NIC exists
        $nicQuery = "SELECT id FROM users WHERE nic = $1";
        $nicResult = pg_query_params($conn, $nicQuery, array($nic));
        
        if (!$nicResult) {
            throw new Exception("NIC check failed: " . pg_last_error($conn));
        }
        
        if (pg_num_rows($nicResult) > 0) {
            http_response_code(409);
            echo json_encode(['status' => 'ERROR', 'message' => 'NIC already registered']);
            return;
        }
        
        // Validate phone if provided
        if ($phone) {
            $phone = validatePhoneNumber($phone);
            if (!$phone) {
                http_response_code(400);
                echo json_encode(['status' => 'ERROR', 'message' => 'Invalid phone number']);
                return;
            }
        }
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        error_log("PATIENT SIGNUP - About to insert: name=$name, email=$email, nic=$nic, dob=$dob, phone=$phone");
        
        // Insert patient user (PostgreSQL)
        // Only use columns that actually exist in the users table
        $query = "INSERT INTO users (email, password_hash, nic, dob, role) 
                  VALUES ($1, $2, $3, $4, 'PATIENT')
                  RETURNING id";
        
        $result = pg_query_params($conn, $query, 
            array($email, $password_hash, $nic, $dob));
        
        if (!$result) {
            $error_msg = pg_last_error($conn);
            error_log("PATIENT SIGNUP INSERT FAILED: " . $error_msg);
            http_response_code(400);
            echo json_encode([
                'status' => 'ERROR', 
                'message' => 'Failed to create account: ' . $error_msg,
                'debug' => true
            ]);
            return;
        }
        
        $row = pg_fetch_assoc($result);
        $user_id = $row['id'];
        
        // Generate auth token
        $token = generateAuthToken();
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Store token
        $tokenQuery = "INSERT INTO auth_tokens (user_id, token, expires_at) 
                     VALUES ($1, $2, $3)";
        $tokenResult = pg_query_params($conn, $tokenQuery, array($user_id, $token, $expires_at));
        
        if (!$tokenResult) {
            throw new Exception("Failed to create auth token: " . pg_last_error($conn));
        }
        
        // Log registration
        logAuthentication($user_id, 'EMAIL:' . $email, 'REGISTERED');
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Patient account created',
            'token' => $token,
            'user_id' => $user_id,
            'role' => 'PATIENT',
            'profile' => [
                'id' => $user_id,
                'email' => $email,
                'nic' => $nic,
                'role' => 'PATIENT'
            ]
        ]);
        
        error_log("PATIENT SIGNUP SUCCESS: user_id=$user_id, email=$email");
    } catch (Exception $e) {
        error_log("Patient Signup Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'ERROR', 
            'message' => 'Signup failed: ' . $e->getMessage(),
            'debug' => true
        ]);
    }
}
                'id' => $userData['id'],
                'user_id' => $user_id,
                'name' => $name,
                'email' => $email,
                'nic' => $nic,
                'role' => 'PATIENT'
            ]
        ]);
    } catch (Exception $e) {
        error_log("Patient Signup Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Signup failed']);
    }
}

// ============================================================================
// DOCTOR SIGNUP
// ============================================================================
function handleDoctorSignup($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    error_log("DOCTOR SIGNUP INPUT: " . json_encode($input));
    
    $name = $input['name'] ?? null;
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;
    $nic = $input['nic'] ?? null;
    $license_number = $input['license_number'] ?? null;
    $specialty = $input['specialty'] ?? null;
    $phone = $input['phone'] ?? null;
    
    // Validate required fields
    $missing_fields = [];
    if (!$name) $missing_fields[] = 'name';
    if (!$email) $missing_fields[] = 'email';
    if (!$password) $missing_fields[] = 'password';
    if (!$nic) $missing_fields[] = 'nic';
    if (!$license_number) $missing_fields[] = 'license_number';
    
    if (count($missing_fields) > 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'ERROR', 
            'message' => 'Missing required fields: ' . implode(', ', $missing_fields),
            'received_fields' => array_keys($input),
            'debug' => true
        ]);
        error_log("DOCTOR SIGNUP - Missing fields: " . implode(', ', $missing_fields));
        return;
    }
    
    try {
        // Check if email exists
        $checkQuery = "SELECT id FROM users WHERE email = $1";
        $checkResult = pg_query_params($conn, $checkQuery, array($email));
        
        if (!$checkResult) {
            throw new Exception("Email check failed: " . pg_last_error($conn));
        }
        
        if (pg_num_rows($checkResult) > 0) {
            http_response_code(409);
            echo json_encode(['status' => 'ERROR', 'message' => 'Email already registered']);
            return;
        }
        
        // Check if NIC exists
        $nicQuery = "SELECT id FROM users WHERE nic = $1";
        $nicResult = pg_query_params($conn, $nicQuery, array($nic));
        
        if (!$nicResult) {
            throw new Exception("NIC check failed: " . pg_last_error($conn));
        }
        
        if (pg_num_rows($nicResult) > 0) {
            http_response_code(409);
            echo json_encode(['status' => 'ERROR', 'message' => 'NIC already registered']);
            return;
        }
        
        // Validate phone if provided
        if ($phone) {
            $phone = validatePhoneNumber($phone);
            if (!$phone) {
                http_response_code(400);
                echo json_encode(['status' => 'ERROR', 'message' => 'Invalid phone number']);
                return;
            }
        }
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        error_log("DOCTOR SIGNUP - About to insert: name=$name, email=$email, nic=$nic, license=$license_number, specialty=$specialty");
        
        // Insert doctor user (PostgreSQL) - only use columns that exist
        $query = "INSERT INTO users (email, password_hash, nic, license_number, specialty, role) 
                  VALUES ($1, $2, $3, $4, $5, 'DOCTOR')
                  RETURNING id";
        
        $result = pg_query_params($conn, $query, 
            array($email, $password_hash, $nic, $license_number, $specialty));
        
        if (!$result) {
            $error_msg = pg_last_error($conn);
            error_log("DOCTOR SIGNUP INSERT FAILED: " . $error_msg);
            http_response_code(400);
            echo json_encode([
                'status' => 'ERROR', 
                'message' => 'Failed to create account: ' . $error_msg,
                'debug' => true
            ]);
            return;
        }
        
        $row = pg_fetch_assoc($result);
        $user_id = $row['id'];
        
        // Generate auth token
        $token = generateAuthToken();
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Store token
        $tokenQuery = "INSERT INTO auth_tokens (user_id, token, expires_at) 
                     VALUES ($1, $2, $3)";
        $tokenResult = pg_query_params($conn, $tokenQuery, array($user_id, $token, $expires_at));
        
        if (!$tokenResult) {
            throw new Exception("Failed to create auth token: " . pg_last_error($conn));
        }
        
        // Log registration
        logAuthentication($user_id, 'EMAIL:' . $email, 'REGISTERED');
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Doctor account created',
            'token' => $token,
            'user_id' => $user_id,
            'role' => 'DOCTOR',
            'profile' => [
                'id' => $user_id,
                'email' => $email,
                'nic' => $nic,
                'license_number' => $license_number,
                'specialty' => $specialty,
                'role' => 'DOCTOR'
            ]
        ]);
        
        error_log("DOCTOR SIGNUP SUCCESS: user_id=$user_id, email=$email");
    } catch (Exception $e) {
        error_log("Doctor Signup Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'ERROR', 
            'message' => 'Signup failed: ' . $e->getMessage(),
            'debug' => true
        ]);
    }
}

// ============================================================================
// VERIFY USER AUTHENTICATION
// ============================================================================
function handleVerifyUser($method) {
    global $conn;
    
    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    // Get parameters
    $user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
    $mac_address = $_GET['mac'] ?? $_POST['mac'] ?? null;
    
    if (!$user_id || !$mac_address) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters']);
        return;
    }
    
    try {
        // Check if user exists and is active (PostgreSQL syntax)
        $query = "SELECT id, name, age, phone, mac_address, status FROM users 
                  WHERE user_id = $1 AND mac_address = $2 AND status = 'ACTIVE'";
        
        $result = pg_query_params($conn, $query, array($user_id, $mac_address));
        
        if (pg_num_rows($result) > 0) {
            $user = pg_fetch_assoc($result);
            
            // Log authentication
            logAuthentication($user['id'], $mac_address, 'SUCCESS');
            
            // Return success with user data
            http_response_code(200);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'User verified',
                'user_id' => $user_id,
                'name' => $user['name'],
                'age' => $user['age'],
                'phone' => $user['phone']
            ]);
        } else {
            // User not found or inactive
            logAuthentication(null, $mac_address, 'FAILED');
            
            http_response_code(401);
            echo json_encode(['status' => 'ERROR', 'message' => 'User not found or inactive']);
        }
    } catch (Exception $e) {
        error_log("Auth Verify Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Authentication failed']);
    }
}

// ============================================================================
// REGISTER NEW USER
// ============================================================================
function handleRegisterUser($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    // Get JSON input or POST parameters
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $name = $input['name'] ?? $_POST['name'] ?? null;
    $age = $input['age'] ?? $_POST['age'] ?? null;
    $phone = $input['phone'] ?? $_POST['phone'] ?? null;
    $mac_address = $input['mac_address'] ?? $_POST['mac'] ?? null;
    
    if (!$name || !$age || !$phone || !$mac_address) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters']);
        return;
    }
    
    try {
        // Validate phone format
        $phone = validatePhoneNumber($phone);
        if (!$phone) {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Invalid phone number format']);
            return;
        }
        
        // Check if MAC address already exists (PostgreSQL)
        $checkQuery = "SELECT id FROM users WHERE mac_address = $1";
        $checkResult = pg_query_params($conn, $checkQuery, array($mac_address));
        
        if (pg_num_rows($checkResult) > 0) {
            http_response_code(409);
            echo json_encode(['status' => 'ERROR', 'message' => 'Device already registered']);
            return;
        }
        
        // Generate unique user ID
        $user_id = generateUserID();
        
        // Insert new user (PostgreSQL)
        $query = "INSERT INTO users (user_id, name, age, phone, mac_address, status) 
                  VALUES ($1, $2, $3, $4, $5, 'ACTIVE')";
        
        $result = pg_query_params($conn, $query, 
            array($user_id, $name, $age, $phone, $mac_address));
        
        if ($result) {
            // Log registration
            logAuthentication(null, $mac_address, 'REGISTERED');
            
            http_response_code(201);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'User registered successfully',
                'user_id' => $user_id,
                'name' => $name,
                'phone' => $phone
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to register user']);
        }
    } catch (Exception $e) {
        error_log("Register Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Registration failed']);
    }
}

// ============================================================================
// GENERATE QR CODE
// ============================================================================
function handleGenerateQR($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    // Get JSON input or POST parameters
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? null;
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'user_id required']);
        return;
    }
    
    try {
        // Generate QR token (random string)
        $qr_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        // Get user database ID from user_id (PostgreSQL)
        $user_query = "SELECT id FROM users WHERE user_id = $1";
        $user_result = pg_query_params($conn, $user_query, array($user_id));
        
        if (pg_num_rows($user_result) === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'User not found']);
            return;
        }
        
        $user_data = pg_fetch_assoc($user_result);
        $db_user_id = $user_data['id'];
        
        // Insert QR token (PostgreSQL)
        $insert_query = "INSERT INTO qr_tokens (user_id, token, expires_at, is_used) 
                        VALUES ($1, $2, $3, false)";
        
        pg_query_params($conn, $insert_query, array($db_user_id, $qr_token, $expires_at));
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'qr_token' => $qr_token,
            'expires_at' => $expires_at,
            'qr_data' => "AUTH|{$user_id}|{$qr_token}"
        ]);
    } catch (Exception $e) {
        error_log("QR Generate Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'QR generation failed']);
    }
}

// ============================================================================
// LOOKUP USER BY MAC ADDRESS
// ============================================================================
function handleMACLookup($method) {
    global $conn;
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $mac_address = $_GET['mac'] ?? null;
    
    if (!$mac_address) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'MAC address required']);
        return;
    }
    
    try {
        // Look up user by MAC (PostgreSQL)
        $query = "SELECT id, user_id, name, status FROM users WHERE mac_address = $1";
        $result = pg_query_params($conn, $query, array($mac_address));
        
        if (pg_num_rows($result) > 0) {
            $user = pg_fetch_assoc($result);
            http_response_code(200);
            echo json_encode([
                'status' => 'FOUND',
                'user_id' => $user['user_id'],
                'name' => $user['name'],
                'user_status' => $user['status']
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'NOT_FOUND', 'message' => 'User not found']);
        }
    } catch (Exception $e) {
        error_log("MAC Lookup Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Lookup failed']);
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function generateUserID() {
    // Generate format: USER_YYYYMMDD_XXXXX
    $date = date('Ymd');
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    return 'USER_' . $date . '_' . $random;
}

function generateAuthToken() {
    // Generate 64-character random token
    return bin2hex(random_bytes(32));
}

function validatePhoneNumber($phone) {
    // Remove any non-numeric characters except +
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // If doesn't start with +94, handle different formats
    if (strpos($phone, '0') === 0) {
        // 0XXXXXXXXX -> 94XXXXXXXXX
        $phone = '94' . substr($phone, 1);
    } elseif (strpos($phone, '+94') === 0) {
        // Already +94 format, remove the +
        $phone = substr($phone, 1);
    } elseif (strpos($phone, '94') !== 0) {
        // Add 94 prefix if missing
        $phone = '94' . $phone;
    }
    
    // Ensure proper format: 94XXXXXXXXX (11-13 digits)
    if (!preg_match('/^94\d{9,11}$/', $phone)) {
        return false;
    }
    
    return '+' . $phone; // Return with + prefix
}

function logAuthentication($user_id, $mac_address, $status) {
    global $conn;
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Log authentication using PostgreSQL
        $query = "INSERT INTO auth_logs (user_id, mac_address, status, ip_address, user_agent) 
                 VALUES ($1, $2, $3, $4, $5)";
        
        pg_query_params($conn, $query, array($user_id, $mac_address, $status, $ip_address, $user_agent));
    } catch (Exception $e) {
        error_log("Auth Log Error: " . $e->getMessage());
    }
}

?>
