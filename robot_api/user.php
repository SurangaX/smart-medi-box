<?php
/**
 * ============================================================================
 * SMART MEDI BOX - User Management API (PostgreSQL Compatible)
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
    // PATH_INFO format: /api/user/action, so action is at index 2
    $action = $request_uri[2] ?? '';
}

switch ($action) {
    case 'profile':
        handleGetProfile($method);
        break;
    case 'update':
        handleUpdateUser($method);
        break;
    case 'update-push-token':
        handleUpdatePushToken($method);
        break;
    case 'dashboard':
        handleGetDashboard($method);
        break;
    case 'stats':
        handleGetStats($method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found']);
        break;
}

function handleGetProfile($method) {
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
        $query = "SELECT u.id, u.name, u.age, u.phone, u.mac_address, u.status, 
                         u.created_at, ts.target_temp, ts.cooling_mode
                  FROM users u
                  LEFT JOIN temperature_settings ts ON u.id = ts.user_id
                  WHERE u.id = $1";
        
        $result = pg_query_params($conn, $query, array($user_id));
        
        if (pg_num_rows($result) > 0) {
            $user = pg_fetch_assoc($result);
            $db_user_id = $user['id'];
            
            $countQuery = "SELECT COUNT(*) as count FROM schedules WHERE user_id = $1 AND status = 'ACTIVE'";
            $countResult = pg_query_params($conn, $countQuery, array($db_user_id));
            $countData = pg_fetch_assoc($countResult);
            
            http_response_code(200);
            echo json_encode([
                'status' => 'SUCCESS',
                'user' => [
                    'user_id' => $user['user_id'],
                    'name' => $user['name'],
                    'age' => $user['age'],
                    'phone' => $user['phone'],
                    'mac_address' => $user['mac_address'],
                    'status' => $user['status'],
                    'created_at' => $user['created_at'],
                    'total_schedules' => intval($countData['count']),
                    'temperature_settings' => [
                        'target_temp' => floatval($user['target_temp']),
                        'cooling_mode' => $user['cooling_mode']
                    ]
                ]
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'User not found']);
        }
    } catch (Exception $e) {
        error_log("Get Profile Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

function handleUpdateUser($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? null;
    $name = $input['name'] ?? $_POST['name'] ?? null;
    $age = $input['age'] ?? $_POST['age'] ?? null;
    $phone = $input['phone'] ?? $_POST['phone'] ?? null;
    
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
        
        $updates = [];
        $params = [];
        $param_count = 1;
        
        if ($name !== null) {
            $updates[] = "name = \$" . $param_count;
            $params[] = $name;
            $param_count++;
        }
        
        if ($age !== null) {
            $updates[] = "age = \$" . $param_count;
            $params[] = intval($age);
            $param_count++;
        }
        
        if ($phone !== null) {
            $phone = validatePhoneNumber($phone);
            if (!$phone) {
                http_response_code(400);
                echo json_encode(['status' => 'ERROR', 'message' => 'Invalid phone number']);
                return;
            }
            $updates[] = "phone = \$" . $param_count;
            $params[] = $phone;
            $param_count++;
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'No fields to update']);
            return;
        }
        
        $updates[] = "updated_at = NOW()";
        $query = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = \$" . $param_count;
        $params[] = $db_user_id;
        
        $result = pg_query_params($conn, $query, $params);
        
        if ($result) {
            http_response_code(200);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'User updated successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to update user']);
        }
    } catch (Exception $e) {
        error_log("Update User Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

function handleGetDashboard($method) {
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
        
        $today = date('Y-m-d');
        
        $schedQuery = "SELECT type, hour, minute, is_completed FROM schedules 
                       WHERE user_id = $1 AND status = 'ACTIVE'
                       ORDER BY hour, minute";
        $schedResult = pg_query_params($conn, $schedQuery, array($db_user_id));
        
        $todaySchedules = [];
        $completedCount = 0;
        
        while ($row = pg_fetch_assoc($schedResult)) {
            $todaySchedules[] = [
                'type' => $row['type'],
                'time' => sprintf("%02d:%02d", $row['hour'], $row['minute']),
                'completed' => $row['is_completed']
            ];
            if ($row['is_completed']) $completedCount++;
        }
        
        $tempQuery = "SELECT internal_temp, external_humidity, timestamp 
                      FROM temperature_logs 
                      WHERE user_id = $1
                      ORDER BY timestamp DESC LIMIT 1";
        $tempResult = pg_query_params($conn, $tempQuery, array($db_user_id));
        
        $temperature = null;
        if (pg_num_rows($tempResult) > 0) {
            $temp = pg_fetch_assoc($tempResult);
            $temperature = [
                'internal_temp' => floatval($temp['internal_temp']),
                'humidity' => floatval($temp['external_humidity']),
                'timestamp' => $temp['timestamp']
            ];
        }
        
        $weekQuery = "SELECT COUNT(*) as count FROM schedules 
                      WHERE user_id = $1 AND is_completed = true
                      AND created_at >= NOW() - INTERVAL '7 days'";
        $weekResult = pg_query_params($conn, $weekQuery, array($db_user_id));
        $weekData = pg_fetch_assoc($weekResult);
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'dashboard' => [
                'date' => $today,
                'schedules' => [
                    'total' => count($todaySchedules),
                    'completed' => $completedCount,
                    'items' => $todaySchedules
                ],
                'temperature' => $temperature,
                'stats' => [
                    'completed_this_week' => intval($weekData['count'])
                ]
            ]
        ]);
    } catch (Exception $e) {
        error_log("Get Dashboard Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

function handleGetStats($method) {
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
        
        $adherenceQuery = "SELECT 
                          COUNT(*) as total,
                          SUM(CASE WHEN is_completed = true THEN 1 ELSE 0 END) as completed
                          FROM schedules 
                          WHERE user_id = $1 AND status = 'ACTIVE'";
        $adherenceResult = pg_query_params($conn, $adherenceQuery, array($db_user_id));
        $adherence = pg_fetch_assoc($adherenceResult);
        
        $adherenceRate = 0;
        if ($adherence['total'] > 0) {
            $adherenceRate = round(($adherence['completed'] / $adherence['total']) * 100, 2);
        }
        
        $tempQuery = "SELECT AVG(internal_temp) as avg_temp 
                      FROM temperature_logs 
                      WHERE user_id = $1 AND timestamp >= NOW() - INTERVAL '30 days'";
        $tempResult = pg_query_params($conn, $tempQuery, array($db_user_id));
        $temp = pg_fetch_assoc($tempResult);
        
        $trendQuery = "SELECT DATE(created_at) as date, COUNT(*) as completed 
                       FROM schedules 
                       WHERE user_id = $1 AND is_completed = true
                       AND created_at >= NOW() - INTERVAL '7 days'
                       GROUP BY DATE(created_at)
                       ORDER BY date";
        $trendResult = pg_query_params($conn, $trendQuery, array($db_user_id));
        
        $trend = [];
        while ($row = pg_fetch_assoc($trendResult)) {
            $trend[] = [
                'date' => $row['date'],
                'completed' => intval($row['completed'])
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'stats' => [
                'adherence_rate' => $adherenceRate . '%',
                'total_schedules' => intval($adherence['total']),
                'completed' => intval($adherence['completed']),
                'average_temperature' => floatval($temp['avg_temp']),
                'last_7_days_trend' => $trend
            ]
        ]);
    } catch (Exception $e) {
        error_log("Get Stats Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
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

/**
 * Update Expo Push Token for a user
 */
function handleUpdatePushToken($method) {
    global $conn;
    
    error_log("UPDATE_PUSH_TOKEN CALLED: method=$method");
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    error_log("UPDATE_PUSH_TOKEN INPUT: " . json_encode($input));
    
    $user_id = $input['user_id'] ?? null;
    $push_token = $input['expo_push_token'] ?? null;
    
    if (!$user_id || !$push_token) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'user_id and expo_push_token required']);
        return;
    }
    
    try {
        $query = "UPDATE users SET expo_push_token = $1, updated_at = NOW() WHERE id = $2";
        $result = pg_query_params($conn, $query, array($push_token, $user_id));
        
        if ($result && pg_affected_rows($result) > 0) {
            http_response_code(200);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'Push token updated successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'User not found or token already set']);
        }
    } catch (Exception $e) {
        error_log("Update Push Token Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

?>
