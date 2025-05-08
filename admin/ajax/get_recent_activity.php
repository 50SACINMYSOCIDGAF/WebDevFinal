<?php
/**
 * AJAX endpoint for fetching recent activity
 * Returns HTML for the activity feed on the admin dashboard
 */

session_start();
require_once '../../config.php';
require_once '../../functions.php';

// Verify admin and CSRF
if (!isLoggedIn() || !isAdmin() || !isValidCSRFToken($_GET['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDbConnection();

// Get recent activities (reports, new users, etc.)
$recentActivitiesQuery = $conn->query("
    (SELECT 
        'report' as type,
        r.id as id,
        r.created_at as timestamp,
        u.username as username,
        CASE
            WHEN r.post_id IS NOT NULL THEN 'post'
            WHEN r.comment_id IS NOT NULL THEN 'comment'
            ELSE 'user'
        END as reported_type
    FROM reports r
    JOIN users u ON r.reporter_id = u.id
    ORDER BY r.created_at DESC
    LIMIT 5)
    
    UNION
    
    (SELECT 
        'new_user' as type,
        u.id as id,
        u.created_at as timestamp,
        u.username as username,
        NULL as reported_type
    FROM users u
    ORDER BY u.created_at DESC
    LIMIT 5)
    
    UNION
    
    (SELECT 
        'new_post' as type,
        p.id as id,
        p.created_at as timestamp,
        u.username as username,
        NULL as reported_type
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 5)
    
    ORDER BY timestamp DESC
    LIMIT 10
");

// Build HTML output
$html = '';

if ($recentActivitiesQuery->num_rows > 0) {
    while ($activity = $recentActivitiesQuery->fetch_assoc()) {
        $html .= '<div class="activity-item">';
        
        switch ($activity['type']) {
            case 'report':
                $html .= '<div class="activity-icon report-icon"><i class="fas fa-flag"></i></div>';
                $html .= '<div class="activity-details">';
                $html .= '<p><strong>' . htmlspecialchars($activity['username']) . '</strong> reported a ' . htmlspecialchars($activity['reported_type']) . '</p>';
                $html .= '<a href="reports.php?id=' . $activity['id'] . '" class="activity-action">Review</a>';
                break;
                
            case 'new_user':
                $html .= '<div class="activity-icon user-icon"><i class="fas fa-user-plus"></i></div>';
                $html .= '<div class="activity-details">';
                $html .= '<p><strong>' . htmlspecialchars($activity['username']) . '</strong> joined ConnectHub</p>';
                $html .= '<a href="users.php?id=' . $activity['id'] . '" class="activity-action">View Profile</a>';
                break;
                
            case 'new_post':
                $html .= '<div class="activity-icon post-icon"><i class="fas fa-file-alt"></i></div>';
                $html .= '<div class="activity-details">';
                $html .= '<p><strong>' . htmlspecialchars($activity['username']) . '</strong> created a new post</p>';
                $html .= '<a href="../index.php?post=' . $activity['id'] . '" class="activity-action">View Post</a>';
                break;
        }
        
        $html .= '<span class="activity-time">' . formatTimeAgo($activity['timestamp']) . '</span>';
        $html .= '</div></div>';
    }
} else {
    $html = '<p class="no-data">No recent activity</p>';
}

$conn->close();

// Return the HTML
echo json_encode([
    'success' => true,
    'html' => $html
]);
?>