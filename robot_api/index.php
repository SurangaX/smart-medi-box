<?php
/**
 * ============================================================================
 * SMART MEDI BOX - API Router
 * ============================================================================
 * 
 * Main entry point for all API requests
 * Routes requests to appropriate handlers
 * 
 * NEW Authentication Endpoints:
 * - POST /api/auth/login - Login with email/password
 * - POST /api/auth/patient/signup - Patient registration
 * - POST /api/auth/doctor/signup - Doctor registration
 * 
 * Legacy Endpoints (QR-based):
 * - POST /api/auth/verify - User authentication via MAC
 * - POST /api/auth/register - New user registration via MAC
 * - POST /api/auth/qr-generate - Generate QR token
 * - GET /api/auth/mac-lookup - Find user by MAC address
 * 
 * Schedule Endpoints:
 * - /api/schedule/get - Fetch schedules
 * - /api/schedule/create - Create new schedule
 * 
 * Temperature Endpoints:
 * - /api/temp/current - Get current temperature
 * - /api/temp/set-target - Set target temperature
 * - /api/temp/control - Cooling system control
 * 
 * ============================================================================
 */

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set default content type
header('Content-Type: application/json');

// Parse the request URL - use PATH_INFO which doesn't include script name
// PATH_INFO = /api/auth/patient/signup (without the /index.php part)
$request_path = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove leading/trailing slashes and split
$request_path = trim($request_path, '/');
$request_parts = explode('/', $request_path);

// Remove 'index.php' if it's the first part (shouldn't be with PATH_INFO, but be safe)
if (isset($request_parts[0]) && $request_parts[0] === 'index.php') {
    array_shift($request_parts);
}

// Expected format: /api/{module}/{action}
// Example: /api/auth/verify
if (count($request_parts) < 3 || $request_parts[0] !== 'api') {
    http_response_code(400);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'Invalid API request',
        'hint' => 'Use format: /api/{module}/{action}',
        'received_path' => $_SERVER['PATH_INFO'] ?? 'none',
        'debug' => true
    ]);
    exit();
}

$module = $request_parts[1];
$action = isset($request_parts[2]) ? $request_parts[2] : '';
$subaction = isset($request_parts[3]) ? $request_parts[3] : '';

// Route to appropriate module
switch ($module) {
    case 'auth':
        // All auth endpoints (login, signup, QR-based verify, etc.)
        // The auth.php module handles routing to specific handlers
        $_GET['action'] = $action;
        $_GET['module'] = 'auth';
        require 'auth.php';
        break;
    
    case 'doctor':
        // Doctor management
        $_GET['action'] = $action . '/' . $subaction;
        $_GET['module'] = 'doctor';
        require 'doctor_patient_management.php';
        break;
    
    case 'patient':
        // Patient management
        $_GET['action'] = $action . '/' . $subaction;
        $_GET['module'] = 'patient';
        require 'doctor_patient_management.php';
        break;
    
    case 'articles':
        // Article management
        $_GET['action'] = $action;
        if (isset($_GET['id'])) $_GET['id'] = $_GET['id'];
        require 'doctor_patient_management.php';
        break;
    
    case 'schedule':
        // Include schedule module
        $_GET['action'] = $action;
        $_GET['module'] = 'schedule';
        require 'schedule.php';
        break;
    
    case 'temperature':
    case 'temp':
        // Include temperature module
        $_GET['action'] = $action;
        $_GET['module'] = 'temperature';
        require 'temperature.php';
        break;
    
    case 'user':
        // Include user management module
        $_GET['action'] = $action;
        $_GET['module'] = 'user';
        require 'user.php';
        break;
    
    case 'device':
        // Include device management module
        $_GET['action'] = $action;
        $_GET['module'] = 'device';
        require 'device.php';
        break;
    
    case 'status':
        // System status endpoint
        handleSystemStatus();
        break;
    
    case 'docs':
        // API documentation
        handleAPIDocs();
        break;
    
    default:
        http_response_code(404);
        echo json_encode([
            'status' => 'ERROR',
            'message' => 'Unknown API module: ' . $module,
            'available_modules' => ['auth', 'schedule', 'temperature', 'user', 'device', 'status']
        ]);
        break;
}

// ============================================================================
// SYSTEM STATUS ENDPOINT
// ============================================================================
function handleSystemStatus() {
    echo json_encode([
        'status' => 'OK',
        'service' => 'Smart Medi Box API',
        'version' => '2.0.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoints' => [
            'auth' => '/api/auth/{action}',
            'schedule' => '/api/schedule/{action}',
            'temperature' => '/api/temperature/{action}',
            'user' => '/api/user/{action}',
            'device' => '/api/device/{action}'
        ]
    ]);
}

// ============================================================================
// API DOCUMENTATION
// ============================================================================
function handleAPIDocs() {
    $docs = [
        'service' => 'Smart Medi Box API v2.0',
        'description' => 'QR-based authentication system for medicine scheduling with Arduino integration',
        'modules' => [
            'auth' => [
                'verify' => [
                    'method' => 'GET/POST',
                    'description' => 'Verify existing user',
                    'parameters' => ['user_id', 'mac', 'token (optional)'],
                    'example' => '/api/auth/verify?user_id=USER_123&mac=AA:BB:CC:DD:EE:FF'
                ],
                'register' => [
                    'method' => 'POST',
                    'description' => 'Register new user',
                    'parameters' => ['name', 'age', 'phone', 'mac'],
                    'example' => '/api/auth/register'
                ],
                'qr-generate' => [
                    'method' => 'POST',
                    'description' => 'Generate QR code token',
                    'parameters' => ['user_id', 'device_id'],
                    'example' => '/api/auth/qr-generate'
                ],
                'mac-lookup' => [
                    'method' => 'GET',
                    'description' => 'Find user by MAC address',
                    'parameters' => ['mac'],
                    'example' => '/api/auth/mac-lookup?mac=AA:BB:CC:DD:EE:FF'
                ]
            ],
            'schedule' => [
                'get' => [
                    'method' => 'GET',
                    'description' => 'Fetch user schedules',
                    'parameters' => ['user_id'],
                    'example' => '/api/schedule/get?user_id=USER_123'
                ],
                'create' => [
                    'method' => 'POST',
                    'description' => 'Create new schedule',
                    'parameters' => ['user_id', 'type', 'hour', 'minute', 'description (optional)'],
                    'types' => ['MEDICINE', 'FOOD', 'BLOOD_CHECK'],
                    'example' => '/api/schedule/create'
                ],
                'complete' => [
                    'method' => 'POST',
                    'description' => 'Mark schedule as completed',
                    'parameters' => ['schedule_id', 'user_id'],
                    'example' => '/api/schedule/complete'
                ],
                'today' => [
                    'method' => 'GET',
                    'description' => "Get today's schedules",
                    'parameters' => ['user_id'],
                    'example' => '/api/schedule/today?user_id=USER_123'
                ]
            ],
            'temperature' => [
                'current' => [
                    'method' => 'GET',
                    'description' => 'Get current temperature',
                    'parameters' => ['user_id OR device_id'],
                    'example' => '/api/temperature/current?user_id=USER_123'
                ],
                'set-target' => [
                    'method' => 'POST',
                    'description' => 'Set target temperature (2-8°C)',
                    'parameters' => ['user_id', 'target_temp'],
                    'example' => '/api/temperature/set-target'
                ],
                'history' => [
                    'method' => 'GET',
                    'description' => 'Get temperature history',
                    'parameters' => ['user_id', 'days (default: 7)'],
                    'example' => '/api/temperature/history?user_id=USER_123&days=7'
                ],
                'control' => [
                    'method' => 'POST',
                    'description' => 'Control cooling system',
                    'parameters' => ['user_id', 'action (ON/OFF/AUTO)'],
                    'example' => '/api/temperature/control'
                ]
            ]
        ],
        'phone_format' => 'Use format: +94XXXXXXXXX or 0XXXXXXXXX (will auto-convert to +941234567890)',
        'response_format' => 'JSON with status, message, and data fields'
    ];
    
    echo json_encode($docs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
?>
