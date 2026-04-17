<?php
/**
 * ============================================================================
 * SMART MEDI BOX - Image Server
 * ============================================================================
 * Serves article cover images with proper caching headers
 * 
 * Usage:
 * - GET /api/image/article/{article_id} - Serve article cover image
 * 
 * ============================================================================
 */

require_once 'db_config.php';

// Get article_id from URL path
$request_uri = $_SERVER['REQUEST_URI'];
error_log("IMAGE.PHP - REQUEST_URI: " . $request_uri);

// Parse the article ID from the URL
// Expected: /index.php/api/image/article/{article_id}
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/index.php', '', $path);
$parts = explode('/', trim($path, '/'));

error_log("IMAGE.PHP - Path parts: " . json_encode($parts));

// Expected format: api/image/article/{id}
if (count($parts) < 4 || $parts[0] !== 'api' || $parts[1] !== 'image' || $parts[2] !== 'article') {
    error_log("IMAGE.PHP - Invalid path. Expected: /api/image/article/{id}");
    http_response_code(404);
    header('Content-Type: text/plain');
    echo '404 Not Found';
    exit;
}

$article_id = intval($parts[3]);

if (!$article_id) {
    error_log("IMAGE.PHP - Invalid article_id: " . ($parts[3] ?? 'missing'));
    http_response_code(400);
    header('Content-Type: text/plain');
    echo '400 Bad Request: Invalid article_id';
    exit;
}

try {
    error_log("IMAGE.PHP - Fetching image for article_id: " . $article_id);
    
    // Try to fetch from binary storage first (cover_image_data)
    $query = "SELECT 
                COALESCE(
                  CASE WHEN cover_image_data IS NOT NULL THEN cover_image_data ELSE NULL END
                ) AS image_binary,
                COALESCE(cover_image_mime, 'image/jpeg') AS mime_type,
                cover_image_filename,
                cover_image
              FROM articles 
              WHERE id = $1";
    
    $result = pg_query_params($conn, $query, array($article_id));
    
    if ($result === false) {
        error_log("IMAGE.PHP - Query failed: " . pg_last_error($conn));
        http_response_code(500);
        header('Content-Type: text/plain');
        echo '500 Server Error';
        exit;
    }
    
    if (pg_num_rows($result) === 0) {
        error_log("IMAGE.PHP - Article not found: " . $article_id);
        http_response_code(404);
        header('Content-Type: text/plain');
        echo '404 Article Not Found';
        exit;
    }
    
    $row = pg_fetch_assoc($result);
    $image_binary = $row['image_binary'];
    $mime_type = $row['mime_type'] ?? 'image/jpeg';
    $filename = $row['cover_image_filename'] ?? 'image.jpg';
    $cover_image_link = $row['cover_image'];
    
    error_log("IMAGE.PHP - Image binary size: " . (strlen($image_binary) ?? 0));
    error_log("IMAGE.PHP - MIME type: " . $mime_type);
    
    // If binary data exists, serve it
    if (!empty($image_binary)) {
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . strlen($image_binary));
        header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
        header('ETag: "' . md5($image_binary) . '"');
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        error_log("IMAGE.PHP - Serving binary image for article " . $article_id);
        echo $image_binary;
        exit;
    }
    
    // If no binary data but we have a link (URL or Data-URL), redirect or serve the data URL
    if (!empty($cover_image_link)) {
        error_log("IMAGE.PHP - Have cover_image link, type: " . (strpos($cover_image_link, 'data:') === 0 ? 'data-url' : 'url'));
        
        // If it's a Data-URL, extract and serve the binary
        if (strpos($cover_image_link, 'data:') === 0) {
            if (preg_match('#^data:([^;]+);base64,(.*)$#', $cover_image_link, $m)) {
                $data_mime = $m[1];
                $b64_data = $m[2];
                $binary_data = base64_decode($b64_data, true);
                
                if ($binary_data === false) {
                    error_log("IMAGE.PHP - Failed to decode base64 data URL");
                    http_response_code(500);
                    header('Content-Type: text/plain');
                    echo '500 Invalid Data';
                    exit;
                }
                
                header('Content-Type: ' . $data_mime);
                header('Content-Length: ' . strlen($binary_data));
                header('Cache-Control: public, max-age=31536000');
                header('ETag: "' . md5($binary_data) . '"');
                error_log("IMAGE.PHP - Serving data-url image for article " . $article_id);
                echo $binary_data;
                exit;
            }
        } else {
            // It's a URL link - redirect to it
            header('Location: ' . $cover_image_link);
            exit;
        }
    }
    
    // No image found
    error_log("IMAGE.PHP - No image data or link found for article " . $article_id);
    http_response_code(404);
    header('Content-Type: text/plain');
    echo '404 No Image Found';
    
} catch (Exception $e) {
    error_log("IMAGE.PHP - Exception: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    echo '500 Server Error';
}
