<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Schedule API (PostgreSQL Compatible)
 * ============================================================================
 */

require_once 'db_config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Debug logging
error_log("SCHEDULE MODULE - PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'NOT SET'));
error_log("SCHEDULE MODULE - REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET'));
error_log("SCHEDULE MODULE - GET action: " . ($_GET['action'] ?? 'NOT SET'));

// Get action from $_GET (set by index.php router) or parse from PATH_INFO
$action = $_GET['action'] ?? '';

// If action not set, try parsing from PATH_INFO
if (!$action) {
    $request_uri = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    // PATH_INFO format: /api/schedule/action, so action is at index 2
    $action = $request_uri[2] ?? '';
    error_log("SCHEDULE MODULE - Parsed action from PATH_INFO: " . $action);
}

error_log("SCHEDULE MODULE - Final action: " . $action);

switch ($action) {
    case 'get':
        handleGetSchedules($method);
        break;
    case 'create':
        handleCreateSchedule($method);
        break;
    case 'update':
        handleUpdateSchedule($method);
        break;
    case 'complete':
        handleCompleteSchedule($method);
        break;
    case 'today':
        handleGetTodaySchedules($method);
        break;
    case 'delete':
        handleDeleteSchedule($method);
        break;
    case 'stats':
        handleGetStats($method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found']);
        break;
}

function handleGetSchedules($method) {
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
        $query = "SELECT id, type, hour, minute, description, status, is_completed, created_at 
                  FROM schedules 
                  WHERE user_id = (SELECT id FROM users WHERE user_id = $1) AND status = 'ACTIVE'
                  ORDER BY hour ASC, minute ASC";
        
        $result = pg_query_params($conn, $query, array($user_id));
        
        $schedules = [];
        while ($row = pg_fetch_assoc($result)) {
            $schedules[] = [
                'schedule_id' => $row['id'],
                'type' => $row['type'],
                'hour' => intval($row['hour']),
                'minute' => intval($row['minute']),
                'description' => $row['description'],
                'is_completed' => $row['is_completed'],
                'created_at' => $row['created_at']
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'count' => count($schedules),
            'schedules' => $schedules
        ]);
    } catch (Exception $e) {
        error_log("Get Schedules Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

// ============================================================================
// CREATE NEW SCHEDULE
// ============================================================================
function handleCreateSchedule($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? $_POST['token'] ?? null;
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? null;
    $type = $input['type'] ?? $_POST['type'] ?? null;
    $schedule_date = $input['schedule_date'] ?? $_POST['schedule_date'] ?? date('Y-m-d');
    $hour = $input['hour'] ?? $_POST['hour'] ?? null;
    $minute = $input['minute'] ?? $_POST['minute'] ?? null;
    $description = $input['description'] ?? $_POST['description'] ?? '';
    
    error_log("CREATE SCHEDULE - Received token: " . ($token ? substr($token, 0, 20) . '...' : 'NULL'));
    error_log("CREATE SCHEDULE - Received user_id: " . ($user_id ?? 'NULL'));
    error_log("CREATE SCHEDULE - Received type: " . ($type ?? 'NULL'));
    error_log("CREATE SCHEDULE - Received hour: " . ($hour ?? 'NULL'));
    error_log("CREATE SCHEDULE - Received minute: " . ($minute ?? 'NULL'));
    
    // If token is provided, look up the user_id from it
    if ($token && !$user_id) {
        error_log("CREATE SCHEDULE - Looking up user_id from token");
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if ($token_result === false) {
            error_log("CREATE SCHEDULE - Token query failed: " . pg_last_error($conn));
        } else {
            error_log("CREATE SCHEDULE - Token query returned " . pg_num_rows($token_result) . " rows");
        }
        
        if (pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
            error_log("CREATE SCHEDULE - Found user_id from token: " . $user_id);
        } else {
            error_log("CREATE SCHEDULE - No token found in session_tokens");
        }
    }
    
    error_log("CREATE SCHEDULE - Final user_id: " . ($user_id ?? 'NULL'));
    
    if (!$user_id || !$type || !$schedule_date || $hour === null || $minute === null) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters: user_id (via token), type, schedule_date (YYYY-MM-DD), hour, minute']);
        return;
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule_date)) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Invalid date format. Use YYYY-MM-DD']);
        return;
    }
    
    $validTypes = ['MEDICINE', 'FOOD', 'BLOOD_CHECK'];
    if (!in_array($type, $validTypes)) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Invalid schedule type']);
        return;
    }
    
    $hour = intval($hour);
    $minute = intval($minute);
    
    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Invalid time format']);
        return;
    }
    
    try {
        $schedule_id = generateScheduleID();
        
        $user_query = "SELECT id FROM users WHERE user_id = $1";
        $user_result = pg_query_params($conn, $user_query, array($user_id));
        
        if (pg_num_rows($user_result) === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'User not found']);
            return;
        }
        
        $user_data = pg_fetch_assoc($user_result);
        $db_user_id = $user_data['id'];
        
        $query = "INSERT INTO schedules 
                  (schedule_id, user_id, type, schedule_date, hour, minute, description, status, is_completed) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7, 'ACTIVE', false)";
        
        $result = pg_query_params($conn, $query, 
            array($schedule_id, $db_user_id, $type, $schedule_date, $hour, $minute, $description));
        
        if ($result) {
            http_response_code(201);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'Schedule created',
                'schedule_id' => $schedule_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to create schedule']);
        }
    } catch (Exception $e) {
        error_log("Create Schedule Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

// ============================================================================
// UPDATE SCHEDULE
// ============================================================================
function handleUpdateSchedule($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $schedule_id = $input['schedule_id'] ?? $_POST['schedule_id'] ?? null;
    $hour = $input['hour'] ?? $_POST['hour'] ?? null;
    $minute = $input['minute'] ?? $_POST['minute'] ?? null;
    $description = $input['description'] ?? $_POST['description'] ?? null;
    
    if (!$schedule_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'schedule_id required']);
        return;
    }
    
    try {
        $updates = [];
        $params = [];
        $param_count = 1;
        
        if ($hour !== null && $minute !== null) {
            $updates[] = "hour = \$" . $param_count . ", minute = \$" . ($param_count + 1);
            $params[] = $hour;
            $params[] = $minute;
            $param_count += 2;
        }
        
        if ($description !== null) {
            $updates[] = "description = \$" . $param_count;
            $params[] = $description;
            $param_count++;
        }
        
        if (empty($updates)) {
            http_response_code(200);
            echo json_encode(['status' => 'SUCCESS', 'message' => 'No updates provided']);
            return;
        }
        
        $updates[] = "updated_at = NOW()";
        $query = "UPDATE schedules SET " . implode(", ", $updates) . " WHERE id = \$" . $param_count;
        $params[] = $schedule_id;
        
        $result = pg_query_params($conn, $query, $params);
        
        if ($result) {
            http_response_code(200);
            echo json_encode(['status' => 'SUCCESS', 'message' => 'Schedule updated']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to update schedule']);
        }
    } catch (Exception $e) {
        error_log("Update Schedule Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

// ============================================================================
// MARK SCHEDULE AS COMPLETED
// ============================================================================
function handleCompleteSchedule($method) {
    global $conn;
    
    if ($method !== 'POST' && $method !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $schedule_id = $input['schedule_id'] ?? $_POST['schedule_id'] ?? $_GET['schedule_id'] ?? null;
    $token = $input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? null;
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? null;
    
    // If token is provided, look up the user_id from it
    if ($token && !$user_id) {
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if (pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
        }
    }
    
    if (!$schedule_id || !$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters: schedule_id and (user_id or token)']);
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
        
        $query = "UPDATE schedules SET is_completed = true, completed_at = NOW() 
                  WHERE id = $1 AND user_id = $2";
        
        $result = pg_query_params($conn, $query, array($schedule_id, $db_user_id));
        
        if ($result) {
            logScheduleCompletion($db_user_id, $schedule_id);
            http_response_code(200);
            echo json_encode(['status' => 'SUCCESS', 'message' => 'Schedule marked as completed']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to update schedule']);
        }
    } catch (Exception $e) {
        error_log("Complete Schedule Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

// ============================================================================
// GET TODAY'S SCHEDULES
// ============================================================================
function handleGetTodaySchedules($method) {
    global $conn;
    
    if ($method !== 'POST' && $method !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    // Support both POST (with token) and GET (with user_id)
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? null;
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? null;
    $start_date = $input['start_date'] ?? $_POST['start_date'] ?? $_GET['start_date'] ?? date('Y-m-d');
    $end_date = $input['end_date'] ?? $_POST['end_date'] ?? $_GET['end_date'] ?? date('Y-m-d');
    
    error_log("GET TODAY SCHEDULES - Received token: " . ($token ? substr($token, 0, 20) . '...' : 'NULL'));
    error_log("GET TODAY SCHEDULES - Received user_id: " . ($user_id ?? 'NULL'));
    error_log("GET TODAY SCHEDULES - Start date: " . $start_date . ", End date: " . $end_date);
    
    // If token is provided, look up the user_id from it
    if ($token && !$user_id) {
        error_log("GET TODAY SCHEDULES - Looking up user_id from token");
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if ($token_result === false) {
            error_log("GET TODAY SCHEDULES - Token query failed: " . pg_last_error($conn));
        } else {
            error_log("GET TODAY SCHEDULES - Token query returned " . pg_num_rows($token_result) . " rows");
        }
        
        if (pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
            error_log("GET TODAY SCHEDULES - Found user_id from token: " . $user_id);
        } else {
            error_log("GET TODAY SCHEDULES - No token found in session_tokens");
        }
    }
    
    error_log("GET TODAY SCHEDULES - Final user_id: " . ($user_id ?? 'NULL'));
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'user_id required (via token or direct parameter)']);
        return;
    }
    
    try {
        $query = "SELECT id, type, schedule_date, hour, minute, description, is_completed 
                  FROM schedules 
                  WHERE user_id = (SELECT id FROM users WHERE user_id = $1) 
                  AND status = 'ACTIVE'
                  AND schedule_date >= $2
                  AND schedule_date <= $3
                  ORDER BY schedule_date ASC, hour ASC, minute ASC";
        
        $result = pg_query_params($conn, $query, array($user_id, $start_date, $end_date));
        
        $schedules = [];
        while ($row = pg_fetch_assoc($result)) {
            $schedules[] = [
                'schedule_id' => $row['id'],
                'type' => $row['type'],
                'schedule_date' => $row['schedule_date'],
                'hour' => intval($row['hour']),
                'minute' => intval($row['minute']),
                'description' => $row['description'],
                'is_completed' => $row['is_completed'] === 't' || $row['is_completed'] === true,
                'datetime' => $row['schedule_date'] . ' ' . str_pad($row['hour'], 2, '0', STR_PAD_LEFT) . ':' . str_pad($row['minute'], 2, '0', STR_PAD_LEFT)
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'start_date' => $start_date,
            'end_date' => $end_date,
            'count' => count($schedules),
            'schedules' => $schedules
        ]);
    } catch (Exception $e) {
        error_log("Get Today Schedules Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

// ============================================================================
// DELETE SCHEDULE
// ============================================================================
function handleDeleteSchedule($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $schedule_id = $input['schedule_id'] ?? $_POST['schedule_id'] ?? null;
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? null;
    
    if (!$schedule_id || !$user_id) {
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
        
        $query = "UPDATE schedules SET status = 'DELETED', deleted_at = NOW() 
                  WHERE id = $1 AND user_id = $2";
        
        $result = pg_query_params($conn, $query, array($schedule_id, $db_user_id));
        
        if ($result) {
            http_response_code(200);
            echo json_encode(['status' => 'SUCCESS', 'message' => 'Schedule deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to delete schedule']);
        }
    } catch (Exception $e) {
        error_log("Delete Schedule Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

// ============================================================================
// GET STATISTICS
// ============================================================================
function handleGetStats($method) {
    global $conn;
    
    if ($method !== 'POST' && $method !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    // Support both POST (with token) and GET (with user_id)
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? null;
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? null;
    
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
    
    try {
        // Get user's database ID
        $user_query = "SELECT id FROM users WHERE user_id = $1";
        $user_result = pg_query_params($conn, $user_query, array($user_id));
        
        if (pg_num_rows($user_result) === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'User not found']);
            return;
        }
        
        $user_data = pg_fetch_assoc($user_result);
        $db_user_id = $user_data['id'];
        
        // Get today's stats
        $today = date('Y-m-d');
        $today_query = "SELECT 
                        COUNT(*) as total_today,
                        SUM(CASE WHEN is_completed = true THEN 1 ELSE 0 END) as completed_today
                        FROM schedules 
                        WHERE user_id = $1 AND status = 'ACTIVE' AND DATE(created_at) = $2";
        
        $today_result = pg_query_params($conn, $today_query, array($db_user_id, $today));
        $today_data = pg_fetch_assoc($today_result);
        
        $total_today = intval($today_data['total_today']) ?? 0;
        $completed_today = intval($today_data['completed_today']) ?? 0;
        
        // Calculate adherence rate (last 7 days)
        $adherence_query = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_completed = true THEN 1 ELSE 0 END) as completed
                            FROM schedules 
                            WHERE user_id = $1 AND status = 'ACTIVE' 
                            AND created_at >= NOW() - INTERVAL '7 days'";
        
        $adherence_result = pg_query_params($conn, $adherence_query, array($db_user_id));
        $adherence_data = pg_fetch_assoc($adherence_result);
        
        $total_7days = intval($adherence_data['total']) ?? 0;
        $completed_7days = intval($adherence_data['completed']) ?? 0;
        $adherence_rate = $total_7days > 0 ? round(($completed_7days / $total_7days) * 100, 1) : 0;
        
        // Get 7-day trend
        $trend_query = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as total,
                        SUM(CASE WHEN is_completed = true THEN 1 ELSE 0 END) as completed
                        FROM schedules 
                        WHERE user_id = $1 AND status = 'ACTIVE'
                        AND created_at >= NOW() - INTERVAL '7 days'
                        GROUP BY DATE(created_at)
                        ORDER BY date ASC";
        
        $trend_result = pg_query_params($conn, $trend_query, array($db_user_id));
        
        $trend = [];
        while ($row = pg_fetch_assoc($trend_result)) {
            $trend[] = [
                'date' => $row['date'],
                'total' => intval($row['total']),
                'completed' => intval($row['completed'])
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'adherence_rate' => $adherence_rate,
            'completed_today' => $completed_today,
            'total_today' => $total_today,
            'completed_7days' => $completed_7days,
            'total_7days' => $total_7days,
            'trend' => $trend
        ]);
    } catch (Exception $e) {
        error_log("Get Stats Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function generateScheduleID() {
    return 'SCHED_' . time() . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
}

function logScheduleCompletion($user_id, $schedule_id) {
    global $conn;
    
    $query = "INSERT INTO schedule_logs (user_id, schedule_id, action) 
              VALUES ($1, $2, 'COMPLETED')";
    
    pg_query_params($conn, $query, array($user_id, $schedule_id));
}

?>
