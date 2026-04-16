<?php
/**
 * ============================================================================
 * SMART MEDI BOX - QR Authentication & Device Pairing API
 * ============================================================================
 * 
 * Endpoints:
 * - POST /api/qr/authenticate - QR code authentication from Arduino
 * - POST /api/qr/pair-new-device - Detect MAC and ask for user info
 * - POST /api/qr/register-mac - Register new user with MAC/device
 * - GET  /api/qr/verify-token - Verify QR token validity
 * - GET  /api/device/schedules - Get schedules for Arduino
 * 
 * ============================================================================
 */

require_once 'db_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_SERVER['PATH_INFO'] ?? '', '/');
$segments = explode('/', $path);

// Remove 'api' segment
if (isset($segments[0]) && $segments[0] === 'api') {
    array_shift($segments);
}

$action = $segments[0] ?? '';
$subaction = $segments[1] ?? '';

error_log("QR_AUTH REQUEST: action=$action, subaction=$subaction");

switch ($action) {
    case 'qr':
        if ($subaction === 'authenticate') {
            handleQRAuthenticate($method);
        } elseif ($subaction === 'pair-new-device') {
            handlePairNewDevice($method);
        } elseif ($subaction === 'register-mac') {
            handleRegisterMAC($method);
        } elseif ($subaction === 'verify-token') {
            handleVerifyToken($method);
        }
        break;
    
    case 'device':
        if ($subaction === 'schedules') {
            handleGetDeviceSchedules($method);
        } elseif ($subaction === 'commands') {
            handleGetDeviceCommands($method);
        } elseif ($subaction === 'door-opened') {
            handleDoorOpened($method);
        } elseif ($subaction === 'alarm-status') {
            handleAlarmStatus($method);
        }
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found']);
}

// ============================================================================
// HANDLER: QR Authentication from Arduino Display
// ============================================================================
function handleQRAuthenticate($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $qr_token = $input['qr_token'] ?? null;
    $device_mac = $input['device_mac'] ?? null;
    $device_type = $input['device_type'] ?? 'ARDUINO_LEONARDO';
    
    error_log("QR_AUTHENTICATE: token=$qr_token, mac=$device_mac");
    
    if (!$qr_token || !$device_mac) {
        return errorResponse(400, 'QR token and device MAC required');
    }
    
    try {
        // Check if QR token exists and is valid
        $query = "SELECT user_id, expires_at FROM auth_tokens 
                  WHERE token = $1 AND expires_at > NOW()";
        $result = pg_query_params($conn, $query, [$qr_token]);
        
        if (!$result || pg_num_rows($result) === 0) {
            error_log("QR_AUTH FAILED: Invalid or expired token");
            return errorResponse(401, 'Invalid or expired QR token');
        }
        
        $auth = pg_fetch_assoc($result);
        $user_id = $auth['user_id'];
        
        // Check if user exists
        $userQuery = "SELECT id, email, role FROM users WHERE id = $1 AND role IN ('PATIENT', 'DOCTOR')";
        $userResult = pg_query_params($conn, $userQuery, [$user_id]);
        
        if (!$userResult || pg_num_rows($userResult) === 0) {
            error_log("QR_AUTH FAILED: User not found");
            return errorResponse(404, 'User not found');
        }
        
        $user = pg_fetch_assoc($userResult);
        
        // Register device if not exists
        $deviceQuery = "SELECT id FROM device_registry WHERE mac_address = $1 AND user_id = $2";
        $deviceResult = pg_query_params($conn, $deviceQuery, [$device_mac, $user_id]);
        
        if (!$deviceResult || pg_num_rows($deviceResult) === 0) {
            $deviceId = 'DEVICE_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
            $registerQuery = "INSERT INTO device_registry (device_id, user_id, mac_address, device_type, status) 
                            VALUES ($1, $2, $3, $4, 'ACTIVE')
                            RETURNING id";
            $registerResult = pg_query_params($conn, $registerQuery, [$deviceId, $user_id, $device_mac, $device_type]);
            
            if (!$registerResult) {
                error_log("QR_AUTH DEVICE REGISTER FAILED: " . pg_last_error($conn));
            } else {
                error_log("QR_AUTH NEW DEVICE REGISTERED: $deviceId");
            }
        } else {
            error_log("QR_AUTH DEVICE ALREADY REGISTERED: $device_mac");
        }
        
        // Create device session token
        $sessionToken = bin2hex(random_bytes(32));
        $sessionExpiry = date('Y-m-d H:i:s', strtotime('+8 hours'));
        
        $sessionQuery = "INSERT INTO device_sessions (user_id, device_mac, session_token, expires_at) 
                       VALUES ($1, $2, $3, $4)
                       ON CONFLICT (device_mac) DO UPDATE SET session_token = $3, expires_at = $4
                       RETURNING session_token";
        $sessionResult = pg_query_params($conn, $sessionQuery, [$user_id, $device_mac, $sessionToken, $sessionExpiry]);
        
        if (!$sessionResult) {
            error_log("Session creation warning: " . pg_last_error($conn));
        }
        
        error_log("QR_AUTH SUCCESS: user_id=$user_id, device=$device_mac");
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'QR authentication successful',
            'user_id' => $user_id,
            'email' => $user['email'],
            'role' => $user['role'],
            'device_mac' => $device_mac,
            'session_token' => $sessionToken,
            'authenticated' => true
        ]);
        
    } catch (Exception $e) {
        error_log("QR_AUTH ERROR: " . $e->getMessage());
        return errorResponse(500, 'Authentication failed: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Pair New Device (Detect MAC and Ask for User Info)
// ============================================================================
function handlePairNewDevice($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $device_mac = $input['device_mac'] ?? null;
    $device_type = $input['device_type'] ?? 'ARDUINO_LEONARDO';
    
    error_log("PAIR_NEW_DEVICE: mac=$device_mac");
    
    if (!$device_mac) {
        return errorResponse(400, 'Device MAC address required');
    }
    
    try {
        // Check if MAC is already registered
        $checkQuery = "SELECT user_id FROM device_registry WHERE mac_address = $1";
        $checkResult = pg_query_params($conn, $checkQuery, [$device_mac]);
        
        if ($checkResult && pg_num_rows($checkResult) > 0) {
            error_log("PAIR_NEW_DEVICE FAILED: MAC already registered");
            return errorResponse(409, 'Device already registered. Use QR authentication.');
        }
        
        // Generate pairing token for new device
        $pairingToken = bin2hex(random_bytes(32));
        $pairingExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Store pairing request
        $pairingQuery = "INSERT INTO device_pairings (device_mac, pairing_token, device_type, expires_at) 
                        VALUES ($1, $2, $3, $4)
                        RETURNING pairing_token";
        $pairingResult = pg_query_params($conn, $pairingQuery, [$device_mac, $pairingToken, $device_type, $pairingExpiry]);
        
        if (!$pairingResult) {
            error_log("PAIR_NEW_DEVICE FAILED: " . pg_last_error($conn));
            return errorResponse(500, 'Pairing request failed');
        }
        
        error_log("PAIR_NEW_DEVICE SUCCESS: pairing_token issued for $device_mac");
        
        http_response_code(200);
        echo json_encode([
            'status' => 'PAIRING_REQUIRED',
            'message' => 'New device detected. Please provide user information.',
            'pairing_token' => $pairingToken,
            'device_mac' => $device_mac,
            'required_fields' => ['name', 'email', 'password', 'nic', 'dob', 'phone']
        ]);
        
    } catch (Exception $e) {
        error_log("PAIR_NEW_DEVICE ERROR: " . $e->getMessage());
        return errorResponse(500, 'Pairing failed: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Register MAC Address with New User
// ============================================================================
function handleRegisterMAC($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $pairing_token = $input['pairing_token'] ?? null;
    $name = $input['name'] ?? null;
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;
    $nic = $input['nic'] ?? null;
    $dob = $input['dob'] ?? null;
    $phone = $input['phone'] ?? null;
    $device_mac = $input['device_mac'] ?? null;
    
    error_log("REGISTER_MAC: email=$email, mac=$device_mac");
    
    // Validate required fields
    $missing = [];
    if (!$pairing_token) $missing[] = 'pairing_token';
    if (!$name) $missing[] = 'name';
    if (!$email) $missing[] = 'email';
    if (!$password) $missing[] = 'password';
    if (!$nic) $missing[] = 'nic';
    if (!$dob) $missing[] = 'dob';
    if (!$phone) $missing[] = 'phone';
    if (!$device_mac) $missing[] = 'device_mac';
    
    if (!empty($missing)) {
        error_log("REGISTER_MAC FAILED: Missing fields - " . implode(', ', $missing));
        return errorResponse(400, 'Missing required fields: ' . implode(', ', $missing));
    }
    
    try {
        // Verify pairing token
        $pairingQuery = "SELECT device_mac, device_type FROM device_pairings 
                        WHERE pairing_token = $1 AND expires_at > NOW()";
        $pairingResult = pg_query_params($conn, $pairingQuery, [$pairing_token]);
        
        if (!$pairingResult || pg_num_rows($pairingResult) === 0) {
            error_log("REGISTER_MAC FAILED: Invalid or expired pairing token");
            return errorResponse(401, 'Invalid or expired pairing token');
        }
        
        $pairing = pg_fetch_assoc($pairingResult);
        if ($pairing['device_mac'] !== $device_mac) {
            error_log("REGISTER_MAC FAILED: MAC mismatch");
            return errorResponse(400, 'Device MAC mismatch');
        }
        
        // Check if email/NIC already registered
        $checkEmail = pg_query_params($conn, "SELECT id FROM users WHERE email = $1", [$email]);
        if ($checkEmail && pg_num_rows($checkEmail) > 0) {
            return errorResponse(409, 'Email already registered');
        }
        
        $checkNIC = pg_query_params($conn, "SELECT id FROM users WHERE nic = $1", [$nic]);
        if ($checkNIC && pg_num_rows($checkNIC) > 0) {
            return errorResponse(409, 'NIC already registered');
        }
        
        // Create new patient user
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $userQuery = "INSERT INTO users (email, password_hash, nic, dob, role) 
                     VALUES ($1, $2, $3, $4, 'PATIENT')
                     RETURNING id";
        $userResult = pg_query_params($conn, $userQuery, [$email, $password_hash, $nic, $dob]);
        
        if (!$userResult) {
            error_log("REGISTER_MAC USER INSERT FAILED: " . pg_last_error($conn));
            return errorResponse(500, 'User registration failed');
        }
        
        $user = pg_fetch_assoc($userResult);
        $user_id = $user['id'];
        
        // Register device with new user
        $deviceId = 'DEVICE_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $deviceQuery = "INSERT INTO device_registry (device_id, user_id, mac_address, device_type, status) 
                      VALUES ($1, $2, $3, $4, 'ACTIVE')";
        $deviceResult = pg_query_params($conn, $deviceQuery, [$deviceId, $user_id, $device_mac, $pairing['device_type']]);
        
        if (!$deviceResult) {
            error_log("REGISTER_MAC DEVICE REGISTER FAILED: " . pg_last_error($conn));
            return errorResponse(500, 'Device registration failed');
        }
        
        // Create auth token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $tokenQuery = "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES ($1, $2, $3)";
        pg_query_params($conn, $tokenQuery, [$user_id, $token, $expires_at]);
        
        // Create device session token
        $sessionToken = bin2hex(random_bytes(32));
        $sessionExpiry = date('Y-m-d H:i:s', strtotime('+8 hours'));
        
        $sessionQuery = "INSERT INTO device_sessions (user_id, device_mac, session_token, expires_at) 
                       VALUES ($1, $2, $3, $4)";
        pg_query_params($conn, $sessionQuery, [$user_id, $device_mac, $sessionToken, $sessionExpiry]);
        
        // Delete pairing request
        pg_query_params($conn, "DELETE FROM device_pairings WHERE pairing_token = $1", [$pairing_token]);
        
        error_log("REGISTER_MAC SUCCESS: user_id=$user_id, device=$device_mac");
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Device and user registered successfully',
            'user_id' => $user_id,
            'device_id' => $deviceId,
            'device_mac' => $device_mac,
            'email' => $email,
            'token' => $token,
            'session_token' => $sessionToken
        ]);
        
    } catch (Exception $e) {
        error_log("REGISTER_MAC ERROR: " . $e->getMessage());
        return errorResponse(500, 'Registration failed: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Verify QR Token
// ============================================================================
function handleVerifyToken($method) {
    global $conn;
    
    if ($method !== 'GET') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $qr_token = $_GET['token'] ?? null;
    
    if (!$qr_token) {
        return errorResponse(400, 'Token required');
    }
    
    try {
        $query = "SELECT user_id, expires_at FROM auth_tokens 
                  WHERE token = $1 AND expires_at > NOW()";
        $result = pg_query_params($conn, $query, [$qr_token]);
        
        if (!$result || pg_num_rows($result) === 0) {
            return errorResponse(401, 'Invalid or expired token');
        }
        
        $auth = pg_fetch_assoc($result);
        
        http_response_code(200);
        echo json_encode([
            'status' => 'VALID',
            'user_id' => $auth['user_id'],
            'expires_at' => $auth['expires_at']
        ]);
        
    } catch (Exception $e) {
        return errorResponse(500, 'Verification failed: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Get Schedules for Arduino (Medicine/Food/Blood Check times)
// ============================================================================
function handleGetDeviceSchedules($method) {
    global $conn;
    
    if ($method !== 'GET') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $user_id = $_GET['user_id'] ?? null;
    $session_token = $_GET['session_token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    
    if (!$user_id) {
        return errorResponse(400, 'user_id required');
    }
    
    try {
        // Get active schedules for user
        $query = "SELECT id, type, hour, minute, description, is_completed 
                  FROM schedules 
                  WHERE user_id = $1 AND status = 'ACTIVE' AND is_completed = false
                  ORDER BY hour, minute";
        $result = pg_query_params($conn, $query, [$user_id]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        $schedules = [];
        while ($row = pg_fetch_assoc($result)) {
            $schedules[] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'time' => sprintf('%02d:%02d', $row['hour'], $row['minute']),
                'hour' => intval($row['hour']),
                'minute' => intval($row['minute']),
                'description' => $row['description'],
                'is_completed' => $row['is_completed']
            ];
        }
        
        error_log("DEVICE_SCHEDULES: user_id=$user_id, count=" . count($schedules));
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'user_id' => intval($user_id),
            'schedules' => $schedules,
            'count' => count($schedules)
        ]);
        
    } catch (Exception $e) {
        error_log("DEVICE_SCHEDULES ERROR: " . $e->getMessage());
        return errorResponse(500, 'Failed to fetch schedules: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Get Device Commands Queue
// ============================================================================
function handleGetDeviceCommands($method) {
    global $conn;
    
    if ($method !== 'GET') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        return errorResponse(400, 'user_id required');
    }
    
    try {
        $query = "SELECT id, command, status FROM arduino_commands 
                  WHERE user_id = $1 AND status IN ('PENDING', 'SENT')
                  ORDER BY created_at ASC
                  LIMIT 10";
        $result = pg_query_params($conn, $query, [$user_id]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        $commands = [];
        while ($row = pg_fetch_assoc($result)) {
            $commands[] = [
                'id' => intval($row['id']),
                'command' => $row['command'],
                'status' => $row['status']
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'commands' => $commands,
            'count' => count($commands)
        ]);
        
    } catch (Exception $e) {
        error_log("DEVICE_COMMANDS ERROR: " . $e->getMessage());
        return errorResponse(500, 'Failed to fetch commands: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Door Opened Event (Alarm Control)
// ============================================================================
function handleDoorOpened($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    $schedule_id = $input['schedule_id'] ?? null;
    
    error_log("DOOR_OPENED: user_id=$user_id, schedule_id=$schedule_id");
    
    if (!$user_id) {
        return errorResponse(400, 'user_id required');
    }
    
    try {
        // Log door opening and stop alarm
        $updateQuery = "UPDATE alarm_logs 
                       SET door_opened = true, dismissed_at = NOW(), status = 'DISMISSED'
                       WHERE user_id = $1 AND status = 'TRIGGERED' AND dismissed_at IS NULL
                       ORDER BY triggered_at DESC LIMIT 1";
        pg_query_params($conn, $updateQuery, [$user_id]);
        
        // Mark schedule as completed if provided
        if ($schedule_id) {
            $scheduleQuery = "UPDATE schedules 
                             SET is_completed = true, completed_at = NOW()
                             WHERE id = $1 AND user_id = $2";
            pg_query_params($conn, $scheduleQuery, [$schedule_id, $user_id]);
        }
        
        error_log("DOOR_OPENED: Alarm dismissed and schedule marked complete");
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Door opened. Alarm dismissed.',
            'alarm_stopped' => true
        ]);
        
    } catch (Exception $e) {
        error_log("DOOR_OPENED ERROR: " . $e->getMessage());
        return errorResponse(500, 'Failed to process door opening: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Alarm Status Update
// ============================================================================
function handleAlarmStatus($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    $schedule_id = $input['schedule_id'] ?? null;
    $status = $input['status'] ?? null; // TRIGGERED, ACKNOWLEDGED, DISMISSED
    
    error_log("ALARM_STATUS: user_id=$user_id, status=$status");
    
    if (!$user_id || !$status) {
        return errorResponse(400, 'user_id and status required');
    }
    
    try {
        $insertQuery = "INSERT INTO alarm_logs (user_id, schedule_id, triggered_at, status) 
                       VALUES ($1, $2, NOW(), $3)
                       RETURNING id";
        $result = pg_query_params($conn, $insertQuery, [$user_id, $schedule_id, $status]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        $alarm = pg_fetch_assoc($result);
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'alarm_id' => intval($alarm['id']),
            'message' => "Alarm status: $status"
        ]);
        
    } catch (Exception $e) {
        error_log("ALARM_STATUS ERROR: " . $e->getMessage());
        return errorResponse(500, 'Failed to update alarm status: ' . $e->getMessage());
    }
}

// ============================================================================
// Helper Functions
// ============================================================================

function errorResponse($code, $message) {
    http_response_code($code);
    echo json_encode([
        'status' => 'ERROR',
        'message' => $message
    ]);
}

?>
