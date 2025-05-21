<?php
/**
 * AJAX Endpoint for Editing Posts
 */
session_start();
require_once '../functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to edit posts.']);
    exit;
}

// Validate CSRF token from header or POST
$csrf_token_valid = false;
if (isset($_POST['csrf_token']) && isValidCSRFToken($_POST['csrf_token'])) {
    $csrf_token_valid = true;
} else {
    $headers = getallheaders();
    $csrfHeaderKey = '';
    foreach (array_keys($headers) as $key) {
        if (strtolower($key) === 'x-csrf-token') {
            $csrfHeaderKey = $key;
            break;
        }
    }
    $header_token = isset($headers[$csrfHeaderKey]) ? $headers[$csrfHeaderKey] : '';
    if (!empty($header_token) && isValidCSRFToken($header_token)) {
        $csrf_token_valid = true;
    }
}

if (!$csrf_token_valid) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit();
}

if (!isset($_POST['post_id']) || !isset($_POST['content'])) {
    echo json_encode(['success' => false, 'message' => 'Missing post ID or content.']);
    exit;
}

$post_id = intval($_POST['post_id']);
$content = sanitize($_POST['content']);
$privacy = isset($_POST['privacy']) && in_array($_POST['privacy'], ['public', 'friends', 'private'])
    ? $_POST['privacy']
    : 'public'; // Default to public if not set or invalid

$user_id = $_SESSION['user_id'];

$conn = getDbConnection();

// Check if the user owns the post
$stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Post not found.']);
    $conn->close();
    exit;
}
$post_owner = $result->fetch_assoc()['user_id'];

if ($post_owner !== $user_id && !isAdmin()) { // Allow admin to edit posts if needed, otherwise strict ownership
    echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this post.']);
    $conn->close();
    exit;
}

// Update the post
$stmt = $conn->prepare("UPDATE posts SET content = ?, privacy = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("ssi", $content, $privacy, $post_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Post updated successfully.', 'new_content' => nl2br(htmlspecialchars($content)), 'new_privacy' => $privacy]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update post.']);
}

$stmt->close();
$conn->close();
?>