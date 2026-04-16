<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Authentication Module
 * ============================================================================
 * 
 * Handles:
 * - Patient signup
 * - Doctor signup
 * - User login
 * - Token validation
 * - Password management
 * 
 * Endpoints:
 * - POST /api/auth/patient/signup
 * - POST /api/auth/doctor/signup
 * - POST /api/auth/login
 * - POST /api/auth/logout
 * - POST /api/auth/validate-token
 * - POST /api/auth/refresh-token
 * 
 * ============================================================================
 */

require_once 'db_config.php';

class AuthHandler {
    private $db;
    private $token_expiry = 86400 * 7; // 7 days
    private $pairing_token_expiry = 3600; // 1 hour
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Patient Signup
     * Required fields:
     * - email
     * - password
     * - name
     * - nic
     * - date_of_birth (YYYY-MM-DD)
     * - gender (MALE, FEMALE, OTHER)
     * - blood_type (A+, A-, B+, B-, AB+, AB-, O+, O-)
     * - phone_number
     * - transplanted_organ (optional)
     * - transplantation_date (optional, YYYY-MM-DD)
     */
    public function patientSignup($data) {
        try {
            // Validate required fields
            $required = ['email', 'password', 'name', 'nic', 'date_of_birth', 'gender', 'blood_type', 'phone_number'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['status' => 'ERROR', 'message' => "Missing required field: $field"];
                }
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['status' => 'ERROR', 'message' => 'Invalid email format'];
            }
            
            // Validate password strength
            if (strlen($data['password']) < 8) {
                return ['status' => 'ERROR', 'message' => 'Password must be at least 8 characters'];
            }
            
            // Validate date of birth
            $dob = DateTime::createFromFormat('Y-m-d', $data['date_of_birth']);
            if (!$dob) {
                return ['status' => 'ERROR', 'message' => 'Invalid date of birth format (use YYYY-MM-DD)'];
            }
            
            // Check if patient already exists
            $stmt = $this->db->prepare("SELECT id FROM patients WHERE nic = $1 OR email IN (SELECT email FROM users WHERE email = $2)");
            $result = pg_execute($this->db, $stmt, [$data['nic'], $data['email']]);
            
            if (pg_num_rows($result) > 0) {
                return ['status' => 'ERROR', 'message' => 'Patient with this NIC or email already exists'];
            }
            
            // Hash password
            $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
            
            // Start transaction
            pg_query($this->db, "BEGIN");
            
            try {
                // Create user account
                $stmt = $this->db->prepare(
                    "INSERT INTO users (email, password_hash, role) VALUES ($1, $2, $3) RETURNING id"
                );
                $result = pg_execute($this->db, $stmt, [$data['email'], $password_hash, 'PATIENT']);
                $user_row = pg_fetch_array($result);
                $user_id = $user_row['id'];
                
                // Create patient profile
                $transplanted_organ = $data['transplanted_organ'] ?? 'NONE';
                $transplantation_date = !empty($data['transplantation_date']) ? $data['transplantation_date'] : null;
                $emergency_contact = $data['emergency_contact'] ?? null;
                $medical_history = $data['medical_history'] ?? null;
                
                $stmt = $this->db->prepare(
                    "INSERT INTO patients (user_id, nic, name, date_of_birth, gender, blood_type, transplanted_organ, transplantation_date, phone_number, emergency_contact, medical_history) 
                     VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)"
                );
                pg_execute($this->db, $stmt, [
                    $user_id,
                    $data['nic'],
                    $data['name'],
                    $data['date_of_birth'],
                    $data['gender'],
                    $data['blood_type'],
                    $transplanted_organ,
                    $transplantation_date,
                    $data['phone_number'],
                    $emergency_contact,
                    $medical_history
                ]);
                
                // Generate session token
                $token = $this->generateToken();
                $expires_at = date('Y-m-d H:i:s', time() + $this->token_expiry);
                
                $stmt = $this->db->prepare(
                    "INSERT INTO session_tokens (user_id, token, expires_at) VALUES ($1, $2, $3)"
                );
                pg_execute($this->db, $stmt, [$user_id, $token, $expires_at]);
                
                // Commit transaction
                pg_query($this->db, "COMMIT");
                
                return [
                    'status' => 'SUCCESS',
                    'message' => 'Patient account created successfully',
                    'token' => $token,
                    'user_id' => $user_id,
                    'role' => 'PATIENT'
                ];
            } catch (Exception $e) {
                pg_query($this->db, "ROLLBACK");
                throw $e;
            }
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Doctor Signup
     * Required fields:
     * - email
     * - password
     * - name
     * - nic
     * - date_of_birth (YYYY-MM-DD)
     * - specialization
     * - hospital
     * - license_number
     * - phone_number
     */
    public function doctorSignup($data) {
        try {
            // Validate required fields
            $required = ['email', 'password', 'name', 'nic', 'date_of_birth', 'specialization', 'hospital', 'license_number', 'phone_number'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['status' => 'ERROR', 'message' => "Missing required field: $field"];
                }
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['status' => 'ERROR', 'message' => 'Invalid email format'];
            }
            
            // Validate password strength
            if (strlen($data['password']) < 8) {
                return ['status' => 'ERROR', 'message' => 'Password must be at least 8 characters'];
            }
            
            // Validate date of birth
            $dob = DateTime::createFromFormat('Y-m-d', $data['date_of_birth']);
            if (!$dob) {
                return ['status' => 'ERROR', 'message' => 'Invalid date of birth format (use YYYY-MM-DD)'];
            }
            
            // Check if doctor already exists
            $stmt = $this->db->prepare("SELECT id FROM doctors WHERE nic = $1 OR license_number = $2 OR email IN (SELECT email FROM users WHERE email = $3)");
            $result = pg_execute($this->db, $stmt, [$data['nic'], $data['license_number'], $data['email']]);
            
            if (pg_num_rows($result) > 0) {
                return ['status' => 'ERROR', 'message' => 'Doctor with this NIC, license number, or email already exists'];
            }
            
            // Hash password
            $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
            
            // Start transaction
            pg_query($this->db, "BEGIN");
            
            try {
                // Create user account
                $stmt = $this->db->prepare(
                    "INSERT INTO users (email, password_hash, role) VALUES ($1, $2, $3) RETURNING id"
                );
                $result = pg_execute($this->db, $stmt, [$data['email'], $password_hash, 'DOCTOR']);
                $user_row = pg_fetch_array($result);
                $user_id = $user_row['id'];
                
                // Create doctor profile
                $stmt = $this->db->prepare(
                    "INSERT INTO doctors (user_id, nic, name, date_of_birth, specialization, hospital, license_number, phone_number) 
                     VALUES ($1, $2, $3, $4, $5, $6, $7, $8)"
                );
                pg_execute($this->db, $stmt, [
                    $user_id,
                    $data['nic'],
                    $data['name'],
                    $data['date_of_birth'],
                    $data['specialization'],
                    $data['hospital'],
                    $data['license_number'],
                    $data['phone_number']
                ]);
                
                // Generate session token
                $token = $this->generateToken();
                $expires_at = date('Y-m-d H:i:s', time() + $this->token_expiry);
                
                $stmt = $this->db->prepare(
                    "INSERT INTO session_tokens (user_id, token, expires_at) VALUES ($1, $2, $3)"
                );
                pg_execute($this->db, $stmt, [$user_id, $token, $expires_at]);
                
                // Commit transaction
                pg_query($this->db, "COMMIT");
                
                return [
                    'status' => 'SUCCESS',
                    'message' => 'Doctor account created successfully',
                    'token' => $token,
                    'user_id' => $user_id,
                    'role' => 'DOCTOR',
                    'verification_pending' => true
                ];
            } catch (Exception $e) {
                pg_query($this->db, "ROLLBACK");
                throw $e;
            }
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * User Login
     * Required fields:
     * - email_or_nic (can be either email or NIC)
     * - password
     * OR
     * - email
     * - password
     * OR
     * - nic
     * - password
     */
    public function login($data) {
        try {
            $login_identifier = null;
            
            // Support multiple input formats
            if (!empty($data['email_or_nic'])) {
                $login_identifier = $data['email_or_nic'];
            } elseif (!empty($data['email'])) {
                $login_identifier = $data['email'];
            } elseif (!empty($data['nic'])) {
                $login_identifier = $data['nic'];
            }
            
            if (empty($login_identifier) || empty($data['password'])) {
                return ['status' => 'ERROR', 'message' => 'Email/NIC and password are required'];
            }
            
            // Get user by email first
            $stmt = $this->db->prepare("SELECT id, password_hash, role FROM users WHERE email = $1 AND deleted_at IS NULL");
            $result = pg_execute($this->db, $stmt, [$login_identifier]);
            $user = pg_fetch_array($result);
            
            // If not found by email, try by NIC (search in patients and doctors tables)
            if (!$user) {
                // Check if it's a patient
                $stmt = $this->db->prepare(
                    "SELECT u.id, u.password_hash, u.role FROM users u 
                     JOIN patients p ON u.id = p.user_id 
                     WHERE p.nic = $1 AND u.deleted_at IS NULL"
                );
                $result = pg_execute($this->db, $stmt, [$login_identifier]);
                $user = pg_fetch_array($result);
            }
            
            // If still not found, check if it's a doctor
            if (!$user) {
                $stmt = $this->db->prepare(
                    "SELECT u.id, u.password_hash, u.role FROM users u 
                     JOIN doctors d ON u.id = d.user_id 
                     WHERE d.nic = $1 AND u.deleted_at IS NULL"
                );
                $result = pg_execute($this->db, $stmt, [$login_identifier]);
                $user = pg_fetch_array($result);
            }
            
            if (!$user) {
                return ['status' => 'ERROR', 'message' => 'Invalid email/NIC or password'];
            }
            
            // Verify password
            if (!password_verify($data['password'], $user['password_hash'])) {
                return ['status' => 'ERROR', 'message' => 'Invalid email/NIC or password'];
            }
            
            // Update last login
            $stmt = $this->db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = $1");
            pg_execute($this->db, $stmt, [$user['id']]);
            
            // Generate session token
            $token = $this->generateToken();
            $expires_at = date('Y-m-d H:i:s', time() + $this->token_expiry);
            
            $stmt = $this->db->prepare(
                "INSERT INTO session_tokens (user_id, token, expires_at) VALUES ($1, $2, $3)"
            );
            pg_execute($this->db, $stmt, [$user['id'], $token, $expires_at]);
            
            // Get user profile info
            $profile = [];
            if ($user['role'] === 'PATIENT') {
                $stmt = $this->db->prepare("SELECT id, nic, name FROM patients WHERE user_id = $1");
                $result = pg_execute($this->db, $stmt, [$user['id']]);
                $profile = pg_fetch_array($result);
            } else if ($user['role'] === 'DOCTOR') {
                $stmt = $this->db->prepare("SELECT id, nic, name, is_verified FROM doctors WHERE user_id = $1");
                $result = pg_execute($this->db, $stmt, [$user['id']]);
                $profile = pg_fetch_array($result);
            }
            
            return [
                'status' => 'SUCCESS',
                'message' => 'Login successful',
                'token' => $token,
                'user_id' => $user['id'],
                'role' => $user['role'],
                'profile' => $profile
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate Token
     */
    public function validateToken($token) {
        try {
            $stmt = $this->db->prepare(
                "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP"
            );
            $result = pg_execute($this->db, $stmt, [$token]);
            
            if (pg_num_rows($result) === 0) {
                return ['status' => 'ERROR', 'message' => 'Invalid or expired token'];
            }
            
            $row = pg_fetch_array($result);
            
            // Get user info
            $stmt = $this->db->prepare("SELECT id, email, role FROM users WHERE id = $1");
            $result = pg_execute($this->db, $stmt, [$row['user_id']]);
            $user = pg_fetch_array($result);
            
            return [
                'status' => 'SUCCESS',
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Token validation failed'];
        }
    }
    
    /**
     * Logout
     */
    public function logout($token) {
        try {
            $stmt = $this->db->prepare("DELETE FROM session_tokens WHERE token = $1");
            pg_execute($this->db, $stmt, [$token]);
            
            return ['status' => 'SUCCESS', 'message' => 'Logged out successfully'];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Logout failed'];
        }
    }
    
    /**
     * Generate Device Pairing Token
     */
    public function generatePairingToken($user_id) {
        try {
            // Get patient ID
            $stmt = $this->db->prepare("SELECT id FROM patients WHERE user_id = $1");
            $result = pg_execute($this->db, $stmt, [$user_id]);
            
            if (pg_num_rows($result) === 0) {
                return ['status' => 'ERROR', 'message' => 'Patient not found'];
            }
            
            $patient = pg_fetch_array($result);
            
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + $this->pairing_token_expiry);
            
            $stmt = $this->db->prepare(
                "INSERT INTO pairing_tokens (patient_id, token, expires_at) VALUES ($1, $2, $3) RETURNING token"
            );
            $result = pg_execute($this->db, $stmt, [$patient['id'], $token, $expires_at]);
            
            return [
                'status' => 'SUCCESS',
                'pairing_token' => $token,
                'expires_in' => $this->pairing_token_expiry
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Failed to generate pairing token'];
        }
    }
    
    /**
     * Complete Device Pairing
     */
    public function completeDevicePairing($pairing_token, $mac_address, $device_name) {
        try {
            // Validate pairing token
            $stmt = $this->db->prepare(
                "SELECT patient_id FROM pairing_tokens WHERE token = $1 AND is_used = FALSE AND expires_at > CURRENT_TIMESTAMP"
            );
            $result = pg_execute($this->db, $stmt, [$pairing_token]);
            
            if (pg_num_rows($result) === 0) {
                return ['status' => 'ERROR', 'message' => 'Invalid or expired pairing token'];
            }
            
            $token_row = pg_fetch_array($result);
            $patient_id = $token_row['patient_id'];
            
            // Get user_id from patient
            $stmt = $this->db->prepare("SELECT user_id FROM patients WHERE id = $1");
            $result = pg_execute($this->db, $stmt, [$patient_id]);
            $patient = pg_fetch_array($result);
            $user_id = $patient['user_id'];
            
            // Start transaction
            pg_query($this->db, "BEGIN");
            
            try {
                // Mark pairing token as used
                $stmt = $this->db->prepare(
                    "UPDATE pairing_tokens SET is_used = TRUE, device_mac_address = $1, device_name = $2 WHERE token = $3"
                );
                pg_execute($this->db, $stmt, [$mac_address, $device_name, $pairing_token]);
                
                // Register device
                $device_id = "DEVICE-" . bin2hex(random_bytes(8));
                $stmt = $this->db->prepare(
                    "INSERT INTO device_registry (device_id, user_id, device_name, mac_address, device_type) VALUES ($1, $2, $3, $4, $5)"
                );
                pg_execute($this->db, $stmt, [$device_id, $user_id, $device_name, $mac_address, 'SMART_BOX']);
                
                // Commit transaction
                pg_query($this->db, "COMMIT");
                
                return [
                    'status' => 'SUCCESS',
                    'message' => 'Device paired successfully',
                    'device_id' => $device_id,
                    'mac_address' => $mac_address
                ];
            } catch (Exception $e) {
                pg_query($this->db, "ROLLBACK");
                throw $e;
            }
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Device pairing failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Helper: Generate secure token
     */
    private function generateToken() {
        return bin2hex(random_bytes(32));
    }
}

// ==================== ROUTER ====================

$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

$auth = new AuthHandler($db);

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'patient/signup':
            echo json_encode($auth->patientSignup($input));
            break;
            
        case 'doctor/signup':
            echo json_encode($auth->doctorSignup($input));
            break;
            
        case 'login':
            echo json_encode($auth->login($input));
            break;
            
        case 'logout':
            $token = $input['token'] ?? '';
            echo json_encode($auth->logout($token));
            break;
            
        case 'validate-token':
            $token = $input['token'] ?? '';
            echo json_encode($auth->validateToken($token));
            break;
            
        case 'generate-pairing-token':
            $token = $input['token'] ?? '';
            $validation = $auth->validateToken($token);
            if ($validation['status'] === 'SUCCESS') {
                echo json_encode($auth->generatePairingToken($validation['user_id']));
            } else {
                echo json_encode(['status' => 'ERROR', 'message' => 'Unauthorized']);
            }
            break;
            
        case 'complete-pairing':
            $pairing_token = $input['pairing_token'] ?? '';
            $mac_address = $input['mac_address'] ?? '';
            $device_name = $input['device_name'] ?? '';
            echo json_encode($auth->completeDevicePairing($pairing_token, $mac_address, $device_name));
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'Unknown action']);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'ERROR', 'message' => 'POST method required']);
}
