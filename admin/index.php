<?php
/**
 * Admin Dashboard
 * Main admin panel for site management and moderation
 */

session_start();
require_once '../config.php';
require_once '../functions.php';

// Redirect if not logged in or not admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get user stats
$conn = getDbConnection();

// Total users count
$totalUsersQuery = $conn->query("SELECT COUNT(*) as count FROM users");
$totalUsers = $totalUsersQuery->fetch_assoc()['count'];

// Total posts count
$totalPostsQuery = $conn->query("SELECT COUNT(*) as count FROM posts");
$totalPosts = $totalPostsQuery->fetch_assoc()['count'];

// Reports count
$pendingReportsQuery = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
$pendingReports = $pendingReportsQuery->fetch_assoc()['count'];

// Users registered in last 7 days
$newUsersQuery = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$newUsers = $newUsersQuery->fetch_assoc()['count'];

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | ConnectHub</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Chart.js for statistics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">
    <div class="admin-container">
        <!-- Admin sidebar -->
        <div class="admin-sidebar">
            <div class="admin-logo">
                <h2>ConnectHub</h2>
                <span class="admin-badge">Admin</span>
            </div>
            
            <nav class="admin-nav">
                <ul>
                    <li class="active">
                        <a href="index.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php">
                            <i class="fas fa-users"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-flag"></i>
                            <span>Reports</span>
                            <?php if ($pendingReports > 0): ?>
                                <span class="notification-badge"><?php echo $pendingReports; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="analytics.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Analytics</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="admin-sidebar-footer">
                <a href="../index.php">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Site</span>
                </a>
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main content -->
        <div class="admin-content">
            <header class="admin-header">
                <div class="admin-header-title">
                    <h1>Dashboard</h1>
                    <p>Welcome to the ConnectHub Admin Panel</p>
                </div>
                
                <div class="admin-user">
                    <?php $user = getUserById($_SESSION['user_id']); ?>
                    <img src="<?php echo getUserAvatar($_SESSION['user_id']); ?>" alt="<?php echo $user['username']; ?>" class="user-avatar-small">
                    <span><?php echo $user['username']; ?></span>
                </div>
            </header>
            
            <!-- Stats cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-inner">
                        <div class="stat-card-front">
                            <div class="stat-icon blue">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Users</h3>
                                <p class="stat-number"><?php echo $totalUsers; ?></p>
                                <p class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i> <?php echo $newUsers; ?> new this week
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-inner">
                        <div class="stat-card-front">
                            <div class="stat-icon green">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Posts</h3>
                                <p class="stat-number"><?php echo $totalPosts; ?></p>
                                <?php
                                // Get posts from last week
                                $lastWeekPostsQuery = $conn->query("SELECT COUNT(*) as count FROM posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                                $lastWeekPosts = $lastWeekPostsQuery->fetch_assoc()['count'];
                                ?>
                                <p class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i> <?php echo $lastWeekPosts; ?> new this week
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-inner">
                        <div class="stat-card-front">
                            <div class="stat-icon red">
                                <i class="fas fa-flag"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Reports</h3>
                                <p class="stat-number"><?php echo $pendingReports; ?></p>
                                <p class="stat-label">pending review</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-inner">
                        <div class="stat-card-front">
                            <div class="stat-icon purple">
                                <i class="fas fa-comment"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Comments</h3>
                                <?php
                                // Get total comments
                                $commentsQuery = $conn->query("SELECT COUNT(*) as count FROM comments");
                                $totalComments = $commentsQuery->fetch_assoc()['count'];
                                ?>
                                <p class="stat-number"><?php echo $totalComments; ?></p>
                                <?php
                                // Get comments from last week
                                $lastWeekCommentsQuery = $conn->query("SELECT COUNT(*) as count FROM comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                                $lastWeekComments = $lastWeekCommentsQuery->fetch_assoc()['count'];
                                ?>
                                <p class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i> <?php echo $lastWeekComments; ?> new this week
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent activity and posts chart -->
            <div class="admin-flex-grid">
                <div class="admin-card activity-card">
                    <div class="admin-card-header">
                        <h3>Recent Activity</h3>
                        <div class="admin-card-actions">
                            <button class="btn-icon" id="refresh-activity">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div class="activity-list">
                            <?php
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
                            
                            if ($recentActivitiesQuery->num_rows > 0) {
                                while ($activity = $recentActivitiesQuery->fetch_assoc()) {
                                    echo '<div class="activity-item">';
                                    
                                    switch ($activity['type']) {
                                        case 'report':
                                            echo '<div class="activity-icon report-icon"><i class="fas fa-flag"></i></div>';
                                            echo '<div class="activity-details">';
                                            echo '<p><strong>' . htmlspecialchars($activity['username']) . '</strong> reported a ' . htmlspecialchars($activity['reported_type']) . '</p>';
                                            echo '<a href="reports.php?id=' . $activity['id'] . '" class="activity-action">Review</a>';
                                            break;
                                            
                                        case 'new_user':
                                            echo '<div class="activity-icon user-icon"><i class="fas fa-user-plus"></i></div>';
                                            echo '<div class="activity-details">';
                                            echo '<p><strong>' . htmlspecialchars($activity['username']) . '</strong> joined ConnectHub</p>';
                                            echo '<a href="users.php?id=' . $activity['id'] . '" class="activity-action">View Profile</a>';
                                            break;
                                            
                                        case 'new_post':
                                            echo '<div class="activity-icon post-icon"><i class="fas fa-file-alt"></i></div>';
                                            echo '<div class="activity-details">';
                                            echo '<p><strong>' . htmlspecialchars($activity['username']) . '</strong> created a new post</p>';
                                            echo '<a href="../index.php?post=' . $activity['id'] . '" class="activity-action">View Post</a>';
                                            break;
                                    }
                                    
                                    echo '<span class="activity-time">' . formatTimeAgo($activity['timestamp']) . '</span>';
                                    echo '</div></div>';
                                }
                            } else {
                                echo '<p class="no-data">No recent activity</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="admin-card chart-card">
                    <div class="admin-card-header">
                        <h3>Posts Activity</h3>
                        <div class="admin-card-actions">
                            <select id="time-range">
                                <option value="week">Last 7 days</option>
                                <option value="month">Last 30 days</option>
                                <option value="year">Last 12 months</option>
                            </select>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <canvas id="posts-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Recent reports -->
            <div class="admin-card full-width">
                <div class="admin-card-header">
                    <h3>Recent Reports</h3>
                    <a href="reports.php" class="view-all">View All</a>
                </div>
                <div class="admin-card-body">
                    <div class="responsive-table">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Reporter</th>
                                    <th>Type</th>
                                    <th>Reason</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get recent reports
                                $recentReportsQuery = $conn->query("
                                    SELECT r.*, 
                                           u1.username as reporter_username,
                                           u2.username as reported_username,
                                           CASE
                                               WHEN r.post_id IS NOT NULL THEN 'Post'
                                               WHEN r.comment_id IS NOT NULL THEN 'Comment'
                                               ELSE 'User'
                                           END as report_type
                                    FROM reports r
                                    JOIN users u1 ON r.reporter_id = u1.id
                                    LEFT JOIN users u2 ON r.reported_user_id = u2.id
                                    ORDER BY r.created_at DESC
                                    LIMIT 5
                                ");
                                
                                if ($recentReportsQuery->num_rows > 0) {
                                    while ($report = $recentReportsQuery->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($report['reporter_username']) . '</td>';
                                        echo '<td>' . $report['report_type'] . '</td>';
                                        echo '<td class="truncate">' . htmlspecialchars($report['reason']) . '</td>';
                                        echo '<td>' . formatTimeAgo($report['created_at']) . '</td>';
                                        
                                        // Status badge
                                        $statusClass = '';
                                        switch ($report['status']) {
                                            case 'pending':
                                                $statusClass = 'status-pending';
                                                break;
                                            case 'reviewed':
                                                $statusClass = 'status-reviewed';
                                                break;
                                            case 'actioned':
                                                $statusClass = 'status-actioned';
                                                break;
                                            case 'dismissed':
                                                $statusClass = 'status-dismissed';
                                                break;
                                        }
                                        
                                        echo '<td><span class="status-badge ' . $statusClass . '">' . ucfirst($report['status']) . '</span></td>';
                                        
                                        // Actions
                                        echo '<td class="actions-cell">';
                                        echo '<a href="reports.php?id=' . $report['id'] . '" class="btn-icon" title="Review"><i class="fas fa-eye"></i></a>';
                                        
                                        if ($report['status'] === 'pending') {
                                            echo '<a href="reports.php?action=dismiss&id=' . $report['id'] . '&csrf_token=' . $csrf_token . '" class="btn-icon" title="Dismiss"><i class="fas fa-times"></i></a>';
                                            
                                            if ($report['reported_user_id']) {
                                                echo '<a href="users.php?action=block&id=' . $report['reported_user_id'] . '&report=' . $report['id'] . '&csrf_token=' . $csrf_token . '" class="btn-icon" title="Block User"><i class="fas fa-ban"></i></a>';
                                            }
                                        }
                                        
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="no-data">No reports found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> ConnectHub. All rights reserved.</p>
            </footer>
        </div>
    </div>
    
    <!-- Chart data -->
    <script>
        // Generate posts data for the chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('posts-chart').getContext('2d');
            
            <?php
            // Get post data for the last 7 days
            $postsData = [];
            $labels = [];
            
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('D', strtotime($date));
                
                $query = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM posts 
                    WHERE DATE(created_at) = ?
                ");
                $query->bind_param("s", $date);
                $query->execute();
                $result = $query->get_result();
                $row = $result->fetch_assoc();
                
                $postsData[] = $row['count'];
            }
            ?>
            
            // Initial chart data
            const initialData = {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Posts',
                    data: <?php echo json_encode($postsData); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.1
                }]
            };
            
            // Create chart
            const postsChart = new Chart(ctx, {
                type: 'line',
                data: initialData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Handle time range change
            document.getElementById('time-range').addEventListener('change', function() {
                const range = this.value;
                
                // Fetch data based on selected range
                fetch(`admin/ajax/get_post_stats.php?range=${range}&csrf_token=<?php echo $csrf_token; ?>`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update chart data
                            postsChart.data.labels = data.labels;
                            postsChart.data.datasets[0].data = data.values;
                            postsChart.update();
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching post stats:', error);
                    });
            });
            
            // Handle activity refresh
            document.getElementById('refresh-activity').addEventListener('click', function() {
                const activityList = document.querySelector('.activity-list');
                activityList.innerHTML = '<div class="loading-spinner"><i class="fas fa-circle-notch fa-spin"></i></div>';
                
                fetch('admin/ajax/get_recent_activity.php?csrf_token=<?php echo $csrf_token; ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            activityList.innerHTML = data.html;
                        } else {
                            activityList.innerHTML = '<p class="error-message">Failed to load activities</p>';
                        }
                    })
                    .catch(error => {
                        activityList.innerHTML = '<p class="error-message">Failed to load activities</p>';
                        console.error('Error fetching activity:', error);
                    });
            });
        });
    </script>
    
    <!-- Notification container -->
    <div id="notification-container" class="notification-container"></div>
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
</body>
</html>
<?php $conn->close(); ?>