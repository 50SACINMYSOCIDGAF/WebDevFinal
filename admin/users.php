<?php
/**
 * Admin User Management
 * Allows admins to view, edit, and manage user accounts
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

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isValidCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        
        if ($userId <= 0) {
            $message = 'Invalid user ID.';
            $messageType = 'error';
        } else {
            switch ($action) {
                case 'delete':
                    // Delete user account
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
                    $stmt->bind_param("i", $userId);
                    
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = 'User account has been deleted successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete user account. Admin accounts cannot be deleted.';
                        $messageType = 'error';
                    }
                    break;
                    
                case 'block':
                    // Block user account
                    $reason = sanitize($_POST['block_reason'] ?? '');
                    $duration = (int)$_POST['block_duration'] ?? 30;
                    
                    // Calculate expiry date
                    $expiry = date('Y-m-d H:i:s', strtotime("+$duration days"));
                    
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET status = 'blocked', block_reason = ?, block_expiry = ?
                        WHERE id = ? AND is_admin = 0
                    ");
                    $stmt->bind_param("ssi", $reason, $expiry, $userId);
                    
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = 'User account has been blocked successfully.';
                        $messageType = 'success';
                        
                        // Update report if specified
                        if (isset($_POST['report_id']) && !empty($_POST['report_id'])) {
                            $reportId = (int)$_POST['report_id'];
                            $adminNotes = "User blocked for $duration days. Reason: $reason";
                            
                            $reportStmt = $conn->prepare("
                                UPDATE reports 
                                SET status = 'actioned', admin_notes = ?
                                WHERE id = ?
                            ");
                            $reportStmt->bind_param("si", $adminNotes, $reportId);
                            $reportStmt->execute();
                        }
                    } else {
                        $message = 'Failed to block user account. Admin accounts cannot be blocked.';
                        $messageType = 'error';
                    }
                    break;
                    
                case 'unblock':
                    // Unblock user account
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET status = 'active', block_reason = NULL, block_expiry = NULL
                        WHERE id = ?
                    ");
                    $stmt->bind_param("i", $userId);
                    
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = 'User account has been unblocked successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to unblock user account.';
                        $messageType = 'error';
                    }
                    break;
                    
                case 'make_admin':
                    // Make user an admin
                    $stmt = $conn->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
                    $stmt->bind_param("i", $userId);
                    
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = 'User has been granted admin privileges.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update user privileges.';
                        $messageType = 'error';
                    }
                    break;
                    
                case 'remove_admin':
                    // Remove admin privileges
                    // Don't allow removing your own admin privileges
                    if ($userId == $_SESSION['user_id']) {
                        $message = 'You cannot remove your own admin privileges.';
                        $messageType = 'error';
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET is_admin = 0 WHERE id = ?");
                        $stmt->bind_param("i", $userId);
                        
                        if ($stmt->execute() && $stmt->affected_rows > 0) {
                            $message = 'Admin privileges have been removed.';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to update user privileges.';
                            $messageType = 'error';
                        }
                    }
                    break;
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Search functionality
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query
$whereClause = "1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $whereClause .= " AND (username LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if (!empty($filterStatus)) {
    $whereClause .= " AND status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

// Count total users matching the search/filter
$countQuery = "SELECT COUNT(*) as total FROM users WHERE $whereClause";
$countStmt = $conn->prepare($countQuery);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$countResult = $countStmt->get_result();
$totalUsers = $countResult->fetch_assoc()['total'];

// Calculate total pages
$totalPages = ceil($totalUsers / $itemsPerPage);

// Get users with pagination
$usersQuery = "
    SELECT * 
    FROM users 
    WHERE $whereClause
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";

$usersStmt = $conn->prepare($usersQuery);

// Add pagination parameters
$params[] = $itemsPerPage;
$params[] = $offset;
$types .= "ii";

$usersStmt->bind_param($types, ...$params);
$usersStmt->execute();
$usersResult = $usersStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | ConnectHub Admin</title>
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
                    <li class="active">
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
                    <h1>User Management</h1>
                    <p>View and manage user accounts</p>
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
            
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>User Accounts</h3>
                    <div class="admin-card-actions">
                        <form action="users.php" method="GET" class="search-form">
                            <div class="search-container">
                                <input type="text" name="search" placeholder="Search by username or email" value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="search-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            
                            <select name="status" class="select-filter" onchange="this.form.submit()">
                                <option value="" <?php echo $filterStatus === '' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="blocked" <?php echo $filterStatus === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                <option value="suspended" <?php echo $filterStatus === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
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
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($usersResult->num_rows > 0): ?>
                                    <?php while ($user = $usersResult->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td class="user-cell">
                                                <img src="<?php echo getUserAvatar($user['id'], 'small'); ?>" alt="<?php echo $user['username']; ?>" class="user-avatar-mini">
                                                <span><?php echo htmlspecialchars($user['username']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php 
                                                $statusClass = '';
                                                switch ($user['status']) {
                                                    case 'active':
                                                        $statusClass = 'status-active';
                                                        break;
                                                    case 'blocked':
                                                        $statusClass = 'status-blocked';
                                                        break;
                                                    case 'suspended':
                                                        $statusClass = 'status-suspended';
                                                        break;
                                                }
                                                ?>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                                
                                                <?php if ($user['status'] === 'blocked' && !empty($user['block_expiry'])): ?>
                                                    <div class="status-expiry">
                                                        Until <?php echo date('M j, Y', strtotime($user['block_expiry'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $user['is_admin'] ? '<span class="admin-role">Admin</span>' : 'User'; ?>
                                            </td>
                                            <td><?php echo formatTimeAgo($user['created_at']); ?></td>
                                            <td class="actions-cell">
                                                <a href="../profile.php?id=<?php echo $user['id']; ?>" class="btn-icon" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <?php if ($user['status'] === 'blocked'): ?>
                                                        <button class="btn-icon" title="Unblock User" onclick="confirmAction('unblock', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            <i class="fas fa-unlock"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn-icon" title="Block User" onclick="showBlockModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($user['is_admin']): ?>
                                                        <button class="btn-icon" title="Remove Admin" onclick="confirmAction('remove_admin', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            <i class="fas fa-user-minus"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn-icon" title="Make Admin" onclick="confirmAction('make_admin', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            <i class="fas fa-user-shield"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button class="btn-icon btn-danger" title="Delete User" onclick="confirmAction('delete', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="no-data">No users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filterStatus); ?>" class="pagination-arrow">
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
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filterStatus); ?>" class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filterStatus); ?>" class="pagination-arrow">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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
            
            <!-- Confirmation modal -->
            <div id="confirm-modal" class="modal-overlay">
                <div class="modal-container">
                    <div class="modal-header">
                        <h3 id="confirm-title">Confirm Action</h3>
                        <button class="modal-close" onclick="closeModal('confirm-modal')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p id="confirm-message"></p>
                        
                        <form id="action-form" action="users.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" id="action-type">
                            <input type="hidden" name="user_id" id="action-user-id">
                            
                            <div class="modal-actions">
                                <button type="button" class="btn" onclick="closeModal('confirm-modal')">Cancel</button>
                                <button type="submit" class="btn btn-danger" id="confirm-button">Confirm</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> ConnectHub. All rights reserved.</p>
            </footer>
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
        
        // Confirm action
        function confirmAction(action, userId, username) {
            let title, message, buttonText;
            
            switch (action) {
                case 'delete':
                    title = 'Delete User Account';
                    message = `Are you sure you want to delete the account for ${username}? This action cannot be undone.`;
                    buttonText = 'Delete';
                    break;
                case 'unblock':
                    title = 'Unblock User';
                    message = `Are you sure you want to unblock ${username}?`;
                    buttonText = 'Unblock';
                    break;
                case 'make_admin':
                    title = 'Grant Admin Privileges';
                    message = `Are you sure you want to make ${username} an admin? They will have full access to the admin panel.`;
                    buttonText = 'Grant Admin';
                    break;
                case 'remove_admin':
                    title = 'Remove Admin Privileges';
                    message = `Are you sure you want to remove admin privileges from ${username}?`;
                    buttonText = 'Remove Admin';
                    break;
                default:
                    return;
            }
            
            document.getElementById('confirm-title').textContent = title;
            document.getElementById('confirm-message').textContent = message;
            document.getElementById('confirm-button').textContent = buttonText;
            document.getElementById('action-type').value = action;
            document.getElementById('action-user-id').value = userId;
            
            document.getElementById('confirm-modal').classList.add('active');
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