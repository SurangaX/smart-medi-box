#!/usr/bin/env php
<?php
/**
 * Test Smart Medi Box Authentication Endpoints
 */

$api_url = 'https://smart-medi-box.onrender.com';

function makeRequest($url, $data) {
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data),
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    // Get HTTP response code
    if (isset($http_response_header)) {
        preg_match('{HTTP/\d+\.\d+ (\d+)}', $http_response_header[0], $matches);
        $http_code = $matches[1] ?? '000';
    } else {
        $http_code = '000';
    }
    
    return [$response, $http_code];
}

echo "=== Smart Medi Box Authentication Test ===\n\n";

// Test 1: Patient Signup
echo "Test 1: Patient Signup\n";
echo "---------------------\n";

$patient_data = [
    'name' => 'Test Patient',
    'email' => 'patient_' . time() . '@test.com',
    'password' => 'Password123!',
    'nic' => 'NIC' . time(),
    'dob' => '1990-01-15',
    'phone' => '0777123456'
];

list($response, $http_code) = makeRequest($api_url . '/index.php/api/auth/patient/signup', $patient_data);

echo "Response Code: $http_code\n";
echo "Response: " . $response . "\n\n";

// Parse response for token
$result = json_decode($response, true);
$patient_token = $result['token'] ?? null;
$patient_email = $patient_data['email'];

if ($patient_token) {
    echo "✓ Patient signup successful!\n";
    echo "  Token: " . substr($patient_token, 0, 20) . "...\n";
    echo "  Email: $patient_email\n\n";
} else {
    echo "✗ Patient signup failed!\n\n";
}

// Test 2: Doctor Signup
echo "Test 2: Doctor Signup\n";
echo "---------------------\n";

$doctor_data = [
    'name' => 'Test Doctor',
    'email' => 'doctor_' . time() . '@test.com',
    'password' => 'DocPass123!',
    'nic' => 'DOCNIC' . time(),
    'license_number' => 'LIC' . time(),
    'specialty' => 'Cardiology',
    'phone' => '0777654321'
];

list($response, $http_code) = makeRequest($api_url . '/index.php/api/auth/doctor/signup', $doctor_data);

echo "Response Code: $http_code\n";
echo "Response: " . $response . "\n\n";

$result = json_decode($response, true);
$doctor_token = $result['token'] ?? null;
$doctor_email = $doctor_data['email'];

if ($doctor_token) {
    echo "✓ Doctor signup successful!\n";
    echo "  Token: " . substr($doctor_token, 0, 20) . "...\n";
    echo "  Email: $doctor_email\n\n";
} else {
    echo "✗ Doctor signup failed!\n\n";
}

// Test 3: Patient Login
if ($patient_email) {
    echo "Test 3: Patient Login\n";
    echo "---------------------\n";
    
    $login_data = [
        'email' => $patient_email,
        'password' => $patient_data['password']
    ];
    
    list($response, $http_code) = makeRequest($api_url . '/index.php/api/auth/login', $login_data);
    
    echo "Response Code: $http_code\n";
    echo "Response: " . $response . "\n\n";
    
    $result = json_decode($response, true);
    if ($result['status'] === 'SUCCESS') {
        echo "✓ Login successful!\n";
    } else {
        echo "✗ Login failed!\n";
    }
}

echo "\n=== Test Complete ===\n";
?>
