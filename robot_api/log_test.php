<?php
$log_path = ini_get('error_log');
echo "Error log path: " . $log_path . "\n";
error_log("TEST LOG ENTRY - " . date('Y-m-d H:i:s'));
?>
