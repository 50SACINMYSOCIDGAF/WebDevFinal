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

// --- Validate CSRF token ---
$csrf_token_valid = false;
if (isset($_POST['csrf_token']) && isValidCSRFToken($_POST['csrf_token'])) {
    $csrf_token_valid = true;
} else {
    $headers = getallheaders();
    // Normalize header key (some servers might change case)
    $csrfHeaderKey = '';
    foreach (array_keys($headers) as $key) {
        if (strtolower($key) === 'x-csrf-token') {
            $csrfHeaderKey = $key;
            break;
        }
    }
    $header_token = isset($headers[$csrfHeaderKey]) ? $headers[$csrfHeaderKey] : '';

    if (!empty($header_token) && isValidCSRFToken($header_token)) {
        $csrf_token_valid = true;
    }
}

if (!$csrf_token_valid) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit();
}

// --- Validate required parameters from $_POST ---
if (!isset($_POST['user_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters (user_id or action).']);
    exit();
}

$target_user_id = intval($_POST['user_id']);
$action = sanitize($_POST['action']);
$user_id = $_SESSION['user_id'];

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
    $conn->close();
    exit();
}
$target_user = $user_result->fetch_assoc();
$target_username = $target_user['username'];

$conn->begin_transaction();
try {
    $response = ['success' => false, 'message' => 'Could not process request.'];

    $friendship_check_query = "
        SELECT id, status, user_id as initiator_id
        FROM friends
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ";
    $friendship_check_stmt = $conn->prepare($friendship_check_query);
    $friendship_check_stmt->bind_param("iiii", $user_id, $target_user_id, $target_user_id, $user_id);
    $friendship_check_stmt->execute();
    $friendship_result = $friendship_check_stmt->get_result();
    $friendship = $friendship_result->fetch_assoc();
    $friendship_id = $friendship['id'] ?? null;
    $current_status = $friendship['status'] ?? null;
    $initiator_id = $friendship['initiator_id'] ?? null;

    switch ($action) {
        case 'add':
            if ($current_status === 'accepted') {
                $response = ['success' => false, 'message' => 'You are already friends.'];
            } elseif ($current_status === 'pending' && $initiator_id === $user_id) {
                $response = ['success' => false, 'message' => 'Friend request already sent.'];
            } elseif ($current_status === 'pending' && $initiator_id === $target_user_id) {
                 $stmt = $conn->prepare("UPDATE friends SET status = 'accepted', updated_at = NOW() WHERE id = ?");
                 $stmt->bind_param("i", $friendship_id);
                 if ($stmt->execute()) {
                     createNotification($target_user_id, 'friend_accept', $_SESSION['username'] . " accepted your friend request", $user_id);
                     $response = ['success' => true, 'message' => 'Friend request accepted!'];
                 } else { $response['message'] = 'Failed to accept request.'; }
            } elseif ($current_status === 'blocked') {
                 $response = ['success' => false, 'message' => $initiator_id === $user_id ? 'You have blocked this user.' : 'This user has blocked you.'];
            } else {
                 if ($current_status === 'rejected' || $current_status === 'cancelled') { // Allow re-sending if rejected or cancelled by target
                      $stmt = $conn->prepare("UPDATE friends SET user_id = ?, friend_id = ?, status = 'pending', created_at = NOW(), updated_at = NOW() WHERE id = ?");
                      $stmt->bind_param("iii", $user_id, $target_user_id, $friendship_id);
                 } else {
                      $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status, created_at, updated_at) VALUES (?, ?, 'pending', NOW(), NOW())");
                      $stmt->bind_param("ii", $user_id, $target_user_id);
                 }
                 if ($stmt->execute()) {
                     createNotification($target_user_id, 'friend_request', $_SESSION['username'] . " sent you a friend request", $user_id);
                     $response = ['success' => true, 'message' => 'Friend request sent!'];
                 } else { $response['message'] = 'Failed to send friend request.'; }
            }
            break;

        case 'accept':
            if ($current_status === 'pending' && $initiator_id === $target_user_id) {
                $stmt = $conn->prepare("UPDATE friends SET status = 'accepted', updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $friendship_id);
                if ($stmt->execute()) {
                    createNotification($target_user_id, 'friend_accept', $_SESSION['username'] . " accepted your friend request", $user_id);
                    $response = ['success' => true, 'message' => 'Friend request accepted!'];
                } else { $response['message'] = 'Failed to accept friend request.'; }
            } else { $response['message'] = 'No pending request found or you cannot accept this request.'; }
            break;

        case 'reject':
             if ($current_status === 'pending' && $initiator_id === $target_user_id) {
                 $stmt = $conn->prepare("UPDATE friends SET status = 'rejected', updated_at = NOW() WHERE id = ?");
                 $stmt->bind_param("i", $friendship_id);
                 if ($stmt->execute()) {
                     $response = ['success' => true, 'message' => 'Friend request declined.'];
                 } else { $response['message'] = 'Failed to decline friend request.'; }
             } else { $response['message'] = 'No pending request found to decline.'; }
             break;

        case 'cancel':
             if ($current_status === 'pending' && $initiator_id === $user_id) {
                 $stmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
                 $stmt->bind_param("i", $friendship_id);
                 if ($stmt->execute()) {
                     $response = ['success' => true, 'message' => 'Friend request cancelled.'];
                 } else { $response['message'] = 'Failed to cancel friend request.'; }
             } else { $response['message'] = 'No pending request found to cancel or you are not the sender.'; }
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

    $conn->commit();
    echo json_encode($response);

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("Friend request error: " . $e->getMessage()); // Log the error
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>