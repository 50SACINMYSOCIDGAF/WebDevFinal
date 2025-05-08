<?php
/**
 * Admin Analytics
 * Provides insights and statistics about platform usage
 */

session_start();
require_once '../config.php';
require_once '../functions.php';

// Redirect if not logged in or not admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$conn = getDbConnection();

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Get stats for different time periods
function getDateRange($period) {
    switch ($period) {
        case 'week':
            return [
                'start' => date('Y-m-d', strtotime('-7 days')),
                'end' => date('Y-m-d'),
                'format' => '%Y-%m-%d',
                'group' => 'day',
                'display' => 'l' // Day of week
            ];
        case 'month':
            return [
                'start' => date('Y-m-d', strtotime('-30 days')),
                'end' => date('Y-m-d'),
                'format' => '%Y-%m-%d',
                'group' => 'day',
                'display' => 'M j' // Jan 1
            ];
        case 'year':
            return [
                'start' => date('Y-m-d', strtotime('-12 months')),
                'end' => date('Y-m-d'),
                'format' => '%Y-%m',
                'group' => 'month',
                'display' => 'M Y' // Jan 2023
            ];
        default:
            return getDateRange('week');
    }
}

// Selected time period
$timePeriod = isset($_GET['period']) ? $_GET['period'] : 'week';
$range = getDateRange($timePeriod);

// Generate date series for the selected period
$dates = [];
$dateLabels = [];

if ($range['group'] === 'day') {
    $startDate = new DateTime($range['start']);
    $endDate = new DateTime($range['end']);
    $interval = new DateInterval('P1D');
    
    $datePeriod = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
    
    foreach ($datePeriod as $date) {
        $dates[] = $date->format('Y-m-d');
        $dateLabels[] = $date->format($range['display']);
    }
} else {
    $startDate = new DateTime($range['start']);
    $endDate = new DateTime($range['end']);
    $startDate->modify('first day of this month');
    
    while ($startDate <= $endDate) {
        $dates[] = $startDate->format('Y-m');
        $dateLabels[] = $startDate->format($range['display']);
        $startDate->modify('+1 month');
    }
}

// Get posts data
$postsData = array_fill(0, count($dates), 0);
$commentsData = array_fill(0, count($dates), 0);
$usersData = array_fill(0, count($dates), 0);
$likesData = array_fill(0, count($dates), 0);

if ($range['group'] === 'day') {
    // Posts per day
    $postsQuery = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM posts
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
    ");
    $postsQuery->bind_param("ss", $range['start'], $range['end']);
    
    // Comments per day
    $commentsQuery = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM comments
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
    ");
    $commentsQuery->bind_param("ss", $range['start'], $range['end']);
    
    // New users per day
    $usersQuery = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM users
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
    ");
    $usersQuery->bind_param("ss", $range['start'], $range['end']);
    
    // Likes per day
    $likesQuery = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM likes
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
    ");
    $likesQuery->bind_param("ss", $range['start'], $range['end']);
} else {
    // Posts per month
    $postsQuery = $conn->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count
        FROM posts
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ");
    $postsQuery->bind_param("ss", $range['start'], $range['end']);
    
    // Comments per month
    $commentsQuery = $conn->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count
        FROM comments
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ");
    $commentsQuery->bind_param("ss", $range['start'], $range['end']);
    
    // New users per month
    $usersQuery = $conn->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count
        FROM users
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ");
    $usersQuery->bind_param("ss", $range['start'], $range['end']);
    
    // Likes per month
    $likesQuery = $conn->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count
        FROM likes
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ");
    $likesQuery->bind_param("ss", $range['start'], $range['end']);
}

// Execute queries and populate data arrays
$postsQuery->execute();
$postsResult = $postsQuery->get_result();
while ($row = $postsResult->fetch_assoc()) {
    $index = array_search($row['date'], $dates);
    if ($index !== false) {
        $postsData[$index] = (int)$row['count'];
    }
}

$commentsQuery->execute();
$commentsResult = $commentsQuery->get_result();
while ($row = $commentsResult->fetch_assoc()) {
    $index = array_search($row['date'], $dates);
    if ($index !== false) {
        $commentsData[$index] = (int)$row['count'];
    }
}

$usersQuery->execute();
$usersResult = $usersQuery->get_result();
while ($row = $usersResult->fetch_assoc()) {
    $index = array_search($row['date'], $dates);
    if ($index !== false) {
        $usersData[$index] = (int)$row['count'];
    }
}

$likesQuery->execute();
$likesResult = $likesQuery->get_result();
while ($row = $likesResult->fetch_assoc()) {
    $index = array_search($row['date'], $dates);
    if ($index !== false) {
        $likesData[$index] = (int)$row['count'];
    }
}

// Get top users by post count
$topPostersQuery = $conn->query("
    SELECT u.id, u.username, u.profile_picture, COUNT(p.id) as post_count
    FROM users u
    JOIN posts p ON u.id = p.user_id
    GROUP BY u.id
    ORDER BY post_count DESC
    LIMIT 5
");

// Get top users by comment count
$topCommentersQuery = $conn->query("
    SELECT u.id, u.username, u.profile_picture, COUNT(c.id) as comment_count
    FROM users u
    JOIN comments c ON u.id = c.user_id
    GROUP BY u.id
    ORDER BY comment_count DESC
    LIMIT 5
");

// Get content engagement statistics
$contentStatsQuery = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM posts) as total_posts,
        (SELECT COUNT(*) FROM comments) as total_comments,
        (SELECT COUNT(*) FROM likes WHERE post_id IS NOT NULL) as total_post_likes,
        (SELECT COUNT(*) FROM likes WHERE comment_id IS NOT NULL) as total_comment_likes,
        (SELECT COUNT(*) FROM posts WHERE privacy = 'public') as public_posts,
        (SELECT COUNT(*) FROM posts WHERE privacy = 'friends') as friends_posts,
        (SELECT COUNT(*) FROM posts WHERE privacy = 'private') as private_posts,
        (SELECT COUNT(*) FROM posts WHERE image IS NOT NULL) as posts_with_images,
        (SELECT COUNT(*) FROM posts WHERE location_lat IS NOT NULL) as posts_with_location
");
$contentStats = $contentStatsQuery->fetch_assoc();

// Calculate post engagement rate
$postEngagementRate = 0;
if ($contentStats['total_posts'] > 0) {
    $postEngagementRate = round(($contentStats['total_comments'] + $contentStats['total_post_likes']) / $contentStats['total_posts'], 2);
}

// Get active vs inactive users
$activeUsersQuery = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(DISTINCT user_id) FROM posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as active_posters,
        (SELECT COUNT(DISTINCT user_id) FROM comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as active_commenters,
        (SELECT COUNT(DISTINCT user_id) FROM likes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as active_likers
");
$userActivityStats = $activeUsersQuery->fetch_assoc();

// Calculate active users (posted, commented or liked in last 30 days)
$activeUsers = $conn->query("
    SELECT COUNT(DISTINCT u.id) as count
    FROM users u
    LEFT JOIN posts p ON u.id = p.user_id AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    LEFT JOIN comments c ON u.id = c.user_id AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    LEFT JOIN likes l ON u.id = l.user_id AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE p.id IS NOT NULL OR c.id IS NOT NULL OR l.id IS NOT NULL
")->fetch_assoc()['count'];

$inactiveUsers = $userActivityStats['total_users'] - $activeUsers;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | ConnectHub Admin</title>
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
                    <li>
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
                            <?php 
                            $pendingReportsQuery = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
                            $pendingReports = $pendingReportsQuery->fetch_assoc()['count'];
                            if ($pendingReports > 0):
                            ?>
                                <span class="notification-badge"><?php echo $pendingReports; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="active">
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
                    <h1>Analytics</h1>
                    <p>Platform metrics and statistics</p>
                </div>
                
                <div class="admin-user">
                    <?php $user = getUserById($_SESSION['user_id']); ?>
                    <img src="<?php echo getUserAvatar($_SESSION['user_id']); ?>" alt="<?php echo $user['username']; ?>" class="user-avatar-small">
                    <span><?php echo $user['username']; ?></span>
                </div>
            </header>
            
            <!-- Time period selector -->
            <div class="period-selector">
                <a href="?period=week" class="period-link <?php echo $timePeriod === 'week' ? 'active' : ''; ?>">Last 7 Days</a>
                <a href="?period=month" class="period-link <?php echo $timePeriod === 'month' ? 'active' : ''; ?>">Last 30 Days</a>
                <a href="?period=year" class="period-link <?php echo $timePeriod === 'year' ? 'active' : ''; ?>">Last 12 Months</a>
            </div>
            
            <!-- Activity Charts -->
            <div class="admin-flex-grid">
                <div class="admin-card chart-card wide">
                    <div class="admin-card-header">
                        <h3>Platform Activity</h3>
                        <div class="admin-card-actions">
                            <button id="download-chart" class="btn-sm">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <canvas id="activity-chart"></canvas>
                    </div>
                </div>
                
                <div class="admin-card chart-card">
                    <div class="admin-card-header">
                        <h3>User Activity</h3>
                    </div>
                    <div class="admin-card-body">
                        <canvas id="user-activity-chart"></canvas>
                    </div>
                </div>
                
                <div class="admin-card chart-card">
                    <div class="admin-card-header">
                        <h3>Content Privacy</h3>
                    </div>
                    <div class="admin-card-body">
                        <canvas id="privacy-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Content Stats -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Content Engagement</h3>
                </div>
                <div class="admin-card-body">
                    <div class="stats-grid stats-grid-small">
                        <div class="stat-card stat-card-small">
                            <div class="stat-icon blue">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h4>Total Posts</h4>
                                <p class="stat-number"><?php echo number_format($contentStats['total_posts']); ?></p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-card-small">
                            <div class="stat-icon green">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="stat-info">
                                <h4>Total Comments</h4>
                                <p class="stat-number"><?php echo number_format($contentStats['total_comments']); ?></p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-card-small">
                            <div class="stat-icon red">
                                <i class="fas fa-thumbs-up"></i>
                            </div>
                            <div class="stat-info">
                                <h4>Total Likes</h4>
                                <p class="stat-number"><?php echo number_format($contentStats['total_post_likes'] + $contentStats['total_comment_likes']); ?></p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-card-small">
                            <div class="stat-icon purple">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-info">
                                <h4>Engagement Rate</h4>
                                <p class="stat-number"><?php echo $postEngagementRate; ?></p>
                                <p class="stat-label">interactions per post</p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-card-small">
                            <div class="stat-icon orange">
                                <i class="fas fa-image"></i>
                            </div>
                            <div class="stat-info">
                                <h4>Posts with Images</h4>
                                <p class="stat-number"><?php echo number_format($contentStats['posts_with_images']); ?></p>
                                <p class="stat-label"><?php echo $contentStats['total_posts'] > 0 ? round(($contentStats['posts_with_images'] / $contentStats['total_posts']) * 100) . '%' : '0%'; ?></p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-card-small">
                            <div class="stat-icon teal">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h4>Posts with Location</h4>
                                <p class="stat-number"><?php echo number_format($contentStats['posts_with_location']); ?></p>
                                <p class="stat-label"><?php echo $contentStats['total_posts'] > 0 ? round(($contentStats['posts_with_location'] / $contentStats['total_posts']) * 100) . '%' : '0%'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Users -->
            <div class="admin-flex-grid">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h3>Top Posters</h3>
                    </div>
                    <div class="admin-card-body">
                        <ul class="top-users-list">
                            <?php if ($topPostersQuery->num_rows > 0): ?>
                                <?php while ($poster = $topPostersQuery->fetch_assoc()): ?>
                                    <li class="top-user-item">
                                        <img src="<?php echo !empty($poster['profile_picture']) ? $poster['profile_picture'] : 'https://via.placeholder.com/50'; ?>" alt="<?php echo $poster['username']; ?>" class="user-avatar-small">
                                        <div class="top-user-info">
                                            <a href="../profile.php?id=<?php echo $poster['id']; ?>" class="user-link"><?php echo htmlspecialchars($poster['username']); ?></a>
                                            <span class="top-user-stat"><?php echo number_format($poster['post_count']); ?> posts</span>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <li class="no-data">No data available</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h3>Top Commenters</h3>
                    </div>
                    <div class="admin-card-body">
                        <ul class="top-users-list">
                            <?php if ($topCommentersQuery->num_rows > 0): ?>
                                <?php while ($commenter = $topCommentersQuery->fetch_assoc()): ?>
                                    <li class="top-user-item">
                                        <img src="<?php echo !empty($commenter['profile_picture']) ? $commenter['profile_picture'] : 'https://via.placeholder.com/50'; ?>" alt="<?php echo $commenter['username']; ?>" class="user-avatar-small">
                                        <div class="top-user-info">
                                            <a href="../profile.php?id=<?php echo $commenter['id']; ?>" class="user-link"><?php echo htmlspecialchars($commenter['username']); ?></a>
                                            <span class="top-user-stat"><?php echo number_format($commenter['comment_count']); ?> comments</span>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <li class="no-data">No data available</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> ConnectHub. All rights reserved.</p>
            </footer>
        </div>
    </div>
    
    <!-- Charts JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Platform Activity Chart
            const activityCtx = document.getElementById('activity-chart').getContext('2d');
            const activityChart = new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dateLabels); ?>,
                    datasets: [
                        {
                            label: 'Posts',
                            data: <?php echo json_encode($postsData); ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        },
                        {
                            label: 'Comments',
                            data: <?php echo json_encode($commentsData); ?>,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        },
                        {
                            label: 'Likes',
                            data: <?php echo json_encode($likesData); ?>,
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        },
                        {
                            label: 'New Users',
                            data: <?php echo json_encode($usersData); ?>,
                            backgroundColor: 'rgba(153, 102, 255, 0.2)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        }
                    ]
                },
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
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
            
            // User Activity Chart (active vs inactive)
            const userActivityCtx = document.getElementById('user-activity-chart').getContext('2d');
            const userActivityChart = new Chart(userActivityCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Active Users', 'Inactive Users'],
                    datasets: [{
                        data: [<?php echo $activeUsers; ?>, <?php echo $inactiveUsers; ?>],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(201, 203, 207, 0.8)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(201, 203, 207, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
            
            // Privacy Distribution Chart
            const privacyCtx = document.getElementById('privacy-chart').getContext('2d');
            const privacyChart = new Chart(privacyCtx, {
                type: 'pie',
                data: {
                    labels: ['Public', 'Friends Only', 'Private'],
                    datasets: [{
                        data: [
                            <?php echo $contentStats['public_posts']; ?>,
                            <?php echo $contentStats['friends_posts']; ?>,
                            <?php echo $contentStats['private_posts']; ?>
                        ],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(255, 99, 132, 0.8)'
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(255, 99, 132, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Export chart as image
            document.getElementById('download-chart').addEventListener('click', function() {
                const canvas = document.getElementById('activity-chart');
                const image = canvas.toDataURL('image/png');
                
                const link = document.createElement('a');
                link.href = image;
                link.download = 'connecthub-activity-<?php echo $timePeriod; ?>.png';
                link.click();
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>