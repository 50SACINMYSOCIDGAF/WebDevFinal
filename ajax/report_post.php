<?php
/**
 * Report Post AJAX Endpoint
 * Handles reporting posts for review by admins
 */
session_start();
require_once '../functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to report posts.']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !isValidCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit();
}

// Validate required parameters
if (!isset($_POST['post_id']) || !isset($_POST['reason'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit();
}

$post_id = intval($_POST['post_id']);
$reason = sanitize($_POST['reason']);
$details = isset($_POST['details']) ? sanitize($_POST['details']) : '';
$user_id = $_SESSION['user_id'];

// Validate reason
if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please select a reason for your report.']);
    exit();
}

$conn = getDbConnection();

// Check if post exists
$post_query = "SELECT p.*, u.username FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = ?";
$post_stmt = $conn->prepare($post_query);
$post_stmt->bind_param("i", $post_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();

if ($post_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Post not found.']);
    exit();
}

$post = $post_result->fetch_assoc();

// Check if user already reported this post
$report_check_query = "SELECT id FROM reports WHERE reporter_id = ? AND post_id = ?";
$report_check_stmt = $conn->prepare($report_check_query);
$report_check_stmt->bind_param("ii", $user_id, $post_id);
$report_check_stmt->execute();
$report_result = $report_check_stmt->get_result();

if ($report_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already reported this post.']);
    exit();
}

// Insert report
$report_query = "INSERT INTO reports (reporter_id, reported_user_id, post_id, reason, status) VALUES (?, ?, ?, ?, 'pending')";
$report_stmt = $conn->prepare($report_query);
$report_stmt->bind_param("iiis", $user_id, $post['user_id'], $post_id, $reason);

if ($report_stmt->execute()) {
    $report_id = $report_stmt->insert_id;
    
    // If details were provided, update the report
    if (!empty($details)) {
        $update_query = "UPDATE reports SET admin_notes = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $details, $report_id);
        $update_stmt->execute();
    }
    
    // Notify admins (in a real system, you might want to send emails or other notifications)
    $admin_query = "SELECT id FROM users WHERE is_admin = 1";
    $admin_result = $conn->query($admin_query);
    
    while ($admin = $admin_result->fetch_assoc()) {
        $message = "New post report: " . $_SESSION['username'] . " reported a post by " . $post['username'];
        createNotification($admin['id'], 'report', $message, $user_id, $post_id);
    }
    
    echo json_encode(['success' => true, 'message' => 'Report submitted successfully. Thank you for helping to keep our community safe.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit report: ' . $conn->error]);
}

$conn->close();
?>