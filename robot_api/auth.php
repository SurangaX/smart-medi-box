<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Authentication API
 * ============================================================================
 * Version: 1.2.0
 * 
 * Endpoints:
 * - POST /api/auth/login - Email/password login
 * - POST /api/auth/patient/signup - Patient registration
 * - POST /api/auth/doctor/signup - Doctor registration
 * - POST /api/auth/verify - MAC-based verification (legacy)
 * - POST /api/auth/register - MAC-based registration (legacy)
 * - POST /api/auth/qr-generate - Generate QR token (legacy)
 * - POST /api/auth/generate-pairing-token - Generate device pairing token
 * - POST /api/auth/complete-pairing - Complete device pairing
 * 
 * ============================================================================
 */

require_once 'db_config.php';

// Enable CORS for all requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
header('Access-Control-Max-Age: 86400');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse request path
$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_SERVER['PATH_INFO'] ?? '', '/');
$segments = explode('/', $path);

// Remove 'api' segment if present
if (isset($segments[0]) && $segments[0] === 'api') {
    array_shift($segments);
}

// Remove 'auth' segment if present
if (isset($segments[0]) && $segments[0] === 'auth') {
    array_shift($segments);
}

$action = $segments[0] ?? '';
$subaction = $segments[1] ?? '';

error_log("AUTH REQUEST: method=$method, action=$action, subaction=$subaction");

// Route to handler
switch ($action) {
    case 'login':
        handleLogin($method);
        break;
    
    case 'patient':
        if ($subaction === 'signup') {
            handlePatientSignup($method);
        } else {
            errorResponse(404, 'Endpoint not found');
        }
        break;
    
    case 'doctor':
        if ($subaction === 'signup') {
            handleDoctorSignup($method);
        } else {
            errorResponse(404, 'Endpoint not found');
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
    
    case 'generate-pairing-token':
        handleGeneratePairingToken($method);
        break;
    
    case 'complete-pairing':
        handleCompletePairing($method);
        break;
    
    default:
        errorResponse(404, 'Endpoint not found');
}

// ============================================================================
// HANDLER: LOGIN
// ============================================================================
function handleLogin($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    error_log("LOGIN INPUT: " . json_encode($input));
    
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;
    
    if (!$email || !$password) {
        return errorResponse(400, 'Email and password required');
    }
    
    try {
        $query = "SELECT id, email, password_hash, role FROM users WHERE email = $1";
        $result = pg_query_params($conn, $query, [$email]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        if (pg_num_rows($result) === 0) {
            error_log("LOGIN FAILED: User not found - $email");
            return errorResponse(401, 'Invalid email or password');
        }
        
        $user = pg_fetch_assoc($result);
        
        if (!password_verify($password, $user['password_hash'])) {
            error_log("LOGIN FAILED: Wrong password - $email");
            return errorResponse(401, 'Invalid email or password');
        }
        
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        error_log("LOGIN - Creating token for user_id: {$user['id']}, expires_at: $expires_at");
        
        $tokenQuery = "INSERT INTO session_tokens (user_id, token, expires_at) VALUES ($1, $2, $3)";
        $tokenResult = pg_query_params($conn, $tokenQuery, [$user['id'], $token, $expires_at]);
        
        if (!$tokenResult) {
            throw new Exception("Failed to create token: " . pg_last_error($conn));
        }
        
        error_log("LOGIN SUCCESS: {$user['id']} - $email");
        
        // Build profile object including extra fields from patients/doctors tables when available
        $profile = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ];

        try {
            if ($user['role'] === 'PATIENT') {
                $pQuery = "SELECT nic, name, phone_number, blood_type, transplanted_organ FROM patients WHERE user_id = $1";
                $pResult = pg_query_params($conn, $pQuery, [$user['id']]);
                if ($pResult && pg_num_rows($pResult) > 0) {
                    $pRow = pg_fetch_assoc($pResult);
                    $profile = array_merge($profile, [
                        'nic' => $pRow['nic'] ?? null,
                        'name' => $pRow['name'] ?? null,
                        'phone_number' => $pRow['phone_number'] ?? null,
                        'blood_type' => $pRow['blood_type'] ?? null,
                        'transplanted_organ' => $pRow['transplanted_organ'] ?? null
                    ]);
                }
            } else if ($user['role'] === 'DOCTOR') {
                $dQuery = "SELECT nic, name, specialization, hospital, license_number, phone_number FROM doctors WHERE user_id = $1";
                $dResult = pg_query_params($conn, $dQuery, [$user['id']]);
                if ($dResult && pg_num_rows($dResult) > 0) {
                    $dRow = pg_fetch_assoc($dResult);
                    $profile = array_merge($profile, [
                        'nic' => $dRow['nic'] ?? null,
                        'name' => $dRow['name'] ?? null,
                        'specialization' => $dRow['specialization'] ?? ($dRow['specialty'] ?? null),
                        'hospital' => $dRow['hospital'] ?? null,
                        'license_number' => $dRow['license_number'] ?? null,
                        'phone_number' => $dRow['phone_number'] ?? null
                    ]);
                }
            }
        } catch (Exception $e) {
            // ignore extra profile lookup errors
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'token' => $token,
            'user_id' => $user['id'],
            'role' => $user['role'],
            'profile' => $profile
        ]);
    } catch (Exception $e) {
        error_log("LOGIN ERROR: " . $e->getMessage());
        return errorResponse(500, 'Login failed: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: PATIENT SIGNUP
// ============================================================================
function handlePatientSignup($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    error_log("PATIENT SIGNUP INPUT: " . json_encode($input));
    
    $name = $input['name'] ?? null;
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;
    $nic = $input['nic'] ?? null;
    $dob = $input['dob'] ?? null;
    $phone = $input['phone'] ?? null;
    
    // Validate required fields
    $missing = [];
    if (!$name) $missing[] = 'name';
    if (!$email) $missing[] = 'email';
    if (!$password) $missing[] = 'password';
    if (!$nic) $missing[] = 'nic';
    
    if (!empty($missing)) {
        error_log("PATIENT SIGNUP FAILED: Missing fields - " . implode(', ', $missing));
        error_log("PATIENT SIGNUP - Received data: " . json_encode($input));
        http_response_code(400);
        echo json_encode([
            'status' => 'ERROR',
            'message' => 'Missing required fields: ' . implode(', ', $missing),
            'missing_fields' => $missing,
            'received_fields' => array_keys($input),
            'debug' => true
        ]);
        return;
    }
    
    try {
        // Check if email exists
        $check = pg_query_params($conn, "SELECT id FROM users WHERE email = $1", [$email]);
        if (!$check) throw new Exception("Email check failed: " . pg_last_error($conn));
        if (pg_num_rows($check) > 0) {
            return errorResponse(409, 'Email already registered');
        }
        
        // Check if NIC exists
        $check = pg_query_params($conn, "SELECT id FROM users WHERE nic = $1", [$nic]);
        if (!$check) throw new Exception("NIC check failed: " . pg_last_error($conn));
        if (pg_num_rows($check) > 0) {
            return errorResponse(409, 'NIC already registered');
        }
        
        // Validate phone if provided
        if ($phone) {
            $phone = validatePhoneNumber($phone);
            if (!$phone) {
                return errorResponse(400, 'Invalid phone number');
            }
        }
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        error_log("PATIENT SIGNUP - Inserting: email=$email, nic=$nic, dob=$dob, phone=$phone");
        
        // Insert patient user - only use columns that actually exist
        $query = "INSERT INTO users (email, password_hash, nic, dob, role) 
                  VALUES ($1, $2, $3, $4, 'PATIENT')
                  RETURNING id";
        
        $result = pg_query_params($conn, $query, [$email, $password_hash, $nic, $dob]);
        
        if (!$result) {
            $msg = pg_last_error($conn);
            error_log("PATIENT SIGNUP INSERT FAILED: $msg");
            http_response_code(400);
            echo json_encode([
                'status' => 'ERROR',
                'message' => 'Failed to create account',
                'error_details' => $msg,
                'debug' => true
            ]);
            return;
        }
        
        $row = pg_fetch_assoc($result);
        $user_id = $row['id'];
        
        // Create auth token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $tokenQuery = "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES ($1, $2, $3)";
        $tokenResult = pg_query_params($conn, $tokenQuery, [$user_id, $token, $expires_at]);
        
        if (!$tokenResult) {
            throw new Exception("Failed to create auth token: " . pg_last_error($conn));
        }
        
        error_log("PATIENT SIGNUP SUCCESS: {$user_id} - $email");
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
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
    } catch (Exception $e) {
        error_log("PATIENT SIGNUP ERROR: " . $e->getMessage());
        return errorResponse(500, 'Signup failed: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: DOCTOR SIGNUP
// ============================================================================
function handleDoctorSignup($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
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
    $missing = [];
    if (!$name) $missing[] = 'name';
    if (!$email) $missing[] = 'email';
    if (!$password) $missing[] = 'password';
    if (!$nic) $missing[] = 'nic';
    if (!$license_number) $missing[] = 'license_number';
    
    if (!empty($missing)) {
        error_log("DOCTOR SIGNUP FAILED: Missing fields - " . implode(', ', $missing));
        return errorResponse(400, 'Missing required fields: ' . implode(', ', $missing));
    }
    
    try {
        // Check if email exists
        $check = pg_query_params($conn, "SELECT id FROM users WHERE email = $1", [$email]);
        if (!$check) throw new Exception("Email check failed: " . pg_last_error($conn));
        if (pg_num_rows($check) > 0) {
            return errorResponse(409, 'Email already registered');
        }
        
        // Check if NIC exists
        $check = pg_query_params($conn, "SELECT id FROM users WHERE nic = $1", [$nic]);
        if (!$check) throw new Exception("NIC check failed: " . pg_last_error($conn));
        if (pg_num_rows($check) > 0) {
            return errorResponse(409, 'NIC already registered');
        }
        
        // Validate phone if provided
        if ($phone) {
            $phone = validatePhoneNumber($phone);
            if (!$phone) {
                return errorResponse(400, 'Invalid phone number');
            }
        }
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        error_log("DOCTOR SIGNUP - Inserting: email=$email, nic=$nic, license=$license_number, specialty=$specialty");
        
        // Insert doctor user - only use columns that actually exist
        $query = "INSERT INTO users (email, password_hash, nic, license_number, specialty, role) 
                  VALUES ($1, $2, $3, $4, $5, 'DOCTOR')
                  RETURNING id";
        
        $result = pg_query_params($conn, $query, [$email, $password_hash, $nic, $license_number, $specialty]);
        
        if (!$result) {
            $msg = pg_last_error($conn);
            error_log("DOCTOR SIGNUP INSERT FAILED: $msg");
            return errorResponse(400, 'Failed to create account: ' . $msg);
        }
        
        $row = pg_fetch_assoc($result);
        $user_id = $row['id'];
        
        // Create auth token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $tokenQuery = "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES ($1, $2, $3)";
        $tokenResult = pg_query_params($conn, $tokenQuery, [$user_id, $token, $expires_at]);
        
        if (!$tokenResult) {
            throw new Exception("Failed to create auth token: " . pg_last_error($conn));
        }
        
        error_log("DOCTOR SIGNUP SUCCESS: {$user_id} - $email");
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
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
    } catch (Exception $e) {
        error_log("DOCTOR SIGNUP ERROR: " . $e->getMessage());
        return errorResponse(500, 'Signup failed: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: VERIFY USER (Legacy - MAC based)
// ============================================================================
function handleVerifyUser($method) {
    global $conn;
    
    if ($method !== 'GET' && $method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
    $mac_address = $_GET['mac'] ?? $_POST['mac'] ?? null;
    
    if (!$user_id || !$mac_address) {
        return errorResponse(400, 'Missing required parameters');
    }
    
    try {
        $query = "SELECT id FROM users WHERE user_id = $1 AND mac_address = $2 AND status = 'ACTIVE'";
        $result = pg_query_params($conn, $query, [$user_id, $mac_address]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        if (pg_num_rows($result) > 0) {
            $user = pg_fetch_assoc($result);
            http_response_code(200);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'User verified',
                'user_id' => $user_id
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['status' => 'ERROR', 'message' => 'User not found']);
        }
    } catch (Exception $e) {
        error_log("Verify Error: " . $e->getMessage());
        return errorResponse(500, 'Verification failed');
    }
}

// ============================================================================
// HANDLER: REGISTER USER (Legacy - MAC based)
// ============================================================================
function handleRegisterUser($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = $input['name'] ?? null;
    $mac_address = $input['mac_address'] ?? $input['mac'] ?? null;
    
    if (!$name || !$mac_address) {
        return errorResponse(400, 'Name and MAC address required');
    }
    
    try {
        $check = pg_query_params($conn, "SELECT id FROM users WHERE mac_address = $1", [$mac_address]);
        if (!$check) throw new Exception(pg_last_error($conn));
        if (pg_num_rows($check) > 0) {
            return errorResponse(409, 'Device already registered');
        }
        
        $user_id = 'USER_' . date('Ymd') . '_' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        
        $query = "INSERT INTO users (user_id, name, mac_address, role) 
                  VALUES ($1, $2, $3, 'PATIENT')
                  RETURNING id";
        
        $result = pg_query_params($conn, $query, [$user_id, $name, $mac_address]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'user_id' => $user_id,
            'name' => $name
        ]);
    } catch (Exception $e) {
        error_log("Register Error: " . $e->getMessage());
        return errorResponse(500, 'Registration failed');
    }
}

// ============================================================================
// HANDLER: GENERATE QR CODE (Legacy)
// ============================================================================
function handleGenerateQR($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    
    if (!$user_id) {
        return errorResponse(400, 'user_id required');
    }
    
    try {
        $qr_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        $userQuery = "SELECT id FROM users WHERE user_id = $1";
        $userResult = pg_query_params($conn, $userQuery, [$user_id]);
        
        if (!$userResult || pg_num_rows($userResult) === 0) {
            return errorResponse(404, 'User not found');
        }
        
        $userData = pg_fetch_assoc($userResult);
        
        $query = "INSERT INTO qr_tokens (user_id, token, expires_at, is_used) 
                  VALUES ($1, $2, $3, false)";
        
        $result = pg_query_params($conn, $query, [$userData['id'], $qr_token, $expires_at]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'qr_token' => $qr_token,
            'expires_at' => $expires_at
        ]);
    } catch (Exception $e) {
        error_log("QR Generate Error: " . $e->getMessage());
        return errorResponse(500, 'QR generation failed');
    }
}

// ============================================================================
// HANDLER: MAC LOOKUP (Legacy)
// ============================================================================
function handleMACLookup($method) {
    global $conn;
    
    if ($method !== 'GET') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $mac_address = $_GET['mac'] ?? null;
    
    if (!$mac_address) {
        return errorResponse(400, 'MAC address required');
    }
    
    try {
        $query = "SELECT id, user_id FROM users WHERE mac_address = $1";
        $result = pg_query_params($conn, $query, [$mac_address]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        if (pg_num_rows($result) > 0) {
            $user = pg_fetch_assoc($result);
            http_response_code(200);
            echo json_encode([
                'status' => 'FOUND',
                'user_id' => $user['user_id']
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'NOT_FOUND']);
        }
    } catch (Exception $e) {
        error_log("MAC Lookup Error: " . $e->getMessage());
        return errorResponse(500, 'Lookup failed');
    }
}

// ============================================================================
// HANDLER: GENERATE PAIRING TOKEN
// ============================================================================
function handleGeneratePairingToken($method) {
    global $conn;
    
    error_log("PAIRING TOKEN HANDLER CALLED: method=$method");
    
    if ($method !== 'POST') {
        error_log("PAIRING TOKEN: Wrong method, returning 405");
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? null;
    
    $user_id = null;
    $patient_id = null;
    
    try {
        // Mode 1: Authenticated (user already logged in)
        if ($token) {
            $query = "SELECT user_id FROM session_tokens 
                      WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
            $result = pg_query_params($conn, $query, [$token]);
            
            if (!$result) {
                throw new Exception(pg_last_error($conn));
            }
            
            if (pg_num_rows($result) === 0) {
                error_log("PAIRING TOKEN: Invalid or expired token");
                return errorResponse(401, 'Invalid or expired token');
            }
            
            $token_row = pg_fetch_assoc($result);
            $user_id = $token_row['user_id'];
            
            error_log("PAIRING TOKEN: user_id=$user_id from auth token");
            
            // Get patient_id
            $query = "SELECT id FROM patients WHERE user_id = $1";
            $result = pg_query_params($conn, $query, [$user_id]);
            
            if ($result && pg_num_rows($result) > 0) {
                $patient = pg_fetch_assoc($result);
                $patient_id = $patient['id'];
                error_log("PAIRING TOKEN: Found existing patient_id=$patient_id");
            } else {
                // Create patient record if it doesn't exist
                error_log("PAIRING TOKEN: No patient record found, creating one");
                $default_dob = '1990-01-01';
                // Generate unique NIC based on user_id to avoid UNIQUE constraint violation
                $unique_nic = 'PAT_' . $user_id . '_' . time();
                $query = "INSERT INTO patients (user_id, nic, name, date_of_birth, gender, blood_type) 
                          VALUES ($1, $2, $3, $4, $5, $6) RETURNING id";
                $result = pg_query_params($conn, $query, [$user_id, $unique_nic, 'Patient', $default_dob, 'OTHER', 'UNKNOWN']);
                
                if ($result) {
                    $patient = pg_fetch_assoc($result);
                    $patient_id = $patient['id'];
                    error_log("PAIRING TOKEN: Created new patient_id=$patient_id with nic=$unique_nic");
                } else {
                    throw new Exception("Failed to create patient record: " . pg_last_error($conn));
                }
            }
        } 
        // Mode 2: Unauthenticated (Arduino/new device)
        else {
            error_log("PAIRING TOKEN: Unauthenticated mode (Arduino device)");
            
            // Create temporary user record for device
            $unique_email = "device_" . time() . "_" . rand(1000, 9999) . "@arduino.local";
            
            $query = "INSERT INTO users (email, password_hash, role) 
                      VALUES ($1, $2, $3) RETURNING id";
            $result = pg_query_params($conn, $query, [$unique_email, password_hash('ARDUINO_DEVICE', PASSWORD_BCRYPT), 'PATIENT']);
            
            if (!$result) {
                throw new Exception("Failed to create user: " . pg_last_error($conn));
            }
            
            $user = pg_fetch_assoc($result);
            $user_id = $user['id'];
            error_log("PAIRING TOKEN: Created temporary user_id=$user_id");
            
            // Create patient record
            $default_dob = '1990-01-01';
            // Generate unique NIC for Arduino device
            $unique_nic = 'DEV_' . $user_id . '_' . time();
            $query = "INSERT INTO patients (user_id, nic, name, date_of_birth, gender, blood_type) 
                      VALUES ($1, $2, $3, $4, $5, $6) RETURNING id";
            $result = pg_query_params($conn, $query, [$user_id, $unique_nic, 'Smart Medi Box', $default_dob, 'OTHER', 'UNKNOWN']);
            
            if (!$result) {
                throw new Exception("Failed to create patient: " . pg_last_error($conn));
            }
            
            $patient = pg_fetch_assoc($result);
            $patient_id = $patient['id'];
            error_log("PAIRING TOKEN: Created patient_id=$patient_id for Arduino");
        }
        
        // Generate pairing token (same for both modes)
        $pairing_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $query = "INSERT INTO pairing_tokens (patient_id, token, expires_at, is_used) 
                  VALUES ($1, $2, $3, false)
                  RETURNING token";
        
        $result = pg_query_params($conn, $query, [$patient_id, $pairing_token, $expires_at]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        error_log("PAIRING TOKEN GENERATED: patient_id=$patient_id, pairing_token=$pairing_token");
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'pairing_token' => $pairing_token,
            'expires_in' => 3600,
            'qr_data' => $pairing_token  // QR code contains just the token
        ]);
    } catch (Exception $e) {
        error_log("Pairing Token Generation Error: " . $e->getMessage());
        return errorResponse(500, 'Failed to generate pairing token: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: COMPLETE PAIRING
// ============================================================================
function handleCompletePairing($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $pairing_token = $input['pairing_token'] ?? null;
    $mac_address = $input['mac_address'] ?? null;
    $device_name = $input['device_name'] ?? null;
    
    if (!$pairing_token || !$mac_address) {
        return errorResponse(400, 'Pairing token and MAC address required');
    }
    
    try {
        // Validate pairing token
        $query = "SELECT patient_id FROM pairing_tokens 
                  WHERE token = $1 AND is_used = false AND expires_at > CURRENT_TIMESTAMP";
        $result = pg_query_params($conn, $query, [$pairing_token]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        if (pg_num_rows($result) === 0) {
            error_log("COMPLETE PAIRING: Invalid or expired pairing token");
            return errorResponse(401, 'Invalid or expired pairing token');
        }
        
        $token_row = pg_fetch_assoc($result);
        $patient_id = $token_row['patient_id'];
        
        error_log("COMPLETE PAIRING: patient_id=$patient_id from pairing token");
        
        // Get user_id from patient_id
        $query = "SELECT user_id FROM patients WHERE id = $1";
        $result = pg_query_params($conn, $query, [$patient_id]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        if (pg_num_rows($result) === 0) {
            error_log("COMPLETE PAIRING: Patient not found for patient_id=$patient_id");
            return errorResponse(404, 'Patient not found');
        }
        
        $patient = pg_fetch_assoc($result);
        $user_id = $patient['user_id'];
        
        error_log("COMPLETE PAIRING: user_id=$user_id");
        
        // Ensure user does not already have a device (one account -> one device)
        $userCheck = pg_query_params($conn, "SELECT id FROM device_user_map WHERE user_id = $1", [$user_id]);
        if (!$userCheck) {
            throw new Exception(pg_last_error($conn));
        }

        if (pg_num_rows($userCheck) > 0) {
            error_log("COMPLETE PAIRING: User already has a paired device: user_id=$user_id");
            return errorResponse(409, 'User already has a paired device');
        }

        // Find existing device by MAC
        $deviceResult = pg_query_params($conn, "SELECT id, device_id FROM devices WHERE mac_address = $1", [$mac_address]);
        if (!$deviceResult) {
            throw new Exception(pg_last_error($conn));
        }

        if (pg_num_rows($deviceResult) > 0) {
            // Device exists; map this user to the existing device
            $deviceRow = pg_fetch_assoc($deviceResult);
            $device_db_id = $deviceRow['id'];
            $device_id = $deviceRow['device_id'];
        } else {
            // Create new device entry
            $device_id = "DEVICE-" . bin2hex(random_bytes(8));
            $insert = pg_query_params($conn, "INSERT INTO devices (device_id, mac_address, device_name, device_type) VALUES ($1,$2,$3,$4) RETURNING id", [$device_id, $mac_address, $device_name ?? 'Smart Box', 'SMART_BOX']);
            if (!$insert) {
                throw new Exception(pg_last_error($conn));
            }
            $insertRow = pg_fetch_assoc($insert);
            $device_db_id = $insertRow['id'];
        }

        // Mark pairing token as used and record device mac
        $query = "UPDATE pairing_tokens SET is_used = true, used_at = CURRENT_TIMESTAMP WHERE token = $1";
        pg_query_params($conn, $query, [$pairing_token]);

        // Map user to device (enforces one device per user via unique constraint on user_id)
        $mapResult = pg_query_params($conn, "INSERT INTO device_user_map (device_id, user_id) VALUES ($1, $2)", [$device_db_id, $user_id]);
        if (!$mapResult) {
            throw new Exception(pg_last_error($conn));
        }

        error_log("DEVICE PAIRED: patient_id=$patient_id, user_id=$user_id, device_id=$device_id, mac=$mac_address");

        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Device paired successfully',
            'device_id' => $device_id,
            'mac_address' => $mac_address
        ]);
    } catch (Exception $e) {
        error_log("Device Pairing Error: " . $e->getMessage());
        return errorResponse(500, 'Device pairing failed: ' . $e->getMessage());
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function errorResponse($code, $message) {
    http_response_code($code);
    echo json_encode([
        'status' => 'ERROR',
        'message' => $message,
        'debug' => true
    ]);
}

function validatePhoneNumber($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    if (strpos($phone, '0') === 0) {
        $phone = '94' . substr($phone, 1);
    } elseif (strpos($phone, '+94') === 0) {
        $phone = substr($phone, 1);
    } elseif (strpos($phone, '94') !== 0) {
        $phone = '94' . $phone;
    }
    
    if (!preg_match('/^94\d{9,11}$/', $phone)) {
        return false;
    }
    
    return '+' . $phone;
}

function logAuthentication($user_id, $mac_address, $status) {
    global $conn;
    
    try {
        $query = "INSERT INTO auth_logs (user_id, mac_address, status) 
                 VALUES ($1, $2, $3)";
        pg_query_params($conn, $query, [$user_id, $mac_address, $status]);
    } catch (Exception $e) {
        error_log("Log Error: " . $e->getMessage());
    }
}
?>
