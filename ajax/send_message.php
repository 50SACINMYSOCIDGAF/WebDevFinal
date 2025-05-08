<?php
/**
 * AJAX endpoint for sending messages
 * Handles creating new messages between users
 */

// Start session and include required files
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to send messages']);
    exit;
}

// Validate CSRF token
$headers = getallheaders();
$token = isset($headers['X-CSRF-Token']) ? $headers['X-CSRF-Token'] : '';

if (!isValidCSRFToken($token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get POST data
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

// Validate inputs
if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
    exit;
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

// Sanitize message content
$content = sanitize($content);

// Get current user ID
$sender_id = $_SESSION['user_id'];

// Check if receiver exists
$receiver = getUserById($receiver_id);
if (!$receiver) {
    echo json_encode(['success' => false, 'message' => 'Recipient not found']);
    exit;
}

// Check if user is blocked by receiver
if (isUserBlocked($receiver_id, $sender_id)) {
    echo json_encode(['success' => false, 'message' => 'You cannot send messages to this user']);
    exit;
}

// Check if sender has blocked receiver
if (isUserBlocked($sender_id, $receiver_id)) {
    echo json_encode(['success' => false, 'message' => 'You have blocked this user. Unblock them to send messages.']);
    exit;
}

// Insert message
$conn = getDbConnection();
$stmt = $conn->prepare("
    INSERT INTO messages (sender_id, receiver_id, content) 
    VALUES (?, ?, ?)
");

$stmt->bind_param("iis", $sender_id, $receiver_id, $content);
$success = $stmt->execute();

if ($success) {
    // Get the new message ID
    $message_id = $conn->insert_id;
    
    // Create notification for receiver
    $sender = getUserById($sender_id);
    $notificationMessage = $sender['username'] . " sent you a message";
    createNotification($receiver_id, 'message', $notificationMessage, $sender_id, $message_id);
    
    // Format response with timestamp
    $timestamp = date('Y-m-d H:i:s');
    $timeAgo = formatTimeAgo($timestamp);
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'message_data' => [
            'id' => $message_id,
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'content' => $content,
            'timestamp' => $timestamp,
            'time_ago' => $timeAgo
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}

$conn->close();
?>