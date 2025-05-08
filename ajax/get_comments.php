<?php
/**
 * Get Comments AJAX Endpoint
 * Retrieves comments for a specific post
 */
session_start();
require_once '../functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to view comments.']);
    exit();
}

// Validate required parameters
if (!isset($_GET['post_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing post ID parameter.']);
    exit();
}

$post_id = intval($_GET['post_id']);
$user_id = $_SESSION['user_id'];

$conn = getDbConnection();

// Check if post exists and user has permission to see it
$post_query = "
    SELECT p.* 
    FROM posts p
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
    echo json_encode(['success' => false, 'message' => 'Post not found or you do not have permission to view it.']);
    exit();
}

// Get comments for the post
$comments_query = "
    SELECT c.*, u.username
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at DESC
    LIMIT 50
";

$comments_stmt = $conn->prepare($comments_query);
$comments_stmt->bind_param("i", $post_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();

$comments = [];

while ($comment = $comments_result->fetch_assoc()) {
    // Get user avatar
    $avatar = getUserAvatar($comment['user_id']);
    
    // Format time
    $time_ago = formatTimeAgo($comment['created_at']);
    
    // Add to comments array
    $comments[] = [
        'id' => $comment['id'],
        'user_id' => $comment['user_id'],
        'username' => $comment['username'],
        'user_avatar' => $avatar,
        'content' => $comment['content'],
        'time_ago' => $time_ago
    ];
}

echo json_encode([
    'success' => true,
    'comments' => $comments
]);

$conn->close();
?>