<?php
/**
 * Helper functions for the social media platform
 * Contains commonly used functions for user interaction, posts, security, etc.
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

/**
 * Sanitize user input to prevent XSS attacks
 * @param string $input The input to sanitize
 * @return string Sanitized input
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate if a password meets strength requirements
 * @param string $password The password to validate
 * @return bool True if password is strong, false otherwise
 */
function isPasswordStrong($password) {
    // At least 8 characters with uppercase, lowercase, number, and special char
    return (strlen($password) >= 8 &&
            preg_match('/[A-Z]/', $password) &&
            preg_match('/[a-z]/', $password) &&
            preg_match('/[0-9]/', $password) &&
            preg_match('/[^A-Za-z0-9]/', $password));
}

/**
 * Get database connection
 * @return mysqli Database connection
 */
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

/**
 * Check if user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is an admin
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    if (!isLoggedIn()) return false;
    
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        return (bool)$user['is_admin'];
    }
    
    return false;
}

/**
 * Get user data by ID
 * @param int $userId User ID
 * @return array|null User data or null if not found
 */
function getUserById($userId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $conn->close();
        return $user;
    }
    
    $conn->close();
    return null;
}

/**
 * Get user avatar URL
 * @param int $userId User ID
 * @param string $size Size of avatar (small, medium, large)
 * @return string URL to avatar image
 */
function getUserAvatar($userId, $size = 'medium') {
    $user = getUserById($userId);
    
    if ($user && !empty($user['profile_picture'])) {
        return $user['profile_picture'];
    }
    
    // Default sizes for placeholder avatars
    $sizes = [
        'small' => 30,
        'medium' => 50,
        'large' => 150
    ];
    
    $pixelSize = isset($sizes[$size]) ? $sizes[$size] : $sizes['medium'];
    return "https://via.placeholder.com/{$pixelSize}";
}

/**
 * Get customization settings for a user
 * @param int $userId User ID
 * @return array Customization settings
 */
function getUserCustomization($userId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT u.theme_color, u.font_preference, u.layout_preference, 
               c.background_image, c.background_color, c.text_color, 
               c.link_color, c.custom_css, c.music_url
        FROM users u
        LEFT JOIN user_customization c ON u.id = c.user_id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $customization = $result->fetch_assoc();
        $conn->close();
        return $customization;
    }
    
    // Default customization settings
    $conn->close();
    return [
        'theme_color' => '#4f46e5',
        'font_preference' => 'System Default',
        'layout_preference' => 'standard',
        'background_image' => '',
        'background_color' => '',
        'text_color' => '',
        'link_color' => '',
        'custom_css' => '',
        'music_url' => ''
    ];
}

/**
 * Check if a user is friends with another user or if a request is pending.
 * @param int $userId Current user ID
 * @param int $friendId Friend user ID to check
 * @return array|bool An associative array of the friendship record if found, or false otherwise.
 * The array will contain 'id', 'user_id', 'friend_id', 'status', 'created_at', 'updated_at'.
 * 'user_id' is the ID of the user who initiated the friendship/request.
 */
function getFriendshipStatus($userId, $friendId) {
    $conn = getDbConnection();

    // Check if there's a friendship record in either direction
    $stmt = $conn->prepare("
        SELECT id, user_id, friend_id, status, created_at, updated_at FROM friends
        WHERE (user_id = ? AND friend_id = ?)
           OR (user_id = ? AND friend_id = ?)
    ");
    $stmt->bind_param("iiii", $userId, $friendId, $friendId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $friendship = $result->fetch_assoc();
        $conn->close();
        return $friendship; // Return the full record
    }

    $conn->close();
    return false;
}

/**
 * Check if a user has blocked another user
 * @param int $userId Current user ID
 * @param int $targetId Target user ID to check
 * @return bool True if blocked, false otherwise
 */
function isUserBlocked($userId, $targetId) {
    $conn = getDbConnection();

    $stmt = $conn->prepare("
        SELECT status FROM friends
        WHERE user_id = ? AND friend_id = ? AND status = 'blocked'
    ");
    $stmt->bind_param("ii", $userId, $targetId);
    $stmt->execute();
    $result = $stmt->get_result();

    $isBlocked = $result->num_rows > 0;
    $conn->close();

    return $isBlocked;
}

/**
 * Format timestamp into human readable format
 * @param string $timestamp MySQL timestamp
 * @return string Formatted time (e.g., "2 hours ago")
 */
function formatTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
    } else {
        return date("F j, Y", $time);
    }
}

/**
 * Generate a CSRF token and store in session
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Check if CSRF token is valid
 * @param string $token Token to validate
 * @return bool True if valid, false otherwise
 */
function isValidCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Count unread messages for a user
 * @param int $userId User ID
 * @return int Number of unread messages
 */
function countUnreadMessages($userId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM messages
        WHERE receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();

    return $row['count'];
}

/**
 * Count pending friend requests for a user
 * @param int $userId User ID
 * @return int Number of pending friend requests
 */
function countPendingFriendRequests($userId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM friends
        WHERE friend_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();

    return $row['count'];
}

/**
 * Apply user customization to page
 * @param array $customization User customization settings
 * @return string CSS to apply customization
 */
function applyUserCustomization($customization) {
    $css = '';

    if (!empty($customization['background_image'])) {
        $css .= "body { background-image: url('" . $customization['background_image'] . "'); background-size: cover; background-attachment: fixed; }";
    }

    if (!empty($customization['background_color'])) {
        $css .= "body { background-color: " . $customization['background_color'] . "; }";
    }

    if (!empty($customization['text_color'])) {
        $css .= "body { color: " . $customization['text_color'] . "; }";
    }

    if (!empty($customization['link_color'])) {
        $css .= "a, .link-color { color: " . $customization['link_color'] . "; }";
    }

    if (!empty($customization['theme_color'])) {
        $css .= ":root { --accent: " . $customization['theme_color'] . "; --accent-hover: " . adjustBrightness($customization['theme_color'], -15) . "; }";
    }

    // Add custom CSS if provided
    if (!empty($customization['custom_css'])) {
        $css .= $customization['custom_css'];
    }

    return $css;
}

/**
 * Adjust brightness of a hex color
 * @param string $hex Hex color
 * @param int $steps Steps to adjust (-255 to 255)
 * @return string Adjusted hex color
 */
function adjustBrightness($hex, $steps) {
    // Remove # if present
    $hex = ltrim($hex, '#');

    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Adjust brightness
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));

    // Convert back to hex
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

/**
 * Create a new notification for a user
 * @param int $userId User ID to notify
 * @param string $type Notification type
 * @param string $message Notification message
 * @param int $fromUserId User ID that triggered notification (optional)
 * @param int $contentId Related content ID (optional)
 * @return bool Success status
 */
function createNotification($userId, $type, $message, $fromUserId = null, $contentId = null) {
    $conn = getDbConnection();

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, from_user_id, type, message, content_id, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iissi", $userId, $fromUserId, $type, $message, $contentId);
    $success = $stmt->execute();

    $conn->close();
    return $success;
}

/**
 * Set timestamp fields for insert operations
 * MariaDB compatibility function to replace triggers
 * @param string $table Table name
 * @param array &$data Data array for insert
 * @return void
 */
function setInsertTimestamps($table, &$data) {
    $now = date('Y-m-d H:i:s');

    // Add created_at timestamp for all tables
    $data['created_at'] = $now;

    // Add table-specific timestamps
    switch ($table) {
        case 'posts':
        case 'friends':
        case 'reports':
        case 'user_customization':
            $data['updated_at'] = $now;
            break;

        case 'saved_posts':
            $data['saved_at'] = $now;
            break;
    }
}

/**
 * Set timestamp fields for update operations
 * MariaDB compatibility function to replace triggers
 * @param string $table Table name
 * @param array &$data Data array for update
 * @return void
 */
function setUpdateTimestamps($table, &$data) {
    $now = date('Y-m-d H:i:s');

    // Add updated_at timestamp for tables that have it
    switch ($table) {
        case 'posts':
        case 'friends':
        case 'reports':
        case 'user_customization':
            $data['updated_at'] = $now;
            break;
    }
}
