<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Notifications & Alarm Management API
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

// Get action from path
$path = trim($_SERVER['PATH_INFO'] ?? '', '/');
$segments = explode('/', $path);
if (isset($segments[0]) && $segments[0] === 'api') {
    array_shift($segments);
}

$action = $segments[0] ?? $_GET['module'] ?? '';
$subaction = $segments[1] ?? $_GET['action'] ?? '';

error_log("NOTIFICATION REQUEST: action=$action, subaction=$subaction");

switch ($action) {
    case 'notifications':
        if ($subaction === 'send') {
            handleSendNotification($method);
        } elseif ($subaction === 'pending') {
            handleGetPendingNotifications($method);
        } elseif ($subaction === 'mark-sent') {
            handleMarkNotificationSent($method);
        } elseif ($subaction === 'dismiss-all') {
            handleDismissAllNotifications($method);
        } elseif ($subaction === 'mark-read') {
            handleMarkNotificationsRead($method);
        } elseif ($subaction === 'test-push') {
            handleTestPush($method);
        }
        break;
    
    case 'alarm':
        if ($subaction === 'trigger') {
            handleTriggerAlarm($method);
        } elseif ($subaction === 'dismiss') {
            handleDismissAlarm($method);
        } elseif ($subaction === 'status') {
            handleGetAlarmStatus($method);
        } elseif ($subaction === 'schedule-reminder') {
            handleScheduleReminder($method);
        }
        break;
    
    case 'rfid':
        if ($subaction === 'unlock') {
            handleRFIDUnlock($method);
        }
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found']);
}

function handleSendNotification($method) {
    global $conn;
    if ($method !== 'POST') return errorResponse(405, 'Method not allowed');
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    $schedule_id = $input['schedule_id'] ?? null;
    $type = $input['type'] ?? 'MEDICINE_REMINDER';
    $message = $input['message'] ?? null;
    $phone = $input['phone'] ?? null;
    
    if (!$user_id || !$message) return errorResponse(400, 'user_id and message required');
    
    try {
        if (!$phone) {
            $uRes = pg_query_params($conn, "SELECT phone FROM users WHERE id = $1", [$user_id]);
            if ($uRes && pg_num_rows($uRes) > 0) {
                $user = pg_fetch_assoc($uRes);
                $phone = $user['phone'];
            }
        }
        
        $insertQuery = "INSERT INTO notifications (user_id, schedule_id, type, message, phone, sms_sent, app_sent) 
                       VALUES ($1, $2, $3, $4, $5, false, false) RETURNING id";
        $insertResult = pg_query_params($conn, $insertQuery, [$user_id, $schedule_id, $type, $message, $phone]);
        if (!$insertResult) throw new Exception(pg_last_error($conn));
        $notif_id = pg_fetch_assoc($insertResult)['id'];
        
        $sms_sent = false;
        if ($phone) {
            $sms_sent = sendSMS($phone, $message);
            pg_query_params($conn, "UPDATE notifications SET sms_sent = $1, sms_sent_at = NOW() WHERE id = $2", [$sms_sent, $notif_id]);
        }
        
        $app_sent = false;
        $tRes = pg_query_params($conn, "SELECT fcm_token, expo_push_token FROM users WHERE id = $1", [$user_id]);
        if ($tRes && pg_num_rows($tRes) > 0) {
            $user = pg_fetch_assoc($tRes);
            if ($user['fcm_token']) {
                $app_sent = sendFCMPushNotification($user['fcm_token'], "Smart Medi Box", $message);
            } elseif ($user['expo_push_token']) {
                $app_sent = sendExpoPushNotification($user['expo_push_token'], "Smart Medi Box", $message);
            }
        }
        
        pg_query_params($conn, "UPDATE notifications SET app_sent = $1, app_sent_at = NOW() WHERE id = $2", [$app_sent, $notif_id]);
        
        http_response_code(201);
        echo json_encode(['status' => 'SUCCESS', 'notification_id' => intval($notif_id), 'sms_sent' => $sms_sent, 'app_sent' => $app_sent]);
    } catch (Exception $e) {
        return errorResponse(500, $e->getMessage());
    }
}

function handleGetPendingNotifications($method) {
    global $conn;
    if ($method !== 'GET') return errorResponse(405, 'Method not allowed');
    $user_id = $_GET['user_id'] ?? null;
    if (!$user_id) return errorResponse(400, 'user_id required');
    
    try {
        $query = "SELECT n.id, n.schedule_id, n.type, n.message, n.sms_sent, n.app_sent, n.is_read, n.created_at, 
                         s.photo, s.medicine_name, s.description
                  FROM notifications n LEFT JOIN schedules s ON s.id = n.schedule_id
                  WHERE n.user_id = $1 AND n.is_dismissed = false AND n.created_at >= NOW() - INTERVAL '24 hours'
                  ORDER BY n.created_at DESC LIMIT 50";
        $result = pg_query_params($conn, $query, [$user_id]);
        $notifications = [];
        while ($row = pg_fetch_assoc($result)) {
            $notifications[] = [
                'id' => intval($row['id']), 'schedule_id' => $row['schedule_id'] ? intval($row['schedule_id']) : null,
                'type' => $row['type'], 'message' => $row['message'], 'medicine_name' => $row['medicine_name'],
                'description' => $row['description'], 'sms_sent' => $row['sms_sent'] === 't',
                'app_sent' => $row['app_sent'] === 't', 'is_read' => $row['is_read'] === 't',
                'created_at' => $row['created_at'], 'photo' => $row['photo']
            ];
        }
        echo json_encode(['status' => 'SUCCESS', 'notifications' => $notifications]);
    } catch (Exception $e) {
        return errorResponse(500, $e->getMessage());
    }
}

function handleTriggerAlarm($method) {
    global $conn;
    if ($method !== 'POST') return errorResponse(405, 'Method not allowed');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    $schedule_id = $input['schedule_id'] ?? null;
    $schedule_type = $input['schedule_type'] ?? 'MEDICINE';
    
    if (!$user_id) return errorResponse(400, 'user_id required');
    
    try {
        $res = pg_query_params($conn, "INSERT INTO alarm_logs (user_id, schedule_id, triggered_at, status) VALUES ($1, $2, NOW(), 'TRIGGERED') RETURNING id", [$user_id, $schedule_id]);
        $alarm_id = pg_fetch_assoc($res)['id'];
        
        $message = "It's time for your $schedule_type medication!";
        pg_query_params($conn, "INSERT INTO notifications (user_id, schedule_id, type, message) VALUES ($1, $2, $3, $4)", [$user_id, $schedule_id, 'ALARM_' . $schedule_type, $message]);

        $app_sent = false;
        $tRes = pg_query_params($conn, "SELECT fcm_token, expo_push_token FROM users WHERE id = $1", [$user_id]);
        if ($tRes && pg_num_rows($tRes) > 0) {
            $user = pg_fetch_assoc($tRes);
            $data = ['type' => 'alarm', 'schedule_id' => $schedule_id];
            if ($user['fcm_token']) {
                $app_sent = sendFCMPushNotification($user['fcm_token'], "Smart Medi Box Alarm", $message, $data);
            } elseif ($user['expo_push_token']) {
                $app_sent = sendExpoPushNotification($user['expo_push_token'], "Smart Medi Box Alarm", $message, $data);
            }
        }

        $commands = ["ALARM_DATA|" . strtoupper($schedule_type) . "|NOW", "BUZZ:ON", "SOL:UNLOCK"];
        foreach ($commands as $cmd) {
            pg_query_params($conn, "INSERT INTO arduino_commands (user_id, command, status) VALUES ($1, $2, 'PENDING')", [$user_id, $cmd]);
        }
        
        http_response_code(201);
        echo json_encode(['status' => 'SUCCESS', 'alarm_id' => intval($alarm_id), 'app_sent' => $app_sent]);
    } catch (Exception $e) {
        return errorResponse(500, $e->getMessage());
    }
}

function handleDismissAlarm($method) {
    global $conn;
    if ($method !== 'POST') return errorResponse(405, 'Method not allowed');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    if (!$user_id) return errorResponse(400, 'user_id required');
    try {
        pg_query_params($conn, "UPDATE alarm_logs SET status = 'DISMISSED', dismissed_at = NOW() WHERE user_id = $1 AND status = 'TRIGGERED'", [$user_id]);
        pg_query_params($conn, "INSERT INTO arduino_commands (user_id, command, status) VALUES ($1, 'BUZZ:OFF', 'PENDING')", [$user_id]);
        echo json_encode(['status' => 'SUCCESS', 'message' => 'Alarm dismissed']);
    } catch (Exception $e) {
        return errorResponse(500, $e->getMessage());
    }
}

function handleGetAlarmStatus($method) {
    global $conn;
    if ($method !== 'GET') return errorResponse(405, 'Method not allowed');
    $user_id = $_GET['user_id'] ?? null;
    if (!$user_id) return errorResponse(400, 'user_id required');
    try {
        $result = pg_query_params($conn, "SELECT id, status, triggered_at, dismissed_at, door_opened FROM alarm_logs WHERE user_id = $1 ORDER BY triggered_at DESC LIMIT 1", [$user_id]);
        if (!$result || pg_num_rows($result) === 0) return errorResponse(404, 'No alarm found');
        $alarm = pg_fetch_assoc($result);
        echo json_encode(['status' => 'SUCCESS', 'alarm' => $alarm]);
    } catch (Exception $e) {
        return errorResponse(500, $e->getMessage());
    }
}

function handleScheduleReminder($method) {
    global $conn;
    if ($method !== 'POST') return errorResponse(405, 'Method not allowed');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $alarm_id = $input['alarm_id'] ?? null;
    $user_id = $input['user_id'] ?? null;
    if (!$alarm_id || !$user_id) return errorResponse(400, 'alarm_id and user_id required');
    try {
        $res = pg_query_params($conn, "INSERT INTO reminder_schedules (alarm_id, user_id, next_reminder_at) VALUES ($1, $2, NOW() + INTERVAL '5 minutes') RETURNING id", [$alarm_id, $user_id]);
        echo json_encode(['status' => 'SUCCESS', 'reminder_id' => pg_fetch_assoc($res)['id']]);
    } catch (Exception $e) {
        return errorResponse(500, $e->getMessage());
    }
}

function handleRFIDUnlock($method) {
    global $conn;
    if ($method !== 'POST') return errorResponse(405, 'Method not allowed');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    $rfid_tag = $input['rfid_tag'] ?? null;
    if (!$user_id || !$rfid_tag) return errorResponse(400, 'user_id and rfid_tag required');
    try {
        $vRes = pg_query_params($conn, "SELECT id FROM user_rfid_tags WHERE user_id = $1 AND rfid_tag = $2 AND is_active = true", [$user_id, $rfid_tag]);
        if (!$vRes || pg_num_rows($vRes) === 0) return errorResponse(401, 'Invalid RFID tag');
        pg_query_params($conn, "INSERT INTO arduino_commands (user_id, command, status) VALUES ($1, 'SOL:UNLOCK', 'PENDING')", [$user_id]);
        pg_query_params($conn, "INSERT INTO rfid_access_logs (user_id, rfid_tag, access_type, authorized) VALUES ($1, $2, 'UNLOCK', true)", [$user_id, $rfid_tag]);
        echo json_encode(['status' => 'SUCCESS', 'message' => 'Solenoid unlocked via RFID']);
    } catch (Exception $e) {
        return errorResponse(500, $e->getMessage());
    }
}

function handleTestPush($method) {
    global $conn;
    $user_id = $_GET['user_id'] ?? null;
    if (!$user_id) return errorResponse(400, 'user_id required');
    try {
        $res = pg_query_params($conn, "SELECT name, fcm_token, expo_push_token FROM users WHERE id = $1", [$user_id]);
        if ($res && pg_num_rows($res) > 0) {
            $user = pg_fetch_assoc($res);
            $sent = false;
            $m = '';
            if ($user['fcm_token']) {
                $sent = sendFCMPushNotification($user['fcm_token'], "Test", "Hello " . $user['name']);
                $m = 'FCM';
            } elseif ($user['expo_push_token']) {
                $sent = sendExpoPushNotification($user['expo_push_token'], "Test", "Hello " . $user['name']);
                $m = 'Expo';
            } else {
                return errorResponse(404, "No tokens found");
            }
            echo json_encode(['status' => 'SUCCESS', 'message' => "Sent via $m", 'sent' => $sent]);
        } else {
            return errorResponse(404, "User not found");
        }
    } catch (Exception $e) {
        return errorResponse(500, $e->getMessage());
    }
}

function sendSMS($phone, $message) {
    error_log("SMS_SENT: phone=$phone, message=$message");
    return true; 
}

function errorResponse($code, $message) {
    http_response_code($code);
    echo json_encode(['status' => 'ERROR', 'message' => $message]);
}
?>