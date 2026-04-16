<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Articles API
 * ============================================================================
 * Version: 1.0.0
 * 
 * Endpoints:
 * - GET /api/articles/list - Get all published articles
 * - GET /api/articles/my - Get articles created by logged-in doctor
 * - POST /api/articles/create - Create new article
 * - POST /api/articles/update - Update article
 * - POST /api/articles/delete - Delete article
 * - POST /api/articles/view - Increment view count
 * 
 * ============================================================================
 */

require_once 'db_config.php';

error_log("ARTICLES.PHP - File included successfully");
error_log("ARTICLES.PHP - GET array: " . json_encode($_GET));
error_log("ARTICLES.PHP - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

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

// Get action from query string
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

error_log("ARTICLES API - Action: $action, Method: $method");

// Route to handler
switch ($action) {
    case 'list':
        error_log("ARTICLES API - Matched case: list");
        handleListArticles($method);
        break;
    
    case 'my':
        error_log("ARTICLES API - Matched case: my");
        handleMyArticles($method);
        break;
    
    case 'create':
        error_log("ARTICLES API - Matched case: create");
        handleCreateArticle($method);
        break;
    
    case 'update':
        error_log("ARTICLES API - Matched case: update");
        handleUpdateArticle($method);
        break;
    
    case 'delete':
        error_log("ARTICLES API - Matched case: delete");
        handleDeleteArticle($method);
        break;
    
    case 'view':
        error_log("ARTICLES API - Matched case: view");
        handleViewArticle($method);
        break;
    
    default:
        error_log("ARTICLES API - No matching case for action: '$action'");
        http_response_code(404);
        echo json_encode(['status' => 'ERROR', 'message' => 'Endpoint not found']);
}

// ============================================================================
// LIST ALL PUBLISHED ARTICLES
// ============================================================================
function handleListArticles($method) {
    global $conn;
    
    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    try {
        error_log("LIST ARTICLES - Starting query");
        
        $query = "SELECT 
                    a.id,
                    a.id as article_id,
                    a.title,
                    a.content,
                    a.summary,
                    a.category,
                    a.view_count as views,
                    a.created_at,
                    d.name as doctor_name
                  FROM articles a
                  JOIN doctors d ON a.doctor_id = d.id
                  WHERE a.is_published = true
                  ORDER BY a.created_at DESC
                  LIMIT 50";
        
        error_log("LIST ARTICLES - Query: " . $query);
        
        $result = pg_query($conn, $query);
        
        if ($result === false) {
            $error_msg = pg_last_error($conn);
            error_log("LIST ARTICLES - Query failed: " . $error_msg);
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Database query failed: ' . $error_msg]);
            return;
        }
        
        error_log("LIST ARTICLES - Query successful, rows: " . pg_num_rows($result));
        
        $articles = [];
        while ($row = pg_fetch_assoc($result)) {
            // Truncate content for list view (first 200 chars)
            $excerpt = substr(strip_tags($row['content']), 0, 200) . '...';
            
            $articles[] = [
                'id' => intval($row['id']),
                'article_id' => $row['article_id'],
                'title' => $row['title'],
                'excerpt' => $excerpt,
                'cover_image' => null,
                'views' => intval($row['views']),
                'created_at' => $row['created_at'],
                'doctor_name' => $row['doctor_name'],
                'doctor_photo' => null
            ];
        }
        
        error_log("LIST ARTICLES - Returning " . count($articles) . " articles");
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'count' => count($articles),
            'articles' => $articles
        ]);
    } catch (Exception $e) {
        error_log("List Articles Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Server error']);
    }
}

// ============================================================================
// GET DOCTOR'S OWN ARTICLES
// ============================================================================
function handleMyArticles($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? null;
    $user_id = $input['user_id'] ?? null;
    
    error_log("MY ARTICLES - Token received: " . ($token ? substr($token, 0, 10) . '...' : 'NULL'));
    error_log("MY ARTICLES - User ID received: " . ($user_id ?? 'NULL'));
    
    // If token is provided, look up user_id from it
    if ($token && !$user_id) {
        error_log("MY ARTICLES - Looking up token: " . substr($token, 0, 10) . '...');
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if ($token_result === false) {
            error_log("MY ARTICLES - Query error: " . pg_last_error($conn));
        } elseif (pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
            error_log("MY ARTICLES - Token lookup successful, user_id: " . $user_id);
        } else {
            error_log("MY ARTICLES - Token lookup returned no rows");
            // Try without expiry check
            $token_query_noexpiry = "SELECT user_id, expires_at FROM session_tokens WHERE token = $1";
            $token_result_noexpiry = pg_query_params($conn, $token_query_noexpiry, array($token));
            if ($token_result_noexpiry && pg_num_rows($token_result_noexpiry) > 0) {
                $token_row_noexpiry = pg_fetch_assoc($token_result_noexpiry);
                error_log("MY ARTICLES - Token found but expired, expires_at: " . $token_row_noexpiry['expires_at']);
            } else {
                error_log("MY ARTICLES - Token not found in session_tokens table");
            }
        }
    }
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'user_id required (via token or parameter)']);
        return;
    }
    
    try {
        $query = "SELECT 
                    id,
                    id as article_id,
                    title,
                    content,
                    summary,
                    category,
                    is_published,
                    view_count as views,
                    created_at,
                    updated_at
                  FROM articles
                  WHERE doctor_id = $1
                  ORDER BY created_at DESC";
        
        $result = pg_query_params($conn, $query, array($user_id));
        
        if ($result === false) {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Database query failed']);
            return;
        }
        
        $articles = [];
        while ($row = pg_fetch_assoc($result)) {
            $articles[] = [
                'id' => intval($row['id']),
                'article_id' => $row['article_id'],
                'title' => $row['title'],
                'content' => $row['content'],
                'summary' => $row['summary'],
                'category' => $row['category'],
                'is_published' => $row['is_published'],
                'views' => intval($row['views']),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'status' => 'SUCCESS',
            'count' => count($articles),
            'articles' => $articles
        ]);
    } catch (Exception $e) {
        error_log("My Articles Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Server error']);
    }
}

// ============================================================================
// CREATE ARTICLE
// ============================================================================
function handleCreateArticle($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? null;
    $user_id = $input['user_id'] ?? null;
    $title = $input['title'] ?? null;
    $content = $input['content'] ?? null;
    $cover_image = $input['cover_image'] ?? null;
    
    error_log("CREATE ARTICLE - Token received: " . ($token ? substr($token, 0, 10) . '...' : 'NULL'));
    error_log("CREATE ARTICLE - User ID received: " . ($user_id ?? 'NULL'));
    error_log("CREATE ARTICLE - Title: " . ($title ?? 'NULL'));
    error_log("CREATE ARTICLE - Content length: " . (strlen($content ?? '') ?? 0));
    
    // If token is provided, look up user_id from it
    if ($token && !$user_id) {
        error_log("CREATE ARTICLE - Looking up token: " . substr($token, 0, 10) . '...');
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if ($token_result === false) {
            error_log("CREATE ARTICLE - Query error: " . pg_last_error($conn));
        } elseif (pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
            error_log("CREATE ARTICLE - Token lookup successful, user_id: " . $user_id);
        } else {
            error_log("CREATE ARTICLE - Token lookup returned no rows");
            // Try without expiry check
            $token_query_noexpiry = "SELECT user_id, expires_at FROM session_tokens WHERE token = $1";
            $token_result_noexpiry = pg_query_params($conn, $token_query_noexpiry, array($token));
            if ($token_result_noexpiry && pg_num_rows($token_result_noexpiry) > 0) {
                $token_row_noexpiry = pg_fetch_assoc($token_result_noexpiry);
                error_log("CREATE ARTICLE - Token found but expired, expires_at: " . $token_row_noexpiry['expires_at']);
            } else {
                error_log("CREATE ARTICLE - Token not found in session_tokens table");
            }
        }
    }
    
    if (!$user_id || !$title || !$content) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters: user_id (or token), title, content']);
        return;
    }
    
    try {
        $article_id = 'ART_' . time() . '_' . bin2hex(random_bytes(4));
        
        // Check if doctor record exists for this user_id
        // If not, create one
        $doctor_check_query = "SELECT id FROM doctors WHERE id = $1";
        $doctor_check_result = pg_query_params($conn, $doctor_check_query, array($user_id));
        
        if (!$doctor_check_result) {
            error_log("CREATE ARTICLE - Doctor check query failed: " . pg_last_error($conn));
            // Try to create a doctor record for this user
            error_log("CREATE ARTICLE - Creating doctor record for user_id: " . $user_id);
            $doctor_create_query = "INSERT INTO doctors (id, name, email) VALUES ($1, $2, $3)";
            $doctor_create_result = pg_query_params($conn, $doctor_create_query, 
                array($user_id, 'Doctor ' . $user_id, 'doctor' . $user_id . '@smartmedibox.local'));
            
            if (!$doctor_create_result) {
                error_log("CREATE ARTICLE - Failed to create doctor record: " . pg_last_error($conn));
            } else {
                error_log("CREATE ARTICLE - Doctor record created successfully");
            }
        } elseif (pg_num_rows($doctor_check_result) === 0) {
            error_log("CREATE ARTICLE - Doctor with id $user_id not found, creating one");
            $doctor_create_query = "INSERT INTO doctors (id, name, email) VALUES ($1, $2, $3)";
            $doctor_create_result = pg_query_params($conn, $doctor_create_query, 
                array($user_id, 'Doctor ' . $user_id, 'doctor' . $user_id . '@smartmedibox.local'));
            
            if (!$doctor_create_result) {
                error_log("CREATE ARTICLE - Failed to create doctor record: " . pg_last_error($conn));
            } else {
                error_log("CREATE ARTICLE - Doctor record created successfully");
            }
        }
        
        // Now insert the article
        $query = "INSERT INTO articles (doctor_id, title, content, is_published)
                  VALUES ($1, $2, $3, true)";
        
        error_log("CREATE ARTICLE - Executing insert with doctor_id: $user_id, title: $title");
        $result = pg_query_params($conn, $query, 
            array($user_id, $title, $content));
        
        if ($result) {
            error_log("CREATE ARTICLE - Insert successful");
            http_response_code(201);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'Article created',
                'article_id' => $article_id
            ]);
        } else {
            $error_msg = pg_last_error($conn);
            error_log("CREATE ARTICLE - Insert failed: " . $error_msg);
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to create article: ' . $error_msg]);
        }
    } catch (Exception $e) {
        error_log("Create Article Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

// ============================================================================
// UPDATE ARTICLE
// ============================================================================
function handleUpdateArticle($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? null;
    $user_id = $input['user_id'] ?? null;
    $article_id = $input['article_id'] ?? null;
    $title = $input['title'] ?? null;
    $content = $input['content'] ?? null;
    
    // If token is provided, look up user_id from it
    if ($token && !$user_id) {
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if (pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
        }
    }
    
    if (!$user_id || !$article_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters']);
        return;
    }
    
    try {
        $query = "UPDATE articles SET title = $1, content = $2, updated_at = NOW()
                  WHERE id = $3 AND doctor_id = $4";
        
        $result = pg_query_params($conn, $query, 
            array($title, $content, $article_id, $user_id));
        
        if ($result) {
            http_response_code(200);
            echo json_encode(['status' => 'SUCCESS', 'message' => 'Article updated']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to update article']);
        }
    } catch (Exception $e) {
        error_log("Update Article Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

// ============================================================================
// DELETE ARTICLE
// ============================================================================
function handleDeleteArticle($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? null;
    $user_id = $input['user_id'] ?? null;
    $article_id = $input['article_id'] ?? null;
    
    // If token is provided, look up user_id from it
    if ($token && !$user_id) {
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if (pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
        }
    }
    
    if (!$user_id || !$article_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters']);
        return;
    }
    
    try {
        $query = "UPDATE articles SET is_published = false WHERE id = $1 AND doctor_id = $2";
        $result = pg_query_params($conn, $query, array($article_id, $user_id));
        
        if ($result) {
            http_response_code(200);
            echo json_encode(['status' => 'SUCCESS', 'message' => 'Article deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to delete article']);
        }
    } catch (Exception $e) {
        error_log("Delete Article Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Database error']);
    }
}

// ============================================================================
// INCREMENT VIEW COUNT
// ============================================================================
function handleViewArticle($method) {
    global $conn;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'ERROR', 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $article_id = $input['article_id'] ?? null;
    
    if (!$article_id) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'article_id required']);
        return;
    }
    
    try {
        $query = "UPDATE articles SET view_count = view_count + 1 WHERE id = $1";
        pg_query_params($conn, $query, array($article_id));
        
        http_response_code(200);
        echo json_encode(['status' => 'SUCCESS']);
    } catch (Exception $e) {
        error_log("View Article Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Server error']);
    }
}
