<?php
/**
 * AJAX endpoint for retrieving messages
 * Gets conversation history between current user and another user
 */

// Set JSON header IMMEDIATELY or bad things happen
header('Content-Type: application/json');

// Start session and include required files
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to view messages']);
    exit;
}

// Get current user ID
$current_user_id = $_SESSION['user_id'];

// Get conversation partner ID
$partner_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Validate partner ID
if ($partner_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

// Optional pagination parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$before_id = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;

$conn = getDbConnection();
$messages = []; // Initialize to ensure it's always an array

try {
    // Get messages between the two users
    $query = "
        SELECT m.*,
               u1.username as sender_name, /* Removed CONCAT for simplicity, adjust if needed */
               u2.username as receiver_name, /* Removed CONCAT */
               u1.profile_picture as sender_avatar,
               u2.profile_picture as receiver_avatar
        FROM messages m
        JOIN users u1 ON m.sender_id = u1.id
        JOIN users u2 ON m.receiver_id = u2.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
    ";

    // Add pagination if needed
    if ($before_id > 0) {
        $query .= " AND m.id < ?";
        $query .= " ORDER BY m.id DESC LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiiii", $current_user_id, $partner_id, $partner_id, $current_user_id, $before_id, $limit);
    } else {
        // For initial load, consider also fetching messages *after* a certain ID if polling for new ones.
        // However, for simple history, DESC LIMIT is fine for the first batch.
        $query .= " ORDER BY m.id DESC LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiii", $current_user_id, $partner_id, $partner_id, $current_user_id, $limit);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Format the message data
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'receiver_id' => $row['receiver_id'],
            'sender_name' => htmlspecialchars($row['sender_name']), // Sanitize output
            'receiver_name' => htmlspecialchars($row['receiver_name']), // Sanitize output
            'sender_avatar' => !empty($row['sender_avatar']) ? htmlspecialchars($row['sender_avatar']) : 'assets/default-avatar.png', // Use local fallback
            'receiver_avatar' => !empty($row['receiver_avatar']) ? htmlspecialchars($row['receiver_avatar']) : 'assets/default-avatar.png', // Use local fallback
            'content' => htmlspecialchars($row['content']), // Sanitize output
            'timestamp' => $row['created_at'],
            'time_ago' => formatTimeAgo($row['created_at']),
            'is_read' => (bool)$row['is_read'],
            'is_from_me' => $row['sender_id'] == $current_user_id
        ];
    }
    $stmt->close();

    // Mark all unread messages from partner as read
    $updateStmt = $conn->prepare("
        UPDATE messages
        SET is_read = 1
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $updateStmt->bind_param("ii", $partner_id, $current_user_id);
    $updateStmt->execute();
    $updateStmt->close();

} catch (Exception $e) {
    // Log the error
    error_log("Error in ajax/get_messages.php: " . $e->getMessage());
    if ($conn && $conn->ping()) {
        $conn->close();
    }
    // Output a JSON error message
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching messages.']);
    exit;
}

if ($conn && $conn->ping()) {
    $conn->close();
}

// Reverse the array to get chronological order for display
$messages = array_reverse($messages);

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'has_more' => count($messages) >= $limit // This logic is correct for DESC fetching
]);
?>