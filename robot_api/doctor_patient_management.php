<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Patient/Doctor Management Module
 * ============================================================================
 */

require_once 'db_config.php';

// Enable CORS for all requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
header('Access-Control-Max-Age: 86400');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

class DoctorPatientManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    private function authenticateUser($token) {
        $query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $result = pg_query_params($this->db, $query, [$token]);

        if (!$result || pg_num_rows($result) === 0) {
            return ['status' => 'ERROR', 'message' => 'Unauthorized'];
        }

        $row = pg_fetch_assoc($result);
        return ['status' => 'SUCCESS', 'user_id' => $row['user_id']];
    }
    
    public function assignPatientToDoctor($token, $patient_nic, $notes = '') {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            
            $doctor_user_id = $auth['user_id'];
            
            $result = pg_query_params($this->db, "SELECT role FROM users WHERE id = $1", [$doctor_user_id]);
            $user = pg_fetch_array($result);
            if (!$user || $user['role'] !== 'DOCTOR') return ['status' => 'ERROR', 'message' => 'Unauthorized'];
            
            $result = pg_query_params($this->db, "SELECT id FROM doctors WHERE user_id = $1", [$doctor_user_id]);
            $doctor = pg_fetch_array($result);
            $doctor_id = $doctor['id'];
            
            $result = pg_query_params($this->db, "SELECT id FROM patients WHERE nic = $1", [$patient_nic]);
            if (!$result || pg_num_rows($result) === 0) return ['status' => 'ERROR', 'message' => 'Patient not found'];
            $patient = pg_fetch_array($result);
            $patient_id = $patient['id'];
            
            $result = pg_query_params($this->db, "SELECT id FROM patient_doctor_assignments WHERE patient_id = $1 AND doctor_id = $2 AND is_active = TRUE", [$patient_id, $doctor_id]);
            if ($result && pg_num_rows($result) > 0) return ['status' => 'ERROR', 'message' => 'Already assigned'];
            
            // Re-activate if exists but inactive, or insert new
            $check_exists = pg_query_params($this->db, "SELECT id FROM patient_doctor_assignments WHERE patient_id = $1 AND doctor_id = $2", [$patient_id, $doctor_id]);
            if (pg_num_rows($check_exists) > 0) {
                pg_query_params($this->db, "UPDATE patient_doctor_assignments SET is_active = TRUE, notes = $3 WHERE patient_id = $1 AND doctor_id = $2", [$patient_id, $doctor_id, $notes]);
            } else {
                pg_query_params($this->db, "INSERT INTO patient_doctor_assignments (patient_id, doctor_id, notes) VALUES ($1, $2, $3)", [$patient_id, $doctor_id, $notes]);
            }
            return ['status' => 'SUCCESS', 'message' => 'Assigned'];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    public function unassignPatient($token, $patient_id) {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            
            $result = pg_query_params($this->db, "SELECT id FROM doctors WHERE user_id = $1", [$auth['user_id']]);
            $doctor = pg_fetch_array($result);
            
            pg_query_params($this->db, "UPDATE patient_doctor_assignments SET is_active = FALSE WHERE patient_id = $1 AND doctor_id = $2", [$patient_id, $doctor['id']]);
            return ['status' => 'SUCCESS', 'message' => 'Unassigned'];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }
    
    public function getDoctorPatients($token) {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            $user_id = $auth['user_id'];

            $result = pg_query_params($this->db, "SELECT id FROM doctors WHERE user_id = $1", [$user_id]);
            $doctor = pg_fetch_array($result);

            $query = "SELECT p.*, pda.assigned_at, pda.notes, 
                      (SELECT COUNT(*) FROM messages WHERE sender_id = p.user_id AND receiver_id = $2 AND is_read = FALSE) as unread_count
                      FROM patients p JOIN patient_doctor_assignments pda ON p.id = pda.patient_id 
                      WHERE pda.doctor_id = $1 AND pda.is_active = TRUE ORDER BY pda.assigned_at DESC";
            $result = pg_query_params($this->db, $query, [$doctor['id'], $user_id]);
            $patients = [];
            while ($row = pg_fetch_assoc($result)) {
                $row['unread_count'] = (int)$row['unread_count'];
                $patients[] = $row;
            }
            return ['status' => 'SUCCESS', 'patients' => $patients];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    public function getPatientDoctors($token) {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            $user_id = $auth['user_id'];

            $result = pg_query_params($this->db, "SELECT id FROM patients WHERE user_id = $1", [$user_id]);
            $patient = pg_fetch_array($result);

            $query = "SELECT d.*, pda.assigned_at, pda.notes, 
                      (SELECT COUNT(*) FROM messages WHERE sender_id = d.user_id AND receiver_id = $2 AND is_read = FALSE) as unread_count
                      FROM doctors d JOIN patient_doctor_assignments pda ON d.id = pda.doctor_id 
                      WHERE pda.patient_id = $1 AND pda.is_active = TRUE ORDER BY pda.assigned_at DESC";
            $result = pg_query_params($this->db, $query, [$patient['id'], $user_id]);
            $doctors = [];
            while ($row = pg_fetch_assoc($result)) {
                $row['unread_count'] = (int)$row['unread_count'];
                $doctors[] = $row;
            }
            return ['status' => 'SUCCESS', 'doctors' => $doctors];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }
    public function searchPatients($token, $query = '') {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            
            if (empty($query)) {
                $q = "SELECT id, user_id, nic, name, profile_photo FROM patients ORDER BY name ASC LIMIT 50";
                $result = pg_query($this->db, $q);
            } else {
                $q = "SELECT id, user_id, nic, name, profile_photo FROM patients WHERE name ILIKE $1 OR nic ILIKE $1 ORDER BY name ASC LIMIT 50";
                $result = pg_query_params($this->db, $q, ["%$query%"]);
            }
            
            $patients = [];
            while ($row = pg_fetch_assoc($result)) $patients[] = $row;
            return ['status' => 'SUCCESS', 'patients' => $patients];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    public function getPatientSchedules($token, $patient_id) {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            
            $result = pg_query_params($this->db, "SELECT user_id, name FROM patients WHERE id = $1", [$patient_id]);
            $patient_info = pg_fetch_assoc($result);
            if (!$patient_info) return ['status' => 'ERROR', 'message' => 'Patient not found'];

            // Robust query:
            // 1. Filter by ACTIVE status to hide deleted ones
            // 2. For one-time schedules (is_recurring=false), use the persistent is_completed column
            // 3. For recurring schedules, check if completed TODAY via logs
            $query = "SELECT 
                        s.*,
                        (CASE 
                            WHEN s.is_recurring = FALSE THEN s.is_completed
                            ELSE (SELECT COUNT(*) > 0 FROM schedule_logs sl 
                                  WHERE sl.schedule_id = s.id AND sl.action = 'COMPLETED' 
                                  AND DATE(sl.created_at) = CURRENT_DATE)
                         END) as calculated_completed
                      FROM schedules s 
                      WHERE s.user_id = $1 AND s.status = 'ACTIVE'
                      ORDER BY s.schedule_date DESC, s.hour DESC, s.minute DESC";
            
            $result = pg_query_params($this->db, $query, [$patient_info['user_id']]);
            $schedules = [];
            while ($row = pg_fetch_assoc($result)) {
                // Ensure is_completed reflects our calculation
                $row['is_completed'] = ($row['calculated_completed'] === 't' || $row['calculated_completed'] === true);
                $schedules[] = $row;
            }
            return ['status' => 'SUCCESS', 'patient_name' => $patient_info['name'], 'schedules' => $schedules];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    public function getPatientDevices($token) {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            $query = "SELECT d.*, dum.assigned_at FROM devices d JOIN device_user_map dum ON dum.device_id = d.id WHERE dum.user_id = $1 ORDER BY dum.assigned_at DESC";
            $result = pg_query_params($this->db, $query, [$auth['user_id']]);
            $devices = [];
            while ($row = pg_fetch_assoc($result)) $devices[] = $row;
            return ['status' => 'SUCCESS', 'devices' => $devices];
        } catch (Exception $e) { return ['status' => 'ERROR', 'message' => $e->getMessage()]; }
    }

    public function unpairDevice($token, $device_id) {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            $result = pg_query_params($this->db, "SELECT id FROM devices WHERE device_id = $1", [$device_id]);
            $device = pg_fetch_assoc($result);
            pg_query_params($this->db, "DELETE FROM device_user_map WHERE device_id = $1 AND user_id = $2", [$device['id'], $auth['user_id']]);
            return ['status' => 'SUCCESS', 'message' => 'Unpaired'];
        } catch (Exception $e) { return ['status' => 'ERROR', 'message' => $e->getMessage()]; }
    }

    public function uploadReport($token, $patient_id, $title, $notes) {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            $doctor_user_id = $auth['user_id'];

            // Get the patient's user_id from their patient_id
            $res = pg_query_params($this->db, "SELECT user_id FROM patients WHERE id = $1", [$patient_id]);
            if (!$res || pg_num_rows($res) === 0) return ['status' => 'ERROR', 'message' => 'Patient not found'];
            $patient_user_id = pg_fetch_assoc($res)['user_id'];

            // Handle file upload
            $file_data = null;
            $file_mime = null;
            $file_name = null;

            if (isset($_FILES['report_file']) && is_uploaded_file($_FILES['report_file']['tmp_name'])) {
                $fileTmp = $_FILES['report_file']['tmp_name'];
                $file_name = $_FILES['report_file']['name'];
                $file_mime = $_FILES['report_file']['type'];
                $file_data = file_get_contents($fileTmp);
            }

            if (!$file_data) return ['status' => 'ERROR', 'message' => 'No file uploaded'];

            $query = "INSERT INTO patient_reports (patient_id, doctor_id, title, notes, file_data, file_mime, file_name) 
                      VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id";
            // Use pg_escape_bytea for the binary data parameter
            $result = pg_query_params($this->db, $query, [$patient_user_id, $doctor_user_id, $title, $notes, pg_escape_bytea($file_data), $file_mime, $file_name]);
            
            if (!$result) throw new Exception(pg_last_error($this->db));

            // Add notification for patient (using patient_user_id)
            $notif_msg = "Your doctor has uploaded a new medical report: " . $title;
            pg_query_params($this->db, "INSERT INTO notifications (user_id, type, message) VALUES ($1, $2, $3)", [$patient_user_id, 'NEW_REPORT', $notif_msg]);

            return ['status' => 'SUCCESS', 'message' => 'Report uploaded successfully'];
        } catch (Exception $e) { 
            error_log("UPLOAD REPORT ERROR: " . $e->getMessage());
            return ['status' => 'ERROR', 'message' => $e->getMessage()]; 
        }
    }

    public function getPatientReports($token, $patient_id = null) {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            $user_id = $auth['user_id'];

            // If patient_id is provided, check if the doctor is assigned to this patient
            if ($patient_id) {
                // Get doctor's ID from doctors table
                $res = pg_query_params($this->db, "SELECT id FROM doctors WHERE user_id = $1", [$user_id]);
                if (!$res || pg_num_rows($res) === 0) return ['status' => 'ERROR', 'message' => 'Doctor profile not found'];
                $doctor_id = pg_fetch_assoc($res)['id'];

                // Check assignment
                $check = pg_query_params($this->db, "SELECT 1 FROM patient_doctor_assignments WHERE doctor_id = $1 AND patient_id = $2", [$doctor_id, $patient_id]);
                if (!$check || pg_num_rows($check) === 0) return ['status' => 'ERROR', 'message' => 'Unauthorized access to patient reports'];
                
                // Get patient's user_id
                $res = pg_query_params($this->db, "SELECT user_id FROM patients WHERE id = $1", [$patient_id]);
                $patient_user_id = pg_fetch_assoc($res)['user_id'];

                $query = "SELECT id, title, notes, file_name, file_mime, created_at FROM patient_reports WHERE patient_id = $1 ORDER BY created_at DESC";
                $result = pg_query_params($this->db, $query, [$patient_user_id]);
            } else {
                // For patient calling their own reports (user_id is users.id)
                $query = "SELECT id, title, notes, file_name, file_mime, created_at FROM patient_reports WHERE patient_id = $1 ORDER BY created_at DESC";
                $result = pg_query_params($this->db, $query, [$user_id]);
            }

            $reports = [];
            if ($result) {
                while ($row = pg_fetch_assoc($result)) $reports[] = $row;
            }
            return ['status' => 'SUCCESS', 'reports' => $reports];
        } catch (Exception $e) { return ['status' => 'ERROR', 'message' => $e->getMessage()]; }
    }
}

class ChatManager {
    private $db;
    public function __construct($db) { $this->db = $db; }
    private function authenticateUser($token) {
        $query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $result = pg_query_params($this->db, $query, [$token]);
        if (!$result || pg_num_rows($result) === 0) return ['status' => 'ERROR', 'message' => 'Unauthorized'];
        $row = pg_fetch_assoc($result);
        return ['status' => 'SUCCESS', 'user_id' => $row['user_id']];
    }
    public function sendMessage($token, $receiver_user_id, $message) {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            
            // Get sender's name for the notification
            $sender_id = $auth['user_id'];
            $sender_res = pg_query_params($this->db, "SELECT name FROM users WHERE id = $1", [$sender_id]);
            $sender_name = "Someone";
            if ($sender_res && pg_num_rows($sender_res) > 0) {
                $sender_name = pg_fetch_assoc($sender_res)['name'];
            }

            // Insert message
            pg_query_params($this->db, "INSERT INTO messages (sender_id, receiver_id, message) VALUES ($1, $2, $3)", [$sender_id, $receiver_user_id, $message]);
            
            // Add notification for receiver
            $notif_msg = "New message from " . $sender_name . ": " . (strlen($message) > 50 ? substr($message, 0, 47) . "..." : $message);
            pg_query_params($this->db, "INSERT INTO notifications (user_id, type, message) VALUES ($1, $2, $3)", [$receiver_user_id, 'NEW_MESSAGE', $notif_msg]);

            return ['status' => 'SUCCESS', 'message' => 'Sent'];
        } catch (Exception $e) { return ['status' => 'ERROR', 'message' => $e->getMessage()]; }
    }
    public function getMessages($token, $other_user_id) {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            $user_id = $auth['user_id'];
            $query = "SELECT * FROM messages WHERE (sender_id = $1 AND receiver_id = $2) OR (sender_id = $2 AND receiver_id = $1) ORDER BY created_at ASC";
            $result = pg_query_params($this->db, $query, [$user_id, $other_user_id]);
            $messages = [];
            while ($row = pg_fetch_assoc($result)) $messages[] = $row;
            pg_query_params($this->db, "UPDATE messages SET is_read = TRUE WHERE receiver_id = $1 AND sender_id = $2 AND is_read = FALSE", [$user_id, $other_user_id]);
            return ['status' => 'SUCCESS', 'messages' => $messages];
        } catch (Exception $e) { return ['status' => 'ERROR', 'message' => $e->getMessage()]; }
    }
}

class ArticleManager {
    private $db;
    public function __construct($db) { $this->db = $db; }
    private function authenticateUser($token) {
        $result = pg_query_params($this->db, "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP", [$token]);
        if (!$result || pg_num_rows($result) === 0) return ['status' => 'ERROR', 'message' => 'Unauthorized'];
        $row = pg_fetch_array($result);
        return ['status' => 'SUCCESS', 'user_id' => $row['user_id']];
    }
    public function createArticle($token, $title, $content, $summary = '', $category = '') {
        try {
            $auth = $this->authenticateUser($token);
            if ($auth['status'] !== 'SUCCESS') return $auth;
            $result = pg_query_params($this->db, "SELECT id FROM doctors WHERE user_id = $1", [$auth['user_id']]);
            $doctor = pg_fetch_array($result);
            $result = pg_query_params($this->db, "INSERT INTO articles (doctor_id, title, content, summary, category) VALUES ($1, $2, $3, $4, $5) RETURNING id", [$doctor['id'], $title, $content, $summary, $category]);
            $article = pg_fetch_array($result);
            return ['status' => 'SUCCESS', 'article_id' => $article['id']];
        } catch (Exception $e) { return ['status' => 'ERROR', 'message' => $e->getMessage()]; }
    }
    public function getAllArticles($limit = 20, $offset = 0) {
        try {
            $query = "SELECT a.*, d.name as doctor_name, d.specialization FROM articles a JOIN doctors d ON a.doctor_id = d.id WHERE a.is_published = TRUE ORDER BY a.created_at DESC LIMIT $1 OFFSET $2";
            $result = pg_query_params($this->db, $query, [$limit, $offset]);
            $articles = [];
            while ($row = pg_fetch_assoc($result)) $articles[] = $row;
            return ['status' => 'SUCCESS', 'articles' => $articles];
        } catch (Exception $e) { return ['status' => 'ERROR', 'message' => $e->getMessage()]; }
    }
}

// ==================== ROUTER ====================
$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];
try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $dpm = new DoctorPatientManager($conn);
        $am = new ArticleManager($conn);
        $cm = new ChatManager($conn);
        switch ($action) {
            case 'doctor/search-patients': echo json_encode($dpm->searchPatients($input['token'] ?? '', $input['query'] ?? '')); break;
            case 'doctor/patient-schedules': echo json_encode($dpm->getPatientSchedules($input['token'] ?? '', $input['patient_id'] ?? '')); break;
            case 'doctor/unassign-patient': echo json_encode($dpm->unassignPatient($input['token'] ?? '', $input['patient_id'] ?? '')); break;
            case 'chat/send': echo json_encode($cm->sendMessage($input['token'] ?? '', $input['receiver_id'] ?? '', $input['message'] ?? '')); break;
            case 'chat/messages': echo json_encode($cm->getMessages($input['token'] ?? '', $input['other_user_id'] ?? '')); break;
            case 'doctor/assign-patient': echo json_encode($dpm->assignPatientToDoctor($input['token'] ?? '', $input['patient_nic'] ?? '', $input['notes'] ?? '')); break;
            case 'doctor/patients': echo json_encode($dpm->getDoctorPatients($input['token'] ?? '')); break;
            case 'patient/doctors': echo json_encode($dpm->getPatientDoctors($input['token'] ?? '')); break;
            case 'patient/devices': echo json_encode($dpm->getPatientDevices($input['token'] ?? '')); break;
            case 'patient/unpair-device': echo json_encode($dpm->unpairDevice($input['token'] ?? '', $input['device_id'] ?? '')); break;
            case 'article/create': echo json_encode($am->createArticle($input['token'] ?? '', $input['title'] ?? '', $input['content'] ?? '', $input['summary'] ?? '', $input['category'] ?? '')); break;
            
            case 'doctor/upload-report':
                // Handle multipart form data
                $token = $_POST['token'] ?? '';
                $patient_id = $_POST['patient_id'] ?? '';
                $title = $_POST['title'] ?? '';
                $notes = $_POST['notes'] ?? '';
                echo json_encode($dpm->uploadReport($token, $patient_id, $title, $notes));
                break;
            case 'doctor/patient-reports': echo json_encode($dpm->getPatientReports($input['token'] ?? '', $input['patient_id'] ?? '')); break;
            case 'patient/my-reports': echo json_encode($dpm->getPatientReports($input['token'] ?? '')); break;

            default: http_response_code(404); echo json_encode(['status' => 'ERROR', 'message' => 'Unknown action: ' . $action]);
        }
    } else if ($method === 'GET') {
        $am = new ArticleManager($conn);
        switch ($action) {
            case 'articles': echo json_encode($am->getAllArticles(isset($_GET['limit']) ? intval($_GET['limit']) : 20, isset($_GET['offset']) ? intval($_GET['offset']) : 0)); break;
            default: http_response_code(404); echo json_encode(['status' => 'ERROR', 'message' => 'Unknown action']);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
}
