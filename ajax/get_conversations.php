<?php
/**
 * AJAX endpoint for retrieving user's conversations
 * Gets list of users the current user has exchanged messages with
 */

// Start session FIRST
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files AFTER session start
require_once '../config.php';
require_once '../functions.php';

// Set JSON header BEFORE any other output
header('Content-Type: application/json'); // <-- Make sure this is present and early

// Check if user is logged in
if (!isLoggedIn()) {
    // Still output JSON for errors
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

// Get current user ID
$user_id = $_SESSION['user_id'];

$conn = getDbConnection();
$conversations = []; // Initialize empty array

try {
    // Get all conversations with latest message
    // Using a more optimized query might be beneficial on large datasets
    $query = "
        SELECT u.id as user_id, u.username, u.profile_picture,
               m.id as last_message_id, m.content as last_message,
               m.created_at as last_message_time, m.sender_id as last_sender_id,
               (SELECT COUNT(*) FROM messages msg
                WHERE msg.sender_id = u.id AND msg.receiver_id = ? AND msg.is_read = 0) as unread_count
        FROM users u
        JOIN (
            SELECT
                LEAST(sender_id, receiver_id) as user1,
                GREATEST(sender_id, receiver_id) as user2,
                MAX(id) as max_id
            FROM messages
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)
        ) latest_msg_ids ON (u.id = latest_msg_ids.user1 OR u.id = latest_msg_ids.user2) AND u.id != ?
        JOIN messages m ON m.id = latest_msg_ids.max_id
        ORDER BY m.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    // Correct binding: user_id appears 4 times in the query
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Format avatar URL
        $avatarUrl = !empty($row['profile_picture']) ? $row['profile_picture'] : 'https://via.placeholder.com/50'; // Or use your default path

        // Add conversation to list
        $conversations[] = [
            'user_id' => $row['user_id'],
            'username' => htmlspecialchars($row['username']), // Sanitize output
            'avatar' => htmlspecialchars($avatarUrl),
            // Prepend "You: " if the current user sent the last message
            'last_message' => ($row['last_sender_id'] == $user_id ? "You: " : "") . htmlspecialchars($row['last_message']),
            'last_message_time' => $row['last_message_time'],
            'time_ago' => formatTimeAgo($row['last_message_time']), // Ensure this function exists and works
            'unread_count' => (int)$row['unread_count']
        ];
    }
    $conn->close();

    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);

} catch (Exception $e) {
    // Log the error $e->getMessage()
     if ($conn && $conn->ping()) { // Check if connection is still valid before closing
         $conn->close();
     }
    // Output JSON error
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching conversations.',
        // 'error_detail' => $e->getMessage() // Optional: for debugging only
    ]);
}
