<?php
/**
 * Messages page
 * Shows conversations and message history between users
 */

session_start();
require_once 'config.php';
require_once 'functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get current user data
$user_id = $_SESSION['user_id'];
$currentUser = getUserById($user_id); // Use this if needed

// Get conversation partner ID if available
$partner_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$partner = null;
if ($partner_id > 0 && $partner_id !== $user_id) { // Prevent messaging self
    $partner = getUserById($partner_id);
    if (!$partner) { // Partner doesn't exist, redirect or show error
        header('Location: messages.php?error=User not found');
        exit;
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
error_log("messages.php page load - Session ID: " . session_id());
error_log("messages.php page load - CSRF Token embedded: " . ($csrf_token ?? 'NOT SET/EMPTY'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | ConnectHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .messages-page-container {
            padding-top: 4rem; /* Adjust based on navbar height */
            height: calc(100vh - 4rem); /* Full height below navbar */
            overflow: hidden; /* Prevent body scrolling */
        }
        .messages-wrapper {
            display: flex;
            height: 100%;
            background-color: var(--bg-primary); /* Use theme background */
            border: 1px solid var(--border);
            border-radius: 8px; /* Optional: rounded corners */
            margin: 1rem; /* Add some margin */
            box-shadow: var(--card-shadow);
        }

        /* Conversations Sidebar */
        .conversations-sidebar {
            width: 300px; /* Fixed width */
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            height: 100%;
            background-color: var(--bg-secondary); /* Slightly different bg */
        }
        .conversations-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .conversations-header h2 {
            font-size: 1.2rem;
            margin: 0 0 0.75rem 0;
        }
        #conversation-search { /* Use standard input styles */
            width: 100%;
        }
        .conversations-list {
            flex: 1; /* Take remaining height */
            overflow-y: auto; /* Scrollable */
        }
        .conversation-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            gap: 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
            transition: background-color var(--transition-fast);
        }
        .conversation-item:hover {
            background-color: var(--bg-tertiary);
        }
        .conversation-item.active {
            background-color: rgba(var(--accent-rgb, 79, 70, 229), 0.1); /* Use accent color */
            border-left: 3px solid var(--accent);
        }
        .conversation-avatar img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
        }
        .conversation-info {
            flex: 1;
            overflow: hidden; /* Prevent text overflow */
        }
        .conversation-info-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 0.25rem;
        }
        .conversation-name {
            font-weight: 600;
            font-size: 0.95rem;
            white-space: nowrap;
        }
        .conversation-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            white-space: nowrap;
        }
        .conversation-preview {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .conversation-preview p {
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .unread-badge {
            background-color: var(--accent);
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            margin-left: 5px;
        }

        /* Message Content Area */
        .messages-content {
            flex: 1; /* Take remaining width */
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .conversation-content-header { /* Renamed for clarity */
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background-color: var(--bg-secondary);
        }
        .conversation-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
         .user-avatar-medium { /* Defined class */
             width: 40px;
             height: 40px;
             border-radius: 50%;
         }
        .conversation-user-info h3 {
            font-size: 1.1rem;
            margin: 0 0 2px 0;
        }
        .user-status {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .user-status.active::before {
            content: '●';
            color: var(--success);
            margin-right: 4px;
            font-size: 0.7em;
        }
        .conversation-actions {
            display: flex;
            gap: 0.5rem;
        }
        .conversation-actions .btn-icon { /* Style action buttons */
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.1rem;
            padding: 0.5rem;
            border-radius: 50%;
            width: 36px;
            height: 36px;
        }
        .conversation-actions .btn-icon:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }

        /* Messages Thread */
        .messages-thread {
            flex: 1; /* Take available space */
            overflow-y: auto; /* Scrollable */
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .message {
            display: flex;
            gap: 0.75rem;
            max-width: 70%; /* Limit message width */
            align-items: flex-end; /* Align avatar with bottom of bubble */
        }
        .message-sent {
            margin-left: auto; /* Push sent messages to the right */
            flex-direction: row-reverse; /* Reverse order for sent */
        }
        .message-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            align-self: flex-end; /* Keep avatar at bottom */
        }
        .message-bubble {
            padding: 0.6rem 1rem;
            border-radius: 18px; /* Rounded bubbles */
            position: relative;
            word-wrap: break-word; /* Break long words */
        }
        .message-received .message-bubble {
            background-color: var(--bg-tertiary);
            border-bottom-left-radius: 4px; /* Pointy corner */
            color: var(--text-primary);
        }
        .message-sent .message-bubble {
            background-color: var(--accent);
            color: white;
            border-bottom-right-radius: 4px; /* Pointy corner */
        }
        .message-text {
             line-height: 1.4;
             font-size: 0.95rem;
        }
        .message-meta {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.6); /* Lighter text for timestamp */
            text-align: right;
            margin-top: 0.25rem;
        }
        .message-received .message-meta {
             color: var(--text-secondary);
             text-align: left;
        }
        .message-status {
            margin-left: 5px;
        }
        .message-date-separator {
             text-align: center;
             margin: 1rem 0;
             font-size: 0.8rem;
             color: var(--text-secondary);
        }
        .message-date-separator span {
             background-color: var(--bg-secondary);
             padding: 0.25rem 0.75rem;
             border-radius: 12px;
        }

        /* Message Input Area */
        .message-input-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            background-color: var(--bg-secondary);
        }
        .message-input-container .btn-icon { /* Style attachment/emoji buttons */
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.2rem;
            padding: 0.5rem;
        }
         .message-input-container .btn-icon:hover {
             color: var(--text-primary);
         }
        #message-input { /* Style the textarea */
            flex: 1;
            resize: none;
            border-radius: 20px;
            padding: 0.6rem 1rem;
            max-height: 100px; /* Limit height */
            min-height: 40px;
             line-height: 1.4;
             /* Use standard input styles from styles.css */
             background-color: var(--bg-tertiary);
             border: 1px solid var(--border);
             color: var(--text-primary);
             font-size: 0.95rem;
        }
         #message-input:focus {
             outline: none;
             border-color: var(--accent);
             box-shadow: 0 0 0 2px rgba(var(--accent-rgb, 79, 70, 229), 0.2);
         }
         #send-message { /* Style send button */
             background-color: var(--accent);
             color: white;
             border-radius: 50%;
             width: 40px;
             height: 40px;
             font-size: 1rem;
             padding: 0;
         }
         #send-message:disabled {
             opacity: 0.5;
             cursor: not-allowed;
             background-color: var(--bg-tertiary);
             color: var(--text-secondary);
         }
         #send-message:hover:not(:disabled) {
              background-color: var(--accent-hover);
         }


        /* Empty state */
        .no-conversation-selected {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--text-secondary);
        }
        .empty-state { /* Use existing styles if available */
             /* Add styles here or ensure they exist in styles.css */
        }
         .empty-state .empty-state-icon {
             font-size: 3rem;
             margin-bottom: 1rem;
             color: var(--border);
         }
         .empty-state h2 {
             font-size: 1.3rem;
             color: var(--text-primary);
             margin-bottom: 0.5rem;
         }
          .empty-state p {
              margin-bottom: 1.5rem;
          }

        /* Loading spinner */
        .loading-spinner {
             display: flex;
             flex-direction: column;
             align-items: center;
             justify-content: center;
             padding: 2rem;
             color: var(--text-secondary);
             font-size: 0.9rem;
         }
         .loading-spinner i {
             font-size: 1.5rem;
             margin-bottom: 0.5rem;
         }
         .error-message {
             padding: 1rem;
             text-align: center;
             color: var(--error);
         }

         /* Responsive */
         @media (max-width: 768px) {
             .conversations-sidebar {
                 width: 80px; /* Collapse sidebar */
             }
             .conversations-header h2,
             .conversation-search,
             .conversation-info,
             .conversation-time,
             .conversation-preview p {
                 display: none; /* Hide text elements */
             }
             .conversation-item {
                 flex-direction: column;
                 justify-content: center;
                 height: 70px; /* Fixed height */
                 padding: 0.5rem;
             }
             .conversation-avatar img {
                 width: 40px;
                 height: 40px;
             }
              .conversation-preview {
                  justify-content: center; /* Center badge */
              }
             .unread-badge { margin-left: 0; margin-top: 4px; }
         }
         @media (max-width: 576px) {
              .messages-wrapper { margin: 0; border-radius: 0; border: none; }
              .conversations-sidebar { display: none; /* Hide sidebar completely */ }
              .messages-page-container { padding-top: 3.5rem; height: calc(100vh - 3.5rem); } /* Adjust for potentially smaller navbar */
              .conversation-content-header { padding: 0.5rem 1rem; }
              .messages-thread { padding: 1rem; }
              .message-input-container { padding: 0.5rem 1rem; gap: 0.5rem; }
              #message-input { min-height: 36px; }
              #send-message { width: 36px; height: 36px; }
         }

    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="messages-page-container"> <div class="messages-wrapper">
            <div class="conversations-sidebar">
                <div class="conversations-header">
                    <h2>Messages</h2>
                    <input type="text" id="conversation-search" placeholder="Search messages..." class="form-control">
                </div>

                <div id="conversations-list" class="conversations-list">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Loading...</span>
                    </div>
                    </div>
            </div>

            <div class="messages-content">
                <?php if ($partner): ?>
                    <div class="conversation-content-header">
                        <a href="profile.php?id=<?php echo $partner['id']; ?>" class="conversation-user">
                            <img src="<?php echo getUserAvatar($partner['id'], 'medium'); ?>" alt="<?php echo htmlspecialchars($partner['username']); ?>" class="user-avatar-medium">
                            <div class="conversation-user-info">
                                <h3><?php echo htmlspecialchars($partner['username']); ?></h3>
                                <div class="user-status active">Active now</div>
                            </div>
                        </a>
                        <div class="conversation-actions">
                            <button class="btn-icon" title="Voice call">
                                <i class="fas fa-phone"></i>
                            </button>
                            <button class="btn-icon" title="Video call">
                                <i class="fas fa-video"></i>
                            </button>
                            <button class="btn-icon" title="User info">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        </div>
                    </div>

                    <div id="messages-thread" class="messages-thread">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading messages...</span>
                        </div>
                        </div>

                    <div class="message-input-container">
                        <button class="btn-icon" title="Attach file">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <textarea id="message-input" placeholder="Type a message..." rows="1"></textarea>
                        <button id="emoji-button" class="btn-icon" title="Add emoji">
                            <i class="far fa-smile"></i>
                        </button>
                        <button id="send-message" class="btn-icon" title="Send" disabled>
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="no-conversation-selected">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="far fa-comments"></i>
                            </div>
                            <h2>Your Messages</h2>
                            <p>Select a conversation or start a new one.</p>
                            <button id="new-message-btn" class="btn btn-primary">
                                <i class="fas fa-edit"></i> New Message
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="new-message-modal" class="modal-overlay" style="display: none;"> <div class="modal modal-small">
            <div class="modal-header">
                <h3 class="modal-title">New Message</h3>
                <button class="modal-close" onclick="closeModal('new-message-modal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="recipient-search">To:</label>
                    <input type="text" id="recipient-search" placeholder="Search for people..." class="form-control">
                </div>
                <div id="recipient-results" class="recipient-results" style="max-height: 250px; overflow-y: auto;">
                    </div>
            </div>
        </div>
    </div>

    <div id="notification-container" class="notification-container"></div>

    <input type="hidden" id="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" id="current-user-id" value="<?php echo $user_id; ?>">
    <?php if ($partner): ?>
    <input type="hidden" id="partner-id" value="<?php echo $partner['id']; ?>">
    <?php endif; ?>

    <script src="js/main.js"></script>
    <script src="js/messages.js"></script>
     <script>
         // Add auto-resize for textarea
         const textarea = document.getElementById('message-input');
         if (textarea) {
             textarea.addEventListener('input', function() {
                 this.style.height = 'auto'; // Reset height
                 this.style.height = (this.scrollHeight) + 'px'; // Set to scroll height
             });
         }

          // Handle new message button click
          const newMessageBtn = document.getElementById('new-message-btn');
          if(newMessageBtn) {
              newMessageBtn.addEventListener('click', () => {
                  openModal('new-message-modal');
                  // Optionally focus the search input
                  document.getElementById('recipient-search')?.focus();
              });
          }
     </script>
</body>
</html>