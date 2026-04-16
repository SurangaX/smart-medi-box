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
        handleListArticles($method);
        break;
    
    case 'my':
        handleMyArticles($method);
        break;
    
    case 'create':
        handleCreateArticle($method);
        break;
    
    case 'update':
        handleUpdateArticle($method);
        break;
    
    case 'delete':
        handleDeleteArticle($method);
        break;
    
    case 'view':
        handleViewArticle($method);
        break;
    
    default:
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
        $query = "SELECT 
                    a.id,
                    a.article_id,
                    a.title,
                    a.content,
                    a.cover_image,
                    a.views,
                    a.created_at,
                    u.name as doctor_name,
                    u.profile_photo as doctor_photo
                  FROM articles a
                  JOIN users u ON a.doctor_id = u.id
                  WHERE a.status = 'PUBLISHED' AND a.deleted_at IS NULL
                  ORDER BY a.created_at DESC
                  LIMIT 50";
        
        $result = pg_query($conn, $query);
        
        if ($result === false) {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Database query failed: ' . pg_last_error($conn)]);
            return;
        }
        
        $articles = [];
        while ($row = pg_fetch_assoc($result)) {
            // Truncate content for list view (first 200 chars)
            $excerpt = substr(strip_tags($row['content']), 0, 200) . '...';
            
            $articles[] = [
                'article_id' => $row['article_id'],
                'title' => $row['title'],
                'excerpt' => $excerpt,
                'cover_image' => $row['cover_image'],
                'views' => intval($row['views']),
                'created_at' => $row['created_at'],
                'doctor_name' => $row['doctor_name'],
                'doctor_photo' => $row['doctor_photo']
            ];
        }
        
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
    
    // If token is provided, look up user_id from it
    if ($token && !$user_id) {
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if (pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
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
                    article_id,
                    title,
                    content,
                    cover_image,
                    status,
                    views,
                    created_at,
                    updated_at
                  FROM articles
                  WHERE doctor_id = $1 AND deleted_at IS NULL
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
                'cover_image' => $row['cover_image'],
                'status' => $row['status'],
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
    
    // If token is provided, look up user_id from it
    if ($token && !$user_id) {
        $token_query = "SELECT user_id FROM session_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $token_result = pg_query_params($conn, $token_query, array($token));
        
        if (pg_num_rows($token_result) > 0) {
            $token_row = pg_fetch_assoc($token_result);
            $user_id = $token_row['user_id'];
        }
    }
    
    if (!$user_id || !$title || !$content) {
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Missing required parameters: user_id (or token), title, content']);
        return;
    }
    
    try {
        $article_id = 'ART_' . time() . '_' . bin2hex(random_bytes(4));
        
        $query = "INSERT INTO articles (article_id, doctor_id, title, content, cover_image, status)
                  VALUES ($1, $2, $3, $4, $5, 'PUBLISHED')";
        
        $result = pg_query_params($conn, $query, 
            array($article_id, $user_id, $title, $content, $cover_image));
        
        if ($result) {
            http_response_code(201);
            echo json_encode([
                'status' => 'SUCCESS',
                'message' => 'Article created',
                'article_id' => $article_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to create article']);
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
    $cover_image = $input['cover_image'] ?? null;
    
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
        $query = "UPDATE articles SET title = $1, content = $2, cover_image = $3, updated_at = NOW()
                  WHERE article_id = $4 AND doctor_id = $5";
        
        $result = pg_query_params($conn, $query, 
            array($title, $content, $cover_image, $article_id, $user_id));
        
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
        $query = "UPDATE articles SET deleted_at = NOW() WHERE article_id = $1 AND doctor_id = $2";
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
        $query = "UPDATE articles SET views = views + 1 WHERE article_id = $1";
        pg_query_params($conn, $query, array($article_id));
        
        http_response_code(200);
        echo json_encode(['status' => 'SUCCESS']);
    } catch (Exception $e) {
        error_log("View Article Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Server error']);
    }
}
