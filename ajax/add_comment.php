<?php
/**
 * Add Comment AJAX Endpoint
 * Handles adding comments to posts
 */
session_start();
require_once '../functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to comment.']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !isValidCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit();
}

// Validate required parameters
if (!isset($_POST['post_id']) || !isset($_POST['content'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit();
}

$post_id = intval($_POST['post_id']);
$content = sanitize($_POST['content']);
$user_id = $_SESSION['user_id'];

// Validate content
if (trim($content) === '') {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
    exit();
}

$conn = getDbConnection();

// Check if post exists and user has permission to see it
$post_query = "
    SELECT p.*, u.username 
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ? AND (
        p.privacy = 'public' 
        OR p.user_id = ? 
        OR (p.privacy = 'friends' AND EXISTS (
            SELECT 1 FROM friends 
            WHERE (user_id = p.user_id AND friend_id = ? AND status = 'accepted')
            OR (user_id = ? AND friend_id = p.user_id AND status = 'accepted')
        ))
    )
";

$stmt = $conn->prepare($post_query);
$stmt->bind_param("iiii", $post_id, $user_id, $user_id, $user_id);
$stmt->execute();
$post_result = $stmt->get_result();

if ($post_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Post not found or you do not have permission to comment on it.']);
    exit();
}

$post = $post_result->fetch_assoc();

// Insert comment
$comment_query = "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)";
$comment_stmt = $conn->prepare($comment_query);
$comment_stmt->bind_param("iis", $post_id, $user_id, $content);

if ($comment_stmt->execute()) {
    $comment_id = $comment_stmt->insert_id;
    
    // Get user data for response
    $user_data = getUserById($user_id);
    $user_avatar = getUserAvatar($user_id);
    
    // Create notification for post owner if it's not the user's own post
    if ($post['user_id'] !== $user_id) {
        $message = $_SESSION['username'] . " commented on your post";
        createNotification($post['user_id'], 'comment', $message, $user_id, $post_id);
    }
    
    // Prepare comment data for response
    $comment_data = [
        'id' => $comment_id,
        'user_id' => $user_id,
        'username' => $_SESSION['username'],
        'user_avatar' => $user_avatar,
        'content' => $content,
        'time_ago' => 'Just now'
    ];
    
    echo json_encode([
        'success' => true, 
        'message' => 'Comment added successfully',
        'comment' => $comment_data
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add comment: ' . $conn->error]);
}

$conn->close();
?>