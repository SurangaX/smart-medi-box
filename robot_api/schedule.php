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
    case 'trigger-due':
        handleTriggerDueSchedules($method);
        break;
    case 'snooze':
        handleSnoozeSchedule($method);
        break;
    case 'dispense-now':
        handleDispenseNow($method);
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
        
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $photo = $input['photo'] ?? null;
        $medicine_name = $input['medicine_name'] ?? $_POST['medicine_name'] ?? null;
        $is_recurring = ($input['is_recurring'] ?? $_POST['is_recurring'] ?? 'false') === 'true' || ($input['is_recurring'] ?? $_POST['is_recurring'] ?? false) === true;
        $end_date = $input['end_date'] ?? $_POST['end_date'] ?? null;

        // Note: $user_id from token lookup is already users.id (the database primary key)
        $db_user_id = $user_id;
        
        $query = "INSERT INTO schedules 
                  (schedule_id, user_id, type, medicine_name, schedule_date, end_date, is_recurring, hour, minute, description, photo, status, is_completed) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, 'ACTIVE', false)";
        
        $result = pg_query_params($conn, $query, 
            array($schedule_id, $db_user_id, $type, $medicine_name, $schedule_date, $end_date, $is_recurring ? 't' : 'f', $hour, $minute, $description, $photo));
        
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

    error_log("COMPLETE_SCHEDULE - Received: schedule_id=$schedule_id, token=" . ($token ? "YES" : "NO") . ", user_id=$user_id");
    
    // If token is provided, look up the user_id from it
    if ($token && !$user_id) {
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if ($token_result && pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
            error_log("COMPLETE_SCHEDULE - Token verified, user_id set to: $user_id");
        } else {
            error_log("COMPLETE_SCHEDULE - Token verification failed or expired");
        }
    }
    
    if (!$schedule_id || !$user_id) {
        error_log("COMPLETE_SCHEDULE - FAILED: schedule_id or user_id missing. schedule_id=$schedule_id, user_id=$user_id");
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters: schedule_id and (user_id or token)']);
        return;
    }
    
    try {
        // Note: $user_id from token lookup is already users.id (the database primary key)
        $db_user_id = $user_id;
        
        // For non-recurring schedules, we mark them as completed in the main table.
        // For recurring schedules, we DO NOT mark them as completed in the main table, 
        // because that would prevent them from triggering on future days.
        // We always log the completion in schedule_logs which is used for daily tracking.
        $query = "UPDATE schedules SET 
                  is_completed = (CASE WHEN is_recurring = false THEN true ELSE false END), 
                  completed_at = (CASE WHEN is_recurring = false THEN NOW() ELSE completed_at END),
                  updated_at = NOW()
                  WHERE id = $1 AND user_id = $2";
        
        $result = pg_query_params($conn, $query, array($schedule_id, $db_user_id));
        
        if ($result) {
            // Also dismiss any existing notifications for this schedule to prevent popups re-appearing
            $dismiss_query = "UPDATE notifications SET is_dismissed = true, updated_at = NOW() 
                             WHERE schedule_id = $1 AND user_id = $2 AND is_dismissed = false";
            pg_query_params($conn, $dismiss_query, array($schedule_id, $db_user_id));
            
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
        // Fetch schedules that are either:
        // 1. One-time schedules matching the filtered date range
        // 2. Recurring schedules where the filtered date range overlaps with the recurrence period
        // We use generate_series to expand recurring schedules into one entry per day
        $query = "WITH date_range AS (
                    SELECT CAST(generate_series(CAST($2 AS DATE), CAST($3 AS DATE), '1 day') AS DATE) as d
                  )
                  SELECT 
                    s.id, s.type, s.medicine_name, s.hour, s.minute, s.description, s.photo, s.is_recurring, s.end_date, s.status as sched_status,
                    dr.d as current_day,
                    (SELECT COUNT(*) > 0 FROM schedule_logs sl 
                     WHERE sl.schedule_id = s.id AND sl.action LIKE 'COMPLETED%' 
                     AND DATE(sl.created_at) = dr.d) as day_completed,
                    (SELECT COUNT(*) > 0 FROM schedule_logs sl 
                     WHERE sl.schedule_id = s.id AND sl.action = 'MISSED' 
                     AND DATE(sl.created_at) = dr.d) as day_missed
                  FROM schedules s
                  CROSS JOIN date_range dr
                  WHERE s.user_id = $1 
                  AND s.status != 'DELETED'
                  AND (
                      (s.is_recurring = false AND s.schedule_date = dr.d)
                      OR
                      (s.is_recurring = true AND dr.d BETWEEN s.schedule_date AND s.end_date)
                  )
                  ORDER BY dr.d ASC, s.hour ASC, s.minute ASC";
        
        error_log("GET TODAY SCHEDULES - Query parameters: user_id=$user_id, start_date=$start_date, end_date=$end_date");
        
        $result = pg_query_params($conn, $query, array($user_id, $start_date, $end_date));
        
        if ($result === false) {
            error_log("GET TODAY SCHEDULES - Query failed: " . pg_last_error($conn));
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Database query failed: ' . pg_last_error($conn)]);
            return;
        }
        
        error_log("GET TODAY SCHEDULES - Query returned " . pg_num_rows($result) . " rows");
        
        $schedules = [];
        while ($row = pg_fetch_assoc($result)) {
            $is_missed = ($row['day_missed'] === 't' || $row['day_missed'] === true || $row['sched_status'] === 'MISSED');
            $schedules[] = [
                'schedule_id' => $row['id'],
                'type' => $row['type'],
                'medicine_name' => $row['medicine_name'] ?? $row['type'],
                'schedule_date' => $row['current_day'],
                'hour' => intval($row['hour']),
                'minute' => intval($row['minute']),
                'description' => $row['description'],
                'photo' => $row['photo'],
                'is_recurring' => $row['is_recurring'] === 't' || $row['is_recurring'] === true,
                'end_date' => $row['end_date'],
                'is_completed' => $row['day_completed'] === 't' || $row['day_completed'] === true,
                'is_missed' => $is_missed,
                'status' => $is_missed ? 'MISSED' : ($row['day_completed'] === 't' || $row['day_completed'] === true ? 'COMPLETED' : 'ACTIVE'),
                'datetime' => $row['current_day'] . ' ' . str_pad($row['hour'], 2, '0', STR_PAD_LEFT) . ':' . str_pad($row['minute'], 2, '0', STR_PAD_LEFT)
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
    $token = $input['token'] ?? $_POST['token'] ?? null;
    $user_id = $input['user_id'] ?? $_POST['user_id'] ?? null;

    // If token is provided, look up the user_id from it
    if ($token && !$user_id) {
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));

        if ($token_result && pg_num_rows($token_result) > 0) {
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
        // If user_id is passed as a string (legacy/direct), we might need to convert it, 
        // but if it's from token lookup, it's already the numeric primary key.
        // Let's check if it's numeric. If not, we try to find the numeric ID.
        $db_user_id = $user_id;
        if (!is_numeric($user_id)) {
            // Since there is no user_id column in users table, we check against other fields if needed,
            // but usually token lookup is preferred.
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Invalid user ID format']);
            return;
        }

        $query = "UPDATE schedules SET status = 'DELETED', deleted_at = NOW()
                  WHERE id = $1 AND user_id = $2";

        $result = pg_query_params($conn, $query, array($schedule_id, $db_user_id));

        if ($result && pg_affected_rows($result) > 0) {
            http_response_code(200);
            echo json_encode(['status' => 'SUCCESS', 'message' => 'Schedule deleted']);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'Schedule not found or unauthorized']);
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
        // Note: $user_id from token lookup is already users.id (the database primary key)
        // No need to query for it again
        $db_user_id = $user_id;
        
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

// ============================================================================
// TRIGGER DUE SCHEDULES (called by cron / scheduler)
// Finds schedules due at current time, creates dashboard notification,
// logs alarm, and queues arduino commands if device is available.
// ============================================================================
function handleTriggerDueSchedules($method) {
    global $conn;

    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }

    // Use GMT+5:30 timezone (Asia/Colombo); allow override for testing via query param
    $now = new DateTime();
    $override = $_GET['now'] ?? $_POST['now'] ?? null;
    if ($override) {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $override)) {
            $now = DateTime::createFromFormat('Y-m-d H:i:s', $override);
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $override)) {
            $now = DateTime::createFromFormat('Y-m-d H:i', $override);
        }
    }

    $date = $now->format('Y-m-d');
    $hour = intval($now->format('H'));
    $minute = intval($now->format('i'));

    error_log("TRIGGER_DUE - Checking for schedules at $date $hour:$minute");

    try {
        // Find schedules due now or earlier today (that haven't been triggered yet today)
        // Updated to support Recurring schedules (Today is between start_date and end_date)
        $query = "SELECT id, user_id, type, medicine_name, schedule_date, end_date, is_recurring, hour, minute, description 
                  FROM schedules 
                  WHERE status = 'ACTIVE' 
                  AND is_completed = false
                  AND (
                      (is_recurring = false AND schedule_date = $1)
                      OR
                      (is_recurring = true AND $1 BETWEEN schedule_date AND end_date)
                  )
                  AND (hour < $2 OR (hour = $2 AND minute <= $3))";

        $result = pg_query_params($conn, $query, array($date, $hour, $minute));

        if ($result === false) {
            error_log("TRIGGER_DUE - Query failed: " . pg_last_error($conn));
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Database query failed']);
            return;
        }

        $triggered_count = pg_num_rows($result);
        error_log("TRIGGER_DUE - Found $triggered_count potential schedules");

        $triggered = [];
        while ($row = pg_fetch_assoc($result)) {
            error_log("TRIGGER_DUE - Processing schedule ID: " . $row['id']);
            $schedule_db_id = $row['id'];
            $user_db_id = $row['user_id'];
            $type = $row['type'];
            $med_name = $row['medicine_name'] ?? $type;
            $is_recurring = ($row['is_recurring'] === 't' || $row['is_recurring'] === true);

            // Additional check for recurring schedules: were they already completed today?
            if ($is_recurring) {
                $checkCompletedToday = pg_query_params($conn, 
                    "SELECT id FROM schedule_logs WHERE schedule_id = $1 AND DATE(created_at) = $2 AND action LIKE 'COMPLETED%' LIMIT 1", 
                    array($schedule_db_id, $date));
                if ($checkCompletedToday && pg_num_rows($checkCompletedToday) > 0) {
                    error_log("TRIGGER_DUE - Skipping schedule $schedule_db_id: already completed today (recurring)");
                    continue;
                }
            }

            // Check for existing alarms today to handle repeat reminders (every 5 mins up to 30 mins)
            $lastAlarmQuery = "SELECT triggered_at, status FROM alarm_logs 
                               WHERE schedule_id = $1 AND DATE(triggered_at) = $2 
                               ORDER BY triggered_at DESC LIMIT 1";
            $lastAlarmResult = pg_query_params($conn, $lastAlarmQuery, array($schedule_db_id, $date));
            
            $is_repeat = false;
            if ($lastAlarmResult && pg_num_rows($lastAlarmResult) > 0) {
                $lastAlarmRow = pg_fetch_assoc($lastAlarmResult);
                $lastTriggered = new DateTime($lastAlarmRow['triggered_at']);
                $lastStatus = $lastAlarmRow['status'];

                // If user already dismissed or acknowledged today's alarm, stop nagging them for this schedule
                if ($lastStatus === 'DISMISSED' || $lastStatus === 'ACKNOWLEDGED' || $lastStatus === 'MISSED') {
                    error_log("TRIGGER_DUE - Skipping schedule $schedule_db_id: last alarm status is $lastStatus");
                    continue;
                }
                
                // Calculate original scheduled time for today to determine the 30-min window
                $schedTime = clone $now;
                $schedTime->setTime($row['hour'], $row['minute']);
                
                // Use absolute difference in case of slight clock skews
                $diff_minutes_from_schedule = abs($now->getTimestamp() - $schedTime->getTimestamp()) / 60;
                $mins_since_last_alarm = ($now->getTimestamp() - $lastTriggered->getTimestamp()) / 60;
                
                if ($diff_minutes_from_schedule > 30) {
                    error_log("TRIGGER_DUE - Marking schedule $schedule_db_id as MISSED (past 30-minute window)");
                    
                    // Check if we already logged this as MISSED to avoid duplicate MISSED entries
                    $checkMissed = pg_query_params($conn, "SELECT id FROM alarm_logs WHERE schedule_id = $1 AND DATE(triggered_at) = $2 AND status = 'MISSED' LIMIT 1", array($schedule_db_id, $date));
                    
                    if (!$checkMissed || pg_num_rows($checkMissed) === 0) {
                        // Mark alarm as MISSED
                        pg_query_params($conn, "INSERT INTO alarm_logs (user_id, schedule_id, triggered_at, status) VALUES ($1, $2, NOW(), 'MISSED')", array($user_db_id, $schedule_db_id));
                        
                        // For non-recurring, also mark the main schedule as MISSED
                        if (!$is_recurring) {
                            pg_query_params($conn, "UPDATE schedules SET status = 'MISSED' WHERE id = $1", array($schedule_db_id));
                        }
                        
                        // Log it in schedule_logs
                        pg_query_params($conn, "INSERT INTO schedule_logs (user_id, schedule_id, action, details) VALUES ($1, $2, 'MISSED', 'Timed out after 30 mins')", array($user_db_id, $schedule_db_id));
                        
                        // Final Missed Notification
                        $missedMessage = "MISSED: It's time for your $med_name has passed. Please contact your doctor if necessary.";
                        pg_query_params($conn, "INSERT INTO notifications (user_id, schedule_id, type, message, app_sent, is_read) VALUES ($1, $2, 'ALARM_MISSED', $3, true, true)", array($user_db_id, $schedule_db_id, $missedMessage));
                        
                        // Stop Arduino buzzer if it was still on
                        pg_query_params($conn, "INSERT INTO arduino_commands (user_id, command, status) VALUES ($1, 'BUZZ:OFF', 'PENDING')", array($user_db_id));
                    }
                    continue; 
                }
                if ($mins_since_last_alarm < 5) {
                    // Too soon for a repeat
                    continue; 
                }
                
                $is_repeat = true;
                error_log("TRIGGER_DUE - Triggering REPEAT alarm for schedule $schedule_db_id ($diff_minutes_from_schedule mins since scheduled)");
            }

            // Create alarm_log
            $insAlarm = pg_query_params($conn, "INSERT INTO alarm_logs (user_id, schedule_id, triggered_at, status) VALUES ($1, $2, NOW(), 'TRIGGERED') RETURNING id", array($user_db_id, $schedule_db_id));
            $alarm_id = $insAlarm ? (pg_fetch_assoc($insAlarm)['id'] ?? null) : null;

            // Build notification message with Medicine Name
            $message = "It's time for your $med_name, please check the box.";
            if ($type === 'FOOD') $message = "It's time for your meal: $med_name.";
            if ($type === 'BLOOD_CHECK') $message = "Time to check your blood sugar for $med_name.";
            
            // Mark as repeat if applicable
            if ($is_repeat) {
                $message = "Repeat Reminder: " . $message;
            }

            // Insert notification (dashboard)
            $notifResult = pg_query_params($conn, "INSERT INTO notifications (user_id, schedule_id, type, message, sms_sent, app_sent) VALUES ($1, $2, $3, $4, false, false) RETURNING id", array($user_db_id, $schedule_db_id, 'ALARM_' . $type, $message));
            if (!$notifResult) {
                error_log("TRIGGER_DUE - Failed to insert notification: " . pg_last_error($conn));
            } else {
                $notifId = pg_fetch_assoc($notifResult)['id'] ?? null;
                error_log("TRIGGER_DUE - Inserted notification ID: " . $notifId . " for user: " . $user_db_id);

                // Send Push Notification
                // Delivery strictly via ntfy.sh (Bypasses Google limits)
                $app_sent = sendNtfyNotification($user_db_id, "Smart Medi Box Alarm", $message);

                if ($app_sent && $notifId) {
                    pg_query_params($conn, "UPDATE notifications SET app_sent = true, app_sent_at = NOW() WHERE id = $1", [$notifId]);
                }
            }

            // Always queue arduino commands when a schedule triggers
            $time_str = str_pad($row['hour'], 2, '0', STR_PAD_LEFT) . ":" . str_pad($row['minute'], 2, '0', STR_PAD_LEFT);
            $commands = [
                "ALARM_DATA|" . $med_name . "|" . $time_str,
                "BUZZ:ON",
                "SOL:UNLOCK"
            ];
            
            $commands_queued = 0;
            foreach ($commands as $cmd) {
                pg_query_params($conn, "INSERT INTO arduino_commands (user_id, command, status) VALUES ($1, $2, 'PENDING')", array($user_db_id, $cmd));
                $commands_queued++;
            }

            $triggered[] = [
                'schedule_id' => intval($schedule_db_id),
                'user_id' => intval($user_db_id),
                'alarm_id' => $alarm_id ? intval($alarm_id) : null,
                'commands_queued' => $commands_queued
            ];
        }

        http_response_code(200);
        echo json_encode(['status' => 'SUCCESS', 'date' => $date, 'time' => sprintf('%02d:%02d', $hour, $minute), 'triggered_count' => count($triggered), 'details' => $triggered]);
    } catch (Exception $e) {
        error_log("TRIGGER_DUE ERROR: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Failed to trigger due schedules']);
    }
}

function handleSnoozeSchedule($method) {
    global $conn;

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $schedule_id = $input['schedule_id'] ?? $_POST['schedule_id'] ?? null;
    $token = $input['token'] ?? $_POST['token'] ?? null;

    if (!$schedule_id || !$token) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing schedule_id or token']);
        return;
    }

    try {
        // 1. Verify token and get user_id
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));

        if (!$token_result || pg_num_rows($token_result) === 0) {
            http_response_code(401);
            echo json_encode(['status' => 'ERROR', 'message' => 'Invalid or expired token']);
            return;
        }
        $user_id = pg_fetch_assoc($token_result)['user_id'];

        // 2. Get current schedule time
        $query = "SELECT hour, minute FROM schedules WHERE id = $1 AND user_id = $2";
        $result = pg_query_params($conn, $query, array($schedule_id, $user_id));

        if (!$result || pg_num_rows($result) === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'Schedule not found']);
            return;
        }

        $row = pg_fetch_assoc($result);
        $h = intval($row['hour']);
        $m = intval($row['minute']);

        // 3. Add 5 minutes
        $m += 5;
        if ($m >= 60) {
            $m -= 60;
            $h += 1;
            if ($h >= 24) $h = 0;
        }

        // 4. Update schedule and reset is_completed/alarm status
        $update_query = "UPDATE schedules 
                         SET hour = $1, minute = $2, is_completed = false, updated_at = NOW() 
                         WHERE id = $3 AND user_id = $4";
        pg_query_params($conn, $update_query, array($h, $m, $schedule_id, $user_id));

        // 5. Clear current notifications for this schedule today so they don't clutter
        $clear_notif = "DELETE FROM notifications WHERE schedule_id = $1 AND user_id = $2 AND created_at >= CURRENT_DATE";
        pg_query_params($conn, $clear_notif, array($schedule_id, $user_id));

        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS', 
            'message' => 'Schedule snoozed for 5 minutes',
            'new_time' => sprintf("%02d:%02d", $h, $m)
        ]);

    } catch (Exception $e) {
        error_log("Snooze Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

// ============================================================================
// MANUAL DISPENSE TRIGGER
// Queues unlock command and creates a tracking notification for door sensor
// ============================================================================
function handleDispenseNow($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $schedule_id = $input['schedule_id'] ?? null;
    $token = $input['token'] ?? null;
    
    if (!$schedule_id || !$token) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters: schedule_id and token']);
        return;
    }
    
    try {
        // 1. Verify token and get user_id
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if (!$token_result || pg_num_rows($token_result) === 0) {
            http_response_code(401);
            echo json_encode(['status' => 'ERROR', 'message' => 'Invalid or expired token']);
            return;
        }
        $user_id = pg_fetch_assoc($token_result)['user_id'];
        
        // 2. Fetch medicine name for display and verify ownership
        $sched_query = "SELECT medicine_name, type FROM schedules WHERE id = $1 AND user_id = $2";
        $sched_result = pg_query_params($conn, $sched_query, array($schedule_id, $user_id));
        
        if (!$sched_result || pg_num_rows($sched_result) === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'Schedule not found or unauthorized']);
            return;
        }
        
        $sched_row = pg_fetch_assoc($sched_result);
        $med_name = $sched_row['medicine_name'] ?? $sched_row['type'];
        $display_name = substr(strtoupper($med_name), 0, 16);
        
        // 3. Queue Arduino commands in sequence: Display -> Buzzer -> Solenoid
        $commands = [
            "ALARM_DATA|" . $med_name . "|NOW",
            "BUZZ:ON",
            "SOL:UNLOCK|" . $med_name
        ];
        
        foreach ($commands as $cmd) {
            pg_query_params($conn, "INSERT INTO arduino_commands (user_id, command, status) VALUES ($1, $2, 'PENDING')", array($user_id, $cmd));
        }
        
        // 4. Create a tracking notification for the door sensor (med-taken)
        // This ensures that when the user opens/closes the door, the backend finds this
        // notification and marks the associated schedule as completed.
        // We set app_sent = false to indicate this is a tracking placeholder, not a popup.
        $message = "Manual dispense triggered for: $med_name. Please take your medicine.";
        pg_query_params($conn, "INSERT INTO notifications (user_id, schedule_id, type, message, app_sent, is_dismissed) 
                               VALUES ($1, $2, 'MANUAL_DISPENSE', $3, false, false)", 
                               array($user_id, $schedule_id, $message));
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Dispense command sent to device'
        ]);
        
    } catch (Exception $e) {
        error_log("Dispense Now Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

?>
