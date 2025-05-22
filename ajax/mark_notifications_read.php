<?php
/**
 * AJAX endpoint for marking notifications as read
 */
session_start();
require_once '../functions.php'; // Go up one directory

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

// CSRF Token Validation from POST body
if (!isset($_POST['csrf_token']) || !isValidCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'mark_all_read') {
    if (markAllNotificationsAsRead($user_id)) {
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read.']);
    } else {
        // This might happen if there were no unread notifications to mark,
        // or an actual DB error. The function can be modified to return affected_rows.
        echo json_encode(['success' => true, 'message' => 'No unread notifications to mark or operation failed.']);
    }
} elseif ($action === 'mark_one_read') {
    $notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    if ($notification_id > 0) {
        if (markNotificationAsRead($notification_id, $user_id)) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
}
?>