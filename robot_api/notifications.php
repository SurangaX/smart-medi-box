<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Notifications & Alarm Management API
 * ============================================================================
 * 
 * Endpoints:
 * - POST /api/notifications/send - Send SMS and app notification
 * - GET  /api/notifications/pending - Get pending notifications
 * - POST /api/notifications/mark-sent - Mark notification as sent
 * - POST /api/alarm/trigger - Trigger alarm for schedule
 * - POST /api/alarm/dismiss - Dismiss active alarm
 * - GET  /api/alarm/status - Get alarm status
 * - POST /api/alarm/schedule-reminder - Schedule recurring reminders
 * 
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
$path = trim($_SERVER['PATH_INFO'] ?? '', '/');
$segments = explode('/', $path);

// Remove 'api' segment
if (isset($segments[0]) && $segments[0] === 'api') {
    array_shift($segments);
}

$action = $segments[0] ?? '';
$subaction = $segments[1] ?? '';

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

// ============================================================================
// HANDLER: Send Notification (SMS + App)
// ============================================================================
function handleSendNotification($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    $schedule_id = $input['schedule_id'] ?? null;
    $type = $input['type'] ?? 'MEDICINE_REMINDER'; // MEDICINE_REMINDER, FOOD_REMINDER, BLOOD_CHECK_REMINDER
    $message = $input['message'] ?? null;
    $phone = $input['phone'] ?? null;
    
    error_log("SEND_NOTIFICATION: user_id=$user_id, type=$type");
    
    if (!$user_id || !$message) {
        return errorResponse(400, 'user_id and message required');
    }
    
    try {
        // Get user phone if not provided
        if (!$phone) {
            $userQuery = "SELECT phone FROM users WHERE id = $1";
            $userResult = pg_query_params($conn, $userQuery, [$user_id]);
            if ($userResult && pg_num_rows($userResult) > 0) {
                $user = pg_fetch_assoc($userResult);
                $phone = $user['phone'];
            }
        }
        
        // Store notification
        $insertQuery = "INSERT INTO notifications (user_id, schedule_id, type, message, phone, sms_sent, app_sent) 
                       VALUES ($1, $2, $3, $4, $5, false, false)
                       RETURNING id";
        $insertResult = pg_query_params($conn, $insertQuery, [$user_id, $schedule_id, $type, $message, $phone]);
        
        if (!$insertResult) {
            throw new Exception(pg_last_error($conn));
        }
        
        $notif = pg_fetch_assoc($insertResult);
        $notif_id = $notif['id'];
        
        // Send SMS (if phone available)
        $sms_sent = false;
        if ($phone) {
            $sms_sent = sendSMS($phone, $message);
            error_log("SMS SENT: phone=$phone, sent=$sms_sent");
            
            // Update notification
            $updateQuery = "UPDATE notifications SET sms_sent = $1, sms_sent_at = NOW() WHERE id = $2";
            pg_query_params($conn, $updateQuery, [$sms_sent, $notif_id]);
        }
        
        // Queue app notification (would be sent to app via push service)
        $app_sent = true; // Mark as queued for app
        $updateQuery = "UPDATE notifications SET app_sent = $1, app_sent_at = NOW() WHERE id = $2";
        pg_query_params($conn, $updateQuery, [$app_sent, $notif_id]);
        
        error_log("SEND_NOTIFICATION SUCCESS: notif_id=$notif_id, sms_sent=$sms_sent");
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'notification_id' => intval($notif_id),
            'sms_sent' => $sms_sent,
            'app_queued' => true,
            'message' => 'Notification sent'
        ]);
        
    } catch (Exception $e) {
        error_log("SEND_NOTIFICATION ERROR: " . $e->getMessage());
        return errorResponse(500, 'Failed to send notification: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Get Pending Notifications
// ============================================================================
function handleGetPendingNotifications($method) {
    global $conn;
    
    if ($method !== 'GET') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        return errorResponse(400, 'user_id required');
    }
    
    try {
        // Fetch all non-dismissed notifications from the last 24 hours for the user
        $query = "SELECT id, schedule_id, type, message, sms_sent, app_sent, created_at 
                  FROM notifications 
                  WHERE user_id = $1 AND is_dismissed = false AND created_at >= NOW() - INTERVAL '24 hours'
                  ORDER BY created_at DESC
                  LIMIT 50";
        $result = pg_query_params($conn, $query, [$user_id]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        $notifications = [];
        while ($row = pg_fetch_assoc($result)) {
            $notifications[] = [
                'id' => intval($row['id']),
                'schedule_id' => $row['schedule_id'] ? intval($row['schedule_id']) : null,
                'type' => $row['type'],
                'message' => $row['message'],
                'sms_sent' => $row['sms_sent'] === 't',
                'app_sent' => $row['app_sent'] === 't',
                'created_at' => $row['created_at']
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'notifications' => $notifications,
            'count' => count($notifications)
        ]);
        
    } catch (Exception $e) {
        error_log("GET_PENDING_NOTIFICATIONS ERROR: " . $e->getMessage());
        return errorResponse(500, 'Failed to fetch notifications: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Dismiss All Notifications
// ============================================================================
function handleDismissAllNotifications($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    
    if (!$user_id) {
        return errorResponse(400, 'user_id required');
    }
    
    try {
        $updateQuery = "UPDATE notifications SET is_dismissed = true, updated_at = NOW() 
                       WHERE user_id = $1 AND is_dismissed = false";
        pg_query_params($conn, $updateQuery, [$user_id]);
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'All notifications dismissed'
        ]);
        
    } catch (Exception $e) {
        error_log("DISMISS_ALL_NOTIFICATIONS ERROR: " . $e->getMessage());
        return errorResponse(500, 'Failed to dismiss notifications: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Mark Notification as Sent
// ============================================================================
function handleMarkNotificationSent($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $notif_id = $input['notification_id'] ?? null;
    $type = $input['type'] ?? 'SMS'; // SMS, APP
    
    if (!$notif_id) {
        return errorResponse(400, 'notification_id required');
    }
    
    try {
        if ($type === 'SMS') {
            $updateQuery = "UPDATE notifications SET sms_sent = true, sms_sent_at = NOW() WHERE id = $1";
        } else {
            $updateQuery = "UPDATE notifications SET app_sent = true, app_sent_at = NOW() WHERE id = $1";
        }
        
        pg_query_params($conn, $updateQuery, [$notif_id]);
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => "$type notification marked as sent"
        ]);
        
    } catch (Exception $e) {
        return errorResponse(500, 'Failed to mark notification: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Trigger Alarm
// ============================================================================
function handleTriggerAlarm($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    $schedule_id = $input['schedule_id'] ?? null;
    $schedule_type = $input['schedule_type'] ?? 'MEDICINE'; // MEDICINE, FOOD, BLOOD_CHECK
    
    error_log("TRIGGER_ALARM: user_id=$user_id, schedule_id=$schedule_id, type=$schedule_type");
    
    if (!$user_id) {
        return errorResponse(400, 'user_id required');
    }
    
    try {
        // Create alarm log
        $insertQuery = "INSERT INTO alarm_logs (user_id, schedule_id, triggered_at, status) 
                       VALUES ($1, $2, NOW(), 'TRIGGERED')
                       RETURNING id";
        $result = pg_query_params($conn, $insertQuery, [$user_id, $schedule_id]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        $alarm = pg_fetch_assoc($result);
        $alarm_id = $alarm['id'];
        
        // Queue notification
        $message = "It's time for your $schedule_type medication!";
        if ($schedule_type === 'FOOD') {
            $message = "It's time for your meal!";
        } elseif ($schedule_type === 'BLOOD_CHECK') {
            $message = "Time to check your blood sugar!";
        }
        
        // Send notification
        $notifQuery = "INSERT INTO notifications (user_id, schedule_id, type, message) 
                      VALUES ($1, $2, $3, $4)";
        pg_query_params($conn, $notifQuery, [$user_id, $schedule_id, 'ALARM_' . $schedule_type, $message]);
        
        // Queue commands to Arduino: activate buzzer, display, unlock solenoid
        $commands = [
            "BUZZ:ON",  // Activate buzzer
            "DISP:SHOW_" . strtoupper($schedule_type),  // Show message on display
            "SOL:UNLOCK"  // Unlock solenoid
        ];
        
        foreach ($commands as $cmd) {
            $cmdQuery = "INSERT INTO arduino_commands (user_id, command, status) 
                        VALUES ($1, $2, 'PENDING')";
            pg_query_params($conn, $cmdQuery, [$user_id, $cmd]);
        }
        
        error_log("TRIGGER_ALARM SUCCESS: alarm_id=$alarm_id, commands queued");
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'alarm_id' => intval($alarm_id),
            'message' => "Alarm triggered - Buzzer, Display, and Solenoid activated",
            'commands_queued' => 3
        ]);
        
    } catch (Exception $e) {
        error_log("TRIGGER_ALARM ERROR: " . $e->getMessage());
        return errorResponse(500, 'Failed to trigger alarm: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Dismiss Alarm
// ============================================================================
function handleDismissAlarm($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    $alarm_id = $input['alarm_id'] ?? null;
    
    error_log("DISMISS_ALARM: user_id=$user_id, alarm_id=$alarm_id");
    
    if (!$user_id) {
        return errorResponse(400, 'user_id required');
    }
    
    try {
        // Update alarm status
        $updateQuery = "UPDATE alarm_logs 
                       SET status = 'DISMISSED', dismissed_at = NOW()
                       WHERE user_id = $1 AND status = 'TRIGGERED'";
        pg_query_params($conn, $updateQuery, [$user_id]);
        
        // Queue stop buzzer command
        $cmdQuery = "INSERT INTO arduino_commands (user_id, command, status) 
                    VALUES ($1, 'BUZZ:OFF', 'PENDING')";
        pg_query_params($conn, $cmdQuery, [$user_id]);
        
        error_log("DISMISS_ALARM SUCCESS: buzzer stopped");
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Alarm dismissed - Buzzer stopped'
        ]);
        
    } catch (Exception $e) {
        error_log("DISMISS_ALARM ERROR: " . $e->getMessage());
        return errorResponse(500, 'Failed to dismiss alarm: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Get Alarm Status
// ============================================================================
function handleGetAlarmStatus($method) {
    global $conn;
    
    if ($method !== 'GET') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        return errorResponse(400, 'user_id required');
    }
    
    try {
        $query = "SELECT id, status, triggered_at, dismissed_at, door_opened 
                  FROM alarm_logs 
                  WHERE user_id = $1 
                  ORDER BY triggered_at DESC 
                  LIMIT 1";
        $result = pg_query_params($conn, $query, [$user_id]);
        
        if (!$result || pg_num_rows($result) === 0) {
            return errorResponse(404, 'No alarm found');
        }
        
        $alarm = pg_fetch_assoc($result);
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'alarm' => [
                'id' => intval($alarm['id']),
                'status' => $alarm['status'],
                'triggered_at' => $alarm['triggered_at'],
                'dismissed_at' => $alarm['dismissed_at'],
                'door_opened' => $alarm['door_opened'] === 't'
            ]
        ]);
        
    } catch (Exception $e) {
        return errorResponse(500, 'Failed to get alarm status: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: Schedule Reminder (Recurring SMS + App reminders every 5 mins)
// ============================================================================
function handleScheduleReminder($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $alarm_id = $input['alarm_id'] ?? null;
    $user_id = $input['user_id'] ?? null;
    $interval_minutes = $input['interval_minutes'] ?? 5; // Default 5 minute intervals
    
    if (!$alarm_id || !$user_id) {
        return errorResponse(400, 'alarm_id and user_id required');
    }
    
    try {
        // Create reminder schedule
        $insertQuery = "INSERT INTO reminder_schedules (alarm_id, user_id, interval_minutes, next_reminder_at) 
                       VALUES ($1, $2, $3, NOW() + INTERVAL '5 minutes')
                       RETURNING id";
        $result = pg_query_params($conn, $insertQuery, [$alarm_id, $user_id, $interval_minutes]);
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        $reminder = pg_fetch_assoc($result);
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'reminder_id' => intval($reminder['id']),
            'interval_minutes' => intval($interval_minutes),
            'message' => "Reminders will repeat every $interval_minutes minutes"
        ]);
        
    } catch (Exception $e) {
        error_log("SCHEDULE_REMINDER ERROR: " . $e->getMessage());
        return errorResponse(500, 'Failed to schedule reminder: ' . $e->getMessage());
    }
}

// ============================================================================
// HANDLER: RFID Override (Unlock without triggering alarm)
// ============================================================================
function handleRFIDUnlock($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? null;
    $rfid_tag = $input['rfid_tag'] ?? null;
    
    error_log("RFID_UNLOCK: user_id=$user_id, rfid_tag=$rfid_tag");
    
    if (!$user_id || !$rfid_tag) {
        return errorResponse(400, 'user_id and rfid_tag required');
    }
    
    try {
        // Verify RFID tag belongs to user
        $verifyQuery = "SELECT id FROM user_rfid_tags WHERE user_id = $1 AND rfid_tag = $2 AND is_active = true";
        $verifyResult = pg_query_params($conn, $verifyQuery, [$user_id, $rfid_tag]);
        
        if (!$verifyResult || pg_num_rows($verifyResult) === 0) {
            error_log("RFID_UNLOCK FAILED: Invalid RFID tag");
            return errorResponse(401, 'Invalid RFID tag');
        }
        
        // Queue unlock command
        $cmdQuery = "INSERT INTO arduino_commands (user_id, command, status) 
                    VALUES ($1, 'SOL:UNLOCK', 'PENDING')";
        pg_query_params($conn, $cmdQuery, [$user_id]);
        
        // Log RFID access
        $logQuery = "INSERT INTO rfid_access_logs (user_id, rfid_tag, access_type, authorized) 
                    VALUES ($1, $2, 'UNLOCK', true)";
        pg_query_params($conn, $logQuery, [$user_id, $rfid_tag]);
        
        error_log("RFID_UNLOCK SUCCESS: solenoid unlocked");
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Solenoid unlocked via RFID - No alarm triggered',
            'authorized_access' => true
        ]);
        
    } catch (Exception $e) {
        error_log("RFID_UNLOCK ERROR: " . $e->getMessage());
        return errorResponse(500, 'RFID unlock failed: ' . $e->getMessage());
    }
}

// ============================================================================
// Helper Functions
// ============================================================================

function sendSMS($phone, $message) {
    // Integration with SMS gateway (Twilio, AWS SNS, etc.)
    // For now, log the SMS
    error_log("SMS_SENT: phone=$phone, message=$message");
    
    // TODO: Implement actual SMS sending
    // Example Twilio integration:
    // $client = new Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
    // $client->messages->create($phone, ['from' => TWILIO_PHONE, 'body' => $message]);
    
    return true; // Simulate success for now
}

function errorResponse($code, $message) {
    http_response_code($code);
    echo json_encode([
        'status' => 'ERROR',
        'message' => $message
    ]);
}

?>