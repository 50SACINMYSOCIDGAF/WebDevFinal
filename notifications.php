<?php
session_start();
require_once 'functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id); // For navbar or other page elements

// Initial fetch of notifications for the page
// The js/notifications.js file will handle dynamic loading and display.
// We can still fetch an initial batch here to reduce initial JS load if desired,
// or let JS handle all fetching. For this version, we'll let JS handle it.
// $notifications = getNotifications($user_id, false, 20, 0); // Example: Fetch first 20
$unread_notification_count = countUnreadNotifications($user_id); // For the "Mark all read" button visibility

// Generate CSRF token for actions
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - ConnectHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Basic styling for notifications page - can be moved to styles.css */
        .notifications-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 1rem;
        }

        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .notifications-header h1 {
            margin: 0;
        }

        /* Styles for the notification list and items will be in styles.css or loaded by js/notifications.js */
        .notification-list {
            list-style: none;
            padding: 0;
        }

        .notification-item {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 1rem;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background-color 0.3s;
        }

        .notification-item.unread {
            background-color: var(--bg-tertiary); /* Slightly different for unread */
            border-left: 3px solid var(--accent);
        }

        .notification-item:hover {
            background-color: var(--bg-tertiary);
        }

        .notification-item a {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 1rem;
            width: 100%;
        }

        .notification-avatar img,
        .notification-avatar .fa-bell { /* Style for placeholder bell */
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
         .notification-avatar .fa-bell {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-tertiary); /* Optional: background for icon */
            color: var(--text-secondary);
         }


        .notification-content {
            flex-grow: 1;
        }

        .notification-message {
            margin: 0 0 0.25rem 0;
            color: var(--text-primary);
        }

        .notification-message .username { /* If you plan to bold usernames within the message */
            font-weight: 600;
        }

        .notification-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .empty-notifications, .loading-indicator { /* Styles for empty or loading states */
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }
        .empty-notifications i, .loading-indicator i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="page-container">
        <div class="notifications-container">
            <div class="notifications-header">
                <h1>Notifications</h1>
                <?php if ($unread_notification_count > 0): // Conditionally show based on initial count, JS will update/hide ?>
                    <button id="mark-all-read-btn" class="btn btn-secondary btn-sm">Mark all as read</button>
                <?php else: ?>
                    <button id="mark-all-read-btn" class="btn btn-secondary btn-sm" style="display: none;">Mark all as read</button>
                <?php endif; ?>
            </div>

            <ul class="notification-list" id="notification-list-ul">
                <li class="empty-notifications">
                    <i class="fas fa-spinner fa-spin"></i>
                    Loading notifications...
                </li>
            </ul>
            </div>
    </div>

    <div id="notification-container" class="notification-container"></div> <input type="hidden" id="csrf_token" value="<?php echo $csrf_token; ?>">

    <script src="js/main.js"></script>
    <script src="js/notifications.js"></script>
</body>
</html>