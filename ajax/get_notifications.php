<?php
/**
 * AJAX endpoint for fetching user notifications
 */
session_start();
require_once '../functions.php'; // Go up one directory to access functions.php

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';

// Validate inputs
if ($limit <= 0) $limit = 20;
if ($offset < 0) $offset = 0;

$notifications = getNotifications($user_id, $unreadOnly, $limit, $offset);
$totalUnread = countUnreadNotifications($user_id); // Get total unread count for UI update

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'total_unread' => $totalUnread,
    'has_more' => count($notifications) === $limit // Basic check for more notifications
]);
?>