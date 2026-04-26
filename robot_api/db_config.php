<?php
// ==================== DATABASE CONFIGURATION ====================
// Smart Medi Box System - Cloud Ready
// Supports: PostgreSQL via Neon
// Version: 1.0.1

// Suppress all error output to prevent HTML from being sent
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set custom error log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/api_debug.log');

// Check if DATABASE_URL is set (Render/Railway deployment)
if (!empty(getenv('DATABASE_URL'))) {
    $db_url = parse_url(getenv('DATABASE_URL'));
    define('DB_HOST', $db_url['host']);
    define('DB_PORT', $db_url['port'] ?? 5432);
    define('DB_NAME', ltrim($db_url['path'], '/'));
    define('DB_USER', $db_url['user']);
    define('DB_PASSWORD', $db_url['pass']);
    define('DB_SSLMODE', 'require');
} else {
    // Local/Development configuration (Neon)
    define('DB_HOST', getenv('DB_HOST') ?: 'ep-shy-mountain-achv0blk-pooler.sa-east-1.aws.neon.tech');
    define('DB_PORT', getenv('DB_PORT') ?: 5432);
    define('DB_NAME', getenv('DB_NAME') ?: 'neondb');
    define('DB_USER', getenv('DB_USER') ?: 'neondb_owner');
    define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'npg_oZ0eaCKj1zYO');
    define('DB_SSLMODE', 'require');
}

define('DB_TYPE', 'postgresql');

// PostgreSQL Connection with SSL for cloud databases
$connection_string = "host=" . DB_HOST . 
                     " port=" . DB_PORT . 
                     " dbname=" . DB_NAME . 
                     " user=" . DB_USER . 
                     " password=" . DB_PASSWORD .
                     " sslmode=" . DB_SSLMODE;

$conn = pg_connect($connection_string);

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'PostgreSQL connection failed: ' . pg_last_error(),
        'hint' => 'Check database credentials and connectivity'
    ]);
    exit();
}

pg_set_client_encoding($conn, 'UTF-8');

// Set global timezone to GMT+5:30 (Asia/Colombo)
date_default_timezone_set('Asia/Colombo');
pg_query($conn, "SET TIMEZONE TO 'Asia/Colombo'");

define('DB_CONNECTED', true);

/**
 * Push Notification Configuration
 */
// Web Push (VAPID) - For Desktop/Browser notifications
define('VAPID_PUBLIC_KEY', 'BGI1Gh5bI6T4k70t9hxd8VdWMk9-elOXjU-u9vYoNLkD8vhuhgT3XdboPQmJFU3oFXLAdEd4AkEsAvrPWFiYZgE');
define('VAPID_PRIVATE_KEY', 'zMyrGmmYPlNKOHdMS5r-Y1pHqpegQm9PHWABztiVp0s');

// FCM Server Key - For Android APK
define('FCM_SERVER_KEY', 'AIzaSyC84CWZ6doiGn11XR_LLNKql8m9HlBkHn0');

/**
 * Send push notification via Expo Push API
 * Shared utility for all API modules
 */
function sendExpoPushNotification($expoPushToken, $title, $body, $data = []) {
    if (empty($expoPushToken)) return false;

    $url = 'https://exp.host/--/api/v2/push/send';

    $payload = [
        'to' => $expoPushToken,
        'title' => $title,
        'body' => $body,
        'data' => $data,
        'sound' => 'default',
        'priority' => 'high'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $resData = json_decode($response, true);
        if (isset($resData['data']['status']) && $resData['data']['status'] === 'ok') {
            return true;
        }
        error_log("EXPO PUSH API ERROR: " . json_encode($resData));
    }

    error_log("EXPO PUSH HTTP FAILED: code=$httpCode, response=$response");
    if (curl_errno($ch)) {
        error_log("CURL ERROR: " . curl_error($ch));
    }
    curl_close($ch);
    return false;
    }

/**
* Send push notification via Firebase Cloud Messaging (FCM)
* Upgraded to HTTP v1 API using Service Account
*/
function sendFCMPushNotification($fcmToken, $title, $body, $data = []) {
    if (empty($fcmToken)) return false;

    // 1. Get Google OAuth2 Access Token
    $accessToken = getGoogleAccessToken();
    if (!$accessToken) {
        error_log("FCM PUSH FAILED: Could not generate OAuth2 token");
        return false;
    }

    $projectId = 'smart-medi-box-69d5e';
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    // 2. Format payload for HTTP v1
    // Flatten data values to strings (FCM v1 requirement)
    $formattedData = [];
    foreach ($data as $key => $val) {
        $formattedData[$key] = strval($val);
    }

    $payload = [
        'message' => [
            'token' => $fcmToken,
            'notification' => [
                'title' => $title,
                'body' => $body
            ],
            'data' => $formattedData,
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                    'click_action' => 'TOP_STORY_ACTIVITY'
                ]
            ]
        ]
    ];

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return true;
    }

    error_log("FCM v1 PUSH FAILED: code=$httpCode, response=$response");
    return false;
}

/**
 * Generate Google OAuth2 Access Token using Service Account JSON
 * Helper for FCM v1 API
 */
function getGoogleAccessToken() {
    $jsonPath = __DIR__ . '/firebase_service_account.json';
    if (!file_exists($jsonPath)) return null;

    $config = json_decode(file_get_contents($jsonPath), true);
    $privateKey = $config['private_key'];
    $clientEmail = $config['client_email'];
    $tokenUri = $config['token_uri'];

    // Header
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $headerBase64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));

    // Payload
    $now = time();
    $payload = json_encode([
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $tokenUri,
        'exp' => $now + 3600,
        'iat' => $now
    ]);
    $payloadBase64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    // Sign
    $signature = '';
    openssl_sign($headerBase64 . '.' . $payloadBase64, $signature, $privateKey, 'SHA256');
    $signatureBase64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    $jwt = $headerBase64 . '.' . $payloadBase64 . '.' . $signatureBase64;

    // Exchange for Access Token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUri);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}
?>