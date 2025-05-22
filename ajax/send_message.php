<?php
/**
 * AJAX endpoint for sending messages
 * Handles creating new messages between users
 */

// Start session and include required files
session_start();
require_once '../config.php';
require_once '../functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to send messages']);
    exit;
}

// --- CSRF Token Validation ---
// Priority 1: Check POST body
$token_from_post = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null;

// Priority 2: Check Headers (as a fallback or if you use it elsewhere)
$token_from_header = null;
if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $token_from_header = $_SERVER['HTTP_X_CSRF_TOKEN'];
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['X-CSRF-Token'])) {
        $token_from_header = $headers['X-CSRF-Token'];
    } elseif (isset($headers['x-csrf-token'])) { // Check lowercase too, some servers might change case
        $token_from_header = $headers['x-csrf-token'];
    }
}

// Use token from POST if available, otherwise from header
$token_to_validate = $token_from_post ?? $token_from_header;

// Logging for debugging CSRF token sources
error_log("AJAX send_message.php - Session ID: " . session_id());
error_log("AJAX send_message.php - Token from POST: " . ($token_from_post ?? 'NOT SET'));
error_log("AJAX send_message.php - Token from Header (HTTP_X_CSRF_TOKEN): " . ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? 'NOT SET'));
error_log("AJAX send_message.php - Token from Header (getallheaders): " . ($token_from_header ?? 'NOT SET - getallheaders might not have found it or function unavailable'));
error_log("AJAX send_message.php - Token chosen for validation: " . ($token_to_validate ?? 'NOT SET/EMPTY'));
error_log("AJAX send_message.php - Session CSRF Token for comparison: " . ($_SESSION['csrf_token'] ?? 'NOT SET/EMPTY'));


if (!isValidCSRFToken($token_to_validate)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please try again.']);
    exit;
}
// --- End CSRF Token Validation ---


// Get POST data for the message itself
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
$content = sanitize($content); // Make sure sanitize() is robust enough for your needs

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
    INSERT INTO messages (sender_id, receiver_id, content, created_at)
    VALUES (?, ?, ?, NOW())
");
// Assuming your 'messages' table's 'created_at' is auto-timestamped or you set it manually.
// If it's not DATETIME NULL and needs manual NOW(), then the query should be
// VALUES (?, ?, ?, NOW()) and bind_param should include the NOW() or be handled by setInsertTimestamps if that's your pattern.
// Your functions.php uses setInsertTimestamps/setUpdateTimestamps, but messages table isn't listed there.
// For simplicity, adding NOW() directly here.

$stmt->bind_param("iis", $sender_id, $receiver_id, $content);
$success = $stmt->execute();

if ($success) {
    $message_id = $conn->insert_id;

    $sender = getUserById($sender_id);
    $notificationMessage = ($sender ? htmlspecialchars($sender['username']) : 'Someone') . " sent you a message";
    createNotification($receiver_id, 'message', $notificationMessage, $sender_id, $message_id);

    $timestamp = date('Y-m-d H:i:s'); // Get current timestamp for response

    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'message_data' => [
            'id' => $message_id,
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'content' => $content, // Already sanitized
            'timestamp' => $timestamp,
            'time_ago' => formatTimeAgo($timestamp), // Format it for immediate display
            'sender_avatar' => getUserAvatar($sender_id),
            'sender_name' => $sender ? htmlspecialchars($sender['username']) : 'Unknown User',
            'is_read' => false // New message is unread for receiver
        ]
    ]);
} else {
    error_log("Failed to send message DB error: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again.']);
}

$conn->close();
?>