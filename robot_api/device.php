<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Device Management API (PostgreSQL Compatible)
 * ============================================================================
 */

require_once 'db_config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// ONLY set $action if it's not already defined by index.php
if (!isset($action) || empty($action)) {
    $action = $_GET['action'] ?? '';

    // If action not set, try parsing from PATH_INFO
    if (!$action) {
        $request_uri = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
        // PATH_INFO format: /api/device/action, so action is at index 2
        $action = $request_uri[2] ?? '';
    }
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
    case 'complete-command':
        handleCompleteCommand($method);
        break;
    case 'heartbeat':
        handleDeviceHeartbeat($method);
        break;
    case 'check-commands':
        handleCheckCommands($method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found', 'received_action' => $action]);
        break;
}

/**
 * Handle device registration/pairing lookup
 */
function handleRegisterDevice($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $mac_address = $input['mac_address'] ?? $_POST['mac_address'] ?? null;
    
    if (!$mac_address) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'mac_address required']);
        return;
    }

    $mac_address = trim($mac_address);

    try {
        $query = "SELECT d.id as device_db_id, d.device_id, u.id as user_db_id, u.name as user_name 
                  FROM devices d
                  LEFT JOIN device_user_map dum ON d.id = dum.device_id
                  LEFT JOIN users u ON dum.user_id = u.id
                  WHERE UPPER(TRIM(d.mac_address)) = UPPER($1)";
        
        $result = pg_query_params($conn, $query, array($mac_address));
        
        if (pg_num_rows($result) > 0) {
            $data = pg_fetch_assoc($result);
            
            if ($data['user_db_id']) {
                http_response_code(200);
                echo json_encode([
                    'status' => 'SUCCESS', 
                    'message' => 'Device recognized',
                    'device_id' => $data['device_id'],
                    'user_name' => $data['user_name'] ?? 'Paired User'
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    'status' => 'PENDING',
                    'message' => 'Device found but not paired',
                    'device_id' => $data['device_id']
                ]);
            }
        } else {
            // Check users table for legacy MAC mapping
            $userQuery = "SELECT id, name, user_id FROM users WHERE UPPER(TRIM(mac_address)) = UPPER($1)";
            $userRes = pg_query_params($conn, $userQuery, array($mac_address));
            
            if (pg_num_rows($userRes) > 0) {
                $userData = pg_fetch_assoc($userRes);
                http_response_code(200);
                echo json_encode([
                    'status' => 'SUCCESS',
                    'message' => 'Device recognized via legacy mapping',
                    'device_id' => 'LEGACY_' . $userData['user_id'],
                    'user_name' => $userData['name']
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    'status' => 'UNPAIRED',
                    'message' => 'Device not found'
                ]);
            }
        }
    } catch (Exception $e) {
        error_log("Register Device Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
    }
}

/**
 * Update device status and log sensor data
 */
function handleUpdateDeviceStatus($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        return;
    }
    
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true) ?? [];
    
    $mac_address = $input['mac_address'] ?? $_POST['mac_address'] ?? null;
    $temp = $input['temperature'] ?? null;
    $humidity = $input['humidity'] ?? null;
    $door_open = $input['door_open'] ?? null;
    $alarm_active = $input['alarm_active'] ?? null;
    
    if (!$mac_address) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'mac_address required']);
        return;
    }
    
    $mac_address = trim($mac_address);
    
    try {
        // Try new schema first
        $query = "SELECT d.id as device_db_id, dum.user_id 
                  FROM devices d
                  LEFT JOIN device_user_map dum ON d.id = dum.device_id
                  WHERE UPPER(TRIM(d.mac_address)) = UPPER($1)";
        
        $result = pg_query_params($conn, $query, array($mac_address));
        $user_id = null;
        $device_db_id = null;

        if ($result && pg_num_rows($result) > 0) {
            $data = pg_fetch_assoc($result);
            $device_db_id = $data['device_db_id'];
            $user_id = $data['user_id'];
        } else {
            // Try legacy users table mapping
            $legacyQuery = "SELECT id FROM users WHERE UPPER(TRIM(mac_address)) = UPPER($1)";
            $legacyRes = pg_query_params($conn, $legacyQuery, array($mac_address));
            if ($legacyRes && pg_num_rows($legacyRes) > 0) {
                $user_id = pg_fetch_result($legacyRes, 0, 0);
                error_log("Update Device Status - Using legacy user mapping for $mac_address");
            }
        }
        
        if (!$user_id && !$device_db_id) {
            error_log("Update Device Status - Warning: MAC $mac_address not found anywhere");
            http_response_code(200); // Return 200 to ESP32 to stop retries, but with UNKNOWN status
            echo json_encode(['status' => 'UNKNOWN', 'message' => 'Device not recognized']);
            return;
        }
        
        // Log sensor data
        if ($user_id && $temp !== null) {
            $logQuery = "INSERT INTO temperature_logs 
                        (user_id, internal_temp, external_humidity, cooling_status, timestamp) 
                        VALUES ($1, $2, $3, $4, NOW())";
            
            $is_alarm = ($alarm_active === true || $alarm_active === 1 || $alarm_active === 'true' || $alarm_active === '1');
            $cooling_status = $is_alarm ? 'ALARM' : 'NORMAL';
            
            pg_query_params($conn, $logQuery, array($user_id, $temp, $humidity, $cooling_status));
        }
        
        // Update heartbeat if device exists in devices table
        if ($device_db_id) {
            pg_query_params($conn, "UPDATE devices SET last_sync = NOW() WHERE id = $1", array($device_db_id));
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Status updated',
            'user_id' => $user_id
        ]);
        
    } catch (Exception $e) {
        error_log("Update Device Status Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
    }
}

/**
 * Heartbeat
 */
function handleDeviceHeartbeat($method) {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $mac = trim($input['mac_address'] ?? '');
    
    if (!$mac) {
        http_response_code(400);
        return;
    }
    
    try {
        pg_query_params($conn, "UPDATE devices SET last_sync = NOW() WHERE UPPER(TRIM(mac_address)) = UPPER($1)", array($mac));
        echo json_encode(['status' => 'SUCCESS']);
    } catch (Exception $e) {
        http_response_code(500);
    }
}

/**
 * Commands
 */
function handleCheckCommands($method) {
    global $conn;
    $mac = trim($_GET['mac_address'] ?? '');
    
    if (!$mac) {
        http_response_code(400);
        return;
    }
    
    try {
        // Try finding user_id from devices table or users table
        $q = "SELECT user_id FROM (
                SELECT dum.user_id FROM devices d JOIN device_user_map dum ON d.id = dum.device_id WHERE UPPER(TRIM(d.mac_address)) = UPPER($1)
                UNION
                SELECT id FROM users WHERE UPPER(TRIM(mac_address)) = UPPER($1)
              ) AS combined LIMIT 1";
        
        $res = pg_query_params($conn, $q, array($mac));
        if ($res && pg_num_rows($res) > 0) {
            $user_id = pg_fetch_result($res, 0, 0);
            $cmdRes = pg_query_params($conn, "SELECT id, command FROM arduino_commands WHERE user_id = $1 AND status = 'PENDING' ORDER BY created_at ASC", array($user_id));
            $cmds = [];
            while ($r = pg_fetch_assoc($cmdRes)) { $cmds[] = $r; }
            echo json_encode(['status' => 'SUCCESS', 'commands' => $cmds]);
        } else {
            echo json_encode(['status' => 'SUCCESS', 'commands' => []]);
        }
    } catch (Exception $e) {
        http_response_code(500);
    }
}

function handleSyncDevice($method) { handleUpdateDeviceStatus($method); }
function handleListDevices($method) { echo json_encode(['status' => 'SUCCESS', 'message' => 'Deprecated']); }

/**
 * Mark a command as completed
 */
function handleCompleteCommand($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $command_id = $input['command_id'] ?? $_POST['command_id'] ?? null;
    
    if (!$command_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'command_id required']);
        return;
    }
    
    try {
        $query = "UPDATE arduino_commands SET status = 'COMPLETED', updated_at = NOW() WHERE id = $1";
        $result = pg_query_params($conn, $query, array($command_id));
        
        if ($result) {
            echo json_encode(['status' => 'SUCCESS', 'message' => 'Command marked as completed']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to update command']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
    }
}
?>
