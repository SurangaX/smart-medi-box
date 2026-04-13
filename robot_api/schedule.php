<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Schedule API (PostgreSQL Compatible)
 * ============================================================================
 */

require_once 'db_config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$action = $request_uri[0] ?? '';

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
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? null;
    $type = $input['type'] ?? $_POST['type'] ?? null;
    $hour = $input['hour'] ?? $_POST['hour'] ?? null;
    $minute = $input['minute'] ?? $_POST['minute'] ?? null;
    $description = $input['description'] ?? $_POST['description'] ?? '';
    
    if (!$user_id || !$type || $hour === null || $minute === null) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters']);
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
                  (id, user_id, type, hour, minute, description, status, is_completed) 
                  VALUES ($1, $2, $3, $4, $5, $6, 'ACTIVE', false)";
        
        $result = pg_query_params($conn, $query, 
            array($schedule_id, $db_user_id, $type, $hour, $minute, $description));
        
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
    
    $schedule_id = $_POST['schedule_id'] ?? $_GET['schedule_id'] ?? null;
    $user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? null;
    
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
        $today = date('Y-m-d');
        
        $query = "SELECT id, type, hour, minute, description, is_completed 
                  FROM schedules 
                  WHERE user_id = (SELECT id FROM users WHERE user_id = $1) 
                  AND status = 'ACTIVE'
                  AND DATE(created_at) = $2
                  ORDER BY hour ASC, minute ASC";
        
        $result = pg_query_params($conn, $query, array($user_id, $today));
        
        $schedules = [];
        while ($row = pg_fetch_assoc($result)) {
            $schedules[] = [
                'schedule_id' => $row['id'],
                'type' => $row['type'],
                'time' => sprintf("%02d:%02d", $row['hour'], $row['minute']),
                'description' => $row['description'],
                'is_completed' => $row['is_completed']
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'date' => $today,
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
