<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Device Management API (PostgreSQL Compatible)
 * ============================================================================
 */

require_once 'db_config.php';

// Turn off error displaying to prevent corrupting JSON output
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Get action from router
if (!isset($action) || empty($action)) {
    $action = $_GET['action'] ?? '';
    if (!$action) {
        $request_uri = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
        $action = $request_uri[2] ?? '';
    }
}

// Function to output pure JSON and exit
function sendJSON($data) {
    if (ob_get_length()) ob_clean(); // Clear any accidental output/logs
    echo json_encode($data);
    exit;
}

switch ($action) {
    case 'register': handleRegisterDevice($method); break;
    case 'update-status': handleUpdateDeviceStatus($method); break;
    case 'complete-command': handleCompleteCommand($method); break;
    case 'heartbeat': handleDeviceHeartbeat($method); break;
    case 'check-commands': handleCheckCommands($method); break;
    case 'trigger-manual': handleManualTrigger($method); break;
    case 'med-taken': handleMedicineTaken($method); break; // New auto-approve endpoint
    default:
        http_response_code(404);
        sendJSON(['status' => 'ERROR', 'message' => 'Endpoint not found', 'received_action' => $action]);
        break;
}

/**
 * Handle medicine taken event from device
 */
function handleMedicineTaken($method) {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $mac = trim($input['mac_address'] ?? '');
    if (!$mac) { sendJSON(['status' => 'ERROR', 'message' => 'mac_address required']); }
    
    try {
        // Find the user mapped to this device
        $q = "SELECT dum.user_id 
              FROM devices d 
              JOIN device_user_map dum ON d.id = dum.device_id 
              WHERE UPPER(TRIM(d.mac_address)) = UPPER($1) 
              LIMIT 1";
        $res = pg_query_params($conn, $q, array($mac));
        
        if ($res && pg_num_rows($res) > 0) {
            $user_id = pg_fetch_result($res, 0, 0);
            
            // Find all active (not dismissed) notifications for today for this user
            $notifQ = "SELECT id, schedule_id FROM notifications 
                       WHERE user_id = $1 
                       AND is_dismissed = false 
                       AND created_at >= CURRENT_DATE";
            $notifRes = pg_query_params($conn, $notifQ, array($user_id));
            
            $completed_count = 0;
            if ($notifRes && pg_num_rows($notifRes) > 0) {
                while ($row = pg_fetch_assoc($notifRes)) {
                    $notif_id = $row['id'];
                    $sched_id = $row['schedule_id'];
                    
                    if ($sched_id) {
                        // Mark the associated schedule as completed
                        $updateSched = "UPDATE schedules SET 
                                        is_completed = (CASE WHEN is_recurring = false THEN true ELSE false END), 
                                        completed_at = (CASE WHEN is_recurring = false THEN NOW() ELSE completed_at END),
                                        updated_at = NOW()
                                        WHERE id = $1";
                        pg_query_params($conn, $updateSched, array($sched_id));
                        
                        // Log the action
                        pg_query_params($conn, "INSERT INTO schedule_logs (user_id, schedule_id, action) VALUES ($1, $2, 'COMPLETED_VIA_DOOR')", array($user_id, $sched_id));
                    }
                    
                    // Dismiss the notification
                    pg_query_params($conn, "UPDATE notifications SET is_dismissed = true, updated_at = NOW() WHERE id = $1", array($notif_id));
                    $completed_count++;
                }
            }
            
            sendJSON(['status' => 'SUCCESS', 'completed_notifications' => $completed_count]);
        } else {
            sendJSON(['status' => 'ERROR', 'message' => 'MAC not paired']);
        }
    } catch (Exception $e) {
        sendJSON(['status' => 'ERROR', 'message' => $e->getMessage()]);
    }
}

function handleManualTrigger($method) {
    global $conn;
    $mac = $_GET['mac_address'] ?? '';
    $type = $_GET['type'] ?? 'BUZZ:ON';
    if (!$mac) { sendJSON(['status' => 'ERROR', 'message' => 'mac_address required']); }
    
    try {
        $q = "SELECT dum.user_id FROM devices d JOIN device_user_map dum ON d.id = dum.device_id WHERE UPPER(TRIM(d.mac_address)) = UPPER($1)";
        $res = pg_query_params($conn, $q, array($mac));
        if ($res && pg_num_rows($res) > 0) {
            $user_id = pg_fetch_result($res, 0, 0);
            pg_query_params($conn, "INSERT INTO arduino_commands (user_id, command, status) VALUES ($1, $2, 'PENDING')", array($user_id, $type));
            sendJSON(['status' => 'SUCCESS', 'message' => 'Manual command queued: ' . $type]);
        } else {
            sendJSON(['status' => 'ERROR', 'message' => 'MAC not paired']);
        }
    } catch (Exception $e) { sendJSON(['status' => 'ERROR', 'message' => $e->getMessage()]); }
}

function handleCheckCommands($method) {
    global $conn;
    $mac = trim($_GET['mac_address'] ?? '');
    if (!$mac) { sendJSON(['status' => 'ERROR', 'message' => 'mac_address required']); }
    
    try {
        $q = "SELECT dum.user_id FROM devices d JOIN device_user_map dum ON d.id = dum.device_id WHERE UPPER(TRIM(d.mac_address)) = UPPER($1)";
        $res = pg_query_params($conn, $q, array($mac));
        
        if ($res && pg_num_rows($res) > 0) {
            $user_id = pg_fetch_result($res, 0, 0);
            $cmdRes = pg_query_params($conn, "SELECT id, command FROM arduino_commands WHERE user_id = $1 AND status = 'PENDING' ORDER BY created_at ASC", array($user_id));
            
            $cmds = [];
            while ($r = pg_fetch_assoc($cmdRes)) {
                $cmds[] = ['id' => (int)$r['id'], 'command' => $r['command']];
            }
            sendJSON(['status' => 'SUCCESS', 'commands' => $cmds]);
        } else {
            sendJSON(['status' => 'SUCCESS', 'commands' => []]);
        }
    } catch (Exception $e) { sendJSON(['status' => 'ERROR', 'message' => 'DB Error']); }
}

function handleRegisterDevice($method) {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $mac = trim($input['mac_address'] ?? $_POST['mac_address'] ?? '');
    if (!$mac) { sendJSON(['status' => 'ERROR', 'message' => 'MAC required']); }

    try {
        $q = "SELECT d.device_id, u.name as user_name 
              FROM devices d
              JOIN device_user_map dum ON d.id = dum.device_id
              JOIN users u ON dum.user_id = u.id
              WHERE UPPER(TRIM(d.mac_address)) = UPPER($1)";
        $res = pg_query_params($conn, $q, array($mac));
        if ($res && pg_num_rows($res) > 0) {
            $data = pg_fetch_assoc($res);
            sendJSON(['status' => 'SUCCESS', 'user_name' => $data['user_name'], 'device_id' => $data['device_id']]);
        } else {
            sendJSON(['status' => 'UNPAIRED']);
        }
    } catch (Exception $e) { sendJSON(['status' => 'ERROR']); }
}

function handleUpdateDeviceStatus($method) {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $mac = trim($input['mac_address'] ?? '');
    if (!$mac) { return; }

    try {
        $q = "SELECT d.id, dum.user_id FROM devices d LEFT JOIN device_user_map dum ON d.id = dum.device_id WHERE UPPER(TRIM(d.mac_address)) = UPPER($1)";
        $res = pg_query_params($conn, $q, array($mac));
        if ($res && pg_num_rows($res) > 0) {
            $data = pg_fetch_assoc($res);
            $user_id = $data['user_id'];
            if ($user_id && isset($input['temperature'])) {
                pg_query_params($conn, "INSERT INTO temperature_logs (user_id, internal_temp, external_humidity, timestamp) VALUES ($1, $2, $3, NOW())", 
                    array($user_id, $input['temperature'], $input['humidity']));
            }
            pg_query_params($conn, "UPDATE devices SET last_sync = NOW() WHERE id = $1", array($data['id']));
            sendJSON(['status' => 'SUCCESS']);
        }
    } catch (Exception $e) { }
}

function handleCompleteCommand($method) {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $input['command_id'] ?? null;
    if ($id) {
        // Use EXECUTED instead of COMPLETED based on database enum check
        pg_query_params($conn, "UPDATE arduino_commands SET status = 'EXECUTED' WHERE id = $1", array($id));
        sendJSON(['status' => 'SUCCESS']);
    }
}

function handleDeviceHeartbeat($method) { sendJSON(['status' => 'SUCCESS']); }
function handleSyncDevice($method) { handleUpdateDeviceStatus($method); }
function handleListDevices($method) { echo json_encode(['status' => 'SUCCESS', 'message' => 'Deprecated']); }
?>
