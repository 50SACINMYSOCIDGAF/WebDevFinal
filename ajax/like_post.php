<?php
/**
 * Like/Unlike Post AJAX Endpoint
 * Handles liking and unliking posts
 */
session_start();
require_once '../functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to like posts.']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !isValidCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit();
}

// Validate required parameters
if (!isset($_POST['post_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit();
}

$post_id = intval($_POST['post_id']);
$action = $_POST['action']; // 'like' or 'unlike'
$user_id = $_SESSION['user_id'];

// Validate action
if ($action !== 'like' && $action !== 'unlike') {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
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
    echo json_encode(['success' => false, 'message' => 'Post not found or you do not have permission to interact with it.']);
    exit();
}

$post = $post_result->fetch_assoc();

// Check if user has already liked the post
$like_check_query = "SELECT id FROM likes WHERE post_id = ? AND user_id = ?";
$like_check_stmt = $conn->prepare($like_check_query);
$like_check_stmt->bind_param("ii", $post_id, $user_id);
$like_check_stmt->execute();
$like_result = $like_check_stmt->get_result();
$already_liked = $like_result->num_rows > 0;

// Perform like/unlike action
if ($action === 'like' && !$already_liked) {
    // Add like
    $like_query = "INSERT INTO likes (post_id, user_id) VALUES (?, ?)";
    $like_stmt = $conn->prepare($like_query);
    $like_stmt->bind_param("ii", $post_id, $user_id);
    
    if ($like_stmt->execute()) {
        // Create notification for post owner if it's not the user's own post
        if ($post['user_id'] !== $user_id) {
            $message = $_SESSION['username'] . " liked your post";
            createNotification($post['user_id'], 'like', $message, $user_id, $post_id);
        }
        
        echo json_encode(['success' => true, 'message' => 'Post liked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to like post.']);
    }
} elseif ($action === 'unlike' && $already_liked) {
    // Remove like
    $unlike_query = "DELETE FROM likes WHERE post_id = ? AND user_id = ?";
    $unlike_stmt = $conn->prepare($unlike_query);
    $unlike_stmt->bind_param("ii", $post_id, $user_id);
    
    if ($unlike_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Post unliked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unlike post.']);
    }
} else {
    // Like status already matches desired action
    echo json_encode(['success' => true, 'message' => 'No change needed.']);
}

$conn->close();
?>