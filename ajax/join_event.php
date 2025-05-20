<?php
/**
 * AJAX endpoint for joining or leaving events.
 * Handles updating user attendance status for an event.
 */
session_start();
require_once '../functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to join events.']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !isValidCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit();
}

// Validate required parameters
if (!isset($_POST['event_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit();
}

$event_id = intval($_POST['event_id']);
$action = sanitize($_POST['action']); // 'going', 'interested', 'leave'
$user_id = $_SESSION['user_id'];

// Validate action
$allowed_actions = ['going', 'interested', 'leave'];
if (!in_array($action, $allowed_actions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit();
}

$conn = getDbConnection();

// Check if event exists
$event_check_stmt = $conn->prepare("SELECT id, user_id, title FROM events WHERE id = ?");
$event_check_stmt->bind_param("i", $event_id);
$event_check_stmt->execute();
$event_result = $event_check_stmt->get_result();

if ($event_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Event not found.']);
    exit();
}
$event = $event_result->fetch_assoc();
$event_creator_id = $event['user_id'];
$event_title = $event['title'];

// Check current attendance status
$current_status_stmt = $conn->prepare("SELECT id, status FROM event_attendees WHERE event_id = ? AND user_id = ?");
$current_status_stmt->bind_param("ii", $event_id, $user_id);
$current_status_stmt->execute();
$current_status_result = $current_status_stmt->get_result();
$current_attendance = $current_status_result->fetch_assoc();

$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

if ($action === 'leave') {
    if ($current_attendance) {
        $delete_stmt = $conn->prepare("DELETE FROM event_attendees WHERE event_id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $event_id, $user_id);
        if ($delete_stmt->execute()) {
            $response = ['success' => true, 'message' => 'You have left the event.', 'new_status' => 'none'];
        } else {
            $response['message'] = 'Failed to leave event.';
        }
    } else {
        $response['message'] = 'You are not currently attending this event.';
    }
} else { // 'going' or 'interested'
    if ($current_attendance) {
        // Update existing status
        if ($current_attendance['status'] === $action) {
            $response = ['success' => true, 'message' => 'Your status is already set to ' . $action . '.'];
        } else {
            $update_stmt = $conn->prepare("UPDATE event_attendees SET status = ?, created_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("si", $action, $current_attendance['id']);
            if ($update_stmt->execute()) {
                $response = ['success' => true, 'message' => 'Your status has been updated to ' . $action . '.', 'new_status' => $action];
                // Notify event creator if status changed to 'going'
                if ($action === 'going' && $event_creator_id !== $user_id) {
                    $message = $_SESSION['username'] . " is now going to your event: " . $event_title;
                    createNotification($event_creator_id, 'event_going', $message, $user_id, $event_id);
                }
            } else {
                $response['message'] = 'Failed to update status.';
            }
        }
    } else {
        // Insert new attendance record
        $insert_stmt = $conn->prepare("INSERT INTO event_attendees (event_id, user_id, status, created_at) VALUES (?, ?, ?, NOW())");
        $insert_stmt->bind_param("iis", $event_id, $user_id, $action);
        if ($insert_stmt->execute()) {
            $response = ['success' => true, 'message' => 'You are now ' . $action . ' to this event!', 'new_status' => $action];
            // Notify event creator
            if ($event_creator_id !== $user_id) {
                $message = $_SESSION['username'] . " is " . $action . " to your event: " . $event_title;
                createNotification($event_creator_id, 'event_join', $message, $user_id, $event_id);
            }
        } else {
            $response['message'] = 'Failed to join event.';
        }
    }
}

$conn->close();
echo json_encode($response);
?>
