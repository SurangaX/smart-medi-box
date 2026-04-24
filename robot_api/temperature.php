<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Temperature & Cooling API (PostgreSQL Compatible)
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
        $action = $request_uri[2] ?? '';
    }
}

switch ($action) {
    case 'current':
        handleGetCurrentTemp($method);
        break;
    case 'set-target':
        handleSetTargetTemp($method);
        break;
    case 'history':
        handleGetTempHistory($method);
        break;
    case 'control':
        handleCoolingControl($method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found', 'received_action' => $action]);
        break;
}

/**
 * Helper to get DB user ID from various identifiers
 */
function getDbUserId($identifier) {
    global $conn;
    if (!$identifier) return null;
    
    // If it's a numeric string, it might be the database ID
    if (is_numeric($identifier)) {
        $res = pg_query_params($conn, "SELECT id FROM users WHERE id = $1", array($identifier));
        if ($res && pg_num_rows($res) > 0) return pg_fetch_result($res, 0, 0);
    }
    
    // Otherwise try matching by user_id string or mac_address
    $res = pg_query_params($conn, "SELECT id FROM users WHERE user_id = $1 OR UPPER(mac_address) = UPPER($1)", array($identifier));
    if ($res && pg_num_rows($res) > 0) return pg_fetch_result($res, 0, 0);
    
    return null;
}

function handleGetCurrentTemp($method) {
    global $conn;
    
    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? null;
    $user_id_param = $input['user_id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? null;
    $mac_address = $input['mac_address'] ?? $_POST['mac_address'] ?? $_GET['mac_address'] ?? null;
    
    $db_user_id = null;
    
    if ($token) {
        $token_res = pg_query_params($conn, "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP", array($token));
        if ($token_res && pg_num_rows($token_res) > 0) $db_user_id = pg_fetch_result($token_res, 0, 0);
    }
    
    if (!$db_user_id && $user_id_param) $db_user_id = getDbUserId($user_id_param);
    if (!$db_user_id && $mac_address) $db_user_id = getDbUserId($mac_address);
    
    if (!$db_user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'User not identified']);
        return;
    }
    
    try {
        $query = "SELECT internal_temp, external_humidity, target_temp, cooling_status, timestamp 
                  FROM temperature_logs 
                  WHERE user_id = $1
                  ORDER BY timestamp DESC LIMIT 1";
        
        $result = pg_query_params($conn, $query, array($db_user_id));
        
        if ($result && pg_num_rows($result) > 0) {
            $row = pg_fetch_assoc($result);
            echo json_encode([
                'status' => 'SUCCESS',
                'temperature' => [
                    'internal_temp' => floatval($row['internal_temp']),
                    'external_humidity' => floatval($row['external_humidity']),
                    'target_temp' => floatval($row['target_temp']),
                    'cooling_status' => ($row['cooling_status'] === 'ON' || $row['cooling_status'] === 'ALARM'),
                    'timestamp' => $row['timestamp']
                ]
            ]);
        } else {
            // Check settings if no logs yet
            $setRes = pg_query_params($conn, "SELECT target_temp FROM temperature_settings WHERE user_id = $1", array($db_user_id));
            $target = ($setRes && pg_num_rows($setRes) > 0) ? pg_fetch_result($setRes, 0, 0) : 4.0;
            
            http_response_code(200); // Return 200 but with NO_DATA status
            echo json_encode([
                'status' => 'NO_DATA', 
                'message' => 'Waiting for device to report data',
                'target_temp' => floatval($target)
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
    }
}

function handleSetTargetTemp($method) {
    global $conn;
    if ($method !== 'POST') return;
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id_param = $input['user_id'] ?? $_POST['user_id'] ?? null;
    $target_temp = $input['target_temp'] ?? $_POST['target_temp'] ?? null;
    
    $db_user_id = getDbUserId($user_id_param);
    
    if (!$db_user_id || $target_temp === null) {
        http_response_code(400);
        return;
    }
    
    $target_temp = floatval($target_temp);
    
    try {
        pg_query_params($conn, "UPDATE temperature_settings SET target_temp = $1, updated_at = NOW() WHERE user_id = $2", array($target_temp, $db_user_id));
        pg_query_params($conn, "INSERT INTO arduino_commands (user_id, command, status) VALUES ($1, $2, 'PENDING')", array($db_user_id, "TEMP_SET|" . $target_temp));
        
        echo json_encode(['status' => 'SUCCESS', 'target_temp' => $target_temp]);
    } catch (Exception $e) {
        http_response_code(500);
    }
}

function handleGetTempHistory($method) {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id_param = $input['user_id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? null;
    $days = intval($input['days'] ?? $_GET['days'] ?? 7);
    
    $db_user_id = getDbUserId($user_id_param);
    if (!$db_user_id) { http_response_code(400); return; }
    
    try {
        $query = "SELECT internal_temp, external_humidity, target_temp, cooling_status, timestamp 
                  FROM temperature_logs 
                  WHERE user_id = $1 AND timestamp >= NOW() - INTERVAL '1 day' * $2
                  ORDER BY timestamp ASC LIMIT 2000";
        
        $result = pg_query_params($conn, $query, array($db_user_id, $days));
        $history = [];
        while ($row = pg_fetch_assoc($result)) {
            $history[] = [
                'timestamp' => $row['timestamp'],
                'temp' => floatval($row['internal_temp']),
                'humidity' => floatval($row['external_humidity'])
            ];
        }
        echo json_encode(['status' => 'SUCCESS', 'history' => $history]);
    } catch (Exception $e) {
        http_response_code(500);
    }
}

function handleCoolingControl($method) {
    // Similar robust implementation
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id_param = $input['user_id'] ?? $_POST['user_id'] ?? null;
    $action = $input['action'] ?? $_POST['action'] ?? null;
    
    $db_user_id = getDbUserId($user_id_param);
    if (!$db_user_id || !$action) { http_response_code(400); return; }
    
    pg_query_params($conn, "UPDATE temperature_settings SET cooling_mode = $1 WHERE user_id = $2", array($action, $db_user_id));
    pg_query_params($conn, "INSERT INTO arduino_commands (user_id, command, status) VALUES ($1, $2, 'PENDING')", array($db_user_id, "COOLING_" . $action));
    echo json_encode(['status' => 'SUCCESS', 'mode' => $action]);
}
?>
