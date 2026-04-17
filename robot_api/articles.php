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
                                                                                    a.cover_image,
                                                                                    encode(a.cover_image_data, 'base64') AS cover_image_b64,
                                                                                    a.cover_image_mime,
                                                                                    a.cover_image_filename,
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
                    // If image binary exists (returned as base64), convert to data URL, otherwise return cover_image link
                    'cover_image' => (!empty($row['cover_image_b64'])) ?
                                      ('data:' . ($row['cover_image_mime'] ?? 'image/jpeg') . ';base64,' . $row['cover_image_b64']) :
                                      ($row['cover_image'] ?? null),
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
        // Look up the doctor_id for this user_id
        error_log("MY ARTICLES - Looking up doctor_id for user_id: " . $user_id);
        $doctor_lookup_query = "SELECT id FROM doctors WHERE user_id = $1";
        $doctor_lookup_result = pg_query_params($conn, $doctor_lookup_query, array($user_id));
        
        if ($doctor_lookup_result === false) {
            throw new Exception("Doctor lookup failed: " . pg_last_error($conn));
        }
        
        if (pg_num_rows($doctor_lookup_result) === 0) {
            error_log("MY ARTICLES - No doctor record found for user_id: " . $user_id);
            http_response_code(200);
            echo json_encode(['status' => 'SUCCESS', 'articles' => []]);
            return;
        }
        
        $doctor_row = pg_fetch_assoc($doctor_lookup_result);
        $doctor_id = $doctor_row['id'];
        error_log("MY ARTICLES - Found doctor_id: " . $doctor_id);
        
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
                                        updated_at,
                                        cover_image,
                                        encode(cover_image_data, 'base64') AS cover_image_b64,
                                        cover_image_mime,
                                        cover_image_filename
                                    FROM articles
                                    WHERE doctor_id = $1
                                    ORDER BY created_at DESC";
        
        error_log("MY ARTICLES - Executing query with doctor_id: " . $doctor_id);
        $result = pg_query_params($conn, $query, array($doctor_id));
        
        if ($result === false) {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Database query failed: ' . pg_last_error($conn)]);
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
                'updated_at' => $row['updated_at'],
                'cover_image' => (!empty($row['cover_image_b64'])) ?
                                  ('data:' . ($row['cover_image_mime'] ?? 'image/jpeg') . ';base64,' . $row['cover_image_b64']) :
                                  ($row['cover_image'] ?? null)
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
    $cover_image_base64 = $input['cover_image_base64'] ?? null;
    $cover_image_mime = $input['cover_image_mime'] ?? null;
    $cover_image_filename = $input['cover_image_filename'] ?? null;
    
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
        error_log("CREATE ARTICLE - Starting article creation for user_id: " . $user_id);
        
        // Step 1: Look up the doctor.id for this user_id
        $doctor_lookup_query = "SELECT id FROM doctors WHERE user_id = $1";
        $doctor_lookup_result = pg_query_params($conn, $doctor_lookup_query, array($user_id));
        
        if ($doctor_lookup_result === false) {
            throw new Exception("Doctor lookup query failed: " . pg_last_error($conn));
        }
        
        $doctor_id = null;
        if (pg_num_rows($doctor_lookup_result) > 0) {
            $doctor_row = pg_fetch_assoc($doctor_lookup_result);
            $doctor_id = $doctor_row['id'];
            error_log("CREATE ARTICLE - Found doctor record: doctor_id=" . $doctor_id . " for user_id=" . $user_id);
        } else {
            error_log("CREATE ARTICLE - No doctor record found for user_id=" . $user_id . ", creating one");
            
            // Get user info
            $user_query = "SELECT name FROM users WHERE id = $1";
            $user_result = pg_query_params($conn, $user_query, array($user_id));
            
            $doctor_name = 'Doctor ' . $user_id;
            if ($user_result && pg_num_rows($user_result) > 0) {
                $user = pg_fetch_assoc($user_result);
                $doctor_name = $user['name'] ?? $doctor_name;
            }
            
            // Create doctor record
            $doctor_create_query = "INSERT INTO doctors (user_id, nic, name, date_of_birth, specialization, hospital) 
                                   VALUES ($1, $2, $3, $4, $5, $6) 
                                   RETURNING id";
            $doctor_create_result = pg_query_params($conn, $doctor_create_query, 
                array($user_id, 'DOC_' . $user_id . '_' . time(), $doctor_name, '1990-01-01', 'General', 'Smart Medi Box'));
            
            if ($doctor_create_result === false) {
                throw new Exception("Failed to create doctor record: " . pg_last_error($conn));
            }
            
            $new_doctor = pg_fetch_assoc($doctor_create_result);
            $doctor_id = $new_doctor['id'];
            error_log("CREATE ARTICLE - Created new doctor record: doctor_id=" . $doctor_id);
        }
        
        if (!$doctor_id) {
            throw new Exception("Failed to get or create doctor_id for user_id=" . $user_id);
        }
        
        // Step 2: Insert the article with the correct doctor_id
        error_log("CREATE ARTICLE - Inserting article with doctor_id=" . $doctor_id);

        if ($cover_image_base64) {
            // Store binary data using SQL decode(base64) to avoid sending raw non-UTF8 bytes in params
            $query = "INSERT INTO articles (doctor_id, title, content, cover_image, cover_image_data, cover_image_mime, cover_image_filename, is_published)
                      VALUES ($1, $2, $3, $4, decode($5, 'base64'), $6, $7, true)
                      RETURNING id";

            $result = pg_query_params($conn, $query,
                array($doctor_id, $title, $content, $cover_image, $cover_image_base64, $cover_image_mime, $cover_image_filename));
        } else {
            $query = "INSERT INTO articles (doctor_id, title, content, cover_image, is_published)
                      VALUES ($1, $2, $3, $4, true)
                      RETURNING id";
            $result = pg_query_params($conn, $query, array($doctor_id, $title, $content, $cover_image));
        }
        
        if ($result === false) {
            throw new Exception("Failed to insert article: " . pg_last_error($conn));
        }
        
        $article = pg_fetch_assoc($result);
        $article_id = $article['id'] ?? null;
        
        error_log("CREATE ARTICLE - Article created successfully with id: " . $article_id);
        
        http_response_code(201);
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Article created',
            'article_id' => $article_id
        ]);
    } catch (Exception $e) {
        error_log("Create Article Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Failed to create article: ' . $e->getMessage()]);
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
        // Look up the doctor_id for this user_id
        $doctor_lookup_query = "SELECT id FROM doctors WHERE user_id = $1";
        $doctor_lookup_result = pg_query_params($conn, $doctor_lookup_query, array($user_id));
        
        if ($doctor_lookup_result === false || pg_num_rows($doctor_lookup_result) === 0) {
            http_response_code(403);
            echo json_encode(['status' => 'ERROR', 'message' => 'User is not a doctor']);
            return;
        }
        
        $doctor_row = pg_fetch_assoc($doctor_lookup_result);
        $doctor_id = $doctor_row['id'];
        
        $query = "UPDATE articles SET title = $1, content = $2, updated_at = NOW()
                  WHERE id = $3 AND doctor_id = $4";
        
        $result = pg_query_params($conn, $query, 
            array($title, $content, $article_id, $doctor_id));
        
        if ($result) {
            http_response_code(200);
            echo json_encode(['status' => 'SUCCESS', 'message' => 'Article updated']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to update article: ' . pg_last_error($conn)]);
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
        // Look up the doctor_id for this user_id
        $doctor_lookup_query = "SELECT id FROM doctors WHERE user_id = $1";
        $doctor_lookup_result = pg_query_params($conn, $doctor_lookup_query, array($user_id));
        
        if ($doctor_lookup_result === false || pg_num_rows($doctor_lookup_result) === 0) {
            http_response_code(403);
            echo json_encode(['status' => 'ERROR', 'message' => 'User is not a doctor']);
            return;
        }
        
        $doctor_row = pg_fetch_assoc($doctor_lookup_result);
        $doctor_id = $doctor_row['id'];
        
        // Permanently remove the article row owned by this doctor
        error_log("DELETE ARTICLE - Attempting to delete article_id={$article_id} for doctor_id={$doctor_id}");
        $query = "DELETE FROM articles WHERE id = $1 AND doctor_id = $2";
        $result = pg_query_params($conn, $query, array($article_id, $doctor_id));

        if ($result === false) {
            error_log("DELETE ARTICLE - Query failed: " . pg_last_error($conn));
            http_response_code(500);
            echo json_encode(['status' => 'ERROR', 'message' => 'Failed to delete article: ' . pg_last_error($conn)]);
            return;
        }

        $affected = pg_affected_rows($result);
        if ($affected > 0) {
            http_response_code(200);
            echo json_encode(['status' => 'SUCCESS', 'message' => 'Article deleted']);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'ERROR', 'message' => 'Article not found or not owned by doctor']);
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
        // increment view count
        $query = "UPDATE articles SET view_count = view_count + 1 WHERE id = $1";
        pg_query_params($conn, $query, array($article_id));

        // return the full article details for the client to render (include image binary if present)
        $detail_query = "SELECT a.id, a.id as article_id, a.title, a.content, a.summary, a.view_count as views, a.created_at, d.name as doctor_name,
                         a.cover_image, encode(a.cover_image_data, 'base64') AS cover_image_b64, a.cover_image_mime
                         FROM articles a
                         JOIN doctors d ON a.doctor_id = d.id
                         WHERE a.id = $1 LIMIT 1";
        $detail_result = pg_query_params($conn, $detail_query, array($article_id));
        if ($detail_result && pg_num_rows($detail_result) > 0) {
            $row = pg_fetch_assoc($detail_result);
            http_response_code(200);
            echo json_encode(['status' => 'SUCCESS', 'article' => [
                'id' => intval($row['id']),
                'article_id' => $row['article_id'],
                'title' => $row['title'],
                'content' => $row['content'],
                'summary' => $row['summary'],
                'views' => intval($row['views']),
                'created_at' => $row['created_at'],
                'doctor_name' => $row['doctor_name'],
                'cover_image' => (!empty($row['cover_image_b64'])) ?
                                  ('data:' . ($row['cover_image_mime'] ?? 'image/jpeg') . ';base64,' . $row['cover_image_b64']) :
                                  ($row['cover_image'] ?? null)
            ]]);
            return;
        }

        // fallback: success without article
        http_response_code(200);
        echo json_encode(['status' => 'SUCCESS']);
    } catch (Exception $e) {
        error_log("View Article Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => 'Server error']);
    }
}
