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
    case 'heartbeat':
        handleDeviceHeartbeat($method);
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
    
    if (!$mac_address) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'mac_address required']);
        return;
    }

    try {
        // First check if device already exists by MAC (Case-Insensitive)
        $checkQuery = "SELECT dr.id, dr.user_id, u.name as user_name 
                      FROM device_registry dr
                      LEFT JOIN users u ON dr.user_id = u.id
                      WHERE UPPER(dr.mac_address) = UPPER($1)";
        $checkResult = pg_query_params($conn, $checkQuery, array($mac_address));
        
        if (pg_num_rows($checkResult) > 0) {
            $device_data = pg_fetch_assoc($checkResult);
            
            // If already registered and paired to a user, return success with user name
            if ($device_data['user_id']) {
                http_response_code(200);
                echo json_encode([
                    'status' => 'SUCCESS', 
                    'message' => 'Device already paired',
                    'device_id' => $device_data['id'],
                    'user_name' => $device_data['user_name'] ?? 'Paired User'
                ]);
                return;
            }
            
            // If registered but not paired, and no user_id provided to pair it with
            if (!$user_id) {
                http_response_code(200);
                echo json_encode([
                    'status' => 'PENDING',
                    'message' => 'Device registered but not paired to a user',
                    'device_id' => $device_data['id']
                ]);
                return;
            }
        }
        
        // If we reach here, we need to either Register or Pair the device
        if (!$user_id) {
            // Can't create NEW registration without a user_id in this logic
            // But we can return a 200 with "UNPAIRED" status
            http_response_code(200);
            echo json_encode([
                'status' => 'UNPAIRED',
                'message' => 'Device not found or not paired'
            ]);
            return;
        }

        $user_query = "SELECT id, name FROM users WHERE user_id = $1";
        $user_result = pg_query_params($conn, $user_query, array($user_id));
        
        if (pg_num_rows($user_result) === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'User not found']);
            return;
        }
        
        $user_data = pg_fetch_assoc($user_result);
        $db_user_id = $user_data['id'];
        $user_real_name = $user_data['name'] ?? 'User';
        
        // Final registration/update logic
        if (pg_num_rows($checkResult) > 0) {
            // Update existing record with new user_id
            $device_data = pg_fetch_assoc($checkResult);
            $query = "UPDATE device_registry SET user_id = $1, status = 'ACTIVE' WHERE id = $2";
            pg_query_params($conn, $query, array($db_user_id, $device_data['id']));
            $device_id = $device_data['id'];
        } else {
            // Insert new record
            $device_id = 'DEVICE_' . bin2hex(random_bytes(8));
            $query = "INSERT INTO device_registry 
                      (id, user_id, device_name, device_type, mac_address, firmware_version, status)
                      VALUES ($1, $2, $3, $4, $5, $6, 'ACTIVE')";
            pg_query_params($conn, $query, 
                array($device_id, $db_user_id, $device_name, $device_type, $mac_address, $firmware_version));
        }
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Device registered and paired',
            'device_id' => $device_id,
            'user_name' => $user_real_name
        ]);

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
    
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true) ?? [];
    
    // Debug logging
    error_log("Update Device Status - Raw Input: " . $raw_input);
    
    $device_id = $input['device_id'] ?? $_POST['device_id'] ?? null;
    $mac_address = $input['mac_address'] ?? $_POST['mac_address'] ?? null;
    $status = $input['status'] ?? $_POST['status'] ?? null;
    
    // Sensor data
    $temp = $input['temperature'] ?? null;
    $humidity = $input['humidity'] ?? null;
    $door_open = $input['door_open'] ?? null;
    $alarm_active = $input['alarm_active'] ?? null;
    
    if (!$device_id && !$mac_address) {
        error_log("Update Device Status - Error: Missing device_id and mac_address");
        http_response_code(400);
        echo json_encode([
            'status' => 'ERROR', 
            'message' => 'device_id or mac_address required',
            'debug_received' => array_keys($input)
        ]);
        return;
    }
    
    try {
        // If mac_address is provided, find the user_id and device_id
        $db_user_id = null;
        $db_device_id = $device_id;
        
        if ($mac_address) {
            // Use case-insensitive matching for MAC address
            $macQuery = "SELECT id, user_id FROM device_registry WHERE UPPER(mac_address) = UPPER($1)";
            $macResult = pg_query_params($conn, $macQuery, array($mac_address));
            if (pg_num_rows($macResult) > 0) {
                $macData = pg_fetch_assoc($macResult);
                $db_user_id = $macData['user_id'];
                $db_device_id = $macData['id'];
            } else {
                error_log("Update Device Status - Warning: MAC address $mac_address not found in registry");
            }
        }
        
        if (!$db_device_id) {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'Device not found for provided identifiers']);
            return;
        }
        
        // Log sensor data if present
        if ($db_user_id && $temp !== null) {
            $logQuery = "INSERT INTO temperature_logs 
                        (user_id, internal_temp, external_humidity, cooling_status, timestamp) 
                        VALUES ($1, $2, $3, $4, NOW())";
            
            // Handle different types for alarm_active
            $is_alarm = ($alarm_active === true || $alarm_active === 1 || $alarm_active === 'true' || $alarm_active === '1');
            $cooling_status = $is_alarm ? 'ALARM' : 'NORMAL';
            
            pg_query_params($conn, $logQuery, array($db_user_id, $temp, $humidity, $cooling_status));
        }
        
        // Update device registry
        $updateQuery = "UPDATE device_registry SET last_sync = NOW()";
        $params = [];
        
        if ($status) {
            $updateQuery .= ", status = $1 WHERE id = $2";
            $params = [$status, $db_device_id];
        } else {
            $updateQuery .= " WHERE id = $1";
            $params = [$db_device_id];
        }
        
        pg_query_params($conn, $updateQuery, $params);
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Device status/data updated',
            'device_id' => $db_device_id,
            'sensor_logged' => ($temp !== null)
        ]);
        
    } catch (Exception $e) {
        error_log("Update Device Status Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDeviceHeartbeat($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $mac_address = $input['mac_address'] ?? null;
    $status = $input['status'] ?? 'ACTIVE';
    $wifi_signal = $input['wifi_signal'] ?? null;
    
    if (!$mac_address) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'mac_address required']);
        return;
    }
    
    try {
        $query = "UPDATE device_registry SET 
                  last_sync = NOW(), 
                  status = $1 
                  WHERE mac_address = $2";
        
        $result = pg_query_params($conn, $query, array($status, $mac_address));
        
        if ($result) {
            http_response_code(200);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'Heartbeat received'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to process heartbeat']);
        }
    } catch (Exception $e) {
        error_log("Device Heartbeat Error: " . $e->getMessage());
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
