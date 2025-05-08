<?php
/**
 * Friends Page
 * Displays the user's friends list and friend requests.
 */
session_start();
require_once 'functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = getDbConnection();

// --- Fetch Accepted Friends ---
$friends = [];
$friends_query = "
    SELECT
        u.id,
        u.username,
        u.profile_picture,
        u.last_login,
        f.updated_at as friends_since -- When the friendship was accepted/updated
    FROM
        users u
    JOIN
        friends f ON (u.id = f.friend_id AND f.user_id = ?) OR (u.id = f.user_id AND f.friend_id = ?)
    WHERE
        f.status = 'accepted'
        AND u.id != ? -- Exclude the current user
    ORDER BY
        -- Show recently active friends first, then alphabetically
        CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 0 ELSE 1 END,
        u.last_login DESC,
        u.username ASC;
";

$stmt = $conn->prepare($friends_query);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $friends[] = $row;
}
$stmt->close();

// --- Fetch Pending Friend Requests Received By User ---
$requests_received = [];
$requests_query = "
    SELECT u.id, u.username, u.profile_picture, f.created_at as request_time
    FROM users u
    JOIN friends f ON u.id = f.user_id -- Join based on who sent the request
    WHERE f.friend_id = ? -- The current user is the friend_id (receiver)
    AND f.status = 'pending'
    ORDER BY f.created_at DESC;
";
$stmt = $conn->prepare($requests_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$request_result = $stmt->get_result();
while ($row = $request_result->fetch_assoc()) {
    $requests_received[] = $row;
}
$stmt->close();

// --- Fetch Pending Friend Requests Sent By User ---
$requests_sent = [];
$sent_query = "
    SELECT u.id, u.username, u.profile_picture, f.created_at as request_time
    FROM users u
    JOIN friends f ON u.id = f.friend_id -- Join based on who received the request
    WHERE f.user_id = ? -- The current user is the user_id (sender)
    AND f.status = 'pending'
    ORDER BY f.created_at DESC;
";
$stmt = $conn->prepare($sent_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sent_result = $stmt->get_result();
while ($row = $sent_result->fetch_assoc()) {
    $requests_sent[] = $row;
}
$stmt->close();


$conn->close();

// Helper function for status
function get_last_seen_status($last_login_timestamp) {
    if (empty($last_login_timestamp)) {
        return '<span class="status-offline">Offline</span>';
    }
    $last_login_time = strtotime($last_login_timestamp);
    $now = time();
    $diff_seconds = $now - $last_login_time;

    // Adjust threshold as needed (e.g., 15 minutes = 900 seconds)
    $online_threshold = 15 * 60;

    if ($diff_seconds < $online_threshold) {
        return '<span class="status-online">Online</span>';
    } elseif ($diff_seconds < (60 * 60)) { // Within the last hour
        return '<span class="status-away">Last seen: ' . floor($diff_seconds / 60) . 'm ago</span>';
    } elseif ($diff_seconds < (24 * 60 * 60)) { // Within the last day
        return '<span class="status-offline">Last seen: ' . floor($diff_seconds / 3600) . 'h ago</span>';
    } elseif ($diff_seconds < (2 * 24 * 60 * 60)) { // Yesterday
        return '<span class="status-offline">Last seen: Yesterday</span>';
    } else { // Older than yesterday
        return '<span class="status-offline">Last seen: ' . date('M j', $last_login_time) . '</span>';
    }
}

// Generate CSRF token for actions
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends - ConnectHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .friends-container {
            max-width: 900px; /* Adjust width as needed */
            margin: 2rem auto;
        }
        .friends-tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }
        .friends-tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            font-weight: 600;
            position: relative;
            border-bottom: 3px solid transparent;
            transition: all var(--transition-fast);
        }
        .friends-tab:hover {
            color: var(--text-primary);
        }
        .friends-tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        .friends-tab .badge { /* Simple badge for request count */
             background-color: var(--accent);
             color: white;
             font-size: 0.7rem;
             padding: 2px 6px;
             border-radius: 10px;
             margin-left: 5px;
             vertical-align: middle;
        }

        .friends-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); /* Responsive grid */
            gap: 1.5rem;
        }
        .friend-card-item { /* Use card style */
            /* Inherits .card styles */
            text-align: center;
        }
         .friend-card-item .card-body {
            padding: 1.5rem 1rem; /* Adjust padding */
         }

        .friend-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem auto;
            border: 3px solid var(--bg-tertiary);
        }
        .friend-name a {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-primary);
            text-decoration: none;
            display: block; /* Make link take full width */
            margin-bottom: 0.25rem;
        }
        .friend-name a:hover {
            color: var(--accent);
        }
        .friend-status {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            min-height: 1.2em; /* Prevent layout shift */
        }
        .status-online { color: var(--success); font-weight: 500; }
        .status-online::before { content: '● '; font-size: 0.8em; }
        .status-away { color: var(--warning); font-weight: 500; }
         .status-offline { color: var(--text-secondary); }

        .friend-actions {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1rem;
             padding-top: 1rem;
             border-top: 1px solid var(--border);
        }
         .friend-actions .btn-icon { /* Style action buttons */
             padding: 0.5rem;
             font-size: 0.9rem;
             width: auto; /* Allow text */
             height: auto;
             border-radius: 6px;
         }
         .friend-actions .btn-icon i { margin-right: 5px; }
         .btn-danger-outline { /* Style for unfriend */
            background-color: transparent;
            border-color: var(--error);
            color: var(--error);
         }
          .btn-danger-outline:hover {
              background-color: rgba(var(--error-rgb, 239, 68, 68), 0.1);
          }

        /* Friend Request Card */
        .friend-request-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
             background-color: var(--bg-secondary);
             border: 1px solid var(--border);
             border-radius: 8px;
        }
         .request-info { flex-grow: 1; }
         .request-time { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
         .request-actions { display: flex; gap: 0.5rem; }

        .empty-state { /* Reuse existing empty state */
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }
        .empty-state .empty-state-icon i {
             font-size: 3rem;
             margin-bottom: 1rem;
             color: var(--border);
        }
         .empty-state h3 {
             font-size: 1.3rem;
             color: var(--text-primary);
             margin-bottom: 0.5rem;
         }

        /* Hide content initially */
        .friends-content { display: none; }
        .friends-content.active { display: block; }

        /* CSS for friends.php empty state */

        .friends-content .empty-state {
            padding-top: 3rem;
            padding-bottom: 4rem; /* Increased bottom padding from 3rem */
            padding-left: 1rem;
            padding-right: 1rem;
            text-align: center;
            color: var(--text-secondary);
            display: flex; /* Use flexbox for better centering */
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 300px; /* Ensure it has some minimum height */
        }

        .friends-content .empty-state .empty-state-icon i {
             font-size: 3rem;
             margin-bottom: 1rem;
             color: var(--border); /* Use theme variable */
        }

        .friends-content .empty-state h3 {
             font-size: 1.3rem;
             color: var(--text-primary); /* Use theme variable */
             margin-bottom: 0.5rem;
         }

        .friends-content .empty-state p {
            margin-bottom: 1.5rem; /* Ensure space between text and button */
            max-width: 400px; /* Limit text width */
            line-height: 1.5;
        }

        .friends-content .empty-state .btn {
            margin-top: 0; /* Remove any default top margin */
        }

    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="page-container">
        <div class="friends-container">
            <h1>Friends</h1>

            <div class="friends-tabs">
                <div class="friends-tab active" data-tab="all-friends">All Friends (<?php echo count($friends); ?>)</div>
                <div class="friends-tab" data-tab="requests">
                    Friend Requests
                    <?php $request_count = count($requests_received); if ($request_count > 0): ?>
                        <span class="badge"><?php echo $request_count; ?></span>
                    <?php endif; ?>
                </div>
                 <div class="friends-tab" data-tab="sent-requests">Sent Requests (<?php echo count($requests_sent); ?>)</div>
                </div>

            <div id="tab-all-friends" class="friends-content active">
                <?php if (empty($friends)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-users-slash"></i></div>
                        <h3>No Friends Yet</h3>
                        <p>You haven't added any friends yet. Search for people you know!</p>
                        <a href="#" class="btn btn-primary" onclick="document.getElementById('search-input').focus(); return false;">Find Friends</a>
                    </div>
                <?php else: ?>
                    <div class="friends-grid">
                        <?php foreach ($friends as $friend): ?>
                            <div class="card friend-card-item">
                                <div class="card-body">
                                    <a href="profile.php?id=<?php echo $friend['id']; ?>">
                                        <img src="<?php echo getUserAvatar($friend['id'], 'large'); ?>" alt="<?php echo htmlspecialchars($friend['username']); ?>" class="friend-avatar-large">
                                    </a>
                                    <div class="friend-name">
                                        <a href="profile.php?id=<?php echo $friend['id']; ?>"><?php echo htmlspecialchars($friend['username']); ?></a>
                                    </div>
                                    <div class="friend-status">
                                        <?php echo get_last_seen_status($friend['last_login']); ?>
                                    </div>
                                     <div class="friend-actions">
                                         <a href="messages.php?user_id=<?php echo $friend['id']; ?>" class="btn btn-icon btn-secondary btn-sm" title="Message">
                                             <i class="fas fa-comment"></i> Message
                                         </a>
                                         <button class="btn btn-icon btn-danger-outline btn-sm unfriend-btn" data-user-id="<?php echo $friend['id']; ?>" title="Unfriend">
                                             <i class="fas fa-user-minus"></i> Unfriend
                                         </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="tab-requests" class="friends-content">
                 <h2>Friend Requests Received</h2>
                 <?php if (empty($requests_received)): ?>
                     <div class="empty-state">
                         <p>You have no pending friend requests.</p>
                     </div>
                 <?php else: ?>
                     <div class="requests-list" style="display: flex; flex-direction: column; gap: 1rem;">
                         <?php foreach ($requests_received as $request): ?>
                             <div class="friend-request-card">
                                 <a href="profile.php?id=<?php echo $request['id']; ?>">
                                     <img src="<?php echo getUserAvatar($request['id']); ?>" alt="<?php echo htmlspecialchars($request['username']); ?>" class="user-avatar">
                                 </a>
                                 <div class="request-info">
                                     <div class="friend-name">
                                         <a href="profile.php?id=<?php echo $request['id']; ?>"><?php echo htmlspecialchars($request['username']); ?></a>
                                     </div>
                                     <div class="request-time">Sent <?php echo formatTimeAgo($request['request_time']); ?></div>
                                 </div>
                                 <div class="request-actions">
                                     <button class="btn btn-primary btn-sm accept-request-btn" data-user-id="<?php echo $request['id']; ?>">Accept</button>
                                     <button class="btn btn-secondary btn-sm reject-request-btn" data-user-id="<?php echo $request['id']; ?>">Decline</button>
                                 </div>
                             </div>
                         <?php endforeach; ?>
                     </div>
                 <?php endif; ?>
            </div>

             <div id="tab-sent-requests" class="friends-content">
                 <h2>Friend Requests Sent</h2>
                 <?php if (empty($requests_sent)): ?>
                     <div class="empty-state">
                         <p>You have no pending sent requests.</p>
                     </div>
                 <?php else: ?>
                     <div class="requests-list" style="display: flex; flex-direction: column; gap: 1rem;">
                         <?php foreach ($requests_sent as $request): ?>
                             <div class="friend-request-card">
                                 <a href="profile.php?id=<?php echo $request['id']; ?>">
                                     <img src="<?php echo getUserAvatar($request['id']); ?>" alt="<?php echo htmlspecialchars($request['username']); ?>" class="user-avatar">
                                 </a>
                                 <div class="request-info">
                                     <div class="friend-name">
                                         <a href="profile.php?id=<?php echo $request['id']; ?>"><?php echo htmlspecialchars($request['username']); ?></a>
                                     </div>
                                     <div class="request-time">Sent <?php echo formatTimeAgo($request['request_time']); ?></div>
                                 </div>
                                 <div class="request-actions">
                                     <button class="btn btn-secondary btn-sm cancel-request-btn" data-user-id="<?php echo $request['id']; ?>">Cancel Request</button>
                                 </div>
                             </div>
                         <?php endforeach; ?>
                     </div>
                 <?php endif; ?>
            </div>

            </div>
    </div>

    <div id="notification-container" class="notification-container"></div>
    <input type="hidden" id="csrf_token" value="<?php echo $csrf_token; ?>">


    <script src="js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.friends-tab');
            const contents = document.querySelectorAll('.friends-content');
            const csrfToken = document.getElementById('csrf_token').value;

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');

                    // Update tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');

                    // Update content display
                    contents.forEach(content => {
                        if (content.id === `tab-${targetTab}`) {
                            content.classList.add('active');
                        } else {
                            content.classList.remove('active');
                        }
                    });
                });
            });

            // --- Friend Request Actions ---

            function handleFriendAction(button, action, userId) {
                const originalHtml = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; // Loading state
                button.disabled = true;

                fetchWithCSRF('ajax/friend_request.php', { // Assuming friend_request.php handles accept/reject/cancel/unfriend
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${action}&user_id=${userId}&csrf_token=${encodeURIComponent(csrfToken)}`
                })
                .then(data => {
                    if (data.success) {
                        showNotification(data.message || `${action} successful!`, 'success');
                        // Refresh the page or dynamically remove/update the item
                        // For simplicity, let's just reload after a short delay
                        setTimeout(() => { window.location.reload(); }, 1500);
                    } else {
                        showNotification(data.message || `Failed to ${action}.`, 'error');
                        button.innerHTML = originalHtml; // Restore button
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    showNotification(`An error occurred during ${action}.`, 'error');
                    console.error(`Error during ${action}:`, error);
                    button.innerHTML = originalHtml; // Restore button
                    button.disabled = false;
                });
            }

            // Accept Request
            document.querySelectorAll('.accept-request-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    handleFriendAction(this, 'accept', userId);
                });
            });

            // Reject Request
            document.querySelectorAll('.reject-request-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    handleFriendAction(this, 'reject', userId);
                });
            });

            // Cancel Sent Request
            document.querySelectorAll('.cancel-request-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    handleFriendAction(this, 'cancel', userId);
                });
            });

            // Unfriend
            document.querySelectorAll('.unfriend-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                     if (confirm('Are you sure you want to unfriend this user?')) {
                        handleFriendAction(this, 'unfriend', userId);
                     }
                });
            });

        });
    </script>
</body>
</html>