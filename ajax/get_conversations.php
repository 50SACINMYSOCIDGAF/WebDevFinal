<?php
/**
 * AJAX endpoint for retrieving user's conversations
 * Gets list of users the current user has exchanged messages with
 */

// Start session and include required files
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to view conversations']);
    exit;
}

// Get current user ID
$user_id = $_SESSION['user_id'];

$conn = getDbConnection();

// Get all conversations with latest message
$query = "
    SELECT u.id as user_id, u.username, u.profile_picture,
           m.id as last_message_id, m.content as last_message,
           m.created_at as last_message_time,
           (SELECT COUNT(*) FROM messages 
            WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
    FROM users u
    JOIN (
        SELECT DISTINCT
            CASE 
                WHEN sender_id = ? THEN receiver_id
                ELSE sender_id
            END as conversation_user_id,
            MAX(id) as max_id
        FROM messages
        WHERE sender_id = ? OR receiver_id = ?
        GROUP BY conversation_user_id
    ) c ON u.id = c.conversation_user_id
    JOIN messages m ON m.id = c.max_id
    ORDER BY m.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$conversations = [];
while ($row = $result->fetch_assoc()) {
    // Format avatar URL
    $avatarUrl = !empty($row['profile_picture']) ? $row['profile_picture'] : 'https://via.placeholder.com/50';
    
    // Add conversation to list
    $conversations[] = [
        'user_id' => $row['user_id'],
        'username' => $row['username'],
        'avatar' => $avatarUrl,
        'last_message' => $row['last_message'],
        'last_message_time' => $row['last_message_time'],
        'time_ago' => formatTimeAgo($row['last_message_time']),
        'unread_count' => (int)$row['unread_count']
    ];
}

$conn->close();

echo json_encode([
    'success' => true,
    'conversations' => $conversations
]);
?>