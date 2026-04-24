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
    case 'status': handleGetDeviceStatus($method); break; // New fetch status endpoint
    case 'complete-command': handleCompleteCommand($method); break;
    case 'heartbeat': handleDeviceHeartbeat($method); break;
    case 'check-commands': handleCheckCommands($method); break;
    case 'trigger-manual': handleManualTrigger($method); break;
    case 'med-taken': handleMedicineTaken($method); break;
    default:
        http_response_code(404);
        sendJSON(['status' => 'ERROR', 'message' => 'Endpoint not found', 'received_action' => $action]);
        break;
}

/**
 * Fetch current real-time status of a device for dashboard
 */
function handleGetDeviceStatus($method) {
    global $conn;
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) { sendJSON(['status' => 'ERROR', 'message' => 'user_id required']); }
    
    try {
        $query = "SELECT d.door_state, d.lock_state, d.alarm_state, d.last_sync, d.status 
                  FROM devices d
                  JOIN device_user_map dum ON d.id = dum.device_id
                  WHERE dum.user_id = (SELECT id FROM users WHERE user_id = $1 OR CAST(id AS TEXT) = $1 LIMIT 1)";
        
        $result = pg_query_params($conn, $query, array($user_id));
        
        if ($result && pg_num_rows($result) > 0) {
            $data = pg_fetch_assoc($result);
            sendJSON(['status' => 'SUCCESS', 'device_status' => [
                'door' => $data['door_state'],
                'solenoid' => $data['lock_state'],
                'alarm' => $data['alarm_state'],
                'last_sync' => $data['last_sync'],
                'online_status' => $data['status']
            ]]);
        } else {
            sendJSON(['status' => 'ERROR', 'message' => 'Device not found for user']);
        }
    } catch (Exception $e) { sendJSON(['status' => 'ERROR', 'message' => $e->getMessage()]); }
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
            $db_id = $data['id'];
            $user_id = $data['user_id'];
            
            // Map states from device to DB strings
            $door = isset($input['door_open']) ? ($input['door_open'] ? 'OPEN' : 'CLOSED') : 'CLOSED';
            $lock = isset($input['lock_open']) ? ($input['lock_open'] ? 'UNLOCKED' : 'LOCKED') : 'LOCKED';
            $alarm = isset($input['alarm_active']) ? ($input['alarm_active'] ? 'ACTIVE' : 'INACTIVE') : 'INACTIVE';
            
            // Update sensor logs if user is mapped
            if ($user_id && isset($input['temperature'])) {
                pg_query_params($conn, "INSERT INTO temperature_logs (user_id, internal_temp, external_humidity, timestamp) VALUES ($1, $2, $3, NOW())", 
                    array($user_id, $input['temperature'], $input['humidity']));
            }
            
            // Update device with new real-time columns
            $upQuery = "UPDATE devices SET 
                        last_sync = NOW(), 
                        status = 'ACTIVE',
                        door_state = $1,
                        lock_state = $2,
                        alarm_state = $3
                        WHERE id = $4";
            pg_query_params($conn, $upQuery, array($door, $lock, $alarm, $db_id));
            
            sendJSON(['status' => 'SUCCESS']);
        }
    } catch (Exception $e) { }
}

function handleMedicineTaken($method) {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $mac = trim($input['mac_address'] ?? '');
    if (!$mac) { sendJSON(['status' => 'ERROR', 'message' => 'mac_address required']); }
    
    try {
        $q = "SELECT dum.user_id FROM devices d JOIN device_user_map dum ON d.id = dum.device_id WHERE UPPER(TRIM(d.mac_address)) = UPPER($1) LIMIT 1";
        $res = pg_query_params($conn, $q, array($mac));
        if ($res && pg_num_rows($res) > 0) {
            $user_id = pg_fetch_result($res, 0, 0);
            $notifRes = pg_query_params($conn, "SELECT id, schedule_id FROM notifications WHERE user_id = $1 AND is_dismissed = false AND created_at >= CURRENT_DATE", array($user_id));
            if ($notifRes && pg_num_rows($notifRes) > 0) {
                while ($row = pg_fetch_assoc($notifRes)) {
                    $notif_id = $row['id']; $sched_id = $row['schedule_id'];
                    if ($sched_id) {
                        pg_query_params($conn, "UPDATE schedules SET is_completed = (CASE WHEN is_recurring = false THEN true ELSE false END), completed_at = NOW() WHERE id = $1", array($sched_id));
                        pg_query_params($conn, "INSERT INTO schedule_logs (user_id, schedule_id, action) VALUES ($1, $2, 'COMPLETED_VIA_DOOR')", array($user_id, $sched_id));
                    }
                    pg_query_params($conn, "UPDATE notifications SET is_dismissed = true, updated_at = NOW() WHERE id = $1", array($notif_id));
                }
            }
            sendJSON(['status' => 'SUCCESS']);
        }
    } catch (Exception $e) { sendJSON(['status' => 'ERROR']); }
}

function handleManualTrigger($method) {
    global $conn;
    $mac = $_GET['mac_address'] ?? '';
    $type = $_GET['type'] ?? 'BUZZ:ON';
    try {
        $q = "SELECT dum.user_id FROM devices d JOIN device_user_map dum ON d.id = dum.device_id WHERE UPPER(TRIM(d.mac_address)) = UPPER($1)";
        $res = pg_query_params($conn, $q, array($mac));
        if ($res && pg_num_rows($res) > 0) {
            $user_id = pg_fetch_result($res, 0, 0);
            pg_query_params($conn, "INSERT INTO arduino_commands (user_id, command, status) VALUES ($1, $2, 'PENDING')", array($user_id, $type));
            sendJSON(['status' => 'SUCCESS']);
        }
    } catch (Exception $e) { sendJSON(['status' => 'ERROR']); }
}

function handleCheckCommands($method) {
    global $conn;
    $mac = trim($_GET['mac_address'] ?? '');
    try {
        $q = "SELECT dum.user_id FROM devices d JOIN device_user_map dum ON d.id = dum.device_id WHERE UPPER(TRIM(d.mac_address)) = UPPER($1)";
        $res = pg_query_params($conn, $q, array($mac));
        if ($res && pg_num_rows($res) > 0) {
            $user_id = pg_fetch_result($res, 0, 0);
            $cmdRes = pg_query_params($conn, "SELECT id, command FROM arduino_commands WHERE user_id = $1 AND status = 'PENDING' ORDER BY created_at ASC", array($user_id));
            $cmds = [];
            while ($r = pg_fetch_assoc($cmdRes)) { $cmds[] = ['id' => (int)$r['id'], 'command' => $r['command']]; }
            sendJSON(['status' => 'SUCCESS', 'commands' => $cmds]);
        } else { sendJSON(['status' => 'SUCCESS', 'commands' => []]); }
    } catch (Exception $e) { sendJSON(['status' => 'ERROR']); }
}

function handleRegisterDevice($method) {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $mac = trim($input['mac_address'] ?? $_POST['mac_address'] ?? '');
    try {
        $q = "SELECT d.device_id, u.name as user_name FROM devices d JOIN device_user_map dum ON d.id = dum.device_id JOIN users u ON dum.user_id = u.id WHERE UPPER(TRIM(d.mac_address)) = UPPER($1)";
        $res = pg_query_params($conn, $q, array($mac));
        if ($res && pg_num_rows($res) > 0) {
            $data = pg_fetch_assoc($res);
            sendJSON(['status' => 'SUCCESS', 'user_name' => $data['user_name'], 'device_id' => $data['device_id']]);
        } else { sendJSON(['status' => 'UNPAIRED']); }
    } catch (Exception $e) { sendJSON(['status' => 'ERROR']); }
}

function handleCompleteCommand($method) {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $input['command_id'] ?? null;
    if ($id) {
        pg_query_params($conn, "UPDATE arduino_commands SET status = 'EXECUTED' WHERE id = $1", array($id));
        sendJSON(['status' => 'SUCCESS']);
    }
}

function handleDeviceHeartbeat($method) { sendJSON(['status' => 'SUCCESS']); }
function handleSyncDevice($method) { handleUpdateDeviceStatus($method); }
function handleListDevices($method) { echo json_encode(['status' => 'SUCCESS', 'message' => 'Deprecated']); }
?>
