<?php
/**
 * AJAX endpoint for blocking/unblocking users
 * Updates friendship status to 'blocked' or removes block
 */

// Start session and include required files
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action']);
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
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Cannot block yourself
if ($user_id === $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot block yourself']);
    exit;
}

// Check if user exists
$target_user = getUserById($user_id);
if (!$target_user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$conn = getDbConnection();
$current_user_id = $_SESSION['user_id'];

// Get current friendship status
$status_stmt = $conn->prepare("
    SELECT id, status, user_id, friend_id 
    FROM friends 
    WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
");
$status_stmt->bind_param("iiii", $current_user_id, $user_id, $user_id, $current_user_id);
$status_stmt->execute();
$status_result = $status_stmt->get_result();
$friendship = $status_result->fetch_assoc();

if ($action === 'block') {
    // If a friendship record exists, update it
    if ($friendship) {
        if ($friendship['status'] === 'blocked') {
            echo json_encode(['success' => false, 'message' => 'This user is already blocked']);
            exit;
        }
        
        // Update existing record
        if ($friendship['user_id'] === $current_user_id) {
            // Current user is already the initiator
            $update_stmt = $conn->prepare("
                UPDATE friends
                SET status = 'blocked', updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->bind_param("i", $friendship['id']);
        } else {
            // Need to swap user_id and friend_id
            $delete_stmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
            $delete_stmt->bind_param("i", $friendship['id']);
            $delete_stmt->execute();
            
            // Create new record with current user as initiator
            $insert_stmt = $conn->prepare("
                INSERT INTO friends (user_id, friend_id, status, created_at)
                VALUES (?, ?, 'blocked', NOW())
            ");
            $insert_stmt->bind_param("ii", $current_user_id, $user_id);
            $success = $insert_stmt->execute();
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'User has been blocked']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to block user']);
            }
            $conn->close();
            exit;
        }
    } else {
        // Create new record
        $update_stmt = $conn->prepare("
            INSERT INTO friends (user_id, friend_id, status, created_at)
            VALUES (?, ?, 'blocked', NOW())
        ");
        $update_stmt->bind_param("ii", $current_user_id, $user_id);
    }
    
    $success = $update_stmt->execute();
    
    if ($success) {
        // Delete any messages between the users
        $delete_msgs_stmt = $conn->prepare("
            DELETE FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ");
        $delete_msgs_stmt->bind_param("iiii", $user_id, $current_user_id, $current_user_id, $user_id);
        $delete_msgs_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'User has been blocked']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to block user']);
    }
} elseif ($action === 'unblock') {
    // Check if the user is actually blocked
    if (!$friendship || $friendship['status'] !== 'blocked' || $friendship['user_id'] !== $current_user_id) {
        echo json_encode(['success' => false, 'message' => 'This user is not blocked by you']);
        exit;
    }
    
    // Remove the block by deleting the record
    $delete_stmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
    $delete_stmt->bind_param("i", $friendship['id']);
    $success = $delete_stmt->execute();
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'User has been unblocked']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unblock user']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>