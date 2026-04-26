<?php
require_once 'db_config.php';
header('Content-Type: application/json');

try {
    $user_id = 51;
    
    // 1. Check notifications for this user from last 12 hours
    $notif_res = pg_query_params($conn, 
        "SELECT id, schedule_id, is_dismissed, message, created_at 
         FROM notifications 
         WHERE user_id = $1 AND created_at >= NOW() - INTERVAL '12 hours'
         ORDER BY created_at DESC", 
        array($user_id)
    );
    $notifs = [];
    while ($row = pg_fetch_assoc($notif_res)) { $notifs[] = $row; }

    // 2. Check schedules
    $sched_res = pg_query_params($conn,
        "SELECT id, medicine_name, is_completed, is_recurring, completed_at 
         FROM schedules 
         WHERE user_id = $1",
        array($user_id)
    );
    $scheds = [];
    while ($row = pg_fetch_assoc($sched_res)) { $scheds[] = $row; }

    // 3. Check logs
    $log_res = pg_query_params($conn,
        "SELECT * FROM schedule_logs WHERE user_id = $1 ORDER BY created_at DESC LIMIT 5",
        array($user_id)
    );
    $logs = [];
    while ($row = pg_fetch_assoc($log_res)) { $logs[] = $row; }

    echo json_encode([
        'status' => 'SUCCESS',
        'notifications' => $notifs,
        'schedules' => $scheds,
        'logs' => $logs,
        'server_time' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
}
?>
