<?php
/**
 * Friend Request AJAX Endpoint
 * Handles sending friend requests
 */
session_start();
require_once '../functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to send friend requests.']);
    exit();
}

// Validate CSRF token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || !isValidCSRFToken($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit();
}

// Validate required parameters
if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing user ID parameter.']);
    exit();
}

$target_user_id = intval($_GET['user_id']);
$user_id = $_SESSION['user_id'];

// Check if it's not the user trying to friend themselves
if ($target_user_id === $user_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot send a friend request to yourself.']);
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
    exit();
}

$target_user = $user_result->fetch_assoc();

// Check if there's already a friendship or request
$friendship_check_query = "
    SELECT * FROM friends 
    WHERE (user_id = ? AND friend_id = ?) 
    OR (user_id = ? AND friend_id = ?)
";
$friendship_check_stmt = $conn->prepare($friendship_check_query);
$friendship_check_stmt->bind_param("iiii", $user_id, $target_user_id, $target_user_id, $user_id);
$friendship_check_stmt->execute();
$friendship_result = $friendship_check_stmt->get_result();

if ($friendship_result->num_rows > 0) {
    $friendship = $friendship_result->fetch_assoc();
    
    // Check existing relationship status
    if ($friendship['status'] === 'accepted') {
        echo json_encode(['success' => false, 'message' => 'You are already friends with this user.']);
    } elseif ($friendship['status'] === 'pending' && $friendship['user_id'] === $user_id) {
        echo json_encode(['success' => false, 'message' => 'You have already sent a friend request to this user.']);
    } elseif ($friendship['status'] === 'pending' && $friendship['friend_id'] === $user_id) {
        // They already sent you a request, accepting it
        $accept_query = "UPDATE friends SET status = 'accepted' WHERE id = ?";
        $accept_stmt = $conn->prepare($accept_query);
        $accept_stmt->bind_param("i", $friendship['id']);
        
        if ($accept_stmt->execute()) {
            // Create notification for the other user
            $message = $_SESSION['username'] . " accepted your friend request";
            createNotification($target_user_id, 'friend_accept', $message, $user_id);
            
            echo json_encode(['success' => true, 'message' => 'Friend request accepted!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to accept friend request.']);
        }
    } elseif ($friendship['status'] === 'blocked') {
        // Don't reveal blocking status, just use generic message
        echo json_encode(['success' => false, 'message' => 'Unable to send friend request.']);
    } elseif ($friendship['status'] === 'rejected') {
        // Allow sending a new request if previously rejected
        $update_query = "UPDATE friends SET status = 'pending' WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $friendship['id']);
        
        if ($update_stmt->execute()) {
            // Create notification for the target user
            $message = $_SESSION['username'] . " sent you a friend request";
            createNotification($target_user_id, 'friend_request', $message, $user_id);
            
            echo json_encode(['success' => true, 'message' => 'Friend request sent!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send friend request.']);
        }
    }
    
    $conn->close();
    exit();
}

// Send new friend request
$request_query = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')";
$request_stmt = $conn->prepare($request_query);
$request_stmt->bind_param("ii", $user_id, $target_user_id);

if ($request_stmt->execute()) {
    // Create notification for the target user
    $message = $_SESSION['username'] . " sent you a friend request";
    createNotification($target_user_id, 'friend_request', $message, $user_id);
    
    echo json_encode(['success' => true, 'message' => 'Friend request sent!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send friend request.']);
}

$conn->close();
?>