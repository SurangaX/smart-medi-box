<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Device Management API (PostgreSQL Compatible)
 * ============================================================================
 */

require_once 'db_config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Get action from $_GET (set by index.php router) or parse from PATH_INFO
$action = $_GET['action'] ?? '';

// If action not set, try parsing from PATH_INFO
if (!$action) {
    $request_uri = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    // PATH_INFO format: /api/device/action, so action is at index 2
    $action = $request_uri[2] ?? '';
}

switch ($action) {
    case 'register':
        handleRegisterDevice($method);
        break;
    case 'list':
        handleListDevices($method);
        break;
    case 'sync':
        handleSyncDevice($method);
        break;
    case 'update-status':
        handleUpdateDeviceStatus($method);
        break;
    case 'check-commands':
        handleCheckCommands($method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found']);
        break;
}

function handleRegisterDevice($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? null;
    $device_name = $input['device_name'] ?? $_POST['device_name'] ?? 'Arduino Leonardo';
    $device_type = $input['device_type'] ?? $_POST['device_type'] ?? 'ARDUINO_LEONARDO';
    $mac_address = $input['mac_address'] ?? $_POST['mac_address'] ?? null;
    $firmware_version = $input['firmware_version'] ?? $_POST['firmware_version'] ?? '1.0.0';
    
    if (!$user_id || !$mac_address) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters']);
        return;
    }
    
    try {
        $user_query = "SELECT id FROM users WHERE user_id = $1";
        $user_result = pg_query_params($conn, $user_query, array($user_id));
        
        if (pg_num_rows($user_result) === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'User not found']);
            return;
        }
        
        $user_data = pg_fetch_assoc($user_result);
        $db_user_id = $user_data['id'];
        
        $checkQuery = "SELECT id FROM device_registry WHERE user_id = $1 AND mac_address = $2";
        $checkResult = pg_query_params($conn, $checkQuery, array($db_user_id, $mac_address));
        
        if (pg_num_rows($checkResult) > 0) {
            http_response_code(409);
            echo json_encode(['status' => 'ERROR', 'message' => 'Device already registered']);
            return;
        }
        
        $device_id = 'DEVICE_' . bin2hex(random_bytes(8));
        
        $query = "INSERT INTO device_registry 
                  (id, user_id, device_name, device_type, mac_address, firmware_version, status)
                  VALUES ($1, $2, $3, $4, $5, $6, 'ACTIVE')";
        
        $result = pg_query_params($conn, $query, 
            array($device_id, $db_user_id, $device_name, $device_type, $mac_address, $firmware_version));
        
        if ($result) {
            http_response_code(201);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'Device registered',
                'device_id' => $device_id,
                'device_name' => $device_name
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to register device']);
        }
    } catch (Exception $e) {
        error_log("Register Device Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

function handleListDevices($method) {
    global $conn;
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'user_id required']);
        return;
    }
    
    try {
        $query = "SELECT id, device_name, device_type, mac_address, firmware_version, 
                         status, last_sync, created_at
                  FROM device_registry
                  WHERE user_id = (SELECT id FROM users WHERE user_id = $1) 
                  AND status != 'DELETED'
                  ORDER BY created_at DESC";
        
        $result = pg_query_params($conn, $query, array($user_id));
        
        $devices = [];
        while ($row = pg_fetch_assoc($result)) {
            $devices[] = [
                'device_id' => $row['id'],
                'name' => $row['device_name'],
                'type' => $row['device_type'],
                'mac_address' => $row['mac_address'],
                'firmware_version' => $row['firmware_version'],
                'status' => $row['status'],
                'last_sync' => $row['last_sync'],
                'created_at' => $row['created_at']
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'count' => count($devices),
            'devices' => $devices
        ]);
    } catch (Exception $e) {
        error_log("List Devices Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

function handleSyncDevice($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $device_id = $input['device_id'] ?? $_POST['device_id'] ?? null;
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? null;
    $firmware_version = $input['firmware_version'] ?? $_POST['firmware_version'] ?? null;
    
    if (!$device_id || !$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters']);
        return;
    }
    
    try {
        $user_query = "SELECT id FROM users WHERE user_id = $1";
        $user_result = pg_query_params($conn, $user_query, array($user_id));
        
        if (pg_num_rows($user_result) === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'User not found']);
            return;
        }
        
        $user_data = pg_fetch_assoc($user_result);
        $db_user_id = $user_data['id'];
        
        if ($firmware_version) {
            $query = "UPDATE device_registry SET last_sync = NOW(), firmware_version = $1
                      WHERE id = $2 AND user_id = $3";
            pg_query_params($conn, $query, array($firmware_version, $device_id, $db_user_id));
        } else {
            $query = "UPDATE device_registry SET last_sync = NOW() WHERE id = $1 AND user_id = $2";
            pg_query_params($conn, $query, array($device_id, $db_user_id));
        }
        
        $commandQuery = "SELECT id, command FROM arduino_commands 
                        WHERE user_id = $1 AND status = 'PENDING'
                        ORDER BY created_at ASC LIMIT 10";
        
        $cmdResult = pg_query_params($conn, $commandQuery, array($db_user_id));
        
        $commands = [];
        while ($row = pg_fetch_assoc($cmdResult)) {
            $commands[] = [
                'id' => $row['id'],
                'command' => $row['command']
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Device synced',
            'pending_commands' => $commands
        ]);
    } catch (Exception $e) {
        error_log("Sync Device Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

function handleUpdateDeviceStatus($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $device_id = $input['device_id'] ?? $_POST['device_id'] ?? null;
    $status = $input['status'] ?? $_POST['status'] ?? null;
    
    if (!$device_id || !$status) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters']);
        return;
    }
    
    if (!in_array($status, ['ACTIVE', 'OFFLINE', 'ERROR'])) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Invalid status']);
        return;
    }
    
    try {
        $query = "UPDATE device_registry SET status = $1, updated_at = NOW() WHERE id = $2";
        
        $result = pg_query_params($conn, $query, array($status, $device_id));
        
        if ($result) {
            http_response_code(200);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'Device status updated',
                'device_status' => $status
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to update device']);
        }
    } catch (Exception $e) {
        error_log("Update Device Status Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

function handleCheckCommands($method) {
    global $conn;
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'user_id required']);
        return;
    }
    
    try {
        $user_query = "SELECT id FROM users WHERE user_id = $1";
        $user_result = pg_query_params($conn, $user_query, array($user_id));
        
        if (pg_num_rows($user_result) === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'User not found']);
            return;
        }
        
        $user_data = pg_fetch_assoc($user_result);
        $db_user_id = $user_data['id'];
        
        $query = "SELECT id, command FROM arduino_commands 
                  WHERE user_id = $1 AND status = 'PENDING'
                  ORDER BY created_at ASC";
        
        $result = pg_query_params($conn, $query, array($db_user_id));
        
        $commands = [];
        while ($row = pg_fetch_assoc($result)) {
            $commands[] = [
                'id' => $row['id'],
                'command' => $row['command']
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'pending_count' => count($commands),
            'commands' => $commands
        ]);
    } catch (Exception $e) {
        error_log("Check Commands Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

?>
