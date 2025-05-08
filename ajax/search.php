<?php
/**
 * Search AJAX Endpoint
 * Handles searching for users, posts, etc.
 */
session_start();
require_once '../functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([]);
    exit();
}

// Validate required parameters
if (!isset($_GET['q']) || strlen($_GET['q']) < 2) {
    echo json_encode([]);
    exit();
}

$query = sanitize($_GET['q']);
$user_id = $_SESSION['user_id'];

$conn = getDbConnection();
$results = [];

// Search for users
$user_query = "
    SELECT id, username, profile_picture, bio 
    FROM users 
    WHERE username LIKE CONCAT('%', ?, '%') OR 
          bio LIKE CONCAT('%', ?, '%')
    LIMIT 5
";

$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("ss", $query, $query);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

while ($user = $user_result->fetch_assoc()) {
    $avatar = !empty($user['profile_picture']) ? $user['profile_picture'] : 'assets/default-avatar.png';
    $bio_excerpt = !empty($user['bio']) ? substr($user['bio'], 0, 50) . (strlen($user['bio']) > 50 ? '...' : '') : 'No bio';
    
    $results[] = [
        'type' => 'user',
        'id' => $user['id'],
        'name' => $user['username'],
        'meta' => $bio_excerpt,
        'image' => $avatar,
        'link' => 'profile.php?id=' . $user['id']
    ];
}

// Search for posts (that the user can see)
$post_query = "
    SELECT p.id, p.content, p.created_at, u.id as user_id, u.username, u.profile_picture
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE (p.content LIKE CONCAT('%', ?, '%')) AND (
        p.privacy = 'public' 
        OR p.user_id = ? 
        OR (p.privacy = 'friends' AND EXISTS (
            SELECT 1 FROM friends 
            WHERE (user_id = p.user_id AND friend_id = ? AND status = 'accepted')
            OR (user_id = ? AND friend_id = p.user_id AND status = 'accepted')
        ))
    )
    ORDER BY p.created_at DESC
    LIMIT 5
";

$post_stmt = $conn->prepare($post_query);
$post_stmt->bind_param("siii", $query, $user_id, $user_id, $user_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();

while ($post = $post_result->fetch_assoc()) {
    $avatar = !empty($post['profile_picture']) ? $post['profile_picture'] : 'assets/default-avatar.png';
    $content_excerpt = substr($post['content'], 0, 50) . (strlen($post['content']) > 50 ? '...' : '');
    $time_ago = formatTimeAgo($post['created_at']);
    
    $results[] = [
        'type' => 'post',
        'id' => $post['id'],
        'name' => $post['username'] . "'s post",
        'meta' => $content_excerpt,
        'image' => $avatar,
        'link' => 'post.php?id=' . $post['id']
    ];
}

// Check for hashtags in query
if (strpos($query, '#') === 0) {
    $hashtag = substr($query, 1);
    
    $hashtag_query = "
        SELECT p.id, p.content, p.created_at, u.id as user_id, u.username, u.profile_picture
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.content LIKE CONCAT('%#', ?, '%') AND (
            p.privacy = 'public' 
            OR p.user_id = ? 
            OR (p.privacy = 'friends' AND EXISTS (
                SELECT 1 FROM friends 
                WHERE (user_id = p.user_id AND friend_id = ? AND status = 'accepted')
                OR (user_id = ? AND friend_id = p.user_id AND status = 'accepted')
            ))
        )
        ORDER BY p.created_at DESC
        LIMIT 3
    ";
    
    $hashtag_stmt = $conn->prepare($hashtag_query);
    $hashtag_stmt->bind_param("siii", $hashtag, $user_id, $user_id, $user_id);
    $hashtag_stmt->execute();
    $hashtag_result = $hashtag_stmt->get_result();
    
    if ($hashtag_result->num_rows > 0) {
        $results[] = [
            'type' => 'hashtag',
            'id' => 0,
            'name' => '#' . $hashtag,
            'meta' => 'View all posts with this hashtag',
            'image' => 'assets/hashtag-icon.png',
            'link' => 'search.php?q=%23' . $hashtag
        ];
        
        while ($post = $hashtag_result->fetch_assoc()) {
            $avatar = !empty($post['profile_picture']) ? $post['profile_picture'] : 'assets/default-avatar.png';
            $content_excerpt = substr($post['content'], 0, 50) . (strlen($post['content']) > 50 ? '...' : '');
            $time_ago = formatTimeAgo($post['created_at']);
            
            $results[] = [
                'type' => 'post',
                'id' => $post['id'],
                'name' => $post['username'] . "'s post with #" . $hashtag,
                'meta' => $content_excerpt,
                'image' => $avatar,
                'link' => 'post.php?id=' . $post['id']
            ];
        }
    }
}

$conn->close();
echo json_encode($results);
?>