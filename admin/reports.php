<?php
/**
 * Admin Reports Management
 * Review and manage user reports
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
$message = '';
$messageType = '';

// Handle report actions
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['csrf_token'])) {
    // Verify CSRF token
    if (!isValidCSRFToken($_GET['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_GET['action'];
        $reportId = (int)$_GET['id'];
        
        if ($reportId <= 0) {
            $message = 'Invalid report ID.';
            $messageType = 'error';
        } else {
            switch ($action) {
                case 'dismiss':
                    // Dismiss the report
                    $stmt = $conn->prepare("
                        UPDATE reports
                        SET status = 'dismissed', admin_notes = 'Dismissed by admin', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->bind_param("i", $reportId);
                    
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = 'Report has been dismissed.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update report status.';
                        $messageType = 'error';
                    }
                    break;
                    
                case 'review':
                    // Mark as reviewed
                    $stmt = $conn->prepare("
                        UPDATE reports
                        SET status = 'reviewed', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->bind_param("i", $reportId);
                    
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = 'Report has been marked as reviewed.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update report status.';
                        $messageType = 'error';
                    }
                    break;
                    
                case 'delete_content':
                    // Get report details
                    $reportStmt = $conn->prepare("
                        SELECT reported_user_id, post_id, comment_id
                        FROM reports
                        WHERE id = ?
                    ");
                    $reportStmt->bind_param("i", $reportId);
                    $reportStmt->execute();
                    $report = $reportStmt->get_result()->fetch_assoc();
                    
                    $deleted = false;
                    $contentType = '';
                    
                    if ($report) {
                        // Delete reported content
                        if ($report['post_id']) {
                            $contentType = 'post';
                            $deleteStmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
                            $deleteStmt->bind_param("i", $report['post_id']);
                            $deleted = $deleteStmt->execute() && $deleteStmt->affected_rows > 0;
                        } elseif ($report['comment_id']) {
                            $contentType = 'comment';
                            $deleteStmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
                            $deleteStmt->bind_param("i", $report['comment_id']);
                            $deleted = $deleteStmt->execute() && $deleteStmt->affected_rows > 0;
                        }
                        
                        if ($deleted) {
                            // Update report status
                            $updateStmt = $conn->prepare("
                                UPDATE reports
                                SET status = 'actioned', admin_notes = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $notes = "Deleted reported $contentType";
                            $updateStmt->bind_param("si", $notes, $reportId);
                            $updateStmt->execute();
                            
                            $message = "The reported $contentType has been deleted.";
                            $messageType = 'success';
                        } else {
                            $message = "Failed to delete the reported content. It may have already been removed.";
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Report not found.';
                        $messageType = 'error';
                    }
                    break;
            }
        }
    }
}

// Handle POST action for adding admin notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_notes') {
    // Verify CSRF token
    if (!isValidCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
        $adminNotes = isset($_POST['admin_notes']) ? sanitize($_POST['admin_notes']) : '';
        
        if ($reportId <= 0) {
            $message = 'Invalid report ID.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("
                UPDATE reports
                SET admin_notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("si", $adminNotes, $reportId);
            
            if ($stmt->execute()) {
                $message = 'Admin notes have been updated.';
                $messageType = 'success';
            } else {
                $message = 'Failed to update admin notes.';
                $messageType = 'error';
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Get detailed report if ID is provided
$report = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $reportId = (int)$_GET['id'];
    
    $reportStmt = $conn->prepare("
        SELECT r.*, 
               u1.username as reporter_username,
               u2.username as reported_username,
               p.content as post_content,
               p.image as post_image,
               c.content as comment_content
        FROM reports r
        JOIN users u1 ON r.reporter_id = u1.id
        LEFT JOIN users u2 ON r.reported_user_id = u2.id
        LEFT JOIN posts p ON r.post_id = p.id
        LEFT JOIN comments c ON r.comment_id = c.id
        WHERE r.id = ?
    ");
    $reportStmt->bind_param("i", $reportId);
    $reportStmt->execute();
    $report = $reportStmt->get_result()->fetch_assoc();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 15;
$offset = ($page - 1) * $itemsPerPage;

// Filtering
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$typeFilter = isset($_GET['type']) ? sanitize($_GET['type']) : '';

// Build query
$whereClause = "1=1";
$params = [];
$types = "";

if (!empty($statusFilter)) {
    $whereClause .= " AND r.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (!empty($typeFilter)) {
    switch ($typeFilter) {
        case 'post':
            $whereClause .= " AND r.post_id IS NOT NULL";
            break;
        case 'comment':
            $whereClause .= " AND r.comment_id IS NOT NULL";
            break;
        case 'user':
            $whereClause .= " AND r.reported_user_id IS NOT NULL AND r.post_id IS NULL AND r.comment_id IS NULL";
            break;
    }
}

// Count total reports matching the filter
$countQuery = "SELECT COUNT(*) as total FROM reports r WHERE $whereClause";
$countStmt = $conn->prepare($countQuery);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$countResult = $countStmt->get_result();
$totalReports = $countResult->fetch_assoc()['total'];

// Calculate total pages
$totalPages = ceil($totalReports / $itemsPerPage);

// Get reports with pagination
$reportsQuery = "
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
    WHERE $whereClause
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";

// Add pagination parameters
$params[] = $itemsPerPage;
$params[] = $offset;
$types .= "ii";

$reportsStmt = $conn->prepare($reportsQuery);
$reportsStmt->bind_param($types, ...$params);
$reportsStmt->execute();
$reportsResult = $reportsStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Management | ConnectHub Admin</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                    <li class="active">
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
                    <h1>Reports Management</h1>
                    <p>Review and manage user reports</p>
                </div>
                
                <div class="admin-user">
                    <?php $user = getUserById($_SESSION['user_id']); ?>
                    <img src="<?php echo getUserAvatar($_SESSION['user_id']); ?>" alt="<?php echo $user['username']; ?>" class="user-avatar-small">
                    <span><?php echo $user['username']; ?></span>
                </div>
            </header>
            
            <?php if (!empty($message)): ?>
                <div class="admin-alert <?php echo $messageType; ?>">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <p><?php echo $message; ?></p>
                    <button class="close-alert">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if ($report): ?>
                <!-- Single report view -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h3>Report Details #<?php echo $report['id']; ?></h3>
                        <a href="reports.php" class="btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to All Reports
                        </a>
                    </div>
                    <div class="admin-card-body">
                        <div class="report-details">
                            <div class="report-meta">
                                <div class="report-status">
                                    <?php
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
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($report['status']); ?>
                                    </span>
                                    <span class="report-time">Reported <?php echo formatTimeAgo($report['created_at']); ?></span>
                                </div>
                                
                                <div class="report-users">
                                    <div class="report-user">
                                        <span>Reporter:</span>
                                        <a href="../profile.php?id=<?php echo $report['reporter_id']; ?>" class="user-link">
                                            <?php echo htmlspecialchars($report['reporter_username']); ?>
                                        </a>
                                    </div>
                                    
                                    <?php if ($report['reported_user_id']): ?>
                                        <div class="report-user">
                                            <span>Reported User:</span>
                                            <a href="../profile.php?id=<?php echo $report['reported_user_id']; ?>" class="user-link">
                                                <?php echo htmlspecialchars($report['reported_username']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="report-reason">
                                <h4>Report Reason</h4>
                                <p><?php echo htmlspecialchars($report['reason']); ?></p>
                            </div>
                            
                            <div class="reported-content">
                                <h4>Reported Content</h4>
                                <?php if ($report['post_id']): ?>
                                    <div class="content-type">Post</div>
                                    <div class="content-preview">
                                        <p><?php echo htmlspecialchars($report['post_content']); ?></p>
                                        <?php if ($report['post_image']): ?>
                                            <div class="content-image">
                                                <img src="<?php echo htmlspecialchars($report['post_image']); ?>" alt="Post image">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="../index.php?post=<?php echo $report['post_id']; ?>" class="btn-sm" target="_blank">
                                        <i class="fas fa-external-link-alt"></i> View Post
                                    </a>
                                <?php elseif ($report['comment_id']): ?>
                                    <div class="content-type">Comment</div>
                                    <div class="content-preview">
                                        <p><?php echo htmlspecialchars($report['comment_content']); ?></p>
                                    </div>
                                    <?php
                                    // Get post ID for the comment
                                    $commentStmt = $conn->prepare("SELECT post_id FROM comments WHERE id = ?");
                                    $commentStmt->bind_param("i", $report['comment_id']);
                                    $commentStmt->execute();
                                    $commentResult = $commentStmt->get_result();
                                    if ($commentResult->num_rows > 0) {
                                        $postId = $commentResult->fetch_assoc()['post_id'];
                                        echo '<a href="../index.php?post=' . $postId . '#comment-' . $report['comment_id'] . '" class="btn-sm" target="_blank">';
                                        echo '<i class="fas fa-external-link-alt"></i> View Comment';
                                        echo '</a>';
                                    }
                                    ?>
                                <?php else: ?>
                                    <div class="content-type">User</div>
                                    <div class="content-preview">
                                        <p>User reported for inappropriate behavior or profile content.</p>
                                    </div>
                                    <a href="../profile.php?id=<?php echo $report['reported_user_id']; ?>" class="btn-sm" target="_blank">
                                        <i class="fas fa-external-link-alt"></i> View Profile
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="admin-notes">
                                <h4>Admin Notes</h4>
                                <form action="reports.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="add_notes">
                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                    
                                    <textarea name="admin_notes" id="admin-notes" rows="4" class="form-control"><?php echo htmlspecialchars($report['admin_notes'] ?? ''); ?></textarea>
                                    <button type="submit" class="btn btn-sm">Save Notes</button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="report-actions">
                            <?php if ($report['status'] === 'pending'): ?>
                                <a href="reports.php?action=review&id=<?php echo $report['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn">
                                    <i class="fas fa-check"></i> Mark as Reviewed
                                </a>
                                <a href="reports.php?action=dismiss&id=<?php echo $report['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn">
                                    <i class="fas fa-times"></i> Dismiss
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($report['post_id'] || $report['comment_id']): ?>
                                <button class="btn btn-danger" onclick="confirmDeleteContent(<?php echo $report['id']; ?>, '<?php echo $report['post_id'] ? 'post' : 'comment'; ?>')">
                                    <i class="fas fa-trash-alt"></i> Delete Content
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($report['reported_user_id']): ?>
                                <button class="btn btn-danger" onclick="showBlockModal(<?php echo $report['reported_user_id']; ?>, '<?php echo htmlspecialchars($report['reported_username']); ?>', <?php echo $report['id']; ?>)">
                                    <i class="fas fa-ban"></i> Block User
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Reports list -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h3>All Reports</h3>
                        <div class="filter-controls">
                            <form action="reports.php" method="GET" class="filter-form">
                                <select name="status" class="select-filter" onchange="this.form.submit()">
                                    <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="reviewed" <?php echo $statusFilter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                    <option value="actioned" <?php echo $statusFilter === 'actioned' ? 'selected' : ''; ?>>Actioned</option>
                                    <option value="dismissed" <?php echo $statusFilter === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                                </select>
                                
                                <select name="type" class="select-filter" onchange="this.form.submit()">
                                    <option value="" <?php echo $typeFilter === '' ? 'selected' : ''; ?>>All Types</option>
                                    <option value="post" <?php echo $typeFilter === 'post' ? 'selected' : ''; ?>>Posts</option>
                                    <option value="comment" <?php echo $typeFilter === 'comment' ? 'selected' : ''; ?>>Comments</option>
                                    <option value="user" <?php echo $typeFilter === 'user' ? 'selected' : ''; ?>>Users</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div class="responsive-table">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Reporter</th>
                                        <th>Type</th>
                                        <th>Reported</th>
                                        <th>Reason</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($reportsResult->num_rows > 0): ?>
                                        <?php while ($report = $reportsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $report['id']; ?></td>
                                                <td>
                                                    <a href="../profile.php?id=<?php echo $report['reporter_id']; ?>" class="user-link">
                                                        <?php echo htmlspecialchars($report['reporter_username']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo $report['report_type']; ?></td>
                                                <td>
                                                    <?php if ($report['reported_user_id']): ?>
                                                        <a href="../profile.php?id=<?php echo $report['reported_user_id']; ?>" class="user-link">
                                                            <?php echo htmlspecialchars($report['reported_username']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td class="truncate"><?php echo htmlspecialchars(substr($report['reason'], 0, 50)) . (strlen($report['reason']) > 50 ? '...' : ''); ?></td>
                                                <td><?php echo formatTimeAgo($report['created_at']); ?></td>
                                                <td>
                                                    <?php
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
                                                    ?>
                                                    <span class="status-badge <?php echo $statusClass; ?>">
                                                        <?php echo ucfirst($report['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="actions-cell">
                                                    <a href="reports.php?id=<?php echo $report['id']; ?>" class="btn-icon" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($report['status'] === 'pending'): ?>
                                                        <a href="reports.php?action=review&id=<?php echo $report['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn-icon" title="Mark as Reviewed">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                        <a href="reports.php?action=dismiss&id=<?php echo $report['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn-icon" title="Dismiss">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="no-data">No reports found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&type=<?php echo urlencode($typeFilter); ?>" class="pagination-arrow">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $startPage + 4);
                                
                                if ($endPage - $startPage < 4 && $startPage > 1) {
                                    $startPage = max(1, $endPage - 4);
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&type=<?php echo urlencode($typeFilter); ?>" class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&type=<?php echo urlencode($typeFilter); ?>" class="pagination-arrow">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> ConnectHub. All rights reserved.</p>
            </footer>
        </div>
    </div>
    
    <!-- Block user modal -->
    <div id="block-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Block User</h3>
                <button class="modal-close" onclick="closeModal('block-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="block-form" action="users.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="block">
                    <input type="hidden" name="user_id" id="block-user-id">
                    <input type="hidden" name="report_id" id="block-report-id">
                    
                    <div class="form-group">
                        <label for="block-duration">Block Duration (days):</label>
                        <select name="block_duration" id="block-duration" class="form-control">
                            <option value="1">1 day</option>
                            <option value="3">3 days</option>
                            <option value="7">7 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="90">90 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="block-reason">Reason:</label>
                        <textarea name="block_reason" id="block-reason" class="form-control" rows="3" required></textarea>
                        <small>This reason will be shown to the user when they attempt to log in.</small>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn" onclick="closeModal('block-modal')">Cancel</button>
                        <button type="submit" class="btn btn-danger">Block User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete content confirmation modal -->
    <div id="delete-content-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Delete Reported Content</h3>
                <button class="modal-close" onclick="closeModal('delete-content-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p id="delete-content-message">Are you sure you want to delete this content? This action cannot be undone.</p>
                
                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeModal('delete-content-modal')">Cancel</button>
                    <a href="#" id="delete-content-link" class="btn btn-danger">Delete Content</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Handle close alert button
        document.querySelectorAll('.close-alert').forEach(button => {
            button.addEventListener('click', function() {
                this.parentElement.style.display = 'none';
            });
        });
        
        // Show block modal
        function showBlockModal(userId, username, reportId = null) {
            document.getElementById('block-user-id').value = userId;
            document.getElementById('block-modal').classList.add('active');
            
            if (reportId) {
                document.getElementById('block-report-id').value = reportId;
            } else {
                document.getElementById('block-report-id').value = '';
            }
            
            document.getElementById('block-reason').focus();
        }
        
        // Confirm delete content
        function confirmDeleteContent(reportId, contentType) {
            const message = `Are you sure you want to delete this ${contentType}? This action cannot be undone.`;
            document.getElementById('delete-content-message').textContent = message;
            
            const deleteLink = `reports.php?action=delete_content&id=${reportId}&csrf_token=<?php echo $csrf_token; ?>`;
            document.getElementById('delete-content-link').setAttribute('href', deleteLink);
            
            document.getElementById('delete-content-modal').classList.add('active');
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Auto-hide alert after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.admin-alert').forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>
<?php $conn->close(); ?>