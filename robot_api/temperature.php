<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Temperature & Cooling API (PostgreSQL Compatible)
 * ============================================================================
 */

require_once 'db_config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$action = $request_uri[0] ?? '';

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
        echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found']);
        break;
}

function handleGetCurrentTemp($method) {
    global $conn;
    
    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    // Support both POST (with token) and GET (with user_id or device_id)
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? null;
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? null;
    $device_id = $input['device_id'] ?? $_POST['device_id'] ?? $_GET['device_id'] ?? null;
    
    // If token is provided, look up the user_id from it
    if ($token && !$user_id && !$device_id) {
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if (pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
        }
    }
    
    if (!$user_id && !$device_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'user_id or device_id required (via token or direct parameter)']);
        return;
    }
    
    try {
        if ($user_id) {
            $query = "SELECT internal_temp, external_humidity, target_temp, cooling_status, timestamp 
                      FROM temperature_logs 
                      WHERE user_id = (SELECT id FROM users WHERE user_id = $1)
                      ORDER BY timestamp DESC LIMIT 1";
            
            $result = pg_query_params($conn, $query, array($user_id));
        } else {
            $query = "SELECT internal_temp, external_humidity, target_temp, cooling_status, timestamp 
                      FROM temperature_logs 
                      WHERE device_id = $1
                      ORDER BY timestamp DESC LIMIT 1";
            
            $result = pg_query_params($conn, $query, array($device_id));
        }
        
        if (pg_num_rows($result) > 0) {
            $row = pg_fetch_assoc($result);
            
            http_response_code(200);
            echo json_encode([
                'status' => 'SUCCESS',
                'temperature' => [
                    'internal_temp' => floatval($row['internal_temp']),
                    'external_humidity' => floatval($row['external_humidity']),
                    'target_temp' => floatval($row['target_temp']),
                    'cooling_status' => $row['cooling_status'] === 'ON',
                    'timestamp' => $row['timestamp']
                ]
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'NO_DATA', 'message' => 'No temperature data found']);
        }
    } catch (Exception $e) {
        error_log("Get Current Temp Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

function handleSetTargetTemp($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? null;
    $target_temp = $input['target_temp'] ?? $_POST['target_temp'] ?? null;
    
    if (!$user_id || $target_temp === null) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters']);
        return;
    }
    
    $target_temp = floatval($target_temp);
    if ($target_temp < 2 || $target_temp > 8) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Temperature must be between 2-8°C']);
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
        
        $query = "UPDATE temperature_settings SET target_temp = $1, updated_at = NOW() 
                  WHERE user_id = $2";
        
        $result = pg_query_params($conn, $query, array($target_temp, $db_user_id));
        
        if ($result) {
            $logQuery = "INSERT INTO temperature_logs (user_id, target_temp, action) 
                        VALUES ($1, $2, 'TARGET_SET')";
            pg_query_params($conn, $logQuery, array($db_user_id, $target_temp));
            
            broadcastToArduino($db_user_id, "TEMP_SET|" . $target_temp);
            
            http_response_code(200);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'Target temperature set',
                'target_temp' => $target_temp
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to update temperature']);
        }
    } catch (Exception $e) {
        error_log("Set Target Temp Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

function handleGetTempHistory($method) {
    global $conn;
    
    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    // Support both POST (with token) and GET (with user_id)
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? null;
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? null;
    $days = $input['days'] ?? $_POST['days'] ?? $_GET['days'] ?? 7;
    
    // If token is provided, look up the user_id from it
    if ($token && !$user_id) {
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if (pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
        }
    }
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'user_id required (via token or direct parameter)']);
        return;
    }
    
    $days = intval($days);
    if ($days < 1 || $days > 90) {
        $days = 7;
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
        
        $query = "SELECT internal_temp, external_humidity, target_temp, cooling_status, timestamp 
                  FROM temperature_logs 
                  WHERE user_id = $1 AND timestamp >= NOW() - INTERVAL '1 day' * $2
                  ORDER BY timestamp DESC 
                  LIMIT 1000";
        
        $result = pg_query_params($conn, $query, array($db_user_id, $days));
        
        $history = [];
        $avgTemp = 0;
        $count = 0;
        
        while ($row = pg_fetch_assoc($result)) {
            $history[] = [
                'date' => date('Y-m-d', strtotime($row['timestamp'])),
                'timestamp' => $row['timestamp'],
                'avg_temp' => floatval($row['internal_temp']),
                'target_temp' => floatval($row['target_temp']),
                'external_humidity' => floatval($row['external_humidity']),
                'cooling_status' => $row['cooling_status'] === 'ON'
            ];
            $avgTemp += floatval($row['internal_temp']);
            $count++;
        }
        
        $avgTemp = $count > 0 ? $avgTemp / $count : 0;
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'period_days' => $days,
            'records' => $count,
            'average_temp' => round($avgTemp, 2),
            'history' => array_reverse($history)
        ]);
    } catch (Exception $e) {
        error_log("Get Temp History Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

function handleCoolingControl($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? null;
    $action = $input['action'] ?? $_POST['action'] ?? null;
    
    if (!$user_id || !$action) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters']);
        return;
    }
    
    if (!in_array($action, ['ON', 'OFF', 'AUTO'])) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Invalid action']);
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
        
        $query = "UPDATE temperature_settings SET cooling_mode = $1, updated_at = NOW() 
                  WHERE user_id = $2";
        
        $result = pg_query_params($conn, $query, array($action, $db_user_id));
        
        if ($result) {
            $logQuery = "INSERT INTO temperature_logs (user_id, action) 
                        VALUES ($1, $2)";
            $actionLog = 'COOLING_' . $action;
            pg_query_params($conn, $logQuery, array($db_user_id, $actionLog));
            
            broadcastToArduino($db_user_id, "COOLING_" . $action);
            
            http_response_code(200);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'Cooling control updated',
                'mode' => $action
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to update cooling']);
        }
    } catch (Exception $e) {
        error_log("Cooling Control Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

function broadcastToArduino($user_id, $command) {
    global $conn;
    
    $query = "INSERT INTO arduino_commands (user_id, command, status) 
              VALUES ($1, $2, 'PENDING')";
    
    pg_query_params($conn, $query, array($user_id, $command));
}

?>
