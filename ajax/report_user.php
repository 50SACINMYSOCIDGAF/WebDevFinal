<?php
/**
 * AJAX endpoint for reporting users
 * Creates a report record for administrators to review
 */

// Start session and include required files
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to report a user']);
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
$reported_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$reason = isset($_POST['reason']) ? sanitize($_POST['reason']) : '';
$details = isset($_POST['details']) ? sanitize($_POST['details']) : '';

// Validate inputs
if ($reported_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please select a reason for your report']);
    exit;
}

// Cannot report yourself
if ($reported_id === $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot report yourself']);
    exit;
}

// Check if user exists
$reported_user = getUserById($reported_id);
if (!$reported_user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$conn = getDbConnection();
$reporter_id = $_SESSION['user_id'];

// Check if user has already reported this user recently
$check_stmt = $conn->prepare("
    SELECT id FROM reports 
    WHERE reporter_id = ? AND reported_user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND post_id IS NULL AND comment_id IS NULL
");
$check_stmt->bind_param("ii", $reporter_id, $reported_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already reported this user in the last 24 hours']);
    $conn->close();
    exit;
}

// Create the report
$report_stmt = $conn->prepare("
    INSERT INTO reports (reporter_id, reported_user_id, reason, status, created_at)
    VALUES (?, ?, ?, 'pending', NOW())
");
$report_stmt->bind_param("iis", $reporter_id, $reported_id, $reason);
$success = $report_stmt->execute();

if ($success) {
    // Get the report ID
    $report_id = $conn->insert_id;
    
    // Add details if provided
    if (!empty($details)) {
        $details_stmt = $conn->prepare("UPDATE reports SET admin_notes = ? WHERE id = ?");
        $details_stmt->bind_param("si", $details, $report_id);
        $details_stmt->execute();
    }
    
    // Notify administrators
    $admins_query = $conn->query("SELECT id FROM users WHERE is_admin = 1");
    while ($admin = $admins_query->fetch_assoc()) {
        $message = "New user report: {$reported_user['username']} was reported for {$reason}";
        createNotification($admin['id'], 'report', $message, $reporter_id, $report_id);
    }
    
    echo json_encode(['success' => true, 'message' => 'Thank you for your report. Our team will review it shortly.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit report. Please try again.']);
}

$conn->close();
?>