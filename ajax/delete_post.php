<?php
/**
 * AJAX Endpoint for Deleting Posts
 */
session_start();
require_once '../functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to delete posts.']);
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


if (!isset($_POST['post_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing post ID.']);
    exit;
}

$post_id = intval($_POST['post_id']);
$user_id = $_SESSION['user_id'];

$conn = getDbConnection();

// Check if the user owns the post or is an admin
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

if ($post_owner !== $user_id && !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this post.']);
    $conn->close();
    exit;
}

// Delete associated likes first
$stmt_likes = $conn->prepare("DELETE FROM likes WHERE post_id = ?");
$stmt_likes->bind_param("i", $post_id);
$stmt_likes->execute();
$stmt_likes->close();

// Delete associated comments
$stmt_comments = $conn->prepare("DELETE FROM comments WHERE post_id = ?");
$stmt_comments->bind_param("i", $post_id);
$stmt_comments->execute();
$stmt_comments->close();

// Delete associated saved posts
$stmt_saved = $conn->prepare("DELETE FROM saved_posts WHERE post_id = ?");
$stmt_saved->bind_param("i", $post_id);
$stmt_saved->execute();
$stmt_saved->close();

// Delete associated reports for this post
$stmt_reports = $conn->prepare("DELETE FROM reports WHERE post_id = ?");
$stmt_reports->bind_param("i", $post_id);
$stmt_reports->execute();
$stmt_reports->close();


// Delete the post
$stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
$stmt->bind_param("i", $post_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Post deleted successfully.']);
    } else {
        // This case might happen if the post was already deleted by another process
        // or if the initial check for post existence passed but deletion failed for some other reason
        // (though unlikely if permissions were correct).
        echo json_encode(['success' => false, 'message' => 'Post could not be deleted or was already deleted.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete post.']);
}

$stmt->close();
$conn->close();
?>