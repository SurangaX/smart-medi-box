<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Report Downloader
 * ============================================================================
 */

require_once 'db_config.php';

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/index.php', '', $path);
$parts = explode('/', trim($path, '/'));

// Expected format: api/report/download/{id}
if (count($parts) < 4 || $parts[0] !== 'api' || $parts[1] !== 'report' || $parts[2] !== 'download') {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$report_id = intval($parts[3]);

if (!$report_id) {
    http_response_code(400);
    echo '400 Bad Request: Invalid report_id';
    exit;
}

try {
    $query = "SELECT file_data, file_mime, file_name FROM patient_reports WHERE id = $1";
    $result = pg_query_params($conn, $query, [$report_id]);

    if (!$result || pg_num_rows($result) === 0) {
        http_response_code(404);
        echo 'Report not found';
        exit;
    }

    $row = pg_fetch_assoc($result);
    $data = pg_unescape_bytea($row['file_data']);
    $mime = $row['file_mime'] ?: 'application/octet-stream';
    $name = $row['file_name'] ?: 'report_' . $report_id;

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: private, max-age=86400');

    echo $data;
} catch (Exception $e) {
    http_response_code(500);
    echo 'Internal Server Error: ' . $e->getMessage();
}
