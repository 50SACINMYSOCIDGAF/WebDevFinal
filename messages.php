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
$currentUser = getUserById($user_id);

// Get conversation partner ID if available
$partner_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$partner = $partner_id > 0 ? getUserById($partner_id) : null;

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | ConnectHub</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Include the navbar -->
    <?php include 'components/navbar.php'; ?>
    
    <div class="container">
        <div class="messages-wrapper">
            <!-- Left sidebar - Conversations list -->
            <div class="conversations-sidebar">
                <div class="section-header">
                    <h2>Conversations</h2>
                    <input type="text" id="conversation-search" placeholder="Search messages...">
                </div>
                
                <div id="conversations-list" class="conversations-list">
                    <div class="loading-spinner">
                        <i class="fas fa-circle-notch fa-spin"></i>
                        <span>Loading conversations...</span>
                    </div>
                </div>
            </div>
            
            <!-- Main content - Message thread -->
            <div class="messages-content">
                <?php if ($partner): ?>
                    <!-- Conversation header -->
                    <div class="conversation-header">
                        <div class="conversation-user">
                            <img src="<?php echo getUserAvatar($partner['id']); ?>" alt="<?php echo $partner['username']; ?>" class="user-avatar-medium">
                            <div class="conversation-user-info">
                                <h3><?php echo $partner['username']; ?></h3>
                                <div class="user-status active">Active now</div>
                            </div>
                        </div>
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
                    
                    <!-- Messages thread -->
                    <div id="messages-thread" class="messages-thread">
                        <div class="loading-spinner">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <span>Loading messages...</span>
                        </div>
                    </div>
                    
                    <!-- Message input -->
                    <div class="message-input-container">
                        <button class="btn-icon" title="Attach file">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <textarea id="message-input" placeholder="Type a message..."></textarea>
                        <button id="emoji-button" class="btn-icon" title="Add emoji">
                            <i class="far fa-smile"></i>
                        </button>
                        <button id="send-message" class="btn-icon btn-primary" title="Send" disabled>
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
                            <p>Select a conversation or start a new one</p>
                            <button id="new-message-btn" class="btn btn-primary">
                                <i class="fas fa-edit"></i> New Message
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- New message modal -->
    <div id="new-message-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3>New Message</h3>
                <button class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="recipient-search">To:</label>
                    <input type="text" id="recipient-search" placeholder="Search for people...">
                </div>
                <div id="recipient-results" class="recipient-results"></div>
            </div>
        </div>
    </div>
    
    <!-- Notification container -->
    <div id="notification-container" class="notification-container"></div>
    
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" id="current-user-id" value="<?php echo $user_id; ?>">
    <?php if ($partner): ?>
    <input type="hidden" id="partner-id" value="<?php echo $partner['id']; ?>">
    <?php endif; ?>
    
    <script src="js/main.js"></script>
    <script src="js/messages.js"></script>
</body>
</html>