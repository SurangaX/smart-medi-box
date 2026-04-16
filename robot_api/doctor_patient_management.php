<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Patient/Doctor Management Module
 * ============================================================================
 * 
 * Handles:
 * - Doctor assigning patients
 * - Doctor viewing assigned patients
 * - Patient viewing assigned doctors
 * - Doctor posting articles
 * - Viewing articles
 * 
 * Endpoints:
 * - POST /api/doctor/assign-patient
 * - GET /api/doctor/patients
 * - GET /api/doctor/patients/{patient_id}
 * - POST /api/doctor/article/create
 * - GET /api/articles
 * - GET /api/articles/{article_id}
 * - PUT /api/doctor/article/{article_id}
 * - DELETE /api/doctor/article/{article_id}
 * - GET /api/patient/doctors
 * - GET /api/patient/devices
 * 
 * ============================================================================
 */

require_once 'db_config.php';

class DoctorPatientManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Authenticate user from token
     */
    private function authenticateUser($token) {
        $query = "SELECT user_id FROM auth_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $result = pg_query_params($this->db, $query, [$token]);
        
        if (!$result || pg_num_rows($result) === 0) {
            return ['status' => 'ERROR', 'message' => 'Unauthorized'];
        }
        
        $row = pg_fetch_assoc($result);
        return ['status' => 'SUCCESS', 'user_id' => $row['user_id']];
    }
    
    /**
     * Assign patient to doctor
     */
    public function assignPatientToDoctor($token, $patient_nic, $notes = '') {
        try {
            // Authenticate doctor
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') {
                return $auth;
            }
            
            $doctor_user_id = $auth['user_id'];
            
            // Verify doctor role
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = $1");
            $result = pg_execute($this->db, $stmt, [$doctor_user_id]);
            $user = pg_fetch_array($result);
            
            if ($user['role'] !== 'DOCTOR') {
                return ['status' => 'ERROR', 'message' => 'Only doctors can assign patients'];
            }
            
            // Get doctor ID
            $stmt = $this->db->prepare("SELECT id FROM doctors WHERE user_id = $1");
            $result = pg_execute($this->db, $stmt, [$doctor_user_id]);
            $doctor = pg_fetch_array($result);
            $doctor_id = $doctor['id'];
            
            // Get patient ID
            $stmt = $this->db->prepare("SELECT id FROM patients WHERE nic = $1");
            $result = pg_execute($this->db, $stmt, [$patient_nic]);
            
            if (pg_num_rows($result) === 0) {
                return ['status' => 'ERROR', 'message' => 'Patient not found'];
            }
            
            $patient = pg_fetch_array($result);
            $patient_id = $patient['id'];
            
            // Check if already assigned
            $stmt = $this->db->prepare(
                "SELECT id FROM patient_doctor_assignments WHERE patient_id = $1 AND doctor_id = $2"
            );
            $result = pg_execute($this->db, $stmt, [$patient_id, $doctor_id]);
            
            if (pg_num_rows($result) > 0) {
                return ['status' => 'ERROR', 'message' => 'Patient already assigned to this doctor'];
            }
            
            // Create assignment
            $stmt = $this->db->prepare(
                "INSERT INTO patient_doctor_assignments (patient_id, doctor_id, notes) VALUES ($1, $2, $3)"
            );
            pg_execute($this->db, $stmt, [$patient_id, $doctor_id, $notes]);
            
            return ['status' => 'SUCCESS', 'message' => 'Patient assigned successfully'];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Failed to assign patient: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get doctor's assigned patients
     */
    public function getDoctorPatients($token) {
        try {
            // Authenticate doctor
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') {
                return $auth;
            }
            
            $doctor_user_id = $auth['user_id'];
            
            // Get doctor ID
            $stmt = $this->db->prepare("SELECT id FROM doctors WHERE user_id = $1");
            $result = pg_execute($this->db, $stmt, [$doctor_user_id]);
            $doctor = pg_fetch_array($result);
            $doctor_id = $doctor['id'];
            
            // Get assigned patients
            $query = "
                SELECT 
                    p.id,
                    p.user_id,
                    p.nic,
                    p.name,
                    p.date_of_birth,
                    p.age,
                    p.gender,
                    p.blood_type,
                    p.transplanted_organ,
                    p.transplantation_date,
                    p.phone_number,
                    pda.assigned_at,
                    pda.notes
                FROM patients p
                JOIN patient_doctor_assignments pda ON p.id = pda.patient_id
                WHERE pda.doctor_id = $1 AND pda.is_active = TRUE
                ORDER BY pda.assigned_at DESC
            ";
            
            $result = pg_query_params($this->db, $query, [$doctor_id]);
            $patients = [];
            
            while ($row = pg_fetch_assoc($result)) {
                $patients[] = $row;
            }
            
            return [
                'status' => 'SUCCESS',
                'patients' => $patients,
                'count' => count($patients)
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Failed to fetch patients: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get patient's assigned doctors
     */
    public function getPatientDoctors($token) {
        try {
            // Authenticate patient
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') {
                return $auth;
            }
            
            $patient_user_id = $auth['user_id'];
            
            // Get patient ID
            $stmt = $this->db->prepare("SELECT id FROM patients WHERE user_id = $1");
            $result = pg_execute($this->db, $stmt, [$patient_user_id]);
            $patient = pg_fetch_array($result);
            $patient_id = $patient['id'];
            
            // Get assigned doctors
            $query = "
                SELECT 
                    d.id,
                    d.user_id,
                    d.name,
                    d.specialization,
                    d.hospital,
                    d.phone_number,
                    pda.assigned_at,
                    pda.notes
                FROM doctors d
                JOIN patient_doctor_assignments pda ON d.id = pda.doctor_id
                WHERE pda.patient_id = $1 AND pda.is_active = TRUE
                ORDER BY pda.assigned_at DESC
            ";
            
            $result = pg_query_params($this->db, $query, [$patient_id]);
            $doctors = [];
            
            while ($row = pg_fetch_assoc($result)) {
                $doctors[] = $row;
            }
            
            return [
                'status' => 'SUCCESS',
                'doctors' => $doctors,
                'count' => count($doctors)
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Failed to fetch doctors: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get patient's devices
     */
    public function getPatientDevices($token) {
        try {
            if (empty($token)) {
                return ['status' => 'ERROR', 'message' => 'Token required'];
            }
            
            // Authenticate patient
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') {
                return $auth;
            }
            
            $user_id = $auth['user_id'];
            
            // Get patient devices from device_registry
            $query = "
                SELECT 
                    device_id,
                    device_name,
                    mac_address,
                    device_type,
                    status,
                    created_at
                FROM device_registry
                WHERE user_id = $1
                ORDER BY created_at DESC
            ";
            
            $result = pg_query_params($this->db, $query, [$user_id]);
            if (!$result) {
                error_log("Device query error: " . pg_last_error($this->db));
                return ['status' => 'ERROR', 'message' => 'Database query failed'];
            }
            
            $devices = [];
            while ($row = pg_fetch_assoc($result)) {
                $devices[] = [
                    'device_id' => $row['device_id'],
                    'device_name' => $row['device_name'] ?? 'Smart Medi Box',
                    'mac_address' => $row['mac_address'],
                    'device_type' => $row['device_type'],
                    'status' => $row['status'] ?? 'ACTIVE',
                    'created_at' => $row['created_at']
                ];
            }
            
            return [
                'status' => 'SUCCESS',
                'devices' => $devices,
                'count' => count($devices)
            ];
        } catch (Exception $e) {
            error_log("getPatientDevices exception: " . $e->getMessage());
            return ['status' => 'ERROR', 'message' => 'Failed to fetch devices'];
        }
    }
    
    /**
     * Get patient details (for doctor)
     */
    public function getPatientDetails($token, $patient_id) {
        try {
            // Authenticate doctor
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') {
                return $auth;
            }
            
            $doctor_user_id = $auth['user_id'];
            
            // Get doctor ID
            $stmt = $this->db->prepare("SELECT id FROM doctors WHERE user_id = $1");
            $result = pg_execute($this->db, $stmt, [$doctor_user_id]);
            $doctor = pg_fetch_array($result);
            $doctor_id = $doctor['id'];
            
            // Check if patient is assigned to doctor
            $stmt = $this->db->prepare(
                "SELECT id FROM patient_doctor_assignments WHERE patient_id = $1 AND doctor_id = $2 AND is_active = TRUE"
            );
            $result = pg_execute($this->db, $stmt, [$patient_id, $doctor_id]);
            
            if (pg_num_rows($result) === 0) {
                return ['status' => 'ERROR', 'message' => 'Access denied'];
            }
            
            // Get patient details
            $stmt = $this->db->prepare("SELECT * FROM patients WHERE id = $1");
            $result = pg_execute($this->db, $stmt, [$patient_id]);
            
            if (pg_num_rows($result) === 0) {
                return ['status' => 'ERROR', 'message' => 'Patient not found'];
            }
            
            $patient = pg_fetch_assoc($result);
            
            // Get patient's devices
            $stmt = $this->db->prepare(
                "SELECT device_id, device_name, mac_address, status, last_sync FROM device_registry WHERE user_id = $1"
            );
            $result = pg_execute($this->db, $stmt, [$patient['user_id']]);
            $devices = [];
            
            while ($row = pg_fetch_assoc($result)) {
                $devices[] = $row;
            }
            
            // Get latest temperature readings
            $stmt = $this->db->prepare(
                "SELECT internal_temp, external_humidity, timestamp FROM temperature_logs WHERE user_id = $1 ORDER BY timestamp DESC LIMIT 10"
            );
            $result = pg_execute($this->db, $stmt, [$patient['user_id']]);
            $temps = [];
            
            while ($row = pg_fetch_assoc($result)) {
                $temps[] = $row;
            }
            
            return [
                'status' => 'SUCCESS',
                'patient' => $patient,
                'devices' => $devices,
                'temperature_readings' => $temps
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Failed to fetch patient details: ' . $e->getMessage()];
        }
    }
}

class ArticleManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Authenticate user from token
     */
    private function authenticateUser($token) {
        $stmt = $this->db->prepare(
            "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP"
        );
        $result = pg_execute($this->db, $stmt, [$token]);
        
        if (pg_num_rows($result) === 0) {
            return ['status' => 'ERROR', 'message' => 'Unauthorized'];
        }
        
        $row = pg_fetch_array($result);
        return ['status' => 'SUCCESS', 'user_id' => $row['user_id']];
    }
    
    /**
     * Create article (doctor only)
     */
    public function createArticle($token, $title, $content, $summary = '', $category = '') {
        try {
            // Authenticate doctor
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') {
                return $auth;
            }
            
            $doctor_user_id = $auth['user_id'];
            
            // Verify doctor role
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = $1");
            $result = pg_execute($this->db, $stmt, [$doctor_user_id]);
            $user = pg_fetch_array($result);
            
            if ($user['role'] !== 'DOCTOR') {
                return ['status' => 'ERROR', 'message' => 'Only doctors can create articles'];
            }
            
            // Get doctor ID
            $stmt = $this->db->prepare("SELECT id FROM doctors WHERE user_id = $1");
            $result = pg_execute($this->db, $stmt, [$doctor_user_id]);
            $doctor = pg_fetch_array($result);
            $doctor_id = $doctor['id'];
            
            // Create article
            $stmt = $this->db->prepare(
                "INSERT INTO articles (doctor_id, title, content, summary, category) VALUES ($1, $2, $3, $4, $5) RETURNING id"
            );
            $result = pg_execute($this->db, $stmt, [$doctor_id, $title, $content, $summary, $category]);
            $article = pg_fetch_array($result);
            
            return [
                'status' => 'SUCCESS',
                'message' => 'Article created successfully',
                'article_id' => $article['id']
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Failed to create article: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all published articles
     */
    public function getAllArticles($limit = 20, $offset = 0) {
        try {
            $query = "
                SELECT 
                    a.id,
                    a.title,
                    a.summary,
                    a.category,
                    a.view_count,
                    a.created_at,
                    a.updated_at,
                    d.name as doctor_name,
                    d.specialization
                FROM articles a
                JOIN doctors d ON a.doctor_id = d.id
                WHERE a.is_published = TRUE
                ORDER BY a.created_at DESC
                LIMIT $1 OFFSET $2
            ";
            
            $result = pg_query_params($this->db, $query, [$limit, $offset]);
            $articles = [];
            
            while ($row = pg_fetch_assoc($result)) {
                $articles[] = $row;
            }
            
            // Get total count
            $count_query = "SELECT COUNT(*) as total FROM articles WHERE is_published = TRUE";
            $count_result = pg_query($this->db, $count_query);
            $count = pg_fetch_assoc($count_result)['total'];
            
            return [
                'status' => 'SUCCESS',
                'articles' => $articles,
                'total' => $count,
                'limit' => $limit,
                'offset' => $offset
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Failed to fetch articles: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get single article with full content
     */
    public function getArticle($article_id) {
        try {
            $stmt = $this->db->prepare(
                "SELECT a.*, d.name as doctor_name, d.specialization FROM articles a JOIN doctors d ON a.doctor_id = d.id WHERE a.id = $1 AND a.is_published = TRUE"
            );
            $result = pg_execute($this->db, $stmt, [$article_id]);
            
            if (pg_num_rows($result) === 0) {
                return ['status' => 'ERROR', 'message' => 'Article not found'];
            }
            
            $article = pg_fetch_assoc($result);
            
            // Increment view count
            $stmt = $this->db->prepare("UPDATE articles SET view_count = view_count + 1 WHERE id = $1");
            pg_execute($this->db, $stmt, [$article_id]);
            
            return [
                'status' => 'SUCCESS',
                'article' => $article
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Failed to fetch article: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update article (doctor only)
     */
    public function updateArticle($token, $article_id, $title, $content, $summary = '', $category = '') {
        try {
            // Authenticate doctor
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') {
                return $auth;
            }
            
            $doctor_user_id = $auth['user_id'];
            
            // Get doctor ID
            $stmt = $this->db->prepare("SELECT id FROM doctors WHERE user_id = $1");
            $result = pg_execute($this->db, $stmt, [$doctor_user_id]);
            $doctor = pg_fetch_array($result);
            $doctor_id = $doctor['id'];
            
            // Check if article belongs to doctor
            $stmt = $this->db->prepare("SELECT id FROM articles WHERE id = $1 AND doctor_id = $2");
            $result = pg_execute($this->db, $stmt, [$article_id, $doctor_id]);
            
            if (pg_num_rows($result) === 0) {
                return ['status' => 'ERROR', 'message' => 'Access denied'];
            }
            
            // Update article
            $stmt = $this->db->prepare(
                "UPDATE articles SET title = $1, content = $2, summary = $3, category = $4 WHERE id = $5"
            );
            pg_execute($this->db, $stmt, [$title, $content, $summary, $category, $article_id]);
            
            return ['status' => 'SUCCESS', 'message' => 'Article updated successfully'];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Failed to update article: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete article (doctor only)
     */
    public function deleteArticle($token, $article_id) {
        try {
            // Authenticate doctor
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') {
                return $auth;
            }
            
            $doctor_user_id = $auth['user_id'];
            
            // Get doctor ID
            $stmt = $this->db->prepare("SELECT id FROM doctors WHERE user_id = $1");
            $result = pg_execute($this->db, $stmt, [$doctor_user_id]);
            $doctor = pg_fetch_array($result);
            $doctor_id = $doctor['id'];
            
            // Check if article belongs to doctor
            $stmt = $this->db->prepare("SELECT id FROM articles WHERE id = $1 AND doctor_id = $2");
            $result = pg_execute($this->db, $stmt, [$article_id, $doctor_id]);
            
            if (pg_num_rows($result) === 0) {
                return ['status' => 'ERROR', 'message' => 'Access denied'];
            }
            
            // Delete article
            $stmt = $this->db->prepare("DELETE FROM articles WHERE id = $1");
            pg_execute($this->db, $stmt, [$article_id]);
            
            return ['status' => 'SUCCESS', 'message' => 'Article deleted successfully'];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Failed to delete article: ' . $e->getMessage()];
        }
    }
}

// ==================== ROUTER ====================

$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $dpm = new DoctorPatientManager($db);
    $am = new ArticleManager($db);
    
    switch ($action) {
        case 'doctor/assign-patient':
            $token = $input['token'] ?? '';
            $patient_nic = $input['patient_nic'] ?? '';
            $notes = $input['notes'] ?? '';
            echo json_encode($dpm->assignPatientToDoctor($token, $patient_nic, $notes));
            break;
            
        case 'doctor/patients':
            $token = $input['token'] ?? '';
            echo json_encode($dpm->getDoctorPatients($token));
            break;
            
        case 'patient/doctors':
            $token = $input['token'] ?? '';
            echo json_encode($dpm->getPatientDoctors($token));
            break;
            
        case 'patient/devices':
            $token = $input['token'] ?? '';
            echo json_encode($dpm->getPatientDevices($token));
            break;
            
        case 'article/create':
            $token = $input['token'] ?? '';
            $title = $input['title'] ?? '';
            $content = $input['content'] ?? '';
            $summary = $input['summary'] ?? '';
            $category = $input['category'] ?? '';
            echo json_encode($am->createArticle($token, $title, $content, $summary, $category));
            break;
            
        case 'article/update':
            $token = $input['token'] ?? '';
            $article_id = $input['article_id'] ?? '';
            $title = $input['title'] ?? '';
            $content = $input['content'] ?? '';
            $summary = $input['summary'] ?? '';
            $category = $input['category'] ?? '';
            echo json_encode($am->updateArticle($token, $article_id, $title, $content, $summary, $category));
            break;
            
        case 'article/delete':
            $token = $input['token'] ?? '';
            $article_id = $input['article_id'] ?? '';
            echo json_encode($am->deleteArticle($token, $article_id));
            break;
            
        case 'doctor/patient-details':
            $token = $input['token'] ?? '';
            $patient_id = $input['patient_id'] ?? '';
            echo json_encode($dpm->getPatientDetails($token, $patient_id));
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'Unknown action']);
    }
} else if ($method === 'GET') {
    $am = new ArticleManager($db);
    
    switch ($action) {
        case 'articles':
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
            echo json_encode($am->getAllArticles($limit, $offset));
            break;
            
        case 'article':
            $article_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            echo json_encode($am->getArticle($article_id));
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'Unknown action']);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid method']);
}
