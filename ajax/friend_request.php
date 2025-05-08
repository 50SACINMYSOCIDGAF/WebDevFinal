<?php
/**
 * Friend Request AJAX Endpoint
 * Handles sending/accepting/rejecting/canceling/unfriending actions.
 */
session_start();
require_once '../functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to manage friends.']);
    exit();
}

// --- Validate CSRF token (expecting it in POST body now) ---
if (!isset($_POST['csrf_token']) || !isValidCSRFToken($_POST['csrf_token'])) {
    // Fallback check for header if needed, but POST body is preferred from JS now
    // $headers = getallheaders();
    // $token = isset($headers['X-CSRF-Token']) ? $headers['X-CSRF-Token'] : '';
    // if (!isValidCSRFToken($token)) {
         echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
         exit();
    // }
}

// --- Validate required parameters from $_POST ---
if (!isset($_POST['user_id']) || !isset($_POST['action'])) { // Check $_POST
    echo json_encode(['success' => false, 'message' => 'Missing required parameters (user_id or action).']);
    exit();
}

$target_user_id = intval($_POST['user_id']); // Read from $_POST
$action = sanitize($_POST['action']); // Read action from $_POST
$user_id = $_SESSION['user_id']; // Current user's ID

// Validate action type
$allowed_actions = ['add', 'accept', 'reject', 'cancel', 'unfriend'];
if (!in_array($action, $allowed_actions)) {
     echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
     exit();
}

// Check if it's not the user trying to friend themselves
if ($target_user_id === $user_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot perform this action on yourself.']);
    exit();
}

$conn = getDbConnection();

// Check if target user exists
$user_check_query = "SELECT id, username FROM users WHERE id = ?";
$user_check_stmt = $conn->prepare($user_check_query);
$user_check_stmt->bind_param("i", $target_user_id);
$user_check_stmt->execute();
$user_result = $user_check_stmt->get_result();

if ($user_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    $conn->close(); // Close connection before exit
    exit();
}
$target_user = $user_result->fetch_assoc();
$target_username = $target_user['username']; // Get username for notifications

// --- Process Actions ---

$conn->begin_transaction(); // Start transaction
try {
    $response = ['success' => false, 'message' => 'Could not process request.']; // Default response

    // Get existing friendship status
    $friendship_check_query = "
        SELECT id, status, user_id, friend_id
        FROM friends
        WHERE (user_id = ? AND friend_id = ?)
        OR (user_id = ? AND friend_id = ?)
    ";
    $friendship_check_stmt = $conn->prepare($friendship_check_query);
    $friendship_check_stmt->bind_param("iiii", $user_id, $target_user_id, $target_user_id, $user_id);
    $friendship_check_stmt->execute();
    $friendship_result = $friendship_check_stmt->get_result();
    $friendship = $friendship_result->fetch_assoc();
    $friendship_id = $friendship['id'] ?? null;
    $current_status = $friendship['status'] ?? null;
    $initiator_id = $friendship['user_id'] ?? null; // Who initiated the existing request/block

    switch ($action) {
        case 'add':
            if ($current_status === 'accepted') {
                $response = ['success' => false, 'message' => 'You are already friends.'];
            } elseif ($current_status === 'pending' && $initiator_id === $user_id) {
                $response = ['success' => false, 'message' => 'Request already sent.'];
            } elseif ($current_status === 'pending' && $initiator_id === $target_user_id) {
                // They sent a request, accept it instead
                 $stmt = $conn->prepare("UPDATE friends SET status = 'accepted', updated_at = NOW() WHERE id = ?");
                 $stmt->bind_param("i", $friendship_id);
                 if ($stmt->execute()) {
                     createNotification($target_user_id, 'friend_accept', $_SESSION['username'] . " accepted your friend request", $user_id);
                     $response = ['success' => true, 'message' => 'Friend request accepted!'];
                 } else { $response['message'] = 'Failed to accept request.'; }
            } elseif ($current_status === 'blocked') {
                 $response = ['success' => false, 'message' => 'Cannot send request due to block.'];
            } else { // No existing or rejected request
                 // If rejected, update; otherwise insert
                 if ($current_status === 'rejected') {
                      $stmt = $conn->prepare("UPDATE friends SET user_id = ?, friend_id = ?, status = 'pending', updated_at = NOW() WHERE id = ?");
                      $stmt->bind_param("iii", $user_id, $target_user_id, $friendship_id); // Ensure current user is sender
                 } else {
                      $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
                      $stmt->bind_param("ii", $user_id, $target_user_id);
                 }
                 if ($stmt->execute()) {
                     createNotification($target_user_id, 'friend_request', $_SESSION['username'] . " sent you a friend request", $user_id);
                     $response = ['success' => true, 'message' => 'Friend request sent!'];
                 } else { $response['message'] = 'Failed to send request.'; }
            }
            break;

        case 'accept':
            if ($current_status === 'pending' && $initiator_id === $target_user_id) { // Make sure they sent the request
                $stmt = $conn->prepare("UPDATE friends SET status = 'accepted', updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $friendship_id);
                if ($stmt->execute()) {
                    createNotification($target_user_id, 'friend_accept', $_SESSION['username'] . " accepted your friend request", $user_id);
                    $response = ['success' => true, 'message' => 'Friend request accepted!'];
                } else { $response['message'] = 'Failed to accept request.'; }
            } else { $response['message'] = 'No pending request found to accept.'; }
            break;

        case 'reject':
             if ($current_status === 'pending' && $initiator_id === $target_user_id) { // Make sure they sent the request
                 // Option 1: Delete the request
                 // $stmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
                 // Option 2: Mark as rejected (allows resending later)
                 $stmt = $conn->prepare("UPDATE friends SET status = 'rejected', updated_at = NOW() WHERE id = ?");
                 $stmt->bind_param("i", $friendship_id);
                 if ($stmt->execute()) {
                     $response = ['success' => true, 'message' => 'Friend request declined.'];
                 } else { $response['message'] = 'Failed to decline request.'; }
             } else { $response['message'] = 'No pending request found to decline.'; }
             break;

        case 'cancel':
             if ($current_status === 'pending' && $initiator_id === $user_id) { // Make sure current user sent it
                 $stmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
                 $stmt->bind_param("i", $friendship_id);
                 if ($stmt->execute()) {
                     $response = ['success' => true, 'message' => 'Friend request cancelled.'];
                 } else { $response['message'] = 'Failed to cancel request.'; }
             } else { $response['message'] = 'No pending request found to cancel.'; }
             break;

        case 'unfriend':
            if ($current_status === 'accepted') {
                $stmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
                $stmt->bind_param("i", $friendship_id);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'User unfriended successfully.'];
                } else { $response['message'] = 'Failed to unfriend user.'; }
            } else { $response['message'] = 'You are not friends with this user.'; }
            break;
    }

    $conn->commit(); // Commit transaction if successful
    echo json_encode($response);

} catch (mysqli_sql_exception $e) {
    $conn->rollback(); // Rollback on error
    // Log error $e->getMessage()
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
} finally {
    if ($conn) {
        $conn->close(); // Always close connection
    }
}
